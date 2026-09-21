@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Montajını Yaptığımız Markalar',
        'subtitle' => 'Marka ayrımı yapmıyoruz. Aşağıdaki markaların ürünlerini düzenli olarak kuruyoruz.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'Markalar', 'url' => route('brands.index')]],
    ])

    <section class="section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9 text-center">
                    <p class="lead mb-5">
                        Mağazadan, bayiden veya internetten aldığınız paketli mobilyaların kurulumunu marka fark
                        etmeksizin yapıyoruz. Aşağıdaki markalar en sık montajını yaptıklarımız; listede olmayan
                        bir markanın ürününü de kuruyoruz.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                @foreach ($brands as $brand)
                    <div class="col-xl-3 col-md-6 reveal">
                        @include('partials.brand-card', ['brand' => $brand])
                    </div>
                @endforeach
            </div>

            <p class="text-muted small text-center mt-5 mb-0">
                Marka adları ve logoları ilgili sahiplerine aittir. {{ site_name() }} bu markaların yetkili servisi
                veya bayisi değildir; bağımsız mobilya montaj hizmeti verir.
            </p>
        </div>
    </section>

    @include('partials.cta-band')
@endsection
