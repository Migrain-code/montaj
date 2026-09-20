<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        if (Feature::query()->exists()) {
            return;
        }

        $trust = [
            ['fa-solid fa-screwdriver-wrench', 'Profesyonel Montaj', 'Kendi ekipmanımızla, üretici kılavuzuna uygun kurulum.'],
            ['fa-regular fa-calendar-check', 'Hızlı Randevu', 'Çoğu talebe aynı gün veya ertesi gün randevu.'],
            ['fa-solid fa-broom', 'Titiz Çalışma', 'Montaj sonrası ambalajları toplar, alanı temiz bırakırız.'],
            ['fa-solid fa-map-location-dot', 'Trakya Bölgesinde Hizmet', 'Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçeleri.'],
            ['fa-solid fa-tag', 'Uygun Fiyat', 'İşe başlamadan önce net fiyat, sürpriz ücret yok.'],
            ['fa-regular fa-face-smile', 'Müşteri Memnuniyeti', 'İş bitmeden kapak ve çekmece ayarlarını birlikte kontrol ederiz.'],
        ];

        $whyUs = [
            ['fa-solid fa-user-gear', 'Profesyonel çalışma', 'Montaj işini meslek olarak yapan, ekipmanı tam bir ekip.'],
            ['fa-solid fa-ruler-combined', 'Titiz montaj', 'Terazi ayarı, kapak hizası ve sabitleme kontrolüyle teslim.'],
            ['fa-regular fa-clock', 'Zamanında hizmet', 'Verdiğimiz randevu saatine uyarız; gecikme olursa önceden haber veririz.'],
            ['fa-brands fa-whatsapp', 'Hızlı iletişim', 'WhatsApp\'tan gönderdiğiniz fotoğrafa kısa sürede fiyat döneriz.'],
            ['fa-solid fa-route', 'Bölgeyi bilen ekip', 'Trakya\'nın ilçe ve mahallelerini biliyor, ulaşımı verimli planlıyoruz.'],
            ['fa-solid fa-hand-holding-dollar', 'Şeffaf fiyatlandırma', 'Fiyat işe başlamadan belli olur, sonradan ek ücret çıkmaz.'],
            ['fa-solid fa-thumbs-up', 'Müşteri memnuniyeti', 'İş bitiminde birlikte kontrol eder, memnun kalmadan ayrılmayız.'],
        ];

        $process = [
            ['fa-solid fa-phone', 'Bize Ulaşın', 'Telefon veya WhatsApp üzerinden montaj ihtiyacınızı iletin.'],
            ['fa-solid fa-camera', 'Bilgi ve Fotoğraf Gönderin', 'Montaj yapılacak mobilyanın fotoğrafını ve konum bilgisini paylaşın.'],
            ['fa-regular fa-calendar-check', 'Randevunuzu Oluşturalım', 'Uygun gün ve saat için randevunuzu planlayalım.'],
            ['fa-solid fa-check-double', 'Montajı Tamamlayalım', 'Ekibimiz adresinize gelerek montaj işlemini gerçekleştirsin.'],
        ];

        foreach ([Feature::TYPE_TRUST => $trust, Feature::TYPE_WHY_US => $whyUs, Feature::TYPE_PROCESS => $process] as $type => $items) {
            foreach ($items as $i => [$icon, $title, $description]) {
                Feature::query()->create([
                    'type' => $type,
                    'icon' => $icon,
                    'title' => $title,
                    'description' => $description,
                    'sort_order' => $i + 1,
                ]);
            }
        }
    }
}
