<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        if (Faq::query()->exists()) {
            return;
        }

        $faqs = [
            [
                'question' => 'Mobilya montaj fiyatı nasıl belirleniyor?',
                'answer' => 'Fiyat; mobilyanın türüne (gardırop, baza, TV ünitesi vb.), parça sayısına, kapak/çekmece adedine ve adresinizin bulunduğu ilçeye göre belirlenir. WhatsApp\'tan gönderdiğiniz ürün fotoğrafı ve konum bilgisine göre işe başlamadan önce net fiyat veriyoruz.',
            ],
            [
                'question' => 'Hangi bölgelere hizmet veriyorsunuz?',
                'answer' => 'Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine hizmet veriyoruz. Çorlu, Çerkezköy, Kapaklı, Süleymanpaşa, Lüleburgaz, Edirne merkez ve Keşan başta olmak üzere Trakya\'nın tamamına ulaşıyoruz.',
            ],
            [
                'question' => 'Randevu ne kadar sürede oluşuyor?',
                'answer' => 'Yoğunluğa göre çoğu talebe aynı gün veya ertesi gün randevu verebiliyoruz. Uzak ilçeler için aynı bölgedeki işleri aynı güne planlayarak en kısa tarihi sunuyoruz.',
            ],
            [
                'question' => 'Montaj için ne hazırlamam gerekiyor?',
                'answer' => 'Mobilyanın kurulacağı alanın boş ve ulaşılabilir olması yeterlidir. Aletler, vida ve dübeller ekibimizde bulunur. Ürün kutusundaki parça listesini ve montaj kılavuzunu saklamanız işi hızlandırır.',
            ],
            [
                'question' => 'Taşınma sırasında mobilya sökme ve yeniden kurma yapıyor musunuz?',
                'answer' => 'Evet. Gardırop, yatak odası takımı, kitaplık ve ofis mobilyalarını eski adreste söküyor, yeni adreste yeniden kuruyoruz. Sökme ve kurma işlemleri ayrı ayrı veya birlikte planlanabilir.',
            ],
            [
                'question' => 'IKEA mobilyalarını da monte ediyor musunuz?',
                'answer' => 'Evet. PAX gardırop, BESTÅ ve KALLAX üniteler, MALM yatak odası ürünleri dahil tüm IKEA mobilyalarının kurulumunu yapıyoruz.',
            ],
            [
                'question' => 'Ödeme nasıl yapılıyor?',
                'answer' => 'Ödeme montaj tamamlandıktan sonra yapılır. Nakit veya havale/EFT ile ödeme kabul ediyoruz. İşe başlamadan önce bildirilen fiyat dışında ek ücret talep edilmez.',
            ],
            [
                'question' => 'Montajda bir parça eksik veya hasarlı çıkarsa ne oluyor?',
                'answer' => 'Kurulum sırasında fark edilen eksik ya da hasarlı parçaları size hemen bildiririz. Parça temin edildikten sonra kalan montajı tamamlamak için tekrar randevu oluştururuz.',
            ],
        ];

        foreach ($faqs as $i => $faq) {
            Faq::query()->create($faq + ['sort_order' => $i + 1, 'show_on_home' => $i < 6]);
        }
    }
}
