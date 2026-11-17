<?php

namespace App\Services\Google;

use App\Models\IndexStatus;
use App\Models\SeoAnalysis;
use App\Support\AutomationLog;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use Throwable;

/**
 * Google Indexing API: dizine girmemiş sayfaları Google'a bildirir.
 *
 * DİKKAT — Google bu API'yi resmî olarak yalnız iş ilanı (JobPosting) ve canlı yayın
 * (BroadcastEvent) sayfaları için açtı; diğer sayfalar için gönderilen bildirimleri
 * dikkate almayabileceğini söylüyor. Site sahibi bunu bilerek kullanmayı seçti.
 * Asıl dizine girme yolu site haritası ve Search Console'daki elle istek olmaya devam eder.
 *
 * Kurallar:
 * - Günde en fazla GUNLUK_KOTA adres (Google'ın kotası mülk başına 200/gün).
 * - Aynı adres BEKLEME_GUNU geçmeden tekrar gönderilmez; tekrar tekrar bildirmek
 *   dizine girmeyi hızlandırmaz, yalnız kotayı yakar.
 * - Yalnız dizinde OLMAYAN sayfalar gönderilir; "İndekslendi" olanlara dokunulmaz.
 */
class IndexingService
{
    private const API = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

    /** Salt okunur: bildirim göndermeden API'nin açık ve yetkinin tam olduğunu sınar. */
    private const METADATA = 'https://indexing.googleapis.com/v3/urlNotifications/metadata';

    private const SERVICE_USAGE = 'https://serviceusage.googleapis.com/v1/projects/%s/services/indexing.googleapis.com:enable';

    /** Google'ın mülk başına günlük bildirim kotası. */
    public const GUNLUK_KOTA = 200;

    /** Aynı adres bu kadar gün geçmeden tekrar gönderilmez. */
    public const BEKLEME_GUNU = 14;

    public function __construct(protected GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /** Bugün kaç adres gönderilebilir? */
    public function remainingToday(): int
    {
        $bugun = IndexStatus::query()
            ->where('index_submitted_at', '>=', now()->subDay())
            ->count();

        return max(0, self::GUNLUK_KOTA - $bugun);
    }

    /**
     * Gönderim sırası: önce Google'ın "dizinde değil" dediği sayfalar, sonra
     * durumu belirsiz olanlar, en sonda hiç kontrol edilmemişler.
     *
     * @return Collection<int, IndexStatus>
     */
    public function candidates(int $limit): Collection
    {
        return IndexStatus::query()
            ->where(function ($query) {
                $query->whereNull('google_index_status')
                    ->orWhere('google_index_status', '!=', 'PASS');
            })
            ->where(function ($query) {
                $query->whereNull('index_submitted_at')
                    ->orWhere('index_submitted_at', '<', now()->subDays(self::BEKLEME_GUNU));
            })
            ->orderByRaw("CASE google_index_status
                WHEN 'FAIL' THEN 1
                WHEN 'PARTIAL' THEN 2
                WHEN 'NEUTRAL' THEN 3
                WHEN 'UNKNOWN' THEN 4
                ELSE 5 END")
            ->orderByRaw('index_submitted_at IS NULL DESC')
            ->orderBy('index_submitted_at')
            ->limit($limit)
            ->get();
    }

    /** Tek bir adresi bildirir. Başarısızsa istisna fırlatır. */
    public function submit(string $url): void
    {
        $response = $this->client->request(GoogleClient::SCOPE_INDEXING)->post(self::API, [
            'url' => $url,
            'type' => 'URL_UPDATED',
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                $this->client->explainPermissionError($response->status(), (string) $response->json('error.message'))
            );
        }
    }

    /**
     * Dizine girmemiş sayfaları sırayla bildirir.
     *
     * @return array{sent: int, failed: int, remaining: int, skipped: string|null}
     */
    public function submitPending(?int $limit = null): array
    {
        IndexStatus::syncTracked();
        $kalan = $this->remainingToday();
        $limit = min($limit ?? self::GUNLUK_KOTA, $kalan);

        if ($limit < 1) {
            return ['sent' => 0, 'failed' => 0, 'remaining' => 0, 'skipped' => 'quota'];
        }

        $sent = $failed = 0;

        foreach ($this->candidates($limit) as $analysis) {
            try {
                $this->submit((string) $analysis->url);

                $analysis->mirror([
                    'index_submitted_at' => now(),
                    'index_submit_count' => $analysis->index_submit_count + 1,
                    'index_submit_error' => null,
                ]);

                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $analysis->mirror(['index_submit_error' => mb_substr($e->getMessage(), 0, 300)]);
                AutomationLog::error('gsc.indexing', $e->getMessage(), ['url' => $analysis->url]);

                // Yetki ya da kota hatasıysa kalan adresler de düşer; kotayı harcama.
                break;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'remaining' => $this->remainingToday(), 'skipped' => null];
    }

    /**
     * "Bildirimi onar" düğmesi: Indexing API'yi bildirim göndermeden sınar, düzeltilebilecek
     * olanı düzeltir, düzeltilemeyeni (sahiplik gibi) açıkça söyler.
     *
     * - API kapalıysa Service Usage ile açmayı dener. Servis hesabının projede "Düzenleyici"
     *   ya da "Service Usage Admin" rolü yoksa açamaz; o zaman bağlantıyı verir.
     * - Erişim tamsa, son denemesi hatalı kalan sayfaların hatasını siler ki "Bildirim hatalı"
     *   filtresi eski hatayı göstermesin; gönderimi çağıran taraf kuyruğa alır.
     *
     * @return array{status: 'ok'|'enabled'|'manual', message: string, cleared: int}
     */
    public function repair(): array
    {
        $probe = $this->client->request(GoogleClient::SCOPE_INDEXING)
            ->get(self::METADATA, ['url' => rtrim((string) config('app.url'), '/').'/']);

        // 404 = bu adres hiç bildirilmemiş; API açık ve erişim var demektir.
        if ($probe->successful() || $probe->status() === 404) {
            IndexStatus::syncTracked();
            $cleared = IndexStatus::query()->whereNotNull('index_submit_error')->update(['index_submit_error' => null]);
            SeoAnalysis::query()->whereNotNull('index_submit_error')->update(['index_submit_error' => null]);

            return ['status' => 'ok', 'message' => 'Indexing API erişimi çalışıyor.', 'cleared' => $cleared];
        }

        $message = (string) $probe->json('error.message');

        if ($probe->status() !== 403 || ! $this->isServiceDisabled($message)) {
            return ['status' => 'manual', 'message' => $this->client->explainPermissionError($probe->status(), $message), 'cleared' => 0];
        }

        // Proje numarası Google'ın mesajında yazar; yoksa kimlik dosyasındaki proje kimliği.
        $project = preg_match('/project (\d+)/', $message, $m) ? $m[1] : $this->client->projectId();

        if (blank($project)) {
            return ['status' => 'manual', 'message' => $this->client->explainPermissionError(403, $message), 'cleared' => 0];
        }

        try {
            $enable = $this->client->request(GoogleClient::SCOPE_CLOUD)->post(sprintf(self::SERVICE_USAGE, $project));
        } catch (Throwable $e) {
            $enable = null;
            AutomationLog::error('gsc.indexing', 'API açılamadı: '.$e->getMessage());
        }

        if ($enable?->successful()) {
            AutomationLog::summary('gsc.indexing.repair', ['enabled' => true, 'project' => $project]);

            return [
                'status' => 'enabled',
                'message' => 'Web Search Indexing API Google Cloud projesinde açıldı. Google\'ın bunu yayması birkaç dakika sürer; '
                    .'5 dakika sonra bu düğmeye yeniden basın.',
                'cleared' => 0,
            ];
        }

        return [
            'status' => 'manual',
            'message' => $this->client->explainPermissionError(403, $message)
                .' (Otomatik açma denendi ama servis hesabının projede API açma yetkisi yok; bir kez elle açmanız gerekiyor.)',
            'cleared' => 0,
        ];
    }

    private function isServiceDisabled(string $message): bool
    {
        return str_contains($message, 'has not been used') || str_contains($message, 'is disabled');
    }
}
