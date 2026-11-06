<?php

namespace App\Services\Seo;

use App\Models\District;
use App\Models\InternalLinkRule;
use App\Models\Province;
use App\Models\SeoAnalysis;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordAction;
use App\Models\Service;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiException;
use App\Services\Ai\ContentSanitizer;
use App\Services\Google\IndexingService;
use App\Services\InternalLink\LinkApplier;
use App\Support\AutomationLog;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Kampanyadaki kelimenin bağlı olduğu sayfayı o kelime için güçlendirir.
 *
 * tavsiyemiz'deki 12 adımlık playbook'tan montaj'a uyan beşi alındı:
 *   meta       sayfa başlığı ve açıklaması (AI) — sayfanın ANA kelimesi için, 30 günde bir
 *   content    kelimeye özel H2 bölümü (AI) — kelime başına bir kez, sayfa başına tavanlı
 *   faq        kelimeyi soran 2 soru-cevap (AI) — kelime başına bir kez
 *   link_rule  iç link kuralı: anchor = kelime → sayfa (deterministik)
 *   index      sayfada yeni değişiklik varsa Google'a bildirim
 *
 * Bilerek ALINMAYANLAR:
 *   - Destek blogu: para sayfasıyla aynı kelimeyi hedefleyen blog, sayfanın kendi
 *     kelimesini yer (spec §3.1 tek sahip kuralı). tavsiyemiz bunu yapıyordu.
 *   - Canlı SERP / rakip başlık kazıma: dış siteye istek ve kırılgan HTML ayrıştırma;
 *     yerel kelimelerde rakip başlıkları zaten "armut.com" listeleri.
 *
 * KURALLAR:
 *   - Hedefteki (ilk N) ve duraklatılmış kelimeye HİÇBİR şey yapılmaz.
 *   - Her yazma işlemi before/after ile kaydedilir ve geri alınabilir.
 *   - AI çıktısı kod tarafında denetlenir: kelime metinde yoksa kaydedilmez.
 */
class RankBooster
{
    public const STEPS = ['meta', 'content', 'faq', 'link_rule', 'index'];

    private const AI_STEPS = ['meta', 'content', 'faq'];

    private const WRITE_STEPS = ['meta', 'content', 'faq'];

    /** Aynı sayfanın başlığı en erken bu kadar gün sonra yeniden yazılır. */
    private const META_COOLDOWN_DAYS = 30;

    public function __construct(
        protected AiClient $ai,
        protected ContentSanitizer $sanitizer,
        protected IndexingService $indexing,
        protected LinkApplier $links,
    ) {}

    // ---------- sayfa ----------

    /** Kelimenin yazılabilir sayfası: ilçe, il ya da hizmet. Blog ve statik sayfa yazılmaz. */
    public function page(SeoKeyword $keyword): District|Province|Service|null
    {
        $target = $keyword->target;

        if (! $target || ! $target->status) {
            return null;
        }

        return match ($target->content_type) {
            'district' => District::with('province')->find($target->content_id),
            'province' => Province::find($target->content_id),
            'service' => Service::find($target->content_id),
            default => null,
        };
    }

    private function bodyField(Model $page): string
    {
        return $page instanceof Service ? 'description' : 'content';
    }

    private function pageUrl(SeoKeyword $keyword): ?string
    {
        return $keyword->target?->url ? PathNormalizer::normalize($keyword->target->url) : null;
    }

    /** Aynı sayfayı hedefleyen kelimeler arasında başlığın sahibi: en çok gösterim alan. */
    public function primaryKeyword(SeoKeyword $keyword): ?SeoKeyword
    {
        if (! $keyword->target_id) {
            return null;
        }

        return SeoKeyword::query()
            ->inCampaign()
            ->where('target_id', $keyword->target_id)
            ->where('campaign_status', '!=', SeoKeyword::CAMPAIGN_PAUSED)
            ->orderByDesc('gsc_impressions')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    // ---------- durum ----------

    /**
     * Kampanya neden işlenemiyor? null = işlenebilir.
     */
    public function blocker(SeoKeyword $keyword): ?string
    {
        return match (true) {
            $keyword->campaign_status === SeoKeyword::CAMPAIGN_PAUSED => 'Kampanya duraklatıldı.',
            $keyword->campaign_status === SeoKeyword::CAMPAIGN_WON || $keyword->reachedTarget() => 'Kelime hedefte (ilk '.$keyword->targetPosition().'). Hedefteki kelimeye işlem yapılmaz.',
            $keyword->owner_blog_id !== null => 'Kelime bir blog yazısına ait; blog yazıları buradan düzenlenmez.',
            $this->page($keyword) === null => 'Kelimeye bağlı yayında bir ilçe, il veya hizmet sayfası yok. "Sayfaya bağla" ile seçin.',
            default => null,
        };
    }

    /**
     * Adım başına durum: todo (yapılacak) | done (tamam) | blocked (yapılamaz).
     *
     * @return array<string, array{state: string, reason: string}>
     */
    public function diagnose(SeoKeyword $keyword): array
    {
        $page = $this->page($keyword);
        $url = $this->pageUrl($keyword);
        $out = [];

        foreach (self::STEPS as $step) {
            if (! $page && $step !== 'link_rule') {
                $out[$step] = ['state' => 'blocked', 'reason' => 'Bağlı sayfa yok'];

                continue;
            }

            if (in_array($step, self::AI_STEPS, true) && ! $this->ai->isConfigured()) {
                $out[$step] = ['state' => 'blocked', 'reason' => 'AI anahtarı tanımlı değil'];

                continue;
            }

            $out[$step] = match ($step) {
                'meta' => $this->diagnoseMeta($keyword, $page, $url),
                'content' => $this->diagnoseContent($keyword, $url),
                'faq' => $this->diagnoseFaq($keyword, $page),
                'link_rule' => $this->diagnoseLinkRule($keyword, $url),
                'index' => $this->diagnoseIndex($url),
            };
        }

        return $out;
    }

    private function diagnoseMeta(SeoKeyword $keyword, Model $page, string $url): array
    {
        $primary = $this->primaryKeyword($keyword);

        if ($primary && ! $primary->is($keyword)) {
            return ['state' => 'done', 'reason' => 'Sayfa başlığı "'.$primary->keyword.'" kelimesine ayrılmış'];
        }

        if ($this->covers($keyword->keyword, (string) $page->meta_title)) {
            return ['state' => 'done', 'reason' => 'Kelime başlıkta geçiyor'];
        }

        if ($this->lastAction('meta', pageUrl: $url)?->created_at?->gt(now()->subDays(self::META_COOLDOWN_DAYS))) {
            return ['state' => 'done', 'reason' => 'Başlık son '.self::META_COOLDOWN_DAYS.' günde yazıldı'];
        }

        return ['state' => 'todo', 'reason' => 'Kelime sayfa başlığında yok'];
    }

    private function diagnoseContent(SeoKeyword $keyword, string $url): array
    {
        if ($this->lastAction('content', keyword: $keyword)) {
            return ['state' => 'done', 'reason' => 'Kelimeye özel bölüm eklendi'];
        }

        $max = SeoConfig::int('opportunity_max_sections_per_page', 4, 1, 20);

        if ($this->doneActions('content', pageUrl: $url)->count() >= $max) {
            return ['state' => 'blocked', 'reason' => "Sayfada zaten {$max} kelime bölümü var"];
        }

        return ['state' => 'todo', 'reason' => 'Sayfada bu kelimeye ayrılmış bölüm yok'];
    }

    private function diagnoseFaq(SeoKeyword $keyword, Model $page): array
    {
        $faqs = (array) $page->faqs;

        foreach ($faqs as $faq) {
            if ($this->covers($keyword->keyword, (string) ($faq['question'] ?? ''))) {
                return ['state' => 'done', 'reason' => 'Kelimeyi soran bir soru var'];
            }
        }

        if ($this->lastAction('faq', keyword: $keyword)) {
            return ['state' => 'done', 'reason' => 'Soru-cevap eklendi'];
        }

        $max = SeoConfig::int('opportunity_max_faqs', 10, 2, 30);

        if (count($faqs) >= $max) {
            return ['state' => 'blocked', 'reason' => "Sayfada zaten {$max} soru var"];
        }

        return ['state' => 'todo', 'reason' => 'Kelimeyi soran soru yok'];
    }

    private function diagnoseLinkRule(SeoKeyword $keyword, ?string $url): array
    {
        if (! $url) {
            return ['state' => 'blocked', 'reason' => 'Bağlı sayfa yok'];
        }

        if ($this->linkRule($keyword, $url)?->is_active) {
            return ['state' => 'done', 'reason' => $this->links->enabled()
                ? 'Kural var'
                : 'Kural var; iç link motoru kapalı olduğu için henüz uygulanmıyor'];
        }

        return ['state' => 'todo', 'reason' => 'Bu kelimeyle sayfaya link veren kural yok'];
    }

    private function diagnoseIndex(string $url): array
    {
        if (! $this->indexing->isConfigured()) {
            return ['state' => 'blocked', 'reason' => 'Google bağlantısı yok'];
        }

        $lastWrite = SeoKeywordAction::query()
            ->where('page_url', $url)->whereIn('action', self::WRITE_STEPS)->where('status', SeoKeywordAction::DONE)
            ->latest('id')->first();

        $lastPing = $this->lastAction('index', pageUrl: $url);

        if (! $lastWrite || ($lastPing && $lastPing->id > $lastWrite->id)) {
            return ['state' => 'done', 'reason' => 'Sayfada bildirilecek yeni değişiklik yok'];
        }

        // Yetki hatası (403) kendiliğinden düzelmez: her iyileştirmede yeniden denemek yalnız
        // log'u doldurur. Son başarısız deneme 7 gün içindeyse adım beklemeye alınır.
        $lastFailure = SeoKeywordAction::query()
            ->where('page_url', $url)->where('action', 'index')->where('status', SeoKeywordAction::FAILED)
            ->latest('id')->first();

        if ($lastFailure && $lastFailure->id > ($lastPing->id ?? 0) && $lastFailure->created_at?->gt(now()->subDays(7))
            && str_contains((string) $lastFailure->message, '403')) {
            return ['state' => 'blocked', 'reason' => 'Google bildirime izin vermedi (403): servis hesabı Search Console\'da "Sahip" olmalı'];
        }

        if ($this->indexing->remainingToday() < 1) {
            return ['state' => 'blocked', 'reason' => 'Günlük bildirim kotası doldu'];
        }

        return ['state' => 'todo', 'reason' => 'Sayfa değişti, Google\'a bildirilecek'];
    }

    // ---------- çalıştırma ----------

    /**
     * Yapılacak tüm adımları sırayla çalıştırır. Bir AI adımı başarısız olursa diğer
     * adımlar yine denenir; biten adımlar kayıtlı kalır, tekrar çalıştırınca kaldığı
     * yerden devam eder.
     *
     * @return array{ran: array<int, string>, failed: array<int, string>, blocked: string|null}
     */
    public function boost(SeoKeyword $keyword): array
    {
        $result = ['ran' => [], 'failed' => [], 'blocked' => $this->blocker($keyword)];

        if ($result['blocked']) {
            return $result;
        }

        foreach (self::STEPS as $step) {
            // Durum her adımdan önce yeniden okunur: "Google'a bildir" adımı ancak önceki
            // adımlar sayfayı değiştirdikten SONRA yapılacak iş hâline gelir.
            if ($this->diagnose($keyword)[$step]['state'] !== 'todo') {
                continue;
            }

            $action = $this->runStep($keyword, $step);
            $result[$action->status === SeoKeywordAction::DONE ? 'ran' : 'failed'][] = $step;
        }

        $keyword->forceFill(['last_boost_at' => now()])->save();

        AutomationLog::summary('seo.opportunity.boost', [
            'keyword' => $keyword->keyword,
            'position' => $keyword->last_position,
            'ran' => $result['ran'],
        ], errors: array_map(fn ($s) => $s.' başarısız', $result['failed']), changed: $result['ran'] !== []);

        return $result;
    }

    public function runStep(SeoKeyword $keyword, string $step): SeoKeywordAction
    {
        if ($blocked = $this->blocker($keyword)) {
            throw new RuntimeException($blocked);
        }

        $page = $this->page($keyword);
        $url = $this->pageUrl($keyword);

        try {
            [$message, $before, $after] = match ($step) {
                'meta' => $this->writeMeta($keyword, $page),
                'content' => $this->writeContent($keyword, $page),
                'faq' => $this->writeFaq($keyword, $page),
                'link_rule' => $this->writeLinkRule($keyword, $url),
                'index' => $this->ping($keyword, $url),
                default => throw new RuntimeException('Bilinmeyen adım: '.$step),
            };

            $status = SeoKeywordAction::DONE;
        } catch (Throwable $e) {
            [$message, $before, $after, $status] = [$e->getMessage(), null, null, SeoKeywordAction::FAILED];
            AutomationLog::error('seo.opportunity.'.$step, $e->getMessage(), ['keyword' => $keyword->keyword]);
        }

        return SeoKeywordAction::create([
            'keyword_id' => $keyword->getKey(),
            'action' => $step,
            'status' => $status,
            'message' => mb_substr((string) $message, 0, 480),
            'before' => $before,
            'after' => $after,
            'rank_before' => $keyword->last_position,
            'page_url' => $url,
        ]);
    }

    /** @return array{0: string, 1: array, 2: array} */
    private function writeMeta(SeoKeyword $keyword, Model $page): array
    {
        $payload = $this->ai->json('seo.opportunity.meta', $this->systemPrompt(), implode("\n", [
            'GÖREV: Aşağıdaki sayfanın Google\'da görünen başlığını ve açıklamasını, verilen arama kelimesinde',
            'üst sıralara çıkacak şekilde yeniden yaz.',
            '',
            'ARAMA KELİMESİ: '.$keyword->keyword,
            ...$this->pageFacts($keyword, $page),
            '',
            'MEVCUT BAŞLIK: '.($page->meta_title ?: '(yok)'),
            'MEVCUT AÇIKLAMA: '.($page->meta_description ?: '(yok)'),
            '',
            'KURALLAR:',
            '- meta_title en fazla 60 karakter. Arama kelimesi başlığın BAŞINDA ve aynı kelimelerle geçsin;',
            '  Türkçe ek alabilir ("Çerkezköy Mobilya Montajı" gibi).',
            '- meta_description 120-155 karakter. Kelime doğal geçsin; fotoğrafla hızlı fiyat ve WhatsApp\'tan teklif',
            '  gibi gerçek bir fayda ve eylem çağrısı içersin.',
            '- Rakam, yıl, puan, "en iyi", "1 numara" gibi doğrulanamaz iddia YOK.',
            '',
            'Şema: {"meta_title":"","meta_description":""}',
        ]), ['content_type' => $keyword->target?->content_type, 'content_id' => $keyword->target?->content_id, 'batch_key' => 'opportunity.meta']);

        $title = $this->sanitizer->metaTitle($payload['meta_title'] ?? null, $page->meta_title);
        $description = $this->sanitizer->metaDescription($payload['meta_description'] ?? null, $page->meta_description);

        // Modele güvenilmez (spec §10.1): kelime başlıkta yoksa kaydedilmez.
        if (! $this->covers($keyword->keyword, $title)) {
            throw new AiException('Model kelimeyi başlığa koymadı ("'.$title.'"); kaydedilmedi.');
        }

        $before = ['meta_title' => $page->meta_title, 'meta_description' => $page->meta_description];
        $after = ['meta_title' => $title, 'meta_description' => $description];
        $page->forceFill($after)->save();

        return ['Başlık: '.$title, $before, $after];
    }

    /** @return array{0: string, 1: array, 2: array} */
    private function writeContent(SeoKeyword $keyword, Model $page): array
    {
        $field = $this->bodyField($page);

        $payload = $this->ai->json('seo.opportunity.content', $this->systemPrompt(), implode("\n", [
            'GÖREV: Aşağıdaki sayfaya, verilen arama kelimesini yanıtlayan TEK bir bölüm ekle.',
            '',
            'ARAMA KELİMESİ: '.$keyword->keyword,
            ...$this->pageFacts($keyword, $page),
            '',
            'SAYFADA ZATEN OLAN METİN (bunu TEKRARLAMA, yeni bilgi ve açı getir):',
            mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $page->{$field})) ?? ''), 0, 2500),
            '',
            'İSTENEN:',
            '- Bir <h2> başlığı: arama kelimesinin kelimeleri başlıkta geçsin (Türkçe ek alabilir).',
            '- Altında 150-250 kelime: 2-3 <p> paragrafı, istersen bir <ul> listesi.',
            '- Yalnız yukarıdaki gerçek bilgileri kullan (bölge, mahalleler, hizmetler).',
            '- Başka bir bölgeye kopyalanabilecek genel metin yazma; bu bölgenin adını ve mahallelerini kullan.',
            '',
            'Şema: {"html":""}',
        ]), ['content_type' => $keyword->target?->content_type, 'content_id' => $keyword->target?->content_id, 'batch_key' => 'opportunity.content']);

        $html = $this->sanitizer->bodyHtml($payload['html'] ?? null);

        if (! preg_match('#<h2\b[^>]*>(.*?)</h2>#is', $html, $heading)) {
            throw new AiException('Üretilen bölümde H2 başlığı yok; kaydedilmedi.');
        }

        if ($this->sanitizer->wordCount($html) < 100) {
            throw new AiException('Üretilen bölüm çok kısa; kaydedilmedi.');
        }

        if (! $this->covers($keyword->keyword, strip_tags($heading[1]))) {
            throw new AiException('Model kelimeyi bölüm başlığına koymadı; kaydedilmedi.');
        }

        $block = KeywordSections::wrap($keyword->keyword, $html);
        $page->forceFill([$field => trim((string) $page->{$field})."\n".$block])->save();

        return ['Bölüm eklendi: '.strip_tags($heading[1]), ['field' => $field], ['field' => $field, 'html' => $block]];
    }

    /** @return array{0: string, 1: array, 2: array} */
    private function writeFaq(SeoKeyword $keyword, Model $page): array
    {
        $existing = (array) $page->faqs;
        $room = SeoConfig::int('opportunity_max_faqs', 10, 2, 30) - count($existing);

        $known = array_map(fn ($f) => '- '.($f['question'] ?? '').' → '.($f['answer'] ?? ''), $existing);

        $payload = $this->ai->json('seo.opportunity.faq', $this->systemPrompt(), implode("\n", [
            'GÖREV: Bu sayfa için, verilen arama kelimesini arayan birinin soracağı 2 soru ve cevabını yaz.',
            '',
            'ARAMA KELİMESİ: '.$keyword->keyword,
            ...$this->pageFacts($keyword, $page),
            '',
            'SAYFADAKİ MEVCUT SORULAR (aynısını sorma; cevaplarındaki bilgiyi kaynak olarak kullanabilirsin):',
            $known ? implode("\n", $known) : '(yok)',
            '',
            'KURALLAR:',
            '- Her sorunun içinde arama kelimesinin kelimeleri geçsin (Türkçe ek alabilir).',
            '- Cevaplar 40-80 kelime, düz metin.',
            '- Fiyat rakamı, süre garantisi, yıl, müşteri sayısı YOK. Bilinmeyeni genel bırak.',
            '',
            'Şema: {"faqs":[{"question":"","answer":""}]}',
        ]), ['content_type' => $keyword->target?->content_type, 'content_id' => $keyword->target?->content_id, 'batch_key' => 'opportunity.faq']);

        $seen = array_map(fn ($f) => TurkishText::fold($f['question'] ?? ''), $existing);
        $added = [];

        foreach ($this->sanitizer->faqs($payload['faqs'] ?? [], 2) as $faq) {
            $fold = TurkishText::fold($faq['question']);

            if (count($added) >= $room || in_array($fold, $seen, true) || ! $this->covers($keyword->keyword, $faq['question'])) {
                continue;
            }

            $seen[] = $fold;
            $added[] = $faq;
        }

        if ($added === []) {
            throw new AiException('Model kelimeyi soran yeni bir soru üretmedi; kaydedilmedi.');
        }

        $page->forceFill(['faqs' => array_merge($existing, $added)])->save();

        return [count($added).' soru eklendi: '.$added[0]['question'], [], ['faqs' => $added]];
    }

    /** @return array{0: string, 1: array|null, 2: array} */
    private function writeLinkRule(SeoKeyword $keyword, string $url): array
    {
        $rule = $this->linkRule($keyword, $url);
        $before = $rule ? ['id' => $rule->getKey(), 'is_active' => (bool) $rule->is_active] : null;

        $rule ??= new InternalLinkRule([
            'anchor_text' => $keyword->keyword,
            'target_url' => $url,
            'scope_type' => 'all',
            'priority' => 75,
            'max_per_article' => 1,
        ]);

        $rule->is_active = true;
        $rule->save();

        $message = '"'.$keyword->keyword.'" → '.$url;

        return [
            $this->links->enabled() ? $message : $message.' (iç link motoru kapalı; açılınca uygulanır)',
            $before,
            ['id' => $rule->getKey()],
        ];
    }

    /** @return array{0: string, 1: null, 2: array} */
    private function ping(SeoKeyword $keyword, string $url): array
    {
        $this->indexing->submit(url($url));

        // Günlük kota seo_analyses üzerinden sayılır; gece görevi de aynı sayfayı tekrar bildirmesin.
        if ($target = $keyword->target) {
            SeoAnalysis::query()
                ->where('content_type', $target->content_type)
                ->where('content_id', $target->content_id)
                ->get()
                ->each(fn (SeoAnalysis $a) => $a->forceFill([
                    'index_submitted_at' => now(),
                    'index_submit_count' => $a->index_submit_count + 1,
                    'index_submit_error' => null,
                ])->saveQuietly());
        }

        return ['Google\'a bildirildi: '.url($url), null, ['url' => url($url)]];
    }

    // ---------- geri alma ----------

    /**
     * Bir işlemi geri alır. Sayfa işlemden sonra elle değiştirildiyse REDDEDER:
     * sonraki düzenlemeyi ezmek, geri almaktan daha büyük bir zarardır.
     */
    public function revert(SeoKeywordAction $action): string
    {
        if (! $action->isRevertible()) {
            throw new RuntimeException('Bu işlem geri alınamaz.');
        }

        $keyword = $action->keyword;
        $page = $action->action === 'link_rule' ? null : $this->page($keyword);

        if ($action->action !== 'link_rule' && ! $page) {
            throw new RuntimeException('Sayfa bulunamadı.');
        }

        $message = match ($action->action) {
            'meta' => $this->revertMeta($action, $page),
            'content' => $this->revertContent($action, $page),
            'faq' => $this->revertFaq($action, $page),
            'link_rule' => $this->revertLinkRule($action),
        };

        $action->forceFill(['status' => SeoKeywordAction::REVERTED])->save();

        return $message;
    }

    private function revertMeta(SeoKeywordAction $action, Model $page): string
    {
        $after = (array) $action->after;

        if ($page->meta_title !== ($after['meta_title'] ?? null) || $page->meta_description !== ($after['meta_description'] ?? null)) {
            throw new RuntimeException('Başlık bu işlemden sonra değiştirilmiş; üzerine yazılmadı.');
        }

        $page->forceFill(array_intersect_key((array) $action->before, $after))->save();

        return 'Eski başlık ve açıklama geri yüklendi.';
    }

    private function revertContent(SeoKeywordAction $action, Model $page): string
    {
        $field = $action->after['field'] ?? $this->bodyField($page);
        $body = (string) $page->{$field};
        $stripped = KeywordSections::remove($body, $action->keyword->keyword, $action->after['html'] ?? null);

        if ($stripped === $body) {
            throw new RuntimeException('Bölüm sayfada bulunamadı (elle düzenlenmiş olabilir).');
        }

        $page->forceFill([$field => $stripped])->save();

        return 'Bölüm sayfadan kaldırıldı.';
    }

    private function revertFaq(SeoKeywordAction $action, Model $page): string
    {
        $remove = array_map(fn ($f) => TurkishText::fold($f['question'] ?? ''), (array) ($action->after['faqs'] ?? []));
        $faqs = (array) $page->faqs;
        $kept = array_values(array_filter($faqs, fn ($f) => ! in_array(TurkishText::fold($f['question'] ?? ''), $remove, true)));

        if (count($kept) === count($faqs)) {
            throw new RuntimeException('Eklenen sorular sayfada bulunamadı (elle düzenlenmiş olabilir).');
        }

        $page->forceFill(['faqs' => $kept])->save();

        return (count($faqs) - count($kept)).' soru kaldırıldı.';
    }

    private function revertLinkRule(SeoKeywordAction $action): string
    {
        $rule = InternalLinkRule::find($action->after['id'] ?? null);

        // Kural silinmez, pasife alınır (spec §10.9).
        $rule?->update(['is_active' => (bool) ($action->before['is_active'] ?? false)]);

        return 'İç link kuralı pasife alındı.';
    }

    // ---------- yardımcılar ----------

    /**
     * Metin kelimeyi "kapsıyor" mu? Her kelime parçası metinde bir kelimenin başında
     * geçmeli. Türkçe eklere izin verir: "mobilya montaj" ⊂ "Mobilya Montajı".
     */
    public function covers(string $keyword, string $text): bool
    {
        $words = explode(' ', ' '.TurkishText::fold($text));

        foreach (explode(' ', TurkishText::fold($keyword)) as $token) {
            if (strlen($token) < 3) {
                continue;
            }

            $stem = substr($token, 0, min(strlen($token), 5));
            $found = false;

            foreach ($words as $word) {
                if ($word !== '' && str_starts_with($word, $stem)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }

    private function linkRule(SeoKeyword $keyword, string $url): ?InternalLinkRule
    {
        $fold = TurkishText::fold($keyword->keyword);

        return InternalLinkRule::query()
            ->where('target_hash', PathNormalizer::hash($url))
            ->get()
            ->first(fn (InternalLinkRule $rule) => TurkishText::fold($rule->anchor_text) === $fold);
    }

    private function doneActions(string $action, ?SeoKeyword $keyword = null, ?string $pageUrl = null)
    {
        return SeoKeywordAction::query()
            ->where('action', $action)
            ->where('status', SeoKeywordAction::DONE)
            ->when($keyword, fn ($q) => $q->where('keyword_id', $keyword->getKey()))
            ->when($pageUrl, fn ($q) => $q->where('page_url', $pageUrl));
    }

    private function lastAction(string $action, ?SeoKeyword $keyword = null, ?string $pageUrl = null): ?SeoKeywordAction
    {
        return $this->doneActions($action, $keyword, $pageUrl)->latest('id')->first();
    }

    /**
     * Prompt'a verilen GERÇEK bilgiler. Model yalnız bunları kullanabilir.
     *
     * @return array<int, string>
     */
    private function pageFacts(SeoKeyword $keyword, Model $page): array
    {
        $services = Service::query()->where('is_active', true)->orderBy('sort_order')->pluck('title')->all();

        $lines = match (true) {
            $page instanceof District => ['SAYFA: '.$page->name.' ilçe sayfası', 'İL: '.$page->province?->name],
            $page instanceof Province => ['SAYFA: '.$page->name.' il sayfası'],
            default => ['SAYFA: '.$page->title.' hizmet sayfası', 'HİZMET BÖLGESİ: Tekirdağ, Edirne ve Kırklareli (Trakya)'],
        };

        if ($page instanceof District) {
            $neighborhoods = (array) $page->neighborhoods;
            $region = (string) $keyword->region;

            // Takma ad (Kızılpınar): sayfanın kendi adı değilse, adı geçen mahalleler öne alınır.
            if ($region !== '' && TurkishText::fold($region) !== TurkishText::fold($page->name)) {
                $related = array_values(array_filter($neighborhoods, fn ($n) => str_contains(TurkishText::fold($n), TurkishText::fold($region))));
                $lines[] = 'NOT: "'.$region.'" '.$page->name.' ilçesine bağlı bir yerleşimdir'.($related ? ' (mahalleleri: '.implode(', ', $related).')' : '').'.';
            }

            $lines[] = 'HİZMET VERDİĞİMİZ MAHALLE/KÖYLER (yalnız bunları kullan): '.implode(', ', $neighborhoods);
        }

        $lines[] = 'SUNDUĞUMUZ HİZMETLER: '.implode(', ', $services);

        return $lines;
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'Sen Trakya bölgesinde (Tekirdağ, Edirne, Kırklareli) mobilya montaj hizmeti veren bir firmanın',
            'yerel SEO editörüsün. Türkçe, sade ve güven veren bir dille yazarsın.',
            '',
            'MUTLAK KURALLAR:',
            '- ASLA UYDURMA. Rakam, yıl, müşteri sayısı, ödül, sertifika, puan, fiyat yazma.',
            '- Verilmeyen mahalle, köy veya yer adı UYDURMA.',
            '- <a> ve <h1> etiketi KULLANMA; linkleri ayrı bir sistem basar.',
            '- Pazarlama klişesi ve abartı yok. Okuyucunun işine yarayan somut bilgi ver.',
            '',
            'ÇIKTI: yalnızca geçerli JSON. Markdown, kod bloğu veya açıklama ekleme.',
        ]);
    }
}
