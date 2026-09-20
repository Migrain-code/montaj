<div class="cta-card">
    <h3>{{ $ctaTitle ?? 'Hemen Teklif Alın' }}</h3>
    <p>{{ $ctaText ?? 'Mobilyanızın fotoğrafını gönderin, kısa sürede net fiyat verelim.' }}</p>
    <a class="btn btn-whatsapp" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
    @if (site_phone())
        <a class="cta-phone" href="{{ phone_href() }}">
            <span class="icon"><i class="fa-solid fa-phone"></i></span>
            <span><small>Hemen Ara</small><strong>{{ site_phone() }}</strong></span>
        </a>
    @endif
</div>
