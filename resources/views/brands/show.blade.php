@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $brand->heading,
        'subtitle' => $brand->description,
        'breadcrumbs' => $breadcrumbs,
    ])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    @if (! empty($brand->products))
                        <h2 class="h3 mb-3">{{ $brand->name }} ürünlerinden neleri kuruyoruz?</h2>
                        <ul class="check-list two-col mb-5">
                            @foreach ($brand->products as $item)
                                <li><i class="fa-solid fa-circle-check"></i><span class="text-dark">{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="content-prose mb-5">{!! $brand->content !!}</div>

                    @if (! empty($brand->faqs))
                        <h2 class="h3 mb-3">Sık sorulan sorular</h2>
                        <div class="mb-5">
                            @include('partials.faq-accordion', ['faqs' => $brand->faqs, 'accordionId' => 'brandFaq', 'openFirst' => true])
                        </div>
                    @endif

                    <h2 class="h3 mb-3">Hizmet verilen bölgeler</h2>
                    <p class="text-muted">{{ $brand->heading }} hizmetini aşağıdaki il ve ilçelerin tamamında veriyoruz.</p>
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

                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
                        @if (site_phone())
                            <a class="btn btn-navy btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Hemen Ara</a>
                        @endif
                    </div>

                    <p class="text-muted small mb-0">
                        {{ $brand->name }} adı ve logosu marka sahibine aittir. {{ site_name() }} {{ $brand->name }}
                        yetkili servisi veya bayisi değildir; bağımsız mobilya montaj hizmeti verir.
                    </p>
                </div>

                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta', ['ctaTitle' => $brand->heading.' için teklif alın'])
                        <div class="sidebar-widget">
                            <h3>İlgili hizmetler</h3>
                            <ul class="widget-list">
                                @foreach ($services as $service)
                                    <li><a class="service-list-item" href="{{ $service->url }}"><i class="{{ $service->icon_class }}"></i>{{ $service->title }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="sidebar-widget">
                            <h3>Diğer markalar</h3>
                            <div class="chips">
                                @foreach ($otherBrands as $other)
                                    <a href="{{ $other->url }}">{{ $other->name }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @include('partials.cta-band', ['whatsappUrl' => $whatsappUrl])
@endsection
