<?php

namespace App\Models;

use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelime havuzu ve SAHİPLİK (spec §3.1).
 *
 * TEK KURAL: bir kelimenin TEK sahibi olur — ya bir hedef sayfa (target_id) ya bir
 * blog yazısı (owner_blog_id). İkisi birden olamaz.
 */
class SeoKeyword extends Model
{
    // DİKKAT: assignment_status, status (aktif/pasif) ile AYRI bir alandır.
    public const ASSIGN_ACTIVE = 'ACTIVE';

    public const ASSIGN_UNASSIGNED = 'UNASSIGNED';

    public const ASSIGN_HOLD_NO_OWNER = 'HOLD_NO_OWNER';

    public const ASSIGN_HOLD_NO_KEYWORD = 'HOLD_NO_KEYWORD';

    public const ASSIGN_HOLD_CANNIBALIZATION = 'HOLD_CANNIBALIZATION';

    public const ASSIGNMENT_STATUSES = [
        self::ASSIGN_ACTIVE => 'Aktif (sahibi var)',
        self::ASSIGN_UNASSIGNED => 'Atanmamış',
        self::ASSIGN_HOLD_NO_OWNER => 'Beklemede: sahip kayboldu',
        self::ASSIGN_HOLD_NO_KEYWORD => 'Beklemede: sahip kelimeyi hedeflemiyor',
        self::ASSIGN_HOLD_CANNIBALIZATION => 'Beklemede: çakışma',
    ];

    public const ASSIGNMENT_COLORS = [
        self::ASSIGN_ACTIVE => 'success',
        self::ASSIGN_UNASSIGNED => 'gray',
        self::ASSIGN_HOLD_NO_OWNER => 'warning',
        self::ASSIGN_HOLD_NO_KEYWORD => 'warning',
        self::ASSIGN_HOLD_CANNIBALIZATION => 'danger',
    ];

    public const TYPES = [
        'COMMERCIAL_PRIMARY' => 'Ticari — ana',
        'COMMERCIAL_VARIANT' => 'Ticari — varyant',
        'BLOG_PRIMARY' => 'Blog — ana',
        'BLOG_SECONDARY' => 'Blog — ikincil',
        'BLOG_SUPPORTING' => 'Blog — destekleyici',
    ];

    public const INTENTS = [
        'informational' => 'Bilgi arama',
        'commercial' => 'Ticari araştırma',
        'transactional' => 'Satın alma niyeti',
        'navigational' => 'Marka / yönlendirme',
    ];

    /*
     * Fırsat avı kampanyası (GSC Fırsat Avı ekranı).
     * Kampanyadaki kelime ilk target_position sıraya girince "won" olur ve ona
     * artık hiçbir işlem yapılmaz; sıra düşerse kendiliğinden "active"e döner.
     */
    public const CAMPAIGN_ACTIVE = 'active';

    public const CAMPAIGN_WON = 'won';

    public const CAMPAIGN_PAUSED = 'paused';

    public const CAMPAIGN_STATUSES = [
        self::CAMPAIGN_ACTIVE => 'Yükseltiliyor',
        self::CAMPAIGN_WON => 'Hedefte',
        self::CAMPAIGN_PAUSED => 'Duraklatıldı',
    ];

    public const CAMPAIGN_SOURCES = [
        'gsc' => 'Search Console',
        'focus' => 'Odak bölge',
        'manual' => 'Elle eklendi',
    ];

    protected $fillable = [
        'keyword', 'search_intent', 'keyword_type', 'priority', 'search_volume',
        'owner_blog_id', 'target_id', 'assignment_status', 'slot', 'status', 'note', 'last_checked_at',
        'campaign_status', 'target_position', 'campaign_source', 'region',
        'last_position', 'previous_position', 'gsc_clicks', 'gsc_impressions', 'gsc_ctr',
        'ranking_url', 'ranked_at', 'last_boost_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_position' => 'float',
            'previous_position' => 'float',
            'gsc_ctr' => 'float',
            'ranked_at' => 'datetime',
            'last_boost_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $keyword) {
            $keyword->keyword = trim((string) $keyword->keyword);
            $keyword->keyword_hash = md5(TurkishText::lower($keyword->keyword));

            // TEK SAHİP kuralı: hedef atanmışsa blog sahipliği düşer.
            if ($keyword->target_id && $keyword->owner_blog_id) {
                $keyword->owner_blog_id = null;
            }

            $keyword->assignment_status = $keyword->resolveAssignmentStatus();
        });
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(SeoTarget::class, 'target_id');
    }

    public function ownerBlog(): BelongsTo
    {
        return $this->belongsTo(Blog::class, 'owner_blog_id');
    }

    public function rankHistory(): HasMany
    {
        return $this->hasMany(SeoRankHistory::class, 'keyword_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SeoKeywordAction::class, 'keyword_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeInCampaign(Builder $query): Builder
    {
        return $query->whereNotNull('campaign_status');
    }

    public function targetPosition(): int
    {
        return max(1, (int) ($this->target_position ?: 3));
    }

    /** Sıra hedefte mi? Hedefteki kelimeye hiçbir iyileştirme yapılmaz. */
    public function reachedTarget(): bool
    {
        return $this->last_position !== null && round($this->last_position) <= $this->targetPosition();
    }

    /** Pozitif = yükseldi (sıra numarası küçüldü). */
    public function positionDelta(): ?float
    {
        if ($this->last_position === null || $this->previous_position === null) {
            return null;
        }

        return round($this->previous_position - $this->last_position, 1);
    }

    /** Sahipsiz kelimeler — AI konu üreticisine YALNIZ bunlar verilir (spec §7.3). */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('target_id')->whereNull('owner_blog_id');
    }

    public function hasOwner(): bool
    {
        return filled($this->target_id) || filled($this->owner_blog_id);
    }

    /** Bu kelimenin sıralanmasını beklediğimiz adres. */
    public function expectedUrl(): ?string
    {
        if ($this->target_id) {
            return $this->target?->url;
        }

        if ($this->owner_blog_id && class_exists(Blog::class)) {
            return $this->ownerBlog?->path();
        }

        return null;
    }

    private function resolveAssignmentStatus(): string
    {
        // Elle "beklemede: çakışma" işaretlenmişse koru; çakışma tarayıcısı temizler.
        if ($this->assignment_status === self::ASSIGN_HOLD_CANNIBALIZATION && $this->isDirty() === false) {
            return self::ASSIGN_HOLD_CANNIBALIZATION;
        }

        if (! $this->hasOwner()) {
            return self::ASSIGN_UNASSIGNED;
        }

        if ($this->target_id && ! SeoTarget::query()->whereKey($this->target_id)->active()->exists()) {
            return self::ASSIGN_HOLD_NO_OWNER;
        }

        if ($this->owner_blog_id && class_exists(Blog::class)
            && ! Blog::query()->whereKey($this->owner_blog_id)->exists()) {
            return self::ASSIGN_HOLD_NO_OWNER;
        }

        return self::ASSIGN_ACTIVE;
    }

    public function getAssignmentLabelAttribute(): string
    {
        return self::ASSIGNMENT_STATUSES[$this->assignment_status] ?? $this->assignment_status;
    }

    public function getOwnerLabelAttribute(): string
    {
        if ($this->target_id) {
            return 'Hedef: '.($this->target?->name ?? '#'.$this->target_id);
        }

        if ($this->owner_blog_id) {
            return 'Blog: '.($this->ownerBlog?->title ?? '#'.$this->owner_blog_id);
        }

        return 'Sahipsiz';
    }
}
