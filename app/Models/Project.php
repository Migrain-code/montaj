<?php

namespace App\Models;

use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Tamamlanan bir montaj işi ("İşlerimiz").
 *
 * Galeriden farkı: her işin kendi adresi vardır ve Google onu ayrı bir sayfa olarak
 * dizine alır. İş; ilçe, marka ve hizmet sayfalarına bağlanır ve orada listelenir.
 * Yerel aramada "bu firma burada gerçekten iş yapıyor" kanıtı budur.
 *
 * Görseller `images` alanında sıralı tutulur: [{path, kind, alt, caption}]
 *   kind: before (öncesi) | after (sonrası) | detail (yakın çekim)
 */
class Project extends Model
{
    public const KINDS = [
        'before' => 'Öncesi',
        'after' => 'Sonrası',
        'detail' => 'Detay',
    ];

    protected $fillable = [
        'title', 'slug', 'summary', 'body', 'items', 'images',
        'district_id', 'brand_id', 'service_id', 'completed_at',
        'meta_title', 'meta_description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'images' => 'array',
            'completed_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** En yeni iş önce: tarih yoksa elle verilen sıra, sonra kayıt sırası. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('completed_at')->orderBy('sort_order')->orderByDesc('id');
    }

    public function path(): string
    {
        return '/islerimiz/'.$this->slug;
    }

    public function getUrlAttribute(): string
    {
        return url($this->path());
    }

    /** @return array<int, array{path: string, kind: string, alt: string, caption: ?string, url: string}> */
    public function gallery(): array
    {
        return collect($this->images ?? [])
            ->filter(fn ($i) => filled($i['path'] ?? null))
            ->map(fn ($i) => [
                'path' => $i['path'],
                'kind' => array_key_exists($i['kind'] ?? '', self::KINDS) ? $i['kind'] : 'detail',
                'alt' => filled($i['alt'] ?? null) ? $i['alt'] : $this->title,
                'caption' => $i['caption'] ?? null,
                'url' => media_url($i['path']),
            ])
            ->values()
            ->all();
    }

    /** Kapak: ilk "sonrası" görseli; yoksa ilk görsel. */
    public function cover(): ?array
    {
        $gallery = $this->gallery();

        return collect($gallery)->firstWhere('kind', 'after') ?? ($gallery[0] ?? null);
    }

    public function getCoverUrlAttribute(): string
    {
        return $this->cover()['url'] ?? asset('images/placeholder.svg');
    }

    /** Öncesi ve sonrası fotoğrafı birlikte var mı? Sayfada yan yana gösterilir. */
    public function hasBeforeAfter(): bool
    {
        $kinds = array_column($this->gallery(), 'kind');

        return in_array('before', $kinds, true) && in_array('after', $kinds, true);
    }

    /** "Kasım 2026": gün yazılmaz; fotoğraf tarihi ile iş günü birebir aynı olmayabilir. */
    public function getCompletedLabelAttribute(): ?string
    {
        return $this->completed_at?->translatedFormat('F Y');
    }

    /** Başlıkta ilçe yoksa ve ilçe seçildiyse eklenir: yerel aramada sayfanın asıl sinyali. */
    public function getSeoTitleAttribute(): string
    {
        if (filled($this->meta_title)) {
            return $this->meta_title;
        }

        $district = $this->district?->name;

        return $district && ! str_contains(TurkishText::fold($this->title), TurkishText::fold($district))
            ? $district.' '.$this->title
            : $this->title;
    }

    public function getSeoDescriptionAttribute(): string
    {
        return $this->meta_description ?: ($this->summary ?: Str::limit(strip_tags((string) $this->body), 155));
    }
}
