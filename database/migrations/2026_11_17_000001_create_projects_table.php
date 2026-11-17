<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "İşlerimiz": tamamlanan montaj işleri. Her işin kendi sayfası vardır (/islerimiz/{slug})
 * ve bağlı olduğu ilçe, marka ve hizmet sayfalarında listelenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 300)->nullable();      // liste kartı ve meta açıklama
            $table->longText('body')->nullable();             // işin anlatımı (HTML)
            $table->json('items')->nullable();                // kurulan ürünler: ["Gardırop", "Kitaplık"]
            $table->json('images')->nullable();               // [{path, kind: before|after|detail, alt, caption}]
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->date('completed_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
