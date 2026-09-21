<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Montajını yaptığımız mobilya markaları.
 *
 * Marka sayfaları hizmet sayfalarından AYRI bir eksendir: ziyaretçi "istikbal
 * mobilya montajı" diye arar, "gardırop montajı" diye değil. İkisini tek modelde
 * toplamak her iki tarafı da zayıflatırdı.
 */
class Brand extends Model
{
    protected $fillable = [
        'name', 'slug', 'service_id', 'logo', 'description', 'content', 'products', 'faqs',
        'meta_title', 'meta_description', 'is_active', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'products' => 'array',
            'faqs' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Markanın kendi hizmet sayfası varsa (IKEA gibi) ayrı marka sayfası açılmaz.
     * İki sayfa aynı sorguda yarışır ve ikisi de kaybeder (spec §3.4).
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function hasOwnPage(): bool
    {
        return blank($this->service_id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Kartın ve menünün gideceği adres: hizmet sayfası varsa oraya. */
    public function path(): string
    {
        if ($slug = $this->service?->slug) {
            return '/'.$slug;
        }

        return '/markalar/'.$this->slug;
    }

    public function getUrlAttribute(): string
    {
        return url($this->path());
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? media_url($this->logo) : null;
    }

    /** Sayfa başlığı: marka adı tek başına arama karşılığı vermez. */
    public function getHeadingAttribute(): string
    {
        return $this->name.' Mobilya Montajı';
    }
}
