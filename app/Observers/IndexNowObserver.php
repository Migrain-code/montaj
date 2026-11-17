<?php

namespace App\Observers;

use App\Jobs\SubmitIndexNow;
use App\Models\Blog;
use App\Models\District;
use App\Models\Project;
use App\Models\Province;
use App\Services\Seo\IndexNow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Bir sayfa YAYINA GİRDİĞİNDE ya da gövdesi değiştiğinde IndexNow'a bildirilir.
 *
 *  - Blog: yayında ve başlık/gövde/yayın durumu değiştiyse.
 *  - İl/ilçe: aktif, metni dolu VE metni değiştiyse (ilçenin ili de aktif olmalı).
 *  - İş: yayında ve başlık/metin/görsel/yayın durumu değiştiyse; bağlı ilçe sayfası da
 *    bildirilir (iş orada listelenir, sayfa değişmiş olur).
 *
 * Kapalı bir sayfa asla bildirilmez. Her kayıtta site haritası/RSS önbelleği de düşer.
 */
class IndexNowObserver
{
    public function deleted(Model $model): void
    {
        Cache::forget('sitemap.xml');
        Cache::forget('blog.feed');
    }

    public function saved(Model $model): void
    {
        // Harita ve RSS önbelleği her içerik kaydında düşer: yeni yazı/sayfa bir saat
        // beklemeden haritada görünsün (önbellek yalnız elle komutla temizleniyordu).
        Cache::forget('sitemap.xml');
        Cache::forget('blog.feed');

        if (! app(IndexNow::class)->enabled()) {
            return;
        }

        $urls = match (true) {
            $model instanceof Blog => $this->blogUrls($model),
            $model instanceof Province => $this->provinceUrls($model),
            $model instanceof District => $this->districtUrls($model),
            $model instanceof Project => $this->projectUrls($model),
            default => [],
        };

        if ($urls !== []) {
            SubmitIndexNow::dispatch($urls);
        }
    }

    private function blogUrls(Blog $post): array
    {
        if (! $post->is_published || (! $post->wasChanged(['status', 'publish_at', 'title', 'body_html', 'slug']) && ! $post->wasRecentlyCreated)) {
            return [];
        }

        return [$post->url, route('blog.index')];
    }

    private function provinceUrls(Province $province): array
    {
        if (! $province->is_active || blank(strip_tags((string) $province->content))
            || ! ($province->wasChanged('content') || $province->wasRecentlyCreated)) {
            return [];
        }

        return [$province->url];
    }

    private function districtUrls(District $district): array
    {
        if (! $district->is_active || blank(strip_tags((string) $district->content))
            || ! ($district->wasChanged('content') || $district->wasRecentlyCreated)) {
            return [];
        }

        $district->loadMissing('province');

        return $district->province?->is_active ? [$district->url] : [];
    }

    private function projectUrls(Project $project): array
    {
        if (! $project->is_active
            || ! ($project->wasRecentlyCreated || $project->wasChanged(['is_active', 'title', 'slug', 'body', 'summary', 'images', 'district_id']))) {
            return [];
        }

        $urls = [$project->url, route('projects.index')];
        $district = $project->district()->with('province')->first();

        if ($district?->is_active && $district->province?->is_active) {
            $urls[] = $district->url;
        }

        return $urls;
    }
}
