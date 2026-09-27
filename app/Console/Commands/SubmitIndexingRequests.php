<?php

namespace App\Console\Commands;

use App\Services\Google\IndexingService;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

/**
 * Dizine girmemiş sayfaları Google Indexing API ile bildirir.
 *
 * Günlük kota mülk başına 200 adrestir; servis kalan kotayı kendisi hesaplar.
 * Aynı adres 14 gün geçmeden tekrar gönderilmez.
 */
class SubmitIndexingRequests extends Command
{
    protected $signature = 'seo:submit-index {--limit=0 : Bu turda gönderilecek en fazla adres (0 = kalan kota kadar)}';

    protected $description = 'Dizine girmemiş sayfalar için Google\'a dizine ekleme bildirimi gönderir';

    public function handle(IndexingService $indexing): int
    {
        if (! $indexing->isConfigured()) {
            AutomationLog::summary('gsc.indexing', ['configured' => false], reasons: ['not_configured']);
            $this->warn('Google bağlantısı kurulmamış; bildirim gönderilmedi.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $result = $indexing->submitPending($limit > 0 ? $limit : null);

        AutomationLog::summary('gsc.indexing', $result,
            reasons: $result['skipped'] === 'quota' ? ['daily_quota_reached'] : ($result['sent'] === 0 ? ['no_candidates'] : []),
            errors: $result['failed'] > 0 ? ['submit_failed'] : [],
            changed: $result['sent'] > 0);

        if ($result['skipped'] === 'quota') {
            $this->warn('Günlük 200 bildirim kotası dolmuş; yarın devam edilecek.');

            return self::SUCCESS;
        }

        $this->info("{$result['sent']} adres Google'a bildirildi, {$result['failed']} hata. Bugün kalan kota: {$result['remaining']}.");

        return self::SUCCESS;
    }
}
