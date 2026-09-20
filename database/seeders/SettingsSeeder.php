<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site_name' => 'Trakya Mobilya Montaj',
            'site_tagline' => 'Profesyonel Mobilya Montaj Hizmeti',
            'meta_title' => 'Trakya Mobilya Montaj | Tekirdağ, Edirne, Kırklareli',
            'meta_description' => 'Tekirdağ, Edirne ve Kırklareli\'nde profesyonel mobilya montaj hizmeti. Gardırop, yatak, baza, TV ünitesi, IKEA ve ofis mobilyası montajı. WhatsApp\'tan hemen teklif alın.',
            'whatsapp_message' => 'Merhaba, mobilya montajı için fiyat almak istiyorum. Bulunduğum bölge: {bolge}',
            'working_hours' => 'Pazartesi - Cumartesi: 08:30 - 19:00',
            'address' => 'Çorlu, Tekirdağ',
            'service_area_text' => 'Tekirdağ, Edirne, Kırklareli ve tüm Trakya',

            'hero_badge' => 'Tekirdağ · Edirne · Kırklareli',
            'hero_title' => 'Profesyonel Mobilya Montaj Hizmeti',
            'hero_subtitle' => 'Trakya bölgesinde mobilyalarınızı hızlı, güvenli ve özenli şekilde monte ediyoruz.',
            'hero_bullets' => "Fotoğraf gönderin, aynı gün fiyat teklifi alın\nSize uygun gün ve saatte randevu",
            'hero_image' => 'site/hero.webp',

            'about_subtitle' => 'Hakkımızda',
            'about_title' => 'Trakya\'da Güvenilir Mobilya Montaj Ekibi',
            'about_text' => '<p>Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerinde hazır mobilya montajı yapıyoruz. Gardırop, yatak ve baza, TV ünitesi, masa-sandalye, kitaplık, IKEA ve ofis mobilyalarını kendi ekipmanımızla, üretici talimatlarına uygun şekilde kuruyoruz.</p><p>Randevu saatine uyar, montaj sonrası çalışma alanını temiz bırakırız. Fiyatı işe başlamadan önce net olarak bildiririz; sürpriz ücret çıkmaz.</p>',
            'about_image' => 'site/about-1.webp',
            'about_image_2' => 'site/about-2.webp',
            'about_page_content' => '<p>Trakya Mobilya Montaj, Tekirdağ merkezli olarak Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine mobilya montaj hizmeti veren bağımsız bir ekiptir. Nakliyeciler, mobilya mağazaları ve doğrudan ev/ofis sahipleri için çalışıyoruz.</p><h2>Ne yapıyoruz?</h2><p>Paketli (demonte) gelen her türlü mobilyayı kuruyor, taşınma öncesi söküp yeni adreste yeniden monte ediyor, mevcut mobilyalarda kapak, ray ve menteşe ayarlarını yapıyoruz. Duvara sabitlenmesi gereken gardırop, kitaplık ve TV ünitelerini uygun dübel ve vidalarla sabitliyoruz.</p><h2>Nasıl çalışıyoruz?</h2><p>WhatsApp üzerinden gönderdiğiniz fotoğraf ve konum bilgisine göre net fiyat veriyor, size uygun gün için randevu oluşturuyoruz. Montaj günü ekibimiz kendi alet çantasıyla adresinize gelir, işi tamamlar ve ambalajları toplayıp alanı temiz bırakır.</p><h2>Neden biz?</h2><ul><li>Bölgeyi bilen, ilçelere ulaşımı planlayabilen ekip</li><li>İşe başlamadan önce net ve şeffaf fiyat</li><li>Randevu saatine uyum</li><li>Montaj sonrası kontrol ve temizlik</li></ul>',

            'stat_1_value' => '3',
            'stat_1_label' => 'Hizmet Verilen İl',
            'stat_2_value' => '28',
            'stat_2_label' => 'İlçe',
            'stat_3_value' => '8',
            'stat_3_label' => 'Hizmet Türü',
            'stat_4_value' => 'Ücretsiz',
            'stat_4_label' => 'Fotoğrafla Fiyat Teklifi',

            'process_subtitle' => 'Nasıl Çalışıyoruz?',
            'process_title' => 'Dört Adımda Montaj',
            'process_image' => 'site/process.webp',

            'cta_title' => 'Mobilyanız kurulmayı mı bekliyor?',
            'cta_text' => 'Fotoğrafını WhatsApp\'tan gönderin, kısa sürede net fiyat teklifi alın.',
            'cta_image' => 'site/cta-bg.webp',
            'banner_image' => 'site/page-banner.webp',

            'footer_text' => 'Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerinde her türlü mobilya montajı için yanınızdayız.',

            'facebook_url' => '',
            'instagram_url' => '',
            'youtube_url' => '',
            'map_embed' => '',
            'google_analytics_id' => '',
            'google_site_verification' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::flush();
    }
}
