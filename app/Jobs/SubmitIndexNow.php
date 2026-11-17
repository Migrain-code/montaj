<?php

namespace App\Jobs;

use App\Services\Seo\IndexNow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Tek adresi kuyrukta IndexNow'a bildirir; panelde "Kaydet"e basan kişi dış servis
 * yanıtını beklemez. Kuyruk işçisi yoksa bildirim yapılmaz, sayfa yine yayındadır.
 */
class SubmitIndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /** @param  array<int, string>  $urls */
    public function __construct(public array $urls) {}

    public function handle(IndexNow $indexNow): void
    {
        $indexNow->submit($this->urls);
    }
}
