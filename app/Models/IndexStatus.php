<?php

namespace App\Models;

use App\Services\Seo\ContentRegistry;
use App\Support\SitemapUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir adresin Google dizin durumu ve Indexing API bildirim geçmişi.
 *
 * Takip edilen küme = site haritasındaki tüm adresler ∪ skorlanan içerikler. Sonuçlar,
 * adresi aynı olan seo_analyses satırına da yazılır (gösterge paneli ve skor ekranı için).
 */
class IndexStatus extends Model
{
    protected $fillable = [
        'url', 'google_index_status', 'google_index_detail', 'index_checked_at',
        'index_submitted_at', 'index_submit_count', 'index_submit_error',
    ];

    protected function casts(): array
    {
        return [
            'index_checked_at' => 'datetime',
            'index_submitted_at' => 'datetime',
            'index_submit_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (IndexStatus $status) => $status->url_hash = sha1((string) $status->url));
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(SeoAnalysis::class, 'url', 'url');
    }

    /**
     * Takip listesini site haritasıyla eşitler: yeni adresler eklenir (varsa skor
     * kaydındaki eski durumla), artık dizine açık olmayanlar silinir.
     *
     * @return int takip edilen adres sayısı
     */
    public static function syncTracked(): int
    {
        $analyses = SeoAnalysis::query()->whereNotNull('url')->get()->keyBy('url');

        $urls = collect(SitemapUrls::all())->pluck('loc')
            ->merge($analyses->keys())
            ->filter()
            ->map(fn ($url) => (string) $url)
            ->unique()
            ->values();

        $existing = static::query()->pluck('url_hash')->flip();
        $now = now();

        $rows = $urls->reject(fn ($url) => $existing->has(sha1($url)))
            ->map(function (string $url) use ($analyses, $now) {
                $a = $analyses->get($url);

                return [
                    'url' => $url,
                    'url_hash' => sha1($url),
                    'google_index_status' => $a?->google_index_status,
                    'google_index_detail' => $a?->google_index_detail,
                    'index_checked_at' => $a?->index_checked_at,
                    'index_submitted_at' => $a?->index_submitted_at,
                    'index_submit_count' => (int) ($a?->index_submit_count ?? 0),
                    'index_submit_error' => $a?->index_submit_error,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });

        foreach ($rows->chunk(200) as $chunk) {
            static::query()->insert($chunk->values()->all());
        }

        static::query()->whereNotIn('url_hash', $urls->map(fn ($url) => sha1($url))->all())->delete();

        return $urls->count();
    }

    /** Aynı adresli skor kaydına da yazar. */
    public function mirror(array $attributes): void
    {
        $this->forceFill($attributes)->saveQuietly();

        SeoAnalysis::query()->where('url', $this->url)->update($attributes);
    }

    public function getIndexDetailLabelAttribute(): ?string
    {
        if (blank($this->google_index_detail)) {
            return null;
        }

        return collect(explode(' · ', $this->google_index_detail))
            ->map(fn (string $part) => SeoAnalysis::INDEX_DETAILS[trim($part)] ?? trim($part))
            ->unique()
            ->implode(' · ');
    }

    /** Panelde gösterilen tür: skor kaydı varsa onun tipi, yoksa adresin biçimi. */
    public function getKindAttribute(): string
    {
        if ($type = $this->analysis?->content_type) {
            return ContentRegistry::TYPES[$type] ?? $type;
        }

        $path = trim((string) parse_url($this->url, PHP_URL_PATH), '/');

        return match (true) {
            $path === '' => 'Ana sayfa',
            str_starts_with($path, 'blog/') => 'Blog yazısı',
            str_starts_with($path, 'markalar/') => 'Marka sayfası',
            substr_count($path, '/') === 1 => 'İlçe sayfası',
            default => 'Sayfa',
        };
    }
}
