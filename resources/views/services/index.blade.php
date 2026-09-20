@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Mobilya Montaj Hizmetlerimiz',
        'subtitle' => 'Her türlü hazır mobilyayı kendi ekipmanımızla, üretici kılavuzuna uygun şekilde kuruyoruz.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'Hizmetler', 'url' => route('services.index')]],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4">
                @foreach ($services as $service)
                    <div class="col-xl-3 col-md-6 reveal">
                        @include('partials.service-card', ['service' => $service])
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.cta-band')
@endsection
