<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Services\Seo\CannibalizationGuard;
use App\Services\Seo\DuplicateGuard;
use App\Support\SeoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec §9 — "Çakışma filtresi" kabul kriterleri.
 */
class DuplicateGuardTest extends TestCase
{
    use RefreshDatabase;

    // Hizmet ve ilçe başlıkları da karşılaştırmaya girer; bu yüzden seed gerekli.
    protected bool $seed = true;

    private function blog(string $title, array $attributes = []): Blog
    {
        return Blog::create(array_merge([
            'title' => $title,
            'slug' => \App\Support\TurkishText::slug($title),
            'body_html' => '<p>İçerik</p>',
            'status' => Blog::STATUS_PUBLISHED,
            'publish_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_blocks_a_near_duplicate_title(): void
    {
        $this->blog('Gardırop Montajında Dikkat Edilecekler');

        $decision = app(DuplicateGuard::class)->check('Gardıropların Montajında Dikkat Edilecekler');

        $this->assertFalse($decision->accepted);
        $this->assertSame('duplicate_title', $decision->reasonCode);
        $this->assertNotNull($decision->reason, 'ret gerekçesi taşınmalı — sessiz eleme yok');
    }

    public function test_allows_a_genuinely_new_topic(): void
    {
        $this->blog('Gardırop Montajında Dikkat Edilecekler');

        $this->assertTrue(app(DuplicateGuard::class)->check('Ofis Taşımada Mobilya Sökme Sırası')->accepted);
    }

    public function test_draft_posts_also_block_duplicates(): void
    {
        // Aynı konu iki kez kuyruğa girmesin.
        $this->blog('Baza Montajı Ne Kadar Sürer', ['status' => Blog::STATUS_DRAFT]);

        $this->assertFalse(app(DuplicateGuard::class)->check('Baza Montajı Ne Kadar Sürer?')->accepted);
    }

    public function test_service_and_district_titles_also_block(): void
    {
        // Blog, mevcut hizmet sayfasının konusunu tekrarlamamalı.
        $this->assertFalse(app(DuplicateGuard::class)->check('Gardırop Montajı')->accepted);
    }

    public function test_invalid_threshold_falls_back_to_safe_default(): void
    {
        $guard = app(DuplicateGuard::class);
        $default = (float) config('seo.duplicate.scan_threshold');

        foreach (['0', '0.0', '1.5', '-1', 'abc', '99'] as $bad) {
            SeoConfig::set('duplicate_scan_threshold', $bad);
            \App\Models\Setting::flush();

            $this->assertSame($default, $guard->threshold(), "geçersiz eşik '{$bad}' varsayılana düşmeli");
        }

        // Geçerli değer kabul edilir.
        SeoConfig::set('duplicate_scan_threshold', '0.7');
        \App\Models\Setting::flush();
        $this->assertSame(0.7, $guard->threshold());
    }

    public function test_cannibalization_blocks_a_second_owner(): void
    {
        $this->blog('Gardırop Nasıl Sabitlenir', ['primary_keyword' => 'gardırop sabitleme']);

        $decision = app(CannibalizationGuard::class)->check('gardırop sabitleme');

        $this->assertFalse($decision->accepted);
        $this->assertSame('cannibalization', $decision->reasonCode);
        // Kontrol ya ENGELLER ya görünür yere yazar; sessiz uyarı yok (spec §7.4).
        $this->assertStringContainsString('gardırop sabitleme', (string) $decision->reason);
    }

    public function test_cannibalization_counts_assigned_targets(): void
    {
        $target = SeoTarget::create(['name' => 'Gardırop Montajı', 'url' => '/gardirop-montaji', 'target_type' => 'service']);

        // Kelime başlangıç havuzundan gelir; burada bir hedefe SAHİPLENDİRİYORUZ.
        $keyword = SeoKeyword::where('keyword', 'gardırop montaj ustası')->firstOrFail();
        $keyword->update(['target_id' => $target->getKey()]);

        $this->assertSame(SeoKeyword::ASSIGN_ACTIVE, $keyword->refresh()->assignment_status);
        $this->assertFalse(app(CannibalizationGuard::class)->check('gardırop montaj ustası')->accepted);
    }

    public function test_cannibalization_allows_a_free_keyword(): void
    {
        $this->assertTrue(app(CannibalizationGuard::class)->check('tamamen yeni bir kelime öbeği')->accepted);
        $this->assertTrue(app(CannibalizationGuard::class)->check(null)->accepted);
    }
}
