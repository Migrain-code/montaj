<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Service;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('projects.index', [
            'projects' => Project::query()->active()->ordered()->with(['district.province', 'brand', 'service'])->get(),
            'metaTitle' => 'İşlerimiz | Tamamladığımız Mobilya Montajları',
            'metaDescription' => 'Trakya\'da tamamladığımız mobilya montaj işleri: öncesi ve sonrası fotoğrafları, kurulan ürünler, marka ve bölge bilgisiyle.',
            'canonical' => route('projects.index'),
            'jsonLd' => [SchemaOrg::breadcrumbs($this->crumbs())],
        ]);
    }

    public function show(string $slug): View
    {
        $project = Project::query()->active()->where('slug', $slug)
            ->with(['district.province', 'brand', 'service'])->firstOrFail();

        $breadcrumbs = $this->crumbs([['name' => $project->title, 'url' => $project->url]]);

        $related = Project::query()->active()->whereKeyNot($project->getKey())
            ->where(fn ($q) => $q
                ->when($project->district_id, fn ($q) => $q->orWhere('district_id', $project->district_id))
                ->when($project->brand_id, fn ($q) => $q->orWhere('brand_id', $project->brand_id))
                ->when($project->service_id, fn ($q) => $q->orWhere('service_id', $project->service_id)))
            ->ordered()->take(3)->get();

        // Benzer iş yoksa en yeniler: sayfa hiçbir zaman çıkmaz sokak olmasın.
        if ($related->isEmpty()) {
            $related = Project::query()->active()->whereKeyNot($project->getKey())->ordered()->take(3)->get();
        }

        return view('projects.show', [
            'pageType' => 'project',
            'project' => $project,
            'related' => $related,
            'services' => Service::query()->active()->ordered()->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $project->seo_title,
            'metaDescription' => $project->seo_description,
            'canonical' => $project->url,
            'ogImage' => $project->cover()['url'] ?? null,
            'jsonLd' => [SchemaOrg::breadcrumbs($breadcrumbs), SchemaOrg::project($project)],
            'whatsappUrl' => whatsapp_url($project->district ? $project->district->name.', '.$project->district->province?->name : null, $project->service?->title),
        ]);
    }

    private function crumbs(array $extra = []): array
    {
        return array_merge([
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'İşlerimiz', 'url' => route('projects.index')],
        ], $extra);
    }
}
