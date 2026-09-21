@php
    $phone = site_phone();
    $email = setting('email');
    $hours = setting('working_hours');
    $socials = array_filter([
        'facebook' => setting('facebook_url'),
        'instagram' => setting('instagram_url'),
        'youtube' => setting('youtube_url'),
    ]);
@endphp
<div class="topbar d-none d-lg-block">
    <div class="container d-flex align-items-center">
        <ul class="topbar-list">
            @if ($phone)
                <li><i class="fa-solid fa-phone"></i><a href="{{ phone_href() }}">{{ $phone }}</a></li>
            @endif
            @if ($hours)
                <li><i class="fa-regular fa-clock"></i><span>{{ $hours }}</span></li>
            @endif
            @if ($email)
                <li><i class="fa-regular fa-envelope"></i><a href="mailto:{{ $email }}">{{ $email }}</a></li>
            @endif
        </ul>
        <ul class="topbar-social ms-auto">
            @foreach ($socials as $network => $url)
                <li><a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><i class="fa-brands fa-{{ $network }}"></i></a></li>
            @endforeach
            <li><a class="wa-link" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp'tan Yazın</a></li>
        </ul>
    </div>
</div>

<header class="site-header" id="siteHeader">
    <div class="container">
        <nav class="navbar navbar-expand-lg p-0">
            <a class="brand" href="{{ route('home') }}" aria-label="{{ site_name() }} ana sayfa">
                {{-- Panelden logo yüklenmişse o kullanılır; yoksa marka dosyası. --}}
                <img src="{{ setting('logo') ? media_url(setting('logo')) : versioned_asset('images/brand/logo-horizontal-520.webp') }}"
                     alt="{{ site_name() }} — mobilya montaj, Tekirdağ" width="260" height="94">
            </a>

            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Menüyü aç">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="d-none d-lg-flex align-items-center flex-grow-1">
                <ul class="navbar-nav main-nav mx-auto">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Ana Sayfa</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('services.*') ? 'active' : '' }}" href="{{ route('services.index') }}">Hizmetler</a>
                        <ul class="dropdown-menu">
                            @foreach ($navServices as $service)
                                <li><a class="dropdown-item" href="{{ $service->url }}"><i class="{{ $service->icon_class }} me-2 text-accent"></i>{{ $service->title }}</a></li>
                            @endforeach
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item fw-bold" href="{{ route('services.index') }}">Tüm Hizmetler</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('brands.*') ? 'active' : '' }}" href="{{ route('brands.index') }}">Markalar</a>
                        <ul class="dropdown-menu dropdown-columns">
                            @foreach ($navBrands as $brand)
                                <li><a class="dropdown-item" href="{{ $brand->url }}">{{ $brand->name }}</a></li>
                            @endforeach
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item fw-bold" href="{{ route('brands.index') }}">Tüm Markalar</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('regions.*') ? 'active' : '' }}" href="{{ route('regions.index') }}">Bölgeler</a>
                        <div class="dropdown-menu dropdown-mega">
                            <div class="row">
                                @foreach ($navProvinces as $province)
                                    <div class="col mega-col">
                                        <h6><a href="{{ $province->url }}">{{ $province->name }}</a></h6>
                                        @foreach ($province->activeDistricts->take(9) as $district)
                                            <a class="district" href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a>
                                        @endforeach
                                        @if ($province->activeDistricts->count() > 9)
                                            <a class="district fw-semibold text-accent" href="{{ $province->url }}">Tüm ilçeler →</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('gallery.*') ? 'active' : '' }}" href="{{ route('gallery.index') }}">Galeri</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('blog.*') ? 'active' : '' }}" href="{{ route('blog.index') }}">Blog</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">Hakkımızda</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">İletişim</a></li>
                </ul>
                <div class="header-cta d-flex align-items-center gap-3">
                    @if ($phone)
                        <a class="header-phone d-none d-xxl-inline-flex" href="{{ phone_href() }}">
                            <span class="icon"><i class="fa-solid fa-phone"></i></span>
                            <span><small>Hemen Ara</small>{{ $phone }}</span>
                        </a>
                    @endif
                    <a class="btn btn-orange" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Teklif Al</a>
                </div>
            </div>
        </nav>
    </div>
</header>

@include('partials.mobile-nav')
