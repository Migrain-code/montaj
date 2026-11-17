<?php

namespace Tests\Feature;

use App\Jobs\SubmitIndexNow;
use App\Models\Blog;
use App\Models\District;
use App\Models\Province;
use App\Services\Seo\IndexNow;
use App\Support\SeoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '0123456789abcdef0123456789abcdef';

    private const HOST = 'ornek-montaj.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://'.self::HOST]);
        url()->forceRootUrl('https://'.self::HOST);
    }

    private function publishedPost(): Blog
    {
        return Blog::query()->create([
            'title' => 'Gardırop kapağı nasıl ayarlanır', 'slug' => 'gardirop-kapagi-ayari',
            'body_html' => '<p>Menteşe vidaları ile kapak hizası ayarlanır.</p>', 'summary' => 'Kısa özet',
            'status' => Blog::STATUS_PUBLISHED, 'publish_at' => now()->subDay(),
        ]);
    }

    public function test_key_file_is_served_only_for_the_configured_key(): void
    {
        $this->get('/'.self::KEY.'.txt')->assertNotFound();

        config(['seo.indexnow.key' => self::KEY]);

        $this->get('/'.self::KEY.'.txt')->assertOk()->assertSee(self::KEY);
        $this->get('/ffffffffffffffffffffffffffffffff.txt')->assertNotFound();
    }

    public function test_panel_key_takes_precedence_over_the_env_key(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        SeoConfig::set('indexnow_key', 'abcdefabcdefabcdefabcdefabcdefab');

        $this->assertSame('abcdefabcdefabcdefabcdefabcdefab', app(IndexNow::class)->key());
        $this->get('/abcdefabcdefabcdefabcdefabcdefab.txt')->assertOk();
    }

    public function test_submit_posts_only_same_host_urls(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        Http::fake([IndexNow::ENDPOINT => Http::response('', 200)]);

        $sent = app(IndexNow::class)->submit([
            'https://'.self::HOST.'/tekirdag',
            'https://baska-site.com/x',
        ]);

        $this->assertSame(1, $sent);
        Http::assertSent(fn ($request) => $request['host'] === self::HOST
            && $request['key'] === self::KEY
            && $request['keyLocation'] === 'https://'.self::HOST.'/'.self::KEY.'.txt'
            && $request['urlList'] === ['https://'.self::HOST.'/tekirdag']);
    }

    public function test_nothing_is_sent_without_a_key(): void
    {
        Http::fake();

        $this->assertSame(0, app(IndexNow::class)->submit(['https://'.self::HOST.'/tekirdag']));
        Http::assertNothingSent();
    }

    public function test_publishing_a_post_queues_a_notification(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        Queue::fake();

        $post = $this->publishedPost();
        $post->update(['body_html' => $post->body_html.'<p>Güncelleme.</p>']);

        Queue::assertPushed(SubmitIndexNow::class, fn ($job) => in_array($post->url, $job->urls, true));
    }

    public function test_draft_post_is_not_notified(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        Queue::fake();

        Blog::query()->create(['title' => 'Taslak', 'slug' => 'taslak-yazi', 'body_html' => '<p>x</p>', 'status' => Blog::STATUS_DRAFT]);

        Queue::assertNotPushed(SubmitIndexNow::class);
    }

    /** Bölge sayfası yalnız METNİ değişince bildirilir; kısa açıklama değişikliği bildirim üretmez. */
    public function test_region_is_notified_only_when_its_text_changes(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        Queue::fake();

        $district = District::where('slug', 'cerkezkoy')->firstOrFail();
        $district->update(['description' => 'kısa değişiklik']);
        Queue::assertNotPushed(SubmitIndexNow::class);

        $district->update(['content' => '<p>'.str_repeat('Çerkezköy özgün metin kelime ', 40).'</p>']);
        Queue::assertPushed(SubmitIndexNow::class, fn ($job) => $job->urls === [$district->url]);
    }

    public function test_inactive_region_is_never_notified(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        Queue::fake();

        $province = Province::where('slug', 'edirne')->firstOrFail();
        $province->update(['is_active' => false, 'content' => '<p>'.str_repeat('Edirne metin ', 40).'</p>']);

        Queue::assertNotPushed(SubmitIndexNow::class);
    }

    public function test_command_submits_the_sitemap_urls(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        Http::fake([IndexNow::ENDPOINT => Http::response('', 202)]);

        $this->artisan('seo:indexnow', ['--all' => true])->assertSuccessful();

        Http::assertSent(fn ($request) => in_array(url('/tekirdag/cerkezkoy'), $request['urlList'], true)
            && in_array(url('/tekirdag'), $request['urlList'], true));
    }

    /** Panelde yalnız "0 / N" görünüyordu; ret nedeni artık çıktıda yazar. */
    public function test_command_prints_why_indexnow_rejected(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        Http::fake([
            IndexNow::ENDPOINT => Http::response('', 403),
            IndexNow::FALLBACK_ENDPOINT => Http::response('', 403),
        ]);

        $this->artisan('seo:indexnow', ['--all' => true])
            ->expectsOutputToContain('IndexNow 403')
            ->assertFailed();
    }

    /** Kardeş sitede Bing 403 "UserForbiddedToAccessSite" verirken Yandex aynı anahtarı 202 ile kabul etti. */
    public function test_falls_back_to_yandex_when_the_main_endpoint_rejects(): void
    {
        config(['seo.indexnow.key' => self::KEY]);
        Http::fake([
            IndexNow::ENDPOINT => Http::response('{"errorCode":"UserForbiddedToAccessSite"}', 403),
            IndexNow::FALLBACK_ENDPOINT => Http::response('', 202),
        ]);

        $indexNow = app(IndexNow::class);
        $sent = $indexNow->submit(['https://'.self::HOST.'/tekirdag']);

        $this->assertSame(1, $sent);
        $this->assertNull($indexNow->lastError);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === IndexNow::FALLBACK_ENDPOINT);
    }

    public function test_saving_content_refreshes_the_cached_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertSee(url('/tekirdag/cerkezkoy').'<', false);

        District::where('slug', 'cerkezkoy')->firstOrFail()->update(['is_active' => false]);

        $this->get('/sitemap.xml')->assertDontSee(url('/tekirdag/cerkezkoy').'<', false);
    }

    public function test_blog_feed_lists_published_posts(): void
    {
        $post = $this->publishedPost();

        $this->get('/blog/feed.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<link>'.$post->url.'</link>', false);

        $this->get('/')->assertSee('type="application/rss+xml"', false);
    }
}
