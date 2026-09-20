<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\QuoteRequests\QuoteRequestResource;
use App\Models\QuoteRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuoteStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $new = QuoteRequest::query()->where('status', QuoteRequest::STATUS_NEW)->count();
        $week = QuoteRequest::query()->where('created_at', '>=', now()->startOfWeek())->count();
        $month = QuoteRequest::query()->where('created_at', '>=', now()->startOfMonth())->count();
        $total = QuoteRequest::query()->count();

        return [
            Stat::make('Yeni talepler', $new)
                ->description('Henüz iletişime geçilmedi')
                ->color($new > 0 ? 'warning' : 'success')
                ->url(QuoteRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'new']]])),
            Stat::make('Bu hafta', $week)->description('Gelen teklif talebi')->color('info'),
            Stat::make('Bu ay', $month)->description('Gelen teklif talebi')->color('primary'),
            Stat::make('Toplam', $total)->description('Tüm zamanlar')->color('gray'),
        ];
    }
}
