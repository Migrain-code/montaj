<?php

namespace Database\Seeders;

use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Gardırop', 'Yatak', 'Baza', 'TV Ünitesi', 'Masa', 'IKEA', 'Ofis', 'Kitaplık', 'Diğer'];

        $ids = [];
        foreach ($categories as $i => $name) {
            $ids[$name] = GalleryCategory::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i + 1]
            )->id;
        }

        if (GalleryItem::query()->exists()) {
            return;
        }

        // Örnek görseller (public/images/gallery). Gerçek montaj fotoğraflarıyla admin panelinden değiştirilebilir.
        $items = [
            ['Gardırop', 'gardirop-1', 'Gardırop ve çekmeceli dolap montajı'],
            ['Yatak', 'yatak-1', 'Yatak odası takımı kurulumu'],
            ['Yatak', 'yatak-2', 'Başlıklı yatak montajı'],
            ['Baza', 'baza-1', 'Baza ve başlık kurulumu'],
            ['TV Ünitesi', 'tv-1', 'TV ünitesi ve duvar rafı montajı'],
            ['TV Ünitesi', 'tv-2', 'Salon TV ünitesi kurulumu'],
            ['Masa', 'masa-1', 'Yemek masası ve sandalye montajı'],
            ['Masa', 'masa-2', 'Mutfak masası kurulumu'],
            ['IKEA', 'ikea-1', 'IKEA oturma grubu ve sehpa montajı'],
            ['IKEA', 'ikea-2', 'IKEA çalışma masası kurulumu'],
            ['Ofis', 'ofis-1', 'Ofis sandalyesi ve masa montajı'],
            ['Ofis', 'ofis-2', 'Home ofis mobilyası kurulumu'],
            ['Kitaplık', 'kitaplik-1', 'Açık raflı kitaplık montajı'],
            ['Diğer', 'diger-1', 'Koltuk takımı kurulumu'],
        ];

        foreach ($items as $i => [$category, $file, $title]) {
            GalleryItem::query()->create([
                'gallery_category_id' => $ids[$category],
                'title' => $title,
                'image' => 'gallery/'.$file.'.webp',
                'alt_text' => $title.' - örnek çalışma',
                'is_active' => true,
                'show_on_home' => $i < 8,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
