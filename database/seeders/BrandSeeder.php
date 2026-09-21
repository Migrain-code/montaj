<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Montajını yaptığımız markalar.
 *
 * İÇERİK KURALI: metinler MARKA HAKKINDA iddia içermez (ciro, sıralama, kalite
 * yorumu, ödül). Yalnız O MARKANIN ÜRÜNLERİNİ MONTE EDERKEN karşılaştığımız somut
 * durumları anlatır. Marka adları sahiplerine aittir; bağımsız montaj hizmeti
 * verildiği her sayfada belirtilir.
 */
class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // Kendi hizmet sayfası olan markalar ayrı marka sayfası ALMAZ; kartları
        // doğrudan o hizmet sayfasına gider (cannibalization önlemi, spec §3.4).
        $linked = ['ikea' => 'ikea-mobilya-montaji'];

        foreach ($this->brands() as $i => $data) {
            $serviceSlug = $linked[$data['slug']] ?? null;

            $brand = Brand::query()->firstOrCreate(
                ['slug' => $data['slug']],
                $data + [
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'service_id' => $serviceSlug ? Service::query()->where('slug', $serviceSlug)->value('id') : null,
                ]
            );

            $this->syncContent($brand, $data);
        }
    }

    /**
     * Gövde metinleri ayrı dosyada tutulur: uzunlar ve seeder'ın yapı kodunu boğuyor.
     * Yalnız SEED EDİLEN metin hâlâ yerindeyse güncellenir — panelden elle yazılmış
     * bir metnin üzerine YAZILMAZ.
     */
    private function syncContent(Brand $brand, array $seeded): void
    {
        $data = $this->contents()[$brand->slug] ?? null;

        if (! $data) {
            return;
        }

        // İç link motoru gövdeye <a> etiketi basmış olabilir; bu "elle düzenleme"
        // sayılmaz. Karşılaştırmayı link etiketlerinden arındırılmış metinle yapıyoruz.
        $current = $this->withoutLinks($brand->content);

        $untouched = $current === $this->withoutLinks($seeded['content'] ?? '')
            || $current === $this->withoutLinks($data['content']);

        if (! $untouched) {
            return;
        }

        $brand->update([
            'content' => $data['content'],
            'meta_title' => $brand->meta_title ?: $data['meta_title'],
            'meta_description' => $brand->meta_description ?: $data['meta_description'],
        ]);

        $this->syncFaqs($brand);
    }

    /**
     * Ek sorular yalnız EKSİK olanlar için eklenir: aynı soru iki kez yazılmaz ve
     * panelden eklenmiş bir soru silinmez.
     */
    private function syncFaqs(Brand $brand): void
    {
        $extra = (require __DIR__.'/data/brand_faqs.php')[$brand->slug] ?? [];

        if ($extra === []) {
            return;
        }

        $faqs = (array) $brand->faqs;
        $existing = array_map(
            fn ($f) => mb_strtolower(trim((string) ($f['question'] ?? '')), 'UTF-8'),
            $faqs,
        );

        foreach ($extra as $faq) {
            if (! in_array(mb_strtolower($faq['question'], 'UTF-8'), $existing, true)) {
                $faqs[] = $faq;
            }
        }

        count($faqs) === count((array) $brand->faqs) || $brand->update(['faqs' => $faqs]);
    }

    private function withoutLinks(?string $html): string
    {
        $text = preg_replace('#</?a\b[^>]*>#i', '', (string) $html) ?? (string) $html;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function contents(): array
    {
        return require __DIR__.'/data/brand_content.php';
    }

    private function brands(): array
    {
        $region = 'Tekirdağ, Edirne ve Kırklareli';

        return [
            [
                'name' => 'İstikbal',
                'slug' => 'istikbal',
                'is_featured' => true,
                'description' => 'İstikbal yatak odası, koltuk takımı ve genç odası ürünlerinin kurulumu.',
                'products' => ['Yatak odası takımı', 'Baza ve başlık', 'Koltuk takımı', 'Genç odası', 'TV ünitesi', 'Gardırop'],
                'content' => '<p>İstikbal ürünleri bayiden kapıya paketli olarak teslim edilir ve kurulumu ayrıca planlanır. Biz bu kurulumu '.$region.' genelinde bağımsız olarak yapıyoruz: gövde birleştirme, kapak ve çekmece ayarı, devrilmeye karşı duvara sabitleme ve montaj sonrası kontrol dahil.</p><p>En sık geldiğimiz işler yatak odası takımı ve koltuk takımı kurulumu. Sandıklı bazalarda piston ayarı, gardıroplarda sürgü rayı hizası ve köşe koltuklarda modül birleştirme üzerinde özellikle duruyoruz.</p>',
                'faqs' => [
                    ['question' => 'İstikbal bayisinden aldım, montajı siz yapabilir misiniz?', 'answer' => 'Evet. Ürünün hangi bayiden alındığı fark etmez; paketli ürünü adresinizde kuruyoruz. Bayinin kendi montaj hizmetinden ayrı, bağımsız bir hizmettir.'],
                    ['question' => 'Koltuk takımı montajı ne kadar sürer?', 'answer' => 'Standart üçlü-ikili-tekli takım yaklaşık 45-90 dakika sürer. Köşe koltuklarda modül sayısına göre bu süre uzayabilir.'],
                ],
            ],
            [
                'name' => 'Bellona',
                'slug' => 'bellona',
                'is_featured' => true,
                'description' => 'Bellona yatak odası, yemek odası ve oturma grubu montajı.',
                'products' => ['Yatak odası takımı', 'Yemek odası takımı', 'Koltuk takımı', 'Gardırop', 'Vitrin ve konsol'],
                'content' => '<p>Bellona ürünlerinin kurulumunu '.$region.' genelinde yapıyoruz. Yemek odası takımlarında vitrin ve konsolun duvara sabitlenmesi, açılır masalarda ray ve kilit mekanizmasının ayarı bizim standart işlerimizden.</p><p>Yatak odası takımlarında baza, başlık, şifonyer ve gardırobu aynı randevuda kuruyoruz. Ambalajları toplayıp alanı temiz bırakıyoruz.</p>',
                'faqs' => [
                    ['question' => 'Yemek odası takımının tamamını tek randevuda kurar mısınız?', 'answer' => 'Evet. Masa, sandalyeler, vitrin ve konsol tek randevuda kurulur. Parça sayısına göre süre değişir; fotoğrafa bakarak önceden bildiririz.'],
                    ['question' => 'Vitrini duvara sabitliyor musunuz?', 'answer' => 'Evet. Yüksek vitrin ve konsollar devrilme riski taşıdığı için duvar tipine uygun dübelle sabitliyoruz.'],
                ],
            ],
            [
                'name' => 'Mondi Home',
                'slug' => 'mondi-home',
                'description' => 'Mondi Home mobilyalarının kurulum ve montaj hizmeti.',
                'products' => ['Yatak odası takımı', 'Oturma grubu', 'Gardırop', 'TV ünitesi'],
                'content' => '<p>Mondi Home ürünlerinin montajını '.$region.' genelinde yapıyoruz. Paketli gelen ürünü kutu içeriğiyle karşılaştırıp eksik parça varsa işe başlamadan size bildiriyoruz.</p><p>Kurulum sonrası kapak hizası, çekmece rayı ve menteşe ayarlarını birlikte kontrol ediyoruz.</p>',
                'faqs' => [
                    ['question' => 'Montaj kılavuzu kutudan çıkmadı, sorun olur mu?', 'answer' => 'Olmaz. Parça yapısına bakarak kurulum yapabiliyoruz; gerekirse üreticinin çevrim içi kılavuzunu buluyoruz.'],
                ],
            ],
            [
                'name' => 'Doğtaş',
                'slug' => 'dogtas',
                'is_featured' => true,
                'description' => 'Doğtaş yemek odası, yatak odası ve oturma grubu montajı.',
                'products' => ['Yemek odası takımı', 'Yatak odası takımı', 'Koltuk takımı', 'Vitrin', 'Gardırop'],
                'content' => '<p>Doğtaş ürünlerinin kurulumunu '.$region.' genelinde yapıyoruz. Ağır gövdeli vitrin ve gardıroplarda taşıma ve dikme aşaması iki kişilik ekip gerektirir; randevuyu buna göre planlıyoruz.</p><p>Cam raflı vitrinlerde raf taşıyıcılarının doğru takılması ve cam kapakların hizalanması üzerinde ayrıca duruyoruz.</p>',
                'faqs' => [
                    ['question' => 'Ağır gardırop için kaç kişi geliyorsunuz?', 'answer' => 'Gövde ağırlığına ve kat/asansör durumuna göre 1-3 kişilik ekiple geliyoruz. Fotoğraf ve kat bilgisi verirseniz ekibi ona göre ayarlıyoruz.'],
                    ['question' => 'Cam kapaklarda kırılma riski var mı?', 'answer' => 'Cam parçaları en son takıyor ve montaj boyunca ambalajında bırakıyoruz. Taşıma sırasında zaten kırık gelen parçaları işe başlamadan bildiriyoruz.'],
                ],
            ],
            [
                'name' => 'Yataş',
                'slug' => 'yatas',
                'is_featured' => true,
                'description' => 'Yataş baza, başlık ve yatak odası montajı.',
                'products' => ['Sandıklı baza', 'Yatak başlığı', 'Yatak odası takımı', 'Şifonyer ve komodin'],
                'content' => '<p>Yataş ürünlerinde en sık kurduğumuz parça sandıklı baza ve başlık. Pistonlu kaldırma mekanizmasının doğru takılması ve başlığın bazaya sağlam bağlanması kurulumun kritik adımlarıdır; ikisini de kendi ekipmanımızla yapıyoruz.</p><p>'.$region.' genelinde hizmet veriyoruz. Taşınma durumunda bazayı söküp yeni adreste yeniden kurabiliyoruz.</p>',
                'faqs' => [
                    ['question' => 'Sandıklı baza montajı ne kadar sürer?', 'answer' => 'Çift kişilik sandıklı baza ve başlık montajı yaklaşık 45-90 dakika sürer.'],
                    ['question' => 'Eski bazamı söküp yenisini kurar mısınız?', 'answer' => 'Evet. Sökme ve kurma işlemlerini aynı randevuda yapabiliyoruz.'],
                ],
            ],
            [
                'name' => 'Enza Home',
                'slug' => 'enza-home',
                'description' => 'Enza Home yatak odası, yemek odası ve oturma grubu kurulumu.',
                'products' => ['Yatak odası takımı', 'Yemek odası takımı', 'Koltuk takımı', 'TV ünitesi'],
                'content' => '<p>Enza Home ürünlerinin montajını '.$region.' genelinde yapıyoruz. Modüler TV üniteleri ve duvara asılan raflarda terazi ayarı ve duvar tipine uygun dübel seçimi üzerinde duruyoruz.</p><p>Alçıpan duvarlarda asma ünite montajında taşıma kapasitesine uygun özel dübel kullanıyoruz; gerekirse profil arayıp ona sabitliyoruz.</p>',
                'faqs' => [
                    ['question' => 'Asma TV ünitesini alçıpan duvara monte edebilir misiniz?', 'answer' => 'Evet. Alçıpan için uygun kapasiteli dübel kullanıyoruz; ünite ve TV ağırlığına göre profil arayıp ona sabitlemek gerekebilir, bunu yerinde değerlendiriyoruz.'],
                ],
            ],
            [
                'name' => 'Kelebek Mobilya',
                'slug' => 'kelebek-mobilya',
                'description' => 'Kelebek Mobilya yatak odası, genç odası ve gardırop montajı.',
                'products' => ['Yatak odası takımı', 'Genç odası', 'Gardırop', 'Çalışma masası'],
                'content' => '<p>Kelebek Mobilya ürünlerinin kurulumunu '.$region.' genelinde yapıyoruz. Genç odası takımlarında çalışma masası, ranza ve gardırobun aynı randevuda kurulması en sık aldığımız taleplerden.</p><p>Çocuk ve genç odalarında devrilme riski taşıyan tüm yüksek mobilyaları duvara sabitliyoruz; bu işlem için ek ücret almıyoruz.</p>',
                'faqs' => [
                    ['question' => 'Genç odası takımının tamamı tek günde kurulur mu?', 'answer' => 'Çoğu genç odası takımı tek randevuda kurulur. Gardırop, çalışma masası, yatak ve raf sistemini birlikte planlıyoruz.'],
                ],
            ],
            [
                'name' => 'İnegöl Mobilya',
                'slug' => 'inegol-mobilya',
                'description' => 'İnegöl üretimi yatak odası ve yemek odası takımlarının montajı.',
                'products' => ['Yatak odası takımı', 'Yemek odası takımı', 'Koltuk takımı', 'Gardırop'],
                'content' => '<p>İnegöl, Türkiye\'nin mobilya üretim merkezlerinden biridir ve Trakya\'ya çok sayıda takım buradan nakliyeyle gelir. Nakliyeyle gelen ürünlerde parçalar genelde sökülmüş hâlde ulaşır; biz bu takımları adresinizde kuruyoruz.</p><p>'.$region.' genelinde hizmet veriyoruz. Nakliye sırasında hasar görmüş veya eksik gelen parçaları işe başlamadan önce tespit edip size bildiriyoruz.</p>',
                'faqs' => [
                    ['question' => 'Nakliyeyle gelen takımı kurar mısınız?', 'answer' => 'Evet. İnegöl\'den nakliyeyle gelen sökülmüş takımların kurulumu en sık yaptığımız işlerden biridir.'],
                    ['question' => 'Parça eksik çıkarsa ne oluyor?', 'answer' => 'Kutu içeriğini kılavuzdaki listeyle karşılaştırıp eksik varsa hemen bildiriyoruz. Parça temin edildikten sonra kalan montaj için tekrar randevu oluşturuyoruz.'],
                ],
            ],
            [
                'name' => 'Almila',
                'slug' => 'almila',
                'description' => 'Almila mobilyalarının kurulum hizmeti.',
                'products' => ['Yatak odası takımı', 'Gardırop', 'Genç odası', 'TV ünitesi'],
                'content' => '<p>Almila ürünlerinin montajını '.$region.' genelinde yapıyoruz. Paketli gelen gövdeleri kılavuza uygun şekilde birleştirip kapak, çekmece ve menteşe ayarlarını yapıyoruz.</p><p>Yüksek gardırop ve raf sistemlerini devrilmeye karşı duvara sabitliyoruz.</p>',
                'faqs' => [
                    ['question' => 'Montaj öncesi ne hazırlamalıyım?', 'answer' => 'Mobilyanın kurulacağı alanın boş olması ve parçaları yayabileceğimiz yaklaşık 2 m²\'lik bir yer yeterli. Alet ve bağlantı malzemeleri bizde.'],
                ],
            ],
            [
                'name' => 'Çilek',
                'slug' => 'cilek',
                'is_featured' => true,
                'description' => 'Çilek çocuk ve genç odası mobilyalarının montajı.',
                'products' => ['Çocuk odası takımı', 'Genç odası takımı', 'Ranza', 'Çalışma masası', 'Gardırop'],
                'content' => '<p>Çilek, çocuk ve genç odası mobilyalarında uzmanlaşmış bir markadır. Bu odalarda güvenlik montajın en önemli parçasıdır: ranza korkuluklarının doğru takılması, merdiven sabitlemesi ve yüksek gardıropların duvara bağlanması ihmal edilemez.</p><p>'.$region.' genelinde hizmet veriyoruz. Çocuk odasındaki tüm devrilme riskli mobilyaları standart olarak duvara sabitliyor, keskin köşe ve açıkta vida kalıp kalmadığını iş sonunda birlikte kontrol ediyoruz.</p>',
                'faqs' => [
                    ['question' => 'Ranza montajında güvenlik için ne yapıyorsunuz?', 'answer' => 'Korkulukları ve merdiveni kılavuzdaki tüm bağlantı noktalarından sabitliyor, gövdeyi duvara bağlıyoruz. İş bitiminde açıkta vida veya gevşek bağlantı kalmadığını birlikte kontrol ediyoruz.'],
                    ['question' => 'Çocuk odası mobilyalarını duvara sabitliyor musunuz?', 'answer' => 'Evet, standart olarak ve ek ücret almadan. Çocuklu evlerde yüksek mobilyanın devrilmesi ciddi bir risktir.'],
                ],
            ],
            [
                'name' => 'Alfemo',
                'slug' => 'alfemo',
                'description' => 'Alfemo yatak odası, oturma grubu ve yemek odası montajı.',
                'products' => ['Yatak odası takımı', 'Koltuk takımı', 'Yemek odası takımı', 'TV ünitesi'],
                'content' => '<p>Alfemo ürünlerinin kurulumunu '.$region.' genelinde yapıyoruz. Koltuk takımlarında ayak montajı ve modül birleştirme, yatak odalarında baza, başlık ve gardırop kurulumu en sık yaptığımız işler.</p><p>Montaj sonrası tüm kapak ve çekmece ayarlarını yapıp ambalajları topluyoruz.</p>',
                'faqs' => [
                    ['question' => 'Köşe koltuk modüllerini birleştiriyor musunuz?', 'answer' => 'Evet. Modüller arası bağlantı elemanlarını takıp koltuğun tek parça gibi durmasını sağlıyoruz.'],
                ],
            ],
            [
                'name' => 'Veltev',
                'slug' => 'veltev',
                'description' => 'Veltev mobilyalarının kurulum ve montaj hizmeti.',
                'products' => ['Yatak odası takımı', 'Gardırop', 'Koltuk takımı', 'TV ünitesi'],
                'content' => '<p>Veltev ürünlerinin montajını '.$region.' genelinde yapıyoruz. Paketli gelen ürünü kutu listesiyle karşılaştırıp kurulum planını çıkarıyor, ardından gövde birleştirme ve ayar işlemlerini yapıyoruz.</p><p>Taşınma durumunda mevcut mobilyanızı söküp yeni adreste yeniden kurabiliyoruz.</p>',
                'faqs' => [
                    ['question' => 'Taşınma için sökme hizmeti veriyor musunuz?', 'answer' => 'Evet. Sökme ve kurma işlemleri ayrı ayrı veya birlikte planlanabilir.'],
                ],
            ],
            [
                'name' => 'IKEA',
                'slug' => 'ikea',
                'is_featured' => true,
                'description' => 'IKEA mobilyalarının kurulum ve montaj hizmeti.',
                'products' => ['PAX gardırop', 'BESTÅ ünite', 'KALLAX raf', 'MALM yatak', 'BILLY kitaplık', 'Masa ve sandalye'],
                'content' => '<p>IKEA ürünleri kendi bağlantı sistemiyle gelir ve kurulumu diğer markalardan farklıdır. Kamlı bağlantı elemanları, dübel yerleşimi ve iç düzenleyicilerin sıralaması doğru yapılmazsa gövde sonradan gevşer.</p><p>Trakya\'da IKEA mağazası bulunmadığı için ürünler genellikle kargo veya nakliyeyle gelir. '.$region.' genelinde PAX gardırop, BESTÅ ve KALLAX üniteler, MALM yatak odası ürünleri ve BILLY kitaplık kurulumu yapıyoruz.</p>',
                'faqs' => [
                    ['question' => 'PAX gardırop montajı ne kadar sürer?', 'answer' => 'İki kapaklı standart bir PAX yaklaşık 2 saat, sürgülü kapaklı ve iç düzenleyicili üçlü PAX 4-6 saat sürebilir.'],
                    ['question' => 'IKEA\'nın kendi montaj hizmeti yerine neden sizi seçmeliyim?', 'answer' => 'Trakya\'da IKEA mağazası olmadığı için resmi montaj hizmeti her ilçeye ulaşmayabilir. Biz bölgedeki ilçelere geliyor ve randevuyu sizin takviminize göre planlıyoruz.'],
                ],
            ],
            [
                'name' => 'Teksen Mobilya',
                'slug' => 'teksen-mobilya',
                'description' => 'Teksen mobilyalarının kurulum hizmeti.',
                'products' => ['Yatak odası takımı', 'Gardırop', 'Yemek odası takımı'],
                'content' => '<p>Teksen ürünlerinin montajını '.$region.' genelinde yapıyoruz. Gövde birleştirme, kapak ve çekmece ayarı ile duvara sabitleme işlemlerini kendi ekipmanımızla yapıyoruz.</p><p>Fiyatı işe başlamadan önce net olarak bildiriyoruz; sürpriz ücret çıkmaz.</p>',
                'faqs' => [
                    ['question' => 'Fiyat nasıl belirleniyor?', 'answer' => 'Ürünün türüne, parça sayısına, kapak ve çekmece adedine ve adresinizin bulunduğu ilçeye göre belirlenir. WhatsApp\'tan fotoğraf gönderin, işe başlamadan net fiyat verelim.'],
                ],
            ],
            [
                'name' => 'Koçtaş',
                'slug' => 'koctas',
                'description' => 'Koçtaş\'tan alınan hazır mobilya ve raf sistemlerinin montajı.',
                'products' => ['Raf sistemi', 'Dolap', 'Çalışma masası', 'Banyo dolabı', 'Depolama ünitesi'],
                'content' => '<p>Koçtaş\'tan aldığınız paketli mobilya ve raf sistemlerinin kurulumunu '.$region.' genelinde yapıyoruz. Duvara monte edilen raf ve dolaplarda duvar tipine uygun dübel seçimi işin en kritik adımıdır; beton, tuğla ve alçıpan için farklı dübel kullanıyoruz.</p><p>Delme öncesinde duvar içinde tesisat olup olmadığını kontrol ediyoruz.</p>',
                'faqs' => [
                    ['question' => 'Duvara raf takarken tesisata denk gelme riski var mı?', 'answer' => 'Delme öncesinde duvar içinde elektrik ve su tesisatı olup olmadığını kontrol ediyoruz. Riskli bölgelerde konumu sizinle birlikte belirliyoruz.'],
                ],
            ],
        ];
    }
}
