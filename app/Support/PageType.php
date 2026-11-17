<?php

namespace App\Support;

/**
 * Sayfa türü: Google Analytics olaylarında "page_type" parametresi (hangi tür sayfa
 * müşteri getiriyor?). <body data-page-type="..."> ve teklif formunun gizli alanına yazılır.
 *
 * Tek ve iki parçalı adresler (slug.show: hizmet/il/sayfa, regions.district) ve marka
 * sayfası türü "pageType" görünüm verisiyle AÇIKÇA verir; diğer sayfalarda tür rota
 * adından çıkarılır.
 */
class PageType
{
    /** @var array<string, string> rota adı (deseni) => sayfa türü */
    private const ROUTES = [
        'home' => 'home',
        'services.index' => 'service_list',
        'brands.index' => 'brand_list',
        'regions.index' => 'region_list',
        'gallery.index' => 'gallery',
        'blog.*' => 'blog',
        'quote.thanks' => 'thanks',
        'quote.*' => 'quote',
        'contact' => 'contact',
        'about' => 'page',
        'faq' => 'page',
        'slug.show' => 'page',
    ];

    public static function resolve(?string $explicit = null): string
    {
        if (is_string($explicit) && preg_match('/^[a-z_]{1,30}$/', $explicit)) {
            return $explicit;
        }

        $route = request()->route();

        foreach (self::ROUTES as $pattern => $type) {
            if ($route && $route->named($pattern)) {
                return $type;
            }
        }

        return 'other';
    }
}
