<?php

namespace App\Console\Commands;

use App\Models\SeoAnalysis;
use App\Services\Google\SearchConsoleService;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

class CheckIndexStatus extends Command
{
    protected $signature = 'seo:check-index {--limit=0 : Kontrol edilecek adres sayısı (0 = sistemdeki tüm adresler)}';

    protected $description = 'Sayfaların Google indeks durumunu kontrol eder (URL Inspection API)';

    public function handle(SearchConsoleService $gsc): int
    {
        if (! $gsc->isConfigured()) {
            AutomationLog::summary('gsc.index', ['configured' => false], reasons: ['not_configured']);

            return self::SUCCESS;
        }

        $result = $gsc->syncIndexStatus($this->limit());

        AutomationLog::summary('gsc.index', $result,
            reasons: $result['checked'] === 0 ? ['no_candidates'] : [],
            errors: $result['errors'] > 0 ? ['inspection_failed'] : [],
            changed: $result['checked'] > 0);

        $this->info("{$result['checked']} sayfa kontrol edildi, {$result['indexed']} tanesi indeksli.");

        return self::SUCCESS;
    }

    /**
     * Varsayılan: sistemdeki TÜM adresler.
     *
     * URL Inspection API'nin günlük kotası mülk başına 2000 sorgu; site bunun
     * çok altında olduğu için tek turda tamamı kontrol edilir. Kotayı aşmamak
     * için üst sınır yine de 2000'de tutulur.
     */
    private function limit(): int
    {
        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            return $limit;
        }

        return min(2000, max(1, SeoAnalysis::query()->whereNotNull('url')->count()));
    }
}
