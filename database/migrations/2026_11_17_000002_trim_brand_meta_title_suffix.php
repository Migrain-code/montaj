<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marka sayfası başlıklarından eski marka adını kaldırır.
 *
 * Seeder başlıkları "Almila Mobilya Montajı | Trakya Montaj Servisi" biçimindeydi; site
 * adı sonuna eklenince "… | Trakya Montaj Servisi | Dost Montaj" oluyordu: uzun ve iki
 * ayrı marka adı. Yalnız bu iki ek silinir; panelden elle yazılmış başka başlığa dokunulmaz.
 */
return new class extends Migration
{
    private const SUFFIXES = [' | Trakya Montaj Servisi', ' | Trakya Montaj'];

    public function up(): void
    {
        foreach (DB::table('brands')->whereNotNull('meta_title')->get(['id', 'meta_title']) as $brand) {
            foreach (self::SUFFIXES as $suffix) {
                if (str_ends_with($brand->meta_title, $suffix)) {
                    DB::table('brands')->where('id', $brand->id)
                        ->update(['meta_title' => substr($brand->meta_title, 0, -strlen($suffix))]);

                    break;
                }
            }
        }
    }

    public function down(): void
    {
        // Geri alınmaz: eski marka adını başlıklara geri yazmanın bir faydası yok.
    }
};
