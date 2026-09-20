<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Page;
use App\Models\Province;
use App\Models\Service;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $urls = [
                ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
                ['loc' => route('services.index'), 'priority' => '0.9', 'changefreq' => 'weekly'],
                ['loc' => route('regions.index'), 'priority' => '0.8', 'changefreq' => 'weekly'],
                ['loc' => route('gallery.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['loc' => route('about'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['loc' => route('faq'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['loc' => route('contact'), 'priority' => '0.7', 'changefreq' => 'monthly'],
                ['loc' => route('quote.create'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ];

            foreach (Service::query()->active()->ordered()->get() as $service) {
                $urls[] = ['loc' => $service->url, 'lastmod' => $service->updated_at, 'priority' => '0.9', 'changefreq' => 'monthly'];
            }

            foreach (Province::query()->active()->ordered()->get() as $province) {
                $urls[] = ['loc' => $province->url, 'lastmod' => $province->updated_at, 'priority' => '0.8', 'changefreq' => 'monthly'];
            }

            foreach (District::query()->active()->with('province')->get() as $district) {
                if ($district->province?->is_active) {
                    $urls[] = ['loc' => $district->url, 'lastmod' => $district->updated_at, 'priority' => '0.7', 'changefreq' => 'monthly'];
                }
            }

            foreach (Page::query()->active()->get() as $page) {
                $urls[] = ['loc' => $page->url, 'lastmod' => $page->updated_at, 'priority' => '0.3', 'changefreq' => 'yearly'];
            }

            return view('sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
