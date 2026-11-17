<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Google dizin durumu adres başına tutulur.
 *
 * Eskiden yalnız seo_analyses satırları (skorlanan içerikler) takip ediliyordu; site
 * haritasındaki diğer adresler (liste sayfaları vb.) ne kontrol ediliyor ne bildiriliyordu.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kardeş sitede ilk deneme "key too long" ile yarıda kalmıştı: Laravel tabloyu oluşturup
        // benzersiz indeksi AYRI komutla ekliyor, tablo indekssiz ve boş kalabiliyor. Temizle.
        if (Schema::hasTable('index_statuses') && DB::table('index_statuses')->count() === 0) {
            Schema::drop('index_statuses');
        }

        Schema::create('index_statuses', function (Blueprint $table) {
            $table->id();
            // Sunucuda indeks sınırı 1000 bayt (utf8mb4'te 250 karakter); 500 karakterlik
            // adres doğrudan indekslenemez. Benzersizlik adresin sha1 özetiyle sağlanır.
            $table->string('url', 500);
            $table->char('url_hash', 40)->unique();
            $table->string('google_index_status', 30)->nullable()->index();
            $table->text('google_index_detail')->nullable();
            $table->timestamp('index_checked_at')->nullable()->index();
            $table->timestamp('index_submitted_at')->nullable()->index();
            $table->unsignedSmallInteger('index_submit_count')->default(0);
            $table->string('index_submit_error', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_statuses');
    }
};
