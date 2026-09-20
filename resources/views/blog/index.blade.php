@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $activeCategory?->name ?? 'Blog',
        'subtitle' => $activeCategory?->description ?? 'Mobilya montajı, taşınma ve kurulum hakkında pratik bilgiler.',
        'breadcrumbs' => array_filter([
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Blog', 'url' => route('blog.index')],
            $activeCategory ? ['name' => $activeCategory->name, 'url' => $activeCategory->url] : null,
        ]),
    ])

    <section class="section">
        <div class="container">
            @if ($categories->count() > 1)
                <div class="gallery-filters">
                    <a class="filter-btn {{ $activeCategory ? '' : 'active' }}" href="{{ route('blog.index') }}">Tümü</a>
                    @foreach ($categories as $category)
                        <a class="filter-btn {{ $activeCategory?->is($category) ? 'active' : '' }}" href="{{ $category->url }}">
                            {{ $category->name }} ({{ $category->published_posts_count }})
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($posts->isEmpty())
                <p class="text-center text-muted py-5">Bu bölümde henüz yazı yok.</p>
            @else
                <div class="row g-4">
                    @foreach ($posts as $post)
                        <div class="col-lg-4 col-md-6 reveal">
                            <article class="service-card compact h-100">
                                <div class="media">
                                    <a class="thumb" href="{{ $post->url }}" tabindex="-1" aria-hidden="true">
                                        <img src="{{ $post->image_url }}" alt="{{ $post->image_alt ?: $post->title }}" loading="lazy" width="600" height="338">
                                    </a>
                                </div>
                                <div class="body">
                                    @if ($post->category)
                                        <span class="subtitle mb-2 d-inline-block">{{ $post->category->name }}</span>
                                    @endif
                                    <h3><a href="{{ $post->url }}">{{ $post->title }}</a></h3>
                                    <p>{{ $post->summary }}</p>
                                    <div class="d-flex align-items-center justify-content-between mt-auto">
                                        <a class="btn-link-arrow" href="{{ $post->url }}">Devamını Oku <i class="fa-solid fa-arrow-right"></i></a>
                                        <small class="text-muted"><i class="fa-regular fa-clock me-1"></i>{{ $post->reading_minutes }} dk</small>
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 d-flex justify-content-center">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </section>

    @include('partials.cta-band')
@endsection
