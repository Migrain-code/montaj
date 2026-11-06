<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fırsat avında bir kelime için sayfaya yapılan tek işlem.
 *
 * before/after saklanır: her yazma işlemi geri alınabilir (spec §10.10).
 */
class SeoKeywordAction extends Model
{
    public const DONE = 'done';

    public const FAILED = 'failed';

    public const REVERTED = 'reverted';

    public const LABELS = [
        'meta' => 'Başlık ve açıklama',
        'content' => 'Kelimeye özel bölüm',
        'faq' => 'Soru-cevap',
        'link_rule' => 'İç link kuralı',
        'index' => 'Google\'a bildirim',
    ];

    protected $fillable = [
        'keyword_id', 'action', 'status', 'message', 'before', 'after', 'rank_before', 'page_url',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'rank_before' => 'float'];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }

    public function isRevertible(): bool
    {
        return $this->status === self::DONE && in_array($this->action, ['meta', 'content', 'faq', 'link_rule'], true);
    }
}
