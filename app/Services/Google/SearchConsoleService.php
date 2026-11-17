<?php

namespace App\Services\Google;

use App\Models\IndexStatus;
use App\Models\SeoKeyword;
use App\Models\SeoRankHistory;
use App\Models\SeoSearchQuery;
use App\Support\AutomationLog;
use App\Support\PathNormalizer;
use App\Support\TurkishText;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Search Console performans, sıralama ve indeks katmanı (spec §3.7).
 */
class SearchConsoleService
{
    private const API = 'https://searchconsole.googleapis.com';

    public function __construct(protected GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * GSC searchAnalytics sorgusu.
     *
     * @param  array<int, string>  $dimensions
     * @return array<int, array<string, mixed>>
     */
    public function query(CarbonInterface $start, CarbonInterface $end, array $dimensions, int $rowLimit = 1000): array
    {
        $property = $this->client->property();
        $endpoint = self::API.'/webmasters/v3/sites/'.rawurlencode((string) $property).'/searchAnalytics/query';

        $response = $this->client->request()->post($endpoint, [
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'dimensions' => $dimensions,
            'rowLimit' => $rowLimit,
            'dataState' => 'final',
        ]);

        if ($response->status() === 403) {
            throw new RuntimeException($this->client->explainPermissionError(403, $response->json('error.message')));
        }

        if (! $response->successful()) {
            throw new RuntimeException('GSC sorgusu başarısız (HTTP '.$response->status().'): '.Str::limit((string) $response->json('error.message'), 200));
        }

        return $response->json('rows') ?? [];
    }

    /**
     * Performans senkronu: query ve page boyutları. row_hash ile tekilleştirilir (spec §3.7).
     *
     * @return array{queries: int, pages: int}
     */
    public function syncPerformance(int $days = 28): array
    {
        $end = Carbon::today()->subDays(3);   // GSC verisi ~3 gün gecikmeli
        $start = $end->copy()->subDays($days);

        $counts = ['queries' => 0, 'pages' => 0];

        foreach (['query' => 'queries', 'page' => 'pages'] as $dimension => $bucket) {
            foreach ($this->query($start, $end, [$dimension], 5000) as $row) {
                $key = $row['keys'][0] ?? null;

                if (! is_string($key) || $key === '') {
                    continue;
                }

                $isPage = $dimension === 'page';
                $path = $isPage ? PathNormalizer::normalize($key) : null;

                $hash = md5(implode('|', [$dimension, $isPage ? $path : $key, $start->toDateString(), $end->toDateString()]));

                SeoSearchQuery::updateOrCreate(
                    ['row_hash' => $hash],
                    [
                        'dimension' => $dimension,
                        'query' => $isPage ? null : Str::limit($key, 490, ''),
                        'path' => $path ? Str::limit($path, 490, '') : null,
                        'clicks' => (int) ($row['clicks'] ?? 0),
                        'impressions' => (int) ($row['impressions'] ?? 0),
                        'ctr' => (float) ($row['ctr'] ?? 0),
                        'position' => (float) ($row['position'] ?? 0),
                        'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(),
                    ],
                );

                $counts[$bucket]++;
            }
        }

        return $counts;
    }

    /**
     * Takip edilen kelimeler için günlük sıralama (spec §3.7).
     * ranking_url beklenen hedefle karşılaştırılır → target_match.
     *
     * @return array{tracked: int, matched: int, mismatched: int}
     */
    public function syncRankings(): array
    {
        $date = Carbon::today()->subDays(3);
        // Fırsat avındaki kelimeler 28 günlük pencereyle ayrıca izlenir (OpportunityHunter);
        // burada da yazılsalar aynı geçmiş satırının üzerine tek günlük veri yazılırdı.
        $keywords = SeoKeyword::query()->where('status', true)->whereNull('campaign_status')->with('target')->get();

        if ($keywords->isEmpty()) {
            return ['tracked' => 0, 'matched' => 0, 'mismatched' => 0];
        }

        $rows = $this->query($date, $date, ['query', 'page'], 5000);

        // kelime → en çok gösterim alan satır
        $best = [];

        foreach ($rows as $row) {
            // TurkishText::lower: mb_strtolower("İ") arkada birleşik nokta bırakır ve eşleşme kaçar (spec §7.5).
            $query = TurkishText::lower((string) ($row['keys'][0] ?? ''));
            $page = (string) ($row['keys'][1] ?? '');

            if ($query === '') {
                continue;
            }

            if (! isset($best[$query]) || ($row['impressions'] ?? 0) > $best[$query]['impressions']) {
                $best[$query] = [
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                    'page' => $page,
                ];
            }
        }

        $tracked = $matched = $mismatched = 0;

        foreach ($keywords as $keyword) {
            $row = $best[TurkishText::lower($keyword->keyword)] ?? null;

            if (! $row) {
                continue;
            }

            $expected = $keyword->expectedUrl();
            $actual = PathNormalizer::normalize($row['page']);
            $targetMatch = $expected ? (PathNormalizer::normalize($expected) === $actual) : null;

            // whereDate ile aranır: "date" cast'i SQLite'ta saati de yazar ve updateOrCreate
            // aynı günün satırını bulamayıp aynı gün ikinci çalıştırmada unique hatası verir.
            $history = SeoRankHistory::query()
                ->where('keyword_id', $keyword->getKey())
                ->whereDate('data_date', $date->toDateString())
                ->first() ?? new SeoRankHistory(['keyword_id' => $keyword->getKey(), 'data_date' => $date->toDateString()]);

            $history->fill([
                'position_avg' => $row['position'],
                'clicks' => $row['clicks'],
                'impressions' => $row['impressions'],
                'ranking_url' => Str::limit($row['page'], 490, ''),
                'target_match' => $targetMatch,
            ])->save();

            $tracked++;
            $targetMatch === false ? $mismatched++ : ($targetMatch === true ? $matched++ : null);
        }

        return ['tracked' => $tracked, 'matched' => $matched, 'mismatched' => $mismatched];
    }

    /**
     * URL Inspection API — sayfa indekslenmiş mi (spec §3.7).
     * "Tam" yetki yeterlidir; "Sahip" gerekmez.
     *
     * @return array{verdict: string, detail: string}
     */
    public function inspect(string $url): array
    {
        $response = $this->client->request()->post(self::API.'/v1/urlInspection/index:inspect', [
            'inspectionUrl' => $url,
            'siteUrl' => $this->client->property(),
        ]);

        if ($response->status() === 403) {
            throw new RuntimeException($this->client->explainPermissionError(403, $response->json('error.message')));
        }

        if (! $response->successful()) {
            throw new RuntimeException('URL Inspection başarısız (HTTP '.$response->status().')');
        }

        $result = $response->json('inspectionResult.indexStatusResult') ?? [];

        return [
            'verdict' => (string) ($result['verdict'] ?? 'UNKNOWN'),
            'detail' => trim(implode(' · ', array_filter([
                $result['coverageState'] ?? null,
                $result['robotsTxtState'] ?? null,
                $result['indexingState'] ?? null,
            ]))),
        ];
    }

    /** URL Inspection API: mülk başına günde 2000, dakikada 600 sorgu. */
    public const INSPECT_DAILY_QUOTA = 2000;

    /** Bugün kalan sorgu hakkı (elle basılan düğme + gece görevi aynı sayaçtan düşer). */
    public function inspectionsLeftToday(): int
    {
        return max(0, self::INSPECT_DAILY_QUOTA - (int) Cache::get($this->inspectCounterKey(), 0));
    }

    /** Son bu kadar saat içinde kontrol edilen adres yeniden sorulmaz (kota boşa gitmesin). */
    public const RECHECK_AFTER_HOURS = 20;

    /**
     * Site haritasındaki adresleri (hiç bakılmamış / en eski kontrol edilen önce) Google'a sorar.
     *
     * $seconds: süre bütçesi. Kuyruk işi 900 sn'de öldürülür; çok adresli harita tek turda sığmayabilir.
     * Bütçe dolunca durur, kalan adresler sonraki turda (saatlik görev) sorulur.
     *
     * @return array{checked: int, indexed: int, errors: int, tracked: int, pending: int, quota_left: int}
     */
    public function syncIndexStatus(int $limit = self::INSPECT_DAILY_QUOTA, int $seconds = 0): array
    {
        $tracked = IndexStatus::syncTracked();
        $limit = min($limit, $this->inspectionsLeftToday());
        $deadline = $seconds !== 0 ? microtime(true) + $seconds : null;

        $rows = $limit < 1 ? collect() : $this->dueForCheck()
            ->orderByRaw('index_checked_at IS NULL DESC')
            ->orderBy('index_checked_at')
            ->limit($limit)
            ->get();

        $checked = $indexed = $errors = 0;

        foreach ($rows as $row) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }

            $started = microtime(true);

            try {
                $this->countInspection();
                $result = $this->inspect($row->url);

                $row->mirror([
                    'google_index_status' => $result['verdict'],
                    'google_index_detail' => $result['detail'],
                    'index_checked_at' => now(),
                ]);

                $checked++;
                $result['verdict'] === 'PASS' && $indexed++;
            } catch (\Throwable $e) {
                $errors++;
                AutomationLog::error('gsc.inspect', $e->getMessage(), ['url' => $row->url]);
                break; // Yetki ya da kota hatasıysa kalan çağrılar da düşer; kotayı harcama.
            }

            // Dakikada 600 sınırının altında kal: çağrı 110 ms'den kısa sürdüyse kalanı bekle.
            $spent = (int) ((microtime(true) - $started) * 1_000_000);
            app()->runningUnitTests() || $spent >= 110_000 || usleep(110_000 - $spent);
        }

        return [
            'checked' => $checked, 'indexed' => $indexed, 'errors' => $errors, 'tracked' => $tracked,
            'pending' => $this->dueForCheck()->count(), 'quota_left' => $this->inspectionsLeftToday(),
        ];
    }

    private function dueForCheck(): Builder
    {
        return IndexStatus::query()->where(fn ($q) => $q->whereNull('index_checked_at')
            ->orWhere('index_checked_at', '<', now()->subHours(self::RECHECK_AFTER_HOURS)));
    }

    private function countInspection(): void
    {
        $key = $this->inspectCounterKey();
        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key);
    }

    /** Google kotayı Pasifik saatine göre sıfırlar. */
    private function inspectCounterKey(): string
    {
        return 'gsc.inspect.used.'.now('America/Los_Angeles')->toDateString();
    }
}
