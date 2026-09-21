<?php

namespace App\Console\Commands;

use App\Services\Seo\BrandKeywordBuilder;
use App\Services\Seo\TargetSynchroniser;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

class BuildBrandKeywords extends Command
{
    protected $signature = 'seo:brand-keywords';

    protected $description = 'Yayındaki markalar için marka adı içeren anahtar kelimeleri üretir';

    public function handle(BrandKeywordBuilder $builder, TargetSynchroniser $targets): int
    {
        // Kelimeler marka sayfalarına atanacağı için önce hedefler güncel olmalı.
        $targets->sync();

        $result = $builder->build();

        if ($result['brands'] === []) {
            AutomationLog::summary('seo.brand_keywords', ['brands' => 0], reasons: ['no_candidates']);
            $this->warn('Yayında marka yok.');

            return self::SUCCESS;
        }

        $changed = $result['created'] + $result['assigned'] + $result['deactivated'] > 0;

        AutomationLog::summary('seo.brand_keywords', [
            'brands' => count($result['brands']),
            'created' => $result['created'],
            'assigned' => $result['assigned'],
            'deactivated' => $result['deactivated'],
        ], reasons: $changed ? [] : ['unchanged'], changed: $changed);

        $this->info(sprintf(
            '%d marka işlendi: %d yeni kelime, %d tanesi marka sayfasına atandı, %d tanesi pasife alındı.',
            count($result['brands']), $result['created'], $result['assigned'], $result['deactivated'],
        ));
        $this->line('  Markalar: '.implode(' · ', $result['brands']));

        return self::SUCCESS;
    }
}
