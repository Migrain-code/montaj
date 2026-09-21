@php
    $recaptcha = app(\App\Services\Security\Recaptcha::class);
@endphp

@if ($recaptcha->enabled())
    @if ($recaptcha->version() === 'v2')
        <div class="g-recaptcha mb-2" data-sitekey="{{ $recaptcha->siteKey() }}"></div>

        @once
            @push('scripts')
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            @endpush
        @endonce
    @else
        <input type="hidden" name="{{ \App\Services\Security\Recaptcha::FIELD }}">

        @once
            @push('scripts')
                <script>
                    window.__recaptcha = {
                        siteKey: @json($recaptcha->siteKey()),
                        action: @json($recaptcha->action()),
                    };
                </script>
                <script src="https://www.google.com/recaptcha/api.js?render={{ $recaptcha->siteKey() }}" async defer></script>
            @endpush
        @endonce

        {{--
            Rozet gizlendiğinde Google bu bilgilendirmeyi zorunlu tutuyor.
            Rozet, sağ alttaki WhatsApp butonuyla aynı köşeye denk geldiği için gizleniyor.
        --}}
        <p class="form-text recaptcha-notice mb-0">
            Bu form reCAPTCHA ile korunmaktadır; Google
            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener nofollow">Gizlilik Politikası</a> ve
            <a href="https://policies.google.com/terms" target="_blank" rel="noopener nofollow">Kullanım Şartları</a> geçerlidir.
        </p>
    @endif

    @error(\App\Services\Security\Recaptcha::FIELD)
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
@endif
