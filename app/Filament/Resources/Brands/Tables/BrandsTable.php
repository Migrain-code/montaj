<?php

namespace App\Filament\Resources\Brands\Tables;

use App\Models\Brand;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')->label('')->disk('public')->size(48),
                TextColumn::make('name')->label('Marka')->searchable()->sortable()->weight('semibold')
                    ->description(fn (Brand $record) => $record->path()),
                TextColumn::make('description')->label('Kısa açıklama')->limit(60)->toggleable(),
                TextColumn::make('products')->label('Ürün')->badge()->limitList(2)->toggleable(),
                ToggleColumn::make('is_featured')->label('Öne çıkan'),
                ToggleColumn::make('is_active')->label('Yayında'),
                TextColumn::make('sort_order')->label('Sıra')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Yayın durumu'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Sitede gör')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Brand $record) => $record->url)
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
