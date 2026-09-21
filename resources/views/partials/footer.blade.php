@php
    $phone = site_phone();
    $email = setting('email');
    $socials = array_filter([
        'facebook' => setting('facebook_url'),
        'instagram' => setting('instagram_url'),
        'youtube' => setting('youtube_url'),
    ]);
@endphp
<footer class="footer">
    <div class="footer-contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <a class="box" href="{{ phone_href() }}">
                        <span class="icon"><i class="fa-solid fa-phone"></i></span>
                        <span><strong>Telefon</strong><span>{{ $phone ?: 'Numara için ayarları düzenleyin' }}</span></span>
                    </a>
                </div>
                <div class="col-md-4">
                    <a class="box" href="{{ whatsapp_url() }}" target="_blank" rel="noopener">
                        <span class="icon"><i class="fa-brands fa-whatsapp"></i></span>
                        <span><strong>WhatsApp</strong><span>Fotoğraf gönderin, fiyat alın</span></span>
                    </a>
                </div>
                <div class="col-md-4">
                    <div class="box">
                        <span class="icon"><i class="fa-solid fa-location-dot"></i></span>
                        <span><strong>Hizmet Bölgesi</strong><span>{{ setting('service_area_text') }}</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-widgets">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-4 col-md-6">
                    <a class="brand mb-3" href="{{ route('home') }}">
                        <img src="{{ versioned_asset('images/brand/logo-horizontal-light-520.webp') }}"
                             alt="{{ site_name() }}" width="240" height="87">
                    </a>
                    <p>{{ setting('footer_text') }}</p>
                    @if ($email)
                        <p class="mt-2"><i class="fa-regular fa-envelope text-accent me-2"></i><a href="mailto:{{ $email }}">{{ $email }}</a></p>
                    @endif
                    @if (setting('working_hours'))
                        <p class="mt-1"><i class="fa-regular fa-clock text-accent me-2"></i>{{ setting('working_hours') }}</p>
                    @endif
                    @if ($socials)
                        <div class="footer-social">
                            @foreach ($socials as $network => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><i class="fa-brands fa-{{ $network }}"></i></a>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="col-lg-3 col-md-6">
                    <h3>Hizmetler</h3>
                    <ul>
                        @foreach ($navServices as $service)
                            <li><a href="{{ $service->url }}">{{ $service->title }}</a></li>
                        @endforeach
                        <li><a href="{{ route('brands.index') }}">Montajını yaptığımız markalar</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h3>Hizmet Bölgeleri</h3>
                    <ul>
                        @foreach ($navProvinces as $province)
                            <li><a href="{{ $province->url }}">{{ $province->name }} Mobilya Montaj</a></li>
                            @foreach ($province->activeDistricts->take(3) as $district)
                                <li><a href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a></li>
                            @endforeach
                        @endforeach
                        <li><a href="{{ route('regions.index') }}">Tüm bölgeler</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h3>Kurumsal</h3>
                    <ul>
                        <li><a href="{{ route('about') }}">Hakkımızda</a></li>
                        <li><a href="{{ route('gallery.index') }}">Galeri</a></li>
                        <li><a href="{{ route('blog.index') }}">Blog</a></li>
                        <li><a href="{{ route('faq') }}">S.S.S.</a></li>
                        <li><a href="{{ route('quote.create') }}">Teklif Al</a></li>
                        <li><a href="{{ route('contact') }}">İletişim</a></li>
                        @foreach ($footerPages as $page)
                            <li><a href="{{ $page->url }}">{{ $page->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} {{ site_name() }}. Tüm hakları saklıdır.</span>
            <ul>
                @foreach ($footerPages as $page)
                    <li><a href="{{ $page->url }}">{{ $page->title }}</a></li>
                @endforeach
                <li><a href="{{ route('sitemap') }}">Site Haritası</a></li>
            </ul>
        </div>
    </div>
</footer>
