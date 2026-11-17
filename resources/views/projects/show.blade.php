@extends('layouts.app')

@php
    $gallery = $project->gallery();
    $before = collect($gallery)->firstWhere('kind', 'before');
    $after = collect($gallery)->firstWhere('kind', 'after');
    $district = $project->district;
    $districtLive = $district && $district->is_active && $district->province?->is_active;
    $brand = $project->brand?->is_active ? $project->brand : null;
    $service = $project->service?->is_active ? $project->service : null;
@endphp

@section('content')
    @include('partials.page-banner', [
        'title' => $project->title,
        'subtitle' => $project->summary,
        'breadcrumbs' => $breadcrumbs,
    ])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-3 align-items-center mb-4 text-muted small">
                        @if ($districtLive)
                            <span><i class="fa-solid fa-location-dot text-accent me-1"></i><a href="{{ $district->url }}">{{ $district->name }}, {{ $district->province->name }}</a></span>
                        @endif
                        @if ($brand)
                            <span><i class="fa-solid fa-tag text-accent me-1"></i><a href="{{ $brand->url }}">{{ $brand->name }}</a></span>
                        @endif
                        @if ($service)
                            <span><i class="fa-solid fa-screwdriver-wrench text-accent me-1"></i><a href="{{ $service->url }}">{{ $service->title }}</a></span>
                        @endif
                        @if ($project->completed_label)
                            <span><i class="fa-regular fa-calendar text-accent me-1"></i>{{ $project->completed_label }}</span>
                        @endif
                    </div>

                    @if ($before && $after)
                        <h2 class="h3 mb-3">Öncesi ve sonrası</h2>
                        <div class="row g-3 mb-5">
                            @foreach ([[$before, 'Öncesi', 'bg-dark'], [$after, 'Sonrası', 'bg-warning text-dark']] as [$image, $label, $badge])
                                <div class="col-6">
                                    <figure class="position-relative mb-0">
                                        <img class="img-fluid rounded-3 shadow-soft w-100" src="{{ $image['url'] }}" alt="{{ $image['alt'] }}"
                                             width="600" height="800" style="aspect-ratio:3/4;object-fit:cover" @if (! $loop->first) loading="lazy" @endif>
                                        <span class="badge {{ $badge }} position-absolute top-0 start-0 m-2 px-3 py-2">{{ $label }}</span>
                                    </figure>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($cover = $project->cover())
                        <img class="img-fluid rounded-3 shadow-soft w-100 mb-5" src="{{ $cover['url'] }}" alt="{{ $cover['alt'] }}"
                             width="900" height="675" style="aspect-ratio:4/3;object-fit:cover">
                    @endif

                    @if (! empty($project->items))
                        <h2 class="h3 mb-3">Bu işte kurulanlar</h2>
                        <ul class="check-list two-col mb-5">
                            @foreach ($project->items as $item)
                                <li><i class="fa-solid fa-circle-check"></i><span class="text-dark">{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    @endif

                    @if (filled($project->body))
                        <div class="content-prose mb-5">{!! $project->body !!}</div>
                    @endif

                    @if (count($gallery) > 1)
                        <h2 class="h3 mb-3">Fotoğraflar</h2>
                        <div class="gallery-grid mb-5">
                            @foreach ($gallery as $image)
                                <a class="gallery-item" href="{{ $image['url'] }}" data-full="{{ $image['url'] }}" data-title="{{ $image['caption'] ?: $image['alt'] }}" data-alt="{{ $image['alt'] }}">
                                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" width="600" height="600">
                                    <span class="overlay">
                                        <span class="zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                                        <span class="cat">{{ \App\Models\Project::KINDS[$image['kind']] }}</span>
                                        @if ($image['caption'])<span class="title">{{ $image['caption'] }}</span>@endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
                        @if (site_phone())
                            <a class="btn btn-navy btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Hemen Ara</a>
                        @endif
                    </div>

                    @if ($brand)
                        <p class="text-muted small mb-0">
                            {{ $brand->name }} adı ve logosu marka sahibine aittir. {{ site_name() }} {{ $brand->name }}
                            yetkili servisi veya bayisi değildir; bağımsız mobilya montaj hizmeti verir.
                        </p>
                    @endif

                    @if ($related->isNotEmpty())
                        <h2 class="h3 mt-5 mb-3">Diğer işlerimiz</h2>
                        <div class="row g-3">
                            @foreach ($related as $item)
                                <div class="col-md-4">
                                    @include('partials.project-card', ['project' => $item])
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta', ['ctaTitle' => 'Benzer bir iş için teklif alın', 'whatsappUrl' => $whatsappUrl])
                        <div class="sidebar-widget">
                            <h3>Hizmetlerimiz</h3>
                            <ul class="widget-list">
                                @foreach ($services as $item)
                                    <li><a class="service-list-item" href="{{ $item->url }}"><i class="{{ $item->icon_class }}"></i>{{ $item->title }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @include('partials.cta-band', ['whatsappUrl' => $whatsappUrl])
@endsection
