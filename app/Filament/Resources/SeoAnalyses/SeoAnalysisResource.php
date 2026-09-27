<?php

namespace App\Filament\Resources\SeoAnalyses;

use App\Filament\Resources\SeoAnalyses\Pages\ManageSeoAnalyses;
use App\Models\SeoAnalysis;
use App\Services\Seo\ContentRegistry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Hangi sayfa Google dizininde, hangisi değil.
 *
 * Veri, gece çalışan "seo:check-index" görevinden gelir; görev her turda EN ESKİ
 * kontrol edilen 30 sayfayı sorar (Search Console kotası). Bu yüzden sayfa sayısı
 * 30'u aşan sitelerde liste birkaç gün içinde tamamlanır ve "Son kontrol" sütunu
 * hangi satırın taze olduğunu gösterir.
 *
 * Salt okunur: kayıtlar analiz görevinin ürünüdür, elle eklenip silinmez.
 */
class SeoAnalysisResource extends Resource
{
    protected static ?string $model = SeoAnalysis::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Dizin Kaydı';

    protected static ?string $pluralModelLabel = 'Dizin Durumu';

    protected static ?string $recordTitleAttribute = 'url';

    /** Dizine girmemiş sayfa varsa menüde kırmızı rozet; yoksa rozet hiç görünmez. */
    public static function getNavigationBadge(): ?string
    {
        $missing = static::getModel()::query()->where('google_index_status', 'FAIL')->count();

        return $missing > 0 ? (string) $missing : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->label('Adres')->searchable()->wrap()
                    ->url(fn (SeoAnalysis $r) => $r->url, true)
                    ->color('primary'),
                TextColumn::make('content_type')->label('Tip')->badge()->color('gray')->sortable()
                    ->formatStateUsing(fn (?string $state) => ContentRegistry::TYPES[$state] ?? $state),
                TextColumn::make('google_index_status')->label('Dizin durumu')->badge()->sortable()
                    ->placeholder('Kontrol edilmedi')
                    ->formatStateUsing(fn (?string $state) => SeoAnalysis::INDEX_STATUSES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'PASS' => 'success',
                        'FAIL' => 'danger',
                        'PARTIAL', 'NEUTRAL' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('google_index_detail')->label('Google\'ın açıklaması')->wrap()
                    ->placeholder('—')->toggleable()
                    ->state(fn (SeoAnalysis $r) => $r->index_detail_label)
                    // Çeviriyle eşleşmeyen aramalar da sonuç versin diye arama ham metinde yapılır.
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('google_index_detail', 'like', "%{$search}%")),
                TextColumn::make('score')->label('SEO skoru')->badge()->sortable()
                    ->color(fn (SeoAnalysis $r) => $r->score_color),
                TextColumn::make('index_checked_at')->label('Son kontrol')->since()->sortable()
                    ->placeholder('Hiç')
                    ->tooltip(fn (SeoAnalysis $r) => $r->index_checked_at?->translatedFormat('d F Y H:i')),
            ])
            ->filters([
                SelectFilter::make('google_index_status')->label('Dizin durumu')
                    ->options(SeoAnalysis::INDEX_STATUSES),
                SelectFilter::make('content_type')->label('Tip')->options(ContentRegistry::TYPES),
                Filter::make('unchecked')->label('Yalnız hiç kontrol edilmeyenler')
                    ->query(fn (Builder $query) => $query->whereNull('index_checked_at')),
            ])
            // Sorunlu sayfalar üstte: önce dizine girmeyenler, sonra belirsizler, en sonda girenler.
            // CASE ile yazılır; MySQL'in FIELD() işlevi SQLite'ta yok ve testler SQLite'ta koşar.
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw("CASE google_index_status
                    WHEN 'FAIL' THEN 1
                    WHEN 'PARTIAL' THEN 2
                    WHEN 'NEUTRAL' THEN 3
                    WHEN 'UNKNOWN' THEN 4
                    WHEN 'PASS' THEN 5
                    ELSE 6 END")
                ->orderByRaw('index_checked_at IS NULL DESC')
                ->orderByDesc('index_checked_at'))
            ->emptyStateHeading('Henüz dizin kontrolü yapılmadı')
            ->emptyStateDescription(
                'Dizin durumu Google Search Console\'dan gelir. Bağlantı SEO & AI Ayarları sayfasından kurulur; '
                .'ardından gece 03:00\'teki görev sayfaları sorar. Beklemek istemezseniz üstteki düğmeyle hemen çalıştırın.'
            );
    }

    public static function getPages(): array
    {
        return ['index' => ManageSeoAnalyses::route('/')];
    }
}
