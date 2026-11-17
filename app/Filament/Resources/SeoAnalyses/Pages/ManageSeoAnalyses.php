<?php

namespace App\Filament\Resources\SeoAnalyses\Pages;

use App\Filament\Pages\SeoDashboard;
use App\Filament\Resources\SeoAnalyses\SeoAnalysisResource;
use App\Services\Admin\CommandRunner;
use App\Services\Google\IndexingService;
use App\Services\Google\SearchConsoleService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Throwable;

class ManageSeoAnalyses extends ManageRecords
{
    protected static string $resource = SeoAnalysisResource::class;

    public function getSubheading(): ?string
    {
        if (! app(SearchConsoleService::class)->isConfigured()) {
            return 'Search Console bağlantısı kurulmadığı için dizin durumu boş kalır. Bağlantıyı SEO Merkezi → Ayarlar sekmesinden kurabilirsiniz.';
        }

        $gsc = app(SearchConsoleService::class);

        return 'Site haritasındaki tüm adreslerin (ilçe, il, hizmet, marka ve blog sayfaları dahil) Google dizininde olup olmadığını gösterir. '
            .'Gece 01:00–07:00 arası saatlik görev hepsini, hiç bakılmamış olandan başlayarak sorar (günlük kota 2000; bugün kalan: '
            .$gsc->inspectionsLeftToday().'). Dizinde olmayanlara her gece 200 adrese kadar bildirim gider.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check')
                ->label('Şimdi kontrol et')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => auth()->user()?->isSuperAdmin() && app(SearchConsoleService::class)->isConfigured())
                ->requiresConfirmation()
                ->modalHeading('Dizin durumunu kontrol et')
                ->modalDescription(fn () => 'Search Console\'a site haritasındaki tüm adresleri sorar (en eski kontrol edilen önce). '
                    .'Bugün kalan sorgu hakkı: '.app(SearchConsoleService::class)->inspectionsLeftToday().'. Bir tur en fazla ~13 dakika sürer; sığmayan adresler gece 01:00–07:00 arası saatlik turlarda sorulur. '
                    .'iş kuyruğa alınır ve kuyruk işçisi çalıştırır.')
                ->modalSubmitActionLabel('Evet, çalıştır')
                ->action(function (CommandRunner $runner) {
                    abort_unless(auth()->user()?->isSuperAdmin(), 403);

                    try {
                        $runner->start('check-index', auth()->user());
                    } catch (Throwable $e) {
                        Notification::make()->title('Çalıştırılamadı')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Dizin kontrolü sıraya alındı')
                        ->body('Kuyruk işçisi çalıştırdıktan sonra bu sayfayı yenileyin.')
                        ->info()->send();
                }),
            Action::make('submitIndex')
                ->label('Dizine ekleme bildirimi gönder')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => auth()->user()?->isSuperAdmin() && app(IndexingService::class)->isConfigured())
                ->requiresConfirmation()
                ->modalHeading('Dizine ekleme bildirimi')
                ->modalDescription(fn () => implode(' ', [
                    'Dizinde olmayan sayfalar Google Indexing API ile bildirilir.',
                    'Bugün kalan kota: '.app(IndexingService::class)->remainingToday().' adres.',
                    'Google bu API\'yi resmî olarak iş ilanı ve canlı yayın sayfaları için açtı;',
                    'diğer sayfalarda bildirimi dikkate almayabilir. Asıl yol site haritası ve Search Console\'dur.',
                ]))
                ->modalSubmitActionLabel('Evet, gönder')
                ->action(function (CommandRunner $runner) {
                    abort_unless(auth()->user()?->isSuperAdmin(), 403);

                    try {
                        $runner->start('submit-index', auth()->user());
                    } catch (Throwable $e) {
                        Notification::make()->title('Çalıştırılamadı')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Bildirim sıraya alındı')
                        ->body('Kuyruk işçisi gönderimi yapacak; sonucu "Google\'a bildirildi" sütununda görürsünüz.')
                        ->info()->send();
                }),
            Action::make('repairIndexing')
                ->label('Bildirimi onar')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('danger')
                ->visible(fn () => auth()->user()?->isSuperAdmin() && app(IndexingService::class)->isConfigured())
                ->requiresConfirmation()
                ->modalHeading('Google bildirimini onar')
                ->modalDescription('Google\'a bildirim göndermeden erişimi sınar. Indexing API kapalıysa açmayı dener; '
                    .'erişim çalışıyorsa eski hataları temizler ve bildirimi kuyruğa alır. Sahiplik gibi elle '
                    .'yapılması gereken bir şey varsa ne yapılacağını söyler.')
                ->modalSubmitActionLabel('Evet, onar')
                ->action(function (IndexingService $indexing, CommandRunner $runner) {
                    abort_unless(auth()->user()?->isSuperAdmin(), 403);

                    try {
                        $result = $indexing->repair();
                    } catch (Throwable $e) {
                        Notification::make()->title('Onarım çalışmadı')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    if ($result['status'] === 'enabled') {
                        Notification::make()->title('API açıldı')->body($result['message'])->success()->persistent()->send();

                        return;
                    }

                    if ($result['status'] === 'manual') {
                        Notification::make()->title('Elle yapılması gereken bir adım var')->body($result['message'])->danger()->persistent()->send();

                        return;
                    }

                    try {
                        $runner->start('submit-index', auth()->user());
                    } catch (Throwable $e) {
                        Notification::make()->title('Erişim tamam ama bildirim başlatılamadı')->body($e->getMessage())->warning()->persistent()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Erişim çalışıyor, bildirim sıraya alındı')
                        ->body(($result['cleared'] ? $result['cleared'].' sayfanın eski hatası temizlendi. ' : '')
                            .'Kuyruk işçisi gönderdikten sonra "Bildirildi" filtresinde görünür.')
                        ->success()->send();
                }),
            Action::make('dashboard')
                ->label('SEO gösterge paneli')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(SeoDashboard::getUrl()),
        ];
    }
}
