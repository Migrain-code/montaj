<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Filament\Support\FormHelpers;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Marka')
                    ->tabs([
                        Tab::make('İçerik')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(2)->schema(FormHelpers::titleAndSlug('name', 'Marka adı')),
                                Textarea::make('description')
                                    ->label('Kısa açıklama')
                                    ->rows(2)
                                    ->maxLength(200)
                                    ->helperText('Marka kartında ve sayfa başlığının altında görünür.')
                                    ->columnSpanFull(),
                                FormHelpers::imageUpload('logo', 'brands', 'Marka logosu')
                                    ->helperText('İsteğe bağlı. Boş bırakılırsa kartta marka adı yazı olarak gösterilir. Logoyu yalnızca kullanma hakkınız varsa yükleyin.')
                                    ->columnSpanFull(),
                                RichEditor::make('content')
                                    ->label('Sayfa içeriği')
                                    ->helperText('Markanın kendisini değil, o markanın ürünlerini MONTE EDERKEN yaptığınız işi anlatın.')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Detaylar')
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                FormHelpers::simpleList('products', 'Montajını yaptığımız ürünler'),
                                FormHelpers::faqRepeater('faqs', 'Sık sorulan sorular'),
                            ]),

                        Tab::make('SEO & Yayın')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                FormHelpers::seoSection(),
                                Section::make('Yayın')->schema([
                                    Toggle::make('is_active')->label('Yayında')->default(true),
                                    Toggle::make('is_featured')->label('Öne çıkar')->default(false),
                                    TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                                ])->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
