<a class="brand-card" href="{{ $brand->url }}">
    <span class="mark">
        @if ($brand->logo_url)
            <img src="{{ $brand->logo_url }}" alt="{{ $brand->name }} mobilya montajı" loading="lazy" width="140" height="60">
        @else
            <span class="wordmark">{{ $brand->name }}</span>
        @endif
    </span>
    <span class="body">
        <span class="title">{{ $brand->name }} Montajı</span>
        @if (! empty($brand->description))
            <span class="text">{{ $brand->description }}</span>
        @endif
    </span>
</a>
