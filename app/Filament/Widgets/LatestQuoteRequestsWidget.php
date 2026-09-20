<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\QuoteRequests\QuoteRequestResource;
use App\Models\QuoteRequest;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestQuoteRequestsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Son Teklif Talepleri';

    public function table(Table $table): Table
    {
        return $table
            ->query(QuoteRequest::query()->with(['province', 'district', 'service'])->latest())
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->since()->dateTimeTooltip('d.m.Y H:i'),
                TextColumn::make('name')->label('Ad Soyad')->weight('semibold'),
                TextColumn::make('phone')->label('Telefon')->copyable(),
                TextColumn::make('location_label')->label('Bölge'),
                TextColumn::make('service.title')->label('Hizmet')->placeholder('-'),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => QuoteRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => QuoteRequest::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('İncele')
                    ->icon('heroicon-o-eye')
                    ->url(fn (QuoteRequest $record) => QuoteRequestResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
