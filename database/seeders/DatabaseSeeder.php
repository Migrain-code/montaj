<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ImageSeeder::class,
            SettingsSeeder::class,
            ServiceSeeder::class,
            // Oda tipi hizmetler ServiceSeeder'dan SONRA: sort_order'ı onun üstüne ekler.
            RoomServiceSeeder::class,
            // Markalar hizmetlerden SONRA: IKEA kendi hizmet sayfasına bağlanır.
            BrandSeeder::class,
            FeatureSeeder::class,
            RegionSeeder::class,
            GallerySeeder::class,
            FaqSeeder::class,
            PageSeeder::class,
            BlogSeeder::class,
            SeoKeywordSeeder::class,
        ]);
    }
}
