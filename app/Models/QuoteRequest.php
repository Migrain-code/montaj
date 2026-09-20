<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequest extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_NEW => 'Yeni',
        self::STATUS_CONTACTED => 'İletişime Geçildi',
        self::STATUS_SCHEDULED => 'Randevu Verildi',
        self::STATUS_COMPLETED => 'Tamamlandı',
        self::STATUS_CANCELLED => 'İptal',
    ];

    public const STATUS_COLORS = [
        self::STATUS_NEW => 'warning',
        self::STATUS_CONTACTED => 'info',
        self::STATUS_SCHEDULED => 'primary',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_CANCELLED => 'danger',
    ];

    protected $fillable = [
        'name', 'phone', 'email', 'province_id', 'district_id', 'service_id', 'message',
        'photos', 'preferred_date', 'kvkk_accepted', 'status', 'admin_notes', 'source',
        'page_url', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'preferred_date' => 'date',
            'kvkk_accepted' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getLocationLabelAttribute(): string
    {
        return collect([$this->district?->name, $this->province?->name])->filter()->implode(' / ');
    }
}
