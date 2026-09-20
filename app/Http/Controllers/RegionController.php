<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Service;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function index(): View
    {
        return view('regions.index', [
            'provinces' => Province::query()->active()->ordered()->with('activeDistricts')->get(),
            'metaTitle' => 'Hizmet Bölgeleri | Tekirdağ, Edirne ve Kırklareli',
            'metaDescription' => 'Trakya bölgesinde mobilya montaj hizmeti verdiğimiz il ve ilçeler. Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine geliyoruz.',
            'canonical' => route('regions.index'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Hizmet Bölgeleri', 'url' => route('regions.index')],
            ])],
        ]);
    }

    public function province(Province $province): View
    {
        $province->load('activeDistricts');

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Hizmet Bölgeleri', 'url' => route('regions.index')],
            ['name' => $province->name, 'url' => $province->url],
        ];

        $jsonLd = [SchemaOrg::breadcrumbs($breadcrumbs)];

        if ($faq = SchemaOrg::faq($province->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('regions.province', [
            'province' => $province,
            'services' => Service::query()->active()->ordered()->get(),
            'otherProvinces' => Province::query()->active()->ordered()->whereKeyNot($province->getKey())->with('activeDistricts')->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $province->meta_title ?: $province->name.' Mobilya Montaj Hizmeti',
            'metaDescription' => $province->meta_description ?: $province->description,
            'canonical' => $province->url,
            'jsonLd' => $jsonLd,
            'whatsappUrl' => whatsapp_url($province->name),
        ]);
    }

    public function district(Province $province, string $district): View
    {
        abort_unless($province->is_active, 404);

        /** @var District $district */
        $district = $province->activeDistricts()->where('slug', $district)->firstOrFail();
        $district->setRelation('province', $province);

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Hizmet Bölgeleri', 'url' => route('regions.index')],
            ['name' => $province->name, 'url' => $province->url],
            ['name' => $district->name, 'url' => $district->url],
        ];

        $jsonLd = [SchemaOrg::breadcrumbs($breadcrumbs)];

        if ($faq = SchemaOrg::faq($district->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('regions.district', [
            'province' => $province,
            'district' => $district,
            'services' => Service::query()->active()->ordered()->get(),
            'otherDistricts' => $province->activeDistricts()->whereKeyNot($district->getKey())->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $district->meta_title ?: $district->name.' Mobilya Montaj Hizmeti | '.$province->name,
            'metaDescription' => $district->meta_description ?: $district->description,
            'canonical' => $district->url,
            'jsonLd' => $jsonLd,
            'whatsappUrl' => whatsapp_url($district->name.', '.$province->name),
        ]);
    }
}
