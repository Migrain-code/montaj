@extends('layouts.app')

@section('content')
    {{-- Hero --}}
    <section class="hero" style="background-image:url('{{ media_url(setting('hero_image'), asset('images/placeholder.svg')) }}')">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    @if (setting('hero_badge'))
                        <span class="hero-badge"><i class="fa-solid fa-location-dot"></i>{{ setting('hero_badge') }}</span>
                    @endif
                    <h1>{!! preg_replace('/(Mobilya Montaj)/iu', '<span>$1</span>', e(setting('hero_title'))) !!}</h1>
                    <p class="lead">{{ setting('hero_subtitle') }}</p>
                    @php $bullets = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) setting('hero_bullets')))); @endphp
                    @if ($bullets)
                        <ul class="hero-list">
                            @foreach ($bullets as $bullet)
                                <li><i class="fa-solid fa-circle-check"></i>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="hero-actions">
                        <a class="btn btn-whatsapp btn-lg" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
                        @if (site_phone())
                            <a class="btn btn-outline-white btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Hemen Ara</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Güven şeridi --}}
    @if ($trustItems->isNotEmpty())
        <div class="trust-strip">
            <div class="container">
                <div class="trust-card">
                    @foreach ($trustItems as $item)
                        <div class="trust-item">
                            <div class="icon"><i class="{{ $item->icon_class }}"></i></div>
                            <h3>{{ $item->title }}</h3>
                            @if ($item->description)<p>{{ $item->description }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Hizmetler --}}
    <section class="section" id="hizmetler">
        <div class="container">
            <x-section-title subtitle="Hizmetlerimiz" title="Her Türlü Mobilya Montajı" text="Gardıroptan TV ünitesine, IKEA'dan ofis mobilyasına kadar tüm hazır mobilyaları kendi ekipmanımızla kuruyoruz." :center="true" />
            <div class="row g-4">
                @foreach ($services as $service)
                    <div class="col-xl-3 col-md-6 reveal">
                        @include('partials.service-card', ['service' => $service])
                    </div>
                @endforeach
            </div>
            @if ($otherServices->isNotEmpty())
                <div class="text-center mt-5">
                    <p class="text-muted mb-3">Odanın tamamını kurduruyorsanız:</p>
                    <div class="brand-strip">
                        @foreach ($otherServices as $other)
                            <a href="{{ $other->url }}">{{ $other->title }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="text-center mt-5">
                <a class="btn btn-navy" href="{{ route('services.index') }}">Tüm Hizmetler <i class="fa-solid fa-arrow-right ms-2 me-0"></i></a>
            </div>
        </div>
    </section>

    {{-- Montajını yaptığımız markalar --}}
    @if ($brands->isNotEmpty())
        <section class="section pt-0" id="markalar">
            <div class="container">
                <x-section-title subtitle="Markalar" title="Hangi Markaların Montajını Yapıyoruz?" text="Marka ayrımı yapmıyoruz. En sık kurduğumuz markalar aşağıda; listede olmayan bir markanın ürününü de monte ediyoruz." :center="true" />
                <div class="brand-strip reveal">
                    @foreach ($brands as $brand)
                        <a href="{{ $brand->url }}">{{ $brand->name }}</a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Hakkımızda / Neden biz --}}
    <section class="section bg-mist">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="about-images reveal">
                        <img class="img-main" src="{{ media_url(setting('about_image'), asset('images/placeholder.svg')) }}" alt="Montajı tamamlanmış modern oturma odası" loading="lazy" width="640" height="704">
                        @if (setting('about_image_2'))
                            <img class="img-secondary" src="{{ media_url(setting('about_image_2')) }}" alt="Mobilya montaj ekipmanları" loading="lazy" width="320" height="320">
                        @endif
                        <div class="badge-exp"><strong>{{ $provinces->count() }} İl</strong><span>{{ $provinces->sum(fn ($p) => $p->activeDistricts->count()) }} ilçeye hizmet</span></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <x-section-title :subtitle="setting('about_subtitle')" :title="setting('about_title')" class="mb-4" />
                    <div class="content-prose mb-4">{!! setting('about_text') !!}</div>
                    @if ($whyUs->isNotEmpty())
                        <ul class="check-list two-col mb-4">
                            @foreach ($whyUs as $item)
                                <li><i class="fa-solid fa-circle-check"></i><div><strong>{{ $item->title }}</strong><span>{{ $item->description }}</span></div></li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-orange" href="{{ route('about') }}">Hakkımızda</a>
                        <a class="btn btn-outline-navy" href="{{ route('quote.create') }}"><i class="fa-solid fa-file-lines"></i>Teklif Formu</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Sayaçlar --}}
    @if ($stats->isNotEmpty())
        @php $statIcons = ['fa-solid fa-map-location-dot', 'fa-solid fa-city', 'fa-solid fa-screwdriver-wrench', 'fa-solid fa-camera']; @endphp
        <section class="stats-band" style="background-image:url('{{ media_url(setting('cta_image'), '') }}')">
            <div class="container">
                <div class="row g-3">
                    @foreach ($stats as $i => $stat)
                        <div class="col-6 col-md-3">
                            <div class="stat">
                                <i class="{{ $statIcons[$i] ?? 'fa-solid fa-check' }}"></i>
                                <div class="num" data-count="{{ $stat['value'] }}">{{ $stat['value'] }}</div>
                                <div class="label">{{ $stat['label'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Nasıl çalışıyoruz --}}
    <section class="section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 order-lg-2">
                    <x-section-title :subtitle="setting('process_subtitle')" :title="setting('process_title')" text="Tek bir mesajla başlayın; gerisini biz planlayalım." />
                    <div class="process-steps">
                        @foreach ($processSteps as $i => $step)
                            <div class="step reveal">
                                <div class="num">{{ $i + 1 }}</div>
                                <div>
                                    <h3>{{ $step->title }}</h3>
                                    <p>{{ $step->description }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <a class="btn btn-whatsapp mt-4" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Şimdi Başlayın</a>
                </div>
                <div class="col-lg-6 order-lg-1">
                    <div class="process-image reveal">
                        <img src="{{ media_url(setting('process_image'), asset('images/placeholder.svg')) }}" alt="Montaj planlaması ve ekipmanlar" loading="lazy" width="640" height="672">
                        <div class="card-float">
                            <span class="icon"><i class="fa-brands fa-whatsapp"></i></span>
                            <span><strong>Fotoğrafla teklif</strong><span>Kutunun veya ürünün fotoğrafını gönderin, net fiyat alın.</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Galeri --}}
    @if ($galleryItems->isNotEmpty())
        <section class="section bg-mist" id="galeri">
            <div class="container">
                <x-section-title subtitle="Örnek Çalışmalar" title="Yaptığımız Montajlardan" text="Tamamladığımız kurulumlardan bazı örnekler." :center="true" />
                <div data-gallery>
                    @if ($galleryCategories->count() > 1)
                        <div class="gallery-filters">
                            <button type="button" class="filter-btn active" data-filter="all">Tümü</button>
                            @foreach ($galleryCategories as $category)
                                <button type="button" class="filter-btn" data-filter="{{ $category->slug }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                    @endif
                    <div class="gallery-grid">
                        @foreach ($galleryItems as $item)
                            @include('partials.gallery-item', ['item' => $item])
                        @endforeach
                    </div>
                </div>
                <div class="text-center mt-5">
                    <a class="btn btn-navy" href="{{ route('gallery.index') }}">Tüm Galeri <i class="fa-solid fa-arrow-right ms-2 me-0"></i></a>
                </div>
            </div>
        </section>
    @endif

    {{-- Müşteri yorumları (yalnızca gerçek yorum varsa) --}}
    @if ($testimonials->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="row g-0 testimonial-split reveal">
                    <div class="col-lg-5">
                        <div class="split-img h-100" style="background-image:url('{{ media_url(setting('about_image'), asset('images/placeholder.svg')) }}')"></div>
                    </div>
                    <div class="col-lg-7">
                        <div class="split-body h-100">
                            <x-section-title subtitle="Müşteri Yorumları" title="Müşterilerimiz Ne Diyor?" :light="true" class="mb-4" />
                            <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="7000">
                                <div class="carousel-inner">
                                    @foreach ($testimonials as $i => $t)
                                        <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                                            <div class="testimonial">
                                                <div class="stars">{{ str_repeat('★', $t->rating) }}<span class="opacity-25">{{ str_repeat('★', 5 - $t->rating) }}</span></div>
                                                <p>“{{ $t->comment }}”</p>
                                                <div class="who">
                                                    <span class="avatar">{{ mb_strtoupper(mb_substr($t->name, 0, 1)) }}</span>
                                                    <span><strong>{{ $t->name }}</strong><span>{{ collect([$t->location, $t->service?->title])->filter()->implode(' · ') }}</span></span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if ($testimonials->count() > 1)
                                    <div class="carousel-indicators">
                                        @foreach ($testimonials as $i => $t)
                                            <button type="button" data-bs-target="#testimonialCarousel" data-bs-slide-to="{{ $i }}" class="{{ $i === 0 ? 'active' : '' }}" aria-label="Yorum {{ $i + 1 }}"></button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Hizmet bölgeleri --}}
    <section class="section {{ $testimonials->isNotEmpty() ? 'bg-mist' : '' }}" id="bolgeler">
        <div class="container">
            <x-section-title subtitle="Hizmet Bölgeleri" title="Trakya'nın Her Noktasına Geliyoruz" text="Tekirdağ, Edirne ve Kırklareli'nin tüm ilçelerinde mobilya montaj hizmeti." :center="true" />
            <div class="row g-4">
                @foreach ($provinces as $province)
                    <div class="col-lg-4 reveal">
                        @include('partials.region-card', ['province' => $province])
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- SSS + Teklif formu --}}
    <section class="section {{ $testimonials->isNotEmpty() ? '' : 'bg-mist' }}" id="teklif">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-6">
                    <x-section-title subtitle="Teklif Al" title="Fotoğraf Gönderin, Fiyat Alın" text="Formu doldurun; en kısa sürede telefon veya WhatsApp üzerinden size dönelim." />
                    @include('partials.quote-form', ['formId' => 'homeQuoteForm'])
                </div>
                <div class="col-lg-6">
                    <x-section-title subtitle="Sık Sorulan Sorular" title="Merak Edilenler" />
                    @include('partials.faq-accordion', ['faqs' => $faqs, 'accordionId' => 'homeFaq', 'openFirst' => true])
                    <a class="btn-link-arrow d-inline-block mt-3" href="{{ route('faq') }}">Tüm sorular <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    {{-- Ekip: yalnız sitede gösterilmesi işaretlenmiş personel --}}
    @if ($staff->isNotEmpty())
        <section class="section">
            <div class="container">
                <x-section-title subtitle="Ekibimiz" title="Montajı Kim Yapacak?"
                    text="İşinizi yapacak ekibe doğrudan ulaşabilirsiniz." :center="true" />
                @include('partials.staff-cards', ['compact' => true])
            </div>
        </section>
    @endif

    @include('partials.cta-band')
@endsection
