<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Province;
use App\Models\Service;
use App\Models\Testimonial;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $stats = collect(range(1, 4))
            ->map(fn (int $i) => ['value' => setting("stat_{$i}_value"), 'label' => setting("stat_{$i}_label")])
            ->filter(fn (array $s) => filled($s['value']) && filled($s['label']))
            ->values();

        $faqs = Faq::query()->active()->where('show_on_home', true)->ordered()->take(6)->get();

        $jsonLd = [SchemaOrg::localBusiness()];

        if ($faqSchema = SchemaOrg::faq($faqs->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer]))) {
            $jsonLd[] = $faqSchema;
        }

        return view('home', [
            'services' => Service::query()->active()->where('is_featured', true)->ordered()->get(),
            'brands' => Brand::query()->active()->ordered()->get(['id', 'name', 'slug']),
            // Öne çıkmayan hizmetler kart ızgarasını 13'e çıkarıp bozmasın diye
            // ayrı bir şerit olarak gösterilir; ana sayfa linkini yine de alırlar.
            'otherServices' => Service::query()->active()->where('is_featured', false)->ordered()->get(['id', 'title', 'slug']),
            'trustItems' => Feature::query()->active()->ofType(Feature::TYPE_TRUST)->ordered()->get(),
            'whyUs' => Feature::query()->active()->ofType(Feature::TYPE_WHY_US)->ordered()->get(),
            'processSteps' => Feature::query()->active()->ofType(Feature::TYPE_PROCESS)->ordered()->get(),
            'galleryItems' => GalleryItem::query()->active()->where('show_on_home', true)->with('category')->ordered()->take(8)->get(),
            'galleryCategories' => GalleryCategory::query()->ordered()->whereHas('items', fn ($q) => $q->where('is_active', true)->where('show_on_home', true))->get(),
            'testimonials' => Testimonial::query()->active()->ordered()->take(9)->get(),
            'provinces' => Province::query()->active()->ordered()->with('activeDistricts')->get(),
            'faqs' => $faqs,
            'stats' => $stats,
            'metaTitle' => setting('meta_title', site_name().' | '.setting('site_tagline')),
            'metaDescription' => setting('meta_description'),
            'canonical' => url('/'),
            'ogImage' => media_url(setting('hero_image')),
            'jsonLd' => $jsonLd,
        ]);
    }
}
