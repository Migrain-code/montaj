<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\District;
use App\Models\Province;
use App\Models\SeoAnalysis;
use App\Models\Service;
use App\Services\Ai\AiClient;
use App\Services\Ai\RegionContentEnricher;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\ContentScorer;
use App\Services\Seo\PageRenderer;
use App\Support\AutomationLog;
use App\Support\SeoConfig;
use App\Support\SeoIssue;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Hedef skorun altındaki YAYINDAKİ sayfaların içeriğini AI ile genişletir.
 *
 * Kapalı sayfalara dokunmaz: 404 dönen bir sayfayı zenginleştirmek boşa AI çağrısıdır.
 *
 * Seçenekler:
 *   --thin   kendine özgü metni olmayan (150 kelimeden kısa) il/ilçe sayfaları, skordan bağımsız
 *   --short  600 kelimenin altındaki sayfalar: mevcut metne DOKUNMADAN yeni bölümler eklenir
 */
class EnrichContent extends Command
{
    protected $signature = 'seo:enrich
                            {--type=* : district|province|brand|service (boş bırakılırsa hepsi)}
                            {--limit=50 : En fazla kaç sayfa}
                            {--force : Hedef skorun üstündekileri de işle}
                            {--thin : Yalnız henüz kendine özgü metni olmayan (150 kelimeden kısa) il/ilçe sayfaları}
                            {--short : 600 kelimenin altındaki sayfaları, metne dokunmadan yeni bölümlerle uzat}';

    protected $description = 'Yayındaki sayfaların içeriğini AI ile genişletir ve yeniden skorlar';

    public function handle(
        AiClient $ai,
        RegionContentEnricher $enricher,
        ContentRegistry $registry,
        ContentScorer $scorer,
        PageRenderer $renderer,
    ): int {
        if (! $ai->isConfigured()) {
            AutomationLog::summary('seo.enrich', ['configured' => false], reasons: ['not_configured']);
            $this->warn('AI yapılandırılmamış. .env dosyasına AI_API_KEY girin.');

            return self::SUCCESS;
        }

        $target = SeoConfig::int('score_target', 80, 1, 100);
        $short = (bool) $this->option('short');
        $candidates = ($short
            ? $this->shortCandidates((array) $this->option('type'))
            : $this->candidates($registry, $target, (array) $this->option('type'), (bool) $this->option('force'), (bool) $this->option('thin')))
            ->take((int) $this->option('limit'));

        if ($candidates->isEmpty()) {
            AutomationLog::summary('seo.enrich', ['processed' => 0], reasons: ['no_candidates']);
            $this->info('Zenginleştirilecek sayfa yok — yayındaki tüm içerik hedef skorun üstünde.');

            return self::SUCCESS;
        }

        $this->info($candidates->count().' sayfa işlenecek. Her sayfa bir AI çağrısı demektir.');
        $this->newLine();

        $done = 0;
        $errors = [];
        $renderer->prepare();

        foreach ($candidates as $model) {
            $label = $this->label($model);

            try {
                $result = $short ? $enricher->lengthen($model) : $enricher->enrich($model);
            } catch (Throwable $e) {
                $errors[] = $label.': '.$e->getMessage();
                $this->line("  <fg=red>HATA</> {$label} — ".$e->getMessage());

                continue;
            }

            // Hemen yeniden skorla ki sonucu görelim.
            $content = $registry->forModel($model->refresh());
            $content->renderedHtml = $renderer->mainHtml($content->url);
            $analysis = $scorer->persist($content);

            $this->line(sprintf(
                '  <fg=green>TAMAM</> %-28s %d → %d kelime · %d H2 · skor %d/100',
                $label, $result['words_before'], $result['words_after'], $result['h2'], $analysis->score,
            ));

            $done++;
        }

        AutomationLog::summary('seo.enrich', [
            'processed' => $done,
            'failed' => count($errors),
        ], errors: $errors, changed: $done > 0);

        $this->newLine();
        $this->info("{$done} sayfa zenginleştirildi.".(count($errors) ? ' '.count($errors).' hata.' : ''));

        return self::SUCCESS;
    }

    private const TYPES = ['district', 'province', 'brand', 'service'];

    /** Bu kelime sayısının altındaki bölge metni "metinsiz" sayılır (--thin). */
    public const THIN_WORDS = 150;

    /**
     * Son skorlamada "600 kelimeden az" uyarısı alan sayfalar — skordan BAĞIMSIZ. En kısa
     * olanlar önce. Metni hiç olmayan (150 kelime altı) sayfalar --thin işidir, burada atlanır.
     *
     * @return Collection<int, Model>
     */
    private function shortCandidates(array $types): Collection
    {
        $types = array_values(array_intersect($types ?: self::TYPES, self::TYPES)) ?: self::TYPES;

        return SeoAnalysis::query()
            ->whereIn('content_type', $types)
            ->get()
            ->filter(fn (SeoAnalysis $a) => array_intersect([SeoIssue::CONTENT_THIN, SeoIssue::CONTENT_TOO_SHORT], (array) $a->issues) !== [])
            ->map(fn (SeoAnalysis $a) => match ($a->content_type) {
                'district' => District::query()->where('is_active', true)->with('province')->find($a->content_id),
                'province' => Province::query()->where('is_active', true)->find($a->content_id),
                'brand' => Brand::query()->where('is_active', true)->whereNull('service_id')->find($a->content_id),
                'service' => Service::query()->where('is_active', true)->find($a->content_id),
                default => null,
            })
            ->filter()
            ->map(fn (Model $m) => [$m, self::words($m)])
            ->filter(fn (array $row) => $row[1] >= self::THIN_WORDS)
            ->sortBy(fn (array $row) => $row[1])
            ->map(fn (array $row) => $row[0])
            ->values();
    }

    /** Gövde metninin kelime sayısı (etiketler hariç). */
    public static function words(Model $model): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($model instanceof Service ? $model->description : $model->content))));

        return $text === '' ? 0 : count(explode(' ', $text));
    }

    /** @return Collection<int, Model> */
    private function candidates(ContentRegistry $registry, int $target, array $types, bool $force, bool $thin = false): Collection
    {
        $types = array_values(array_intersect($types ?: self::TYPES, self::TYPES)) ?: self::TYPES;

        if ($thin) {
            // Metinsiz bölge sayfası skordan bağımsız olarak işlenir.
            $types = array_values(array_intersect($types, ['district', 'province'])) ?: ['district', 'province'];
            $force = true;
        }

        return $registry->reachable()
            ->filter(fn ($c) => in_array($c->contentType, $types, true))
            ->filter(function ($c) use ($target, $force) {
                if ($force) {
                    return true;
                }

                $score = SeoAnalysis::query()
                    ->where('content_type', $c->contentType)
                    ->where('content_id', $c->contentId)
                    ->value('score');

                return $score === null || $score < $target;
            })
            ->map(fn ($c) => match ($c->contentType) {
                'district' => District::with('province')->find($c->contentId),
                'province' => Province::find($c->contentId),
                'brand' => Brand::query()->whereNull('service_id')->find($c->contentId),
                'service' => Service::find($c->contentId),
                default => null,
            })
            ->filter()
            ->when($thin, fn (Collection $c) => $c->filter(fn (Model $m) => self::words($m) < self::THIN_WORDS))
            ->values();
    }

    private function label(Model $model): string
    {
        return match (true) {
            $model instanceof District => $model->name.' / '.$model->province?->name,
            $model instanceof Province => $model->name.' (il)',
            $model instanceof Brand => $model->name.' (marka)',
            $model instanceof Service => $model->title,
            default => class_basename($model),
        };
    }
}
