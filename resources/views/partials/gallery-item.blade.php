<a class="gallery-item" href="{{ $item->image_url }}" data-full="{{ $item->image_url }}" data-title="{{ $item->title }}" data-alt="{{ $item->alt }}" data-category="{{ $item->category?->slug ?? 'diger' }}">
    <img src="{{ $item->image_url }}" alt="{{ $item->alt }}" loading="lazy" width="600" height="600">
    <span class="overlay">
        <span class="zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
        @if ($item->category)<span class="cat">{{ $item->category->name }}</span>@endif
        <span class="title">{{ $item->title }}</span>
    </span>
</a>
