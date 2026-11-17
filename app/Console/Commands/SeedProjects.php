<?php

namespace App\Console\Commands;

use App\Models\Project;
use Database\Seeders\ProjectSeeder;
use Illuminate\Console\Command;

/**
 * "İşlerimiz" ilk kayıtlarını ekler. Panelden çalıştırılabilsin diye ayrı komuttur:
 * db:seed panelde yasaklıdır (bütün seeder'ları çalıştırabilir).
 *
 * Yalnız eksik kayıtları ekler; var olan işe dokunmaz. Tekrar çalıştırmak güvenlidir.
 */
class SeedProjects extends Command
{
    protected $signature = 'projects:seed';

    protected $description = 'İşlerimiz bölümünün ilk kayıtlarını ekler (var olanlara dokunmaz)';

    public function handle(): int
    {
        $before = Project::query()->count();

        $this->call('db:seed', ['--class' => ProjectSeeder::class, '--force' => true]);

        $this->info((Project::query()->count() - $before).' yeni iş eklendi; toplam '.Project::query()->count().'.');

        return self::SUCCESS;
    }
}
