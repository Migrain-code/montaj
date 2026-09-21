<?php

namespace App\Http\Requests;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s()\-]{10,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'message' => ['nullable', 'string', 'max:2000'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            // Fotoğraf ZORUNLU: montaj fiyatı ürünün parça sayısına, kapak/çekmece
            // adedine ve kurulacak alana göre belirlenir. Fotoğrafsız gelen talepte
            // fiyat verilemez ve karşılıklı mesajlaşmayla zaman kaybedilir.
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'kvkk' => ['accepted'],
            'website' => ['nullable', 'max:0'], // honeypot: gerçek kullanıcılar boş bırakır
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Lütfen geçerli bir telefon numarası girin.',
            'kvkk.accepted' => 'Devam etmek için KVKK aydınlatma metnini onaylamanız gerekir.',
            'photos.required' => 'Fiyat verebilmemiz için en az bir fotoğraf ekleyin.',
            'photos.min' => 'Fiyat verebilmemiz için en az bir fotoğraf ekleyin.',
            'photos.array' => 'Fotoğraflar yüklenemedi, lütfen tekrar deneyin.',
            'photos.max' => 'En fazla 5 fotoğraf yükleyebilirsiniz.',
            'photos.*.max' => 'Her fotoğraf en fazla 5 MB olabilir.',
            'photos.*.mimes' => 'Fotoğraflar JPG, PNG veya WebP formatında olmalıdır.',
            'photos.*.image' => 'Yüklenen dosya bir görsel olmalıdır.',
            'preferred_date.after_or_equal' => 'Tercih edilen tarih bugünden önce olamaz.',
            'website.max' => 'Form doğrulanamadı.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $provinceId = $this->integer('province_id');
            $districtId = $this->integer('district_id');

            if ($provinceId && $districtId) {
                $matches = District::query()->whereKey($districtId)->where('province_id', $provinceId)->exists();

                if (! $matches) {
                    $validator->errors()->add('district_id', 'Seçilen ilçe seçilen ile ait değil.');
                }
            }
        });
    }
}
