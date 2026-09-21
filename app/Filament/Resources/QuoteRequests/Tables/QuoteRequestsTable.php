<?php

namespace App\Filament\Resources\QuoteRequests\Tables;

use App\Models\QuoteRequest;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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
                TextColumn::make('assignee.name')->label('Montajcı')
                    ->placeholder('atanmadı')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'gray')
                    ->description(fn (QuoteRequest $record) => $record->assigned_at?->format('d.m.Y H:i')),
                SelectColumn::make('status')->label('Durum')->options(QuoteRequest::STATUSES)->selectablePlaceholder(false),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(QuoteRequest::STATUSES),
                SelectFilter::make('province_id')->label('İl')->relationship('province', 'name')->preload(),
                SelectFilter::make('service_id')->label('Hizmet')->relationship('service', 'title')->preload(),
                SelectFilter::make('assigned_to')
                    ->label('Montajcı')
                    ->options(fn () => User::query()->installers()->active()->ordered()->pluck('name', 'id'))
                    ->placeholder('Hepsi')
                    // Montajcı zaten yalnız kendi kayıtlarını görür; filtre ona gereksiz.
                    ->visible(fn () => ! auth()->user()?->isInstaller()),
                \Filament\Tables\Filters\Filter::make('unassigned')
                    ->label('Atanmamış talepler')
                    ->query(fn ($query) => $query->whereNull('assigned_to'))
                    ->visible(fn () => ! auth()->user()?->isInstaller()),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label(fn (QuoteRequest $record) => $record->assigned_to ? 'Montajcıyı değiştir' : 'Montajcıya ata')
                    ->icon('heroicon-o-user-plus')
                    ->color(fn (QuoteRequest $record) => $record->assigned_to ? 'gray' : 'primary')
                    // Yalnız süper yönetici ve müşteri temsilcisi atar.
                    ->visible(fn (QuoteRequest $record) => auth()->user()?->can('assign', $record) ?? false)
                    ->schema([
                        Select::make('assigned_to')
                            ->label('Montajcı')
                            ->options(fn () => User::query()->installers()->active()->ordered()
                                ->get()
                                ->mapWithKeys(fn (User $u) => [
                                    $u->id => $u->name.' — '.$u->assignedQuotes()
                                        ->whereNotIn('status', [QuoteRequest::STATUS_COMPLETED, QuoteRequest::STATUS_CANCELLED])
                                        ->count().' açık iş',
                                ]))
                            ->required()
                            ->native(false)
                            ->helperText('Yanındaki sayı, o montajcının bitmemiş iş sayısıdır.'),
                        Textarea::make('assignment_note')
                            ->label('Montajcıya not')
                            ->rows(3)
                            ->placeholder('Adres tarifi, kat/asansör durumu, özel talepler...'),
                    ])
                    ->modalHeading('Talebi montajcıya ata')
                    ->modalSubmitActionLabel('Ata')
                    ->fillForm(fn (QuoteRequest $record) => [
                        'assigned_to' => $record->assigned_to,
                        'assignment_note' => $record->assignment_note,
                    ])
                    ->action(function (QuoteRequest $record, array $data) {
                        $installer = User::findOrFail($data['assigned_to']);
                        $record->assignTo($installer, auth()->user(), $data['assignment_note'] ?? null);

                        Notification::make()
                            ->title('Talep atandı')
                            ->body($record->name.' → '.$installer->name)
                            ->success()->send();
                    }),
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
                    BulkAction::make('bulk_assign')
                        ->label('Seçilenleri montajcıya ata')
                        ->icon('heroicon-o-user-plus')
                        ->color('primary')
                        ->visible(fn () => auth()->user()?->assignsQuotes() ?? false)
                        ->schema([
                            Select::make('assigned_to')->label('Montajcı')
                                ->options(fn () => User::query()->installers()->active()->ordered()->pluck('name', 'id'))
                                ->required()->native(false),
                            Textarea::make('assignment_note')->label('Ortak not')->rows(2),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $installer = User::findOrFail($data['assigned_to']);

                            foreach ($records as $record) {
                                $record->assignTo($installer, auth()->user(), $data['assignment_note'] ?? null);
                            }

                            Notification::make()
                                ->title($records->count().' talep atandı')
                                ->body('Montajcı: '.$installer->name)
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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
