<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * SEO Merkezi: SEO, AI ve blog araçları TEK menü öğesinde; ekranlar arasında üstteki
 * buton şeridiyle geçilir (tavsiyemiz SEO panelindeki düzen ve adlar).
 *
 * Üyeler sınıflarında `$cluster = SeoMerkezi::class` taşır; sıra `$navigationSort` ile.
 */
class SeoMerkezi extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $navigationLabel = 'SEO Merkezi';

    protected static ?string $clusterBreadcrumb = 'SEO Merkezi';

    protected static ?string $slug = 'seo-merkezi';

    protected static ?int $navigationSort = 50;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
