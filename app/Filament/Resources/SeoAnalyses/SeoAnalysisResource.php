<?php

namespace App\Filament\Resources\SeoAnalyses;

use App\Filament\Clusters\SeoMerkezi;
use App\Filament\Resources\SeoAnalyses\Pages\ManageSeoAnalyses;
use App\Models\IndexStatus;
use App\Models\SeoAnalysis;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hangi sayfa Google dizininde, hangisi değil.
 *
 * Liste site haritasındaki TÜM adreslerdir (ilçe, il, marka ve blog sayfaları dahil; index_statuses).
 * Veri gece çalışan "seo:check-index" görevinden gelir; görev günlük URL Inspection
 * kotası (2000) elverdiğince en eski kontrol edilenden başlayarak hepsini sorar.
 *
 * Salt okunur: kayıtlar görevlerin ürünüdür, elle eklenip silinmez.
 */
class SeoAnalysisResource extends Resource
{
    protected static ?string $model = IndexStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static ?string $cluster = SeoMerkezi::class;

    protected static ?string $navigationLabel = 'Index Kontrol';

    protected static ?int $navigationSort = 11;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('analysis'))
            ->columns([
                TextColumn::make('url')->label('Adres')->searchable()->wrap()
                    ->url(fn (IndexStatus $r) => $r->url, true)
                    ->color('primary'),
                TextColumn::make('kind')->label('Tip')->badge()->color('gray')
                    ->state(fn (IndexStatus $r) => $r->kind),
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
                    ->state(fn (IndexStatus $r) => $r->index_detail_label)
                    // Çeviriyle eşleşmeyen aramalar da sonuç versin diye arama ham metinde yapılır.
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('google_index_detail', 'like', "%{$search}%")),
                TextColumn::make('analysis.score')->label('SEO skoru')->badge()->placeholder('—')
                    ->color(fn (IndexStatus $r) => $r->analysis?->score_color),
                TextColumn::make('index_submitted_at')->label('Google\'a bildirildi')->since()->sortable()
                    ->placeholder('—')
                    ->description(fn (IndexStatus $r) => $r->index_submit_error ? 'Hata: '.str($r->index_submit_error)->limit(60) : null)
                    ->color(fn (IndexStatus $r) => $r->index_submit_error ? 'danger' : null),
                TextColumn::make('index_checked_at')->label('Son kontrol')->since()->sortable()
                    ->placeholder('Hiç')
                    ->tooltip(fn (IndexStatus $r) => $r->index_checked_at?->translatedFormat('d F Y H:i')),
            ])
            ->filters([
                SelectFilter::make('google_index_status')->label('Dizin durumu')
                    ->options(SeoAnalysis::INDEX_STATUSES),
                Filter::make('combos')->label('Skoru olmayan adresler (liste sayfaları vb.)')
                    ->query(fn (Builder $query) => $query->whereDoesntHave('analysis')),
                Filter::make('content')->label('Yalnız skorlanan içerikler')
                    ->query(fn (Builder $query) => $query->whereHas('analysis')),
                // Indexing API bildirimi. Başarısız deneme hatayı yazar ama tarihi değiştirmez;
                // bu yüzden "bildirildi" = tarih var ve son denemede hata yok.
                SelectFilter::make('submission')->label('Google\'a bildirim')
                    ->options([
                        'submitted' => 'Bildirildi',
                        'failed' => 'Bildirim hatalı',
                        'never' => 'Hiç bildirilmedi',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'submitted' => $query->whereNotNull('index_submitted_at')->whereNull('index_submit_error'),
                        'failed' => $query->whereNotNull('index_submit_error'),
                        'never' => $query->whereNull('index_submitted_at')->whereNull('index_submit_error'),
                        default => $query,
                    }),
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
                'Dizin durumu Google Search Console\'dan gelir. Bağlantı SEO Merkezi → Ayarlar sekmesinden kurulur; '
                .'ardından gece 03:00\'teki görev sayfaları sorar. Beklemek istemezseniz üstteki düğmeyle hemen çalıştırın.'
            );
    }

    public static function getPages(): array
    {
        return ['index' => ManageSeoAnalyses::route('/')];
    }
}
