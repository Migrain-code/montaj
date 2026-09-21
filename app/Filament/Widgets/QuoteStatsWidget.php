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
        $user = auth()->user();

        // Montajcı için sayılar KENDİ işleri üzerinden hesaplanır.
        $base = fn () => QuoteRequest::query()->visibleTo($user);

        if ($user?->isInstaller()) {
            $open = $base()->whereNotIn('status', [QuoteRequest::STATUS_COMPLETED, QuoteRequest::STATUS_CANCELLED])->count();
            $done = $base()->where('status', QuoteRequest::STATUS_COMPLETED)->count();

            return [
                Stat::make('Açık işim', $open)->description('Tamamlanmayı bekliyor')->color($open > 0 ? 'warning' : 'success'),
                Stat::make('Bu hafta atanan', $base()->where('assigned_at', '>=', now()->startOfWeek())->count())->color('info'),
                Stat::make('Tamamladığım', $done)->color('success'),
                Stat::make('Toplam işim', $base()->count())->color('gray'),
            ];
        }

        $new = $base()->where('status', QuoteRequest::STATUS_NEW)->count();
        $unassigned = $base()->whereNull('assigned_to')
            ->whereNotIn('status', [QuoteRequest::STATUS_COMPLETED, QuoteRequest::STATUS_CANCELLED])->count();
        $week = $base()->where('created_at', '>=', now()->startOfWeek())->count();
        $total = $base()->count();

        return [
            Stat::make('Yeni talepler', $new)
                ->description('Henüz iletişime geçilmedi')
                ->color($new > 0 ? 'warning' : 'success')
                ->url(QuoteRequestResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'new']]])),
            Stat::make('Montajcı bekleyen', $unassigned)
                ->description('Atanmamış, açık talep')
                ->color($unassigned > 0 ? 'danger' : 'success'),
            Stat::make('Bu hafta', $week)->description('Gelen teklif talebi')->color('info'),
            Stat::make('Toplam', $total)->description('Tüm zamanlar')->color('gray'),
        ];
    }
}
