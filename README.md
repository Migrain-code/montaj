# Trakya Mobilya Montaj — Web Sitesi ve Yönetim Paneli

Tekirdağ, Edirne ve Kırklareli'nde mobilya montaj hizmeti veren işletme için hazırlanmış, SEO odaklı kurumsal site.
Ana dönüşüm hedefi **WhatsApp'tan Teklif Al**, ikinci hedef **Hemen Ara**.

- **Altyapı:** Laravel 12, Blade (sunucu taraflı), Bootstrap 5, vanilla JavaScript, Font Awesome, Vite
- **Yönetim paneli:** Filament 5 — `/admin`
- **Veritabanı:** MySQL veya SQLite (`.env` ile seçilir)

## Kurulum

```bash
composer install
npm install
cp .env.example .env        # ardından DB_* ve SITE_* değerlerini düzenleyin
php artisan key:generate
php artisan storage:link
php artisan migrate --seed  # tabloları kurar, örnek içerik ve yönetici kullanıcısını ekler
npm run build
php artisan serve
```

> **Uyarı:** İçerik girildikten sonra `migrate:fresh`, `migrate:refresh` veya `db:wipe` çalıştırmayın; bu komutlar
> veritabanındaki tüm tabloları siler. Yeni migrasyonlar için yalnızca `php artisan migrate` kullanın.
> Seeder'lar tekrar çalıştırılabilir (`php artisan db:seed`); var olan kayıtların üzerine yazmaz.

### Yönetici girişi

| Adres | E-posta | Parola |
| --- | --- | --- |
| `/admin` | `admin@example.com` | `password` |

İlk girişten sonra **Ayarlar → Yöneticiler** bölümünden e-posta ve parolayı mutlaka değiştirin.
Seed öncesinde `.env` dosyasına `ADMIN_EMAIL` ve `ADMIN_PASSWORD` yazarak farklı bilgilerle de oluşturabilirsiniz.

## Telefon ve WhatsApp numarası

Kod içinde sabit numara yoktur. Öncelik sırası:

1. Yönetim paneli → **Ayarlar → Site Ayarları → Genel & İletişim**
2. Panelde boşsa `.env` dosyasındaki `SITE_PHONE`, `SITE_WHATSAPP`, `SITE_EMAIL`, `SITE_NOTIFICATION_EMAIL`

WhatsApp hazır mesajı da panelden düzenlenir; `{bolge}` ve `{hizmet}` yer tutucuları bulunulan sayfaya göre doldurulur
(ör. Çorlu sayfasındaki butonlar "Bulunduğum bölge: Çorlu, Tekirdağ" mesajıyla açılır).

## Yönetim panelinde neler yönetilir?

| Bölüm | İçerik |
| --- | --- |
| Teklif Talepleri | Formdan gelen talepler, fotoğraflar, durum takibi, notlar, WhatsApp/arama kısayolları |
| Hizmetler | Hizmet sayfaları: açıklama, görsel, "Neler yapıyoruz", "Kimler için uygun", süreç, SSS, SEO |
| İller / İlçeler | Lokasyon SEO sayfaları: bölgeye özel içerik, mahalleler, bölgeye özel SSS, SEO |
| Galeri / Kategoriler | Montaj fotoğrafları (WebP önerilir), ALT metni, kategori filtresi |
| Müşteri Yorumları | Yalnızca gerçek yorumlar eklenmelidir; yorum yoksa bölüm sitede görünmez |
| Sık Sorulan Sorular | Genel SSS (ana sayfa ve `/sss`) |
| Ana Sayfa Maddeleri | Güven unsurları, "Neden biz?" ve "Nasıl çalışıyoruz?" adımları |
| Sayfalar | KVKK metni gibi statik sayfalar |
| Site Ayarları | İletişim, hero, hakkımızda, sayaçlar, CTA, SEO, Analytics, sosyal medya, harita |

## Adres yapısı

| Adres | Açıklama |
| --- | --- |
| `/mobilya-montaji`, `/gardirop-montaji` … | Hizmet detay sayfaları (slug panelden değiştirilebilir) |
| `/tekirdag`, `/edirne`, `/kirklareli` | İl sayfaları |
| `/tekirdag/corlu`, `/edirne/kesan` … | İlçe sayfaları |
| `/hizmetler`, `/bolgeler`, `/galeri`, `/hakkimizda`, `/sss`, `/iletisim`, `/teklif-al` | Sabit sayfalar |
| `/sitemap.xml`, `/robots.txt` | Otomatik üretilir (site haritası 1 saat önbelleklenir) |

Hizmet, il ve sayfa slug'ları kök dizinde yayınlandığı için panel, birbirleriyle veya sabit adreslerle çakışan slug'ları reddeder.

## Teklif formu

- Zorunlu alanlar: ad soyad, telefon, KVKK onayı ve **en az bir fotoğraf**. İl/ilçe, hizmet, açıklama
  ve tarih isteğe bağlıdır.
- **Fotoğraf neden zorunlu:** montaj fiyatı ürünün parça sayısına, kapak/çekmece adedine ve kurulacak
  alana göre belirlenir. Fotoğrafsız gelen talepte fiyat verilemez; karşılıklı mesajlaşmayla zaman
  kaybedilir. Fotoğraf çekemeyen ziyaretçi için WhatsApp yolu formun hemen yanında açık durur.
- Fotoğraf: 1–5 adet, her biri en fazla 5 MB; JPG, JPEG, PNG, WEBP. Yüklenenler WebP'ye çevrilir.
- Fotoğraflar herkese açık olmayan diskte (`storage/app/private/quote-photos`) saklanır, yalnızca panelde görüntülenir.
- Spam koruması: gizli alan (honeypot) ve IP başına dakikada 5 gönderim sınırı.
- Panelde **Talep bildirimi e-postası** doluysa ve `.env` içindeki `MAIL_*` ayarları yapılmışsa yeni talepler e-posta ile bildirilir.

## Görseller

Örnek görseller Unsplash lisanslı stok fotoğraflardır ve `database/seeders/images` altından `storage/app/public` içine kopyalanır.
Gerçek montaj fotoğraflarınızı panelden yükleyerek değiştirin. Tüm görsellerde `loading="lazy"` ve ALT metni kullanılır.

## Geliştirme

```bash
npm run dev        # Vite geliştirme sunucusu
php artisan test   # otomatik testler
```

Testler yalnızca bellek içi SQLite üzerinde çalışır. `tests/TestCase.php` içindeki koruma, bağlantı farklıysa testleri
veritabanına dokunmadan durdurur; bu sayede `.env` içindeki gerçek veritabanı testlerden etkilenmez.

## Yayına alma

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize && php artisan filament:optimize
```

`.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://alanadiniz.com`. Yayına aldıktan sonra Google Search Console'a
`https://alanadiniz.com/sitemap.xml` adresini ekleyin ve doğrulama kodunu **Site Ayarları → SEO & Analitik** bölümüne girin.

---

# SEO & AI Yönetim Sistemi

`seo_sistemi_promptu.md` dosyasındaki 9 modülün tamamı kurulmuştur. AI yalnız içerik üretimi,
meta üretimi ve SSS üretiminde kullanılır. **Skorlama, çakışma tespiti, yönlendirme önerisi ve iç
link önerisi deterministiktir** — ücretsiz, tekrarlanabilir ve test edilebilir.

## Modüller

| # | Modül | Nerede yönetilir | AI kullanır mı |
| --- | --- | --- | --- |
| 1 | Kelime ve hedef sahipliği | SEO & AI → Anahtar Kelimeler / Hedef Sayfalar | Hayır |
| 2 | İçerik SEO skoru | SEO & AI → SEO Gösterge Paneli | Hayır |
| 3 | AI içerik üretim hattı | Blog → Blog Yazıları → "AI ile konu üret" | Evet |
| 4 | Çakışma koruması (üretim anı) | Otomatik, üretimle birlikte çalışır | Hayır |
| 5 | Çakışma temizleyici (geçmiş) | SEO & AI → Çakışan İçerikler | Hayır |
| 6 | İç link motoru | SEO & AI → İç Link Önerileri / Kuralları | Hayır |
| 7 | Search Console katmanı | SEO & AI → Ayarlar → Search Console | Hayır |
| 8 | Yönlendirme ve 404 | Gözlem → 404 Kayıtları / Yönlendirmeler | Hayır |
| 9 | AI ajan keşfi | `/llms.txt`, `/robots.txt`, Link başlıkları | Hayır |

## Kurulum

### 1. OpenRouter anahtarı

```dotenv
AI_API_KEY=sk-or-v1-...
AI_BASE_URL=https://openrouter.ai/api/v1
AI_MODEL=openai/gpt-4o-mini
```

Anahtarı https://openrouter.ai/keys adresinden alın. Model listesi panelde
**SEO & AI → Ayarlar → AI sağlayıcı** sekmesinde OpenRouter'dan canlı çekilir; oradan seçebilirsiniz.

Anahtar girilmeden sistem çalışmaya devam eder: AI aksiyonları panelde **gizlenir**, hata vermez.

### 2. Zamanlanmış görevler

Sunucuda tek bir cron satırı yeterlidir:

```
* * * * * cd /proje/yolu && php artisan schedule:run >> /dev/null 2>&1
```

Yazı üretimi kuyrukta koştuğu için bir kuyruk işçisi de gerekir:

```
php artisan queue:work --tries=2 --timeout=600
```

### 3. Google Search Console (isteğe bağlı)

1. Google Cloud'da bir servis hesabı oluşturup JSON anahtarını indirin.
2. Dosyayı `storage/app/private/` altına koyun.
3. Panelde **SEO & AI → Ayarlar → Search Console** bölümüne dosya adını ve mülk adresini girin.
4. Search Console'da servis hesabının e-postasını mülke **kullanıcı** olarak ekleyin.

> **403 "You do not own this site" alırsanız** üç sebebi olabilir: servis hesabı mülke eklenmemiştir;
> mülk tipi uyuşmuyordur (`sc-domain:ornek.com` ile `https://ornek.com/` farklı mülklerdir ve
> ayardaki değer GSC'dekiyle **birebir** aynı olmalıdır); ya da incelenen adres mülkün dışındadır.
> URL Inspection için **"Tam"** yetki yeterlidir. Indexing API için **"Sahip"** gerekir ve sahiplik
> ayrı bir ekrandan verilir; kullanıcı ekleme penceresinde "Sahip" seçeneği çıkmaz.

## Zamanlama

Sıra önemlidir: önce veri çekilir, sonra ondan türetilen işler koşar.

| Saat | Görev | Komut |
| --- | --- | --- |
| 01:00 | Site haritası önbelleği | `sitemap:generate` |
| 01:10 | llms.txt üretimi | `seo:discovery` |
| 01:50 | Hedef sayfa senkronu | `seo:sync-targets` |
| 02:00 | SEO skorlama | `seo:score` |
| 03:00 | Google indeks kontrolü | `seo:check-index` |
| 04:00 | Blog konusu üretimi | `blog:generate` |
| 05:00 | Search Console performansı | `seo:sync-search-console` |
| 05:10 | Kelime sıralama geçmişi | `seo:sync-rankings` |
| 05:20 (pazartesi) | Yeni kelime keşfi | `seo:discover-keywords` |
| 05:30 (pazartesi) | Düşük CTR meta tazeleme | `seo:refresh-meta` |
| 05:40 | Yönlendirme önerisi | `seo:suggest-redirects` |
| 05:50 | İç linkleri işle | `links:apply` |
| her dakika | Zamanı gelen yazıları yayınla | `blog:publish-due` |

Yayın kararı dakika hassasiyetinde olduğu için o görev her dakika koşar; 5 dakikada bir koşturmak
kararı 5 dakika geciktirirdi.

## Blog üretim hattı nasıl çalışır?

```
günlük komut → kategori seç → AI konu üret → FİLTRE 1 → FİLTRE 2 → kuyruğa al
                                                                      ↓
                              yayın zamanı gelince yayınla ← taslak ← AI yazı üret
```

**Filtre 1 — başlık benzerliği.** Tam eşleşme yeterli değildir: tek ek veya tek kelime farkı
tekrar yazıyı kaçırır. Başlıklar Türkçeye özgü normalleştirmeden geçirilir (büyük İ/I elle eşlenir),
durak kelimeler atılır, her kelime ilk 6 harfe indirilir ve Jaccard benzerliği hesaplanır.
Eşik varsayılan **0.55**'tir.

*Bilinen sınır:* kökü 6 harften kısa kelimelerde ek katlanmaz ("rayı" ile "raylar" ayrı sayılır).
Uzun köklerde algoritma doğru çalışır. Kısa köklü tekrarlar **Çakışan İçerikler** ekranında elle görülür.

**Filtre 2 — kelime çakışması.** Adayın ana anahtar kelimesini zaten hedefleyen içerik varsa konu
reddedilir. Bu kontrol uyarı üretip geçmez; **engeller**.

Reddedilen her aday gerekçesiyle kaydedilir. Hiçbir konu üretilemezse sistem sessizce durmaz;
boştaki kelime sayısını, açık kategori sayısını ve nereye bakılacağını söyler.

## Çakışan içerikleri temizleme

İki ayrı eşik kullanılır ve bu **bilerek** böyledir:

- **Tarama eşiği (0.55):** "çakışma olabilir" → ekranda listelenir, buton çıkmaz
- **Birleştirme eşiği (0.90):** "pratikte aynı yazı" → tek tuşla birleştirilebilir

Aradaki çiftler elle bırakılır. %67 benzeyen iki yazı farklı konular olabilir; otomatik birleştirmek
içerik silmek demektir.

Birleştirme yapıldığında:

1. Kaybeden yazıdan kazanana **301** yönlendirme oluşturulur
2. Kaybeden **yayından kaldırılır** — asla silinmez
3. Her ikisi tek transaction içinde yapılır; yönlendirme yazılamazsa yazı da yayında kalır

Kazananı **veri seçer**: tıklama → gösterim → eski yayın tarihi → kısa slug.
Hedef yayında değilse birleştirme reddedilir. İşlem idempotenttir ve **geri alınabilir**.

## İç link motoru

Varsayılan olarak **kapalıdır**. Açmadan önce kuralları gözden geçirin.

- Link yalnız düz metne basılır; başlık, mevcut bağlantı, `code` ve `pre` içine asla girilmez
- Tavanlar her istekte kontrol edilir: makale başına, toplam ve anasayfa için ayrı
- Motor kelime uydurmaz; anchor metinleri yalnız gerçek hedef sayfalardan gelir
- AI gövdeye link gömmez; link basmanın tek sahibi bu motordur
- Reddedilen bir öneri (yazı + hedef + anchor) bir daha gösterilmez

## Otomasyon günlüğü

Otomasyonun izi `storage/logs/automation-YYYY-MM-DD.log` dosyasında, ana uygulama log'undan **ayrı**
tutulur. Her dakika koşan bir görev ana log'a yazsaydı gerçek hataları gömerdi.

Her tur bir satır yazar, değişiklik olmasa bile: "hiç koşmadı" ile "koştu ama atladı" ayrımı
teşhisin yarısıdır. Rutin sonuçlar `debug`, değişiklik ve hatalar `info` seviyesindedir; bir hata
varsa yanındaki rutin sebepler onu susturmaz.

Gürültüden kurtulmak için:

```dotenv
AUTOMATION_LOG_LEVEL=info
```

"Bugün blog neden üretilmedi?" sorusu tek komutla yanıtlanır:

```bash
grep "blog.generate" storage/logs/automation-*.log
```

## Aktif bölge kuralı

Sistem yalnız **erişilebilir** sayfalarla ilgilenir. Bir ilçe sayfasının erişilebilir sayılması için
**hem ilçenin hem de bağlı olduğu ilin** yayında olması gerekir; ili kapalı bir ilçe adresi 404 döner.

Bu kural her yerde geçerlidir:

- **Skorlama** yalnız erişilebilir sayfaları tarar. Kapatılan bir sayfanın eski skoru silinir, böylece
  gösterge panelinde var olmayan sayfalar "hedefin altında" görünmeye devam etmez.
- **Bölge anahtar kelimeleri** yalnız erişilebilir bölgeler için üretilir. Bölge kapanırsa kelimeler
  silinmez, pasife alınır; bölge tekrar açılınca kendiliğinden geri gelir.
- **İç link kuralları** yalnız erişilebilir sayfaları hedefler. Kapanan sayfanın kuralı pasife alınır.
- **İçerik zenginleştirme** kapalı sayfaya AI çağrısı harcamaz.

## Anahtar kelime sahipliği

| Kelime tipi | Örnek | Sahibi |
| --- | --- | --- |
| Bölge ticari | `çorlu mobilya montaj` | İlçe sayfası |
| Hizmet ticari | `ikea montaj servisi` | Hizmet sayfası |
| Bölge + hizmet | `çorlu gardırop montajı` | Sahipsiz — blog hattının hedefi |
| Bilgi amaçlı | `menteşe ayarı nasıl yapılır` | Sahipsiz — blog hattının hedefi |

Ticari kelimeler bilerek hizmet ve bölge sayfalarına atanır. Sahipsiz bırakılsalardı blog üretim
hattının havuzuna düşer ve blog, para kazandıran sayfayla aynı kelimeyi hedefleyerek onu yerdi.

## İç link motoru ve Türkçe

Türkçe eklemeli bir dildir: metinde "gardırop montajı" değil "gardırop montaj**ını**" geçer.
Motor, anchor metninden sonra gelen **çekim eklerini** (hâl, iyelik, bağlantı) kabul eder ama
**yapım eklerini** kabul etmez. Yani "montajını" linklenir, "montaj**cı**" linklenmez.

Motor idempotenttir: her gün koşsa bile mevcut linkleri bütçeye sayar ve üstüne link yığmaz.

## İçerik üretim takvimi

```bash
# Bir aylık stok üret (her güne bir yazı, taslak olarak)
php artisan blog:bulk --days=30

# Önce konuları gör, yazı üretme
php artisan blog:bulk --days=7 --dry-run
```

Yazılar **taslak** olarak kaydedilir ve `publish_at` alanına bir tarih verilir. `blog:publish-due`
her dakika koşarak zamanı geleni yayınlar.

Günlük `blog:generate` komutu **stoku tamamlar, üstüne yığmaz**: ileriye dönük üç günden fazla
planlanmış taslak varsa üretim yapmaz ve bunu günlüğe yazar. Stok azalınca kendiliğinden devam eder.

Üretilen her yazının uzunluğu **kod tarafında** denetlenir. Model istenen uzunluğun belirgin
altında bir metin döndürürse bir genişletme turu yapılır; mevcut bölümler korunur, üzerine yeni
bölüm eklenir.

## Yeni komutlar

| Komut | Ne yapar |
| --- | --- |
| `seo:location-keywords` | Aktif il/ilçeler için bölge adı içeren kelimeler üretir |
| `seo:enrich` | Hedef skorun altındaki yayındaki sayfaları AI ile genişletir |
| `blog:bulk --days=30` | Bir dönemlik blog stoğu üretir |
| `seo:score --all` | Yayında olmayanları da skorlar (varsayılan: yalnız yayındakiler) |
| `links:apply --type=district` | Yalnız belirli içerik tipine link işler |

## Model seçimi

Varsayılan `openai/gpt-4o-mini` ucuzdur ama kısa yazma eğilimindedir; sistem bunu genişletme turuyla
telafi eder. Daha uzun ve derin içerik için panelden daha güçlü bir model seçebilirsiniz
(**SEO & AI → Ayarlar → AI sağlayıcı**). Model listesi OpenRouter'dan canlı çekilir.

## Kırmızı çizgiler

Sistemde uygulanan, değiştirilmemesi gereken kurallar:

1. **Uydurma yasak.** Hiçbir metrik, müşteri, yorum veya rakam uydurulmaz. AI çıktısı kod tarafında
   sınırlanır: slug ASCII'ye indirilir, meta başlık 60, meta açıklama 155 karakterde kesilir,
   gövdeden bağlantılar ve `script` sökülür. Modele güvenilmez.
2. **API anahtarı** log'a, veritabanına, URL'ye veya hata mesajına yazılmaz.
3. **Loglama isteği bozmaz**, ama sessizce de yutulmaz; kanal yoksa varsayılana düşülür.
4. **Sorun metinleri sabittir.** Gösterge panelindeki sayımlar tam metinle eşleşir.
5. **Kimlik yoksa çökmez.** Google kimliği yoksa GSC aksiyonları, AI anahtarı yoksa AI aksiyonları gizlenir.
6. **Yönlendirme yalnız 404'te** çalışır; geçerli adreslere maliyeti sıfırdır.
7. **Keşif dosyaları cron'da** üretilir, istek anında değil.
8. **İçerik silinmez.** Yayından kaldırılır ve yönlendirilir.
9. **Geri alınamaz işlem yoktur.** Birleştirme geri alınabilir, pasife alınan hedefler silinmez.

## Marka ve logo

Logo vektör olarak `public/images/brand/` altındadır. Beş sürüm üretilmiştir:

| Dosya | Nerede kullanılır |
| --- | --- |
| `logo.svg` / `logo.webp` | Tam logo (alt slogan dahil) — basılı iş, sosyal medya |
| `logo-horizontal.svg` / `.webp` | Üst bilgi ve mobil menü (alt slogan yok) |
| `logo-light.svg` / `.webp` | Koyu zeminde tam logo |
| `logo-horizontal-light.svg` / `.webp` | Altbilgi (koyu zemin) |
| `logo-mark.svg` / `.webp` | Yalnız simge — favicon, kare kullanım |

Renkler logodan ölçülmüştür: lacivert `#0F2B47`, turuncu `#D86C30`.

Sitede WebP sürümler kullanılır (istediğiniz gibi), SVG dosyaları kaynak olarak durur. Panelden
**Site Ayarları** üzerinden logo yüklerseniz yüklediğiniz dosya bunların yerine geçer.

> Yazı tipi notu: SVG'lerde başlıklar sistem yazı tipi yığınıyla (`Arial Black` ve alternatifleri)
> tanımlıdır. Başka bir bilgisayarda açtığınızda harfler biraz farklı görünebilir. Sitede kullanılan
> WebP dosyaları burada işlendiği için her yerde aynı görünür. Kurumsal bir baskı işi için
> yazıların dışa vektör olarak (outline) çevrilmesi gerekir.

## Görseller otomatik WebP olur

Panele yüklenen her görsel **WebP'ye çevrilir**: hizmet görselleri, galeri, blog kapakları, personel
fotoğrafları, site ayarlarındaki görseller ve müşterilerin teklif formundan gönderdiği fotoğraflar.

Aynı işlemde:

- Telefon fotoğraflarındaki **EXIF dönüklüğü** düzeltilir (yan yatmış fotoğraf sorunu).
- Uzun kenarı **2200 pikseli** aşan görseller küçültülür.
- Dosya adı Türkçe karakterlerden arındırılıp okunabilir hâle getirilir (görsel aramasında işe yarar).

**Görsel olmayan dosyalara dokunulmaz.** Google servis hesabı JSON'u olduğu gibi kaydedilir; çevrilseydi
bozulurdu. Bir görsel çevrilemezse (bozuk dosya, animasyonlu GIF) orijinali kaydedilir; yükleme asla düşmez.

Ölçüm: 4000×3000 piksel 321 KB JPEG → 2200×1650 piksel 6 KB WebP.

## Roller ve yetkiler

Dört rol vardır. Yetkiler `app/Policies` altındaki dört ortak ilkede tanımlıdır.

| | Süper Yönetici | Müşteri Temsilcisi | Personel | Montajcı |
| --- | --- | --- | --- | --- |
| İçerik görür | ✓ | ✓ | ✓ | — |
| İçerik düzenler | ✓ | — | ✓ | — |
| Teklif taleplerini görür | tümü | tümü | tümü | **yalnız kendine atananlar** |
| Talep atar | ✓ | ✓ | — | — |
| SEO ekranlarını görür | ✓ | — | ✓ | — |
| SEO ayarlarını değiştirir | ✓ | — | — | — |
| Site ayarları / kullanıcılar | ✓ | — | — | — |

**Montajcı yalnız kendi işini görür.** Bu iki katmanda birden uygulanır: liste sorgusu filtrelenir ve
tek kayıt erişimi ilkeyle korunur. Yalnız listeyi filtrelemek yeterli olmazdı — kayıt numarasını bilen
biri doğrudan adrese gidebilirdi.

**Hesap aktif** kapatılırsa kişi panele giremez ve sitede de görünmez; kayıtları ve geçmiş atamaları silinmez.

## Talep atama akışı

```
Müşteri formu doldurur
        ↓
Müşteri temsilcisi talebi görür  →  "Montajcıya ata" (tek tek veya toplu)
        ↓
Montajcı panelinde yalnız kendi işini görür, durumunu günceller
```

Atama yapıldığında kim atadı, ne zaman atadı ve montajcıya bırakılan not kaydedilir. Durum otomatik
olarak "Yeni"den "İletişime Geçildi"ye geçer; tamamlanmış bir iş yeniden atanırsa durumu geri alınmaz.

Atama listesinde her montajcının yanında **açık iş sayısı** görünür, böylece iş yükü dengelenebilir.

## Personelin sitede görünmesi

**Ayarlar → Personel** ekranında bir kişiye telefon girip **"Web sitesinde göster"** anahtarını açın.
O kişi iletişim sayfasındaki ve ana sayfadaki ekip listesinde yer alır; ziyaretçiler doğrudan onun
numarasına yönlendirilir (tıkla-ara ve WhatsApp).

Kurallar:

- Telefonu olmayan kişi listelenmez — tıklanacak bir şey olmadan kart göstermek ziyaretçiyi çıkmaza sokar.
- WhatsApp alanı boşsa telefon numarası kullanılır.
- Fotoğraf yoksa baş harfleri gösterilir.
- **E-posta ve rol asla siteye basılmaz**; panel hesabı bilgileri herkese açık sayfaya sızmaz.

## Analitik paneli

**SEO & AI → Analitik** ekranı tek sayfada şunları verir:

- **Sayı kutuları:** teklif talebi (son 30 gün, önceki döneme göre değişimle), yayındaki ve planlı
  blog sayısı, AI bot ziyareti, ortalama SEO skoru, çözülmemiş 404.
- **Teklif talepleri grafiği:** son 30 günün günlük seyri. Sitenin ana dönüşüm ölçüsüdür.
- **Yapay zeka botları:** bot başına toplu kart (aşağıda).
- **İçerik tipine göre SEO skoru:** yatay çubuk, rakam çubuğun yanında yazılı.
- **Yaklaşan blog yazıları:** planlanmış taslakların takvimi.
- **Google araması:** Search Console bağlıysa tıklama, gösterim, ortalama sıra ve en çok tıklanan
  aramalar. Bağlı değilse bunu açıkça söyler ve anahtar yükleme ekranına bağlantı verir.
- **Bulunamayan adresler:** en çok istenen 404'ler.

Veri yoksa her bölüm ne yapılması gerektiğini söyleyen bir boş durum gösterir; boş eksen çizmez.

### Grafik renkleri

`app/Support/ChartPalette.php` içindeki palet, renk körlüğü ayrımı ve yüzey kontrastı için
doğrulanmıştır. Renkler kimliğe göre **sabit sırayla** atanır, döngüye sokulmaz. Rakamlar her zaman
çubuğun yanında yazılıdır; bilgi asla yalnız renkle taşınmaz.

Palet değiştirilecekse doğrulama yeniden çalıştırılmalıdır; gelişigüzel renk seçmeyin.

## AI bot ziyaretleri

Ham kayıt (bot + adres) okunmaz: tek bir bot onlarca satıra dağılır. Bu yüzden ziyaretler
**bot başına tek kartta** toplanır:

- toplam ziyaret ve genel içindeki payı
- kaç farklı adres okunduğu
- en çok okunan 3 adres
- hatalı istek sayısı (404 vb.)
- son ziyaret zamanı

Kartlar hem **Analitik** ekranında hem de **Gözlem → AI Bot Ziyaretleri** sayfasının üstünde
görünür; ham kayıtlar aynı sayfadaki tabloda kalır.

## Google Search Console anahtarını yükleme

Artık dosyayı elle klasöre koymanıza gerek yok. **SEO & AI → Ayarlar → Search Console** sekmesinde:

1. Google Cloud → **IAM ve Yönetici → Hizmet Hesapları → Anahtarlar → Anahtar ekle → JSON**
2. İnen dosyayı yükleme alanına sürükleyin, **Kaydet**'e basın.

Dosya `storage/app/private` altına yazılır; bu klasör web sunucusundan **erişilemez**. Yükleme
kabul edilmeden önce doğrulanır: geçerli JSON mu, türü `service_account` mı, özel anahtar var mı.
Geçersiz dosya diskten silinir ve eski ayar korunur — bozuk bir yolu kaydetmek her Search Console
çağrısının sessizce düşmesine yol açardı.

Kaydettikten sonra servis hesabının e-posta adresi ekranda görünür. Onu Search Console'da
**Ayarlar → Kullanıcılar ve izinler** bölümünden mülkünüze ekleyin.

## Yönetim paneli teması

Panel, `resources/css/filament/admin/theme.css` dosyasından derlenen bir tema kullanır. Bu tema
`app/Filament` ve `resources/views/filament` altındaki dosyaları tarar; panele eklenen özel kart ve
ızgaraların biçimlenmesi buna bağlıdır.

Panele yeni bir Blade görünümü ekledikten sonra **yeniden derleyin**:

```bash
npm run build
```

Tailwind yalnız bu tema için kullanılır. Sitenin ön yüzü Bootstrap 5'tedir ve bu değişiklikten
etkilenmez.

## Sistemi otomatik çalıştırmak

Şu an tüm ayarlar açık ama **zamanlayıcı ve kuyruk işçisi çalışmıyor**. Sunucuda bu ikisi olmadan
hiçbir görev kendiliğinden koşmaz.

**1. Cron (tek satır yeterli):**

```
* * * * * cd /Users/muhammet/Desktop/montaj && php artisan schedule:run >> /dev/null 2>&1
```

**2. Kuyruk işçisi** (günlük blog üretimi kuyruğa iş atar):

```bash
php artisan queue:work --tries=2 --timeout=600
```

Sunucuda bunu Supervisor veya `systemd` ile sürekli açık tutun. Yerel makinede denemek için
ayrı bir terminalde çalıştırmanız yeterli.

**Çalıştığını nasıl anlarsınız:**

```bash
tail -f storage/logs/automation-$(date +%F).log
```

Rutin turlar `DEBUG`, değişiklik olanlar `INFO` satırı yazar. Hiç satır yoksa cron koşmuyordur.

## Veritabanı hakkında uyarı

Bu proje canlı içerik barındırır. **`migrate:fresh`, `migrate:refresh`, `migrate:reset` ve `db:wipe`
çalıştırmayın**; bu komutlar bağlı veritabanındaki tüm tabloları siler. Yeni migrasyonlar için
yalnız `php artisan migrate` kullanın. Seeder'lar idempotenttir (`firstOrCreate`), tekrar
çalıştırmak var olan kayıtların üzerine yazmaz.

Testler yalnız bellek içi SQLite üzerinde koşar. `tests/TestCase.php` içindeki koruma, bağlantı
farklıysa testleri veritabanına dokunmadan durdurur.
