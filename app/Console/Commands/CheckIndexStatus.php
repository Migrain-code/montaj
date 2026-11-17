<?php

namespace App\Console\Commands;

use App\Services\Google\SearchConsoleService;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

class CheckIndexStatus extends Command
{
    protected $signature = 'seo:check-index {--limit=0 : Kontrol edilecek adres sayısı (0 = günlük kota elverdiğince site haritasındaki tüm adresler)} {--seconds=780 : Süre bütçesi; dolunca durur, kalanı sonraki tur sorar (0 = sınırsız)}';

    protected $description = 'Sayfaların Google indeks durumunu kontrol eder (URL Inspection API)';

    public function handle(SearchConsoleService $gsc): int
    {
        if (! $gsc->isConfigured()) {
            AutomationLog::summary('gsc.index', ['configured' => false], reasons: ['not_configured']);

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $result = $gsc->syncIndexStatus($limit > 0 ? $limit : SearchConsoleService::INSPECT_DAILY_QUOTA, (int) $this->option('seconds'));

        AutomationLog::summary('gsc.index', $result,
            reasons: $result['checked'] === 0 ? ['no_candidates'] : [],
            errors: $result['errors'] > 0 ? ['inspection_failed'] : [],
            changed: $result['checked'] > 0);

        $this->info("{$result['checked']} / {$result['tracked']} adres kontrol edildi, {$result['indexed']} tanesi indeksli. Sırada bekleyen: {$result['pending']} (sonraki saatlik turda sorulur). Bugün kalan sorgu hakkı: {$result['quota_left']}.");

        return self::SUCCESS;
    }
}
