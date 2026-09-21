<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Services\InternalLink\LinkApplier;
use App\Services\InternalLink\RuleBuilder;
use App\Services\Seo\TargetSynchroniser;
use App\Support\SeoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * İç linkler alan adından BAĞIMSIZ yazılmalı.
 *
 * Link motoru gövdeyi veritabanına kalıcı yazar. Tam adres (url()) yazılırsa o
 * anki alan adı içeriğe gömülür: yerelde üretilen içerik canlıya taşındığında
 * bütün iç linkler 127.0.0.1'e gider ve kırılır.
 */
class InternalLinkPortabilityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_applied_links_are_root_relative(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();
        SeoConfig::set('internal_links_enabled', true);
        \App\Models\Setting::flush();

        $html = app(LinkApplier::class)->apply(
            '<p>Çorlu mobilya montaj ve gardırop kurulumu hakkında bilgi.</p>',
            'blog',
            '/blog/ornek',
        );

        $this->assertStringContainsString('class="internal-link"', $html, 'Kural uygulanmadı; test anlamsız kalır.');
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringNotContainsString('http', $html);
        $this->assertMatchesRegularExpression('#<a href="/[a-z0-9\-/]+" class="internal-link">#', $html);
    }

    public function test_migration_strips_local_hosts_from_stored_content(): void
    {
        $brand = Brand::query()->whereNull('service_id')->firstOrFail();

        DB::table('brands')->where('id', $brand->id)->update(['content' => implode('', [
            '<p><a href="http://127.0.0.1:8000/tekirdag/corlu" class="internal-link">Çorlu</a></p>',
            '<p><a href="http://localhost/gardirop-montaji">gardırop</a></p>',
            '<p><a href="http://127.0.0.1:8000">ana sayfa</a></p>',
            // Gerçek dış bağlantılara ve benzer görünen alan adlarına dokunulmamalı.
            '<p><a href="https://policies.google.com/privacy">Google</a></p>',
            '<p><a href="http://localhost.ornek.com/x">benzer ad</a></p>',
        ])]);

        $migration = require database_path('migrations/2026_09_21_000005_make_internal_links_root_relative.php');
        $migration->up();

        $content = DB::table('brands')->where('id', $brand->id)->value('content');

        $this->assertStringContainsString('href="/tekirdag/corlu"', $content);
        $this->assertStringContainsString('href="/gardirop-montaji"', $content);
        $this->assertStringContainsString('href="/"', $content);
        $this->assertStringContainsString('href="https://policies.google.com/privacy"', $content);
        $this->assertStringContainsString('href="http://localhost.ornek.com/x"', $content);
        $this->assertStringNotContainsString('127.0.0.1', $content);

        // İkinci çalıştırma hiçbir şeyi bozmamalı.
        $migration->up();
        $this->assertSame($content, DB::table('brands')->where('id', $brand->id)->value('content'));
    }
}
