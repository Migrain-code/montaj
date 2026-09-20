<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->services() as $i => $data) {
            Service::query()->firstOrCreate(
                ['slug' => $data['slug']],
                $data + ['sort_order' => $i + 1, 'is_active' => true, 'is_featured' => true]
            );
        }
    }

    private function steps(string $item): array
    {
        return [
            ['title' => 'Bize ulaşın', 'description' => 'WhatsApp veya telefon üzerinden '.$item.' talebinizi iletin.'],
            ['title' => 'Fotoğraf ve konum gönderin', 'description' => 'Ürünün ve kurulacağı alanın fotoğrafı ile ilçe bilgisini paylaşın; net fiyat verelim.'],
            ['title' => 'Randevu oluşturalım', 'description' => 'Size uygun gün ve saat için randevunuzu planlayalım.'],
            ['title' => 'Montajı tamamlayalım', 'description' => 'Ekibimiz kendi ekipmanıyla adresinize gelsin, montajı yapıp alanı temiz bıraksın.'],
        ];
    }

    private function services(): array
    {
        return [
            [
                'title' => 'Mobilya Montajı',
                'slug' => 'mobilya-montaji',
                'icon' => 'fa-solid fa-couch',
                'image' => 'services/mobilya-montaji.webp',
                'image_alt' => 'Salon mobilyası montajı tamamlanmış modern oturma odası',
                'short_description' => 'Her türlü hazır (demonte) mobilyanın profesyonel kurulumu.',
                'description' => '<p>Mağazadan veya internetten satın aldığınız paketli mobilyaların kurulumunu, üretici kılavuzuna uygun şekilde ve kendi ekipmanımızla yapıyoruz. Marka fark etmeksizin yatak odası takımı, oturma grubu, yemek odası, genç odası ve salon mobilyalarını monte ediyoruz.</p><p>Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine geliyoruz. Montaj öncesinde ürün fotoğrafına göre net fiyat veriyor, montaj sonrasında kapak ve çekmece ayarlarını yapıp ambalajları topluyoruz.</p>',
                'what_we_do' => [
                    'Paketli (demonte) mobilyaların kurulumu',
                    'Yatak odası, yemek odası ve genç odası takımlarının montajı',
                    'Taşınma öncesi sökme, yeni adreste yeniden kurma',
                    'Kapak, ray ve menteşe ayarlarının yapılması',
                    'Devrilme riski olan mobilyaların duvara sabitlenmesi',
                    'Montaj sonrası ambalaj toplama ve alan temizliği',
                ],
                'suitable_for' => [
                    'Yeni ev kuran aileler',
                    'Taşınma sürecindeki ev ve ofisler',
                    'İnternetten mobilya sipariş edenler',
                    'Nakliye firmaları ve mobilya mağazaları',
                    'Kiralık daire ve öğrenci evi sahipleri',
                ],
                'process_steps' => $this->steps('mobilya montajı'),
                'faqs' => [
                    ['question' => 'Hangi markaların mobilyalarını kuruyorsunuz?', 'answer' => 'Marka ayrımı yapmıyoruz. Yerli ve yabancı tüm hazır mobilya markalarının ürünlerini, kutudan çıkan montaj kılavuzuna uygun şekilde kuruyoruz.'],
                    ['question' => 'Montaj ne kadar sürer?', 'answer' => 'Süre ürüne göre değişir. Tek bir baza veya masa 30-60 dakika, komple yatak odası takımı 3-5 saat sürebilir. Fotoğrafa göre tahmini süreyi randevu öncesinde bildiririz.'],
                    ['question' => 'Montaj kılavuzu kaybolduysa kurabilir misiniz?', 'answer' => 'Evet. Deneyimli ekibimiz parça yapısına göre kurulum yapabilir; ürün fotoğrafını gönderdiğinizde gerekirse üreticinin çevrim içi kılavuzunu buluruz.'],
                    ['question' => 'Vida ve dübel getiriyor musunuz?', 'answer' => 'Ürün kutusundan çıkan bağlantı elemanları kullanılır; duvara sabitleme için gerekli dübel ve vidaları biz temin ederiz.'],
                ],
                'meta_title' => 'Mobilya Montajı | Tekirdağ, Edirne, Kırklareli Mobilya Montaj Servisi',
                'meta_description' => 'Trakya bölgesinde her türlü hazır mobilyanın profesyonel montajı. Fotoğraf gönderin, net fiyat alın. Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine hizmet.',
            ],
            [
                'title' => 'IKEA Mobilya Montajı',
                'slug' => 'ikea-mobilya-montaji',
                'icon' => 'fa-solid fa-box-open',
                'image' => 'services/ikea-mobilya-montaji.webp',
                'image_alt' => 'IKEA tarzı mobilyalarla döşenmiş oturma odası',
                'short_description' => 'IKEA mobilyalarının kurulum ve montaj hizmeti.',
                'description' => '<p>IKEA ürünlerinin kendine özgü parça ve bağlantı sistemine hakim ekibimizle PAX gardırop, BESTÅ ve KALLAX üniteler, MALM ve HEMNES yatak odası ürünleri, BILLY kitaplık, LACK sehpa ve tüm diğer IKEA mobilyalarını kuruyoruz.</p><p>Trakya\'da IKEA mağazası bulunmadığından ürünler genellikle kargo veya nakliye ile geliyor. Kutuları açmadan önce bize fotoğrafını gönderin; parça sayısına göre net fiyat verelim ve size uygun günde montajı tamamlayalım.</p>',
                'what_we_do' => [
                    'PAX gardırop ve iç düzenleyici (KOMPLEMENT) kurulumu',
                    'BESTÅ, KALLAX ve EKET ünitelerinin montajı ve duvara sabitlenmesi',
                    'MALM, HEMNES, BRIMNES yatak ve şifonyer kurulumu',
                    'BILLY kitaplık ve raf sistemleri',
                    'Masa, sandalye, sehpa ve TV sehpası montajı',
                    'Sürgülü kapak, ray ve menteşe ayarları',
                ],
                'suitable_for' => [
                    'IKEA\'dan online sipariş verenler',
                    'İstanbul\'dan ürün getirip Trakya\'da kurduranlar',
                    'Öğrenci evleri ve kiralık daireler',
                    'Home ofis kuranlar',
                ],
                'process_steps' => $this->steps('IKEA mobilya montajı'),
                'faqs' => [
                    ['question' => 'PAX gardırop montajı ne kadar sürer?', 'answer' => 'İki kapaklı standart bir PAX yaklaşık 2 saat, sürgülü kapaklı ve iç düzenleyicili üçlü PAX 4-6 saat sürebilir.'],
                    ['question' => 'IKEA\'nın kendi montaj hizmeti yerine neden sizi seçmeliyim?', 'answer' => 'Trakya\'da IKEA mağazası bulunmadığı için resmi montaj hizmeti her ilçeye ulaşmayabilir. Biz Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine geliyoruz ve randevuyu sizin takviminize göre planlıyoruz.'],
                    ['question' => 'Ürün kutuları açılmış olsa da kurulum yapıyor musunuz?', 'answer' => 'Evet. Parçaların tam olduğundan emin olmak için kutu içeriğini kılavuzdaki listeyle karşılaştırıp montaja başlıyoruz.'],
                ],
                'meta_title' => 'IKEA Mobilya Montajı | Tekirdağ, Edirne, Kırklareli',
                'meta_description' => 'PAX, BESTÅ, KALLAX, MALM ve tüm IKEA mobilyalarının Trakya bölgesinde profesyonel kurulumu. WhatsApp\'tan fotoğraf gönderin, net fiyat alın.',
            ],
            [
                'title' => 'Gardırop Montajı',
                'slug' => 'gardirop-montaji',
                'icon' => 'fa-solid fa-door-closed',
                'image' => 'services/gardirop-montaji.webp',
                'image_alt' => 'Montajı tamamlanmış ahşap gardırop',
                'short_description' => 'Sürgülü, kapaklı ve farklı modellerde gardırop montajı.',
                'description' => '<p>İki kapaklıdan altı kapaklıya, sürgülü kapaklıdan köşe modellerine kadar her türlü gardırobu kuruyoruz. Gövde kurulumu, kapak ayarı, sürgü rayı montajı, iç raf ve askı düzeni ile duvara sabitleme işlemlerini aynı randevuda tamamlıyoruz.</p><p>Gardıroplar yüksekliği nedeniyle devrilme riski taşır; bu yüzden özellikle çocuk odalarında duvara sabitlemeyi standart olarak yapıyoruz. Taşınma durumunda mevcut gardırobunuzu söküp yeni adreste yeniden kurabiliyoruz.</p>',
                'what_we_do' => [
                    'Kapaklı gardırop gövde ve kapak montajı',
                    'Sürgülü kapak ray sistemi kurulumu ve ayarı',
                    'Köşe gardırop ve giyinme odası (walk-in) sistemleri',
                    'İç raf, çekmece ve askı borusu düzenlemesi',
                    'Duvara sabitleme (devrilme önleyici)',
                    'Taşınma için sökme ve yeniden kurma',
                ],
                'suitable_for' => [
                    'Yeni yatak odası takımı alanlar',
                    'Çocuk ve genç odası düzenleyenler',
                    'Taşınanlar',
                    'Ev sahipleri ve kiralık daire yatırımcıları',
                ],
                'process_steps' => $this->steps('gardırop montajı'),
                'faqs' => [
                    ['question' => 'Gardırop montajı için odanın boş olması gerekir mi?', 'answer' => 'Gardırobun kurulacağı duvar önünün ve parçaları yayabileceğimiz yaklaşık 2 m²\'lik bir alanın boş olması yeterlidir.'],
                    ['question' => 'Tavan yüksekliği yeterli değilse ne olur?', 'answer' => 'Randevu öncesinde gardırop yüksekliği ile oda tavan yüksekliğini sorarız. Kurulum sırasında dik kaldırma için ürün yüksekliğine ek yaklaşık 5-10 cm boşluk gerekir; gerekirse gövdeyi yatay kurup dikiyoruz.'],
                    ['question' => 'Sürgülü kapaklar sonradan kaydırmaya başlarsa ayar yapıyor musunuz?', 'answer' => 'Evet. Mevcut gardıroplarınızda kapak, ray ve menteşe ayarı için de hizmet veriyoruz.'],
                ],
                'meta_title' => 'Gardırop Montajı | Sürgülü ve Kapaklı Gardırop Kurulumu - Trakya',
                'meta_description' => 'Tekirdağ, Edirne ve Kırklareli\'nde sürgülü, kapaklı ve köşe gardırop montajı. Duvara sabitleme dahil profesyonel kurulum. WhatsApp\'tan teklif alın.',
            ],
            [
                'title' => 'Yatak ve Baza Montajı',
                'slug' => 'yatak-baza-montaji',
                'icon' => 'fa-solid fa-bed',
                'image' => 'services/yatak-baza-montaji.webp',
                'image_alt' => 'Kurulumu tamamlanmış başlıklı yatak ve baza',
                'short_description' => 'Yatak, baza ve başlık kurulum hizmeti.',
                'description' => '<p>Tek kişilik, çift kişilik, sandıklı ve çekmeceli bazaları; ayaklı, başlıklı ve karyola tipi yatak sistemlerini kuruyoruz. Sandıklı bazalarda pistonlu kaldırma mekanizmasının doğru ayarlanması ve başlığın bazaya sağlam bağlanması için kendi ekipmanımızı kullanıyoruz.</p><p>Nakliye ile gelen yatak odası takımlarında baza ve başlıkla birlikte şifonyer, komodin ve gardırop montajını aynı randevuda tamamlayabiliyoruz.</p>',
                'what_we_do' => [
                    'Tek ve çift kişilik baza kurulumu',
                    'Sandıklı (pistonlu) baza mekanizması montajı ve ayarı',
                    'Yatak başlığı montajı ve bazaya bağlanması',
                    'Karyola, ranza ve genç odası yatakları',
                    'Komodin ve şifonyer kurulumu',
                    'Taşınma için baza sökme ve yeniden kurma',
                ],
                'suitable_for' => [
                    'Yeni yatak odası alanlar',
                    'Çocuk ve genç odası hazırlayanlar',
                    'Pansiyon, apart ve öğrenci evleri',
                    'Taşınanlar',
                ],
                'process_steps' => $this->steps('yatak ve baza montajı'),
                'faqs' => [
                    ['question' => 'Baza montajı ne kadar sürer?', 'answer' => 'Standart bir çift kişilik sandıklı baza ve başlık montajı yaklaşık 45-90 dakika sürer.'],
                    ['question' => 'Başlık duvara mı bazaya mı sabitleniyor?', 'answer' => 'Başlığın modeline göre bazaya bağlanır veya duvara monte edilir. Ürün fotoğrafına göre uygun yöntemi belirleyip gerekli malzemeyi getiririz.'],
                    ['question' => 'Sadece baza sökme hizmeti alabilir miyim?', 'answer' => 'Evet. Taşınma veya tadilat için yalnızca sökme, yalnızca kurma ya da her ikisi için randevu oluşturabilirsiniz.'],
                ],
                'meta_title' => 'Yatak ve Baza Montajı | Trakya Bölgesi Baza Kurulum Servisi',
                'meta_description' => 'Tekirdağ, Edirne ve Kırklareli\'nde sandıklı baza, başlık ve yatak odası montajı. Aynı gün randevu, işe başlamadan net fiyat.',
            ],
            [
                'title' => 'Masa ve Sandalye Montajı',
                'slug' => 'masa-sandalye-montaji',
                'icon' => 'fa-solid fa-chair',
                'image' => 'services/masa-sandalye-montaji.webp',
                'image_alt' => 'Kurulmuş yemek masası ve sandalyeler',
                'short_description' => 'Masa, sandalye ve çalışma masası kurulumu.',
                'description' => '<p>Yemek masası, mutfak masası, açılır (uzayabilir) masa, çalışma masası ve sandalyelerin montajını yapıyoruz. Açılır masalarda mekanizmanın, döner sandalyelerde amortisör ve tekerleklerin doğru takılması için ürün kılavuzuna uygun çalışıyoruz.</p><p>Kafe ve restoranlar için toplu masa-sandalye kurulumlarında ürün adedine göre paket fiyat veriyoruz.</p>',
                'what_we_do' => [
                    'Yemek ve mutfak masası montajı',
                    'Açılır / uzayabilir masa mekanizması kurulumu',
                    'Çalışma masası ve bilgisayar masası montajı',
                    'Sandalye, tabure ve bar sandalyesi kurulumu',
                    'Kafe ve restoran için toplu montaj',
                    'Masa ayağı ve gövde sabitleme kontrolü',
                ],
                'suitable_for' => [
                    'Ev ve mutfak yenileyenler',
                    'Kafe, restoran ve ofisler',
                    'Home ofis kuranlar',
                    'Öğrenci evleri',
                ],
                'process_steps' => $this->steps('masa ve sandalye montajı'),
                'faqs' => [
                    ['question' => 'Sadece sandalye montajı için geliyor musunuz?', 'answer' => 'Evet. Adet ve ilçeye göre fiyat verip randevu oluşturuyoruz; aynı bölgedeki işlerle birleştirerek uygun tarih sunuyoruz.'],
                    ['question' => 'Açılır masa mekanizması ayarı yapıyor musunuz?', 'answer' => 'Evet, açılır masalarda ray ve kilit mekanizmasının ayarını kurulum sonunda test ederek teslim ediyoruz.'],
                ],
                'meta_title' => 'Masa ve Sandalye Montajı | Tekirdağ, Edirne, Kırklareli',
                'meta_description' => 'Yemek masası, çalışma masası ve sandalye montajı. Trakya bölgesinin tüm ilçelerinde hızlı randevu ve net fiyat.',
            ],
            [
                'title' => 'TV Ünitesi Montajı',
                'slug' => 'tv-unitesi-montaji',
                'icon' => 'fa-solid fa-tv',
                'image' => 'services/tv-unitesi-montaji.webp',
                'image_alt' => 'Duvara monte edilmiş TV ünitesi bulunan salon',
                'short_description' => 'TV ünitesi ve benzeri salon mobilyalarının kurulumu.',
                'description' => '<p>Yerden ve duvara asılan TV ünitelerini, duvar raflarını, vitrin ve konsolları kuruyoruz. Duvara asılan modellerde duvar tipine uygun dübel seçimi ve terazi ayarı yaparak üniteyi güvenli şekilde sabitliyoruz.</p><p>İsteğe bağlı olarak TV askı aparatı montajını da aynı randevuda yapıyoruz.</p>',
                'what_we_do' => [
                    'Yerden TV ünitesi ve TV sehpası montajı',
                    'Duvara asılan (asma) TV ünitesi ve raf kurulumu',
                    'Vitrin, konsol ve dresuar montajı',
                    'TV askı aparatı montajı (isteğe bağlı)',
                    'Kablo düzenleme için kablo kanalı montajı',
                    'Kapak ve çekmece ayarları',
                ],
                'suitable_for' => [
                    'Salon takımı yenileyenler',
                    'Yeni taşınanlar',
                    'Ofis ve toplantı odaları',
                    'Otel ve apart işletmeleri',
                ],
                'process_steps' => $this->steps('TV ünitesi montajı'),
                'faqs' => [
                    ['question' => 'Alçıpan duvara asma TV ünitesi monte edilebilir mi?', 'answer' => 'Evet, alçıpan duvarlar için uygun ağırlık kapasitesine sahip özel dübeller kullanıyoruz. Ünitenin ve TV\'nin ağırlığına göre profil aramaya gerek olup olmadığını randevu öncesinde değerlendiriyoruz.'],
                    ['question' => 'TV\'yi de duvara asıyor musunuz?', 'answer' => 'Evet, askı aparatınız varsa TV montajını da aynı randevuda yapıyoruz.'],
                ],
                'meta_title' => 'TV Ünitesi Montajı | Trakya Bölgesi TV Ünitesi Kurulumu',
                'meta_description' => 'Tekirdağ, Edirne ve Kırklareli\'nde yerden ve duvara asılan TV ünitesi montajı. Terazi ayarı ve güvenli sabitleme dahil.',
            ],
            [
                'title' => 'Kitaplık Montajı',
                'slug' => 'kitaplik-montaji',
                'icon' => 'fa-solid fa-book',
                'image' => 'services/kitaplik-montaji.webp',
                'image_alt' => 'Duvar boyu kurulmuş renkli kitaplık',
                'short_description' => 'Kitaplık ve raf sistemlerinin kurulumu.',
                'description' => '<p>Modüler kitaplıklar, duvar boyu raf sistemleri, açık raflı üniteler ve çalışma odası kitaplıklarını kuruyoruz. Yüksek kitaplıkların devrilmemesi için duvara sabitleme işlemini standart olarak yapıyoruz.</p><p>Birden fazla modülün yan yana kurulduğu sistemlerde modüllerin birbirine bağlanması ve hizalanması için terazi ve mesafe ayarlarını dikkatle yapıyoruz.</p>',
                'what_we_do' => [
                    'Modüler kitaplık ve raf ünitesi montajı',
                    'Duvar rafı ve konsol raf kurulumu',
                    'Modüllerin birbirine bağlanması ve hizalanması',
                    'Duvara sabitleme (devrilme önleyici)',
                    'Çalışma odası ve kütüphane düzenleri',
                    'Ofis dosya dolabı ve raf sistemleri',
                ],
                'suitable_for' => [
                    'Ev kütüphanesi kuranlar',
                    'Öğrenci ve çalışma odaları',
                    'Ofisler ve arşiv odaları',
                    'Çocuk odaları',
                ],
                'process_steps' => $this->steps('kitaplık montajı'),
                'faqs' => [
                    ['question' => 'Kitaplığın duvara sabitlenmesi zorunlu mu?', 'answer' => 'Zorunlu olmasa da 120 cm\'den yüksek kitaplıkların, özellikle çocuklu evlerde, devrilme riskine karşı sabitlenmesini öneriyor ve ek ücret almadan yapıyoruz.'],
                    ['question' => 'Duvar rafı montajında duvarı deliyor musunuz?', 'answer' => 'Evet, duvar rafları dübel ile sabitlenir. Delme öncesinde duvar içinde tesisat olup olmadığını kontrol ediyoruz.'],
                ],
                'meta_title' => 'Kitaplık Montajı | Tekirdağ, Edirne, Kırklareli Kitaplık Kurulumu',
                'meta_description' => 'Modüler kitaplık, raf sistemi ve duvar rafı montajı. Trakya bölgesinde duvara sabitleme dahil profesyonel kurulum.',
            ],
            [
                'title' => 'Ofis Mobilyası Montajı',
                'slug' => 'ofis-mobilyasi-montaji',
                'icon' => 'fa-solid fa-briefcase',
                'image' => 'services/ofis-mobilyasi-montaji.webp',
                'image_alt' => 'Kurulumu tamamlanmış modern ofis çalışma alanı',
                'short_description' => 'Ofis masası, dolap, sandalye ve diğer ofis mobilyalarının kurulumu.',
                'description' => '<p>Çalışma masaları, workstation (çoklu çalışma) sistemleri, toplantı masaları, dosya dolapları, keson ve ofis sandalyelerinin montajını yapıyoruz. Yeni ofis kurulumlarında ve ofis taşımalarında ürün adedine göre paket fiyat veriyor, mesai dışı saatlerde de çalışabiliyoruz.</p><p>Çorlu, Çerkezköy, Kapaklı ve Lüleburgaz gibi sanayi bölgelerindeki fabrika ve ofislere düzenli hizmet veriyoruz.</p>',
                'what_we_do' => [
                    'Çalışma masası ve workstation kurulumu',
                    'Toplantı masası ve yönetici masası montajı',
                    'Dosya dolabı, keson ve arşiv rafları',
                    'Ofis sandalyesi kurulumu (adet bazlı)',
                    'Ofis taşımada sökme ve yeniden kurma',
                    'Seperatör ve bölme panel montajı',
                ],
                'suitable_for' => [
                    'Yeni açılan ofisler',
                    'Fabrika ve sanayi tesisleri idari binaları',
                    'Ofis taşıyan firmalar',
                    'Kamu kurumları ve okullar',
                    'Home ofis kuranlar',
                ],
                'process_steps' => $this->steps('ofis mobilyası montajı'),
                'faqs' => [
                    ['question' => 'Hafta sonu veya mesai sonrası montaj yapıyor musunuz?', 'answer' => 'Evet. Ofisin çalışma düzenini bozmamak için hafta sonu ve mesai sonrası randevu planlayabiliyoruz.'],
                    ['question' => 'Fatura kesiyor musunuz?', 'answer' => 'Evet, kurumsal müşterilerimiz için fatura düzenliyoruz.'],
                    ['question' => 'Kaç kişilik ekiple geliyorsunuz?', 'answer' => 'İşin büyüklüğüne göre 1-3 kişilik ekiple geliyoruz; toplu kurulumlarda süreyi kısaltmak için ekip sayısını artırıyoruz.'],
                ],
                'meta_title' => 'Ofis Mobilyası Montajı | Çorlu, Çerkezköy, Lüleburgaz Ofis Kurulumu',
                'meta_description' => 'Trakya bölgesinde ofis masası, workstation, dolap ve sandalye montajı. Yeni ofis kurulumu ve ofis taşımada paket fiyat.',
            ],
        ];
    }
}
