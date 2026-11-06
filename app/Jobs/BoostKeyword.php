<?php

namespace App\Jobs;

use App\Models\SeoKeyword;
use App\Services\Seo\RankBooster;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Panelden "Yükselt"e basılan kelimenin sayfasını iyileştirir.
 *
 * Kuyrukta koşar: üç AI çağrısı birkaç dakika sürebilir, hostingde web isteği ise
 * 30-120 saniyede kesilir. Tekrar deneme yok: biten adımlar kayıtlı kalır, yeniden
 * çalıştırıldığında kaldığı yerden devam eder; AI kredisi boşa harcanmaz.
 */
class BoostKeyword implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $keywordId) {}

    /** Aynı kelime kuyrukta iki kez beklemesin (çift tıklama). */
    public function uniqueId(): string
    {
        return (string) $this->keywordId;
    }

    public function handle(RankBooster $booster): void
    {
        if ($keyword = SeoKeyword::find($this->keywordId)) {
            $booster->boost($keyword);
        }
    }
}
