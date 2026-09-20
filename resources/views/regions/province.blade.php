@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $province->name.' Mobilya Montaj Hizmeti',
        'subtitle' => $province->description,
        'breadcrumbs' => $breadcrumbs,
    ])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    <div class="content-prose mb-5">{!! $province->content !!}</div>

                    <h2 class="h3 mb-3">{{ $province->name }} ilinde hizmet verdiğimiz ilçeler</h2>
                    <div class="row g-3 mb-5">
                        @foreach ($province->activeDistricts as $district)
                            <div class="col-md-6">
                                <a class="service-list-item" href="{{ url('/'.$province->slug.'/'.$district->slug) }}"><i class="fa-solid fa-location-dot"></i>{{ $district->name }} mobilya montajı</a>
                            </div>
                        @endforeach
                    </div>

                    <h2 class="h3 mb-3">{{ $province->name }} genelinde sunduğumuz hizmetler</h2>
                    <div class="row g-3 mb-5">
                        @foreach ($services as $service)
                            <div class="col-md-6">
                                <a class="service-list-item" href="{{ $service->url }}"><i class="{{ $service->icon_class }}"></i>{{ $service->title }}</a>
                            </div>
                        @endforeach
                    </div>

                    @if (! empty($province->faqs))
                        <h2 class="h3 mb-3">{{ $province->name }} için sık sorulan sorular</h2>
                        <div class="mb-5">
                            @include('partials.faq-accordion', ['faqs' => $province->faqs, 'accordionId' => 'provinceFaq', 'openFirst' => true])
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
                        @if (site_phone())
                            <a class="btn btn-navy btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Hemen Ara</a>
                        @endif
                    </div>
                </div>
                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta', ['ctaTitle' => $province->name.' için teklif alın', 'whatsappUrl' => $whatsappUrl])
                        <div class="sidebar-widget">
                            <h3>Diğer iller</h3>
                            <ul class="widget-list">
                                @foreach ($otherProvinces as $other)
                                    <li><a class="service-list-item" href="{{ $other->url }}"><i class="fa-solid fa-map"></i>{{ $other->name }} mobilya montajı</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @include('partials.cta-band', ['whatsappUrl' => $whatsappUrl, 'ctaTitle' => $province->name.' bölgesinde mobilya montajı mı lazım?'])
@endsection
