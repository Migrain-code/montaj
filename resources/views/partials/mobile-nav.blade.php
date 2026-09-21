<div class="offcanvas offcanvas-end mobile-nav" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header">
        <span class="brand" id="mobileNavLabel">
            <img src="{{ setting('logo') ? media_url(setting('logo')) : asset('images/brand/logo-horizontal.webp') }}"
                 alt="{{ site_name() }}" width="220" height="80">
        </span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="mnav">
            <li><a class="mnav-link" href="{{ route('home') }}">Ana Sayfa</a></li>
            <li>
                <a class="mnav-link" data-bs-toggle="collapse" href="#mnavServices" role="button" aria-expanded="false" aria-controls="mnavServices">Hizmetler <i class="fa-solid fa-chevron-down toggle-icon"></i></a>
                <ul class="collapse mnav-sub" id="mnavServices">
                    @foreach ($navServices as $service)
                        <li><a href="{{ $service->url }}">{{ $service->title }}</a></li>
                    @endforeach
                    <li><a href="{{ route('services.index') }}" class="fw-semibold">Tüm Hizmetler</a></li>
                </ul>
            </li>
            <li>
                <a class="mnav-link" data-bs-toggle="collapse" href="#mnavBrands" role="button" aria-expanded="false" aria-controls="mnavBrands">Markalar <i class="fa-solid fa-chevron-down toggle-icon"></i></a>
                <ul class="collapse mnav-sub" id="mnavBrands">
                    @foreach ($navBrands as $brand)
                        <li><a href="{{ $brand->url }}">{{ $brand->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('brands.index') }}" class="fw-semibold">Tüm Markalar</a></li>
                </ul>
            </li>
            <li>
                <a class="mnav-link" data-bs-toggle="collapse" href="#mnavRegions" role="button" aria-expanded="false" aria-controls="mnavRegions">Bölgeler <i class="fa-solid fa-chevron-down toggle-icon"></i></a>
                <ul class="collapse mnav-sub" id="mnavRegions">
                    @foreach ($navProvinces as $province)
                        <li class="sub-title"><a href="{{ $province->url }}">{{ $province->name }}</a></li>
                        @foreach ($province->activeDistricts->take(6) as $district)
                            <li><a href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a></li>
                        @endforeach
                    @endforeach
                    <li><a href="{{ route('regions.index') }}" class="fw-semibold">Tüm Bölgeler</a></li>
                </ul>
            </li>
            <li><a class="mnav-link" href="{{ route('gallery.index') }}">Galeri</a></li>
            <li><a class="mnav-link" href="{{ route('blog.index') }}">Blog</a></li>
            <li><a class="mnav-link" href="{{ route('about') }}">Hakkımızda</a></li>
            <li><a class="mnav-link" href="{{ route('faq') }}">Sık Sorulan Sorular</a></li>
            <li><a class="mnav-link" href="{{ route('contact') }}">İletişim</a></li>
        </ul>
        <div class="d-grid gap-2 mt-4">
            <a class="btn btn-whatsapp" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
            @if (site_phone())
                <a class="btn btn-outline-navy" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Hemen Ara</a>
            @endif
        </div>
    </div>
</div>
