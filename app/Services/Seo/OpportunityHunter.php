<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\SeoKeyword;
use App\Models\SeoRankHistory;
use App\Models\SeoTarget;
use App\Services\Google\SearchConsoleService;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * GSC fırsat avı: bölge kelimelerini bulur, sayfaya bağlar, sırasını izler.
 *
 * DETERMİNİSTİK — AI kullanmaz. Sayfayı iyileştirme işi RankBooster'dadır.
 *
 * tavsiyemiz'deki "Kelime Sırası" modülünden uyarlandı; oradaki iki hata burada yok:
 *   - Eşleştirme mb_strtolower ile değil, TurkishText::fold ile yapılır: "İ" bozulmaz ve
 *     "cerkezkoy" ile "çerkezköy" aynı kelime sayılır.
 *   - Yeni kelime yalnız GERÇEK GSC sorgusundan ve bölge + iş terimi içeriyorsa eklenir;
 *     tur başına tavan vardır. "İlgili arama"dan kelime türetilmez (orada binlerce
 *     alakasız kelimeye yol açmıştı).
 */
class OpportunityHunter
{
    /** Yer adına bitişik yazılan hâl ekleri, ASCII'ye katlanmış hâlde (de/da/te/ta, den, deki, ye, nin…). */
    private const CASE_SUFFIXES = 'deki|daki|teki|taki|den|dan|ten|tan|de|da|te|ta|ye|ya|nin|nun|in|un|e|a';

    /** @var array<int, array{name: string, fold: string, url: string, label: string, type: string, focus: bool, target_id: int|null}>|null */
    private ?array $regions = null;

    public function __construct(
        protected SearchConsoleService $gsc,
        protected LocationKeywordBuilder $locations,
    ) {}

    public function isConfigured(): bool
    {
        return $this->gsc->isConfigured();
    }

    /**
     * Tam tur: odak kelimeleri garantile → GSC verisini çek → yeni fırsatları ekle → sıraları güncelle.
     *
     * @return array{seeded: int, rows: int, hunted: int, tracked: int, updated: int, won: int, lost: int, no_data: int}
     */
    public function run(): array
    {
        $result = ['seeded' => $this->ensureFocusKeywords(), 'rows' => 0, 'hunted' => 0, 'near' => 0,
            'tracked' => 0, 'updated' => 0, 'won' => 0, 'lost' => 0, 'no_data' => 0];

        if (! $this->isConfigured()) {
            return $result;
        }

        $data = $this->pull();
        $result['rows'] = count($data['rows']);
        $result['hunted'] = $this->hunt($data['rows']);
        $result['near'] = $this->huntNearMisses($data['rows'], SeoConfig::int('opportunity_max_new_per_run', 30, 1, 200) - $result['hunted']);
        $result['hunted'] += $result['near'];

        return array_merge($result, $this->sync($data['rows'], $data['end']));
    }

    // ---------- bölgeler ----------

    /**
     * Erişilebilir il/ilçeler + kendi sayfası olmayan yer adları (Kızılpınar → Çerkezköy).
     *
     * @return array<int, array{name: string, fold: string, url: string, label: string, type: string, focus: bool, target_id: int|null}>
     */
    public function regions(): array
    {
        if ($this->regions !== null) {
            return $this->regions;
        }

        $focus = array_map([TurkishText::class, 'fold'], (array) config('seo.opportunity.focus_regions', []));
        $regions = [];
        $byUrl = [];

        foreach ($this->locations->reachableRegions() as $region) {
            $byUrl[PathNormalizer::normalize($region['url'])] = $region;
            $regions[] = $this->region($region['name'], $region['url'], $region['label'], $region['type'], $focus);
        }

        foreach ((array) config('seo.opportunity.region_aliases', []) as $name => $url) {
            $parent = $byUrl[PathNormalizer::normalize($url)] ?? null;

            // Bağlanacağı sayfa kapalıysa takma ad da yok sayılır: 404'e kelime bağlanmaz.
            if ($parent) {
                $regions[] = $this->region($name, $parent['url'], $name.' → '.$parent['label'], 'alias', $focus);
            }
        }

        return $this->regions = $regions;
    }

    /** @param  array<int, string>  $focus */
    private function region(string $name, string $url, string $label, string $type, array $focus): array
    {
        $fold = TurkishText::fold($name);

        return [
            'name' => $name,
            'fold' => $fold,
            'url' => PathNormalizer::normalize($url),
            'label' => $label,
            'type' => $type,
            'focus' => in_array($fold, $focus, true),
            'target_id' => SeoTarget::query()->where('url_hash', PathNormalizer::hash($url))->where('status', true)->value('id'),
        ];
    }

    /**
     * Sorgudaki bölge. İlçe ve takma ad, ilden önce gelir: "tekirdağ çerkezköy montaj"
     * Çerkezköy sayfasının kelimesidir. Aynı türde uzun ad kazanır.
     */
    public function matchRegion(string $query): ?array
    {
        $haystack = TurkishText::fold($query);
        $best = null;

        foreach ($this->regions() as $region) {
            /*
             * Bölge adı tek başına ya da hâl ekiyle: "çerkezköyde", "kapaklıdaki", "saraya".
             * Canlı veride "çorluda ikea varmı" ek yüzünden eşleşmiyordu. Yalnız hâl ekleri
             * kabul edilir; "sarayiçi" gibi başka bir yer adı Saray sayılmaz.
             */
            if ($region['fold'] === '' || ! preg_match(
                '/(?:^| )'.preg_quote($region['fold'], '/').'(?:'.self::CASE_SUFFIXES.')?(?: |$)/',
                $haystack,
            )) {
                continue;
            }

            $rank = [$region['type'] === 'province' ? 0 : 1, strlen($region['fold'])];

            if ($best === null || $rank > $best[0]) {
                $best = [$rank, $region];
            }
        }

        return $best[1] ?? null;
    }

    /** Sorgu işimizle mi ilgili? Kelime başında eşleşir: "masa" eşleşir, "kamasa" eşleşmez. */
    public function isBusiness(string $query): bool
    {
        $haystack = ' '.TurkishText::fold($query);

        foreach ((array) config('seo.opportunity.business_terms', []) as $term) {
            if (str_contains($haystack, ' '.TurkishText::fold($term))) {
                return true;
            }
        }

        return false;
    }

    // ---------- GSC ----------

    /**
     * Son N günün sorguları, katlanmış hâle göre birleştirilmiş.
     *
     * Bölge kelimeleri az aranır: tek günlük veri çoğu gün boş gelir. Bu yüzden 28 günlük
     * pencere kullanılır. İki istek yeter: biri sıra/gösterim için, biri sıralanan sayfa için.
     *
     * @return array{rows: array<string, array{keyword: string, clicks: int, impressions: int, ctr: float, position: float, page: string|null}>, end: Carbon}
     */
    public function pull(): array
    {
        $end = Carbon::today()->subDays(3);   // GSC "final" verisi ~3 gün gecikmeli
        $start = $end->copy()->subDays(SeoConfig::int('opportunity_window_days', 28, 7, 90) - 1);

        $groups = [];

        foreach ($this->gsc->query($start, $end, ['query'], 5000) as $row) {
            $query = trim((string) ($row['keys'][0] ?? ''));
            $fold = TurkishText::fold($query);

            if ($fold === '') {
                continue;
            }

            $groups[$fold][] = [
                'query' => $query,
                'clicks' => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ];
        }

        $pages = [];

        foreach ($this->gsc->query($start, $end, ['query', 'page'], 5000) as $row) {
            $fold = TurkishText::fold((string) ($row['keys'][0] ?? ''));
            $impressions = (int) ($row['impressions'] ?? 0);

            if ($fold !== '' && (! isset($pages[$fold]) || $impressions > $pages[$fold][1])) {
                $pages[$fold] = [(string) ($row['keys'][1] ?? ''), $impressions];
            }
        }

        $rows = [];

        foreach ($groups as $fold => $variants) {
            $impressions = array_sum(array_column($variants, 'impressions'));
            $clicks = array_sum(array_column($variants, 'clicks'));

            // Sıra gösterime göre ağırlıklı ortalama: çok gösterilen yazım biçimi belirleyicidir.
            $position = $impressions > 0
                ? array_sum(array_map(fn ($v) => $v['position'] * $v['impressions'], $variants)) / $impressions
                : (float) $variants[0]['position'];

            // Görünen ad: Türkçe karakterli yazım varsa o ("çerkezköy" > "cerkezkoy").
            usort($variants, fn ($a, $b) => [preg_match('/[^\x20-\x7E]/', $b['query']), $b['impressions']]
                <=> [preg_match('/[^\x20-\x7E]/', $a['query']), $a['impressions']]);

            $rows[$fold] = [
                'keyword' => TurkishText::lower($variants[0]['query']),
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0.0,
                'position' => round($position, 2),
                'page' => $pages[$fold][0] ?? null,
            ];
        }

        return ['rows' => $rows, 'end' => $end];
    }

    // ---------- fırsat ekleme ----------

    /**
     * GSC'de görünen bölge + iş sorgularını kampanyaya alır.
     *
     * @param  array<string, array{keyword: string, clicks: int, impressions: int, ctr: float, position: float, page: string|null}>  $rows
     */
    public function hunt(array $rows): int
    {
        $minImpressions = SeoConfig::int('opportunity_min_impressions', 3, 1, 1000);
        $maxPosition = SeoConfig::int('opportunity_max_position', 60, 4, 100);
        $cap = SeoConfig::int('opportunity_max_new_per_run', 30, 1, 200);
        $index = $this->keywordIndex();

        $candidates = [];

        foreach ($rows as $fold => $row) {
            if ($row['impressions'] < $minImpressions || $row['position'] > $maxPosition || ! $this->isBusiness($row['keyword'])) {
                continue;
            }

            if (! $region = $this->matchRegion($row['keyword'])) {
                continue;
            }

            $existing = $index[$fold] ?? null;

            if ($existing?->campaign_status !== null) {
                continue;   // zaten takipte
            }

            $candidates[] = [$fold, $row, $region, $existing];
        }

        // Önce odak bölgeler, sonra çok gösterilenler.
        usort($candidates, fn ($a, $b) => [$b[2]['focus'], $b[1]['impressions']] <=> [$a[2]['focus'], $a[1]['impressions']]);

        $added = 0;

        foreach (array_slice($candidates, 0, $cap) as [$fold, $row, $region, $existing]) {
            $keyword = $existing ?? new SeoKeyword([
                'keyword' => $row['keyword'],
                'keyword_type' => 'COMMERCIAL_VARIANT',
                'search_intent' => 'transactional',
                'priority' => $region['focus'] ? 1 : 2,
                'status' => true,
                'note' => 'GSC fırsat avı · '.$region['label'],
            ]);

            $this->enroll($keyword, $region, 'gsc');
            $added++;
        }

        return $added;
    }

    /**
     * Odak bölgelerin ana kelimeleri GSC verisi beklenmeden takibe alınır.
     * Kızılpınar'ın kendi sayfası olmadığı için "kızılpınar mobilya montaj"
     * başka türlü hiç oluşmazdı.
     */
    /**
     * "Sıraya yakın" aramalar: bölge adı geçmeyen ama Google'ın sitemizi zaten 4-25. sırada
     * gösterdiği sorgular (canlıda: "oda kapı menteşe ayarı nasıl yapılır" #10, 30 gösterim).
     *
     * Kelime, Google'ın o sorguda gösterdiği sayfaya bağlanır; sayfayı biz seçmeyiz, Google
     * zaten onu ilgili buluyor. Sayfa iyileştirilebilir değilse (marka, statik sayfa) alınmaz.
     */
    public function huntNearMisses(array $rows, int $room): int
    {
        if ($room < 1 || ! SeoConfig::bool('opportunity_near_enabled', true)) {
            return 0;
        }

        $minImpressions = SeoConfig::int('opportunity_min_impressions', 3, 1, 1000);
        $min = SeoConfig::int('opportunity_near_min_position', 4, 2, 50);
        $max = SeoConfig::int('opportunity_near_max_position', 25, 5, 100);
        $index = $this->keywordIndex();
        $candidates = [];

        foreach ($rows as $fold => $row) {
            if ($row['impressions'] < $minImpressions || $row['position'] < $min || $row['position'] > $max) {
                continue;
            }

            if (($index[$fold] ?? null)?->campaign_status !== null || ! $this->isNearMissQuery($row['keyword'])) {
                continue;
            }

            if (! $owner = $this->ownerForUrl($row['page'])) {
                continue;
            }

            $candidates[] = [$row, $owner, $index[$fold] ?? null];
        }

        usort($candidates, fn ($a, $b) => $b[0]['impressions'] <=> $a[0]['impressions']);

        // Sayfa başına TEK "sıraya yakın" kelime: canlı veride aynı menteşe yazısına altı benzer
        // sorgu düştü; her birine ayrı bölüm eklemek yazıyı tekrar eden başlıklarla doldurur
        // (Google bunu kelime doldurma sayar). En çok gösterim alan sorgu sayfayı temsil eder.
        $taken = SeoKeyword::query()->inCampaign()->where('campaign_source', 'gsc_near')->get()
            ->map(fn (SeoKeyword $k) => $k->owner_blog_id ? 'b'.$k->owner_blog_id : 't'.$k->target_id)
            ->flip()->all();

        $added = 0;

        foreach ($candidates as [$row, $owner, $existing]) {
            $key = isset($owner['owner_blog_id']) ? 'b'.$owner['owner_blog_id'] : 't'.$owner['target_id'];

            if ($added >= $room || isset($taken[$key])) {
                continue;
            }

            $taken[$key] = true;

            $keyword = $existing ?? new SeoKeyword([
                'keyword' => $row['keyword'],
                'keyword_type' => 'COMMERCIAL_VARIANT',
                'search_intent' => $this->isBusiness($row['keyword']) ? 'transactional' : 'informational',
                'priority' => 2,
                'status' => true,
                'note' => 'GSC fırsat avı · sıraya yakın',
            ]);

            $keyword->fill([
                'campaign_status' => SeoKeyword::CAMPAIGN_ACTIVE,
                'campaign_source' => $keyword->campaign_source ?? 'gsc_near',
                'target_position' => $keyword->target_position ?: SeoConfig::int('opportunity_target_position', 3, 1, 10),
                'status' => true,
            ]);

            // Var olan bir sahiplik ezilmez (tek sahip kuralı; elle yapılmış bağ korunur).
            if (! $keyword->hasOwner()) {
                $keyword->fill($owner);
            }

            $keyword->save();
            $added++;
        }

        return $added;
    }

    public function isNearMissQuery(string $query): bool
    {
        $haystack = ' '.TurkishText::fold($query).' ';

        foreach ((array) config('seo.opportunity.skip_terms', []) as $term) {
            if (str_contains($haystack, ' '.TurkishText::fold($term))) {
                return false;
            }
        }

        if ($this->isBusiness($query)) {
            return true;
        }

        foreach ((array) config('seo.opportunity.howto_terms', []) as $term) {
            if (str_contains($haystack, ' '.TurkishText::fold($term))) {
                return true;
            }
        }

        return false;
    }

    /**
     * GSC'nin gösterdiği adres → kelimenin sahibi. Yalnız RankBooster'ın yazabildiği sayfalar:
     * yayındaki blog yazısı ya da ilçe/il/hizmet hedefi. www ve http farkı yok sayılır.
     *
     * @return array{target_id?: int, owner_blog_id?: int}|null
     */
    public function ownerForUrl(?string $url): ?array
    {
        $path = PathNormalizer::normalize((string) (parse_url((string) $url, PHP_URL_PATH) ?: ''));

        if ($path === '/' || $url === null) {
            return null;
        }

        if (preg_match('#^/blog/([^/]+)$#', $path, $m)) {
            $id = Blog::query()->published()->where('slug', $m[1])->value('id');

            return $id ? ['owner_blog_id' => (int) $id] : null;
        }

        $id = SeoTarget::query()->where('url_hash', PathNormalizer::hash($path))->where('status', true)
            ->whereIn('content_type', ['district', 'province', 'service'])->value('id');

        return $id ? ['target_id' => (int) $id] : null;
    }

    public function ensureFocusKeywords(): int
    {
        $index = $this->keywordIndex();
        $added = 0;

        foreach ($this->regions() as $region) {
            if (! $region['focus'] || $region['type'] === 'province') {
                continue;
            }

            foreach ((array) config('seo.opportunity.seed_patterns', []) as $pattern) {
                $text = sprintf($pattern, TurkishText::searchLower($region['name']));
                $existing = $index[TurkishText::fold($text)] ?? null;

                if ($existing?->campaign_status !== null) {
                    continue;
                }

                $keyword = $existing ?? new SeoKeyword([
                    'keyword' => $text,
                    'keyword_type' => 'COMMERCIAL_PRIMARY',
                    'search_intent' => 'transactional',
                    'priority' => 1,
                    'status' => true,
                    'note' => 'GSC fırsat avı · odak bölge · '.$region['label'],
                ]);

                $this->enroll($keyword, $region, 'focus');
                $added++;
            }
        }

        return $added;
    }

    /** Kelimeyi kampanyaya alır; sahibi yoksa bölge sayfasına bağlar. Elle verilmiş sahibe DOKUNMAZ. */
    private function enroll(SeoKeyword $keyword, array $region, string $source): void
    {
        $keyword->fill([
            'campaign_status' => SeoKeyword::CAMPAIGN_ACTIVE,
            'campaign_source' => $keyword->campaign_source ?? $source,
            'target_position' => $keyword->target_position ?: SeoConfig::int('opportunity_target_position', 3, 1, 10),
            'region' => $region['name'],
            'status' => true,
        ]);

        if (! $keyword->hasOwner() && $region['target_id']) {
            $keyword->target_id = $region['target_id'];
        }

        $keyword->save();
    }

    /** @return array<string, SeoKeyword> katlanmış kelime → kayıt (kampanyadaki önce) */
    private function keywordIndex(): array
    {
        $index = [];

        foreach (SeoKeyword::query()->orderByRaw('campaign_status IS NULL')->orderBy('id')->get() as $keyword) {
            $index[TurkishText::fold($keyword->keyword)] ??= $keyword;
        }

        return $index;
    }

    // ---------- sıra güncelleme ----------

    /**
     * Kampanyadaki kelimelerin sırasını günceller. Hedefe giren "won" olur; düşen
     * kendiliğinden "active"e döner. Duraklatılmış kelimenin durumu değişmez.
     *
     * GSC'de bu dönemde hiç görünmeyen kelimenin son sırası SİLİNMEZ: "veri yok" ile
     * "sıra kaybedildi" aynı şey değildir (tavsiyemiz'de sıra null'a düşüyordu).
     *
     * @return array{tracked: int, updated: int, won: int, lost: int, no_data: int}
     */
    public function sync(array $rows, Carbon $dataDate): array
    {
        $stats = ['tracked' => 0, 'updated' => 0, 'won' => 0, 'lost' => 0, 'no_data' => 0];

        foreach (SeoKeyword::query()->inCampaign()->with('target', 'ownerBlog')->get() as $keyword) {
            $stats['tracked']++;
            $row = $rows[TurkishText::fold($keyword->keyword)] ?? null;

            if (! $row) {
                $stats['no_data']++;
                $keyword->forceFill(['gsc_clicks' => 0, 'gsc_impressions' => 0, 'gsc_ctr' => null, 'ranked_at' => now()])->save();

                continue;
            }

            $wasWon = $keyword->campaign_status === SeoKeyword::CAMPAIGN_WON;
            $page = $row['page'] ? PathNormalizer::normalize($row['page']) : null;

            $keyword->forceFill([
                'previous_position' => $keyword->last_position,
                'last_position' => $row['position'],
                'gsc_clicks' => $row['clicks'],
                'gsc_impressions' => $row['impressions'],
                'gsc_ctr' => $row['ctr'],
                'ranking_url' => $page ? Str::limit($page, 490, '') : null,
                'ranked_at' => now(),
            ]);

            if ($keyword->campaign_status !== SeoKeyword::CAMPAIGN_PAUSED) {
                $keyword->campaign_status = $keyword->reachedTarget() ? SeoKeyword::CAMPAIGN_WON : SeoKeyword::CAMPAIGN_ACTIVE;
            }

            $keyword->save();

            $expected = $keyword->expectedUrl();

            // whereDate ile aranır: "date" cast'i SQLite'ta saati de yazar ve updateOrCreate'in
            // düz eşitlik araması aynı günün satırını bulamayıp ikinci kez eklemeye kalkar.
            $history = SeoRankHistory::query()
                ->where('keyword_id', $keyword->getKey())
                ->whereDate('data_date', $dataDate->toDateString())
                ->first() ?? new SeoRankHistory(['keyword_id' => $keyword->getKey(), 'data_date' => $dataDate->toDateString()]);

            $history->fill([
                'position_avg' => $row['position'],
                'clicks' => $row['clicks'],
                'impressions' => $row['impressions'],
                'ranking_url' => $page,
                'target_match' => $expected && $page ? PathNormalizer::normalize($expected) === $page : null,
            ])->save();

            $stats['updated']++;
            $isWon = $keyword->campaign_status === SeoKeyword::CAMPAIGN_WON;
            $stats['won'] += (! $wasWon && $isWon) ? 1 : 0;
            $stats['lost'] += ($wasWon && ! $isWon) ? 1 : 0;
        }

        return $stats;
    }
}
