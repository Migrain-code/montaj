<?php

namespace App\Http\Controllers;

use App\Support\SitemapUrls;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        // Adres listesi tek kaynaktan (SitemapUrls): IndexNow ve dizin kontrolü de onu kullanır.
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn () => view('sitemap', ['urls' => SitemapUrls::all()])->render());

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
