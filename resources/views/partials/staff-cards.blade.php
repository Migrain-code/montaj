@php
    $staff = $staff ?? collect();
    $compact = $compact ?? false;
@endphp

@if ($staff->isNotEmpty())
    <div class="row g-4">
        @foreach ($staff as $person)
            @php
                $wa = $person->whatsapp_number;
                $tel = $person->phone ? phone_href($person->phone) : null;
            @endphp
            <div class="{{ $compact ? 'col-lg-3 col-md-6' : 'col-lg-4 col-md-6' }} reveal">
                <div class="staff-card h-100">
                    <div class="staff-head">
                        @if ($person->photo_url)
                            <img class="staff-photo" src="{{ $person->photo_url }}"
                                 alt="{{ $person->name }} — {{ $person->title ?: 'Dost Montaj ekibi' }}"
                                 loading="lazy" width="72" height="72">
                        @else
                            <span class="staff-photo staff-initials">{{ $person->initials }}</span>
                        @endif
                        <div class="min-w-0">
                            <h3>{{ $person->name }}</h3>
                            @if ($person->title)
                                <span class="staff-title">{{ $person->title }}</span>
                            @endif
                        </div>
                    </div>

                    @if ($person->bio && ! $compact)
                        <p class="staff-bio">{{ $person->bio }}</p>
                    @endif

                    <div class="staff-actions">
                        @if ($wa)
                            <a class="btn btn-whatsapp btn-sm"
                               href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Merhaba, mobilya montajı için fiyat almak istiyorum.') }}"
                               target="_blank" rel="noopener">
                                <i class="fa-brands fa-whatsapp"></i>WhatsApp
                            </a>
                        @endif
                        @if ($tel)
                            <a class="btn btn-outline-navy btn-sm" href="{{ $tel }}">
                                <i class="fa-solid fa-phone"></i>{{ $person->phone }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
