<?php

namespace App\Filament\Pages;

use App\Jobs\BoostKeyword;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordAction;
use App\Models\SeoTarget;
use App\Services\Ai\AiClient;
use App\Services\Seo\OpportunityHunter;
use App\Services\Seo\RankBooster;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;
use UnitEnum;

/**
 * GSC Fırsat Avı: bölge kelimelerini Search Console'dan izler ve ilk 3'e taşır.
 *
 * Kelime → sayfa bağı SeoKeyword.target_id'dir (tek sahip kuralı). İlk 3'e giren
 * kelimeye hiçbir işlem yapılmaz; düşerse kendiliğinden yeniden ele alınır.
 */
class OpportunityHunt extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?string $navigationLabel = 'GSC Fırsat Avı';

    protected static ?string $title = 'GSC Fırsat Avı';

    protected static ?string $slug = 'gsc-firsat-avi';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.opportunity-hunt';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->isStaff());
    }

    private static function canEdit(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Menü rozeti: ilk 3'e girmeyi bekleyen odak bölge kelimeleri.
     *
     * Rozet panelin HER sayfasında çizilir. Kod sunucuya migration'dan önce ulaşırsa sütun
     * henüz yoktur; sorgu çalışsaydı bütün panel 500 verirdi — migration'ı çalıştıracak
     * Sistem Komutları sayfası dahil. Bu yüzden sütun yoksa rozet sessizce gizlenir.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! Schema::hasColumn('seo_keywords', 'campaign_status')) {
            return null;
        }

        $count = SeoKeyword::query()
            ->where('campaign_status', SeoKeyword::CAMPAIGN_ACTIVE)
            ->whereIn('region', (array) config('seo.opportunity.focus_regions', []))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getViewData(): array
    {
        $keywords = SeoKeyword::query()->inCampaign()->get();
        $focus = [];

        foreach ((array) config('seo.opportunity.focus_regions', []) as $region) {
            $own = $keywords->where('region', $region);
            $best = $own->whereNotNull('last_position')->sortBy('last_position')->first();

            $focus[] = [
                'name' => $region,
                'count' => $own->count(),
                'won' => $own->where('campaign_status', SeoKeyword::CAMPAIGN_WON)->count(),
                'best' => $best?->last_position,
                'best_keyword' => $best?->keyword,
            ];
        }

        $measured = $keywords->whereNotNull('last_position');

        return [
            'gscReady' => app(OpportunityHunter::class)->isConfigured(),
            'aiReady' => app(AiClient::class)->isConfigured(),
            'autopilot' => SeoConfig::bool('opportunity_autopilot', true),
            'dailyLimit' => SeoConfig::int('opportunity_daily_limit', 3, 1, 20),
            'cooldown' => SeoConfig::int('opportunity_cooldown_days', 7, 1, 60),
            'target' => SeoConfig::int('opportunity_target_position', 3, 1, 10),
            'focus' => $focus,
            'stats' => [
                'total' => $keywords->count(),
                'won' => $keywords->where('campaign_status', SeoKeyword::CAMPAIGN_WON)->count(),
                'near' => $measured->filter(fn ($k) => $k->last_position > 3 && $k->last_position <= 10)->count(),
                'far' => $measured->filter(fn ($k) => $k->last_position > 10)->count(),
                'unmeasured' => $keywords->whereNull('last_position')->count(),
            ],
            'actions' => SeoKeywordAction::query()->with('keyword:id,keyword')->latest('id')->limit(25)->get(),
            'canEdit' => self::canEdit(),
        ];
    }

    // ---------- üst butonlar ----------

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sıraları çek')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => self::canEdit())
                ->action(function (OpportunityHunter $hunter) {
                    try {
                        $s = $hunter->run();
                    } catch (Throwable $e) {
                        Notification::make()->title('Search Console verisi alınamadı')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    $body = $hunter->isConfigured()
                        ? "{$s['rows']} sorgu okundu. {$s['hunted']} yeni fırsat, {$s['updated']} kelimenin sırası güncellendi, {$s['won']} kelime hedefe girdi."
                        : 'Search Console bağlı değil; yalnız odak bölge kelimeleri takibe alındı ('.$s['seeded'].' yeni).';

                    Notification::make()->title('Fırsat avı tamamlandı')->body($body)->success()->send();
                }),

            Action::make('add')
                ->label('Kelime ekle')
                ->icon('heroicon-o-plus')
                ->visible(fn () => self::canEdit())
                ->schema([
                    TextInput::make('keyword')->label('Anahtar kelime')->required()->maxLength(255)
                        ->placeholder('çerkezköy mobilya montaj'),
                    Select::make('target_id')->label('Bağlanacak sayfa')
                        ->options(fn () => SeoTarget::query()->active()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Boş bırakılırsa kelimedeki bölgenin sayfasına bağlanır.'),
                    TextInput::make('target_position')->label('Hedef sıra')->numeric()->minValue(1)->maxValue(10)->default(3),
                ])
                ->action(function (array $data, OpportunityHunter $hunter) {
                    $text = TurkishText::lower(trim($data['keyword']));
                    $region = $hunter->matchRegion($text);

                    $keyword = SeoKeyword::query()->where('keyword_hash', md5($text))->first()
                        ?? new SeoKeyword(['keyword' => $text, 'keyword_type' => 'COMMERCIAL_VARIANT',
                            'search_intent' => 'transactional', 'priority' => 1, 'note' => 'GSC fırsat avı · elle']);

                    $keyword->fill([
                        'campaign_status' => SeoKeyword::CAMPAIGN_ACTIVE,
                        'campaign_source' => $keyword->campaign_source ?? 'manual',
                        'target_position' => (int) ($data['target_position'] ?: 3),
                        'region' => $region['name'] ?? $keyword->region,
                        'status' => true,
                    ]);

                    $targetId = $data['target_id'] ?: (! $keyword->hasOwner() ? ($region['target_id'] ?? null) : null);

                    if ($targetId) {
                        $keyword->target_id = (int) $targetId;
                    }

                    $keyword->save();

                    Notification::make()->title('Kelime takibe alındı')
                        ->body($keyword->target_id ? null : 'Sayfa bağlanmadı; "Sayfaya bağla" ile seçin.')
                        ->success()->send();
                }),

            Action::make('autopilot')
                ->label(fn () => SeoConfig::bool('opportunity_autopilot', true) ? 'Otomatik: Açık' : 'Otomatik: Kapalı')
                ->icon(fn () => SeoConfig::bool('opportunity_autopilot', true) ? 'heroicon-o-play' : 'heroicon-o-pause')
                ->color(fn () => SeoConfig::bool('opportunity_autopilot', true) ? 'success' : 'gray')
                ->visible(fn () => self::canEdit())
                ->requiresConfirmation()
                ->modalHeading(fn () => SeoConfig::bool('opportunity_autopilot', true) ? 'Otomatik iyileştirmeyi kapat' : 'Otomatik iyileştirmeyi aç')
                ->modalDescription('Açıkken her sabah 05:15\'te günlük sınır kadar kelimenin sayfası yapay zeka ile iyileştirilir. Kapalıyken sıralar yine çekilir, yalnız sayfalara yazılmaz.')
                ->action(function () {
                    $on = ! SeoConfig::bool('opportunity_autopilot', true);
                    SeoConfig::set('opportunity_autopilot', $on);

                    Notification::make()->title($on ? 'Otomatik iyileştirme açıldı' : 'Otomatik iyileştirme kapatıldı')->success()->send();
                }),
        ];
    }

    // ---------- tablo ----------

    public function table(Table $table): Table
    {
        $focus = (array) config('seo.opportunity.focus_regions', []);

        return $table
            ->query(SeoKeyword::query()->inCampaign()->with('target'))
            ->columns([
                TextColumn::make('keyword')->label('Kelime')->searchable()->weight('semibold')->wrap()
                    ->description(fn (SeoKeyword $k) => trim(
                        (in_array($k->region, $focus, true) ? '★ ' : '').($k->region ?? 'Bölgesiz')
                        .' · '.(SeoKeyword::CAMPAIGN_SOURCES[$k->campaign_source] ?? 'Elle')
                    )),

                TextColumn::make('last_position')->label('Sıra')->sortable()
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : '#'.round((float) $state, 1))
                    ->placeholder('Ölçülmedi')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state <= 3.49 => 'success',
                        $state <= 10 => 'warning',
                        default => 'danger',
                    })
                    ->description(function (SeoKeyword $k) {
                        $delta = $k->positionDelta();

                        return match (true) {
                            $delta === null || abs($delta) < 0.1 => 'hedef #'.$k->targetPosition(),
                            $delta > 0 => '↑ '.$delta.' · hedef #'.$k->targetPosition(),
                            default => '↓ '.abs($delta).' · hedef #'.$k->targetPosition(),
                        };
                    }),

                TextColumn::make('target.url')->label('Bağlı sayfa')->wrap()->placeholder('Sayfa bağlı değil')
                    ->description(function (SeoKeyword $k) {
                        // Google başka bir sayfamızı gösteriyorsa bu GÖRÜNÜR olmalı (spec §7.4).
                        if ($k->ranking_url && $k->target?->url && $k->ranking_url !== $k->target->url) {
                            return 'Google şu sayfayı gösteriyor: '.$k->ranking_url;
                        }

                        return $k->target?->name;
                    }),

                TextColumn::make('gsc_impressions')->label('Gösterim')->numeric()->sortable()
                    ->description(fn (SeoKeyword $k) => $k->gsc_clicks.' tık · %'.round(($k->gsc_ctr ?? 0) * 100, 1).' TO'),

                TextColumn::make('campaign_status')->label('Durum')->badge()
                    ->formatStateUsing(fn (string $state) => SeoKeyword::CAMPAIGN_STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        SeoKeyword::CAMPAIGN_WON => 'success',
                        SeoKeyword::CAMPAIGN_ACTIVE => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('steps')->label('Adımlar')
                    ->getStateUsing(function (SeoKeyword $k) {
                        $steps = app(RankBooster::class)->diagnose($k);
                        $actionable = array_filter($steps, fn ($s) => $s['state'] !== 'blocked');
                        $done = array_filter($actionable, fn ($s) => $s['state'] === 'done');

                        return count($done).'/'.count($actionable);
                    })
                    ->badge()->color('gray')
                    ->toggleable(),

                TextColumn::make('last_boost_at')->label('Son iyileştirme')->since()->sortable()
                    ->placeholder('Henüz yok')->toggleable(),
            ])
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw("CASE campaign_status WHEN 'active' THEN 0 WHEN 'won' THEN 1 ELSE 2 END")
                ->orderByRaw('last_position IS NULL')
                ->orderBy('last_position'))
            ->filters([
                SelectFilter::make('campaign_status')->label('Durum')->options(SeoKeyword::CAMPAIGN_STATUSES),
                Filter::make('focus')->label('Yalnız odak bölgeler')
                    ->query(fn (Builder $q) => $q->whereIn('region', $focus)),
                SelectFilter::make('region')->label('Bölge')
                    ->options(fn () => SeoKeyword::query()->inCampaign()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region', 'region')->all()),
            ])
            ->recordActions([
                Action::make('boost')
                    ->label('Yükselt')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('primary')
                    ->visible(fn (SeoKeyword $k) => self::canEdit() && $k->campaign_status === SeoKeyword::CAMPAIGN_ACTIVE)
                    ->modalHeading(fn (SeoKeyword $k) => '"'.$k->keyword.'" için sayfayı iyileştir')
                    ->modalContent(fn (SeoKeyword $k) => view('filament.partials.opportunity-steps', [
                        'steps' => app(RankBooster::class)->diagnose($k),
                        'blocker' => app(RankBooster::class)->blocker($k),
                        'labels' => SeoKeywordAction::LABELS,
                    ]))
                    ->modalSubmitActionLabel('Yapılacak adımları çalıştır')
                    ->action(function (SeoKeyword $k, RankBooster $booster) {
                        if ($reason = $booster->blocker($k)) {
                            Notification::make()->title('Çalıştırılmadı')->body($reason)->warning()->send();

                            return;
                        }

                        BoostKeyword::dispatch($k->getKey());

                        Notification::make()->title('Kuyruğa alındı')
                            ->body('Adımlar arka planda çalışıyor; birkaç dakika içinde aşağıdaki işlem kaydında görünür.')
                            ->success()->send();
                    }),

                ActionGroup::make([
                    Action::make('steps')
                        ->label('Adımları gör')
                        ->icon('heroicon-o-list-bullet')
                        ->modalHeading(fn (SeoKeyword $k) => $k->keyword)
                        ->modalContent(fn (SeoKeyword $k) => view('filament.partials.opportunity-steps', [
                            'steps' => app(RankBooster::class)->diagnose($k),
                            'blocker' => app(RankBooster::class)->blocker($k),
                            'labels' => SeoKeywordAction::LABELS,
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Kapat'),

                    Action::make('bind')
                        ->label('Sayfaya bağla')
                        ->icon('heroicon-o-link')
                        ->visible(fn () => self::canEdit())
                        ->fillForm(fn (SeoKeyword $k) => ['target_id' => $k->target_id])
                        ->schema([
                            Select::make('target_id')->label('Sayfa')->required()->searchable()
                                ->options(fn () => SeoTarget::query()->active()->orderBy('name')->pluck('name', 'id')),
                        ])
                        ->action(function (SeoKeyword $k, array $data) {
                            $k->update(['target_id' => (int) $data['target_id']]);
                            Notification::make()->title('Sayfa bağlandı')->success()->send();
                        }),

                    Action::make('pause')
                        ->label(fn (SeoKeyword $k) => $k->campaign_status === SeoKeyword::CAMPAIGN_PAUSED ? 'Devam ettir' : 'Duraklat')
                        ->icon(fn (SeoKeyword $k) => $k->campaign_status === SeoKeyword::CAMPAIGN_PAUSED ? 'heroicon-o-play' : 'heroicon-o-pause')
                        ->visible(fn () => self::canEdit())
                        ->action(function (SeoKeyword $k) {
                            $k->update(['campaign_status' => $k->campaign_status === SeoKeyword::CAMPAIGN_PAUSED
                                ? ($k->reachedTarget() ? SeoKeyword::CAMPAIGN_WON : SeoKeyword::CAMPAIGN_ACTIVE)
                                : SeoKeyword::CAMPAIGN_PAUSED]);
                        }),

                    Action::make('untrack')
                        ->label('Takipten çıkar')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->visible(fn () => self::canEdit())
                        ->requiresConfirmation()
                        ->modalDescription('Kelime silinmez; yalnız bu ekrandan çıkar. Sayfaya yapılan değişiklikler yerinde kalır, istenirse işlem kaydından geri alınır.')
                        ->action(fn (SeoKeyword $k) => $k->update(['campaign_status' => null])),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('boostMany')
                    ->label('Seçilenleri yükselt')
                    ->icon('heroicon-o-rocket-launch')
                    ->visible(fn () => self::canEdit())
                    ->requiresConfirmation()
                    ->modalDescription('Hedefteki ve duraklatılmış kelimeler atlanır. Her kelime ayrı bir iş olarak kuyruğa alınır.')
                    ->action(function (Collection $records, RankBooster $booster) {
                        $queued = $records->filter(fn (SeoKeyword $k) => $booster->blocker($k) === null)
                            ->each(fn (SeoKeyword $k) => BoostKeyword::dispatch($k->getKey()))
                            ->count();

                        Notification::make()->title($queued.' kelime kuyruğa alındı')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Takipte kelime yok')
            ->emptyStateDescription('"Sıraları çek" ile Search Console\'daki bölge kelimelerini getirin ya da "Kelime ekle" ile elle ekleyin.')
            ->paginated([25, 50, 100]);
    }

    // ---------- işlem kaydı ----------

    public function revertAction(): Action
    {
        return Action::make('revert')
            ->label('Geri al')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->size('sm')
            ->visible(fn () => self::canEdit())
            ->requiresConfirmation()
            ->modalDescription('Sayfa bu işlemden önceki hâline döner. İşlemden sonra sayfa elle değiştirildiyse geri alma reddedilir.')
            ->action(function (array $arguments, RankBooster $booster) {
                $action = SeoKeywordAction::find($arguments['id'] ?? null);

                if (! $action) {
                    return;
                }

                try {
                    $message = $booster->revert($action);
                } catch (Throwable $e) {
                    Notification::make()->title('Geri alınamadı')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Geri alındı')->body($message)->success()->send();
            });
    }
}
