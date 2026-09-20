@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Hizmet Bölgeleri',
        'subtitle' => 'Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine mobilya montajı için geliyoruz.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'Hizmet Bölgeleri', 'url' => route('regions.index')]],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4">
                @foreach ($provinces as $province)
                    <div class="col-lg-4 reveal">
                        @include('partials.region-card', ['province' => $province])
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.cta-band')
@endsection
