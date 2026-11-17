<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Province;
use App\Models\Service;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('services.index', [
            'services' => Service::query()->active()->ordered()->get(),
            'metaTitle' => 'Mobilya Montaj Hizmetlerimiz',
            'metaDescription' => 'Gardırop, yatak ve baza, TV ünitesi, masa-sandalye, kitaplık, IKEA ve ofis mobilyası montajı. Tekirdağ, Edirne ve Kırklareli\'nde profesyonel mobilya kurulum hizmetleri.',
            'canonical' => route('services.index'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Hizmetler', 'url' => route('services.index')],
            ])],
        ]);
    }

    public function show(Service $service): View
    {
        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Hizmetler', 'url' => route('services.index')],
            ['name' => $service->title, 'url' => $service->url],
        ];

        $jsonLd = [
            SchemaOrg::breadcrumbs($breadcrumbs),
            SchemaOrg::service($service),
        ];

        if ($faq = SchemaOrg::faq($service->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('services.show', [
            'pageType' => 'service',
            'service' => $service,
            'otherServices' => Service::query()->active()->ordered()->whereKeyNot($service->getKey())->get(),
            'projects' => Project::query()->active()->where('service_id', $service->getKey())->with(['district.province', 'brand'])->ordered()->take(4)->get(),
            'provinces' => Province::query()->active()->ordered()->with('activeDistricts')->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $service->meta_title ?: $service->title.' | Trakya Bölgesi',
            'metaDescription' => $service->meta_description ?: $service->short_description,
            'canonical' => $service->url,
            'ogImage' => $service->image_url,
            'jsonLd' => $jsonLd,
            'whatsappUrl' => whatsapp_url(null, $service->title),
        ]);
    }
}
