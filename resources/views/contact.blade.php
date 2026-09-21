@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'İletişim',
        'subtitle' => 'Telefon, WhatsApp veya form üzerinden bize ulaşın; aynı gün dönüş yapalım.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'İletişim', 'url' => route('contact')]],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 mb-5">
                @if (site_phone())
                    <div class="col-lg-3 col-md-6">
                        <div class="contact-box">
                            <span class="icon"><i class="fa-solid fa-phone"></i></span>
                            <div><h3>Telefon</h3><a href="{{ phone_href() }}">{{ site_phone() }}</a></div>
                        </div>
                    </div>
                @endif
                <div class="col-lg-3 col-md-6">
                    <div class="contact-box wa">
                        <span class="icon"><i class="fa-brands fa-whatsapp"></i></span>
                        <div><h3>WhatsApp</h3><a href="{{ whatsapp_url() }}" target="_blank" rel="noopener">Fotoğraf gönderin, teklif alın</a></div>
                    </div>
                </div>
                @if (setting('email'))
                    <div class="col-lg-3 col-md-6">
                        <div class="contact-box">
                            <span class="icon"><i class="fa-regular fa-envelope"></i></span>
                            <div><h3>E-posta</h3><!--email_off--><a href="mailto:{{ setting('email') }}">{{ setting('email') }}</a><!--/email_off--></div>
                        </div>
                    </div>
                @endif
                <div class="col-lg-3 col-md-6">
                    <div class="contact-box">
                        <span class="icon"><i class="fa-regular fa-clock"></i></span>
                        <div><h3>Çalışma Saatleri</h3><p>{{ setting('working_hours') }}</p></div>
                    </div>
                </div>
            </div>

            @if ($staff->isNotEmpty())
                <div class="mb-5">
                    <x-section-title subtitle="Ekibimiz" title="Doğrudan Ulaşın"
                        text="Aşağıdaki ekip arkadaşlarımıza doğrudan yazabilir veya arayabilirsiniz." />
                    @include('partials.staff-cards')
                </div>
            @endif

            <div class="row g-5">
                <div class="col-lg-7">
                    <x-section-title subtitle="Teklif Formu" title="Bize Yazın" text="Formu doldurun, fotoğraf ekleyin; size en kısa sürede dönelim." class="mb-4" />
                    @include('partials.quote-form', ['formId' => 'contactQuoteForm', 'source' => 'contact'])
                </div>
                <div class="col-lg-5">
                    <x-section-title subtitle="Hizmet Bölgesi" title="Nerelere Geliyoruz?" class="mb-4" />
                    <p>{{ setting('service_area_text') }} bölgesinde hizmet veriyoruz. Merkezimiz: <strong>{{ setting('address') }}</strong></p>
                    <a class="btn-link-arrow d-inline-block mb-4" href="{{ route('regions.index') }}">Tüm hizmet bölgeleri <i class="fa-solid fa-arrow-right"></i></a>
                    @if (setting('map_embed'))
                        <div class="map-embed">{!! setting('map_embed') !!}</div>
                    @endif
                    <div class="mt-4">
                        @include('partials.sidebar-cta')
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
