<?php

namespace Database\Seeders;

use App\Models\SeoKeyword;
use App\Support\TurkishText;
use Illuminate\Database\Seeder;

/**
 * Başlangıç kelime havuzu.
 *
 * Bunlar ARAMA HACMİ İDDİASI TAŞIMAZ: hacim alanı boş bırakılır. Gerçek hacim,
 * Search Console bağlandığında seo:discover-keywords ile veriden gelir (spec §10.1).
 */
class SeoKeywordSeeder extends Seeder
{
    public function run(): void
    {
        $keywords = [
            // Ticari niyet — hizmet sayfalarına atanacak
            ['mobilya montaj ustası', 'transactional', 'COMMERCIAL_PRIMARY', 1],
            ['mobilya montaj fiyatları', 'commercial', 'COMMERCIAL_PRIMARY', 1],
            ['gardırop montaj ustası', 'transactional', 'COMMERCIAL_VARIANT', 2],
            ['ikea montaj servisi', 'transactional', 'COMMERCIAL_PRIMARY', 1],
            ['baza montaj ustası', 'transactional', 'COMMERCIAL_VARIANT', 2],
            ['tv ünitesi montaj ustası', 'transactional', 'COMMERCIAL_VARIANT', 2],
            ['ofis mobilyası kurulum firması', 'transactional', 'COMMERCIAL_VARIANT', 2],
            ['mobilya sökme takma servisi', 'transactional', 'COMMERCIAL_VARIANT', 2],

            // Bilgi amaçlı — blog yazılarına atanacak
            ['gardırop montajı nasıl yapılır', 'informational', 'BLOG_PRIMARY', 1],
            ['sürgülü dolap kapağı ayarı', 'informational', 'BLOG_PRIMARY', 2],
            ['sandıklı baza pistonu değişimi', 'informational', 'BLOG_PRIMARY', 2],
            ['ikea pax kurulum süresi', 'informational', 'BLOG_PRIMARY', 2],
            ['mobilya duvara nasıl sabitlenir', 'informational', 'BLOG_PRIMARY', 1],
            ['alçıpan duvara dolap asma', 'informational', 'BLOG_PRIMARY', 2],
            ['taşınmada mobilya sökme sırası', 'informational', 'BLOG_PRIMARY', 2],
            ['mobilya montaj kılavuzu kayboldu', 'informational', 'BLOG_SECONDARY', 3],
            ['eksik vida nasıl temin edilir', 'informational', 'BLOG_SECONDARY', 3],
            ['mdf mi sunta mı daha dayanıklı', 'informational', 'BLOG_SECONDARY', 3],
            ['yatak odası takımı montaj sırası', 'informational', 'BLOG_PRIMARY', 2],
            ['kitaplık devrilmesini önleme', 'informational', 'BLOG_PRIMARY', 2],
            ['açılır masa mekanizması ayarı', 'informational', 'BLOG_SECONDARY', 3],
            ['ofis taşıma kontrol listesi', 'informational', 'BLOG_PRIMARY', 2],
            ['çekmece rayı nasıl değişir', 'informational', 'BLOG_SECONDARY', 3],
            ['menteşe ayarı nasıl yapılır', 'informational', 'BLOG_PRIMARY', 2],
            ['montaj öncesi oda hazırlığı', 'informational', 'BLOG_SECONDARY', 3],
            ['ikea beste duvara montaj', 'informational', 'BLOG_SECONDARY', 3],
            ['öğrenci evi mobilya kurulumu', 'informational', 'BLOG_SUPPORTING', 3],
            ['yazlık mobilya montajı', 'informational', 'BLOG_SUPPORTING', 4],
            ['demonte mobilya nedir', 'informational', 'BLOG_SUPPORTING', 4],
            ['mobilya montajında kullanılan aletler', 'informational', 'BLOG_SECONDARY', 3],
        ];

        foreach ($keywords as [$keyword, $intent, $type, $priority]) {
            SeoKeyword::query()->firstOrCreate(
                ['keyword_hash' => md5(TurkishText::lower($keyword))],
                [
                    'keyword' => $keyword,
                    'search_intent' => $intent,
                    'keyword_type' => $type,
                    'priority' => $priority,
                    'status' => true,
                    'note' => 'Başlangıç havuzu',
                ]
            );
        }
    }
}
