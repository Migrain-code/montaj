@php
    $districtMap = $formProvinces->mapWithKeys(fn ($p) => [$p->id => $p->activeDistricts->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values()])->all();
    $selProvince = old('province_id', $selectedProvince ?? '');
    $selDistrict = old('district_id', $selectedDistrict ?? '');
    $selService = old('service_id', $selectedService ?? '');
    $formId = $formId ?? 'quoteForm';
@endphp
<div class="quote-form-card">
    @if (! empty($formTitle))
        <h3 class="mb-1">{{ $formTitle }}</h3>
    @endif
    @if (! empty($formText))
        <p class="text-muted mb-4">{{ $formText }}</p>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Formda eksik veya hatalı alanlar var:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('quote.store') }}" enctype="multipart/form-data" data-quote-form id="{{ $formId }}" novalidate>
        @csrf
        <input type="hidden" name="source" value="{{ $source ?? 'form' }}">
        <input type="hidden" name="page_url" value="{{ url()->current() }}">
        <div class="d-none" aria-hidden="true"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <script type="application/json" data-districts>{!! json_encode($districtMap, JSON_UNESCAPED_UNICODE) !!}</script>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="{{ $formId }}-name">Ad Soyad</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="{{ $formId }}-name" name="name" value="{{ old('name') }}" required autocomplete="name">
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="{{ $formId }}-phone">Telefon</label>
                <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="{{ $formId }}-phone" name="phone" value="{{ old('phone') }}" placeholder="05xx xxx xx xx" required autocomplete="tel">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ $formId }}-province">İl</label>
                <select class="form-select @error('province_id') is-invalid @enderror" id="{{ $formId }}-province" name="province_id">
                    <option value="">İl seçin</option>
                    @foreach ($formProvinces as $province)
                        <option value="{{ $province->id }}" @selected((string) $selProvince === (string) $province->id)>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ $formId }}-district">İlçe</label>
                <select class="form-select @error('district_id') is-invalid @enderror" id="{{ $formId }}-district" name="district_id" data-selected="{{ $selDistrict }}">
                    <option value="">Önce il seçin</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ $formId }}-service">Hizmet</label>
                <select class="form-select @error('service_id') is-invalid @enderror" id="{{ $formId }}-service" name="service_id">
                    <option value="">Hizmet seçin</option>
                    @foreach ($formServices as $service)
                        <option value="{{ $service->id }}" @selected((string) $selService === (string) $service->id)>{{ $service->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ $formId }}-date">Tercih edilen tarih</label>
                <input type="date" class="form-control @error('preferred_date') is-invalid @enderror" id="{{ $formId }}-date" name="preferred_date" value="{{ old('preferred_date') }}" min="{{ now()->toDateString() }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="{{ $formId }}-message">Açıklama</label>
                <textarea class="form-control @error('message') is-invalid @enderror" id="{{ $formId }}-message" name="message" rows="3" placeholder="Örn: 3 kapaklı sürgülü gardırop, paketli halde; 4. kat, asansör var.">{{ old('message') }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label required" for="{{ $formId }}-photos">
                    Fotoğraf ekleyin <span class="text-muted fw-normal">(1–5 adet)</span>
                </label>
                <div class="photo-drop @error('photos') is-invalid @enderror">
                    <i class="fa-solid fa-camera"></i>
                    <strong>Mobilyanın, kutunun veya kurulacak alanın fotoğrafını ekleyin</strong>
                    <div class="form-text">
                        Fiyatı fotoğrafa bakarak veriyoruz: parça sayısı, kapak/çekmece adedi ve
                        kurulacak alan fiyatı belirler. En az bir fotoğraf gerekli.
                    </div>
                    <div class="form-text">JPG, PNG veya WebP · her biri en fazla 5 MB</div>
                    <input type="file" id="{{ $formId }}-photos" name="photos[]"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required>
                </div>
                @error('photos')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div class="form-text" data-photo-count></div>
                <div class="photo-previews" data-photo-previews></div>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input @error('kvkk') is-invalid @enderror" type="checkbox" name="kvkk" value="1" id="{{ $formId }}-kvkk" @checked(old('kvkk')) required>
                    <label class="form-check-label" for="{{ $formId }}-kvkk">
                        @if ($kvkkPage)
                            <a href="{{ $kvkkPage->url }}" target="_blank">{{ $kvkkPage->title }}</a>'ni okudum, kişisel verilerimin talebimin değerlendirilmesi amacıyla işlenmesini kabul ediyorum.
                        @else
                            Kişisel verilerimin talebimin değerlendirilmesi amacıyla işlenmesini kabul ediyorum.
                        @endif
                    </label>
                </div>
            </div>
            <div class="col-12 d-grid d-md-flex gap-2 pt-2">
                <button type="submit" class="btn btn-orange btn-lg"><i class="fa-solid fa-paper-plane"></i>Teklif İste</button>
                <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
            </div>
            <div class="col-12">
                <p class="form-text mb-0">
                    Fotoğraf çekemiyorsanız WhatsApp'tan yazın, birlikte halledelim.
                </p>
            </div>
        </div>
    </form>
</div>
