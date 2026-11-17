<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Brand;
use App\Models\Province;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Service;
use App\Services\Ai\AiClient;
use App\Services\Ai\ArticleGenerator;
use App\Services\InternalLink\RelatedLinks;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Yazılabilir sayfası olmayan kelimeye ev bulur.
 *
 * Fırsat avı Search Console'dan gelen her sorguyu takibe alır; ama bir kısmının bağlı
 * olduğu sayfa (marka, statik sayfa) RankBooster'ın yazamadığı türdendir ya da hiç sayfa
 * yoktur. Bu kelimeler "Yükselt"te takılıyordu. Sıra:
 *
 *   1. Kelime tek bir hizmetle açıkça eşleşiyor ve marka içermiyorsa ("gardırop
 *      kurulumu") o hizmet sayfasına bağlanır — aynı konuda blog açmak hizmet sayfasıyla
 *      yarıştırır (kanibalizasyon).
 *   2. Yoksa kelimeye özel blog yazısı yazılır, yayınlanır ve içine ilgili para
 *      sayfalarının (marka, hizmet, ilçe/il) linkleri eklenir. Kelime yazıya bağlanır;
 *      yazı da o sayfalara iç link taşıyarak sıralarına yardım eder.
 */
class KeywordBlogWriter
{
    /** Hizmet adlarında ayırt edici olmayan sözcükler (katlanmış). */
    private const STOPWORDS = ['montaj', 'montaji', 'kurulum', 'kurulumu', 've', 'ile'];

    /** Kelimede bölge geçmiyorsa linklenen il sayısı (sıralamada ilk iller). */
    private const DEFAULT_PROVINCES = 3;

    public const MAX_LINKS = 8;

    public function __construct(
        protected AiClient $ai,
        protected ArticleGenerator $articles,
        protected OpportunityHunter $hunter,
    ) {}

    /** Blog yazılabilir mi? Değilse neden. */
    public function unavailableReason(): ?string
    {
        return match (true) {
            ! SeoConfig::bool('opportunity_blog_fallback', true) => 'Sayfasız kelimeye blog yazma ayarı kapalı',
            ! $this->ai->isConfigured() => 'AI anahtarı tanımlı değil',
            $this->category() === null => 'Aktif blog kategorisi yok',
            default => null,
        };
    }

    /** Kelimenin açıkça ait olduğu hizmet sayfası (marka içeren kelimede yok). */
    public function serviceTarget(SeoKeyword $keyword): ?SeoTarget
    {
        if ($this->brands($keyword->keyword)->isNotEmpty()) {
            return null;
        }

        $matches = $this->scoredServices($keyword->keyword);

        // Tek ve net kazanan yoksa tahmin etme.
        if ($matches->isEmpty() || ($matches->count() > 1 && $matches[0]['score'] === $matches[1]['score'])) {
            return null;
        }

        return SeoTarget::query()->active()
            ->where('content_type', 'service')
            ->where('content_id', $matches[0]['service']->getKey())
            ->first();
    }

    /**
     * Kelimeye özel yazıyı üretir, yayınlar, linkleri ekler ve kelimeyi yazıya bağlar.
     *
     * @return array{blog: Blog, links: int}
     */
    public function write(SeoKeyword $keyword): array
    {
        if ($reason = $this->unavailableReason()) {
            throw new RuntimeException($reason);
        }

        $blog = $this->articles->generate($this->category(), [
            'title' => Str::ucfirst($keyword->keyword),
            'slug' => Str::slug(TurkishText::fold($keyword->keyword)),
            'primary_keyword' => $keyword->keyword,
            'outline' => [],
        ], now());

        $links = $this->moneyLinks($keyword);

        $blog->forceFill([
            'body_html' => $this->insertLinks((string) $blog->body_html, $links),
            'status' => Blog::STATUS_PUBLISHED,
            // Saniye eşitliğinde "henüz yayında değil" sayılmasın.
            'publish_at' => now()->subMinute(),
        ])->save();

        // target_id dolu kalırsa model kaydederken owner_blog_id'yi siler (tek sahip kuralı).
        $keyword->forceFill(['target_id' => null, 'owner_blog_id' => $blog->getKey()])->save();
        $keyword->unsetRelation('target')->unsetRelation('ownerBlog');

        return ['blog' => $blog, 'links' => count($links)];
    }

    /**
     * Yazıya konacak iç linkler: [yol, metin]. Yalnız gerçek ve aktif sayfalar.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function moneyLinks(SeoKeyword $keyword): array
    {
        $links = [];
        $brands = $this->brands($keyword->keyword);

        foreach ($brands as $brand) {
            $links[] = [$brand->path(), $brand->name.' montajı'];
        }

        $services = $this->scoredServices($keyword->keyword)->pluck('service');

        if ($services->isEmpty()) {
            $services = Service::query()->active()->ordered()->take(2)->get();
        }

        $services = $services->unique(fn (Service $s) => $s->getKey())->take(2)->values();
        $provinces = $this->provinces($keyword->keyword);

        foreach ($services as $service) {
            $links[] = ['/'.$service->slug, TurkishText::lower($service->title)];
        }

        // Bölge: kelimede geçen ilçe/il sayfası; geçmiyorsa hizmet verilen iller.
        $region = $this->hunter->matchRegion($keyword->keyword);

        if ($region && $region['url']) {
            $links[] = [$region['url'], $region['name'].' mobilya montaj'];
        }

        foreach ($region ? [] : $provinces as $province) {
            $links[] = ['/'.$province->slug, $province->name.' mobilya montaj'];
        }

        return collect($links)->unique(fn ($l) => $l[0])->take(self::MAX_LINKS)->values()->all();
    }

    /** @param  array<int, array{0: string, 1: string}>  $links */
    private function insertLinks(string $html, array $links): string
    {
        if ($links === [] || str_contains($html, RelatedLinks::MARKER)) {
            return $html;
        }

        $items = implode('', array_map(fn ($l) => '<li><a href="'.e($l[0]).'">'.e($l[1]).'</a></li>', $links));
        $block = "<h2>İlgili sayfalar</h2>\n<ul class=\"".RelatedLinks::MARKER.'">'.$items."</ul>\n";

        // Kapanıştaki iletişim (CTA) paragrafı en sonda kalsın.
        $pos = strrpos($html, '<p');

        return $pos === false ? rtrim($html)."\n".$block : substr($html, 0, $pos).$block.substr($html, $pos);
    }

    private function category(): ?BlogCategory
    {
        return BlogCategory::query()->where('is_active', true)
            ->orderByDesc('auto_generate')->orderBy('sort_order')->first();
    }

    /** @return Collection<int, Brand> */
    private function brands(string $keyword): Collection
    {
        $haystack = ' '.TurkishText::fold($keyword).' ';

        return Brand::query()->active()->whereNull('service_id')->get()
            ->filter(fn (Brand $b) => ($name = TurkishText::fold($b->name)) !== '' && str_contains($haystack, ' '.$name.' '))
            ->values();
    }

    /** @return Collection<int, array{service: Service, score: int}> en çok ortak sözcüğe sahip önce */
    private function scoredServices(string $keyword): Collection
    {
        $words = $this->words($keyword);

        return Service::query()->active()->ordered()->get()
            ->map(fn (Service $s) => ['service' => $s, 'score' => count(array_intersect($this->words($s->title), $words))])
            ->filter(fn ($m) => $m['score'] > 0)
            ->sortByDesc('score')
            ->values();
    }

    /** @return Collection<int, Province> hizmet verilen iller (sıralamaya göre ilk birkaçı) */
    private function provinces(string $keyword): Collection
    {
        return Province::query()->active()->ordered()->limit(self::DEFAULT_PROVINCES)->get();
    }

    /** @return array<int, string> */
    private function words(string $text): array
    {
        return array_values(array_diff(
            array_filter(preg_split('/[^a-z0-9]+/', TurkishText::fold($text)) ?: [], fn ($w) => strlen($w) >= 3),
            self::STOPWORDS,
        ));
    }
}
