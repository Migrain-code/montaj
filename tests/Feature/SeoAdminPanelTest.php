<?php

namespace Tests\Feature;

use App\Models\AiCrawlerVisit;
use App\Models\AiGeneration;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\InternalLinkRule;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\SeoAnalysis;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Setting;
use App\Models\User;
use App\Filament\Pages\SeoAiSettings;
use App\Filament\Resources\SeoAnalyses\Pages\ManageSeoAnalyses;
use App\Filament\Resources\SeoAnalyses\SeoAnalysisResource;
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
            '/admin/seo-dashboard',
            '/admin/seo-ai-settings',
            '/admin/duplicate-cleaner',
            '/admin/internal-link-suggestions',
            '/admin/blogs', '/admin/blogs/create', '/admin/blogs/'.Blog::first()->id.'/edit',
            '/admin/blog-categories',
            '/admin/seo-keywords',
            '/admin/seo-targets',
            '/admin/internal-link-rules',
            '/admin/ai-generations',
            '/admin/redirects',
            '/admin/not-found-logs',
            '/admin/ai-crawler-visits',
            '/admin/seo-analyses',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    // ---------- Dizin durumu ----------

    /** @return array<string, \App\Models\SeoAnalysis> */
    private function seedIndexStatuses(): array
    {
        // Her içeriğin tek analiz kaydı vardır (content_type + content_id benzersiz).
        $id = 0;
        $make = function (string $url, ?string $status, ?string $checked) use (&$id) {
            return SeoAnalysis::create([
                'content_type' => 'page', 'content_id' => ++$id, 'url' => $url, 'score' => 70,
                'google_index_status' => $status,
                'google_index_detail' => $status === 'FAIL' ? 'URL is unknown to Google' : null,
                'index_checked_at' => $checked,
                'analyzed_at' => now(),
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
        foreach (['/admin/seo-dashboard', '/admin/seo-ai-settings', '/admin/duplicate-cleaner', '/admin/seo-keywords'] as $url) {
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
        $this->assertTrue(app(\App\Services\InternalLink\LinkApplier::class)->enabled());
    }

    public function test_site_settings_page_does_not_clobber_seo_settings(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        SeoConfig::set('internal_links_enabled', true);
        Setting::flush();

        // İki ayrı ayar sayfası aynı tabloyu kullanır; biri diğerinin anahtarlarını silmemeli.
        Livewire::test(\App\Filament\Pages\SiteSettings::class)->call('save')->assertHasNoFormErrors();

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
        Livewire::test(\App\Filament\Resources\Blogs\Pages\ListBlogs::class)
            ->assertActionHidden('generate');

        config(['seo.ai.api_key' => 'test-key']);

        Livewire::test(\App\Filament\Resources\Blogs\Pages\ListBlogs::class)
            ->assertActionVisible('generate');
    }
}
