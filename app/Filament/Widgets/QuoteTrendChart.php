<?php

namespace App\Filament\Widgets;

use App\Models\QuoteRequest;
use App\Support\ChartPalette;
use Illuminate\Support\Carbon;
use Filament\Widgets\ChartWidget;

/**
 * Teklif taleplerinin günlük seyri.
 *
 * TEK SERİ: gösterge kutusu yok, başlık zaten seriyi adlandırıyor.
 * İkinci bir ölçüyü (ör. görüntülenme) aynı grafiğe İKİNCİ EKSENLE eklemeyin —
 * iki ölçek tek grafikte yanıltıcıdır; gerekirse ayrı bir grafik açın.
 */
class QuoteTrendChart extends ChartWidget
{
    protected ?string $heading = 'Teklif talepleri — son 30 gün';

    protected ?string $description = 'Sitenin ana dönüşüm ölçüsü. Form ve iletişim sayfasından gelen talepler.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $start = Carbon::today()->subDays(29);

        $counts = QuoteRequest::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($r) => $r->created_at->toDateString())
            ->map->count();

        $labels = [];
        $values = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i);
            $labels[] = $date->translatedFormat('d M');
            $values[] = $counts->get($date->toDateString(), 0);
        }

        return [
            'datasets' => [[
                'label' => 'Teklif talebi',
                'data' => $values,
                'backgroundColor' => ChartPalette::PRIMARY,
                'hoverBackgroundColor' => ChartPalette::PRIMARY_DARK,
                'borderRadius' => 4,   // veri ucu yuvarlatılır, taban düz kalır
                'borderSkipped' => 'bottom',
                'barPercentage' => 0.72,
                'categoryPercentage' => 0.86,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false], // tek seri → gösterge gereksiz
                'tooltip' => ['displayColors' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['drawBorder' => false],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['maxRotation' => 0, 'autoSkipPadding' => 16],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
