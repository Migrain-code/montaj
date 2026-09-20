<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\Province;
use App\Models\Service;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        RateLimiter::for('quote', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Header / footer / teklif formu için ortak veriler
        View::composer(['partials.header', 'partials.footer', 'partials.mobile-nav'], function ($view) {
            $view->with([
                'navServices' => Service::query()->active()->ordered()->get(['id', 'title', 'slug', 'icon']),
                'navProvinces' => Province::query()->active()->ordered()->with('activeDistricts:id,province_id,name,slug')->get(['id', 'name', 'slug']),
                'footerPages' => Page::query()->active()->where('show_in_footer', true)->ordered()->get(['id', 'title', 'slug']),
            ]);
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
