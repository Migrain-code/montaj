<?php

namespace App\Services\InternalLink;

use App\Models\Brand;
use App\Models\District;
use App\Models\InternalLinkRule;
use App\Models\Province;
use App\Models\SeoTarget;
use App\Models\Service;
use App\Support\PathNormalizer;

/**
 * Aktif hizmet ve bölge sayfalarından iç link kuralı üretir — DETERMİNİSTİK.
 *
 * Anchor metinleri UYDURULMAZ: yalnız gerçek sayfa adlarından gelir.
 * Yalnız erişilebilir hedefler için kural açılır; kapalı sayfalar pasife alınır.
 */
class RuleBuilder
{
    /**
     * Hizmet sayfaları için doğal anchor varyantları.
     *
     * Sayfa başlığı ("Yatak ve Baza Montajı") düz metinde nadiren aynen geçer;
     * yazarlar "baza montajı" der. Varyantlar UYDURULMAZ, her biri gerçek bir
     * sayfaya işaret eder ve elle seçilmiştir.
     *
     * @var array<string, array<int, string>>
     */
    private const SERVICE_ALIASES = [
        'ikea-mobilya-montaji' => ['IKEA montajı', 'IKEA mobilyası'],
        // DİKKAT: "yatak odası montajı" buraya EKLENMEZ — artık kendi sayfası var.
        'yatak-baza-montaji' => ['baza montajı', 'sandıklı baza kurulumu'],
        'tv-unitesi-montaji' => ['TV ünitesi'],
        'masa-sandalye-montaji' => ['masa montajı', 'sandalye montajı'],
        'ofis-mobilyasi-montaji' => ['ofis mobilyası'],
        'kitaplik-montaji' => ['kitaplık ve raf'],
        'gardirop-montaji' => ['gardırop kurulumu'],
        'mobilya-montaji' => ['mobilya kurulumu'],
        'yatak-odasi-montaji' => ['yatak odası takımı montajı'],
        'yemek-odasi-montaji' => ['yemek odası takımı montajı'],
        'koltuk-takimi-montaji' => ['köşe koltuk montajı', 'oturma grubu montajı'],
        'genc-odasi-montaji' => ['genç odası takımı montajı'],
        'cocuk-odasi-montaji' => ['ranza montajı', 'bebek odası montajı'],
    ];

    /** @return array{created: int, deactivated: int} */
    public function build(): array
    {
        $created = 0;
        $activeHashes = [];

        // Hizmet sayfaları — başlık + doğal varyantlar.
        foreach (Service::query()->where('is_active', true)->get() as $service) {
            $url = '/'.$service->slug;
            $created += $this->upsert($service->title, $url, 'all', 70, $activeHashes);

            foreach (self::SERVICE_ALIASES[$service->slug] ?? [] as $alias) {
                $created += $this->upsert($alias, $url, 'all', 65, $activeHashes);
            }
        }

        /*
         * Marka sayfaları.
         *
         * Anchor olarak yalın marka adı KULLANILMAZ: "Çilek" gibi adlar günlük
         * dilde başka anlama gelir ve yanlış yere link basılır. Tek kelimelik
         * markalarda "{marka} montajı" kalıbı zorunludur; iki kelimeli adlar
         * ("Kelebek Mobilya") zaten kendi başına açıktır.
         */
        foreach (Brand::query()->where('is_active', true)->with('service:id,slug')->get() as $brand) {
            $url = $brand->path();
            $created += $this->upsert($brand->name.' montajı', $url, 'all', 55, $activeHashes);

            if (str_contains(trim($brand->name), ' ')) {
                $created += $this->upsert($brand->name, $url, 'all', 35, $activeHashes);
            }
        }

        // İl sayfaları — tam ifade ve yalın ad.
        foreach (Province::query()->where('is_active', true)->get() as $province) {
            $url = '/'.$province->slug;
            $created += $this->upsert($province->name.' mobilya montaj', $url, 'all', 50, $activeHashes);
            // Yalın bölge adı düşük öncelikli: uzun ifade varsa o kazanır.
            $created += $this->upsert($province->name, $url, 'all', 20, $activeHashes);
        }

        // İlçe sayfaları — yalnız ili de aktifse.
        foreach (District::query()->where('is_active', true)->with('province')->get() as $district) {
            if (! $district->province?->is_active) {
                continue;
            }

            $url = '/'.$district->province->slug.'/'.$district->slug;
            $created += $this->upsert($district->name.' mobilya montaj', $url, 'all', 60, $activeHashes);
            $created += $this->upsert($district->name, $url, 'all', 30, $activeHashes);
        }

        // Kaynağı kapanan kurallar pasife alınır — silinmez.
        $deactivated = InternalLinkRule::query()
            ->whereNotIn('target_hash', $activeHashes)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return ['created' => $created, 'deactivated' => $deactivated];
    }

    private function upsert(string $anchor, string $url, string $scope, int $priority, array &$hashes): int
    {
        $hash = PathNormalizer::hash($url);
        $hashes[] = $hash;

        // Hedef gerçekten var mı? (SeoTarget senkronu bunu doğrular)
        if (! SeoTarget::query()->where('url_hash', $hash)->where('status', true)->exists()) {
            return 0;
        }

        $existing = InternalLinkRule::query()->where('anchor_text', $anchor)->where('target_hash', $hash)->first();

        if ($existing) {
            $existing->is_active || $existing->update(['is_active' => true]);

            return 0;
        }

        InternalLinkRule::create([
            'anchor_text' => $anchor,
            'target_url' => $url,
            'scope_type' => $scope,
            'priority' => $priority,
            'max_per_article' => 1,
            'is_active' => true,
        ]);

        return 1;
    }
}
