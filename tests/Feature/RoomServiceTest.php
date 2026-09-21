<?php

namespace Tests\Feature;

use App\Models\InternalLinkRule;
use App\Models\SeoAnalysis;
use App\Models\SeoKeyword;
use App\Models\Service;
use App\Services\InternalLink\RuleBuilder;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\ContentScorer;
use App\Services\Seo\LocationKeywordBuilder;
use App\Services\Seo\TargetSynchroniser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Oda tipi hizmetler (yatak odası, yemek odası, koltuk takımı, genç odası, çocuk odası).
 *
 * Bunlar ürün bazlı hizmetlerden AYRI bir arama niyetini karşılar. En büyük risk,
 * "yatak odası montajı" ifadesinin hem kendi sayfasına hem de baza sayfasına
 * işaret etmesidir; iç link kuralı bunu yapmamalıdır.
 */
class RoomServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const SLUGS = [
        'yatak-odasi-montaji',
        'yemek-odasi-montaji',
        'koltuk-takimi-montaji',
        'genc-odasi-montaji',
        'cocuk-odasi-montaji',
    ];

    public static function roomServices(): array
    {
        return array_map(fn (string $slug) => [$slug], self::SLUGS);
    }

    #[DataProvider('roomServices')]
    public function test_room_service_page_renders(string $slug): void
    {
        $service = Service::query()->where('slug', $slug)->firstOrFail();

        $response = $this->get('/'.$slug);

        $response->assertOk();
        $response->assertSee($service->title, false);
        $response->assertSee('Neler yapıyoruz?', false);
        $response->assertSee('Sık sorulan sorular', false);
    }

    #[DataProvider('roomServices')]
    public function test_room_service_has_the_content_a_service_page_needs(string $slug): void
    {
        $service = Service::query()->where('slug', $slug)->firstOrFail();

        $this->assertNotEmpty($service->what_we_do, $slug.': "neler yapıyoruz" boş.');
        $this->assertNotEmpty($service->suitable_for, $slug.': "kimler için uygun" boş.');
        $this->assertNotEmpty($service->process_steps, $slug.': süreç adımları boş.');
        $this->assertGreaterThanOrEqual(3, count($service->faqs ?? []), $slug.': en az üç soru olmalı.');
        $this->assertNotEmpty($service->meta_title, $slug.': meta başlık boş.');
        $this->assertLessThanOrEqual(70, mb_strlen($service->meta_title), $slug.': meta başlık panel sınırını aşıyor.');
        $this->assertNotEmpty($service->image, $slug.': görsel yok.');
    }

    #[DataProvider('roomServices')]
    public function test_room_service_reaches_the_score_target(string $slug): void
    {
        $service = Service::query()->where('slug', $slug)->firstOrFail();
        $content = app(ContentRegistry::class)->forModel($service);

        $result = app(ContentScorer::class)->score($content);

        $this->assertGreaterThanOrEqual(
            80,
            $result['score'],
            $slug.' skoru hedefin altında: '.implode(' | ', $result['issues'])
        );
    }

    public function test_bed_service_alias_no_longer_steals_the_bedroom_phrase(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $rule = InternalLinkRule::query()
            ->where('anchor_text', 'yatak odası montajı')
            ->where('is_active', true)
            ->first();

        // Ya kural yok, ya da varsa doğru sayfayı göstermeli — asla baza sayfasını değil.
        if ($rule) {
            $this->assertNotSame('/yatak-baza-montaji', $rule->target_url);
        }

        $this->assertTrue(
            InternalLinkRule::query()->where('target_url', '/yatak-odasi-montaji')->where('is_active', true)->exists(),
            'Yatak odası sayfası için iç link kuralı üretilmeli.'
        );
    }

    public function test_room_services_get_region_keywords(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        foreach (['yatak odası montajı', 'çocuk odası montajı', 'koltuk takımı montajı'] as $term) {
            $this->assertTrue(
                SeoKeyword::query()->where('keyword', 'like', '%'.$term)->where('status', true)->exists(),
                'Bölge × '.$term.' kelimesi üretilmeli.'
            );
        }
    }

    public function test_deactivated_room_service_drops_out_of_keywords(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $this->assertTrue(SeoKeyword::query()->where('keyword', 'like', '%çocuk odası montajı')->where('status', true)->exists());

        Service::query()->where('slug', 'cocuk-odasi-montaji')->update(['is_active' => false]);
        app(LocationKeywordBuilder::class)->build();

        $rows = SeoKeyword::query()->where('keyword', 'like', '%çocuk odası montajı')->get();

        $this->assertNotEmpty($rows, 'Kelimeler SİLİNMEMELİ.');
        $this->assertTrue($rows->every(fn ($k) => ! $k->status), 'Hizmet kapatılınca kelimeleri pasife alınmalı.');
    }

    public function test_sitemap_lists_the_room_services(): void
    {
        Cache::forget('sitemap.xml');

        $response = $this->get('/sitemap.xml');

        foreach (self::SLUGS as $slug) {
            $response->assertSee(url('/'.$slug), false);
        }
    }
}
