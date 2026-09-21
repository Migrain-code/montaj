<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Page;
use App\Models\Province;
use App\Models\Service;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // İstek başına bir kez: ayarlar ve menü verisi. "scoped" kayıtlar her kuyruk
        // işinin başında da sıfırlanır, böylece uzun yaşayan işçi eski veriyle kalmaz.
        $this->app->scoped(\App\Models\Setting::MEMO, fn () => \App\Models\Setting::loadFromStore());

        // Ana sayfa hem kendisi hem içindeki personel kartları için aynı listeyi ister.
        $this->app->scoped('site.staff', fn () => \App\Models\User::query()
            ->public()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->ordered()
            ->get());

        $this->app->scoped('site.nav', fn () => [
            'navServices' => Service::query()->active()->ordered()->get(['id', 'title', 'slug', 'icon']),
            'navBrands' => Brand::query()->active()->ordered()->get(['id', 'name', 'slug']),
            'navProvinces' => Province::query()->active()->ordered()->with('activeDistricts:id,province_id,name,slug')->get(['id', 'name', 'slug']),
            'footerPages' => Page::query()->active()->where('show_in_footer', true)->ordered()->get(['id', 'title', 'slug']),
        ]);
    }

    /**
     * Yetki ilkeleri.
     *
     * Model başına ayrı dosya yerine dört ortak ilke kullanılır: içerik, SEO,
     * teklif talebi ve kullanıcı. On sekiz neredeyse aynı dosya bakımı zorlaştırır
     * ve bir tanesini güncellemeyi unutmak sessiz bir yetki açığı yaratır.
     */
    private function registerPolicies(): void
    {
        $content = [
            \App\Models\Service::class, \App\Models\Province::class, \App\Models\District::class,
            \App\Models\Brand::class,
            \App\Models\Page::class, \App\Models\Blog::class, \App\Models\BlogCategory::class,
            \App\Models\GalleryItem::class, \App\Models\GalleryCategory::class,
            \App\Models\Testimonial::class, \App\Models\Faq::class, \App\Models\Feature::class,
        ];

        foreach ($content as $model) {
            Gate::policy($model, \App\Policies\ContentPolicy::class);
        }

        $seo = [
            \App\Models\SeoKeyword::class, \App\Models\SeoTarget::class,
            \App\Models\InternalLinkRule::class, \App\Models\AiGeneration::class,
            \App\Models\Redirect::class, \App\Models\NotFoundLog::class,
            \App\Models\AiCrawlerVisit::class,
        ];

        foreach ($seo as $model) {
            Gate::policy($model, \App\Policies\SeoPolicy::class);
        }

        Gate::policy(\App\Models\QuoteRequest::class, \App\Policies\QuoteRequestPolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        $this->registerPolicies();

        RateLimiter::for('quote', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Header / footer / teklif formu için ortak veriler
        // Üç görünüm aynı veriyi kullanır; sorgular üç kez değil, istek başına bir kez çalışır.
        View::composer(['partials.header', 'partials.footer', 'partials.mobile-nav'], function ($view) {
            $view->with($this->app->make('site.nav'));
        });

        /*
         * Sitede gösterilecek personel.
         *
         * Müşteri, ana numaraya değil doğrudan ilgili kişiye ulaşabilsin diye
         * telefonu girilmiş ve "web sitesinde göster" işaretli personel yayınlanır.
         * Telefonu olmayan kayıt listelenmez — tıklanacak bir şey olmadan kart göstermek
         * ziyaretçiyi çıkmaza sokar.
         */
        View::composer(['partials.staff-cards', 'contact', 'home'], function ($view) {
            $view->with('staff', $this->app->make('site.staff'));
        });

        View::composer('partials.quote-form', function ($view) {
            $view->with([
                'formProvinces' => Province::query()->active()->ordered()->with('activeDistricts:id,province_id,name')->get(['id', 'name']),
                'formServices' => Service::query()->active()->ordered()->get(['id', 'title']),
                'kvkkPage' => Page::query()->active()->where('slug', 'like', '%kvkk%')->first(['id', 'title', 'slug']),
            ]);
        });
    }
}
