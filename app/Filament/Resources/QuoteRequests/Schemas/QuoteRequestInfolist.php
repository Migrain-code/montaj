<?php

namespace App\Filament\Resources\QuoteRequests\Schemas;

use App\Models\QuoteRequest;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuoteRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Müşteri')->schema([
                    TextEntry::make('name')->label('Ad Soyad')->weight('semibold'),
                    TextEntry::make('phone')->label('Telefon')->copyable()->url(fn (QuoteRequest $record) => phone_href($record->phone)),
                    TextEntry::make('email')->label('E-posta')->placeholder('-')->copyable(),
                    TextEntry::make('location_label')->label('Bölge')->placeholder('-'),
                    TextEntry::make('service.title')->label('Hizmet')->placeholder('-'),
                    TextEntry::make('preferred_date')->label('Tercih edilen tarih')->date('d.m.Y')->placeholder('-'),
                ])->columns(3)->columnSpanFull(),

                Section::make('Talep')->schema([
                    TextEntry::make('message')->label('Açıklama')->placeholder('Açıklama girilmemiş.')->columnSpanFull(),
                    ViewEntry::make('photos')->label('Fotoğraflar')->view('filament.infolists.quote-photos')->columnSpanFull(),
                ])->columnSpanFull(),

                Section::make('Atama')->schema([
                    TextEntry::make('assignee.name')->label('Montajcı')->placeholder('Henüz atanmadı')
                        ->badge()->color(fn (?string $state) => $state ? 'success' : 'gray'),
                    TextEntry::make('assigner.name')->label('Atayan')->placeholder('-'),
                    TextEntry::make('assigned_at')->label('Atama zamanı')->dateTime('d.m.Y H:i')->placeholder('-'),
                    TextEntry::make('assignment_note')->label('Montajcıya not')->placeholder('-')->columnSpanFull(),
                ])->columns(3)->columnSpanFull(),

                Section::make('Takip')->schema([
                    TextEntry::make('status')
                        ->label('Durum')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => QuoteRequest::STATUSES[$state] ?? $state)
                        ->color(fn (string $state) => QuoteRequest::STATUS_COLORS[$state] ?? 'gray'),
                    IconEntry::make('kvkk_accepted')->label('KVKK onayı')->boolean(),
                    TextEntry::make('created_at')->label('Gönderim')->dateTime('d.m.Y H:i'),
                    TextEntry::make('admin_notes')->label('Notlar')->placeholder('-')->columnSpanFull(),
                ])->columns(3)->columnSpanFull(),

                Section::make('Teknik')->schema([
                    TextEntry::make('source')->label('Kaynak'),
                    TextEntry::make('page_url')->label('Gönderilen sayfa')->placeholder('-')->url(fn (?string $state) => $state)->openUrlInNewTab(),
                    TextEntry::make('ip')->label('IP')->placeholder('-'),
                    TextEntry::make('user_agent')->label('Tarayıcı')->placeholder('-')->limit(80)->columnSpanFull(),
                ])->columns(3)->collapsible()->collapsed()->columnSpanFull(),
            ]);
    }
}
