<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * İlk "İşlerimiz" kayıtları: Instagram'da paylaşılan gerçek işler (Kasım 2026).
 *
 * Görseller depoya ELLE yüklenir (FTP): storage/app/public/projects/{slug}/…
 * Seeder görseli kopyalamaz; eksikse uyarır ama kaydı yine ekler.
 *
 * Kurallar:
 * - Var olan kayıt (aynı slug) DEĞİŞTİRİLMEZ: panelde yapılan düzeltme ezilmez.
 * - Fotoğrafta görünmeyen bilgi yazılmaz. İşin yapıldığı ilçe bilinmediği için boş
 *   bırakıldı; panelden seçilince iş o ilçenin sayfasında da listelenir.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $brand = fn (string $slug) => Brand::query()->where('slug', $slug)->value('id');
        $service = fn (string $slug) => Service::query()->where('slug', $slug)->value('id');

        $projects = [
            [
                'slug' => 'almila-genc-odasi-montaji',
                'title' => 'Almila genç odası montajı',
                'summary' => 'Kutularda gelen Almila genç odası takımının montajı: aynalı gardırop, kitaplık ve bazalı karyola.',
                'items' => ['Aynalı gardırop', 'Kitaplık', 'Bazalı karyola'],
                'body' => '<p>Almila genç odası takımı kutular hâlinde teslim edilmişti: gardırop, kitaplık ve bazalı karyola. Takımın tamamını kurduk.</p>'
                    .'<h2>Gardırop</h2><p>Gardırobun orta kapağında boy aynası var. Gövde kurulduktan sonra kapaklar takıldı ve tek tek hizalandı; montajın sonunda her kapağın düzgün açılıp kapandığı kontrol edildi.</p>'
                    .'<h2>Kitaplık</h2><p>Açık raflı, dik kitaplık gardırobun yanına kuruldu.</p>'
                    .'<h2>Bazalı karyola</h2><p>Karyolanın bazası amortisörlü kaldırma mekanizmasıyla kuruldu. Baza yukarı kaldırıldığında altındaki saklama alanı kullanılabiliyor.</p>',
                'brand_id' => $brand('almila'),
                'service_id' => $service('genc-odasi-montaji'),
                'completed_at' => '2026-11-12',
                'images' => [
                    ['path' => 'projects/almila-genc-odasi-montaji/01-oncesi-kutular.webp', 'kind' => 'before', 'alt' => 'Almila genç odası takımının montaj öncesi kutuları', 'caption' => 'Kutularda geldi'],
                    ['path' => 'projects/almila-genc-odasi-montaji/02-oncesi-gardirop-kutusu.webp', 'kind' => 'before', 'alt' => 'Duvara dayalı gardırop kutusu', 'caption' => null],
                    ['path' => 'projects/almila-genc-odasi-montaji/03-sonrasi-gardirop-kitaplik.webp', 'kind' => 'after', 'alt' => 'Kurulmuş Almila gardırop ve kitaplık', 'caption' => 'Montaj tamamlandı'],
                    ['path' => 'projects/almila-genc-odasi-montaji/04-aynali-gardirop.webp', 'kind' => 'after', 'alt' => 'Orta kapağında boy aynası olan Almila gardırop', 'caption' => 'Aynalı gardırop'],
                    ['path' => 'projects/almila-genc-odasi-montaji/05-bazali-karyola.webp', 'kind' => 'detail', 'alt' => 'Amortisörlü bazası açık karyola', 'caption' => 'Bazalı karyola'],
                ],
            ],
            [
                'slug' => 'almila-beyaz-genc-odasi-montaji',
                'title' => 'Almila beyaz genç odası montajı',
                'summary' => 'Beyaz Almila genç odası takımının montajı: üç kapaklı gardırop, korkuluklu karyola ve dört çekmeceli şifonyer.',
                'items' => ['Üç kapaklı gardırop', 'Korkuluklu karyola', 'Karyola alt çekmecesi', 'Dört çekmeceli şifonyer'],
                'body' => '<p>Beyaz renkli Almila genç odası takımının tüm parçalarını kurduk.</p>'
                    .'<h2>Gardırop</h2><p>Üç kapaklı gardırobun kapakları takıldıktan sonra tek tek hizalandı.</p>'
                    .'<h2>Karyola</h2><p>Karyola, kumaş kaplı sırt dayanağı ve korkuluğuyla kuruldu. Altındaki çekmece de takıldı; dışarı çekilerek kullanılıyor.</p>'
                    .'<h2>Şifonyer</h2><p>Dört çekmeceli şifonyer kuruldu, çekmeceleri yerine takıldı.</p>',
                'brand_id' => $brand('almila'),
                'service_id' => $service('genc-odasi-montaji'),
                'completed_at' => '2026-11-12',
                'images' => [
                    ['path' => 'projects/almila-beyaz-genc-odasi-montaji/01-gardirop.webp', 'kind' => 'after', 'alt' => 'Kurulmuş beyaz üç kapaklı Almila gardırop', 'caption' => 'Üç kapaklı gardırop'],
                    ['path' => 'projects/almila-beyaz-genc-odasi-montaji/02-karyola.webp', 'kind' => 'after', 'alt' => 'Kumaş sırt dayanaklı beyaz genç odası karyolası', 'caption' => 'Karyola'],
                    ['path' => 'projects/almila-beyaz-genc-odasi-montaji/03-karyola-alt-cekmece.webp', 'kind' => 'detail', 'alt' => 'Korkuluklu karyola ve altındaki çekmece', 'caption' => 'Alt çekmecesiyle'],
                    ['path' => 'projects/almila-beyaz-genc-odasi-montaji/04-sifonyer.webp', 'kind' => 'after', 'alt' => 'Dört çekmeceli beyaz şifonyer', 'caption' => 'Şifonyer'],
                ],
            ],
            [
                'slug' => 'asra-kitaplik-montaji',
                'title' => 'Asra kitaplık montajı',
                'summary' => 'Kutusunda gelen Asra kitaplığın montajı: üstte açık raflar, altta dört kapaklı dolap.',
                'items' => ['Asra kitaplık (açık raflı, alt dolaplı)'],
                'body' => '<p>Kitaplık iki bölmeli: üst kısımda açık raflar, alt kısımda dört kapaklı dolap var. Kutudan çıkan parçalarla kitaplığı kurduk, rafları ve kapakları yerine taktık.</p>',
                'brand_id' => null,
                'service_id' => $service('kitaplik-montaji'),
                'completed_at' => '2026-11-05',
                'images' => [
                    ['path' => 'projects/asra-kitaplik-montaji/01-oncesi-kutu.webp', 'kind' => 'before', 'alt' => 'Montaj öncesi duvara dayalı kitaplık kutusu', 'caption' => 'Kutusunda geldi'],
                    ['path' => 'projects/asra-kitaplik-montaji/02-sonrasi-kitaplik.webp', 'kind' => 'after', 'alt' => 'Kurulmuş açık raflı ve alt dolaplı Asra kitaplık', 'caption' => 'Montaj tamamlandı'],
                ],
            ],
        ];

        $missing = [];

        foreach ($projects as $i => $data) {
            foreach ($data['images'] as $image) {
                if (! Storage::disk('public')->exists($image['path'])) {
                    $missing[] = $image['path'];
                }
            }

            Project::query()->firstOrCreate(['slug' => $data['slug']], $data + [
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        if ($missing !== []) {
            $this->command?->warn(count($missing).' görsel depoda yok; FTP ile storage/app/public altına yükleyin: '.implode(', ', array_slice($missing, 0, 3)).(count($missing) > 3 ? ' …' : ''));
        }
    }
}
