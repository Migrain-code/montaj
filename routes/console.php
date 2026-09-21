<?php

use Illuminate\Support\Facades\Schedule;

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
*/

/*
 * Nabız: panel bu damgaya bakarak cron'un ve kuyruk işçisinin çalıştığını gösterir.
 * Terminal erişimi olmayan sunucuda bunu görmenin başka yolu yok.
 */
Schedule::command('system:heartbeat')->everyMinute()->withoutOverlapping();

// Panelden çalıştırılan komutların 30 günden eski kayıtları silinir.
Schedule::command('model:prune', ['--model' => [\App\Models\CommandRun::class]])->dailyAt('00:30')->withoutOverlapping();

Schedule::command('sitemap:generate')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('seo:discovery')->dailyAt('01:10')->withoutOverlapping();

Schedule::command('seo:sync-targets')->dailyAt('01:50')->withoutOverlapping();

/*
 * Bölge kelimeleri hedeflere atandığı için hedef senkronundan SONRA koşar.
 * Bir il/ilçe panelden açılıp kapatıldığında kelimeler kendiliğinden açılır/pasifleşir.
 */
Schedule::command('seo:location-keywords')->dailyAt('01:55')->withoutOverlapping();

/*
 * Marka kelimeleri de hedeflere atandığı için hedef senkronundan SONRA koşar.
 * Panelden bir marka kapatıldığında kelimeleri kendiliğinden pasifleşir.
 */
Schedule::command('seo:brand-keywords')->dailyAt('01:57')->withoutOverlapping();

Schedule::command('seo:score')->dailyAt('02:00')->withoutOverlapping();

/*
 * Hedef skorun altında kalan YAYINDAKİ sayfaları AI ile genişletir.
 * Skorlamadan sonra koşar ki hangi sayfanın zayıf olduğu belli olsun.
 * Günlük sınır düşük tutulur: AI çağrısı ücretlidir ve içerik bir gecede değil,
 * zaman içinde iyileşmelidir.
 */
Schedule::command('seo:enrich', ['--limit' => 3])->dailyAt('02:20')->withoutOverlapping();

Schedule::command('seo:check-index')->dailyAt('03:00')->withoutOverlapping();

Schedule::command('blog:generate')->dailyAt('04:00')->withoutOverlapping();

// --- veri çekme ---
Schedule::command('seo:sync-search-console')->dailyAt('05:00')->withoutOverlapping();
Schedule::command('seo:sync-rankings')->dailyAt('05:10')->withoutOverlapping();

// --- veriden türetilen işler ---
Schedule::command('seo:discover-keywords')->weeklyOn(1, '05:20')->withoutOverlapping();
Schedule::command('seo:refresh-meta')->weeklyOn(1, '05:30')->withoutOverlapping();
Schedule::command('seo:suggest-redirects')->dailyAt('05:40')->withoutOverlapping();
// Kurallar önce üretilir; yeni açılan sayfa aynı gün link almaya başlasın.
Schedule::command('links:build-rules')->dailyAt('05:45')->withoutOverlapping();
Schedule::command('links:apply')->dailyAt('05:50')->withoutOverlapping();

// İç linkler basıldıktan sonra skorlar tazelensin.
Schedule::command('seo:score')->dailyAt('06:00')->withoutOverlapping();

/*
 * Yayın kararı DAKİKA hassasiyetindedir; görevi 5 dakikada bir koşturmak kararı
 * 5 dakika geciktirir (spec §7.14). Dışarıya çıkmayan, yalnız tek sorgu atan bir
 * iş olduğu için sıklığı kararın hassasiyetiyle eşitlenir: her dakika.
 */
Schedule::command('blog:publish-due')->everyMinute()->withoutOverlapping();
