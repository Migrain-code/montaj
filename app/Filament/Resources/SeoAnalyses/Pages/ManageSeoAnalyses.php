<?php

namespace App\Filament\Resources\SeoAnalyses\Pages;

use App\Filament\Pages\SeoDashboard;
use App\Filament\Resources\SeoAnalyses\SeoAnalysisResource;
use App\Services\Admin\CommandRunner;
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
            return 'Search Console bağlantısı kurulmadığı için dizin durumu boş kalır. Bağlantıyı SEO & AI Ayarları sayfasından kurabilirsiniz.';
        }

        return 'Sayfaların Google dizininde olup olmadığını gösterir. Gece çalışan görev her turda en eski '
            .'kontrol edilen 30 sayfayı sorar; "Son kontrol" sütunu satırın ne kadar taze olduğunu söyler.';
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
                ->modalDescription('Search Console\'a sorar. Her çalıştırmada en eski kontrol edilen 30 sayfaya bakılır; '
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
            Action::make('dashboard')
                ->label('SEO gösterge paneli')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(SeoDashboard::getUrl()),
        ];
    }
}
