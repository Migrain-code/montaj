<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Ayarlar';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Yönetici';

    protected static ?string $pluralModelLabel = 'Yöneticiler';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Ad Soyad')->required()->maxLength(100),
            TextInput::make('email')->label('E-posta')->email()->required()->unique(ignoreRecord: true)->maxLength(150),
            TextInput::make('password')
                ->label('Parola')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('Düzenlerken boş bırakılırsa parola değişmez.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ad Soyad')->searchable()->weight('semibold'),
                TextColumn::make('email')->label('E-posta')->searchable(),
                TextColumn::make('created_at')->label('Oluşturulma')->dateTime('d.m.Y')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record) => $record->getKey() === auth()->id()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
