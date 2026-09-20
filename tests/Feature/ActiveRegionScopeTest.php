<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\InternalLinkRule;
use App\Models\Province;
use App\Models\SeoAnalysis;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Services\InternalLink\RuleBuilder;
use App\Services\Seo\CommercialKeywordAssigner;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\LocationKeywordBuilder;
use App\Services\Seo\TargetSynchroniser;
use App\Support\TurkishText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yalnız ERİŞİLEBİLİR bölgelerin işlenmesi.
 *
 * Erişilebilir = ilçe aktif VE bağlı olduğu il aktif. Aksi hâlde adres 404 döner;
 * o sayfayı skorlamak, kelime üretmek veya link hedefi yapmak boşunadır.
 */
class ActiveRegionScopeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Yalnız Tekirdağ + Çorlu açık; Kırklareli kapalı ama Vize açık (tutarsız durum). */
    private function narrowToOneDistrict(): void
    {
        Province::query()->update(['is_active' => false]);
        District::query()->update(['is_active' => false]);

        Province::where('slug', 'tekirdag')->update(['is_active' => true]);
        District::where('slug', 'corlu')->update(['is_active' => true]);

        // Tuzak: ilçe açık ama ili kapalı → sayfa 404 döner.
        District::where('slug', 'vize')->update(['is_active' => true]);
    }

    public function test_district_of_an_inactive_province_is_unreachable(): void
    {
        $this->narrowToOneDistrict();

        $this->get('/tekirdag/corlu')->assertOk();
        $this->get('/kirklareli/vize')->assertNotFound();
    }

    public function test_registry_excludes_unreachable_content(): void
    {
        $this->narrowToOneDistrict();

        $reachable = app(ContentRegistry::class)->reachable();
        $urls = $reachable->pluck('url')->filter()->map(fn ($u) => parse_url($u, PHP_URL_PATH))->all();

        $this->assertContains('/tekirdag', $urls);
        $this->assertContains('/tekirdag/corlu', $urls);
        $this->assertNotContains('/kirklareli/vize', $urls, 'ili kapalı ilçe erişilebilir sayılmamalı');
        $this->assertNotContains('/edirne', $urls);

        $this->assertLessThan(app(ContentRegistry::class)->all()->count(), $reachable->count());
    }

    public function test_scoring_skips_unreachable_pages_and_clears_old_scores(): void
    {
        // Önce her şey skorlansın.
        $this->artisan('seo:score', ['--all' => true])->assertSuccessful();
        $before = SeoAnalysis::count();

        $this->narrowToOneDistrict();
        $this->artisan('seo:score')->assertSuccessful();

        $this->assertLessThan($before, SeoAnalysis::count());

        // Kapalı sayfanın eski skoru KALMAMALI: panelde hayalet satır olmasın.
        $vize = District::where('slug', 'vize')->first();
        $this->assertDatabaseMissing('seo_analyses', ['content_type' => 'district', 'content_id' => $vize->id]);

        $corlu = District::where('slug', 'corlu')->first();
        $this->assertDatabaseHas('seo_analyses', ['content_type' => 'district', 'content_id' => $corlu->id]);
    }

    public function test_location_keywords_are_built_only_for_reachable_regions(): void
    {
        $this->narrowToOneDistrict();

        $result = app(LocationKeywordBuilder::class)->build();

        $this->assertSame(['Tekirdağ (il)', 'Çorlu / Tekirdağ'], $result['regions']);

        $keywords = SeoKeyword::where('note', 'like', 'Bölge otomasyonu%')->pluck('keyword');

        $this->assertTrue($keywords->contains('çorlu mobilya montaj'));
        $this->assertTrue($keywords->contains('tekirdağ mobilya montaj'));
        $this->assertFalse($keywords->contains('vize mobilya montaj'), 'erişilemeyen ilçe için kelime üretilmemeli');
        $this->assertFalse($keywords->contains('keşan mobilya montaj'));
    }

    public function test_location_keywords_include_service_combinations(): void
    {
        $this->narrowToOneDistrict();
        app(LocationKeywordBuilder::class)->build();

        $keywords = SeoKeyword::pluck('keyword')->map(fn ($k) => TurkishText::lower($k));

        // İl/ilçe + hizmet birleşimleri blog hattının hedefi olur.
        $this->assertTrue($keywords->contains('çorlu gardırop montajı'));
        $this->assertTrue($keywords->contains('çorlu ikea montajı'));
        $this->assertTrue($keywords->contains('çorlu baza montajı'));
    }

    public function test_region_keywords_are_deactivated_when_the_region_closes(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $this->assertTrue(SeoKeyword::where('keyword', 'çorlu mobilya montaj')->value('status'));

        District::where('slug', 'corlu')->update(['is_active' => false]);
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $keyword = SeoKeyword::where('keyword', 'çorlu mobilya montaj')->first();

        $this->assertNotNull($keyword, 'kelime SİLİNMEMELİ, yalnız pasife alınmalı');
        $this->assertFalse((bool) $keyword->status);

        // Bölge tekrar açılırsa kelime geri gelir.
        District::where('slug', 'corlu')->update(['is_active' => true]);
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $this->assertTrue((bool) $keyword->refresh()->status);
    }

    public function test_district_keyword_is_owned_by_its_page(): void
    {
        $this->narrowToOneDistrict();
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $keyword = SeoKeyword::where('keyword', 'çorlu mobilya montaj')->firstOrFail();

        $this->assertNotNull($keyword->target_id);
        $this->assertSame('/tekirdag/corlu', $keyword->target->url);
        $this->assertSame(SeoKeyword::ASSIGN_ACTIVE, $keyword->assignment_status);

        // Hizmet birleşimleri blog için SAHİPSİZ kalmalı.
        $serviceKeyword = SeoKeyword::where('keyword', 'çorlu gardırop montajı')->firstOrFail();
        $this->assertFalse($serviceKeyword->hasOwner());
    }

    public function test_commercial_keywords_go_to_service_pages(): void
    {
        app(TargetSynchroniser::class)->sync();

        $result = app(CommercialKeywordAssigner::class)->assign();
        $this->assertGreaterThan(0, $result['assigned']);

        $keyword = SeoKeyword::where('keyword', 'ikea montaj servisi')->firstOrFail();

        // Ticari kelimeyi blog değil, para kazandıran hizmet sayfası sahiplenmeli.
        $this->assertSame('/ikea-mobilya-montaji', $keyword->target?->url);
        $this->assertFalse(
            app(\App\Services\Seo\KeywordPool::class)->available()->contains('keyword', 'ikea montaj servisi'),
            'sahiplenen ticari kelime blog havuzunda kalmamalı',
        );
    }

    public function test_link_rules_only_target_reachable_pages(): void
    {
        $this->narrowToOneDistrict();
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $active = InternalLinkRule::where('is_active', true)->pluck('target_url')->unique();

        $this->assertTrue($active->contains('/tekirdag/corlu'));
        $this->assertFalse($active->contains('/kirklareli/vize'), 'erişilemeyen sayfa link hedefi olmamalı');
        $this->assertFalse($active->contains('/edirne'));
    }

    public function test_link_rules_are_deactivated_when_a_page_closes(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $this->assertTrue(InternalLinkRule::where('target_url', '/tekirdag/corlu')->where('is_active', true)->exists());

        District::where('slug', 'corlu')->update(['is_active' => false]);
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $rule = InternalLinkRule::where('target_url', '/tekirdag/corlu')->first();
        $this->assertNotNull($rule, 'kural SİLİNMEMELİ');
        $this->assertFalse((bool) $rule->is_active);
    }

    public function test_published_blog_gets_internal_links_at_render_time(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        \App\Support\SeoConfig::set('internal_links_enabled', true);
        \App\Models\Setting::flush();

        $blog = \App\Models\Blog::create([
            'title' => 'Gardırop Kurulumunda Dikkat Edilecekler',
            'slug' => 'gardirop-kurulumunda-dikkat',
            'body_html' => '<p>Çorlu mobilya montajı sırasında gardırop montajını doğru yapmak önemlidir.</p>',
            'status' => \App\Models\Blog::STATUS_PUBLISHED,
            'publish_at' => now()->subHour(),
        ]);

        $html = $this->get($blog->path())->assertOk()->getContent();

        // Link RENDER ANINDA basılır; links:apply cron'unu beklemez.
        $this->assertStringContainsString('class="internal-link"', $html);
        $this->assertStringContainsString('/gardirop-montaji', $html);

        // Veritabanındaki gövde değişmemiş olmalı (kalıcı yazma ayrı bir iştir).
        $this->assertStringNotContainsString('internal-link', $blog->refresh()->body_html);
    }

    public function test_render_time_links_are_off_when_the_engine_is_off(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $blog = \App\Models\Blog::create([
            'title' => 'Motor Kapalıyken', 'slug' => 'motor-kapaliyken',
            'body_html' => '<p>Çorlu mobilya montajı ve gardırop montajı.</p>',
            'status' => \App\Models\Blog::STATUS_PUBLISHED, 'publish_at' => now()->subHour(),
        ]);

        $html = $this->get($blog->path())->assertOk()->getContent();

        $this->assertStringNotContainsString('class="internal-link"', $html);
    }
}
