<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSC fırsat avı: kelimeyi ilk 3'e taşıma kampanyası.
 *
 * Kampanya kelimenin kendisinde tutulur (seo_keywords): kelimenin sayfası zaten
 * target_id ile belli, sıralama geçmişi seo_rank_history'de. Yalnız kampanya alanları
 * eklenir; mevcut sütunlara dokunulmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            // null = kampanyada değil | active | won | paused
            $table->string('campaign_status', 20)->nullable()->index()->after('status');
            $table->unsignedTinyInteger('target_position')->default(3)->after('campaign_status');
            // gsc | focus | manual — kelime kampanyaya nereden girdi
            $table->string('campaign_source', 20)->nullable()->after('target_position');
            $table->string('region', 120)->nullable()->after('campaign_source');
            $table->decimal('last_position', 6, 2)->nullable()->after('region');
            $table->decimal('previous_position', 6, 2)->nullable()->after('last_position');
            $table->unsignedInteger('gsc_clicks')->default(0)->after('previous_position');
            $table->unsignedInteger('gsc_impressions')->default(0)->after('gsc_clicks');
            $table->decimal('gsc_ctr', 6, 4)->nullable()->after('gsc_impressions');
            $table->string('ranking_url', 500)->nullable()->after('gsc_ctr');
            $table->timestamp('ranked_at')->nullable()->after('ranking_url');
            $table->timestamp('last_boost_at')->nullable()->after('ranked_at');
        });

        Schema::create('seo_keyword_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            // meta | content | faq | link_rule | index
            $table->string('action', 30);
            // done | failed | reverted
            $table->string('status', 20);
            $table->string('message', 500)->nullable();
            // Geri alma için: işlemden önceki ve sonraki değerler.
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->decimal('rank_before', 6, 2)->nullable();
            $table->string('page_url', 500)->nullable();
            $table->timestamps();

            // page_url'e indeks konmaz: utf8mb4'te 500 karakter ~2 KB eder ve eski MySQL/MariaDB'nin
            // 767 baytlık indeks sınırını aşar. Tablo küçüktür; tarama yeterlidir.
            $table->index(['keyword_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_actions');

        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->dropIndex(['campaign_status']);
            $table->dropColumn([
                'campaign_status', 'target_position', 'campaign_source', 'region',
                'last_position', 'previous_position', 'gsc_clicks', 'gsc_impressions',
                'gsc_ctr', 'ranking_url', 'ranked_at', 'last_boost_at',
            ]);
        });
    }
};
