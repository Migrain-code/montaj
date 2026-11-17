<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Support\FormHelpers;
use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('İş')
                    ->description('Başlıkta ne kurulduğu ve varsa markası geçsin. Örn: "Almila genç odası montajı". İlçe seçerseniz Google başlığına kendiliğinden eklenir.')
                    ->schema([
                        Grid::make(2)->schema(FormHelpers::titleAndSlug()),
                        Textarea::make('summary')->label('Kısa özet')->rows(2)->maxLength(300)
                            ->helperText('Liste kartında ve Google açıklamasında görünür. Bir-iki cümle.'),
                        TagsInput::make('items')->label('Bu işte kurulanlar')
                            ->placeholder('Ürün yazıp Enter\'a basın (Gardırop, Kitaplık…)'),
                        RichEditor::make('body')->label('İşin anlatımı')
                            ->helperText('Birkaç cümle gerçek bilgi: ne geldi, ne kuruldu, varsa özel bir durum. Yalnız fotoğraftan oluşan sayfayı Google zayıf sayar.'),
                    ])->columnSpanFull(),

                Section::make('Bağlantılar')
                    ->description('İş, seçilen ilçe, marka ve hizmet sayfalarında "yaptığımız işler" olarak listelenir.')
                    ->schema([
                        Select::make('district_id')->label('İlçe')
                            ->relationship('district', 'name')->preload()->searchable()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.' / '.$record->province?->name)
                            ->helperText('İşin yapıldığı ilçe. Yerel aramada en önemli bilgi budur.'),
                        Select::make('brand_id')->label('Marka')->relationship('brand', 'name')->preload()->searchable(),
                        Select::make('service_id')->label('Hizmet')->relationship('service', 'title')->preload()->searchable(),
                        DatePicker::make('completed_at')->label('Tarih')->native(false)->displayFormat('d.m.Y')
                            ->helperText('Sitede yalnız ay ve yıl görünür.'),
                    ])->columns(2)->columnSpanFull(),

                Section::make('Fotoğraflar')
                    ->description('Sıralama sitede de aynıdır. Kapak, ilk "Sonrası" fotoğrafıdır. Öncesi ve sonrası birlikte varsa sayfada yan yana gösterilir.')
                    ->schema([
                        Repeater::make('images')->hiddenLabel()
                            ->schema([
                                FormHelpers::imageUpload('path', 'projects', 'Fotoğraf')->required()->columnSpanFull(),
                                Select::make('kind')->label('Tür')->options(Project::KINDS)->default('after')->required(),
                                TextInput::make('alt')->label('ALT metni')->maxLength(150)
                                    ->helperText('Fotoğrafta ne görünüyor? Boşsa iş başlığı kullanılır.'),
                                TextInput::make('caption')->label('Alt yazı (isteğe bağlı)')->maxLength(150),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => (Project::KINDS[$state['kind'] ?? ''] ?? 'Fotoğraf').(filled($state['alt'] ?? null) ? ' · '.$state['alt'] : ''))
                            ->addActionLabel('Fotoğraf ekle')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->defaultItems(0),
                    ])->columnSpanFull(),

                FormHelpers::seoSection(),

                Section::make('Yayın')->schema([
                    Toggle::make('is_active')->label('Yayında')->default(true),
                    TextInput::make('sort_order')->label('Sıra')->numeric()->default(0)
                        ->helperText('Aynı tarihli işlerde küçük sayı önce.'),
                ])->columns(2)->columnSpanFull(),
            ]);
    }
}
