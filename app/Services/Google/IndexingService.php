<?php

namespace App\Services\Google;

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
        $bugun = SeoAnalysis::query()
            ->where('index_submitted_at', '>=', now()->subDay())
            ->count();

        return max(0, self::GUNLUK_KOTA - $bugun);
    }

    /**
     * Gönderim sırası: önce Google'ın "dizinde değil" dediği sayfalar, sonra
     * durumu belirsiz olanlar, en sonda hiç kontrol edilmemişler.
     *
     * @return Collection<int, SeoAnalysis>
     */
    public function candidates(int $limit): Collection
    {
        return SeoAnalysis::query()
            ->whereNotNull('url')
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
        $kalan = $this->remainingToday();
        $limit = min($limit ?? self::GUNLUK_KOTA, $kalan);

        if ($limit < 1) {
            return ['sent' => 0, 'failed' => 0, 'remaining' => 0, 'skipped' => 'quota'];
        }

        $sent = $failed = 0;

        foreach ($this->candidates($limit) as $analysis) {
            try {
                $this->submit((string) $analysis->url);

                $analysis->forceFill([
                    'index_submitted_at' => now(),
                    'index_submit_count' => $analysis->index_submit_count + 1,
                    'index_submit_error' => null,
                ])->saveQuietly();

                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $analysis->forceFill(['index_submit_error' => mb_substr($e->getMessage(), 0, 300)])->saveQuietly();
                AutomationLog::error('gsc.indexing', $e->getMessage(), ['url' => $analysis->url]);

                // Yetki ya da kota hatasıysa kalan adresler de düşer; kotayı harcama.
                break;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'remaining' => $this->remainingToday(), 'skipped' => null];
    }
}
