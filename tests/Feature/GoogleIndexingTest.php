<?php

namespace Tests\Feature;

use App\Models\SeoAnalysis;
use App\Services\Google\GoogleClient;
use App\Services\Google\IndexingService;
use App\Support\Console\CommandCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Google Indexing API ile dizine ekleme bildirimi.
 *
 * Google'ın kotası mülk başına günde 200 adres. Aynı adresi tekrar tekrar bildirmek
 * dizine girmeyi hızlandırmaz, yalnız kotayı yakar; bu yüzden bekleme süresi vardır.
 */
class GoogleIndexingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ağa çıkılmaz: kimlik doğrulama sahte, istekler Http::fake ile karşılanır.
        $this->app->instance(GoogleClient::class, new class extends GoogleClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function property(): ?string
            {
                return 'sc-domain:ornek.test';
            }

            public function token(string $scope = self::SCOPE_WEBMASTERS): string
            {
                return 'sahte-anahtar';
            }
        });
    }

    private function analiz(string $url, ?string $durum, array $fazlasi = []): SeoAnalysis
    {
        static $id = 0;

        return SeoAnalysis::create([
            'content_type' => 'page', 'content_id' => ++$id, 'url' => $url, 'score' => 70,
            'google_index_status' => $durum, 'analyzed_at' => now(),
        ] + $fazlasi);
    }

    /**
     * Canlıda yaşandı: Google Cloud projesinde Indexing API kapalıydı. Mesaj "sahip değilsiniz"
     * dediği için Sahip yetkisi verildi ama hata sürdü; Google'ın asıl mesajı atılıyordu.
     */
    public function test_disabled_api_is_reported_as_such_not_as_missing_ownership(): void
    {
        Http::fake(['indexing.googleapis.com/*' => Http::response(['error' => [
            'message' => 'Web Search Indexing API has not been used in project 447048394966 before or it is disabled. Enable it by visiting https://console.developers.google.com/apis/api/indexing.googleapis.com/overview?project=447048394966 then retry.',
        ]], 403)]);

        $analiz = $this->analiz('/bir', 'FAIL');
        app(IndexingService::class)->submitPending();

        $hata = (string) $analiz->fresh()->index_submit_error;
        $this->assertStringContainsString('API etkin değil', $hata);
        $this->assertStringContainsString('indexing.googleapis.com/overview?project=447048394966', $hata);
        $this->assertStringNotContainsString('sahip değilsiniz', $hata);
    }

    public function test_ownership_error_keeps_googles_own_message(): void
    {
        Http::fake(['indexing.googleapis.com/*' => Http::response(['error' => ['message' => 'Permission denied. Failed to verify the URL ownership.']], 403)]);

        $analiz = $this->analiz('/bir', 'FAIL');
        app(IndexingService::class)->submitPending();

        $this->assertStringContainsString('Failed to verify the URL ownership', (string) $analiz->fresh()->index_submit_error);
    }

    public function test_only_pages_missing_from_the_index_are_submitted(): void
    {
        Http::fake(['indexing.googleapis.com/*' => Http::response(['urlNotificationMetadata' => []], 200)]);

        $dizinde = $this->analiz('/dizinde', 'PASS');
        $degil = $this->analiz('/dizinde-degil', 'FAIL');
        $bilinmiyor = $this->analiz('/hic-bakilmadi', null);

        $sonuc = app(IndexingService::class)->submitPending();

        $this->assertSame(2, $sonuc['sent']);
        $this->assertNull($dizinde->fresh()->index_submitted_at, 'Dizindeki sayfa bildirilmemeli.');
        $this->assertNotNull($degil->fresh()->index_submitted_at);
        $this->assertNotNull($bilinmiyor->fresh()->index_submitted_at);

        // Google'a giden gövde doğru olmalı.
        Http::assertSent(fn ($istek) => $istek['type'] === 'URL_UPDATED' && $istek['url'] === '/dizinde-degil');
    }

    public function test_the_same_page_is_not_submitted_again_within_the_waiting_period(): void
    {
        Http::fake(['indexing.googleapis.com/*' => Http::response([], 200)]);

        $yeni = $this->analiz('/dun-gonderildi', 'FAIL', ['index_submitted_at' => now()->subDay()]);
        $eski = $this->analiz('/cok-once-gonderildi', 'FAIL', ['index_submitted_at' => now()->subDays(20)]);

        $sonuc = app(IndexingService::class)->submitPending();

        $this->assertSame(1, $sonuc['sent']);
        $this->assertTrue($eski->fresh()->index_submitted_at->isToday());
        $this->assertTrue($yeni->fresh()->index_submitted_at->isYesterday(), 'Bekleme süresi dolmadan tekrar gönderilmemeli.');
    }

    public function test_daily_quota_is_respected(): void
    {
        Http::fake(['indexing.googleapis.com/*' => Http::response([], 200)]);

        // Kota bugün zaten dolmuş.
        for ($i = 0; $i < IndexingService::GUNLUK_KOTA; $i++) {
            $this->analiz('/gonderildi-'.$i, 'PASS', ['index_submitted_at' => now()->subHours(2)]);
        }
        $bekleyen = $this->analiz('/sirada', 'FAIL');

        $sonuc = app(IndexingService::class)->submitPending();

        $this->assertSame(0, $sonuc['sent']);
        $this->assertSame('quota', $sonuc['skipped']);
        $this->assertNull($bekleyen->fresh()->index_submitted_at);
        Http::assertNothingSent();
    }

    public function test_a_failed_submission_is_recorded_and_stops_the_run(): void
    {
        // 403: servis hesabı Search Console'da "sahip" değil. Kalan adresleri denemek kotayı yakar.
        Http::fake(['indexing.googleapis.com/*' => Http::response(['error' => ['message' => 'Permission denied']], 403)]);

        $ilk = $this->analiz('/bir', 'FAIL');
        $ikinci = $this->analiz('/iki', 'FAIL');

        $sonuc = app(IndexingService::class)->submitPending();

        $this->assertSame(0, $sonuc['sent']);
        $this->assertSame(1, $sonuc['failed']);
        $this->assertStringContainsString('403', (string) $ilk->fresh()->index_submit_error);
        $this->assertNull($ikinci->fresh()->index_submit_error, 'İlk hatadan sonra durmalı.');
        Http::assertSentCount(1);
    }

    public function test_repair_enables_a_disabled_api(): void
    {
        Http::fake([
            'indexing.googleapis.com/*' => Http::response(['error' => [
                'message' => 'Web Search Indexing API has not been used in project 447048394966 before or it is disabled.',
            ]], 403),
            'serviceusage.googleapis.com/*' => Http::response(['name' => 'operations/x', 'done' => true], 200),
        ]);

        $sonuc = app(IndexingService::class)->repair();

        $this->assertSame('enabled', $sonuc['status']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'projects/447048394966/services/indexing.googleapis.com:enable'));
    }

    public function test_repair_falls_back_to_manual_when_it_cannot_enable(): void
    {
        Http::fake([
            'indexing.googleapis.com/*' => Http::response(['error' => [
                'message' => 'Web Search Indexing API has not been used in project 447048394966 before or it is disabled. Enable it by visiting https://console.developers.google.com/apis/api/indexing.googleapis.com/overview?project=447048394966 then retry.',
            ]], 403),
            'serviceusage.googleapis.com/*' => Http::response(['error' => ['message' => 'Permission denied']], 403),
        ]);

        $sonuc = app(IndexingService::class)->repair();

        $this->assertSame('manual', $sonuc['status']);
        $this->assertStringContainsString('console.developers.google.com', $sonuc['message']);
    }

    public function test_repair_reports_ownership_without_trying_to_enable(): void
    {
        Http::fake(['indexing.googleapis.com/*' => Http::response(['error' => ['message' => 'Permission denied. Failed to verify the URL ownership.']], 403)]);

        $sonuc = app(IndexingService::class)->repair();

        $this->assertSame('manual', $sonuc['status']);
        $this->assertStringContainsString('Failed to verify the URL ownership', $sonuc['message']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'serviceusage'));
    }

    public function test_repair_clears_stale_errors_when_access_works(): void
    {
        // 404: adres hiç bildirilmemiş — API açık ve erişim var.
        Http::fake(['indexing.googleapis.com/*' => Http::response(['error' => ['message' => 'Requested entity was not found.']], 404)]);
        $eski = $this->analiz('/bir', 'FAIL', ['index_submit_error' => 'Google erişimi reddetti (403)']);

        $sonuc = app(IndexingService::class)->repair();

        $this->assertSame('ok', $sonuc['status']);
        $this->assertSame(1, $sonuc['cleared']);
        $this->assertNull($eski->fresh()->index_submit_error);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), ':publish'));
    }

    public function test_command_exists_in_the_panel_catalog(): void
    {
        $this->assertSame('seo:submit-index', CommandCatalog::find('submit-index')['command']);
    }

    public function test_index_check_covers_every_page_in_the_system(): void
    {
        Http::fake(['searchconsole.googleapis.com/*' => Http::response([
            'inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed']],
        ], 200)]);

        foreach (range(1, 45) as $i) {
            $this->analiz('/sayfa-'.$i, null);
        }

        // Limit verilmez: sistemdeki adres sayısı kadar kontrol edilmeli (eskiden 30 ile sınırlıydı).
        $this->artisan('seo:check-index')->assertSuccessful();

        $this->assertSame(45, SeoAnalysis::whereNotNull('index_checked_at')->count());
    }
}
