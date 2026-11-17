<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')->label('Kapak')->square()->size(64)
                    ->state(fn (Project $record) => $record->cover_url),
                TextColumn::make('title')->label('Başlık')->searchable()->sortable()->weight('semibold')->wrap()
                    ->description(fn (Project $record) => $record->path()),
                TextColumn::make('district.name')->label('İlçe')->placeholder('Seçilmedi')
                    ->color(fn (Project $record) => $record->district_id ? null : 'warning'),
                TextColumn::make('brand.name')->label('Marka')->badge()->color('gray')->placeholder('-'),
                TextColumn::make('service.title')->label('Hizmet')->placeholder('-')->toggleable(),
                TextColumn::make('images')->label('Fotoğraf')
                    ->state(fn (Project $record) => count($record->gallery()))->badge()->color('gray'),
                TextColumn::make('completed_at')->label('Tarih')->date('F Y')->sortable()->placeholder('-'),
                ToggleColumn::make('is_active')->label('Yayında'),
            ])
            ->filters([
                SelectFilter::make('district_id')->label('İlçe')->relationship('district', 'name')->preload(),
                SelectFilter::make('brand_id')->label('Marka')->relationship('brand', 'name')->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('completed_at', 'desc')
            ->emptyStateHeading('Henüz iş eklenmedi')
            ->emptyStateDescription('Tamamladığınız bir montajı fotoğraflarıyla ekleyin; sitede kendi sayfası açılır ve seçtiğiniz ilçe, marka ve hizmet sayfalarında listelenir.');
    }
}
