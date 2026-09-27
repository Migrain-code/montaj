<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoAnalysis extends Model
{
    public const INDEX_STATUSES = [
        'PASS' => 'İndekslendi',
        'PARTIAL' => 'Kısmen',
        'FAIL' => 'İndekslenmedi',
        'NEUTRAL' => 'Belirsiz',
        'UNKNOWN' => 'Bilinmiyor',
    ];

    /**
     * Search Console'un İngilizce durum metinleri.
     *
     * API bunları çeviriyle değil sabit metinle döndürür; panelin geri kalanı Türkçe
     * olduğu için burada karşılanır. Listede olmayan bir metin olduğu gibi gösterilir.
     */
    public const INDEX_DETAILS = [
        'Submitted and indexed' => 'Gönderildi ve dizine eklendi',
        'Indexed, not submitted in sitemap' => 'Dizine eklendi, site haritasında yok',
        'Crawled - currently not indexed' => 'Tarandı, henüz dizine eklenmedi',
        'Discovered - currently not indexed' => 'Bulundu, henüz taranmadı',
        'URL is unknown to Google' => 'Google bu adresi hiç görmemiş',
        'Page with redirect' => 'Sayfa yönlendirme yapıyor',
        'Not found (404)' => 'Sayfa bulunamadı (404)',
        'Soft 404' => 'Boş sayfa (yumuşak 404)',
        'Server error (5xx)' => 'Sunucu hatası (5xx)',
        'Blocked by robots.txt' => 'robots.txt engelliyor',
        'Blocked due to unauthorized request (401)' => 'Yetkisiz istek nedeniyle engellendi (401)',
        'Blocked due to access forbidden (403)' => 'Erişim yasak (403)',
        'Excluded by ‘noindex’ tag' => '“noindex” etiketi dizine eklenmesini engelliyor',
        'Alternate page with proper canonical tag' => 'Asıl sayfası başka (canonical)',
        'Duplicate without user-selected canonical' => 'Kopya içerik; asıl sayfa seçilmemiş',
        'Duplicate, Google chose different canonical than user' => 'Kopya içerik; Google başka sayfayı asıl saydı',
        'ALLOWED' => 'robots.txt izin veriyor',
        'DISALLOWED' => 'robots.txt engelliyor',
        'INDEXING_ALLOWED' => 'Dizine eklenmesine izin var',
        'BLOCKED_BY_META_TAG' => 'Sayfadaki etiket engelliyor',
        'BLOCKED_BY_HTTP_HEADER' => 'HTTP başlığı engelliyor',
        'BLOCKED_BY_ROBOTS_TXT' => 'robots.txt engelliyor',
    ];

    protected $fillable = [
        'content_type', 'content_id', 'url', 'score', 'scores', 'issues', 'analyzed_at',
        'google_index_status', 'google_index_detail', 'index_checked_at',
        'index_submitted_at', 'index_submit_count', 'index_submit_error',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'issues' => 'array',
            'analyzed_at' => 'datetime',
            'index_checked_at' => 'datetime',
            'index_submitted_at' => 'datetime',
        ];
    }

    public function content(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'content_type', 'content_id');
    }

    public function scopeBelowTarget(Builder $query): Builder
    {
        return $query->where('score', '<', \App\Support\SeoConfig::int('score_target', 80, 1, 100));
    }

    public function getScoreColorAttribute(): string
    {
        return match (true) {
            $this->score >= 80 => 'success',
            $this->score >= 60 => 'warning',
            default => 'danger',
        };
    }

    /** Google'ın açıklaması Türkçeleştirilmiş hâliyle; bilinmeyen parçalar olduğu gibi kalır. */
    public function getIndexDetailLabelAttribute(): ?string
    {
        if (blank($this->google_index_detail)) {
            return null;
        }

        return collect(explode(' · ', $this->google_index_detail))
            ->map(fn (string $part) => self::INDEX_DETAILS[trim($part)] ?? trim($part))
            ->unique()
            ->implode(' · ');
    }

    public function getIssueCountAttribute(): int
    {
        return count($this->issues ?? []);
    }
}
