<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Province;
use App\Models\Service;
use App\Support\SchemaOrg;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        return view('brands.index', [
            'brands' => Brand::query()->active()->ordered()->with('service:id,slug')->get(),
            'metaTitle' => 'Montajını Yaptığımız Mobilya Markaları',
            'metaDescription' => 'İstikbal, Bellona, Doğtaş, Yataş, Çilek, IKEA ve daha fazlası. Marka fark etmeksizin Tekirdağ, Edirne ve Kırklareli\'nde mobilya montaj hizmeti veriyoruz.',
            'canonical' => route('brands.index'),
            'jsonLd' => [SchemaOrg::breadcrumbs($this->crumbs())],
        ]);
    }

    public function show(Brand $brand): View|RedirectResponse
    {
        // Yayından kaldırılan marka gizlenir. Rota bağlaması yayın durumuna bakmaz.
        abort_unless($brand->is_active, 404);

        // Kendi hizmet sayfası olan marka (örn. IKEA) ayrı sayfa açmaz; kalıcı olarak
        // o hizmet sayfasına yönlenir ki iki sayfa aynı sorguda yarışmasın.
        if (! $brand->hasOwnPage()) {
            return redirect($brand->path(), 301);
        }

        $breadcrumbs = $this->crumbs([['name' => $brand->name, 'url' => url($brand->path())]]);

        $jsonLd = [SchemaOrg::breadcrumbs($breadcrumbs)];

        if ($faq = SchemaOrg::faq($brand->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('brands.show', [
            'brand' => $brand,
            'otherBrands' => Brand::query()->active()->ordered()->with('service:id,slug')->whereKeyNot($brand->getKey())->get(),
            'services' => Service::query()->active()->ordered()->get(),
            'provinces' => Province::query()->active()->ordered()->with('activeDistricts')->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $brand->meta_title ?: $brand->heading.' | Trakya Bölgesi',
            'metaDescription' => $brand->meta_description ?: $brand->description,
            'canonical' => url($brand->path()),
            'jsonLd' => $jsonLd,
            'whatsappUrl' => whatsapp_url(null, $brand->heading),
        ]);
    }

    private function crumbs(array $extra = []): array
    {
        return array_merge([
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Markalar', 'url' => route('brands.index')],
        ], $extra);
    }
}
