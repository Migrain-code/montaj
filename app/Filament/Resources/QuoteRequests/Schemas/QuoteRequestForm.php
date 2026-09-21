<?php

namespace App\Filament\Resources\QuoteRequests\Schemas;

use App\Models\District;
use App\Models\User;
use App\Models\QuoteRequest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class QuoteRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Takip')->schema([
                    Select::make('status')
                        ->label('Durum')
                        ->options(QuoteRequest::STATUSES)
                        ->required()
                        ->native(false),
                    Textarea::make('admin_notes')
                        ->label('Notlar (müşteri görmez)')
                        ->rows(4)
                        ->helperText('Verilen fiyat, randevu tarihi, özel durumlar vb.'),
                ])->columns(2)->columnSpanFull(),

                Section::make('Atama')
                    ->description('Talebi yürütecek montajcı.')
                    ->schema([
                        Select::make('assigned_to')
                            ->label('Montajcı')
                            ->options(fn () => User::query()->installers()->active()->ordered()->pluck('name', 'id'))
                            ->searchable()
                            ->native(false)
                            // Montajcı kendi atamasını değiştiremez.
                            ->disabled(fn () => ! (auth()->user()?->assignsQuotes() ?? false)),
                        Textarea::make('assignment_note')
                            ->label('Montajcıya not')
                            ->rows(3)
                            ->disabled(fn () => ! (auth()->user()?->assignsQuotes() ?? false)),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Müşteri bilgileri')
                    // Montajcı müşteri verisini değiştiremez; yalnız görür.
                    ->disabled(fn () => auth()->user()?->isInstaller() ?? false)
                    ->schema([
                    TextInput::make('name')->label('Ad Soyad')->required()->maxLength(100),
                    TextInput::make('phone')->label('Telefon')->required()->tel()->maxLength(30),
                    TextInput::make('email')->label('E-posta')->email()->maxLength(150),
                    Select::make('service_id')->label('Hizmet')->relationship('service', 'title')->preload()->searchable(),
                    Select::make('province_id')
                        ->label('İl')
                        ->relationship('province', 'name')
                        ->preload()
                        ->live(),
                    Select::make('district_id')
                        ->label('İlçe')
                        ->options(fn (Get $get) => District::query()
                            ->when($get('province_id'), fn ($q, $id) => $q->where('province_id', $id))
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable(),
                ])->columns(3)->columnSpanFull(),

                Section::make('Talep')
                    ->disabled(fn () => auth()->user()?->isInstaller() ?? false)
                    ->schema([
                    Textarea::make('message')->label('Açıklama')->rows(4)->columnSpanFull(),
                    DatePicker::make('preferred_date')->label('Tercih edilen tarih')->native(false)->displayFormat('d.m.Y'),
                ])->columns(2)->columnSpanFull(),
            ]);
    }
}
