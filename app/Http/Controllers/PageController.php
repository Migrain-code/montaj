<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Province;
use App\Models\Service;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Kök dizindeki slug'ları çözer: önce hizmet, sonra il, sonra statik sayfa.
     */
    public function show(string $slug): View
    {
        if ($service = Service::query()->active()->where('slug', $slug)->first()) {
            return app(ServiceController::class)->show($service);
        }

        if ($province = Province::query()->active()->where('slug', $slug)->first()) {
            return app(RegionController::class)->province($province);
        }

        $page = Page::query()->active()->where('slug', $slug)->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'metaTitle' => $page->meta_title ?: $page->title,
            'metaDescription' => $page->meta_description,
            'canonical' => $page->url,
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => $page->title, 'url' => $page->url],
            ])],
        ]);
    }

    public function about(): View
    {
        return view('about', [
            'services' => Service::query()->active()->ordered()->get(),
            'metaTitle' => 'Hakkımızda | '.site_name(),
            'metaDescription' => 'Trakya bölgesinde mobilya montaj hizmeti veren ekibimiz hakkında bilgi: nasıl çalışıyoruz, nerelere geliyoruz, neden bizi tercih etmelisiniz.',
            'canonical' => route('about'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Hakkımızda', 'url' => route('about')],
            ])],
        ]);
    }

    public function faq(): View
    {
        $faqs = Faq::query()->active()->ordered()->get();

        $jsonLd = [SchemaOrg::breadcrumbs([
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Sık Sorulan Sorular', 'url' => route('faq')],
        ])];

        if ($schema = SchemaOrg::faq($faqs->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer]))) {
            $jsonLd[] = $schema;
        }

        return view('faq', [
            'faqs' => $faqs,
            'metaTitle' => 'Sık Sorulan Sorular | Mobilya Montajı',
            'metaDescription' => 'Mobilya montaj fiyatları, randevu süresi, hizmet bölgeleri ve ödeme hakkında sık sorulan sorular.',
            'canonical' => route('faq'),
            'jsonLd' => $jsonLd,
        ]);
    }
}
