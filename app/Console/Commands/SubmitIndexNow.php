<?php

namespace App\Console\Commands;

use App\Services\Seo\IndexNow;
use App\Support\SitemapUrls;
use Illuminate\Console\Command;

/**
 * Elle IndexNow bildirimi.
 *
 *   php artisan seo:indexnow --all            site haritasındaki tüm dizine açık adresler
 *   php artisan seo:indexnow https://…/a …    yalnız verilen adresler
 *
 * Yayın sonrası ilk kurulumda --all bir kez çalıştırılır; sonrasında yeni yazı ve
 * metni açılan bölgeler gözlemci (IndexNowObserver) ile otomatik bildirilir.
 */
class SubmitIndexNow extends Command
{
    protected $signature = 'seo:indexnow {urls?* : Bildirilecek tam adresler} {--all : Site haritasındaki tüm adresler}';

    protected $description = 'Adresleri IndexNow (Bing, Yandex…) ile anında bildirir';

    public function handle(IndexNow $indexNow): int
    {
        if (! $indexNow->enabled()) {
            $this->warn('IndexNow anahtarı tanımlı değil (8–128 karakter hex). SEO Merkezi → Ayarlar → IndexNow bölümüne ya da .env dosyasına (INDEXNOW_KEY) ekleyin.');

            return self::FAILURE;
        }

        $urls = $this->option('all')
            ? array_column(SitemapUrls::all(), 'loc')
            : (array) $this->argument('urls');

        if ($urls === []) {
            $this->warn('Adres verilmedi. --all ya da tam adres(ler) kullanın.');

            return self::INVALID;
        }

        $sent = $indexNow->submit($urls);
        $this->info("{$sent} / ".count($urls).' adres bildirildi.');

        if ($sent === 0 && $indexNow->lastError) {
            $this->error($indexNow->lastError);
        }

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }
}
