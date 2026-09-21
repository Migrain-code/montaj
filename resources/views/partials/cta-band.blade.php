<section class="cta-band">
    <x-bg-picture :path="setting('cta_image')" :mobile="[720, 900]" :widths="[1280, 1920]" />
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h2>{{ $ctaTitle ?? setting('cta_title') }}</h2>
                <p>{{ $ctaText ?? setting('cta_text') }}</p>
            </div>
            <div class="col-lg-5">
                <div class="cta-actions">
                    <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Teklif Al</a>
                    @if (site_phone())
                        <a class="btn btn-outline-white btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Hemen Ara</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
