@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $post->title,
        'subtitle' => null,
        'breadcrumbs' => $breadcrumbs,
    ])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-3 align-items-center mb-4 text-muted small">
                        @if ($post->category)
                            <span><i class="fa-solid fa-folder text-accent me-1"></i>{{ $post->category->name }}</span>
                        @endif
                        <span><i class="fa-regular fa-calendar text-accent me-1"></i>{{ optional($post->publish_at ?? $post->created_at)->translatedFormat('d F Y') }}</span>
                        <span><i class="fa-regular fa-clock text-accent me-1"></i>{{ $post->reading_minutes }} dakika okuma</span>
                    </div>

                    @if ($post->image)
                        <img class="img-fluid rounded-3 shadow-soft mb-4 w-100" src="{{ $post->image_url }}"
                             alt="{{ $post->image_alt ?: $post->title }}" width="900" height="500" style="aspect-ratio:16/9;object-fit:cover">
                    @endif

                    {{-- İç linkler RENDER ANINDA basılır; tavanlar her istekte kontrol edilir (spec §3.6). --}}
                    <div class="content-prose mb-5">
                        {!! internal_links($post->body_html, 'blog', $post->path()) !!}
                    </div>

                    @if (! empty($post->faqs))
                        <h2 class="h3 mb-3">Sık sorulan sorular</h2>
                        <div class="mb-5">
                            @include('partials.faq-accordion', ['faqs' => $post->faqs, 'accordionId' => 'blogFaq', 'openFirst' => true])
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-whatsapp btn-lg" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
                        @if (site_phone())
                            <a class="btn btn-navy btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Hemen Ara</a>
                        @endif
                    </div>

                    @if ($related->isNotEmpty())
                        <h2 class="h3 mt-5 mb-3">İlgili yazılar</h2>
                        <div class="row g-3">
                            @foreach ($related as $item)
                                <div class="col-md-4">
                                    <a class="service-list-item" href="{{ $item->url }}"><i class="fa-regular fa-newspaper"></i>{{ $item->title }}</a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta')
                        <div class="sidebar-widget">
                            <h3>Hizmetlerimiz</h3>
                            <ul class="widget-list">
                                @foreach ($services as $service)
                                    <li><a class="service-list-item" href="{{ $service->url }}"><i class="{{ $service->icon_class }}"></i>{{ $service->title }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @include('partials.cta-band')
@endsection
