<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Oda tipine göre kurulum hizmetleri.
 *
 * Mevcut ServiceSeeder ürün bazlı (gardırop, baza, kitaplık); bu seeder ise
 * ODA bazlı arama niyetini karşılar ("yatak odası montajı", "koltuk takımı kurulumu").
 * İçerik ürün sayfalarıyla çakışmasın diye her sayfa odanın BÜTÜNÜNÜ ve o odaya
 * özgü montaj sorunlarını anlatır; tek bir parçayı değil.
 */
class RoomServiceSeeder extends Seeder
{
    public function run(): void
    {
        $offset = (int) Service::query()->max('sort_order');
        $extra = require __DIR__.'/data/room_service_extra.php';

        foreach ($this->services() as $i => $data) {
            $service = Service::query()->firstOrCreate(
                ['slug' => $data['slug']],
                $data + ['sort_order' => $offset + $i + 1, 'is_active' => true, 'is_featured' => false]
            );

            // Kapanış bölümü yoksa eklenir; elle yazılmış metne dokunulmaz.
            if (isset($extra[$data['slug']]) && ! str_contains((string) $service->description, 'markalar sayfas')) {
                $service->update(['description' => $service->description.$extra[$data['slug']]]);
            }
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
                'title' => 'Yatak Odası Montajı',
                'slug' => 'yatak-odasi-montaji',
                'icon' => 'fa-solid fa-bed',
                'image' => 'services/yatak-odasi-montaji.webp',
                'image_alt' => 'Montajı tamamlanmış yatak odası takımı: gardırop, baza ve şifonyer',
                'short_description' => 'Gardırop, baza, başlık, şifonyer ve komodinden oluşan komple yatak odası takımının kurulumu.',
                'description' => '<p>Yatak odası takımı, bir evde tek seferde kurulan en büyük mobilya grubudur. Gardırop, baza, başlık, şifonyer, komodin ve aynadan oluşan takımın tamamını tek randevuda kuruyoruz. Marka fark etmiyor; paketli gelen her yatak odası takımını üretici kılavuzuna uygun şekilde monte ediyoruz.</p><p>Bu odada montajın sırası önemlidir: gardırop önce gövde olarak yatırılıp birleştirilir, sonra dikilir. Oda dar ise gardırobun dikilebilmesi için tavan yüksekliğinin ölçülmesi gerekir; ekibimiz işe başlamadan bu ölçüyü alır. Baza ve başlık gardıroptan sonra kurulur ki alan boş kalsın.</p><p>Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine geliyoruz. İş bitiminde kapak hizalarını, çekmece raylarını ve sandıklı baza pistonunu birlikte kontrol ediyor, ambalajları topluyoruz.</p>',
                'what_we_do' => [
                    'Gardırop gövde montajı, dikme ve duvara sabitleme',
                    'Sandıklı baza kurulumu ve piston ayarı',
                    'Yatak başlığının bazaya bağlanması',
                    'Şifonyer, komodin ve ayna montajı',
                    'Sürgülü kapak rayı ve menteşe ayarları',
                    'Taşınma için yatak odasının sökülmesi ve yeni adreste kurulması',
                ],
                'suitable_for' => [
                    'Yeni ev kuran ve çeyiz takımı alan aileler',
                    'Yatak odası takımını mağazadan veya internetten alanlar',
                    'Taşınırken takımını söktürmek isteyenler',
                    'İnegöl\'den nakliyeyle takım getirtenler',
                    'Kiralık daire ve rezidans sahipleri',
                ],
                'process_steps' => $this->steps('yatak odası montajı'),
                'faqs' => [
                    ['question' => 'Komple yatak odası takımı montajı ne kadar sürer?', 'answer' => 'Gardırop, baza, başlık, şifonyer ve komodinden oluşan standart bir takım 3-5 saat sürer. Sürgülü kapaklı ve iç düzenleyicili gardıroplarda bu süre uzayabilir.'],
                    ['question' => 'Takımın sadece bir parçasını kurdurabilir miyim?', 'answer' => 'Evet. Yalnız gardırop veya yalnız baza montajı da yapıyoruz; fiyat parça bazında belirlenir.'],
                    ['question' => 'Gardırop odaya sığmazsa ne oluyor?', 'answer' => 'Montaj öncesi oda ve tavan ölçüsünü alıyoruz. Gardırop dikilemeyecek kadar yüksekse gövdeyi yerinde birleştirmek gibi alternatif yöntemler deniyor, mümkün değilse size başlamadan bildiriyoruz.'],
                    ['question' => 'Gardırobu duvara sabitliyor musunuz?', 'answer' => 'Evet, standart olarak. Yüksek gardıroplar devrilme riski taşıdığı için duvar tipine uygun dübelle sabitliyoruz; bu işlem için ek ücret almıyoruz.'],
                ],
                'meta_title' => 'Yatak Odası Montajı | Tekirdağ, Edirne, Kırklareli',
                'meta_description' => 'Gardırop, baza, başlık ve şifonyerden oluşan komple yatak odası takımı montajı. Trakya\'nın tüm ilçelerine geliyoruz. Fotoğraf gönderin, net fiyat alın.',
            ],
            [
                'title' => 'Yemek Odası Montajı',
                'slug' => 'yemek-odasi-montaji',
                'icon' => 'fa-solid fa-utensils',
                'image' => 'services/yemek-odasi-montaji.webp',
                'image_alt' => 'Montajı tamamlanmış yemek odası takımı: masa, sandalye ve vitrin',
                'short_description' => 'Masa, sandalye, vitrin ve konsoldan oluşan yemek odası takımının kurulumu.',
                'description' => '<p>Yemek odası takımı montajında iş, masanın kurulmasından ibaret değildir. Vitrin ve konsol bu odanın en ağır ve en riskli parçalarıdır: cam raflar, cam kapaklar ve yüksek gövde hem dikkatli taşıma hem de mutlaka duvara sabitleme gerektirir.</p><p>Açılır masalarda ray ve kilit mekanizmasının doğru ayarlanması, masanın sonradan zor açılmaması için kritiktir. Sandalyelerde ise ayak bağlantılarını kılavuzdaki tork sırasına göre sıkıyoruz; yanlış sırada sıkılan sandalye kısa sürede sallanmaya başlar.</p><p>Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerinde hizmet veriyoruz. Takımın tamamını tek randevuda kuruyor, cam parçaları en son takıyoruz.</p>',
                'what_we_do' => [
                    'Yemek masası ve açılır masa mekanizmasının kurulumu',
                    'Sandalye ayak ve sırtlık montajı',
                    'Vitrin gövde montajı, cam raf ve kapak takılması',
                    'Konsol kurulumu ve duvara sabitleme',
                    'Aynalı konsol ve büfe montajı',
                    'Taşınma için yemek odasının sökülmesi ve yeniden kurulması',
                ],
                'suitable_for' => [
                    'Yeni ev kuran aileler ve çeyiz sahipleri',
                    'Yemek odası takımını mağazadan alanlar',
                    'Nakliyeyle sökülmüş takım getirtenler',
                    'Restoran, kafe ve toplu yemek alanları',
                    'Taşınma sürecindeki evler',
                ],
                'process_steps' => $this->steps('yemek odası montajı'),
                'faqs' => [
                    ['question' => 'Yemek odası takımı montajı ne kadar sürer?', 'answer' => 'Masa, altı sandalye, vitrin ve konsoldan oluşan standart bir takım 2,5-4 saat sürer. Parça sayısı arttıkça süre uzar.'],
                    ['question' => 'Cam raf ve kapakları siz mi takıyorsunuz?', 'answer' => 'Evet. Cam parçaları montajın en sonunda takıyor, o ana kadar ambalajında bırakıyoruz. Kutudan kırık çıkan cam varsa işe başlamadan bildiriyoruz.'],
                    ['question' => 'Vitrini duvara sabitlemek şart mı?', 'answer' => 'Yüksek vitrin ve büfelerde evet. Çekmece açıldığında ağırlık merkezi öne kayar ve devrilme riski doğar; standart olarak sabitliyoruz.'],
                    ['question' => 'Açılır masa sonradan zor açılıyor, ayar yapar mısınız?', 'answer' => 'Evet. Ray ve kilit mekanizması ayarı tek başına da hizmet olarak veriyoruz; montaj yapmadığımız masalar için de gelebiliriz.'],
                ],
                'meta_title' => 'Yemek Odası Montajı | Tekirdağ, Edirne, Kırklareli',
                'meta_description' => 'Masa, sandalye, vitrin ve konsoldan oluşan yemek odası takımı montajı. Cam raflar dikkatle takılır, vitrin duvara sabitlenir. Trakya geneli hizmet.',
            ],
            [
                'title' => 'Koltuk Takımı Montajı',
                'slug' => 'koltuk-takimi-montaji',
                'icon' => 'fa-solid fa-couch',
                'image' => 'services/koltuk-takimi-montaji.webp',
                'image_alt' => 'Montajı tamamlanmış köşe koltuk takımı bulunan oturma odası',
                'short_description' => 'Üçlü-ikili-tekli ve köşe koltuk takımlarının kurulumu, modül birleştirme ve ayak montajı.',
                'description' => '<p>Koltuk takımları genellikle ayakları sökülü ve modülleri ayrı olarak teslim edilir. Montaj; ayakların takılması, köşe koltuklarda modüllerin birbirine bağlanması ve çekyat mekanizmasının kontrol edilmesinden oluşur.</p><p>Köşe koltuklarda en sık yaşanan sorun, modüllerin bağlantı elemanı takılmadan yan yana konmasıdır. Bu durumda koltuk kullandıkça açılır ve arada boşluk oluşur. Biz modülleri üreticinin kendi bağlantı aparatıyla kilitliyoruz; aparat kutudan çıkmadıysa uygun alternatifi kendimiz temin ediyoruz.</p><p>Dar merdiven ve asansörü olmayan binalarda koltuğun içeri alınması ayrı bir iştir; kat ve merdiven bilgisini önceden paylaşırsanız ekibi ona göre planlıyoruz. Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine geliyoruz.</p>',
                'what_we_do' => [
                    'Üçlü, ikili ve tekli koltuk ayak montajı',
                    'Köşe koltuk modüllerinin bağlantı aparatıyla birleştirilmesi',
                    'Çekyat ve yatak olabilen koltuk mekanizmasının kontrolü',
                    'Puf, şezlong ve modül ilavelerinin eklenmesi',
                    'Sandıklı koltuklarda kapak ve menteşe ayarı',
                    'Taşınma için koltuk takımının sökülmesi ve yeniden kurulması',
                ],
                'suitable_for' => [
                    'Yeni koltuk takımı alan ev sahipleri',
                    'Köşe koltuk ve modüler oturma grubu sahipleri',
                    'Taşınırken koltuğu kapıdan geçmeyenler',
                    'Ofis bekleme alanları ve kafeler',
                    'İnternetten koltuk sipariş edenler',
                ],
                'process_steps' => $this->steps('koltuk takımı montajı'),
                'faqs' => [
                    ['question' => 'Koltuk takımı montajı ne kadar sürer?', 'answer' => 'Üçlü-ikili-tekli standart takım 45-90 dakika sürer. Köşe koltuklarda modül sayısına göre süre uzayabilir.'],
                    ['question' => 'Koltuk kapıdan geçmiyor, ne yapıyorsunuz?', 'answer' => 'Çoğu koltukta ayaklar ve sırt modülleri sökülebilir; sökerek içeri alıp odada kuruyoruz. Sökülemeyen gövdelerde balkon veya pencere yolu değerlendirilir.'],
                    ['question' => 'Köşe koltuk modülleri arasında boşluk kalır mı?', 'answer' => 'Bağlantı aparatı doğru takıldığında kalmaz. Aparat kutudan çıkmadıysa uygun alternatifini biz temin ediyoruz.'],
                    ['question' => 'Taşınma için koltuğu söker misiniz?', 'answer' => 'Evet. Sökme ve yeni adreste kurma işlemlerini birlikte veya ayrı ayrı planlayabiliriz.'],
                ],
                'meta_title' => 'Koltuk Takımı Montajı | Tekirdağ, Edirne, Kırklareli',
                'meta_description' => 'Köşe koltuk ve üçlü-ikili-tekli takımların montajı, modül birleştirme, ayak takma ve çekyat kontrolü. Trakya\'nın tüm ilçelerine geliyoruz.',
            ],
            [
                'title' => 'Genç Odası Montajı',
                'slug' => 'genc-odasi-montaji',
                'icon' => 'fa-solid fa-graduation-cap',
                'image' => 'services/genc-odasi-montaji.webp',
                'image_alt' => 'Montajı tamamlanmış genç odası: çalışma masası, gardırop ve raf sistemi',
                'short_description' => 'Çalışma masası, gardırop, yatak ve raf sisteminden oluşan genç odası takımının kurulumu.',
                'description' => '<p>Genç odası takımları çok parçalıdır: gardırop, çalışma masası, kitaplık, yatak veya ranza, komodin ve çoğu zaman duvar rafları. Parçaların oda içinde doğru sırayla kurulması gerekir, aksi hâlde alan yetmez ve kurulan bir mobilya sökülmek zorunda kalır. Ekibimiz işe başlamadan yerleşim planını sizinle birlikte belirler.</p><p>Bu odada güvenlik ayrı bir başlıktır. Üst raf sistemleri ve yüksek gardıroplar mutlaka duvara sabitlenmelidir; çalışma masası üstü köprü raflarda duvar tipine uygun dübel seçimi taşıma kapasitesini belirler.</p><p>Tekirdağ, Edirne ve Kırklareli genelinde hizmet veriyoruz. Takımın tamamını tek randevuda kuruyor, iş bitiminde açıkta vida veya gevşek bağlantı kalmadığını birlikte kontrol ediyoruz.</p>',
                'what_we_do' => [
                    'Genç odası gardırobunun kurulumu ve duvara sabitlenmesi',
                    'Çalışma masası, keson ve üst köprü rafı montajı',
                    'Karyola, bazalı yatak ve çekmeceli yatak kurulumu',
                    'Kitaplık ve duvar rafı sistemlerinin monte edilmesi',
                    'Raf, çekmece ve menteşe ayarlarının yapılması',
                    'Oda yerleşiminin montaj öncesinde birlikte planlanması',
                ],
                'suitable_for' => [
                    'Okul çağındaki çocuğu için oda kuran aileler',
                    'Üniversite ve öğrenci evleri',
                    'Genç odası takımını mağazadan veya internetten alanlar',
                    'Çocuk odasını genç odasına dönüştürenler',
                    'Taşınma sürecindeki aileler',
                ],
                'process_steps' => $this->steps('genç odası montajı'),
                'faqs' => [
                    ['question' => 'Genç odası takımının tamamı tek günde kurulur mu?', 'answer' => 'Çoğu takım tek randevuda kurulur. Gardırop, çalışma masası, yatak ve raf sistemi birlikte yaklaşık 3-5 saat sürer.'],
                    ['question' => 'Duvar raflarını asmak fiyata dahil mi?', 'answer' => 'Takımın kendi raf sistemi montaja dahildir. Ayrıca satın alınan ek rafları da takıyoruz; parça sayısına göre fiyat belirtiriz.'],
                    ['question' => 'Mobilyaları duvara sabitliyor musunuz?', 'answer' => 'Evet, standart olarak ve ek ücret almadan. Genç odasındaki yüksek gardırop ve raf sistemleri devrilme riski taşır.'],
                    ['question' => 'Odaya nasıl yerleştireceğime karar veremedim, yardımcı olur musunuz?', 'answer' => 'Montaja başlamadan önce pencere, priz ve kapı konumuna göre birkaç yerleşim seçeneği sunuyoruz. Kararı siz veriyorsunuz, biz ona göre kuruyoruz.'],
                ],
                'meta_title' => 'Genç Odası Montajı | Tekirdağ, Edirne, Kırklareli',
                'meta_description' => 'Çalışma masası, gardırop, yatak ve raf sisteminden oluşan genç odası takımı montajı. Tüm yüksek mobilyalar duvara sabitlenir. Trakya geneli hizmet.',
            ],
            [
                'title' => 'Çocuk Odası Montajı',
                'slug' => 'cocuk-odasi-montaji',
                'icon' => 'fa-solid fa-child-reaching',
                'image' => 'services/cocuk-odasi-montaji.webp',
                'image_alt' => 'Montajı tamamlanmış çocuk odası: ranza, dolap ve oyun alanı',
                'short_description' => 'Ranza, karyola, bebek beşiği ve dolaptan oluşan çocuk odası takımının güvenli kurulumu.',
                'description' => '<p>Çocuk odası montajında birinci öncelik güvenliktir. Ranza korkuluklarının kılavuzdaki tüm bağlantı noktalarından sabitlenmesi, merdivenin gövdeye kilitlenmesi ve yüksek dolapların duvara bağlanması ihmal edilemez adımlardır. Bu maddelerin hiçbirini atlamıyor, iş bitiminde hepsini sizinle birlikte kontrol ediyoruz.</p><p>Bebek odalarında beşik ve alt değiştirme ünitesi ayrıca dikkat ister: beşik yan ayar mekanizmasının kilidi ve ünitenin devrilmeye karşı sabitlenmesi kurulumun parçasıdır.</p><p>Montaj sonrası odada açıkta vida, keskin köşe veya gevşek bağlantı kalmadığından emin oluyoruz. Ambalaj, naylon ve küçük parçaları toplayıp götürüyoruz; bunlar küçük çocuklar için risk oluşturur. Tekirdağ, Edirne ve Kırklareli\'nin tüm ilçelerine geliyoruz.</p>',
                'what_we_do' => [
                    'Ranza kurulumu, korkuluk ve merdiven sabitlemesi',
                    'Karyola, çekmeceli yatak ve yer yatağı montajı',
                    'Bebek beşiği ve alt değiştirme ünitesi kurulumu',
                    'Çocuk dolabı ve oyuncak rafı montajı',
                    'Tüm yüksek mobilyaların devrilmeye karşı duvara sabitlenmesi',
                    'Montaj sonrası güvenlik kontrolü ve ambalaj temizliği',
                ],
                'suitable_for' => [
                    'Bebek odası hazırlayan aileler',
                    'Kardeşler için ranza alan evler',
                    'Çocuk odası takımını mağazadan veya internetten alanlar',
                    'Anaokulu, kreş ve çocuk kulüpleri',
                    'Taşınırken çocuk odasını söktürmek isteyenler',
                ],
                'process_steps' => $this->steps('çocuk odası montajı'),
                'faqs' => [
                    ['question' => 'Ranza montajında güvenlik için ne yapıyorsunuz?', 'answer' => 'Korkulukları ve merdiveni kılavuzdaki tüm bağlantı noktalarından sabitliyor, gövdeyi duvara bağlıyoruz. İş bitiminde açıkta vida veya gevşek bağlantı kalmadığını birlikte kontrol ediyoruz.'],
                    ['question' => 'Çocuk odası mobilyalarını duvara sabitliyor musunuz?', 'answer' => 'Evet, standart olarak ve ek ücret almadan. Çocuklu evlerde yüksek mobilyanın devrilmesi ciddi bir risktir.'],
                    ['question' => 'Ranza montajı ne kadar sürer?', 'answer' => 'Standart bir ranza 1,5-2,5 saat sürer. Çekmeceli ve dolaplı modellerde süre uzayabilir.'],
                    ['question' => 'Ambalajları siz topluyor musunuz?', 'answer' => 'Evet. Karton, naylon ve artan küçük parçaları topluyoruz; bunlar küçük çocuklar için risk oluşturduğu için odada bırakmıyoruz.'],
                ],
                'meta_title' => 'Çocuk Odası Montajı | Tekirdağ, Edirne, Kırklareli',
                'meta_description' => 'Ranza, karyola, beşik ve dolaptan oluşan çocuk odası montajı. Korkuluk, merdiven ve duvar sabitlemesi dahil güvenli kurulum. Trakya geneli hizmet.',
            ],
        ];
    }
}
