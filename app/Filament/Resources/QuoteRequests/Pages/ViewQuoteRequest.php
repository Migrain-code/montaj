<?php

namespace App\Filament\Resources\QuoteRequests\Pages;

use App\Filament\Resources\QuoteRequests\QuoteRequestResource;
use App\Models\QuoteRequest;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQuoteRequest extends ViewRecord
{
    protected static string $resource = QuoteRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('whatsapp')
                ->label('WhatsApp\'tan yaz')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn (QuoteRequest $record) => 'https://wa.me/'.ltrim(phone_digits($record->phone), '+'))
                ->openUrlInNewTab(),
            Action::make('call')
                ->label('Ara')
                ->icon('heroicon-o-phone')
                ->url(fn (QuoteRequest $record) => phone_href($record->phone)),
            EditAction::make(),
        ];
    }
}
