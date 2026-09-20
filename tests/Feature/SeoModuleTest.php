<?php

namespace Tests\Feature;

use App\Models\AiCrawlerVisit;
use App\Models\Blog;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\SeoAnalysis;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\User;
use App\Services\Discovery\LlmsTxtGenerator;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\ContentScorer;
use App\Services\Seo\KeywordPool;
use App\Services\Seo\RedirectSuggester;
use App\Services\Seo\TargetSynchroniser;
use App\Support\SeoIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoModuleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // ---------- Gözlem & yönlendirme (modül 8) ----------

    public function test_404_is_logged_without_breaking_the_response(): void
    {
        $this->get('/boyle-bir-sayfa-yok')->assertNotFound();

        $log = NotFoundLog::where('path', '/boyle-bir-sayfa-yok')->first();

        $this->assertNotNull($log);
        $this->assertSame(1, $log->hits);

        // Aynı adres tekrar istenince sayaç ARTAR, yeni satır açılmaz.
        $this->get('/boyle-bir-sayfa-yok')->assertNotFound();
        $this->assertSame(2, $log->refresh()->hits);
        $this->assertSame(1, NotFoundLog::where('path', '/boyle-bir-sayfa-yok')->count());
    }

    public function test_bot_probes_are_not_logged(): void
    {
        // Gürültü gerçek hataları gömer (spec §7.13).
        foreach (['/wp-admin/setup-config.php', '/.env', '/xmlrpc.php', '/phpmyadmin/index.php'] as $probe) {
            $this->get($probe)->assertNotFound();
        }

        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_redirect_hash_is_filled_by_the_model(): void
    {
        // Elle hash verilmiyor; model doldurmalı (spec §7.10).
        $redirect = Redirect::create([
            'from_path' => '/Eski/Adres/',
            'to_path' => '/tekirdag/corlu',
        ]);

        $this->assertSame(md5('/eski/adres'), $redirect->from_hash);
        $this->assertSame('/eski/adres', $redirect->from_path);

        $this->get('/eski/adres')->assertRedirect(url('/tekirdag/corlu'));
        $this->assertSame(1, $redirect->refresh()->hits);
    }

    public function test_redirect_chains_resolve_to_the_final_target(): void
    {
        Redirect::create(['from_path' => '/a', 'to_path' => '/b']);
        Redirect::create(['from_path' => '/b', 'to_path' => '/tekirdag/corlu']);

        $this->get('/a')->assertRedirect(url('/tekirdag/corlu'));
    }

    public function test_redirect_only_runs_on_404(): void
    {
        // Var olan bir sayfaya yönlendirme tanımlansa bile sayfa normal çalışır (spec §10.6).
        Redirect::create(['from_path' => '/gardirop-montaji', 'to_path' => '/']);

        $this->get('/gardirop-montaji')->assertOk();
    }

    public function test_redirect_suggestion_is_deterministic_and_never_invents(): void
    {
        $suggester = app(RedirectSuggester::class);

        $suggestion = $suggester->suggest('/eski-bolum/tekirdag/corlu');
        $this->assertNotNull($suggestion);
        $this->assertContains($suggestion['path'], $suggester->targets()->all(), 'hedef gerçek sayfalardan biri olmalı');

        // Hiçbir şeye benzemeyen adres için öneri ZORLANMAZ.
        $this->assertNull($suggester->suggest('/zzz-qqq-xyzabc-999'));
    }

    public function test_ai_bot_visits_are_recorded(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; ClaudeBot/1.0)')->get('/')->assertOk();

        $visit = AiCrawlerVisit::first();
        $this->assertNotNull($visit);
        $this->assertSame('ClaudeBot', $visit->bot);
        $this->assertSame(200, $visit->last_status);
    }

    public function test_ordinary_visitors_are_not_recorded(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) Safari/605.1')->get('/')->assertOk();

        $this->assertSame(0, AiCrawlerVisit::count());
    }

    // ---------- AI ajan keşfi (modül 9) ----------

    public function test_discovery_headers_are_present_on_get_and_head(): void
    {
        foreach (['get', 'head'] as $method) {
            $response = $this->$method('/');

            $response->assertOk();
            $response->assertHeader('Content-Signal', 'search=yes, ai-input=yes, ai-train=no');
            $this->assertStringContainsString('rel="llms-txt"', $response->headers->get('Link'));
            $this->assertStringContainsString('rel="sitemap"', $response->headers->get('Link'));
        }
    }

    public function test_robots_txt_welcomes_ai_agents_and_points_to_llms(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('User-agent: GPTBot', $body);
        $this->assertStringContainsString('User-agent: ClaudeBot', $body);
        $this->assertStringContainsString(url('/llms.txt'), $body);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
    }

    public function test_llms_txt_is_built_from_the_database_only(): void
    {
        $content = file_get_contents(public_path('llms.txt'));

        app(LlmsTxtGenerator::class)->generate();
        $fresh = file_get_contents(public_path('llms.txt'));

        // Her hizmet veritabanından gelmeli.
        foreach (\App\Models\Service::where('is_active', true)->pluck('title') as $title) {
            $this->assertStringContainsString($title, $fresh);
        }

        $this->assertStringContainsString(site_name(), $fresh);
        $this->assertStringContainsString(url('/llms-full.txt'), $fresh);

        file_put_contents(public_path('llms.txt'), $content); // dosyayı eski hâline getir
    }

    // ---------- Deterministik skor (modül 2) ----------

    public function test_scorer_never_calls_ai_and_is_repeatable(): void
    {
        $content = app(ContentRegistry::class)->all()->firstWhere('contentType', 'service');
        $scorer = app(ContentScorer::class);

        $first = $scorer->score($content);
        $second = $scorer->score($content);

        $this->assertSame($first, $second, 'skor deterministik olmalı');
        $this->assertGreaterThanOrEqual(0, $first['score']);
        $this->assertLessThanOrEqual(100, $first['score']);
        $this->assertSame(['meta', 'content', 'technical', 'links'], array_keys($first['scores']));
    }

    public function test_score_weights_sum_to_one_hundred(): void
    {
        $this->assertSame(100, array_sum(config('seo.score.weights')));
    }

    public function test_issue_strings_are_fixed_constants(): void
    {
        // Dashboard sayımları TAM metinle eşleşir (spec §10.4).
        $content = app(ContentRegistry::class)->all()->firstWhere('contentType', 'service');
        $result = app(ContentScorer::class)->score($content);

        foreach ($result['issues'] as $issue) {
            $this->assertContains($issue, SeoIssue::all(), "'{$issue}' sabit listede yok");
        }
    }

    public function test_scoring_command_persists_results(): void
    {
        $this->artisan('seo:score')->assertSuccessful();

        $this->assertGreaterThan(0, SeoAnalysis::count());

        $analysis = SeoAnalysis::where('content_type', 'service')->first();
        $this->assertNotNull($analysis->analyzed_at);
        $this->assertIsArray($analysis->scores);
    }

    // ---------- Kelime & hedef sahipliği (modül 1) ----------

    public function test_targets_are_derived_from_real_content(): void
    {
        app(TargetSynchroniser::class)->sync();

        $this->assertGreaterThan(0, SeoTarget::count());

        foreach (SeoTarget::where('target_type', 'service')->get() as $target) {
            $this->assertTrue(
                \App\Models\Service::where('slug', ltrim($target->url, '/'))->exists(),
                'hedef gerçek bir hizmete karşılık gelmeli',
            );
        }
    }

    public function test_a_keyword_can_have_only_one_owner(): void
    {
        app(TargetSynchroniser::class)->sync();

        $target = SeoTarget::first();
        $blog = Blog::create(['title' => 'Test', 'slug' => 'test-yazi', 'status' => Blog::STATUS_PUBLISHED]);

        $keyword = SeoKeyword::create([
            'keyword' => 'iki sahipli kelime denemesi',
            'target_id' => $target->getKey(),
            'owner_blog_id' => $blog->getKey(),
        ]);

        // Hedef kazanır, blog sahipliği DÜŞER (spec §3.1).
        $this->assertSame($target->getKey(), $keyword->target_id);
        $this->assertNull($keyword->owner_blog_id);
        $this->assertSame(SeoKeyword::ASSIGN_ACTIVE, $keyword->assignment_status);
    }

    public function test_unassigned_keyword_is_marked_as_such(): void
    {
        $keyword = SeoKeyword::create(['keyword' => 'sahipsiz deneme kelimesi']);

        $this->assertSame(SeoKeyword::ASSIGN_UNASSIGNED, $keyword->assignment_status);
        $this->assertFalse($keyword->hasOwner());
    }

    public function test_keyword_pool_excludes_assigned_and_covered_words(): void
    {
        $pool = app(KeywordPool::class);
        $before = $pool->stats();

        // Mevcut içerikte geçen bir kelime havuza GİRMEMELİ (spec §7.3).
        SeoKeyword::create(['keyword' => 'Gardırop Montajı']);

        $freshPool = app(KeywordPool::class);
        $after = $freshPool->stats();

        $this->assertSame($before['free'], $after['free'], 'içerikte geçen kelime havuzu büyütmemeli');
        $this->assertSame($before['covered'] + 1, $after['covered']);

        // Sahipsiz ve içerikte geçmeyen kelime havuza girer.
        SeoKeyword::create(['keyword' => 'bambaşka bir konu öbeği xyz']);
        $this->assertSame($before['free'] + 1, app(KeywordPool::class)->stats()['free']);
    }

    public function test_keyword_pool_loads_content_once(): void
    {
        // Kelime başına tam tarama 159 kelimede 636 sorgu eder (spec §7.3).
        $pool = app(KeywordPool::class);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $pool->available();
        $first = count(\Illuminate\Support\Facades\DB::getQueryLog());

        \Illuminate\Support\Facades\DB::flushQueryLog();
        $pool->available();
        $second = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThan(15, $first, 'içerik tek seferde yüklenmeli');
        $this->assertLessThanOrEqual(2, $second, 'ikinci çağrı önbellekten gelmeli');
    }
}
