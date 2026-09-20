<?php

namespace App\Services\Ai;

use App\Models\District;
use App\Models\Province;
use App\Models\Service;
use App\Support\AutomationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
            default => throw new AiException('Bu içerik tipi zenginleştirilemiyor: '.$model::class),
        };

        $before = $this->sanitizer->wordCount($this->bodyOf($model));

        $payload = $this->ai->json('seo.content', $system, $user, $meta);

        $body = $this->sanitizer->bodyHtml($payload['body_html'] ?? null);

        if ($this->sanitizer->wordCount($body) < 200) {
            throw new AiException('Üretilen içerik çok kısa, kaydedilmedi.');
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
            strip_tags((string) $district->content),
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
        $lines[] = 'İSTENEN YAPI (400-550 kelime):';
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
            strip_tags((string) $province->content),
            '',
            'BU İLDE HİZMET VERDİĞİMİZ AKTİF İLÇELER (yalnız bunları say, başka ilçe UYDURMA):',
            implode(', ', $districts),
            '',
            'SUNDUĞUMUZ HİZMETLER:',
            '- '.implode("\n- ", $services),
            '',
            'İSTENEN YAPI (400-550 kelime):',
            '1. Giriş paragrafları (H2 başlığı olmadan)',
            '2. <h2>'.$province->name.' genelinde sunduğumuz montaj hizmetleri</h2>',
            '3. <h2>Hizmet verdiğimiz ilçeler</h2> — yalnız yukarıdaki ilçeleri anlat',
            '4. <h2>Randevu süreci</h2> — fotoğrafla fiyat, randevu planlama, montaj günü',
            '',
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
            strip_tags((string) $service->description),
            '',
            'BU HİZMETTE YAPTIKLARIMIZ (sayfada ayrı bir liste olarak zaten var, metinde TEKRARLAMA;',
            'bunları bağlam olarak kullan):',
            '- '.implode("\n- ", (array) $service->what_we_do),
            '',
            'HİZMET BÖLGESİ: Tekirdağ, Edirne ve Kırklareli (Trakya).',
            '',
            'İSTENEN YAPI (400-550 kelime):',
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
}
