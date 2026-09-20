@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $service->title,
        'subtitle' => $service->short_description,
        'breadcrumbs' => $breadcrumbs,
    ])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    <img class="img-fluid rounded-3 shadow-soft mb-4 w-100" src="{{ $service->image_url }}" alt="{{ $service->image_alt ?: $service->title }}" width="900" height="600" style="aspect-ratio:3/2;object-fit:cover">

                    <div class="content-prose mb-5">{!! $service->description !!}</div>

                    @if (! empty($service->what_we_do))
                        <h2 class="h3 mb-3">Neler yapıyoruz?</h2>
                        <ul class="check-list two-col mb-5">
                            @foreach ($service->what_we_do as $item)
                                <li><i class="fa-solid fa-circle-check"></i><span class="text-dark">{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($service->suitable_for))
                        <h2 class="h3 mb-3">Kimler için uygun?</h2>
                        <ul class="check-list two-col mb-5">
                            @foreach ($service->suitable_for as $item)
                                <li><i class="fa-solid fa-user-check"></i><span class="text-dark">{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($service->process_steps))
                        <h2 class="h3 mb-3">Çalışma süreci</h2>
                        <div class="process-steps mb-5">
                            @foreach ($service->process_steps as $i => $step)
                                <div class="step">
                                    <div class="num">{{ $i + 1 }}</div>
                                    <div><h3>{{ $step['title'] ?? '' }}</h3><p>{{ $step['description'] ?? '' }}</p></div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($service->faqs))
                        <h2 class="h3 mb-3">Sık sorulan sorular</h2>
                        <div class="mb-5">
                            @include('partials.faq-accordion', ['faqs' => $service->faqs, 'accordionId' => 'serviceFaq', 'openFirst' => true])
                        </div>
                    @endif

                    <h2 class="h3 mb-3">Hizmet verilen bölgeler</h2>
                    <p class="text-muted">{{ $service->title }} hizmetini aşağıdaki il ve ilçelerin tamamında veriyoruz.</p>
                    <div class="row g-3 mb-4">
                        @foreach ($provinces as $province)
                            <div class="col-md-4">
                                <div class="region-card">
                                    <div class="head"><span class="icon"><i class="fa-solid fa-map-location-dot"></i></span><div><h3 class="h5"><a href="{{ $province->url }}">{{ $province->name }}</a></h3></div></div>
                                    <div class="chips">
                                        @foreach ($province->activeDistricts as $district)
                                            <a href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
                        @if (site_phone())
                            <a class="btn btn-navy btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Hemen Ara</a>
                        @endif
                    </div>
                </div>

                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta', ['ctaTitle' => $service->title.' için teklif alın'])
                        <div class="sidebar-widget">
                            <h3>Diğer hizmetler</h3>
                            <ul class="widget-list">
                                @foreach ($otherServices as $other)
                                    <li><a class="service-list-item" href="{{ $other->url }}"><i class="{{ $other->icon_class }}"></i>{{ $other->title }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="sidebar-widget">
                            <h3>Teklif formu</h3>
                            <p class="text-muted small mb-3">Fotoğraf ekleyerek form üzerinden de talep oluşturabilirsiniz.</p>
                            <a class="btn btn-outline-navy w-100" href="{{ route('quote.create', ['hizmet' => $service->id]) }}"><i class="fa-solid fa-file-lines"></i>Formu Aç</a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @include('partials.cta-band', ['whatsappUrl' => $whatsappUrl])
@endsection
