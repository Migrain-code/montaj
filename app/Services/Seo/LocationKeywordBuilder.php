<?php

namespace App\Services\Seo;

use App\Models\District;
use App\Models\Province;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Service;
use App\Support\PathNormalizer;
use App\Support\TurkishText;

/**
 * İl/ilçe adı içeren anahtar kelimeleri üretir — DETERMİNİSTİK, AI kullanmaz.
 *
 * YALNIZ ERİŞİLEBİLİR bölgeler için üretir: ilçe aktif VE ili aktif olmalıdır.
 * Kapalı bir bölge için kelime üretmek, 404 dönen bir sayfayı hedeflemek demektir.
 *
 * Sahiplik dağılımı (spec §3.1 — tek sahip kuralı):
 *   "{ilçe} mobilya montaj*"  → İLÇE SAYFASI sahiplenir (ticari)
 *   "{ilçe} {hizmet}"         → SAHİPSİZ bırakılır; blog üretim hattının hedefi olur
 */
class LocationKeywordBuilder
{
    /** İlçe sayfasının sahipleneceği ticari kalıplar. */
    private const OWNED_PATTERNS = [
        '%s mobilya montaj' => 'COMMERCIAL_PRIMARY',
        '%s mobilya montaj ustası' => 'COMMERCIAL_VARIANT',
        '%s mobilya montaj fiyatları' => 'COMMERCIAL_VARIANT',
    ];

    /** Blog hattına bırakılan hizmet kalıpları: "{bölge} {hizmet}". */
    private const SERVICE_SLUGS = [
        'gardirop-montaji' => 'gardırop montajı',
        'ikea-mobilya-montaji' => 'ikea montajı',
        'yatak-baza-montaji' => 'baza montajı',
        'tv-unitesi-montaji' => 'tv ünitesi montajı',
        'ofis-mobilyasi-montaji' => 'ofis mobilyası montajı',
        'masa-sandalye-montaji' => 'masa sandalye montajı',
        'kitaplik-montaji' => 'kitaplık montajı',
        'mobilya-montaji' => 'mobilya sökme takma',
    ];

    /** @return array{created: int, assigned: int, deactivated: int, regions: array<int, string>} */
    public function build(): array
    {
        $created = $assigned = 0;
        $regions = [];
        $activeHashes = [];

        foreach ($this->reachableRegions() as $region) {
            $regions[] = $region['label'];
            $target = SeoTarget::query()->where('url_hash', PathNormalizer::hash($region['url']))->first();

            // Ticari kalıplar — ilçe/il sayfası sahiplenir.
            foreach (self::OWNED_PATTERNS as $pattern => $type) {
                $keyword = mb_strtolower(sprintf($pattern, $region['name']), 'UTF-8');
                $activeHashes[] = md5(TurkishText::lower($keyword));

                [$row, $isNew] = $this->upsert($keyword, [
                    'keyword_type' => $type,
                    'search_intent' => 'transactional',
                    'priority' => $region['type'] === 'district' ? 1 : 2,
                    'note' => 'Bölge otomasyonu · '.$region['label'],
                ]);

                $created += $isNew ? 1 : 0;

                // Sahipsizse hedefe ata; elle başka bir sahibe verilmişse DOKUNMA.
                if ($target && $target->status && ! $row->hasOwner()) {
                    $row->update(['target_id' => $target->getKey()]);
                    $assigned++;
                }
            }

            // Hizmet kalıpları — blog hattı için SAHİPSİZ kalır.
            foreach ($this->activeServiceTerms() as $term) {
                $keyword = mb_strtolower($region['name'].' '.$term, 'UTF-8');
                $activeHashes[] = md5(TurkishText::lower($keyword));

                [, $isNew] = $this->upsert($keyword, [
                    'keyword_type' => 'BLOG_PRIMARY',
                    'search_intent' => 'commercial',
                    'priority' => $region['type'] === 'district' ? 2 : 3,
                    'note' => 'Bölge otomasyonu · '.$region['label'],
                ]);

                $created += $isNew ? 1 : 0;
            }
        }

        // Bölge kapatıldıysa o bölgenin kelimeleri pasife alınır — SİLİNMEZ (spec §10.9).
        $deactivated = SeoKeyword::query()
            ->where('note', 'like', 'Bölge otomasyonu%')
            ->whereNotIn('keyword_hash', $activeHashes)
            ->where('status', true)
            ->update(['status' => false]);

        return ['created' => $created, 'assigned' => $assigned, 'deactivated' => $deactivated, 'regions' => $regions];
    }

    /**
     * Erişilebilir bölgeler: ilçe için ili de aktif olmalı.
     *
     * @return array<int, array{name: string, label: string, url: string, type: string}>
     */
    public function reachableRegions(): array
    {
        $regions = [];

        foreach (Province::query()->where('is_active', true)->orderBy('sort_order')->get() as $province) {
            $regions[] = [
                'name' => $province->name,
                'label' => $province->name.' (il)',
                'url' => '/'.$province->slug,
                'type' => 'province',
            ];

            foreach (District::query()->where('province_id', $province->getKey())->where('is_active', true)->orderBy('sort_order')->get() as $district) {
                $regions[] = [
                    'name' => $district->name,
                    'label' => $district->name.' / '.$province->name,
                    'url' => '/'.$province->slug.'/'.$district->slug,
                    'type' => 'district',
                ];
            }
        }

        return $regions;
    }

    /** @return array<int, string> */
    private function activeServiceTerms(): array
    {
        $activeSlugs = Service::query()->where('is_active', true)->pluck('slug')->all();

        return collect(self::SERVICE_SLUGS)
            ->filter(fn ($term, $slug) => in_array($slug, $activeSlugs, true))
            ->values()
            ->all();
    }

    /** @return array{0: SeoKeyword, 1: bool} */
    private function upsert(string $keyword, array $attributes): array
    {
        $hash = md5(TurkishText::lower($keyword));
        $existing = SeoKeyword::query()->where('keyword_hash', $hash)->first();

        if ($existing) {
            // Kapalıyken tekrar aktifleşen bölgenin kelimesini geri aç.
            if (! $existing->status && str_starts_with((string) $existing->note, 'Bölge otomasyonu')) {
                $existing->update(['status' => true]);
            }

            return [$existing, false];
        }

        return [SeoKeyword::create(['keyword' => $keyword, 'status' => true] + $attributes), true];
    }
}
