<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Service;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Services\Discovery\LlmsTxtGenerator;
use App\Services\InternalLink\RuleBuilder;
use App\Services\Seo\BrandKeywordBuilder;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\TargetSynchroniser;
use App\Support\PathNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Marka ekseni.
 *
 * En kritik kural: bir markanın KENDİ hizmet sayfası varsa (IKEA) ayrıca marka
 * sayfası açılmaz. İki sayfa aynı sorguda yarıştığında ikisi de kaybeder.
 */
class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_brand_index_lists_active_brands(): void
    {
        $response = $this->get('/markalar');

        $response->assertOk();
        $response->assertSee('İstikbal', false);
        $response->assertSee('Çilek', false);
    }

    public function test_brand_detail_page_renders(): void
    {
        $brand = Brand::query()->whereNull('service_id')->where('slug', 'istikbal')->firstOrFail();

        $response = $this->get($brand->path());

        $response->assertOk();
        $response->assertSee($brand->heading, false);
        // Marka sahipliği açıkça belirtilmeli: yetkili servis izlenimi verilmemeli.
        $response->assertSee('yetkili servisi veya bayisi değildir', false);
    }

    public function test_inactive_brand_returns_404(): void
    {
        $brand = Brand::query()->whereNull('service_id')->firstOrFail();
        $brand->update(['is_active' => false]);

        // Route model binding aktif olmayan kaydı da bulur; sayfa yine de gizlenmeli.
        $this->get($brand->path())->assertNotFound();
    }

    public function test_brand_with_own_service_page_redirects_instead_of_competing(): void
    {
        $service = Service::query()->where('slug', 'ikea-mobilya-montaji')->firstOrFail();
        $brand = Brand::query()->where('slug', 'ikea')->firstOrFail();

        $this->assertSame($service->getKey(), $brand->service_id, 'IKEA markası hizmet sayfasına bağlı olmalı.');
        $this->assertFalse($brand->hasOwnPage());

        $this->get('/markalar/ikea')->assertRedirect('/ikea-mobilya-montaji');
    }

    public function test_linked_brand_gets_no_separate_target_and_is_not_scored(): void
    {
        app(TargetSynchroniser::class)->sync();

        $this->assertFalse(
            SeoTarget::query()->where('url_hash', PathNormalizer::hash('/markalar/ikea'))->where('status', true)->exists(),
            'Hizmet sayfasına bağlı marka için ayrı hedef açılmamalı.'
        );

        $registry = app(ContentRegistry::class);
        $brandUrls = $registry->all()->where('contentType', 'brand')->pluck('url')->all();

        $this->assertNotContains(url('/markalar/ikea'), $brandUrls);
        $this->assertContains(url('/markalar/istikbal'), $brandUrls);
    }

    public function test_brand_keywords_are_owned_by_the_page_that_actually_answers_them(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(BrandKeywordBuilder::class)->build();

        $ikea = SeoKeyword::query()->where('keyword', 'ikea mobilya montajı')->first();
        $this->assertNotNull($ikea);
        $this->assertSame('/ikea-mobilya-montaji', $ikea->target?->url, 'IKEA kelimesi hizmet sayfasına ait olmalı.');

        $istikbal = SeoKeyword::query()->where('keyword', 'istikbal mobilya montajı')->first();
        $this->assertNotNull($istikbal);
        $this->assertSame('/markalar/istikbal', $istikbal->target?->url);
    }

    public function test_region_brand_keywords_stay_unowned_for_the_blog_pipeline(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(BrandKeywordBuilder::class)->build();

        $keyword = SeoKeyword::query()->where('keyword', 'like', '%istikbal montajı')->where('keyword_type', 'BLOG_PRIMARY')->first();

        $this->assertNotNull($keyword, 'Bölge × marka kelimesi üretilmeli.');
        $this->assertFalse($keyword->hasOwner(), 'Blog hattına bırakılan kelime sahipsiz kalmalı.');
    }

    public function test_deactivating_a_brand_deactivates_its_keywords_without_deleting_them(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(BrandKeywordBuilder::class)->build();

        $before = SeoKeyword::query()->where('keyword', 'çilek mobilya montajı')->firstOrFail();
        $this->assertTrue($before->status);

        Brand::query()->where('slug', 'cilek')->update(['is_active' => false]);
        app(BrandKeywordBuilder::class)->build();

        $after = SeoKeyword::query()->where('keyword', 'çilek mobilya montajı')->first();

        $this->assertNotNull($after, 'Kelime SİLİNMEMELİ, yalnız pasife alınmalı.');
        $this->assertFalse($after->status);
    }

    public function test_brand_link_rules_avoid_ambiguous_single_word_anchors(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $anchors = \App\Models\InternalLinkRule::query()->where('is_active', true)->pluck('anchor_text')->all();

        // "Çilek" tek başına günlük dilde başka anlama gelir; anchor olmamalı.
        $this->assertNotContains('Çilek', $anchors);
        $this->assertContains('Çilek montajı', $anchors);

        // İki kelimeli marka adı kendi başına açıktır.
        $this->assertContains('Kelebek Mobilya', $anchors);
    }

    public function test_sitemap_includes_brand_pages_but_not_the_redirecting_one(): void
    {
        Cache::forget('sitemap.xml');

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee(url('/markalar/istikbal'), false);
        $response->assertSee(url('/markalar'), false);
        $response->assertDontSee(url('/markalar/ikea'), false);
    }

    public function test_llms_txt_declares_brands_and_the_independence_notice(): void
    {
        app(LlmsTxtGenerator::class)->generate();

        $index = file_get_contents(public_path('llms.txt'));

        $this->assertStringContainsString('Montajını yaptığımız markalar', $index);
        $this->assertStringContainsString('/markalar/istikbal', $index);
        $this->assertStringContainsString('yetkili servis değildir', $index);
        $this->assertStringNotContainsString('/markalar/ikea', $index);
    }
}
