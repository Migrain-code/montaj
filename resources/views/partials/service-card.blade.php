<article class="service-card {{ $compact ?? false ? 'compact' : '' }}">
    <div class="media">
        <a class="thumb" href="{{ $service->url }}" tabindex="-1" aria-hidden="true">
            <img src="{{ $service->image_url }}" alt="{{ $service->image_alt ?: $service->title }}" loading="lazy" width="600" height="450">
        </a>
        <span class="icon-badge"><i class="{{ $service->icon_class }}"></i></span>
    </div>
    <div class="body">
        <h3><a href="{{ $service->url }}">{{ $service->title }}</a></h3>
        <p>{{ $service->short_description }}</p>
        <a class="btn-link-arrow" href="{{ $service->url }}">Detaylı Bilgi <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</article>
