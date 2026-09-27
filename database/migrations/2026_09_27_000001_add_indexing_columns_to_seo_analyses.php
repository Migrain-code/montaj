<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Indexing API gönderimlerinin kaydı.
 *
 * Aynı adres günlerce üst üste gönderilmesin ve günlük 200'lük kota takip
 * edilebilsin diye gönderim zamanı, sayısı ve son hata saklanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_analyses', function (Blueprint $table) {
            $table->timestamp('index_submitted_at')->nullable()->index();
            $table->unsignedSmallInteger('index_submit_count')->default(0);
            $table->string('index_submit_error', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_analyses', function (Blueprint $table) {
            $table->dropColumn(['index_submitted_at', 'index_submit_count', 'index_submit_error']);
        });
    }
};
