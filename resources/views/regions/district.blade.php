@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $district->name.' Mobilya Montaj Hizmeti',
        'subtitle' => $district->description,
        'breadcrumbs' => $breadcrumbs,
    ])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    <div class="content-prose mb-5">{!! $district->content !!}</div>

                    @if (! empty($district->neighborhoods))
                        <h2 class="h3 mb-3">{{ $district->name }} ilçesinde hizmet verdiğimiz mahalleler</h2>
                        <p class="text-muted">Aşağıdaki mahalle ve köylerin tamamına montaj için geliyoruz; listede olmayan adresler için de bize yazabilirsiniz.</p>
                        <div class="neighborhood-chips mb-5">
                            @foreach ($district->neighborhoods as $n)
                                <span><i class="fa-solid fa-location-dot"></i>{{ $n }}</span>
                            @endforeach
                        </div>
                    @endif

                    <h2 class="h3 mb-3">{{ $district->name }} ilçesinde sunduğumuz hizmetler</h2>
                    <div class="row g-3 mb-5">
                        @foreach ($services as $service)
                            <div class="col-md-6">
                                <a class="service-list-item" href="{{ $service->url }}"><i class="{{ $service->icon_class }}"></i>{{ $service->title }}</a>
                            </div>
                        @endforeach
                    </div>

                    @if (! empty($district->faqs))
                        <h2 class="h3 mb-3">{{ $district->name }} için sık sorulan sorular</h2>
                        <div class="mb-5">
                            @include('partials.faq-accordion', ['faqs' => $district->faqs, 'accordionId' => 'districtFaq', 'openFirst' => true])
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
                        @include('partials.sidebar-cta', ['ctaTitle' => $district->name.' için teklif alın', 'whatsappUrl' => $whatsappUrl])
                        <div class="sidebar-widget">
                            <h3>{{ $province->name }} ilindeki diğer ilçeler</h3>
                            <ul class="widget-list">
                                @foreach ($otherDistricts as $other)
                                    <li><a class="service-list-item" href="{{ url('/'.$province->slug.'/'.$other->slug) }}"><i class="fa-solid fa-location-dot"></i>{{ $other->name }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @include('partials.cta-band', ['whatsappUrl' => $whatsappUrl, 'ctaTitle' => $district->name.' bölgesinde mobilya montajı mı lazım?'])
@endsection
