<?php

namespace App\Console\Commands;

use App\Models\SeoKeyword;
use App\Services\Seo\OpportunityHunter;
use App\Services\Seo\RankBooster;
use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * GSC fırsat avı turu: sıraları çeker, yeni bölge kelimelerini ekler ve günlük
 * sınır kadar kelimenin sayfasını iyileştirir.
 *
 * Hedefteki (ilk 3) kelimeye dokunulmaz. Aynı kelime bekleme süresi dolmadan
 * yeniden işlenmez: Google'ın bir değişikliğe tepki vermesi günler sürer ve aynı
 * sayfaya her gün yazmak iyileştirme değil gürültüdür.
 */
class HuntOpportunities extends Command
{
    protected $signature = 'seo:opportunities
                            {--sync-only : Yalnız sıraları çek ve fırsat ara, sayfalara yazma}
                            {--limit= : Bu turda en fazla kaç kelime iyileştirilsin}';

    protected $description = 'GSC fırsat avı: bölge kelimelerinin sırasını çeker ve ilk 3\'e taşımak için sayfayı iyileştirir';

    public function handle(OpportunityHunter $hunter, RankBooster $booster): int
    {
        try {
            $stats = $hunter->run();
        } catch (Throwable $e) {
            AutomationLog::error('seo.opportunity', $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $hunter->isConfigured()) {
            $this->warn('Google Search Console bağlı değil: sıra çekilemedi, yalnız odak bölge kelimeleri takibe alındı.');
        }

        $this->info(sprintf(
            '%d sorgu okundu · %d yeni fırsat · %d kelime izleniyor · %d güncellendi · %d hedefe girdi · %d hedeften düştü.',
            $stats['rows'], $stats['hunted'] + $stats['seeded'], $stats['tracked'], $stats['updated'], $stats['won'], $stats['lost'],
        ));

        $boosted = [];
        $reasons = [];

        if ($this->option('sync-only')) {
            $reasons[] = 'sync_only';
        } elseif (! SeoConfig::bool('opportunity_autopilot', true)) {
            $reasons[] = 'disabled';
        } elseif (! $hunter->isConfigured()) {
            // Sıra bilinmeden "ilk 3'te değil" denemez; körlemesine yazılmaz.
            $reasons[] = 'not_configured';
        } else {
            foreach ($this->queue((int) ($this->option('limit') ?: SeoConfig::int('opportunity_daily_limit', 3, 1, 20))) as $keyword) {
                $result = $booster->boost($keyword);
                $boosted[] = $keyword->keyword;

                $this->line(sprintf('  %-40s #%s  %s', $keyword->keyword, $keyword->last_position ?? '-',
                    $result['blocked'] ?? ('yapıldı: '.(implode(', ', $result['ran']) ?: 'yok').($result['failed'] ? ' · hata: '.implode(', ', $result['failed']) : ''))));
            }

            if ($boosted === []) {
                $reasons[] = 'no_candidates';
            }
        }

        AutomationLog::summary('seo.opportunity', $stats + ['boosted' => count($boosted)],
            reasons: $reasons, changed: $stats['hunted'] + $stats['seeded'] + $stats['won'] + $stats['lost'] + count($boosted) > 0);

        return self::SUCCESS;
    }

    /**
     * Sıra: odak bölgeler → 4-20 arası ("ilk sayfaya yakın", en hızlı karşılık veren)
     * → çok gösterim alan. Sırası hiç ölçülmemiş kelime en sona kalır.
     *
     * @return Collection<int, SeoKeyword>
     */
    private function queue(int $limit): Collection
    {
        $cooldown = SeoConfig::int('opportunity_cooldown_days', 7, 1, 60);
        $focus = (array) config('seo.opportunity.focus_regions', []);

        return SeoKeyword::query()
            ->where('campaign_status', SeoKeyword::CAMPAIGN_ACTIVE)
            ->whereNotNull('target_id')
            ->where(fn ($q) => $q->whereNull('last_boost_at')->orWhere('last_boost_at', '<', now()->subDays($cooldown)))
            ->with('target')
            ->get()
            ->reject(fn (SeoKeyword $k) => $k->reachedTarget())
            ->sortBy(fn (SeoKeyword $k) => [
                in_array($k->region, $focus, true) ? 0 : 1,
                $k->last_position !== null && $k->last_position <= 20 ? 0 : 1,
                $k->last_position === null ? 1 : 0,
                -$k->gsc_impressions,
            ])
            ->take($limit)
            ->values();
    }
}
