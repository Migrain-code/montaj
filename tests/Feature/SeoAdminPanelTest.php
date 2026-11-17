<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoAiSettings;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Blogs\Pages\ListBlogs;
use App\Filament\Resources\SeoAnalyses\Pages\ManageSeoAnalyses;
use App\Filament\Resources\SeoAnalyses\SeoAnalysisResource;
use App\Models\AiCrawlerVisit;
use App\Models\AiGeneration;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\IndexStatus;
use App\Models\InternalLinkRule;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\SeoAnalysis;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Setting;
use App\Models\User;
use App\Services\InternalLink\LinkApplier;
use App\Services\Seo\TargetSynchroniser;
use App\Support\SeoConfig;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeoAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function seedSample(): void
    {
        app(TargetSynchroniser::class)->sync();

        Blog::create([
            'title' => 'Örnek Yazı', 'slug' => 'ornek-yazi', 'body_html' => '<p>Metin</p>',
            'blog_category_id' => BlogCategory::first()->getKey(),
            'status' => Blog::STATUS_PUBLISHED, 'publish_at' => now()->subDay(),
        ]);

        Redirect::create(['from_path' => '/eski', 'to_path' => '/']);
        NotFoundLog::record('/yok', null, null);
        AiCrawlerVisit::record('GPTBot', '/', 200);
        InternalLinkRule::create(['anchor_text' => 'gardırop montajı', 'target_url' => '/gardirop-montaji']);
        AiGeneration::create(['operation' => 'blog.topics', 'model' => 'test/model', 'status' => 'success', 'input' => ['system' => 's', 'user' => 'u']]);
    }

    public function test_all_seo_admin_pages_render(): void
    {
        $this->seedSample();
        $this->actingAs($this->admin());

        $urls = [
            '/admin/seo-merkezi/seo-dashboard',
            '/admin/seo-merkezi/seo-ai-settings',
            '/admin/seo-merkezi/duplicate-cleaner',
            '/admin/seo-merkezi/internal-link-suggestions',
            '/admin/seo-merkezi/blogs', '/admin/seo-merkezi/blogs/create', '/admin/seo-merkezi/blogs/'.Blog::first()->id.'/edit',
            '/admin/blog-categories',
            '/admin/seo-merkezi/seo-keywords',
            '/admin/seo-merkezi/seo-targets',
            '/admin/seo-merkezi/internal-link-rules',
            '/admin/seo-merkezi/ai-generations',
            '/admin/seo-merkezi/redirects',
            '/admin/seo-merkezi/not-found-logs',
            '/admin/seo-merkezi/ai-crawler-visits',
            '/admin/seo-merkezi/seo-analyses',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    // ---------- Dizin durumu ----------

    /** @return array<string, SeoAnalysis> */
    private function seedIndexStatuses(): array
    {
        // Liste adres başına tutulur (index_statuses): site haritasındaki tüm adresler.
        $make = function (string $url, ?string $status, ?string $checked) {
            return IndexStatus::create([
                'url' => $url,
                'google_index_status' => $status,
                'google_index_detail' => $status === 'FAIL' ? 'URL is unknown to Google' : null,
                'index_checked_at' => $checked,
            ]);
        };

        return [
            'indexed' => $make('/dizinde', 'PASS', now()->subDay()),
            'missing' => $make('/dizinde-degil', 'FAIL', now()->subDay()),
            'unchecked' => $make('/hic-bakilmadi', null, null),
        ];
    }

    public function test_index_status_page_lists_uncrawled_pages_first(): void
    {
        $rows = $this->seedIndexStatuses();
        $this->actingAs($this->admin());

        Livewire::test(ManageSeoAnalyses::class)
            ->assertCanSeeTableRecords([$rows['missing'], $rows['unchecked'], $rows['indexed']])
            // Dizine girmeyen sayfa üstte olmalı; kullanıcı listeyi bunun için açıyor.
            ->assertCanSeeTableRecords([$rows['missing'], $rows['indexed']], inOrder: true);
    }

    public function test_index_status_page_filters_by_submission(): void
    {
        $rows = $this->seedIndexStatuses();
        $rows['indexed']->forceFill(['index_submitted_at' => now()])->save();
        $rows['missing']->forceFill(['index_submitted_at' => now()->subDays(20), 'index_submit_error' => '403'])->save();
        $this->actingAs($this->admin());

        Livewire::test(ManageSeoAnalyses::class)
            ->filterTable('submission', 'submitted')
            ->assertCanSeeTableRecords([$rows['indexed']])
            ->assertCanNotSeeTableRecords([$rows['missing'], $rows['unchecked']])
            ->filterTable('submission', 'failed')
            ->assertCanSeeTableRecords([$rows['missing']])
            ->assertCanNotSeeTableRecords([$rows['indexed'], $rows['unchecked']])
            ->filterTable('submission', 'never')
            ->assertCanSeeTableRecords([$rows['unchecked']])
            ->assertCanNotSeeTableRecords([$rows['indexed'], $rows['missing']]);
    }

    public function test_index_status_page_filters_by_status(): void
    {
        $rows = $this->seedIndexStatuses();
        $this->actingAs($this->admin());

        Livewire::test(ManageSeoAnalyses::class)
            ->filterTable('google_index_status', 'FAIL')
            ->assertCanSeeTableRecords([$rows['missing']])
            ->assertCanNotSeeTableRecords([$rows['indexed'], $rows['unchecked']]);
    }

    public function test_menu_badge_counts_only_pages_missing_from_the_index(): void
    {
        $this->assertNull(SeoAnalysisResource::getNavigationBadge(), 'Sorun yokken rozet gösterilmemeli.');

        $this->seedIndexStatuses();

        $this->assertSame('1', SeoAnalysisResource::getNavigationBadge());
    }

    public function test_google_status_text_is_shown_in_turkish(): void
    {
        $rows = $this->seedIndexStatuses();

        $this->assertSame('Google bu adresi hiç görmemiş', $rows['missing']->index_detail_label);

        // Listede olmayan bir metin uydurulmaz, olduğu gibi gösterilir.
        $rows['missing']->update(['google_index_detail' => 'Something new from Google · ALLOWED']);

        $this->assertSame(
            'Something new from Google · robots.txt izin veriyor',
            $rows['missing']->fresh()->index_detail_label
        );

        $this->assertNull($rows['unchecked']->index_detail_label);
    }

    /** Kayıtlar analiz görevinin ürünü; panelden elle eklenmez. */
    public function test_index_status_records_cannot_be_created_by_hand(): void
    {
        $this->assertFalse(SeoAnalysisResource::canCreate());
    }

    public function test_seo_pages_require_login(): void
    {
        foreach (['/admin/seo-merkezi/seo-dashboard', '/admin/seo-merkezi/seo-ai-settings', '/admin/seo-merkezi/duplicate-cleaner', '/admin/seo-merkezi/seo-keywords'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
    }

    public function test_settings_page_saves_and_takes_effect(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SeoAiSettings::class)
            ->fillForm([
                'internal_links_enabled' => true,
                'internal_links_max_per_article' => 3,
                'duplicate_scan_threshold' => '0.62',
                'blog_daily_count' => 2,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flush();

        $this->assertTrue(SeoConfig::bool('internal_links_enabled'));
        $this->assertSame(3, SeoConfig::int('internal_links_max_per_article'));
        $this->assertSame(0.62, SeoConfig::threshold('duplicate_scan_threshold'));

        // Ayar gerçekten motoru etkiliyor mu?
        $this->assertTrue(app(LinkApplier::class)->enabled());
    }

    public function test_site_settings_page_does_not_clobber_seo_settings(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        SeoConfig::set('internal_links_enabled', true);
        Setting::flush();

        // İki ayrı ayar sayfası aynı tabloyu kullanır; biri diğerinin anahtarlarını silmemeli.
        Livewire::test(SiteSettings::class)->call('save')->assertHasNoFormErrors();

        Setting::flush();
        $this->assertTrue(SeoConfig::bool('internal_links_enabled'));
    }

    public function test_keyword_ownership_is_enforced_from_the_panel(): void
    {
        $this->seedSample();
        $this->actingAs($this->admin());

        $keyword = SeoKeyword::first();
        $target = SeoTarget::where('target_type', 'service')->firstOrFail();
        $blog = Blog::first();

        $keyword->update(['target_id' => $target->getKey(), 'owner_blog_id' => $blog->getKey()]);

        $this->assertNull($keyword->refresh()->owner_blog_id, 'tek sahip kuralı panelde de geçerli');
    }

    public function test_ai_topic_action_is_hidden_without_an_api_key(): void
    {
        config(['seo.ai.api_key' => null]);
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Anahtar yoksa AI aksiyonu görünmez, hata vermez (spec §10.5).
        Livewire::test(ListBlogs::class)
            ->assertActionHidden('generate');

        config(['seo.ai.api_key' => 'test-key']);

        Livewire::test(ListBlogs::class)
            ->assertActionVisible('generate');
    }

    /**
     * SEO araçları tek "SEO Merkezi" menüsünde; ekranlar arası geçiş üstteki buton
     * şeridiyle (tavsiyemiz SEO paneli adları ve sırası).
     */
    public function test_seo_tools_live_under_one_menu_with_a_top_button_bar(): void
    {
        $this->actingAs($this->admin());

        $html = $this->get('/admin/seo-merkezi/seo-dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('fi-page-sub-navigation-tabs', $html);

        $labels = ['Dashboard', 'GEO', 'Sayfalar', 'Kelime Sırası', 'Anahtar Kelimeler', 'İç Linkleme', 'Analytics',
            'Yönlendirmeler', '404 Yöneticisi', 'Duplicate', 'Index Kontrol', 'Blog Üretici', 'AI Logları', 'Ayarlar'];
        $tabs = substr($html, strpos($html, 'fi-page-sub-navigation-tabs'));
        $last = 0;

        foreach ($labels as $label) {
            $found = preg_match('/>\s*'.preg_quote(e($label), '/').'\s*</u', $tabs, $m, PREG_OFFSET_CAPTURE);
            $this->assertSame(1, $found, $label.' sekmesi yok');
            $pos = $m[0][1];
            $this->assertGreaterThan($last, $pos, $label.' sırası yanlış');
            $last = $pos;
        }

        // Sol menüde eski grup başlıkları yok; tek giriş "SEO Merkezi".
        $sidebar = substr($html, 0, strpos($html, 'fi-page-sub-navigation-tabs'));
        $this->assertStringContainsString('SEO Merkezi', $sidebar);
        $this->assertStringNotContainsString('>SEO &amp; AI<', $sidebar);
        $this->assertStringNotContainsString('>Gözlem<', $sidebar);

        // Kümenin kendi adresi ilk sekmeye yönlenir.
        $this->get('/admin/seo-merkezi')->assertRedirect();
    }
}
