@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Hakkımızda',
        'subtitle' => setting('site_tagline'),
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'Hakkımızda', 'url' => route('about')]],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    <img class="img-fluid rounded-3 shadow-soft mb-4 w-100" src="{{ media_url(setting('about_image'), asset('images/placeholder.svg')) }}" alt="{{ site_name() }} ekibinin tamamladığı mobilya montajı" width="900" height="560" style="aspect-ratio:16/10;object-fit:cover">
                    <div class="content-prose">{!! setting('about_page_content') ?: setting('about_text') !!}</div>
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
