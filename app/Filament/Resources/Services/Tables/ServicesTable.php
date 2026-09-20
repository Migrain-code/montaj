<?php

namespace App\Filament\Resources\Services\Tables;

use App\Models\Service;
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

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->square()->size(48),
                TextColumn::make('title')->label('Hizmet')->searchable()->sortable()->weight('semibold')
                    ->description(fn (Service $record) => '/'.$record->slug),
                TextColumn::make('short_description')->label('Kısa açıklama')->limit(60)->toggleable(),
                ToggleColumn::make('is_featured')->label('Ana sayfa'),
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
                    ->url(fn (Service $record) => $record->url)
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
