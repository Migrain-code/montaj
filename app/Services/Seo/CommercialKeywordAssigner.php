<?php

namespace App\Services\Seo;

use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Service;
use App\Support\PathNormalizer;
use App\Support\TurkishText;

/**
 * Ticari kelimeleri ilgili HİZMET sayfasına atar — DETERMİNİSTİK (spec §3.1).
 *
 * Neden gerekli: sahipsiz bir ticari kelime blog üretim hattının havuzuna düşer ve
 * blog, para kazandıran hizmet sayfasıyla aynı kelimeyi hedefleyerek onu yer
 * (cannibalization). Ticari kelimelerin sahibi hizmet sayfası olmalıdır.
 *
 * Eşleştirme UYDURMAZ: kelimenin içindeki somut terime bakar, eşleşme yoksa
 * kelimeye DOKUNMAZ (sahipsiz bırakır).
 */
class CommercialKeywordAssigner
{
    /** Terim → hizmet slug'ı. Sıra önemli: özel olan önce denenir. */
    private const TERM_MAP = [
        'ikea' => 'ikea-mobilya-montaji',
        'gardırop' => 'gardirop-montaji',
        'dolap' => 'gardirop-montaji',
        'baza' => 'yatak-baza-montaji',
        'yatak' => 'yatak-baza-montaji',
        'tv ünitesi' => 'tv-unitesi-montaji',
        'televizyon' => 'tv-unitesi-montaji',
        'ofis' => 'ofis-mobilyasi-montaji',
        'masa' => 'masa-sandalye-montaji',
        'sandalye' => 'masa-sandalye-montaji',
        'kitaplık' => 'kitaplik-montaji',
        'raf' => 'kitaplik-montaji',
        'sökme' => 'mobilya-montaji',
        'takma' => 'mobilya-montaji',
        'mobilya montaj' => 'mobilya-montaji',
    ];

    /** @return array{assigned: int, skipped: int, details: array<int, string>} */
    public function assign(): array
    {
        $services = Service::query()->where('is_active', true)->get()->keyBy('slug');
        $assigned = 0;
        $skipped = 0;
        $details = [];

        $candidates = SeoKeyword::query()
            ->active()
            ->unassigned()
            ->whereIn('keyword_type', ['COMMERCIAL_PRIMARY', 'COMMERCIAL_VARIANT'])
            ->get();

        foreach ($candidates as $keyword) {
            $slug = $this->matchService($keyword->keyword);

            if (! $slug || ! $services->has($slug)) {
                $skipped++;

                continue;
            }

            $target = SeoTarget::query()
                ->where('url_hash', PathNormalizer::hash('/'.$slug))
                ->where('status', true)
                ->first();

            if (! $target) {
                $skipped++;

                continue;
            }

            $keyword->update(['target_id' => $target->getKey()]);
            $assigned++;
            $details[] = $keyword->keyword.' → '.$target->name;
        }

        return ['assigned' => $assigned, 'skipped' => $skipped, 'details' => $details];
    }

    private function matchService(string $keyword): ?string
    {
        $needle = TurkishText::lower($keyword);

        foreach (self::TERM_MAP as $term => $slug) {
            if (str_contains($needle, TurkishText::lower($term))) {
                return $slug;
            }
        }

        return null;
    }
}
