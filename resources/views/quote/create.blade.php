@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Teklif Al',
        'subtitle' => 'Mobilyanızın fotoğrafını ekleyin, bölgenizi seçin; size net fiyat verelim.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'Teklif Al', 'url' => route('quote.create')]],
    ])

    <section class="section bg-mist">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    @include('partials.quote-form', [
                        'formId' => 'quotePageForm',
                        'formTitle' => 'Teklif / Randevu Formu',
                        'formText' => 'Ad, telefon ve en az bir fotoğraf gerekli. Diğer alanlar daha hızlı ve net fiyat vermemizi sağlar.',
                        'selectedService' => request('hizmet'),
                    ])
                </div>
                <div class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta', ['ctaTitle' => 'Form doldurmak istemiyor musunuz?', 'ctaText' => 'WhatsApp\'tan fotoğraf gönderin, aynı gün fiyat verelim.'])
                        <div class="sidebar-widget">
                            <h3>Hangi fotoğraflar işe yarar?</h3>
                            <ul class="check-list">
                                <li><i class="fa-solid fa-circle-check"></i><span>Mobilyanın veya ürün sayfasının fotoğrafı</span></li>
                                <li><i class="fa-solid fa-circle-check"></i><span>Kurulum yapılacak alanın fotoğrafı</span></li>
                                <li><i class="fa-solid fa-circle-check"></i><span>Ürün kutusunun / etiketinin fotoğrafı</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
