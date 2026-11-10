<?php

/*
|--------------------------------------------------------------------------
| SEO & AI otomasyon yapılandırması
|--------------------------------------------------------------------------
| Buradaki değerler VARSAYILANDIR. Çoğu, admin panelindeki "SEO & AI Ayarları"
| sayfasından değiştirilebilir. Panelde geçersiz bir değer girilirse sistem
| sessizce kapanmaz, buradaki güvenli varsayılana döner (spec §5).
*/

return [

    'ai' => [
        // OpenRouter, OpenAI uyumlu bir API sunar; tek istemci yeterlidir.
        'base_url' => rtrim((string) env('AI_BASE_URL', 'https://openrouter.ai/api/v1'), '/'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'openai/gpt-4o-mini'),
        'timeout' => (int) env('AI_TIMEOUT', 180),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 8000),
        'temperature' => (float) env('AI_TEMPERATURE', 0.7),

        // OpenRouter'ın önerdiği kimlik başlıkları (openrouter.ai sıralamalarında görünür).
        'referer' => env('AI_HTTP_REFERER', env('APP_URL')),
        'title' => env('AI_APP_TITLE', env('APP_NAME')),
    ],

    'google' => [
        // Servis hesabı JSON dosyasının yolu (storage/app/private altında tutun).
        'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        // GSC mülkü: "sc-domain:ornek.com" VEYA "https://ornek.com/" — GSC'dekiyle birebir aynı olmalı (spec §7.9).
        'property' => env('GSC_PROPERTY'),
    ],

    // Deterministik skor: meta 30 + içerik 40 + teknik 15 + link 15 = 100 (spec §3.2)
    'score' => [
        'weights' => ['meta' => 30, 'content' => 40, 'technical' => 15, 'links' => 15],
        'target' => 80,
        'meta_title_max' => 60,
        'meta_description_max' => 155,
        'meta_description_min' => 70,
        'min_words' => 300,
        'good_words' => 600,
    ],

    // İKİ AYRI EŞİK — bilerek farklı (spec §3.5, §7.6)
    'duplicate' => [
        'scan_threshold' => 0.55,   // "çakışma olabilir" → listelenir, buton yok
        'merge_threshold' => 0.90,  // "pratikte aynı yazı" → tek tuşla birleştirilir
        'max_owners_per_keyword' => 1,
        'stem_length' => 6,         // kaba gövdeleme (spec §7.1)
    ],

    'blog' => [
        'daily_enabled' => false,
        'daily_count' => 1,
        'topic_candidates' => 5,    // filtreye takılırsa yedek kalsın (spec §3.3)
        'publish_hour_start' => 9,
        'publish_hour_end' => 18,
        'auto_publish' => true,
        'min_words' => 700,
        'max_words' => 1200,
    ],

    // Varsayılan KAPALI (spec §3.6)
    'internal_links' => [
        'enabled' => false,
        'auto_apply' => false,
        'max_per_article' => 4,
        'max_total' => 8,
        'max_home' => 1,
        'min_score' => 60,
        'replace_existing' => false,
        'batch_size' => 25,
    ],

    'redirects' => [
        'auto_apply' => false,
        'min_similarity' => 0.72,
    ],

    /*
     * GSC fırsat avı: bölge kelimelerini ilk 3'e taşıma.
     *
     * Kelime ilk target_position sıraya girince ona HİÇBİR işlem yapılmaz.
     * Aynı sayfaya sürekli yazmak spam'dir; bu yüzden kelime başına bekleme süresi
     * ve günlük üst sınır vardır.
     */
    'opportunity' => [
        'autopilot' => true,          // günlük görev kelimeleri kendiliğinden iyileştirsin mi
        'daily_limit' => 3,           // günde en fazla kaç kelime iyileştirilir
        'cooldown_days' => 7,         // aynı kelimeye iki iyileştirme arası; Google'ın tepki süresi
        'target_position' => 3,
        'window_days' => 28,          // bölge kelimeleri az aranır; tek günlük veri çoğu gün boş gelir
        'min_impressions' => 3,       // bundan az gösterimli sorgu fırsat sayılmaz
        'max_position' => 60,         // bunun altındaki sıra pratikte görünmez
        'max_new_per_run' => 30,      // bir turda eklenecek yeni kelime tavanı (tavsiyemiz'deki çığ etkisine karşı)
        'max_sections_per_page' => 4, // bir sayfaya eklenecek kelime bölümü tavanı
        'max_faqs' => 10,

        // "Sıraya yakın" arama: bölge adı geçmese de Google'ın sitemizi zaten 4-25. sırada
        // gösterdiği sorgu. En hızlı kazanç burada: sayfa zaten ilgili sayılıyor.
        // Kelime, Google'ın o sorguda gösterdiği sayfaya (blog yazısı dahil) bağlanır.
        'near_enabled' => true,
        'near_min_position' => 4,
        'near_max_position' => 25,

        // Bölgesiz sorguda hizmet kelimesi yoksa bu "nasıl yapılır" ifadelerinden biri gerekir.
        // "ikea çorlu" gibi mağaza aramaları ikisini de taşımaz, alınmaz.
        'howto_terms' => ['nasıl', 'nasil', 'ayar', 'takılır', 'takilir', 'değiş', 'sabitle', 'yapılır', 'kurulum', 'montaj'],

        // Mağaza/navigasyon niyeti: bu sorgular montaj hizmeti aramıyor.
        'skip_terms' => ['var mı', 'varmı', 'varmi', 'nerede', 'adres', 'telefon', 'saatleri', 'randevu', 'lieferzeit', 'kampanya', 'fiyatı ne'],

        // Öne çıkarılacak bölgeler: listede önce bunlar işlenir.
        'focus_regions' => ['Çerkezköy', 'Kapaklı', 'Kızılpınar', 'Saray'],

        // Kendi sayfası olmayan yer adları → bağlanacağı sayfa.
        // Kızılpınar, Çerkezköy'ün mahallesidir (Kızılpınar Atatürk, Gültepe, Namık Kemal).
        'region_aliases' => [
            'Kızılpınar' => '/tekirdag/cerkezkoy',
        ],

        // Odak bölgeler için GSC verisi beklenmeden takibe alınan kelimeler.
        'seed_patterns' => ['%s mobilya montaj', '%s mobilya montajcı'],

        // Sorgunun işimizle ilgili sayılması için bir HİZMET kelimesi geçmeli.
        // Ürün adı yetmez: canlı veride "ikea çorlu", "ikea çorlu da varmı" çıktı —
        // bunlar mağaza arayan kişilerdir, montaj aramaz. "çorlu ikea montaj" ise fırsattır.
        // Kelime başında eşleşir: "montaj" → montajı, montajcı; "kur" yazılmaz, "kurs"u da yakalardı.
        'business_terms' => ['montaj', 'kurulum', 'kurma', 'kuran', 'sökme', 'söküm', 'takma', 'usta'],
    ],

    // Tanınan AI ajanları (spec §3.9)
    'ai_bots' => [
        'GPTBot' => 'GPTBot',
        'OAI-SearchBot' => 'OAI-SearchBot',
        'ChatGPT-User' => 'ChatGPT-User',
        'ClaudeBot' => 'ClaudeBot',
        'Claude-User' => 'Claude-User',
        'Claude-SearchBot' => 'Claude-SearchBot',
        'anthropic-ai' => 'anthropic-ai',
        'PerplexityBot' => 'PerplexityBot',
        'Perplexity-User' => 'Perplexity-User',
        'Google-Extended' => 'Google-Extended',
        'GoogleOther' => 'GoogleOther',
        'Applebot-Extended' => 'Applebot-Extended',
        'Bingbot' => 'bingbot',
        'Amazonbot' => 'Amazonbot',
        'Bytespider' => 'Bytespider',
        'CCBot' => 'CCBot',
        'meta-externalagent' => 'meta-externalagent',
        'YouBot' => 'YouBot',
        'cohere-ai' => 'cohere-ai',
        'MistralAI-User' => 'MistralAI-User',
    ],

    // Başlık benzerliğinde atılan durak kelimeler (spec §3.4 adım 4)
    'stop_words' => [
        've', 'ile', 'için', 'bir', 'bu', 'da', 'de', 'en', 'ne', 'nasıl', 'nedir',
        'mi', 'mı', 'mu', 'mü', 'daha', 'çok', 'gibi', 'her', 'olan', 'olarak',
        'nelerdir', 'hangi', 'kadar', 'ise', 'ya', 'veya', 'ki', 'göre',
    ],
];
