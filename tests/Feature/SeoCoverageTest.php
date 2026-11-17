<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\District;
use App\Models\InternalLinkRule;
use App\Models\Province;
use App\Models\Setting;
use App\Services\Ai\AiException;
use App\Services\Ai\ContentSanitizer;
use App\Services\Ai\RegionContentEnricher;
use App\Services\InternalLink\RuleBuilder;
use App\Services\Seo\TargetSynchroniser;
use App\Support\Console\CommandCatalog;
use App\Support\SeoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kardeş sitede (toplu_alim) görülen boşluklar: iç link motoru doğal dilde yazılmış metne
 * link basamıyordu, marka sayfaları AI zenginleştirmesinin kapsamında değildi, AI metni
 * 600 kelimenin altında kalıyordu.
 */
class SeoCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function enableLinks(): void
    {
        SeoConfig::set('internal_links_enabled', true);
        SeoConfig::set('internal_links_auto_apply', true);
        Setting::flush();

        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();
    }

    private function fakeAi(array $payload): void
    {
        config(['seo.ai.api_key' => 'test-key', 'seo.ai.base_url' => 'https://openrouter.ai/api/v1']);
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]]])]);
    }

    /** Hizmet, marka ve il sayısı küçük: hepsi her turda taranır, ilk N kayıtla sınırlı kalmaz. */
    public function test_link_apply_reaches_provinces_and_districts(): void
    {
        $text = '<p>'.str_repeat('Evinizdeki eşyaları dikkatle kuruyoruz. ', 40).' Ayrıca gardırop montajı talepleri de alıyoruz.</p>';
        Province::where('slug', 'kirklareli')->update(['content' => $text]);
        District::where('slug', 'cerkezkoy')->update(['content' => $text]);

        $this->enableLinks();
        $this->artisan('links:apply')->assertSuccessful();

        $this->assertStringContainsString('href="/gardirop-montaji"', Province::where('slug', 'kirklareli')->value('content'));
        $this->assertStringContainsString('href="/gardirop-montaji"', District::where('slug', 'cerkezkoy')->value('content'));
    }

    /**
     * Doğal dilde yazılmış metinde kural ifadesi birebir geçmez. Yedek paragraf sayfaya
     * gerçek iç link kazandırır, ikinci koşuda tekrar eklenmez, sayfa kendine link vermez.
     */
    public function test_pages_without_matching_anchors_get_a_related_links_paragraph_once(): void
    {
        $plain = '<p>'.str_repeat('Evlerdeki eşyaları özenle ve temiz biçimde kuruyoruz. ', 30).'</p>';
        Province::where('slug', 'edirne')->update(['content' => $plain]);
        District::where('slug', 'cerkezkoy')->update(['content' => $plain]);
        Brand::where('slug', 'istikbal')->update(['content' => $plain.'<p><small>Marka adları sahiplerine aittir.</small></p>', 'service_id' => null, 'is_active' => true]);
        Province::where('slug', 'kirklareli')->update(['content' => '']);

        $this->enableLinks();
        $this->artisan('links:apply')->assertSuccessful();

        $edirne = Province::where('slug', 'edirne')->value('content');
        $this->assertStringContainsString('class="related-links"', $edirne);
        $this->assertMatchesRegularExpression('#href="/edirne/[a-z-]+"#', $edirne, 'il paragrafı ilçelerine link verir');
        $this->assertStringNotContainsString('href="/edirne"', $edirne, 'sayfa kendine link vermemeli');

        $district = District::where('slug', 'cerkezkoy')->value('content');
        $this->assertStringContainsString('class="related-links"', $district);
        $this->assertStringContainsString('href="/tekirdag"', $district, 'ilçe paragrafı iline link verir');

        $brand = Brand::where('slug', 'istikbal')->value('content');
        $this->assertStringContainsString('href="/hizmetler"', $brand);
        $this->assertMatchesRegularExpression('#related-links.*<small>#s', $brand, 'hukuki not sonda kalmalı');

        // İkinci koşu hiçbir şey değiştirmez.
        $this->artisan('links:apply')->assertSuccessful();
        $this->assertSame($edirne, Province::where('slug', 'edirne')->value('content'));
        $this->assertSame(1, substr_count($edirne, 'related-links'));

        // Metinsiz il boş kalır (paragraf tek başına içerik sayılmaz).
        $this->assertSame('', (string) Province::where('slug', 'kirklareli')->value('content'));
    }

    public function test_brand_pages_are_enriched_and_keep_the_trademark_notice(): void
    {
        $body = '<p>'.str_repeat('İstikbal yatak odası takımlarını kurarken parça listesini önce kontrol ediyoruz. ', 30).'</p>'
            .'<h2>Dikkat edilmesi gerekenler</h2><p>Duvara sabitleme ve menteşe ayarı.</p>';
        $this->fakeAi(['body_html' => $body, 'meta_title' => 'İstikbal Mobilya Montajı', 'meta_description' => str_repeat('İstikbal montaj hizmeti. ', 5)]);

        $brand = Brand::where('slug', 'istikbal')->firstOrFail();
        $brand->update(['content' => '<p>Kısa metin.</p><p><small>Marka adları sahiplerine aittir.</small></p>']);

        $result = app(RegionContentEnricher::class)->enrich($brand->fresh());

        $fresh = $brand->fresh();
        $this->assertGreaterThan($result['words_before'], $result['words_after']);
        $this->assertStringContainsString('Dikkat edilmesi gerekenler', $fresh->content);
        $this->assertStringContainsString('Marka adları sahiplerine aittir.', $fresh->content, 'hukuki not korunmalı');

        Http::assertSent(fn ($request) => str_contains(json_decode((string) $request->body(), true)['messages'][1]['content'] ?? '', 'MARKA HAKKINDA İDDİA YASAK'));
    }

    /**
     * AI'ya istenen ifadeler iç link kurallarıyla BİREBİR aynı olmalı; yoksa metne giren
     * ifade linke dönüşmez.
     */
    public function test_province_prompt_asks_for_phrases_that_match_link_rules_and_600_words(): void
    {
        $this->fakeAi(['body_html' => '<p>'.str_repeat('Tekirdağ genelinde mobilya kuruyoruz. ', 160).'</p>', 'meta_title' => 'Tekirdağ Mobilya Montaj', 'meta_description' => str_repeat('Tekirdağ montaj. ', 8)]);

        app(RegionContentEnricher::class)->enrich(Province::where('slug', 'tekirdag')->firstOrFail());

        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();
        $anchors = InternalLinkRule::query()->active()->pluck('anchor_text')->all();

        Http::assertSent(function ($request) use ($anchors) {
            $prompt = json_decode((string) $request->body(), true)['messages'][1]['content'] ?? '';
            if (! str_contains($prompt, 'İSTENEN YAPI')) {
                return false; // genişletme çağrısı
            }
            $this->assertStringContainsString('EN AZ 600', $prompt);
            $this->assertStringContainsString('"Çerkezköy mobilya montaj"', $prompt);
            $this->assertContains('Çerkezköy mobilya montaj', $anchors);

            return true;
        });
    }

    /** AI 650-800 istense de ~300 kelime yazabiliyor; kısa metin ikinci çağrıyla uzatılır. */
    public function test_short_ai_output_is_expanded_with_a_second_call(): void
    {
        config(['seo.ai.api_key' => 'test-key', 'seo.ai.base_url' => 'https://openrouter.ai/api/v1']);
        $reply = fn (int $n) => Http::response(['choices' => [['message' => ['content' => json_encode([
            'body_html' => '<h2>Randevu süreci</h2><p>'.str_repeat('Edirne evlerinde mobilyaları kuruyoruz. ', $n).'</p>',
            'meta_title' => 'Edirne Mobilya Montaj', 'meta_description' => str_repeat('Edirne montaj. ', 9),
        ], JSON_UNESCAPED_UNICODE)]]]]);

        // Mevcut metin kısa tutulur ki "kısa metinle değiştirme" koruması devreye girmesin.
        Province::where('slug', 'edirne')->update(['content' => '<p>Edirne.</p>']);
        Http::fakeSequence('openrouter.ai/*')->pushResponse($reply(60))->pushResponse($reply(170));

        $result = app(RegionContentEnricher::class)->enrich(Province::where('slug', 'edirne')->firstOrFail());

        $this->assertGreaterThanOrEqual(600, $result['words_after']);
        Http::assertSentCount(2);
        // İlk metin korunur, yeni bölümler sona eklenir.
        $this->assertSame(2, substr_count((string) Province::where('slug', 'edirne')->value('content'), '<h2>Randevu süreci</h2>'));
    }

    public function test_a_longer_existing_text_is_never_replaced_by_a_shorter_one(): void
    {
        $long = '<p>'.str_repeat('Edirne evlerinde mobilya kuruyoruz. ', 250).'</p>';
        Province::where('slug', 'edirne')->update(['content' => $long]);

        $this->fakeAi(['body_html' => '<p>'.str_repeat('Kısa metin kelime. ', 110).'</p>', 'meta_title' => 'Edirne', 'meta_description' => 'x']);

        try {
            app(RegionContentEnricher::class)->enrich(Province::where('slug', 'edirne')->firstOrFail());
            $this->fail('kısa metin kaydedilmemeliydi');
        } catch (AiException $e) {
            $this->assertStringContainsString('kısa', $e->getMessage());
        }

        $this->assertSame($long, Province::where('slug', 'edirne')->value('content'));
    }

    /**
     * Skoru hedefi geçtiği için zenginleştirmeye girmeyen ama 600 kelimenin altında kalan
     * sayfalar: metin korunur, yeni bölümler eklenir, sondaki bloklar sonda kalır.
     */
    public function test_short_pages_are_lengthened_without_touching_existing_text(): void
    {
        $original = '<p>'.str_repeat('Kırklareli evlerinde mobilyaları kuruyoruz. ', 50).'</p><h2>Randevu</h2><p>Arayın.</p>';
        Province::where('slug', 'kirklareli')->update(['content' => $original."\n".'<p class="related-links">İlgili: <a href="/kirklareli/luleburgaz">x</a></p>']);

        app(TargetSynchroniser::class)->sync();
        $this->artisan('seo:score')->assertSuccessful();

        $this->fakeAi(['body_html' => '<h2>Montaj öncesi hazırlık</h2><p>'.str_repeat('Kurulacak alanı boşaltıp ölçüleri kontrol edin. ', 50).'</p>']);

        $this->artisan('seo:enrich', ['--short' => true, '--type' => ['province'], '--limit' => 20])->assertSuccessful();

        $content = (string) Province::where('slug', 'kirklareli')->value('content');
        $this->assertStringStartsWith($original, $content, 'mevcut metin aynen korunmalı');
        $this->assertStringContainsString('<h2>Montaj öncesi hazırlık</h2>', $content);
        $this->assertMatchesRegularExpression('#Montaj öncesi hazırlık.*related-links#s', $content, 'ilgili sayfalar sonda kalmalı');
        $this->assertGreaterThanOrEqual(600, app(ContentSanitizer::class)->wordCount($content));
        $this->assertArrayHasKey('enrich-short', CommandCatalog::all());
    }

    /** Metinsiz bölge sayfaları skordan bağımsız doldurulur; metni olan sayfaya dokunulmaz. */
    public function test_thin_option_fills_only_textless_region_pages(): void
    {
        District::where('slug', 'cerkezkoy')->update(['content' => null]);
        District::where('slug', 'kapakli')->update(['content' => '<p>'.str_repeat('Kapaklı sitelerinde gardırop ve baza kuruyoruz. ', 40).'</p>']);
        $kapakli = District::where('slug', 'kapakli')->value('content');

        $this->fakeAi(['body_html' => '<p>'.str_repeat('Çerkezköy mahallelerinde mobilya kuruyoruz. ', 120).'</p>', 'meta_title' => 'Çerkezköy Mobilya Montaj', 'meta_description' => str_repeat('Çerkezköy montaj. ', 8)]);

        $this->artisan('seo:enrich', ['--thin' => true, '--limit' => 50])->assertSuccessful();

        $this->assertStringContainsString('Çerkezköy mahallelerinde', (string) District::where('slug', 'cerkezkoy')->value('content'));
        $this->assertSame($kapakli, District::where('slug', 'kapakli')->value('content'), 'metni olan ilçeye dokunulmaz');
    }

    public function test_enrich_command_accepts_brands(): void
    {
        config(['seo.ai.api_key' => null]);

        // AI yokken sessizce çıkar; ama brand tipi artık geçerli bir seçenek.
        $this->artisan('seo:enrich', ['--type' => ['brand'], '--limit' => 1])->assertSuccessful();
        $this->assertSame(['--type' => ['brand'], '--limit' => 10], CommandCatalog::all()['enrich-brands']['arguments']);
    }
}
