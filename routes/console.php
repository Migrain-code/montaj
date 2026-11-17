<?php

use App\Models\CommandRun;
use App\Support\Console\InProcess;

/*
|--------------------------------------------------------------------------
| SEO & AI otomasyon takvimi (spec §4)
|--------------------------------------------------------------------------
| SIRA ÖNEMLİ: önce veri çekilir (05:00-05:10), sonra ondan TÜRETİLEN işler
| koşar (05:20+). Ters çevrilirse türetilmiş işler bir gün eski veriyle çalışır.
|
| Hepsi withoutOverlapping: uzun süren bir tur, bir sonrakiyle çakışmasın.
|
| Sunucuda tek bir cron satırı yeterlidir:
|   * * * * * cd /proje/yolu && php artisan schedule:run >> /dev/null 2>&1
|
| Görevler Schedule::command() ile DEĞİL InProcess::command() ile eklenir: hosting
| proc_open'ı kapatıyor ve Schedule::command() ayrı süreç başlatamadığı için hiç
| çalışmıyor. Ayrıntı: App\Support\Console\InProcess.
*/

/*
 * Nabız: panel bu damgaya bakarak cron'un ve kuyruk işçisinin çalıştığını gösterir.
 * Terminal erişimi olmayan sunucuda bunu görmenin başka yolu yok.
 */
InProcess::command('system:heartbeat')->everyMinute()->withoutOverlapping();

// Panelden çalıştırılan komutların 30 günden eski kayıtları silinir.
InProcess::command('model:prune', ['--model' => [CommandRun::class]])->dailyAt('00:30')->withoutOverlapping();

InProcess::command('sitemap:generate')->dailyAt('01:00')->withoutOverlapping();
InProcess::command('seo:discovery')->dailyAt('01:10')->withoutOverlapping();

InProcess::command('seo:sync-targets')->dailyAt('01:50')->withoutOverlapping();

/*
 * Bölge kelimeleri hedeflere atandığı için hedef senkronundan SONRA koşar.
 * Bir il/ilçe panelden açılıp kapatıldığında kelimeler kendiliğinden açılır/pasifleşir.
 */
InProcess::command('seo:location-keywords')->dailyAt('01:55')->withoutOverlapping();

/*
 * Marka kelimeleri de hedeflere atandığı için hedef senkronundan SONRA koşar.
 * Panelden bir marka kapatıldığında kelimeleri kendiliğinden pasifleşir.
 */
InProcess::command('seo:brand-keywords')->dailyAt('01:57')->withoutOverlapping();

InProcess::command('seo:score')->dailyAt('02:00')->withoutOverlapping();

/*
 * Hedef skorun altında kalan YAYINDAKİ sayfaları AI ile genişletir.
 * Skorlamadan sonra koşar ki hangi sayfanın zayıf olduğu belli olsun.
 * Günlük sınır düşük tutulur: AI çağrısı ücretlidir ve içerik bir gecede değil,
 * zaman içinde iyileşmelidir.
 */
InProcess::command('seo:enrich', ['--limit' => 10])->dailyAt('02:20')->withoutOverlapping();
// Metinsiz il/ilçe sayfaları (150 kelimeden kısa) her gece 10'ar doldurulur; bitince iş kalmaz.
InProcess::command('seo:enrich', ['--thin' => true, '--limit' => 10])->dailyAt('02:40')->withoutOverlapping();
// 600 kelimenin altındaki sayfalar metne dokunulmadan yeni bölümlerle uzatılır (her gece 10).
InProcess::command('seo:enrich', ['--short' => true, '--limit' => 10])->dailyAt('03:10')->withoutOverlapping();

// Site haritasındaki tüm adresler sorulur. Her tur ~13 dk ile sınırlı (kuyruk zaman aşımı);
// son 20 saatte bakılan adres tekrar sorulmaz, yani işi bitmiş turlar kota harcamadan çıkar.
InProcess::command('seo:check-index')->hourly()->between('01:00', '07:00')->withoutOverlapping(20);

/*
 * Dizine girmemiş sayfalar Google'a bildirilir. Kontrolden SONRA koşar ki hangi
 * sayfanın dizinde olmadığı taze veriyle belli olsun. Günlük kota 200 adres.
 */
InProcess::command('seo:submit-index')->dailyAt('03:30')->withoutOverlapping();

InProcess::command('blog:generate')->dailyAt('04:00')->withoutOverlapping();

// --- veri çekme ---
InProcess::command('seo:sync-search-console')->dailyAt('05:00')->withoutOverlapping();
InProcess::command('seo:sync-rankings')->dailyAt('05:10')->withoutOverlapping();

/*
 * GSC fırsat avı: bölge kelimelerinin 28 günlük sırası, yeni fırsatlar ve günlük sınır
 * kadar kelimenin sayfasını iyileştirme. İlk 3'teki kelimeye dokunmaz.
 * Veri çekme saatlerinden SONRA koşar; iç link kuralları (05:45) ve uygulayıcı (05:50)
 * aynı sabah yeni kuralları alır.
 */
InProcess::command('seo:opportunities')->dailyAt('05:15')->withoutOverlapping();

// --- veriden türetilen işler ---
InProcess::command('seo:discover-keywords')->weeklyOn(1, '05:20')->withoutOverlapping();
InProcess::command('seo:refresh-meta')->weeklyOn(1, '05:30')->withoutOverlapping();
InProcess::command('seo:suggest-redirects')->dailyAt('05:40')->withoutOverlapping();
// Kurallar önce üretilir; yeni açılan sayfa aynı gün link almaya başlasın.
InProcess::command('links:build-rules')->dailyAt('05:45')->withoutOverlapping();
InProcess::command('links:apply')->dailyAt('05:50')->withoutOverlapping();

// İç linkler basıldıktan sonra skorlar tazelensin.
InProcess::command('seo:score')->dailyAt('06:00')->withoutOverlapping();

/*
 * Yayın kararı DAKİKA hassasiyetindedir; görevi 5 dakikada bir koşturmak kararı
 * 5 dakika geciktirir (spec §7.14). Dışarıya çıkmayan, yalnız tek sorgu atan bir
 * iş olduğu için sıklığı kararın hassasiyetiyle eşitlenir: her dakika.
 */
InProcess::command('blog:publish-due')->everyMinute()->withoutOverlapping();
