<?php

namespace App\Filament\Resources\QuoteRequests;

use App\Filament\Resources\QuoteRequests\Pages\EditQuoteRequest;
use App\Filament\Resources\QuoteRequests\Pages\ListQuoteRequests;
use App\Filament\Resources\QuoteRequests\Pages\ViewQuoteRequest;
use App\Filament\Resources\QuoteRequests\Schemas\QuoteRequestForm;
use App\Filament\Resources\QuoteRequests\Schemas\QuoteRequestInfolist;
use App\Filament\Resources\QuoteRequests\Tables\QuoteRequestsTable;
use App\Models\QuoteRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class QuoteRequestResource extends Resource
{
    protected static ?string $model = QuoteRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Talepler';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Teklif Talebi';

    protected static ?string $pluralModelLabel = 'Teklif Talepleri';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return QuoteRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuoteRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuoteRequestsTable::configure($table);
    }

    /**
     * Montajcı yalnız kendisine atanan talepleri görür.
     *
     * İlke (QuoteRequestPolicy) tek kayıt erişimini korur; bu kapsam listeyi korur.
     * İkisi birden gereklidir: yalnız listeyi filtrelemek, kayıt kimliğini bilen
     * birinin doğrudan adrese gitmesini engellemez.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        $count = QuoteRequest::query()
            ->visibleTo($user)
            ->when(
                $user?->isInstaller(),
                // Montajcı için "yeni" değil, "üzerine atanmış ve bitmemiş" iş sayısı anlamlıdır.
                fn ($q) => $q->whereNotIn('status', [QuoteRequest::STATUS_COMPLETED, QuoteRequest::STATUS_CANCELLED]),
                fn ($q) => $q->where('status', QuoteRequest::STATUS_NEW),
            )
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuoteRequests::route('/'),
            'view' => ViewQuoteRequest::route('/{record}'),
            'edit' => EditQuoteRequest::route('/{record}/edit'),
        ];
    }
}
