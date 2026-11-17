<?php

namespace App\Services\Ai;

use App\Models\Brand;
use App\Models\District;
use App\Models\Province;
use App\Models\Service;
use App\Services\Seo\KeywordSections;
use App\Support\AutomationLog;
use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Model;

/**
 * Bölge ve hizmet sayfalarının içeriğini zenginleştirir (spec §1: AI yalnız içerik üretir).
 *
 * KURAL: mevcut benzersiz giriş metni KORUNUR, üzerine bölgeye özgü H2 bölümleri eklenir.
 * Şablonla çoğaltılmış içerik üretilmez; prompt'a yalnız O BÖLGEYE ait gerçek veriler verilir
 * (mahalle listesi, bağlı olduğu il, mevcut metin, sunulan hizmetler).
 *
 * Rakam, ödül, tecrübe yılı gibi doğrulanamaz iddialar YASAKTIR (spec §10.1) ve çıktı
 * ayrıca kod tarafında temizlenir.
 */
class RegionContentEnricher
{
    public function __construct(
        protected AiClient $ai,
        protected ContentSanitizer $sanitizer,
    ) {}

    /** @return array{words_before: int, words_after: int, h2: int} */
    public function enrich(Model $model): array
    {
        [$system, $user, $meta] = match (true) {
            $model instanceof District => $this->districtPrompt($model),
            $model instanceof Province => $this->provincePrompt($model),
            $model instanceof Service => $this->servicePrompt($model),
            $model instanceof Brand => $this->brandPrompt($model),
            default => throw new AiException('Bu içerik tipi zenginleştirilemiyor: '.$model::class),
        };

        $before = $this->sanitizer->wordCount($this->bodyOf($model));

        $payload = $this->ai->json('seo.content', $system, $user, $meta);

        $body = $this->sanitizer->bodyHtml($payload['body_html'] ?? null);

        if ($this->sanitizer->wordCount($body) < 200) {
            throw new AiException('Üretilen içerik çok kısa, kaydedilmedi.');
        }

        $body = $this->expandIfShort($body, $system, $meta);

        // Fırsat avının eklediği kelime bölümleri yeni metnin sonuna aynen geri konur;
        // yoksa her zenginleştirme o kelimeler için yapılan işi sessizce silerdi.
        [, $keywordBlocks] = KeywordSections::extract($this->bodyOf($model));

        if ($keywordBlocks) {
            $body .= "\n".implode("\n", $keywordBlocks);
        }

        // Marka metninin sonundaki hukuki not (<p><small>…</small></p>) varsa korunur.
        if ($model instanceof Brand && preg_match('/<p>\s*<small>.*?<\/small>\s*<\/p>\s*$/su', (string) $this->bodyOf($model), $notice)
            && ! str_contains($body, '<small>')) {
            $body .= "\n".trim($notice[0]);
        }

        // Mevcut daha uzun metin daha kısa bir AI metniyle DEĞİŞTİRİLMEZ (model "genişlet"
        // denince metni baştan yazıp kısaltabiliyor).
        if ($before > 0 && $this->sanitizer->wordCount($body) < $before) {
            throw new AiException('Yeni metin mevcut metinden kısa ('.$this->sanitizer->wordCount($body).' < '.$before.' kelime), kaydedilmedi.');
        }

        $updates = [$this->bodyField($model) => $body];

        // Meta açıklama 70-155 aralığına çekilir.
        if (filled($payload['meta_description'] ?? null)) {
            $updates['meta_description'] = $this->sanitizer->metaDescription($payload['meta_description'], $model->meta_description);
        }

        if (filled($payload['meta_title'] ?? null)) {
            $updates['meta_title'] = $this->sanitizer->metaTitle($payload['meta_title'], $model->meta_title);
        }

        $model->forceFill($updates)->save();

        $after = $this->sanitizer->wordCount($body);

        AutomationLog::summary('seo.enrich', [
            'type' => class_basename($model),
            'id' => $model->getKey(),
            'words_before' => $before,
            'words_after' => $after,
            'h2' => preg_match_all('/<h2[\s>]/i', $body),
        ], changed: true);

        return ['words_before' => $before, 'words_after' => $after, 'h2' => preg_match_all('/<h2[\s>]/i', $body)];
    }

    /**
     * Mevcut metni HİÇ DEĞİŞTİRMEDEN 600 kelimeye tamamlar: yalnız yeni bölümler eklenir.
     * Skoru hedefin üstünde olduğu için zenginleştirmeye girmeyen kısa sayfalar için.
     * Sondaki bloklar (ilgili sayfalar paragrafı, kelime bölümleri, marka hukuki notu)
     * yeni bölümlerin ARKASINDA kalır.
     *
     * @return array{words_before: int, words_after: int, h2: int}
     */
    public function lengthen(Model $model): array
    {
        if (! ($model instanceof District || $model instanceof Province || $model instanceof Service || $model instanceof Brand)) {
            throw new AiException('Bu içerik tipi otomatik uzatılmaz: '.$model::class);
        }

        $original = (string) $this->bodyOf($model);
        $before = $this->sanitizer->wordCount($original);

        if ($before < 150) {
            throw new AiException('Metin çok kısa ya da boş; önce zenginleştirme (metinsiz bölgeleri doldur) çalışmalı.');
        }

        [$main, $keywordBlocks] = KeywordSections::extract($original);

        $tail = [];
        $main = preg_replace_callback('#<p class="related-links">.*?</p>|<p>\s*<small>.*?</small>\s*</p>#su', function ($m) use (&$tail) {
            $tail[] = $m[0];

            return '';
        }, $main) ?? $main;

        $main = trim($main);
        $longer = $this->expandIfShort($main, $this->systemPrompt(), [
            'content_type' => strtolower(class_basename($model)),
            'content_id' => $model->getKey(),
            'batch_key' => 'lengthen.'.strtolower(class_basename($model)),
        ]);

        if ($longer === $main) {
            throw new AiException('Yeni bölüm üretilemedi, metin değişmedi.');
        }

        $body = implode("\n", array_filter([$longer, ...$keywordBlocks, ...$tail]));
        $model->forceFill([$this->bodyField($model) => $body])->save();

        $after = $this->sanitizer->wordCount($body);

        AutomationLog::summary('seo.lengthen', [
            'type' => class_basename($model), 'id' => $model->getKey(), 'words_before' => $before, 'words_after' => $after,
        ], changed: true);

        return ['words_before' => $before, 'words_after' => $after, 'h2' => preg_match_all('/<h2[\s>]/i', $body)];
    }

    /** Skorun "iyi içerik" eşiği 600 kelime; altında kalan metne yeni bölüm eklenir. */
    public const EXPAND_BELOW = 600;

    /**
     * Modeller istenen kelime sayısını tutturmaz (650-800 istenip ~300 gelebiliyor) ve
     * "genişlet" denince metni baştan yazıp yine kısa bırakır. Bu yüzden modelden YALNIZ
     * eklenecek yeni H2 bölümleri istenir ve mevcut metnin sonuna eklenir: metin her
     * turda kesin uzar. En fazla iki tur; çağrı başarısız olursa eldeki metin kalır.
     */
    private function expandIfShort(string $body, string $system, array $meta): string
    {
        for ($round = 1; $round <= 2; $round++) {
            $words = $this->sanitizer->wordCount($body);

            if ($words >= self::EXPAND_BELOW) {
                break;
            }

            preg_match_all('/<h2[^>]*>(.*?)<\/h2>/su', $body, $m);
            $need = max(250, 680 - $words);

            $user = implode("\n", [
                'GÖREV: Aşağıdaki sayfa metni '.$words.' kelime. Metnin SONUNA eklenecek 2-3 YENİ bölüm yaz.',
                '- Toplam EN AZ '.$need.' kelime. Her bölüm <h2> başlık + 2-3 paragraf (gerekirse <ul>).',
                '- Mevcut başlıkları TEKRARLAMA: '.implode(' | ', array_map(fn ($h) => trim(strip_tags($h)), $m[1] ?? [])),
                '- Konu önerileri: montaj öncesi hazırlık, ölçü ve yerleşim, duvara sabitleme ve güvenlik,',
                '  sökme-taşıma-yeniden kurulum, eksik parça ve hasar kontrolü, montaj sonrası bakım, sık yapılan hatalar.',
                '- Yeni bilgi UYDURMA; rakam, fiyat, süre VERME. Mevcut metni çıktıya KOYMA, yalnız yeni bölümler.',
                '- Çıktı yalnız JSON: {"body_html":""}',
                '',
                'MEVCUT METİN (bağlam için):',
                trim(strip_tags($body)),
            ]);

            try {
                $payload = $this->ai->json('seo.content', $system, $user, array_merge($meta, ['batch_key' => ($meta['batch_key'] ?? 'enrich').'.expand']));
                $extra = $this->sanitizer->bodyHtml($payload['body_html'] ?? null);
            } catch (\Throwable) {
                break;
            }

            if ($this->sanitizer->wordCount($extra) < 80) {
                break;
            }

            $body = rtrim($body)."\n".$extra;
        }

        return $body;
    }

    private function bodyField(Model $model): string
    {
        return $model instanceof Service ? 'description' : 'content';
    }

    private function bodyOf(Model $model): ?string
    {
        return $model->{$this->bodyField($model)};
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'Sen Trakya bölgesinde (Tekirdağ, Edirne, Kırklareli) mobilya montaj hizmeti veren bir firmanın',
            'SEO içerik yazarısın. Türkçe, sade ve güven veren bir dille yazarsın.',
            '',
            'MUTLAK KURALLAR:',
            '- ASLA UYDURMA. Rakam, yıl, müşteri sayısı, ödül, sertifika, "20 yıllık tecrübe", "%98 memnuniyet"',
            '  gibi doğrulanamaz hiçbir ifade kullanma. Yalnız sana verilen gerçek bilgileri kullan.',
            '- Verilmeyen mahalle, ilçe veya yer adı UYDURMA.',
            '- Fiyat rakamı verme. Fiyatın işe başlamadan önce net olarak bildirildiğini söyleyebilirsin.',
            '- <a> etiketi KULLANMA. Hiçbir link ekleme; linkleri ayrı bir sistem basar.',
            '- <h1> kullanma; sayfanın tek H1 başlığını şablon basar. Ara başlıklar <h2>, alt başlıklar <h3>.',
            '- Pazarlama klişesi ve abartı kullanma. Okuyucunun işine yarayan somut bilgi ver.',
            '',
            'ÇIKTI: yalnızca geçerli JSON. Markdown, kod bloğu veya açıklama ekleme.',
            'Şema: {"body_html":"","meta_title":"","meta_description":""}',
        ]);
    }

    /** @return array{0: string, 1: string, 2: array} */
    private function districtPrompt(District $district): array
    {
        $province = $district->province;
        $services = Service::query()->where('is_active', true)->pluck('title')->all();
        $neighborhoods = (array) $district->neighborhoods;

        $lines = [
            'GÖREV: Aşağıdaki ilçe sayfasının içeriğini genişlet.',
            '',
            'İLÇE: '.$district->name,
            'İL: '.$province?->name,
            '',
            'MEVCUT METİN (bu metnin bilgilerini KORU, sil veya çelişme — üzerine ekleyerek genişlet):',
            strip_tags(KeywordSections::extract($district->content)[0]),
            '',
            'BU İLÇEDE HİZMET VERDİĞİMİZ MAHALLE/KÖYLER (yalnız bunları kullan, yenisini uydurma):',
            implode(', ', $neighborhoods),
            '',
            'SUNDUĞUMUZ HİZMETLER:',
            '- '.implode("\n- ", $services),
        ];

        if ($faqs = (array) $district->faqs) {
            $lines[] = '';
            $lines[] = 'BU İLÇE İÇİN BİLİNEN SORU-CEVAPLAR (bilgi kaynağı olarak kullan, aynen tekrarlama):';

            foreach ($faqs as $faq) {
                $lines[] = '- '.($faq['question'] ?? '').' → '.($faq['answer'] ?? '');
            }
        }

        $lines[] = '';
        $lines[] = 'İSTENEN YAPI (650-800 kelime, EN AZ 600):';
        $lines[] = '1. Mevcut metinden gelen giriş paragrafları (genişletilmiş hâli, H2 başlığı olmadan)';
        $lines[] = '2. <h2>'.$district->name.' ilçesinde en çok talep edilen montaj işleri</h2>';
        $lines[] = '   Yukarıdaki hizmet listesinden bu ilçenin yapısına uyanları anlat.';
        $lines[] = '3. <h2>Randevu ve ulaşım</h2>';
        $lines[] = '   Bu ilçeye nasıl ve ne kadar sürede gelindiği; mevcut metindeki bilgiyi kullan.';
        $lines[] = '4. <h2>Montaj öncesi hazırlık</h2>';
        $lines[] = '   Müşterinin ne hazırlaması gerektiği; somut ve kısa maddeler (<ul><li>).';
        $lines[] = '';
        $lines[] = 'ÖNEMLİ: Bu metin yalnız '.$district->name.' için yazılıyor. Başka bir ilçeye kopyalanabilecek';
        $lines[] = 'genel bir metin OLMASIN; ilçenin kendi özelliklerine (mahalleleri, yapısı, konumu) değin.';
        $lines[] = '';
        $lines[] = 'Şu hizmet adlarından en az ikisi metinde AYNEN (bu yazımla) geçsin; her biri ilgili hizmet sayfasına bağlanır:';
        $lines[] = $this->serviceAnchors();
        $lines[] = '';
        $lines[] = 'meta_title: en fazla 60 karakter, "'.$district->name.'" geçsin.';
        $lines[] = 'meta_description: 120-150 karakter arası, "'.$district->name.'" geçsin, harekete geçirici olsun.';

        return [
            $this->systemPrompt(),
            implode("\n", $lines),
            ['content_type' => 'district', 'content_id' => $district->getKey(), 'batch_key' => 'enrich.district'],
        ];
    }

    /** @return array{0: string, 1: string, 2: array} */
    private function provincePrompt(Province $province): array
    {
        $districts = $province->activeDistricts()->pluck('name')->all();
        $services = Service::query()->where('is_active', true)->pluck('title')->all();

        $lines = [
            'GÖREV: Aşağıdaki il sayfasının içeriğini genişlet.',
            '',
            'İL: '.$province->name,
            '',
            'MEVCUT METİN (bilgilerini KORU, üzerine ekleyerek genişlet):',
            strip_tags(KeywordSections::extract($province->content)[0]),
            '',
            'BU İLDE HİZMET VERDİĞİMİZ AKTİF İLÇELER (yalnız bunları say, başka ilçe UYDURMA):',
            implode(', ', $districts),
            '',
            'SUNDUĞUMUZ HİZMETLER:',
            '- '.implode("\n- ", $services),
            '',
            'İSTENEN YAPI (650-800 kelime, EN AZ 600):',
            '1. Giriş paragrafları (H2 başlığı olmadan)',
            '2. <h2>'.$province->name.' genelinde sunduğumuz montaj hizmetleri</h2>',
            '3. <h2>Hizmet verdiğimiz ilçeler</h2> — yalnız yukarıdaki ilçeleri anlat',
            '4. <h2>Randevu süreci</h2> — fotoğrafla fiyat, randevu planlama, montaj günü',
            '',
            'Şu ifadelerden en az üçü metinde AYNEN (bu yazımla) geçsin; her biri ilgili ilçe sayfasına bağlanır:',
            $this->districtAnchors($districts),
            'meta_title: en fazla 60 karakter. meta_description: 120-150 karakter.',
        ];

        return [
            $this->systemPrompt(),
            implode("\n", $lines),
            ['content_type' => 'province', 'content_id' => $province->getKey(), 'batch_key' => 'enrich.province'],
        ];
    }

    /** @return array{0: string, 1: string, 2: array} */
    private function servicePrompt(Service $service): array
    {
        $lines = [
            'GÖREV: Aşağıdaki hizmet sayfasının açıklama metnini genişlet.',
            '',
            'HİZMET: '.$service->title,
            'KISA AÇIKLAMA: '.$service->short_description,
            '',
            'MEVCUT METİN (bilgilerini KORU, üzerine ekleyerek genişlet):',
            strip_tags(KeywordSections::extract($service->description)[0]),
            '',
            'BU HİZMETTE YAPTIKLARIMIZ (sayfada ayrı bir liste olarak zaten var, metinde TEKRARLAMA;',
            'bunları bağlam olarak kullan):',
            '- '.implode("\n- ", (array) $service->what_we_do),
            '',
            'HİZMET BÖLGESİ: Tekirdağ, Edirne ve Kırklareli (Trakya).',
            '',
            'İSTENEN YAPI (650-800 kelime, EN AZ 600):',
            '1. Giriş paragrafları (H2 başlığı olmadan)',
            '2. <h2>Nasıl çalışıyoruz?</h2> — montaj günü adım adım ne olduğu',
            '3. <h2>Sık karşılaşılan durumlar</h2> — bu hizmete özgü gerçek sorunlar ve çözümleri',
            '4. <h2>Süre ve fiyatı ne belirler?</h2> — RAKAM VERME, yalnız fiyatı etkileyen etkenleri say',
            '',
            'meta_title: en fazla 60 karakter. meta_description: 120-150 karakter.',
        ];

        return [
            $this->systemPrompt(),
            implode("\n", $lines),
            ['content_type' => 'service', 'content_id' => $service->getKey(), 'batch_key' => 'enrich.service'],
        ];
    }

    /**
     * İç link kurallarıyla BİREBİR aynı ilçe ifadeleri (RuleBuilder: "{ilçe} mobilya montaj").
     * Metinde bu yazımla geçerse links:apply ilgili ilçe sayfasına bağlar.
     *
     * @param  array<int, string>  $districts
     */
    private function districtAnchors(array $districts): string
    {
        return collect($districts)->take(8)->map(fn (string $name) => '"'.$name.' mobilya montaj"')->implode(', ').'.';
    }

    /** İç link kurallarıyla birebir aynı hizmet adları (RuleBuilder: hizmet başlığı). */
    private function serviceAnchors(): string
    {
        return Service::query()->where('is_active', true)->orderBy('sort_order')->limit(6)->pluck('title')
            ->map(fn (string $title) => '"'.TurkishText::lower($title).'"')
            ->implode(', ').'.';
    }

    /** @return array{0: string, 1: string, 2: array} */
    private function brandPrompt(Brand $brand): array
    {
        $existing = trim(strip_tags(KeywordSections::extract($brand->content)[0]));

        $lines = [
            'GÖREV: Aşağıdaki marka sayfasının metnini genişlet. Sayfa, bu markanın mobilyalarını aldıktan sonra',
            'kurulum/montaj desteği arayanlara yöneliktir; biz bağımsız montaj hizmeti veriyoruz.',
            '',
            'MARKA: '.$brand->name,
            'KISA AÇIKLAMA: '.$brand->description,
            '',
            'MEVCUT METİN (bilgilerini KORU, üzerine ekleyerek genişlet):',
            $existing !== '' ? $existing : '(Henüz gövde metni yok. Kısa açıklama: '.trim((string) $brand->description).')',
            '',
            'MONTAJINI YAPTIĞIMIZ ÜRÜNLER (sayfada ayrı liste olarak zaten var, metinde TEKRARLAMA; bağlam olarak kullan):',
            '- '.implode("\n- ", (array) $brand->products),
            '',
            'MARKA HAKKINDA İDDİA YASAK: markanın kalitesi, pazar payı, satış rakamı, sıralaması, ödülleri',
            'veya "en iyi/en çok tercih edilen" gibi yorumlar YAZMA. Yetkili servis olduğumuzu ima ETME.',
            'Markanın kendisini değil, bu markanın ürünlerini MONTE EDERKEN yaptığımız işi anlat.',
            '',
            'HİZMET BÖLGESİ: Tekirdağ, Edirne ve Kırklareli (Trakya).',
            '',
            'İSTENEN YAPI (600-750 kelime, EN AZ 600):',
            '1. Giriş paragrafları (H2 başlığı olmadan): '.$brand->name.' mobilyalarının kurulumu için kısa bir çerçeve.',
            '2. <h2>'.$brand->name.' ürünlerinde montaj nasıl yapılır?</h2> — paket açma, parça kontrolü, kurulum sırası',
            '3. <h2>Dikkat edilmesi gerekenler</h2> — duvara sabitleme, menteşe/ray ayarı, eksik parça durumunda ne yapılır',
            '4. <h2>Randevu ve hazırlık</h2> — fotoğrafla fiyat, montaj günü öncesi hazırlık',
            '',
            'meta_title: en fazla 60 karakter, "'.$brand->name.'" ve "montaj" geçsin. meta_description: 120-150 karakter.',
        ];

        return [
            $this->systemPrompt(),
            implode("\n", $lines),
            ['content_type' => 'brand', 'content_id' => $brand->getKey(), 'batch_key' => 'enrich.brand'],
        ];
    }
}
