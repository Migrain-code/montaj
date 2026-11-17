<?php

namespace Tests\Feature;

use App\Jobs\SubmitIndexNow;
use App\Models\Brand;
use App\Models\District;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Rules\UniquePublicSlug;
use App\Services\Seo\ContentRegistry;
use App\Support\Console\CommandCatalog;
use App\Support\SitemapUrls;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "İşlerimiz": her tamamlanan işin dizine açık kendi sayfası vardır ve bağlı olduğu
 * ilçe, marka ve hizmet sayfalarında listelenir.
 */
class ProjectPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function project(array $attributes = []): Project
    {
        return Project::create($attributes + [
            'title' => 'Almila genç odası montajı',
            'slug' => 'almila-genc-odasi-montaji',
            'summary' => 'Gardırop, kitaplık ve bazalı karyola.',
            'items' => ['Aynalı gardırop', 'Kitaplık'],
            'body' => '<p>Takım kutular hâlinde teslim edilmişti.</p>',
            'images' => [
                ['path' => 'projects/x/oncesi.webp', 'kind' => 'before', 'alt' => 'Montaj öncesi kutular'],
                ['path' => 'projects/x/sonrasi.webp', 'kind' => 'after', 'alt' => 'Kurulmuş gardırop'],
            ],
            'brand_id' => Brand::where('slug', 'almila')->value('id'),
            'service_id' => Service::where('slug', 'genc-odasi-montaji')->value('id'),
            'completed_at' => '2026-11-12',
            'is_active' => true,
        ]);
    }

    public function test_project_page_renders_with_before_after_and_structured_data(): void
    {
        $project = $this->project();

        $this->get('/islerimiz/almila-genc-odasi-montaji')
            ->assertOk()
            ->assertSee('Almila genç odası montajı')
            ->assertSee('Öncesi ve sonrası')
            ->assertSee('Aynalı gardırop')
            ->assertSee('Kasım 2026')
            ->assertSee('storage/projects/x/sonrasi.webp', false)
            ->assertSee('"@type":"ImageGallery"', false)
            ->assertSee('<link rel="canonical" href="'.$project->url.'">', false);

        $this->get('/islerimiz')->assertOk()->assertSee('Almila genç odası montajı');
    }

    public function test_cover_is_the_first_after_photo(): void
    {
        $this->assertStringEndsWith('projects/x/sonrasi.webp', $this->project()->cover_url);
    }

    public function test_unpublished_project_is_not_reachable_or_listed(): void
    {
        $this->project(['is_active' => false]);

        $this->get('/islerimiz/almila-genc-odasi-montaji')->assertNotFound();
        $this->get('/islerimiz')->assertDontSee('Almila genç odası montajı');
        $this->assertNotContains(url('/islerimiz/almila-genc-odasi-montaji'), array_column(SitemapUrls::all(), 'loc'));
    }

    public function test_project_is_listed_on_its_district_brand_and_service_pages(): void
    {
        $district = District::with('province')->where('slug', 'cerkezkoy')->firstOrFail();
        $this->project(['district_id' => $district->id]);

        $this->get($district->url)->assertOk()
            ->assertSee('Çerkezköy ilçesinde yaptığımız işler')
            ->assertSee('/islerimiz/almila-genc-odasi-montaji', false);
        $this->get('/markalar/almila')->assertOk()->assertSee('Kurduğumuz Almila mobilyaları');
        $this->get('/genc-odasi-montaji')->assertOk()->assertSee('Bu hizmetle tamamladığımız işler');

        // İşi olmayan ilçede başlık hiç basılmaz.
        $other = District::with('province')->where('slug', 'kapakli')->firstOrFail();
        $this->get($other->url)->assertOk()->assertDontSee('ilçesinde yaptığımız işler');
    }

    public function test_seo_title_gains_the_district_only_when_one_is_chosen(): void
    {
        $project = $this->project();
        $this->assertSame('Almila genç odası montajı', $project->seo_title);

        $project->update(['district_id' => District::where('slug', 'cerkezkoy')->value('id')]);
        $this->assertSame('Çerkezköy Almila genç odası montajı', $project->fresh()->seo_title);
    }

    public function test_project_is_in_sitemap_and_seo_registry(): void
    {
        $project = $this->project();

        $this->assertContains($project->url, array_column(SitemapUrls::all(), 'loc'));
        $this->assertContains(route('projects.index'), array_column(SitemapUrls::all(), 'loc'));

        $content = app(ContentRegistry::class)->forModel($project);
        $this->assertSame('project', $content->contentType);
        $this->assertTrue(app(ContentRegistry::class)->reachable()->contains(fn ($c) => $c->url === $project->url));
    }

    public function test_publishing_a_project_notifies_indexnow_with_its_district_page(): void
    {
        config(['seo.indexnow.key' => '0123456789abcdef0123456789abcdef']);
        Queue::fake();

        $district = District::with('province')->where('slug', 'cerkezkoy')->firstOrFail();
        $project = $this->project(['district_id' => $district->id]);

        Queue::assertPushed(SubmitIndexNow::class, fn ($job) => in_array($project->url, $job->urls, true)
            && in_array($district->url, $job->urls, true));
    }

    public function test_seeder_adds_projects_once_and_never_overwrites_edits(): void
    {
        $this->seed(ProjectSeeder::class);
        $this->assertSame(3, Project::count());

        $project = Project::where('slug', 'almila-genc-odasi-montaji')->firstOrFail();
        $this->assertSame(Brand::where('slug', 'almila')->value('id'), $project->brand_id);
        $this->assertNull($project->district_id, 'İlçe bilinmiyor; uydurulmaz.');
        $this->assertTrue($project->hasBeforeAfter());

        $project->update(['title' => 'Elle düzeltilmiş başlık']);
        $this->artisan('projects:seed')->assertSuccessful();

        $this->assertSame(3, Project::count());
        $this->assertSame('Elle düzeltilmiş başlık', $project->fresh()->title);
    }

    public function test_seed_command_is_available_in_the_panel(): void
    {
        $this->assertSame('projects:seed', CommandCatalog::find('projects-seed')['command']);
    }

    public function test_slug_cannot_collide_with_root_pages(): void
    {
        $this->assertContains('islerimiz', UniquePublicSlug::RESERVED);
    }

    public function test_admin_pages_render(): void
    {
        $project = $this->project();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());

        foreach (['/admin/projects', '/admin/projects/create', '/admin/projects/'.$project->id.'/edit'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
