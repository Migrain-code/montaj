<?php

namespace App\Services\Seo;

use App\Support\TurkishText;

/**
 * Fırsat avının sayfa metnine eklediği kelime bölümleri.
 *
 *   <section class="kw-block" data-kw="cerkezkoy-mobilya-montaj"> … </section>
 *
 * İşaret, bölümü geri almak ve sayfa metnini yeniden yazan işlerin (seo:enrich) onu
 * silmeden korumak için vardır.
 */
final class KeywordSections
{
    public static function slug(string $keyword): string
    {
        return TurkishText::slug($keyword);
    }

    public static function wrap(string $keyword, string $html): string
    {
        return '<section class="kw-block" data-kw="'.self::slug($keyword).'">'.$html.'</section>';
    }

    /**
     * Bölümleri metinden ayırır.
     *
     * @return array{0: string, 1: array<int, string>} [bölümsüz metin, bölümler]
     */
    public static function extract(?string $body): array
    {
        $blocks = [];

        $clean = preg_replace_callback(
            '#<section\b[^>]*class="kw-block"[^>]*>.*?</section>#is',
            function ($m) use (&$blocks) {
                $blocks[] = $m[0];

                return '';
            },
            (string) $body,
        ) ?? (string) $body;

        return [trim($clean), $blocks];
    }

    /**
     * Kelimenin bölümünü kaldırır. İşaret yoksa (panelde elle düzenlenip editör
     * sarmalayıcıyı attıysa) eklenen HTML birebir aranır.
     */
    public static function remove(string $body, string $keyword, ?string $html = null): string
    {
        $pattern = '#\s*<section\b[^>]*data-kw="'.preg_quote(self::slug($keyword), '#').'"[^>]*>.*?</section>#is';
        $stripped = preg_replace($pattern, '', $body, 1) ?? $body;

        if ($stripped === $body && $html) {
            $inner = preg_replace('#^<section\b[^>]*>|</section>$#i', '', $html) ?? $html;
            $stripped = str_replace([$html, $inner], '', $body);
        }

        return trim($stripped);
    }
}
