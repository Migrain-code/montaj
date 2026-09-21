<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * İçerik gövdelerine gömülmüş YEREL adresleri kök-göreli yapar.
 *
 * İç link motoru eskiden linkleri url() ile tam adres olarak yazıyordu; yerelde
 * çalıştırıldığında gövdelere "http://127.0.0.1:8000/..." gömüldü. Veritabanı
 * canlıya taşındığında bu linkler ziyaretçi ve Google için kırık olur.
 *
 * Yalnız YEREL sunucu adresleri (127.0.0.1, localhost) dönüştürülür; gerçek dış
 * bağlantılara dokunulmaz. Tekrar çalıştırmak güvenlidir.
 */
return new class extends Migration
{
    private const TARGETS = [
        'services' => 'description',
        'brands' => 'content',
        'provinces' => 'content',
        'districts' => 'content',
        'blogs' => 'body_html',
        'pages' => 'content',
    ];

    /*
     * "http://127.0.0.1:8000/tekirdag" → "/tekirdag",  "http://127.0.0.1:8000" → "/".
     * Ana bilgisayar adından sonra "/" ya da tırnak gelmesi şart: "localhost.ornek.com"
     * gibi gerçek bir alan adı yanlışlıkla yakalanmasın.
     */
    private const LOCAL_HREF = '#(href\s*=\s*["\'])https?://(?:127\.0\.0\.1|localhost)(?::\d+)?(?=[/"\'])/?#i';

    public function up(): void
    {
        foreach (self::TARGETS as $table => $column) {
            DB::table($table)
                ->where(fn ($q) => $q->where($column, 'like', '%127.0.0.1%')->orWhere($column, 'like', '%localhost%'))
                ->orderBy('id')
                ->select(['id', $column])
                ->each(function ($row) use ($table, $column) {
                    $fixed = preg_replace(self::LOCAL_HREF, '$1/', (string) $row->{$column});

                    if ($fixed !== null && $fixed !== $row->{$column}) {
                        DB::table($table)->where('id', $row->id)->update([$column => $fixed]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Geri alınmaz: yerel adresi içeriğe geri gömmenin hiçbir faydası yok.
    }
};
