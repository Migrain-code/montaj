<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Montaj Rehberleri', 'Mobilya kurulumunda adım adım anlatımlar ve dikkat edilecek noktalar.'],
            ['Taşınma', 'Taşınma öncesi sökme, taşıma ve yeni adreste yeniden kurulum.'],
            ['IKEA', 'IKEA ürünlerinin kurulumu, parça sistemleri ve sık karşılaşılan sorunlar.'],
            ['Bakım ve Onarım', 'Kapak ayarı, menteşe değişimi, ray bakımı ve küçük onarımlar.'],
            ['Bölge Rehberi', 'Trakya ilçelerinde montaj, ulaşım ve randevu hakkında bilgiler.'],
            ['Ofis ve İşyeri', 'Ofis mobilyası kurulumu, iş yeri taşıma ve toplu montaj.'],
        ];

        foreach ($categories as $i => [$name, $description]) {
            BlogCategory::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                    'auto_generate' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }
}
