<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\InternalLinkRule;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordAction;
use App\Models\SeoTarget;
use App\Models\User;
use App\Services\Ai\RegionContentEnricher;
use App\Services\Google\GoogleClient;
use App\Services\Seo\KeywordSections;
use App\Services\Seo\OpportunityHunter;
use App\Services\Seo\RankBooster;
use App\Services\Seo\TargetSynchroniser;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OpportunityHuntTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Ağa çıkılmaz: Google kimliği sahte, istekler Http::fake ile karşılanır.
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

        config([
            'seo.ai.api_key' => 'test-key',
            'seo.ai.base_url' => 'https://openrouter.ai/api/v1',
            'seo.ai.model' => 'test/model',
        ]);

        app(TargetSynchroniser::class)->sync();
    }

    /**
     * Search Console + AI sahte yanıtları.
     *
     * @param  array<int, array{0: string, 1: float, 2: int, 3?: string}>  $rows  [sorgu, sıra, gösterim, sayfa]
     */
    /** Search Console'un o anki yanıtı; turlar arasında değiştirilebilir. */
    private array $gscRows = [];

    private bool $faked = false;

    private function fake(array $rows, array $ai = []): void
    {
        // Http::fake ikinci kez çağrılınca ilk kayıt geçerli kalır; yalnız veri değişir.
        $this->gscRows = $rows;

        if ($this->faked) {
            return;
        }

        $this->faked = true;

        $ai += [
            'meta_title' => 'Çerkezköy Mobilya Montajı | Hızlı Kurulum',
            'meta_description' => 'Çerkezköy mobilya montaj hizmeti: fotoğrafla hızlı fiyat, aynı hafta randevu. WhatsApp\'tan teklif alın, ekibimiz evinize gelsin.',
            'html' => '<h2>Çerkezköy mobilya montaj hizmeti</h2><p>'.str_repeat('Cumhuriyet ve Fevzipaşa mahallelerindeki sitelerde gardırop ve yatak odası montajı yapıyoruz. ', 12).'</p>',
            'faqs' => [['question' => 'Çerkezköy mobilya montaj için nasıl randevu alırım?', 'answer' => 'Kurulacak ürünün fotoğrafını WhatsApp\'tan gönderin; fiyatı ve uygun günü işe başlamadan önce net olarak bildiririz.']],
            'body_html' => '<p>'.str_repeat('Yeni metin kelimesi ', 220).'</p>',
        ];

        Http::fake([
            'searchconsole.googleapis.com/*' => function (Request $request) {
                $rows = $this->gscRows;
                $withPage = in_array('page', $request['dimensions'] ?? [], true);

                return Http::response(['rows' => array_map(fn ($r) => [
                    'keys' => $withPage ? [$r[0], 'https://ornek.test'.($r[3] ?? '/tekirdag/cerkezkoy')] : [$r[0]],
                    'clicks' => 1, 'impressions' => $r[2], 'ctr' => 1 / max(1, $r[2]), 'position' => $r[1],
                ], $rows)]);
            },
            'indexing.googleapis.com/*' => Http::response(['urlNotificationMetadata' => []]),
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($ai, JSON_UNESCAPED_UNICODE)]]]]),
        ]);
    }

    private function keyword(string $text): ?SeoKeyword
    {
        return SeoKeyword::query()->get()->first(fn ($k) => $k->keyword === $text);
    }

    private function cerkezkoyTarget(): SeoTarget
    {
        return SeoTarget::query()->where('url_hash', PathNormalizer::hash('/tekirdag/cerkezkoy'))->firstOrFail();
    }

    // ---------- eşleştirme ----------

    public function test_turkish_and_ascii_spellings_match_the_same_region(): void
    {
        $hunter = app(OpportunityHunter::class);

        $this->assertSame('Çerkezköy', $hunter->matchRegion('cerkezkoy mobilya montaj')['name']);
        $this->assertSame('Çerkezköy', $hunter->matchRegion('ÇERKEZKÖY MOBİLYA MONTAJ')['name']);
        // İlçe ilden önce gelir.
        $this->assertSame('Çerkezköy', $hunter->matchRegion('tekirdağ çerkezköy montaj')['name']);
        // Kendi sayfası olmayan Kızılpınar Çerkezköy sayfasına bağlanır.
        $this->assertSame('/tekirdag/cerkezkoy', $hunter->matchRegion('kizilpinar mobilya montaj')['url']);
        $this->assertNull($hunter->matchRegion('mobilya montaj ustası'));

        // Bitişik hâl ekleri (canlı veride "çorluda ikea varmı" eşleşmiyordu).
        $this->assertSame('Çerkezköy', $hunter->matchRegion('çerkezköyde mobilya montajcı')['name']);
        $this->assertSame('Kapaklı', $hunter->matchRegion("kapaklı'daki montaj ustası")['name']);
        $this->assertSame('Çorlu', $hunter->matchRegion('çorluda ikea varmı')['name']);
        // Ek olmayan devam başka bir yerdir: "Sarayiçi" (Edirne) Saray değildir.
        $this->assertNull($hunter->matchRegion('sarayiçi mobilya montaj'));
    }

    public function test_region_queries_unrelated_to_the_business_are_not_opportunities(): void
    {
        $hunter = app(OpportunityHunter::class);

        $this->assertFalse($hunter->isBusiness('çerkezköy nöbetçi eczane'));
        // Canlı veriden: mağaza arayan kişi montaj aramıyor.
        $this->assertFalse($hunter->isBusiness('ikea çorlu da varmı'));
        $this->assertTrue($hunter->isBusiness('çorlu ikea montajı'));
        $this->assertTrue($hunter->isBusiness('çerkezköy gardırop kurulumu'));
        $this->assertTrue($hunter->isBusiness('kapakli ikea montaj'));
    }

    // ---------- fırsat ekleme ve sıra ----------

    public function test_hunt_tracks_local_queries_and_binds_them_to_the_region_page(): void
    {
        $this->fake([
            ['çerkezköy gardırop montajı', 6.0, 60],
            ['cerkezkoy gardirop montaji', 8.0, 40],   // aynı arama, Türkçe karaktersiz
            ['kapaklı mobilya montaj', 2.0, 30, '/tekirdag/kapakli'],
            ['çerkezköy nöbetçi eczane', 4.0, 500],
            ['mobilya montaj ustası', 9.0, 80, '/mobilya-montaji'],
        ]);

        $result = app(OpportunityHunter::class)->run();

        $wardrobe = $this->keyword('çerkezköy gardırop montajı');
        $this->assertNotNull($wardrobe, 'İki yazım tek kelime olarak eklenmeli');
        $this->assertNull($this->keyword('cerkezkoy gardirop montaji'));
        $this->assertSame(SeoKeyword::CAMPAIGN_ACTIVE, $wardrobe->campaign_status);
        $this->assertSame(100, $wardrobe->gsc_impressions);
        // Sıra gösterime göre ağırlıklı: (6×60 + 8×40) / 100
        $this->assertEqualsWithDelta(6.8, $wardrobe->last_position, 0.01);
        $this->assertSame($this->cerkezkoyTarget()->id, $wardrobe->target_id);

        $this->assertSame(SeoKeyword::CAMPAIGN_WON, $this->keyword('kapaklı mobilya montaj')->campaign_status);
        $this->assertNull($this->keyword('çerkezköy nöbetçi eczane'));
        // Bölgesiz kelime kampanyaya girmez.
        $this->assertNull($this->keyword('mobilya montaj ustası')->campaign_status);

        // Kızılpınar GSC verisi olmadan da takipte ve Çerkezköy sayfasına bağlı.
        $this->assertSame($this->cerkezkoyTarget()->id, $this->keyword('kızılpınar mobilya montaj')->target_id);
        $this->assertGreaterThan(0, $result['seeded']);
    }

    public function test_keyword_that_drops_out_of_the_top_three_is_picked_up_again(): void
    {
        $this->fake([['kapaklı mobilya montaj', 2.0, 30, '/tekirdag/kapakli']]);
        app(OpportunityHunter::class)->run();
        $this->assertSame(SeoKeyword::CAMPAIGN_WON, $this->keyword('kapaklı mobilya montaj')->campaign_status);

        $this->fake([['kapaklı mobilya montaj', 5.4, 30, '/tekirdag/kapakli']]);
        $result = app(OpportunityHunter::class)->run();

        $keyword = $this->keyword('kapaklı mobilya montaj');
        $this->assertSame(SeoKeyword::CAMPAIGN_ACTIVE, $keyword->campaign_status);
        $this->assertSame(1, $result['lost']);
        $this->assertEqualsWithDelta(-3.4, $keyword->positionDelta(), 0.01);
    }

    public function test_missing_gsc_data_does_not_erase_the_last_known_position(): void
    {
        $this->fake([['saray mobilya montaj', 7.0, 20, '/tekirdag/saray']]);
        app(OpportunityHunter::class)->run();

        $this->fake([]);
        app(OpportunityHunter::class)->run();

        $this->assertSame(7.0, $this->keyword('saray mobilya montaj')->last_position);
    }

    public function test_new_keywords_per_run_are_capped(): void
    {
        SeoConfig::set('opportunity_max_new_per_run', 2);
        $this->fake([
            ['çorlu mobilya montaj', 9.0, 20, '/tekirdag/corlu'],
            ['çorlu gardırop montajı', 12.0, 15, '/tekirdag/corlu'],
            ['vize mobilya montaj', 14.0, 10, '/kirklareli/vize'],
        ]);

        $this->assertSame(2, app(OpportunityHunter::class)->run()['hunted']);
    }

    // ---------- iyileştirme ----------

    private function activeCerkezkoyKeyword(float $position = 8.0, int $impressions = 50): SeoKeyword
    {
        return SeoKeyword::create([
            'keyword' => 'çerkezköy mobilya montaj', 'keyword_type' => 'COMMERCIAL_PRIMARY', 'status' => true,
            'campaign_status' => SeoKeyword::CAMPAIGN_ACTIVE, 'target_position' => 3, 'region' => 'Çerkezköy',
            'last_position' => $position, 'gsc_impressions' => $impressions, 'target_id' => $this->cerkezkoyTarget()->id,
        ]);
    }

    public function test_keyword_in_the_top_three_is_never_touched(): void
    {
        $this->fake([]);
        $keyword = $this->activeCerkezkoyKeyword(2.0);
        $keyword->update(['campaign_status' => SeoKeyword::CAMPAIGN_WON]);
        $before = District::where('slug', 'cerkezkoy')->first()->only(['meta_title', 'content', 'faqs']);

        $result = app(RankBooster::class)->boost($keyword);

        $this->assertNotNull($result['blocked']);
        $this->assertSame([], $result['ran']);
        $this->assertSame($before, District::where('slug', 'cerkezkoy')->first()->only(['meta_title', 'content', 'faqs']));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'openrouter.ai'));
    }

    public function test_boost_strengthens_the_page_and_every_write_can_be_reverted(): void
    {
        $this->fake([]);
        $keyword = $this->activeCerkezkoyKeyword();
        $district = District::where('slug', 'cerkezkoy')->first();
        // Seed'deki başlık kelimeyi zaten içeriyor; başlık adımının çalışması için kelimesiz başlık.
        $district->update(['meta_title' => 'Çerkezköy Hizmet Bölgesi']);
        $original = $district->only(['meta_title', 'meta_description', 'content', 'faqs']);

        $result = app(RankBooster::class)->boost($keyword);

        $this->assertSame(['meta', 'content', 'faq', 'link_rule', 'index'], $result['ran']);

        $district->refresh();
        $this->assertSame('Çerkezköy Mobilya Montajı | Hızlı Kurulum', $district->meta_title);
        $this->assertStringContainsString('data-kw="cerkezkoy-mobilya-montaj"', $district->content);
        $this->assertCount(count($original['faqs']) + 1, $district->faqs);
        $this->assertTrue(InternalLinkRule::where('anchor_text', 'çerkezköy mobilya montaj')->where('target_url', '/tekirdag/cerkezkoy')->exists());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'indexing.googleapis.com'));

        // İkinci tur aynı şeyleri tekrar yazmaz.
        $this->assertSame([], app(RankBooster::class)->boost($keyword->fresh())['ran']);

        $booster = app(RankBooster::class);
        foreach (SeoKeywordAction::where('status', 'done')->get()->filter->isRevertible() as $action) {
            $booster->revert($action);
        }

        $district->refresh();
        $this->assertSame($original, $district->only(['meta_title', 'meta_description', 'content', 'faqs']));
        $this->assertFalse(InternalLinkRule::where('anchor_text', 'çerkezköy mobilya montaj')->first()->is_active);
    }

    /** Canlıda yaşandı: servis hesabı "Sahip" değil, Indexing API her denemede 403 döndü. */
    public function test_index_step_waits_after_a_permission_error(): void
    {
        $this->fake([]);
        $keyword = $this->activeCerkezkoyKeyword();
        $booster = app(RankBooster::class);
        $booster->runStep($keyword, 'content');

        SeoKeywordAction::create([
            'keyword_id' => $keyword->id, 'action' => 'index', 'status' => SeoKeywordAction::FAILED,
            'message' => 'Google "bu siteye sahip değilsiniz" diyor (403).', 'page_url' => '/tekirdag/cerkezkoy',
        ]);

        $this->assertSame('blocked', $booster->diagnose($keyword)['index']['state']);
    }

    public function test_revert_refuses_to_overwrite_a_later_manual_edit(): void
    {
        $this->fake([]);
        $keyword = $this->activeCerkezkoyKeyword();
        District::where('slug', 'cerkezkoy')->first()->update(['meta_title' => 'Çerkezköy Hizmet Bölgesi']);
        $action = app(RankBooster::class)->runStep($keyword, 'meta');
        $this->assertSame(SeoKeywordAction::DONE, $action->status);

        District::where('slug', 'cerkezkoy')->first()->update(['meta_title' => 'Elle yazılmış başlık']);

        $this->expectExceptionMessage('değiştirilmiş');
        app(RankBooster::class)->revert($action);
    }

    public function test_model_output_without_the_keyword_is_not_saved(): void
    {
        $this->fake([], ['meta_title' => 'Trakya Mobilya Kurulum Hizmeti']);
        $keyword = $this->activeCerkezkoyKeyword();
        $before = District::where('slug', 'cerkezkoy')->value('meta_title');

        $action = app(RankBooster::class)->runStep($keyword, 'meta');

        $this->assertSame(SeoKeywordAction::FAILED, $action->status);
        $this->assertSame($before, District::where('slug', 'cerkezkoy')->value('meta_title'));
    }

    public function test_page_title_is_reserved_for_the_page_primary_keyword(): void
    {
        $this->fake([]);
        $this->activeCerkezkoyKeyword(8.0, 200);
        $secondary = SeoKeyword::create([
            'keyword' => 'kızılpınar mobilya montaj', 'status' => true, 'campaign_status' => SeoKeyword::CAMPAIGN_ACTIVE,
            'region' => 'Kızılpınar', 'gsc_impressions' => 10, 'target_id' => $this->cerkezkoyTarget()->id,
        ]);

        $meta = app(RankBooster::class)->diagnose($secondary)['meta'];

        $this->assertSame('done', $meta['state']);
        $this->assertStringContainsString('çerkezköy mobilya montaj', $meta['reason']);
    }

    public function test_autopilot_respects_the_daily_limit(): void
    {
        $this->fake([]);
        SeoConfig::set('opportunity_daily_limit', 1);
        $this->activeCerkezkoyKeyword();
        SeoKeyword::create([
            'keyword' => 'kapaklı mobilya montaj', 'status' => true, 'campaign_status' => SeoKeyword::CAMPAIGN_ACTIVE,
            'region' => 'Kapaklı', 'last_position' => 9, 'target_id' => SeoTarget::where('url_hash', PathNormalizer::hash('/tekirdag/kapakli'))->value('id'),
        ]);

        $this->artisan('seo:opportunities')->assertSuccessful();

        $this->assertSame(1, SeoKeyword::whereNotNull('last_boost_at')->count());

        // Aynı gün ikinci tur: işlenen kelime bekleme süresinde, sıradaki alınır.
        $this->artisan('seo:opportunities')->assertSuccessful();
        $this->assertSame(2, SeoKeyword::whereNotNull('last_boost_at')->count());
    }

    public function test_autopilot_off_still_tracks_but_writes_nothing(): void
    {
        $this->fake([['çerkezköy mobilya montaj', 8.0, 50]]);
        SeoConfig::set('opportunity_autopilot', false);

        $this->artisan('seo:opportunities')->assertSuccessful();

        $this->assertSame(8.0, $this->keyword('çerkezköy mobilya montaj')->last_position);
        $this->assertSame(0, SeoKeywordAction::count());
    }

    public function test_content_enrichment_keeps_keyword_sections(): void
    {
        $this->fake([]);
        $district = District::where('slug', 'cerkezkoy')->first();
        $block = KeywordSections::wrap('çerkezköy mobilya montaj', '<h2>Çerkezköy mobilya montaj</h2><p>Bölüm</p>');
        $district->update(['content' => $district->content.$block]);

        app(RegionContentEnricher::class)->enrich($district->fresh('province'));

        $this->assertStringContainsString($block, $district->fresh()->content);
    }

    // ---------- panel ----------

    /**
     * Canlıda yaşandı: kod migration'dan önce sunucuya ulaştı, menü rozeti olmayan sütunu
     * sorguladı ve BÜTÜN panel 500 verdi — migration'ı çalıştıracak Sistem Komutları dahil.
     */
    public function test_panel_still_opens_before_the_migration_has_run(): void
    {
        Schema::table('seo_keywords', function ($table) {
            $table->dropIndex(['campaign_status']);
            $table->dropColumn('campaign_status');
        });

        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());

        $this->get('/admin')->assertOk();
        $this->get('/admin/system-commands')->assertOk();
    }

    public function test_page_renders_and_requires_permission(): void
    {
        $this->fake([]);
        $this->activeCerkezkoyKeyword();

        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())
            ->get('/admin/gsc-firsat-avi')
            ->assertOk()
            ->assertSee('çerkezköy mobilya montaj')
            ->assertSee('Kızılpınar');
    }
}
