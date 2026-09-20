<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->provinces() as $pi => $p) {
            $province = Province::query()->firstOrCreate(
                ['slug' => Str::slug($p['name'])],
                [
                    'name' => $p['name'],
                    'description' => $p['description'],
                    'content' => $p['content'],
                    'faqs' => $p['faqs'],
                    'meta_title' => $p['name'].' Mobilya Montaj Hizmeti | Tüm İlçelere Montaj',
                    'meta_description' => $p['meta_description'],
                    'sort_order' => $pi + 1,
                ]
            );

            foreach ($p['districts'] as $di => $d) {
                [$name, $description, $content, $neighborhoods, $faq] = $d;

                District::query()->firstOrCreate(
                    ['province_id' => $province->id, 'slug' => Str::slug($name)],
                    [
                        'name' => $name,
                        'description' => $description,
                        'content' => $content,
                        'neighborhoods' => $neighborhoods,
                        'faqs' => array_merge([$faq], $this->commonFaqs($name, $p['name'])),
                        'meta_title' => $name.' Mobilya Montaj Hizmeti | '.$p['name'],
                        'meta_description' => $name.' ve çevresinde gardırop, yatak, baza, TV ünitesi, IKEA ve ofis mobilyası montajı. '.$name.' mahallelerine hızlı randevu, işe başlamadan net fiyat. WhatsApp\'tan teklif alın.',
                        'sort_order' => $di + 1,
                    ]
                );
            }
        }
    }

    private function commonFaqs(string $district, string $province): array
    {
        return [
            [
                'question' => $district.' ilçesinin tüm mahallelerine geliyor musunuz?',
                'answer' => 'Evet. '.$district.' merkez mahalleleri ile bağlı köy ve beldelere de montaj için geliyoruz. Adresinizi WhatsApp\'tan paylaşmanız yeterli.',
            ],
            [
                'question' => $district.' için montaj randevusu ne zaman oluşur?',
                'answer' => $district.' ('.$province.') bölgesindeki talepleri aynı güne planlayarak çoğunlukla 1-2 iş günü içinde randevu veriyoruz. Acil durumlarda aynı gün seçeneğini sorabilirsiniz.',
            ],
        ];
    }

    private function provinces(): array
    {
        return [
            [
                'name' => 'Tekirdağ',
                'description' => 'Tekirdağ\'ın 11 ilçesinde gardırop, yatak-baza, TV ünitesi, IKEA ve ofis mobilyası montajı.',
                'meta_description' => 'Tekirdağ mobilya montaj hizmeti: Çorlu, Çerkezköy, Kapaklı, Süleymanpaşa, Ergene ve tüm ilçelerde profesyonel mobilya kurulumu. WhatsApp\'tan hızlı teklif.',
                'content' => '<p>Tekirdağ, Trakya\'nın en kalabalık ili ve ekibimizin merkezi. İstanbul\'a yakınlığı, Çorlu-Çerkezköy-Kapaklı hattındaki sanayi bölgeleri ve sürekli yükselen yeni konut projeleri nedeniyle Tekirdağ\'da taşınma ve yeni ev kurma hareketliliği yüksek. Bu da beraberinde yoğun bir mobilya montaj ihtiyacı getiriyor.</p><p>Süleymanpaşa\'daki öğrenci evlerinden Marmaraereğlisi ve Şarköy\'deki yazlık sitelere, Çorlu\'nun merkez mahallelerinden Malkara ve Hayrabolu\'nun köylerine kadar ilin her noktasına kendi ekipmanımızla geliyoruz. İlçe bazında aynı güne planlanan randevularla hem hızlı hem de uygun fiyatlı hizmet sunuyoruz.</p>',
                'faqs' => [
                    ['question' => 'Tekirdağ\'ın hangi ilçelerine hizmet veriyorsunuz?', 'answer' => 'Süleymanpaşa, Çorlu, Çerkezköy, Kapaklı, Ergene, Marmaraereğlisi, Şarköy, Malkara, Hayrabolu, Muratlı ve Saray olmak üzere Tekirdağ\'ın 11 ilçesinin tamamına geliyoruz.'],
                    ['question' => 'Tekirdağ\'da uzak ilçeler için ek yol ücreti alınıyor mu?', 'answer' => 'Merkeze uzak ilçeler için mesafe fiyata yansıtılır; ancak bunu işe başlamadan önce tek bir net rakam olarak bildiririz. Aynı bölgede birden fazla iş olduğunda daha uygun fiyat sunabiliyoruz.'],
                ],
                'districts' => [
                    ['Çorlu', 'Tekirdağ\'ın en kalabalık ilçesi Çorlu\'da mobilya montajı: merkez mahalleler, siteler ve yeni konut projeleri.',
                        '<p>Çorlu, Tekirdağ\'ın en kalabalık ilçesi ve Trakya\'nın sanayi merkezi. Organize sanayi bölgelerinde çalışan nüfusun yoğunluğu, sürekli yeni siteler ve toplu konut projeleri Çorlu\'yu taşınma hareketinin en yoğun olduğu ilçe yapıyor. Ekibimiz Çorlu merkezli olduğundan çoğu talebe aynı gün randevu verebiliyoruz.</p><p>Reşadiye, Kemalettin, Hıdırağa, Kazımiye ve Şeyhsinan gibi merkez mahallelerin yanı sıra Alipaşa, Havuzlar ve Esentepe\'deki site projelerine de düzenli olarak gidiyoruz. Yatak odası takımı, gardırop, TV ünitesi ve IKEA mobilyası montajı Çorlu\'da en çok talep edilen hizmetlerimiz.</p>',
                        ['Reşadiye', 'Kemalettin', 'Hıdırağa', 'Cemaliye', 'Nusratiye', 'Şeyhsinan', 'Hatip', 'Alipaşa', 'Zafer', 'Kazımiye', 'Silahtarağa', 'Muhittin', 'Hürriyet', 'Esentepe', 'Havuzlar', 'Rumeli', 'Yenice', 'Çobançeşme', 'Cumhuriyet'],
                        ['question' => 'Çorlu\'da aynı gün montaj mümkün mü?', 'answer' => 'Ekibimiz Çorlu merkezli olduğu için öğleden önce gelen taleplerin çoğuna aynı gün içinde randevu verebiliyoruz.'],
                    ],
                    ['Çerkezköy', 'Çerkezköy OSB çevresindeki yeni siteler ve merkez mahallelerde hızlı mobilya montaj hizmeti.',
                        '<p>Çerkezköy, Türkiye\'nin en büyük organize sanayi bölgelerinden birine ev sahipliği yapıyor ve çalışan nüfusun büyük bölümü ilçedeki yeni konut projelerinde yaşıyor. Fabrika lojmanları, kiralık daireler ve site projeleri nedeniyle Çerkezköy\'de taşınma ve yeni ev kurma talebi yıl boyu sürüyor.</p><p>Cumhuriyet, Fevzipaşa, Gazi Mustafa Kemal Paşa ve Kızılpınar mahallelerindeki sitelere; Veliköy ve Bağlık\'taki yeni konutlara mobilya montajı için sık sık gidiyoruz. Çerkezköy Kapaklı ile bitişik olduğu için iki ilçedeki talepleri aynı güne planlayıp hızlı randevu verebiliyoruz.</p>',
                        ['Cumhuriyet', 'Fevzipaşa', 'Gazi Mustafa Kemal Paşa', 'Gazi Osman Paşa', 'Kızılpınar Atatürk', 'Kızılpınar Gültepe', 'Kızılpınar Namık Kemal', 'Veliköy', 'Bağlık', 'Yıldırım Beyazıt', 'Fatih', 'İstasyon'],
                        ['question' => 'Çerkezköy\'de fabrika lojmanlarına ve sitelere giriş için ne gerekiyor?', 'answer' => 'Site ve lojman girişlerinde güvenliğe bildirim yapmanız yeterli. Ekibimiz randevu saatinden önce sizi arayarak giriş için gerekli bilgiyi alır.'],
                    ],
                    ['Kapaklı', 'Hızla büyüyen Kapaklı\'nın yeni konut projelerinde yatak odası, gardırop ve IKEA mobilyası montajı.',
                        '<p>Kapaklı, son yıllarda Türkiye\'nin en hızlı büyüyen ilçelerinden biri. Çerkezköy OSB\'ye yakınlığı sayesinde genç ve çalışan nüfus ilçedeki yeni yapılan sitelere yerleşiyor; bu da yeni ev kurma ve mobilya montajı talebini sürekli canlı tutuyor.</p><p>Atatürk, Cumhuriyet, İnönü ve İsmetpaşa mahallelerindeki site projeleri ile Karaağaç ve Uzunhacı bölgelerindeki yeni konutlara düzenli hizmet veriyoruz. İnternetten sipariş edilen paketli mobilyaların ve IKEA ürünlerinin kurulumu Kapaklı\'da en çok talep gören işlerimiz arasında.</p>',
                        ['Atatürk', 'Cumhuriyet', 'İnönü', 'İsmetpaşa', 'Bahçelievler', 'Karaağaç', 'Uzunhacı', 'Pınarça', 'Bahçeağıl', 'Yanıkağıl', 'Karlıköy'],
                        ['question' => 'Kapaklı\'daki yeni sitelerde asansör olmayan katlara da montaj yapıyor musunuz?', 'answer' => 'Evet. Paketlerin daireye taşınmış olması yeterlidir; taşıma ihtiyacınız varsa randevu öncesinde belirtin, ekip sayısını buna göre ayarlayalım.'],
                    ],
                    ['Süleymanpaşa', 'Tekirdağ il merkezi Süleymanpaşa\'da öğrenci evleri, sahil siteleri ve yazlıklar için mobilya montajı.',
                        '<p>Süleymanpaşa, Tekirdağ\'ın il merkezi ve sahil ilçesi. Namık Kemal Üniversitesi nedeniyle yoğun bir öğrenci nüfusu, Değirmenaltı ve Altınova\'daki sahil siteleri ile Kumbağ ve Barbaros\'taki yazlıklar farklı dönemlerde farklı montaj ihtiyaçları doğuruyor.</p><p>Eylül-ekim aylarında öğrenci evleri için baza, çalışma masası ve kitaplık; yaz başında yazlık siteler için oturma grubu ve yatak odası montajı en sık talep ettiğimiz işler. Hürriyet, Aydoğdu, Yavuz ve 100. Yıl mahalleleri başta olmak üzere ilçenin tamamına geliyoruz.</p>',
                        ['Hürriyet', 'Aydoğdu', 'Altınova', 'Değirmenaltı', 'Yavuz', 'Eskicami', 'Ertuğrul', 'Çınarlı', 'Karadeniz', 'Turgut', 'Zafer', 'Gündoğdu', 'Namık Kemal', '100. Yıl', 'Barbaros', 'Kumbağ', 'Banarlı', 'Karacakılavuz'],
                        ['question' => 'Süleymanpaşa\'da öğrenci evleri için uygun fiyatlı montaj var mı?', 'answer' => 'Evet. Baza, masa ve kitaplık gibi tek parça montajlar için öğrenci evlerine uygun fiyat veriyor, aynı apartman veya bölgedeki talepleri birleştirerek daha da uygun hale getiriyoruz.'],
                    ],
                    ['Ergene', 'Velimeşe, Ulaş ve Marmaracık başta olmak üzere Ergene\'nin tüm mahallelerinde mobilya montajı.',
                        '<p>Ergene, Çorlu\'dan ayrılarak ilçe olan ve Velimeşe, Ulaş, Marmaracık gibi sanayi ağırlıklı yerleşimleri kapsayan bir ilçe. Çorlu ile iç içe olması nedeniyle Ergene\'deki taleplere Çorlu ile aynı hızda, çoğunlukla aynı gün ulaşıyoruz.</p><p>Velimeşe ve Ulaş\'taki işçi lojmanları ve kiralık daireler ile Marmaracık ve Sağlık mahallelerindeki yeni konutlara yatak, baza, gardırop ve masa-sandalye montajı için düzenli gidiyoruz. Bölgedeki fabrikaların ofis ve yemekhane mobilyalarının kurulumunu da yapıyoruz.</p>',
                        ['Velimeşe', 'Ulaş', 'Marmaracık', 'Sağlık', 'Misinli', 'Yeşiltepe', 'Karamehmet', 'Vakıflar', 'Ahimehmet', 'Pınarbaşı', 'İğneler', 'Bakırca'],
                        ['question' => 'Ergene\'deki fabrikalara ofis mobilyası montajı yapıyor musunuz?', 'answer' => 'Evet. Velimeşe ve Ulaş\'taki sanayi tesislerinin idari bina, ofis ve yemekhane mobilyalarını hafta sonu dahil kuruyoruz.'],
                    ],
                    ['Marmaraereğlisi', 'Marmaraereğlisi merkez ve Sultanköy, Yeniçiftlik yazlık sitelerinde mobilya montajı.',
                        '<p>Marmaraereğlisi, Marmara sahilinde yazlık siteleriyle bilinen bir ilçe. Sultanköy, Yeniçiftlik ve Çeşmeli\'deki sitelerde özellikle mayıs-haziran aylarında oturma grubu, yatak odası ve balkon mobilyası montajı talebi artıyor; kış aylarında ise merkez mahallelerdeki kalıcı konutlara hizmet veriyoruz.</p><p>Çorlu\'ya yakınlığı sayesinde Marmaraereğlisi\'ne 1-2 iş günü içinde randevu verebiliyoruz. Siteye giriş bilgilerini randevu öncesinde alarak ekibimizin zaman kaybetmeden montaja başlamasını sağlıyoruz.</p>',
                        ['Cedit', 'Bahçelievler', 'Dereağzı', 'Sultanköy', 'Yeniçiftlik', 'Çeşmeli', 'Mustafa Kemal Paşa', 'Türkmenli'],
                        ['question' => 'Yazlığa kış aylarında da montaj için geliyor musunuz?', 'answer' => 'Evet. Yaz sezonundan önce yazlıkları hazırlamak isteyenler için kış ve bahar aylarında da Marmaraereğlisi\'ne montaj randevusu veriyoruz.'],
                    ],
                    ['Şarköy', 'Şarköy, Mürefte ve Hoşköy\'deki evler ve yazlıklar için mobilya montaj hizmeti.',
                        '<p>Şarköy, bağcılığı ve sahil yazlıklarıyla tanınan, Tekirdağ merkeze yaklaşık 85 km uzaklıkta bir ilçe. Mürefte, Hoşköy ve Kızılcaterzi\'deki yazlık siteler ile ilçe merkezindeki kalıcı konutlara mobilya montajı için geliyoruz.</p><p>Mesafe nedeniyle Şarköy taleplerini aynı güne toplayarak planlıyor, böylece hem randevu tarihini hem de fiyatı uygun tutuyoruz. Yaz sezonu öncesinde yazlık siteler için toplu montaj taleplerinde paket fiyat veriyoruz.</p>',
                        ['Cumhuriyet', 'İstiklal', 'Mürefte', 'Hoşköy', 'Kızılcaterzi', 'Eriklice', 'Gaziköy', 'Uçmakdere', 'Yeniköy', 'Sofuköy', 'İğdebağları'],
                        ['question' => 'Şarköy için randevu ne kadar sürede oluşur?', 'answer' => 'Şarköy taleplerini bölgesel olarak birleştirdiğimiz için genellikle 2-4 iş günü içinde randevu veriyoruz; birden fazla ürün olduğunda daha erken tarih planlayabiliyoruz.'],
                    ],
                    ['Malkara', 'Malkara merkez mahalleleri ve köylerinde gardırop, yatak odası ve ofis mobilyası montajı.',
                        '<p>Malkara, Tekirdağ\'ın batısında tarım ve hayvancılıkla öne çıkan, Edirne-Keşan yoluna yakın bir ilçe. İlçe merkezindeki Camiatik, Gazibey, Hacıevhat ve Yenimahalle\'deki konutlara ve çevre köylere mobilya montajı için düzenli gidiyoruz.</p><p>Malkara\'da özellikle yatak odası takımı, gardırop ve mutfak masası montajı talep ediliyor. Hayrabolu ve Keşan yönündeki işlerle birleştirerek Malkara için uygun fiyatlı ve hızlı randevu sunuyoruz.</p>',
                        ['Camiatik', 'Gazibey', 'Hacıevhat', 'Yenimahalle', 'Cumhuriyet', 'Kozyörük', 'Şahin', 'Ballı', 'Kalaycı', 'Sağlamtaş', 'Yenidibek'],
                        ['question' => 'Malkara köylerine de montaj için geliyor musunuz?', 'answer' => 'Evet. Malkara merkez dışındaki köy ve beldelere de geliyoruz; adres bilgisini WhatsApp\'tan konum olarak paylaşmanız yeterli.'],
                    ],
                    ['Hayrabolu', 'Hayrabolu merkez ve çevre köylerde mobilya montaj hizmeti.',
                        '<p>Hayrabolu, Tekirdağ\'ın kuzeybatısında ayçiçeği tarımıyla bilinen bir ilçe. Cumhuriyet, Hisar ve İlyas mahallelerindeki konutlar ile Çerkezmüsellim, Lahna ve Dambaslar gibi köylere mobilya montajı için gidiyoruz.</p><p>Hayrabolu\'da gardırop, baza ve TV ünitesi montajı ile taşınma sonrası mobilya kurulumu en sık aldığımız talepler. Muratlı ve Malkara hattındaki işlerle birlikte planlayarak 1-3 iş günü içinde randevu veriyoruz.</p>',
                        ['Cumhuriyet', 'Hisar', 'İlyas', 'Çerkezmüsellim', 'Lahna', 'Susuzmüsellim', 'Dambaslar'],
                        ['question' => 'Hayrabolu\'da taşınma sonrası mobilya kurulumu yapıyor musunuz?', 'answer' => 'Evet. Nakliyeyle gelen sökülmüş mobilyaların yeni evinizde yeniden kurulumunu Hayrabolu merkez ve köylerinde yapıyoruz.'],
                    ],
                    ['Muratlı', 'Çorlu\'ya komşu Muratlı\'da hızlı randevu ile mobilya montajı.',
                        '<p>Muratlı, Çorlu\'ya yaklaşık 20 km uzaklıkta, demiryolu ve sanayi tesisleriyle gelişen bir ilçe. Çorlu\'ya yakınlığı sayesinde Muratlı\'daki taleplere çoğunlukla aynı gün veya ertesi gün ulaşıyoruz.</p><p>Muradiye, Turan ve Yeni mahallelerindeki konutlar ile İnanlı, Ballıhoca ve Sevindikli köylerine gardırop, yatak-baza, masa-sandalye ve IKEA mobilyası montajı için geliyoruz.</p>',
                        ['Muradiye', 'Turan', 'Yeni', 'İnanlı', 'Ballıhoca', 'Aşağısevindikli', 'Yukarısevindikli', 'Çevrimkaya'],
                        ['question' => 'Muratlı için Çorlu ile aynı fiyat mı geçerli?', 'answer' => 'Muratlı merkeze Çorlu\'ya çok yakın olduğu için çoğu üründe aynı fiyatı uyguluyoruz; köyler için mesafeyi işe başlamadan önce net olarak bildiririz.'],
                    ],
                    ['Saray', 'Tekirdağ Saray, Büyükyoncalı ve Küçükyoncalı\'da mobilya montaj hizmeti.',
                        '<p>Saray, Tekirdağ\'ın kuzeyinde Istranca Dağları eteklerinde yer alan, Çerkezköy ve Kapaklı\'ya komşu bir ilçe. Ayaspaşa, Kemalpaşa ve Yeni mahallelerindeki konutlara ve Büyükyoncalı, Küçükyoncalı, Beyazköy gibi beldelere mobilya montajı için gidiyoruz.</p><p>Çerkezköy-Kapaklı hattındaki işlerle birleştirerek Saray için 1-2 iş günü içinde randevu planlıyoruz. Yatak odası, gardırop ve mutfak masası montajı Saray\'da en sık talep edilen hizmetler.</p>',
                        ['Ayaspaşa', 'Kemalpaşa', 'Yeni', 'Büyükyoncalı', 'Küçükyoncalı', 'Beyazköy', 'Sofular', 'Edirköy'],
                        ['question' => 'Saray\'ın köylerine de geliyor musunuz?', 'answer' => 'Evet. Saray merkez ve tüm beldelerine, köylerine montaj için geliyoruz. Uzak köyler için mesafeyi işe başlamadan önce net olarak bildiririz.'],
                    ],
                ],
            ],
            [
                'name' => 'Edirne',
                'description' => 'Edirne merkez, Keşan, Uzunköprü, İpsala ve tüm ilçelerde profesyonel mobilya montajı.',
                'meta_description' => 'Edirne mobilya montaj hizmeti: Edirne merkez, Keşan, Uzunköprü, İpsala, Havsa, Enez, Meriç, Lalapaşa ve Süloğlu\'nda mobilya kurulumu. WhatsApp\'tan teklif alın.',
                'content' => '<p>Edirne, Selimiye Camii ve tarihi dokusuyla tanınan Trakya\'nın sınır ili. Trakya Üniversitesi nedeniyle her yıl binlerce öğrenci Edirne merkezde ev kuruyor; Keşan ve Enez\'deki Saros Körfezi yazlıkları yaz aylarında yoğun bir mobilya montaj talebi yaratıyor.</p><p>Edirne merkez, Keşan, Uzunköprü, İpsala, Havsa, Enez, Meriç, Lalapaşa ve Süloğlu olmak üzere ilin tüm ilçelerine geliyoruz. Edirne merkez ve Havsa\'ya haftanın belirli günlerinde düzenli olarak çıktığımız için bu ilçelerde hızlı randevu verebiliyoruz.</p>',
                'faqs' => [
                    ['question' => 'Edirne\'de öğrenci evleri için montaj yapıyor musunuz?', 'answer' => 'Evet. Trakya Üniversitesi öğrencileri için baza, çalışma masası, kitaplık ve gardırop montajını uygun fiyatla yapıyoruz; aynı apartmandaki talepleri birleştirerek daha uygun fiyat sunuyoruz.'],
                    ['question' => 'Edirne için randevu ne kadar sürede oluşur?', 'answer' => 'Edirne merkez ve Havsa\'ya haftada birkaç gün düzenli çıkıyoruz; genellikle 1-3 iş günü içinde randevu veriyoruz. Keşan ve güney ilçeleri için talepleri aynı güne planlıyoruz.'],
                ],
                'districts' => [
                    ['Edirne Merkez', 'Edirne merkez mahallelerinde öğrenci evleri, yeni konutlar ve ofisler için mobilya montajı.',
                        '<p>Edirne merkez; Kaleiçi\'nin tarihi sokaklarından Yıldırım, Şükrüpaşa ve Fatih mahallelerindeki modern sitelere kadar geniş bir konut çeşitliliğine sahip. Trakya Üniversitesi kampüsünün çevresindeki Karaağaç, Yeni İmaret ve Kirişhane bölgelerinde her eylül binlerce öğrenci evi kuruluyor.</p><p>Öğrenci evleri için baza, çalışma masası ve kitaplık; yeni sitelerde yatak odası takımı ve gardırop; şehir merkezindeki ofis ve dükkanlar için ofis mobilyası montajı Edirne merkezde en sık aldığımız talepler.</p>',
                        ['Çavuşbey', 'Dilaverbey', 'Sabuni', 'Yıldırım Beyazıt', 'Yıldırım Hacı Sarraf', 'Abdurrahman', 'Fatih', 'Şükrüpaşa', 'Yeni İmaret', 'Kirişhane', 'Barutluk', 'Karaağaç', 'Sarayiçi', 'İstasyon', 'Mithatpaşa', 'Umurbey', 'Talatpaşa', 'Koca Sinan', 'Meydan', '1. Murat', 'Nişancıpaşa', 'Menzilahır', 'Medrese Alibey'],
                        ['question' => 'Edirne merkezde öğrenci evine tek parça baza montajı için geliyor musunuz?', 'answer' => 'Evet. Tek parça montajlar için de geliyoruz; aynı gün Edirne\'deki diğer işlerle birleştirerek uygun fiyat veriyoruz.'],
                    ],
                    ['Keşan', 'Güney Trakya\'nın merkezi Keşan\'da ve Saros Körfezi yazlıklarında mobilya montajı.',
                        '<p>Keşan, Edirne\'nin en kalabalık ilçesi ve Güney Trakya\'nın ticaret merkezi. İstanbul-Çanakkale yolu üzerindeki konumu ve Saros Körfezi\'ndeki Erikli, Yayla ve Mecidiye yazlık bölgeleri nedeniyle hem kalıcı konut hem de yazlık montaj talebi yüksek.</p><p>İspat Cami, Büyük Cami, Yukarı ve Aşağı Zaferiye mahallelerindeki konutlara; yaz sezonu öncesinde Erikli ve Mecidiye\'deki yazlık sitelere mobilya montajı için gidiyoruz. Malkara ve İpsala hattındaki işlerle birlikte planladığımız için Keşan\'a 1-3 iş günü içinde randevu veriyoruz.</p>',
                        ['İspat Cami', 'Büyük Cami', 'Yukarı Zaferiye', 'Aşağı Zaferiye', 'İstasyon', 'Yeni Mescit', 'Mustafa Kemal Paşa', 'Paşayiğit', 'Beğendik', 'Mecidiye', 'Yenimuhacir', 'Erikli'],
                        ['question' => 'Erikli ve Saros yazlıklarına montaj için geliyor musunuz?', 'answer' => 'Evet. Erikli, Yayla, Mecidiye ve Danişment\'teki yazlık sitelere sezon öncesi ve sezon içinde mobilya montajı için geliyoruz.'],
                    ],
                    ['Uzunköprü', 'Tarihi köprüsüyle ünlü Uzunköprü\'de konut ve iş yerleri için mobilya montaj hizmeti.',
                        '<p>Uzunköprü, Ergene Nehri üzerindeki tarihi taş köprüsü ve tarım ticaretiyle bilinen, Edirne\'nin güneyindeki büyük ilçelerden biri. Muradiye, Rızaefendi, Halise Hatun ve Cumhuriyet mahallelerindeki konutlar ile Kırcasalih ve Çöpköy beldelerine mobilya montajı için geliyoruz.</p><p>Uzunköprü\'de yatak odası takımı, gardırop, mutfak masası ve TV ünitesi montajı en çok talep edilen hizmetler. Keşan ve Edirne merkez yönündeki işlerle birleştirerek Uzunköprü için uygun fiyatlı randevu planlıyoruz.</p>',
                        ['Muradiye', 'Rızaefendi', 'Halise Hatun', 'Mescit', 'Cumhuriyet', 'Kırcasalih', 'Çöpköy'],
                        ['question' => 'Uzunköprü\'de mağazadan aldığım mobilyanın montajını yapıyor musunuz?', 'answer' => 'Evet. Hangi mağazadan alındığı fark etmeksizin paketli mobilyaların kurulumunu Uzunköprü merkez ve köylerinde yapıyoruz.'],
                    ],
                    ['İpsala', 'İpsala merkez ve köylerinde gardırop, yatak-baza ve masa-sandalye montajı.',
                        '<p>İpsala, Yunanistan sınır kapısı ve pirinç tarımıyla tanınan, Keşan\'a komşu bir ilçe. Fatih ve Cumhuriyet mahallelerindeki konutlar ile Esetçe, Yenikarpuzlu ve İbriktepe gibi köylere mobilya montajı için gidiyoruz.</p><p>İpsala taleplerini Keşan ile aynı güne planlayarak hem hızlı randevu hem de uygun fiyat sunuyoruz. Yatak odası, gardırop ve yemek masası montajı İpsala\'da en sık aldığımız işler.</p>',
                        ['Fatih', 'Cumhuriyet', 'Esetçe', 'Yenikarpuzlu', 'İbriktepe', 'Sarıcaali', 'Turpçular', 'Hıdırköy'],
                        ['question' => 'İpsala için Keşan\'la aynı gün randevu alınabilir mi?', 'answer' => 'Evet. İpsala ve Keşan taleplerini aynı güne planladığımız için Keşan\'a çıktığımız günlerde İpsala\'ya da geliyoruz.'],
                    ],
                    ['Havsa', 'Edirne-İstanbul yolu üzerindeki Havsa\'da hızlı mobilya montaj hizmeti.',
                        '<p>Havsa, Edirne merkeze yaklaşık 25 km uzaklıkta, D-100 karayolu üzerinde yer alan bir ilçe. Edirne merkeze düzenli çıktığımız günlerde Havsa\'ya da uğradığımız için ilçeye hızlı randevu verebiliyoruz.</p><p>Hacı İsa, Hacı Gazi ve Yeni mahallelerindeki konutlar ile Hasköy, Osmanlı ve Necatiye köylerine gardırop, baza, TV ünitesi ve masa-sandalye montajı için geliyoruz.</p>',
                        ['Hacı İsa', 'Hacı Gazi', 'Yeni', 'Hasköy', 'Osmanlı', 'Necatiye', 'Abalar', 'Söğütlüdere', 'Yolageldi'],
                        ['question' => 'Havsa\'ya Edirne merkezle aynı gün geliyor musunuz?', 'answer' => 'Evet. Havsa D-100 üzerinde olduğu için Edirne merkez randevularıyla aynı gün Havsa\'ya da geliyoruz.'],
                    ],
                    ['Enez', 'Enez merkez ve Saros sahili yazlıklarında mobilya montajı.',
                        '<p>Enez, Meriç Deltası ve Saros Körfezi sahiliyle bilinen, Edirne\'nin en güneyindeki ilçe. Gazi Ömer Bey ve İstiklal mahallelerindeki konutlara ve Sultaniçe, Gülçavuş ile Yenice bölgelerindeki yazlık sitelere mobilya montajı için gidiyoruz.</p><p>Enez taleplerini Keşan ve İpsala ile aynı güne planlıyor, yaz sezonu öncesinde yazlık siteler için toplu montajda paket fiyat veriyoruz.</p>',
                        ['Gazi Ömer Bey', 'İstiklal', 'Yeni', 'Yenice', 'Sultaniçe', 'Gülçavuş', 'Vakıf', 'Karaincirli', 'Çavuşköy', 'Hisarlı', 'Büyükevren', 'Küçükevren', 'Umurbey'],
                        ['question' => 'Enez\'deki yazlığıma sezon öncesi montaj için gelir misiniz?', 'answer' => 'Evet. Nisan-haziran döneminde Enez ve Sultaniçe yazlıkları için düzenli montaj planlıyoruz; birden fazla ürün olduğunda paket fiyat uyguluyoruz.'],
                    ],
                    ['Meriç', 'Meriç merkez, Küplü ve Subaşı\'nda mobilya montaj hizmeti.',
                        '<p>Meriç, Yunanistan sınırındaki Meriç Nehri kıyısında tarımla geçinen, Uzunköprü\'ye komşu küçük bir ilçe. İlçe merkezi ile Küplü, Subaşı ve Yenicegörüce beldelerine ve köylerine mobilya montajı için geliyoruz.</p><p>Meriç taleplerini Uzunköprü ve İpsala hattındaki işlerle birleştirerek planlıyoruz. Yatak odası, gardırop ve mutfak masası montajı ilçede en sık talep edilen hizmetler.</p>',
                        ['Merkez', 'Küplü', 'Subaşı', 'Yenicegörüce', 'Adasarhanlı', 'Akçadam', 'Alibey', 'Büyükaltıağaç', 'Kadıdondurma', 'Olacak', 'Saatağacı', 'Serem', 'Umurca', 'Yakupbey'],
                        ['question' => 'Meriç\'in köylerine mobilya montajı için geliyor musunuz?', 'answer' => 'Evet. Meriç merkez, beldeleri ve köylerine geliyoruz; adresi WhatsApp\'tan konum olarak paylaşmanız yeterli.'],
                    ],
                    ['Lalapaşa', 'Lalapaşa merkez ve köylerinde mobilya montaj hizmeti.',
                        '<p>Lalapaşa, Bulgaristan sınırında, Edirne merkeze yaklaşık 25 km uzaklıkta kırsal karakterli bir ilçe. Merkez mahalle ile Büyünlü, Hacıdanişment, Sinanköy ve diğer köylere mobilya montajı için gidiyoruz.</p><p>Edirne merkez randevularıyla aynı güne planlayarak Lalapaşa\'ya uygun fiyatlı hizmet veriyoruz. Yatak odası, gardırop, baza ve mutfak masası montajı ilçede en çok talep edilen işler.</p>',
                        ['Merkez', 'Büyünlü', 'Çömlekpınar', 'Hacıdanişment', 'Sinanköy', 'Çallıdere', 'Ömeroba', 'Vaysal', 'Hamzabeyli', 'Doğanköy', 'Kalkansöğüt', 'Hanlıyenice', 'Süleymandanişment', 'Taşlımüsellim', 'Uzunbayır', 'Yünlüce'],
                        ['question' => 'Lalapaşa için Edirne merkezle aynı gün randevu mümkün mü?', 'answer' => 'Evet. Lalapaşa\'ya Edirne merkez randevularımızla aynı gün geliyoruz; talebinizi 1-2 gün önceden iletmeniz yeterli.'],
                    ],
                    ['Süloğlu', 'Süloğlu merkez ve köylerinde gardırop, yatak ve masa montajı.',
                        '<p>Süloğlu, Edirne merkeze yaklaşık 30 km uzaklıkta, barajı ve tarım arazileriyle bilinen küçük bir ilçe. İlçe merkezi ile Akardere, Büyükgerdelli, Tatarlar ve diğer köylere mobilya montajı için geliyoruz.</p><p>Süloğlu taleplerini Edirne merkez ve Lalapaşa ile aynı güne planlıyoruz. Yatak odası takımı, gardırop ve mutfak masası montajı ilçede en sık aldığımız işler.</p>',
                        ['Merkez', 'Akardere', 'Büyükgerdelli', 'Küküler', 'Domurcalı', 'Geçkinli', 'Keramettin', 'Sülecik', 'Tatarlar', 'Taşlısekban', 'Yağcılı', 'Habiller'],
                        ['question' => 'Süloğlu\'na tek ürün montajı için de geliyor musunuz?', 'answer' => 'Evet. Tek ürün için de geliyoruz; Edirne merkez ve Lalapaşa\'daki işlerle aynı güne planlayarak uygun fiyat veriyoruz.'],
                    ],
                ],
            ],
            [
                'name' => 'Kırklareli',
                'description' => 'Kırklareli merkez, Lüleburgaz, Babaeski, Vize ve tüm ilçelerde mobilya montaj hizmeti.',
                'meta_description' => 'Kırklareli mobilya montaj hizmeti: Lüleburgaz, Babaeski, Vize, Pınarhisar, Demirköy, Kofçaz ve Pehlivanköy\'de gardırop, yatak, TV ünitesi ve IKEA montajı.',
                'content' => '<p>Kırklareli, Istranca Dağları ile Ergene Ovası arasında yer alan, Lüleburgaz\'daki sanayi ve konut projeleriyle hızla gelişen bir Trakya ili. Kırklareli Üniversitesi\'nin il merkezindeki öğrenci nüfusu, D-100 üzerindeki Lüleburgaz ve Babaeski\'nin yeni siteleri ve Karadeniz sahilindeki Kıyıköy ile İğneada\'nın pansiyon ve yazlıkları farklı montaj ihtiyaçları doğuruyor.</p><p>Kırklareli merkez, Lüleburgaz, Babaeski, Vize, Pınarhisar, Demirköy, Kofçaz ve Pehlivanköy olmak üzere ilin 8 ilçesinin tamamına geliyoruz. Lüleburgaz ve Babaeski, Çorlu\'ya yakınlığı sayesinde en hızlı randevu verdiğimiz ilçeler arasında.</p>',
                'faqs' => [
                    ['question' => 'Kırklareli\'nin hangi ilçelerine geliyorsunuz?', 'answer' => 'Kırklareli merkez, Lüleburgaz, Babaeski, Vize, Pınarhisar, Demirköy, Kofçaz ve Pehlivanköy olmak üzere tüm ilçelere geliyoruz.'],
                    ['question' => 'Lüleburgaz\'a ne kadar sürede randevu veriyorsunuz?', 'answer' => 'Lüleburgaz, ekibimizin merkezi Çorlu\'ya yakın olduğu için çoğunlukla aynı gün veya ertesi gün randevu verebiliyoruz.'],
                ],
                'districts' => [
                    ['Kırklareli Merkez', 'Kırklareli merkez mahalleleri ile Kavaklı, Üsküp ve İnece\'de mobilya montajı.',
                        '<p>Kırklareli merkez, il yönetiminin ve Kırklareli Üniversitesi\'nin bulunduğu, Istranca eteklerindeki sakin bir şehir. Karakaş, Karacaibrahim, Cumhuriyet ve Demirtaş mahallelerindeki konutlar, üniversite çevresindeki öğrenci evleri ile Kavaklı, Üsküp ve İnece beldelerine mobilya montajı için geliyoruz.</p><p>Eylül-ekim aylarında öğrenci evleri için baza ve çalışma masası, yıl boyunca yeni sitelerde yatak odası ve gardırop montajı en sık talep ettiğimiz işler. Lüleburgaz hattındaki işlerle birleştirerek 1-2 iş günü içinde randevu veriyoruz.</p>',
                        ['Karakaş', 'Karacaibrahim', 'Cumhuriyet', 'Hacızekeriya', 'Doğu', 'Yayla', 'Demirtaş', 'Bademlik', 'Karahıdır', 'Kavaklı', 'Üsküp', 'İnece', 'Kızılcıkdere', 'Kaynarca'],
                        ['question' => 'Kırklareli Üniversitesi çevresindeki öğrenci evlerine montaj yapıyor musunuz?', 'answer' => 'Evet. Öğrenci evleri için baza, çalışma masası, kitaplık ve gardırop montajını uygun fiyatla yapıyor, aynı bölgedeki talepleri birleştiriyoruz.'],
                    ],
                    ['Lüleburgaz', 'Kırklareli\'nin en kalabalık ilçesi Lüleburgaz\'da hızlı ve profesyonel mobilya montajı.',
                        '<p>Lüleburgaz, Kırklareli\'nin en kalabalık ilçesi ve D-100 üzerindeki konumuyla Trakya\'nın önemli sanayi ve ticaret merkezlerinden biri. Kocasinan, Durak, Yıldırım ve Siteler mahallelerindeki yeni konut projeleri ile Hamitabat ve Büyükkarıştıran bölgesindeki tesislere düzenli hizmet veriyoruz.</p><p>Çorlu\'ya yaklaşık 40 dakika uzaklıkta olduğu için Lüleburgaz\'a çoğunlukla aynı gün veya ertesi gün randevu verebiliyoruz. Yatak odası, gardırop, TV ünitesi ve IKEA mobilyası montajı ile ofis kurulumları Lüleburgaz\'da en sık aldığımız işler.</p>',
                        ['Kocasinan', 'Durak', 'Yıldırım', 'Kurtuluş', 'Gençlik', 'Yeni', 'Siteler', '8 Kasım', 'Dere', 'Barış', 'Atatürk', 'İstiklal', 'Ahmetbey', 'Büyükkarıştıran', 'Evrensekiz', 'Kırıkköy', 'Sakızköy', 'Karaağaç', 'Hamitabat', 'Çeşmekolu'],
                        ['question' => 'Lüleburgaz\'da aynı gün montaj mümkün mü?', 'answer' => 'Evet. Lüleburgaz Çorlu\'ya yakın olduğu için sabah saatlerinde gelen taleplerin çoğuna aynı gün içinde randevu verebiliyoruz.'],
                    ],
                    ['Babaeski', 'Babaeski merkez, Alpullu ve Büyükmandıra\'da mobilya montaj hizmeti.',
                        '<p>Babaeski, D-100 karayolu üzerinde Lüleburgaz ile Edirne arasında yer alan, tarım ve tarıma dayalı sanayiyle bilinen bir ilçe. Dindoğru, Hamidiye, Gazi Kemal ve Fatih mahallelerindeki konutlar ile Alpullu, Büyükmandıra, Karahalil ve Sinanlı beldelerine mobilya montajı için geliyoruz.</p><p>Lüleburgaz ve Edirne hattındaki işlerle birleştirerek Babaeski\'ye 1-2 iş günü içinde randevu veriyoruz. Yatak odası takımı, gardırop ve mutfak masası montajı ilçede en çok talep edilen hizmetler.</p>',
                        ['Dindoğru', 'Hamidiye', 'Gazi Kemal', 'Yeni', 'Fatih', 'Karahalil', 'Büyükmandıra', 'Alpullu', 'Sinanlı', 'Ağayeri', 'Çengelli', 'Düğüncübaşı', 'Ertuğrul', 'Kadıköy', 'Karacaoğlan', 'Mutlu', 'Nadırlı', 'Pancarköy', 'Taşağıl'],
                        ['question' => 'Alpullu ve Büyükmandıra\'ya da montaj için geliyor musunuz?', 'answer' => 'Evet. Babaeski\'nin tüm beldeleri ve köylerine geliyoruz; Alpullu ve Büyükmandıra D-100\'e yakın olduğu için Babaeski merkezle aynı gün planlıyoruz.'],
                    ],
                    ['Vize', 'Vize merkez ve Kıyıköy\'de konut, pansiyon ve yazlıklar için mobilya montajı.',
                        '<p>Vize, antik kalıntıları ve Karadeniz sahilindeki Kıyıköy beldesiyle bilinen, Istranca eteklerinde tarihi bir ilçe. Gazi, Namazgah ve Evrenos mahallelerindeki konutlar ile Kıyıköy\'deki pansiyon ve yazlıklara, Çakıllı ve Sergen beldelerine mobilya montajı için gidiyoruz.</p><p>Saray ve Pınarhisar hattındaki işlerle birleştirerek Vize\'ye 1-3 iş günü içinde randevu veriyoruz. Kıyıköy\'deki pansiyonlar için sezon öncesi toplu yatak, baza ve gardırop montajında paket fiyat uyguluyoruz.</p>',
                        ['Gazi', 'Namazgah', 'Evrenos', 'Kıyıköy', 'Çakıllı', 'Sergen', 'Pazarlı', 'Balkaya', 'Soğucak', 'Evrencik', 'Düzova', 'Kışlacık', 'Hasbuğa', 'Çavuşköy', 'Aksicim', 'Akıncılar'],
                        ['question' => 'Kıyıköy\'deki pansiyonumuz için toplu montaj yapıyor musunuz?', 'answer' => 'Evet. Pansiyon ve apartlar için sezon öncesinde yatak, baza, gardırop ve TV ünitesi montajını toplu olarak planlıyor ve paket fiyat veriyoruz.'],
                    ],
                    ['Pınarhisar', 'Pınarhisar merkez, Kaynarca ve Yenice\'de mobilya montaj hizmeti.',
                        '<p>Pınarhisar, Istranca Dağları eteklerinde, Kırklareli merkez ile Vize arasında yer alan bir ilçe. İlçe merkezindeki mahalleler ile Kaynarca ve Yenice beldelerine, Poyralı, Erenler ve Sütlüce gibi köylere mobilya montajı için geliyoruz.</p><p>Kırklareli merkez ve Vize randevularıyla aynı güne planlayarak Pınarhisar\'a uygun fiyatlı hizmet veriyoruz. Yatak odası, gardırop ve TV ünitesi montajı ilçede en sık talep edilen hizmetler.</p>',
                        ['Cumhuriyet', 'Hacı İsmail', 'Yeni', 'Kaynarca', 'Yenice', 'Poyralı', 'Erenler', 'Sütlüce', 'Çayırdere', 'Evciler', 'Tozaklı', 'İslambeyli', 'Akören', 'Cevizköy', 'Kurudere'],
                        ['question' => 'Pınarhisar için Kırklareli merkezle aynı gün randevu alınabilir mi?', 'answer' => 'Evet. Pınarhisar\'a Kırklareli merkez ve Vize randevularımızla aynı gün geliyoruz.'],
                    ],
                    ['Demirköy', 'Demirköy ve İğneada\'da konut, pansiyon ve yazlıklar için mobilya montajı.',
                        '<p>Demirköy, Istranca ormanlarının içinde, Karadeniz sahilindeki İğneada beldesi ve longoz ormanlarıyla tanınan bir ilçe. İlçe merkezindeki konutlar ile İğneada\'daki pansiyon, otel ve yazlıklara, Beğendik ve Sarpdere gibi köylere mobilya montajı için gidiyoruz.</p><p>Demirköy, ekibimizin merkezine en uzak ilçelerden biri olduğu için talepleri aynı güne toplayarak planlıyoruz; İğneada\'daki pansiyonlar için sezon öncesi toplu montajda paket fiyat veriyoruz.</p>',
                        ['Hamdibey', 'Cumhuriyet', 'İğneada', 'Beğendik', 'Sarpdere', 'Sivriler', 'Avcılar', 'Karacadağ', 'Yeşilce', 'Balaban', 'Boztaş', 'Armutveren', 'İncesırt', 'Yiğitbaşı'],
                        ['question' => 'İğneada\'ya montaj için geliyor musunuz?', 'answer' => 'Evet. İğneada\'daki pansiyon, otel ve yazlıklara geliyoruz; mesafe nedeniyle talepleri aynı güne toplayarak 3-5 iş günü içinde randevu veriyoruz.'],
                    ],
                    ['Kofçaz', 'Kofçaz merkez ve köylerinde mobilya montaj hizmeti.',
                        '<p>Kofçaz, Bulgaristan sınırında Istranca ormanlarının içinde yer alan, Kırklareli\'nin en az nüfuslu ilçesi. İlçe merkezi ile Ahlatlı, Beyci, Devletliağaç ve diğer orman köylerine mobilya montajı için geliyoruz.</p><p>Kofçaz taleplerini Kırklareli merkez randevularıyla aynı güne planlıyoruz. Yatak odası, gardırop, baza ve mutfak masası montajı ilçede en sık aldığımız işler.</p>',
                        ['Merkez', 'Ahlatlı', 'Ahmetler', 'Aşağıkanara', 'Beyci', 'Devletliağaç', 'Elmacık', 'Karaabalar', 'Kocayazı', 'Kula', 'Malkoçlar', 'Taştepe', 'Terzidere', 'Topçular', 'Yukarıkanara'],
                        ['question' => 'Kofçaz\'ın orman köylerine de geliyor musunuz?', 'answer' => 'Evet. Kofçaz merkez ve tüm köylerine geliyoruz; adresi WhatsApp\'tan konum olarak paylaşmanız ulaşımı planlamamızı kolaylaştırır.'],
                    ],
                    ['Pehlivanköy', 'Pehlivanköy merkez ve köylerinde gardırop, yatak ve masa montajı.',
                        '<p>Pehlivanköy, Ergene Nehri kıyısında, Babaeski ile Uzunköprü arasında yer alan küçük bir ilçe. İlçe merkezi ile Doğanca, Hıdırca, İmampazarı, Kumköy ve diğer köylere mobilya montajı için geliyoruz.</p><p>Babaeski ve Uzunköprü hattındaki işlerle birleştirerek Pehlivanköy\'e uygun fiyatlı randevu planlıyoruz. Yatak odası takımı, gardırop ve mutfak masası montajı ilçede en çok talep edilen hizmetler.</p>',
                        ['Merkez', 'Doğanca', 'Hıdırca', 'İmampazarı', 'Kumköy', 'Kuştepe', 'Yeşilova'],
                        ['question' => 'Pehlivanköy için Babaeski\'yle aynı gün randevu mümkün mü?', 'answer' => 'Evet. Pehlivanköy taleplerini Babaeski ve Uzunköprü randevularıyla aynı güne planlıyoruz.'],
                    ],
                ],
            ],
        ];
    }
}
