<?php

namespace App\Console\Commands;

use App\Jobs\QueueHeartbeat;
use App\Support\Heartbeat;
use Illuminate\Console\Command;

/**
 * Zamanlayıcı her dakika çalıştırır.
 *
 * Bilerek bir KOMUT olarak yazıldı, kapanış fonksiyonu (Schedule::call) olarak değil:
 * zamanlanmış komutlar ayrı bir süreçte çalışır. Hosting bu süreci başlatmayı
 * engelliyorsa (proc_open kapalı) kapanış fonksiyonu yine çalışır ve panel yanlışlıkla
 * "her şey yolunda" der. Komut olarak çalışınca damga ancak gerçek görevler de
 * çalışabiliyorsa yazılır.
 */
class SystemHeartbeat extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Zamanlayıcı ve kuyruk işçisinin çalıştığını panele bildirir';

    public function handle(): int
    {
        Heartbeat::beat(Heartbeat::SCHEDULER);
        QueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
