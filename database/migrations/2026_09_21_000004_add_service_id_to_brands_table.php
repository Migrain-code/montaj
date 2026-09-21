<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir markanın KENDİ hizmet sayfası varsa (örn. IKEA), ayrıca marka sayfası
 * açmak iki sayfayı aynı sorguda yarıştırır (cannibalization, spec §3.4).
 * Bu alan doluyken marka kendi sayfasını almaz: kartı hizmet sayfasına gider,
 * kelimeleri de o hizmet sayfasına atanır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('slug')->constrained('services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
    }
};
