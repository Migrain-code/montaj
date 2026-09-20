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
