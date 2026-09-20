<?php

namespace App\Filament\Resources\QuoteRequests\Tables;

use App\Models\QuoteRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class QuoteRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->since()->dateTimeTooltip('d.m.Y H:i')->sortable(),
                TextColumn::make('name')->label('Ad Soyad')->searchable()->weight('semibold'),
                TextColumn::make('phone')->label('Telefon')->searchable()->copyable()->copyMessage('Kopyalandı'),
                TextColumn::make('province.name')->label('İl')->placeholder('-')->toggleable(),
                TextColumn::make('district.name')->label('İlçe')->placeholder('-'),
                TextColumn::make('service.title')->label('Hizmet')->placeholder('-')->limit(25),
                TextColumn::make('photos')->label('Foto')
                    ->getStateUsing(fn (QuoteRequest $record) => count($record->photos ?? []))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'info' : 'gray'),
                SelectColumn::make('status')->label('Durum')->options(QuoteRequest::STATUSES)->selectablePlaceholder(false),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(QuoteRequest::STATUSES),
                SelectFilter::make('province_id')->label('İl')->relationship('province', 'name')->preload(),
                SelectFilter::make('service_id')->label('Hizmet')->relationship('service', 'title')->preload(),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (QuoteRequest $record) => 'https://wa.me/'.ltrim(phone_digits($record->phone), '+'))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_contacted')
                        ->label('İletişime geçildi olarak işaretle')
                        ->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each->update(['status' => QuoteRequest::STATUS_CONTACTED]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }
}
