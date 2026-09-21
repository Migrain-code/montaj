<?php

namespace App\Filament\Pages;

use App\Filament\Support\FormHelpers;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SiteSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Ayarlar';

    protected static ?string $navigationLabel = 'Site Ayarları';

    protected static ?string $title = 'Site Ayarları';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.site-settings';

    /** Site ayarları yalnız süper yöneticide: telefon ve WhatsApp buradan yönetilir. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::query()->pluck('value', 'key')->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Ayarlar')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Genel & İletişim')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('site_name')->label('Site / firma adı')->required()->maxLength(100),
                                    TextInput::make('site_tagline')->label('Slogan')->maxLength(150),
                                    TextInput::make('phone')
                                        ->label('Telefon numarası')
                                        ->placeholder(config('site.phone') ?: '+90 5xx xxx xx xx')
                                        ->helperText('"Hemen Ara" butonlarında kullanılır. Boş bırakılırsa .env dosyasındaki SITE_PHONE kullanılır.')
                                        ->maxLength(30),
                                    TextInput::make('whatsapp')
                                        ->label('WhatsApp numarası')
                                        ->placeholder(config('site.whatsapp') ?: '905xxxxxxxxx')
                                        ->helperText('Ülke koduyla, boşluksuz: 905xxxxxxxxx. Boş bırakılırsa .env dosyasındaki SITE_WHATSAPP kullanılır.')
                                        ->maxLength(30),
                                    TextInput::make('email')->label('E-posta adresi')->email()->maxLength(150)
                                        ->placeholder(config('site.email') ?: 'info@ornek.com'),
                                    TextInput::make('notification_email')
                                        ->label('Talep bildirimi e-postası')
                                        ->email()
                                        ->maxLength(150)
                                        ->helperText('Yeni teklif talepleri bu adrese e-posta ile bildirilir (MAIL ayarları .env dosyasında yapılmalıdır).'),
                                    TextInput::make('address')->label('Adres / merkez')->maxLength(200),
                                    TextInput::make('working_hours')->label('Çalışma saatleri')->maxLength(100),
                                    TextInput::make('service_area_text')->label('Hizmet bölgesi (kısa metin)')->maxLength(150)->columnSpanFull(),
                                    Textarea::make('whatsapp_message')
                                        ->label('WhatsApp hazır mesajı')
                                        ->rows(2)
                                        ->helperText('{bolge} yer tutucusu bulunulan il/ilçe ile, {hizmet} ise hizmet adıyla değiştirilir.')
                                        ->columnSpanFull(),
                                ]),
                            ]),

                        Tab::make('Ana Sayfa')
                            ->icon('heroicon-o-home')
                            ->schema([
                                Section::make('Hero alanı')->schema([
                                    TextInput::make('hero_badge')->label('Üst etiket')->maxLength(100),
                                    TextInput::make('hero_title')->label('Başlık (H1)')->required()->maxLength(120),
                                    Textarea::make('hero_subtitle')->label('Alt başlık')->rows(2)->maxLength(300),
                                    Textarea::make('hero_bullets')->label('Madde işaretleri')->rows(2)->helperText('Her satıra bir madde.'),
                                    FormHelpers::imageUpload('hero_image', 'site', 'Hero görseli')->columnSpanFull(),
                                ])->columns(2),

                                Section::make('Hakkımızda bölümü')->schema([
                                    TextInput::make('about_subtitle')->label('Üst başlık')->maxLength(100),
                                    TextInput::make('about_title')->label('Başlık')->maxLength(150),
                                    RichEditor::make('about_text')->label('Metin')->columnSpanFull(),
                                    FormHelpers::imageUpload('about_image', 'site', 'Büyük görsel'),
                                    FormHelpers::imageUpload('about_image_2', 'site', 'Küçük görsel'),
                                ])->columns(2)->collapsible(),

                                Section::make('Sayaçlar')
                                    ->description('Doğrulanabilir rakamlar kullanın. Boş bırakılan sayaç gösterilmez.')
                                    ->schema(collect(range(1, 4))->flatMap(fn (int $i) => [
                                        TextInput::make("stat_{$i}_value")->label("Sayaç {$i} değer")->maxLength(20),
                                        TextInput::make("stat_{$i}_label")->label("Sayaç {$i} etiket")->maxLength(60),
                                    ])->all())
                                    ->columns(4)->collapsible()->collapsed(),

                                Section::make('Süreç bölümü')->schema([
                                    TextInput::make('process_subtitle')->label('Üst başlık')->maxLength(100),
                                    TextInput::make('process_title')->label('Başlık')->maxLength(150),
                                    FormHelpers::imageUpload('process_image', 'site', 'Görsel')->columnSpanFull(),
                                ])->columns(2)->collapsible()->collapsed(),

                                Section::make('Çağrı (CTA) bandı')->schema([
                                    TextInput::make('cta_title')->label('Başlık')->maxLength(150),
                                    TextInput::make('cta_text')->label('Metin')->maxLength(250),
                                    FormHelpers::imageUpload('cta_image', 'site', 'Arka plan görseli'),
                                    FormHelpers::imageUpload('banner_image', 'site', 'İç sayfa başlık arka planı'),
                                ])->columns(2)->collapsible()->collapsed(),
                            ]),

                        Tab::make('Hakkımızda Sayfası')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                RichEditor::make('about_page_content')->label('Sayfa içeriği (/hakkimizda)')->columnSpanFull(),
                            ]),

                        Tab::make('SEO & Analitik')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                TextInput::make('meta_title')->label('Ana sayfa meta başlık')->maxLength(70),
                                Textarea::make('meta_description')->label('Ana sayfa meta açıklama')->rows(3)->maxLength(320),
                                TextInput::make('google_analytics_id')->label('Google Analytics ölçüm kimliği')->placeholder('G-XXXXXXXXXX')->maxLength(30),
                                TextInput::make('google_site_verification')->label('Google Search Console doğrulama kodu')->maxLength(100),
                            ]),

                        Tab::make('Sosyal Medya & Harita')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                TextInput::make('facebook_url')->label('Facebook')->url()->maxLength(255),
                                TextInput::make('instagram_url')->label('Instagram')->url()->maxLength(255),
                                TextInput::make('youtube_url')->label('YouTube')->url()->maxLength(255),
                                Textarea::make('map_embed')
                                    ->label('Google Haritalar iframe kodu')
                                    ->rows(4)
                                    ->helperText('Google Haritalar > Paylaş > Harita yerleştir bölümündeki <iframe> kodunu yapıştırın.'),
                                Textarea::make('footer_text')->label('Footer kısa metni')->rows(2)->maxLength(300),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state as $key => $value) {
            Setting::set($key, is_array($value) ? json_encode($value) : $value);
        }

        Setting::flush();

        Notification::make()
            ->title('Site ayarları kaydedildi')
            ->success()
            ->send();
    }
}
