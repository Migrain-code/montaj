<?php

namespace App\Services\InternalLink;

use App\Models\Brand;
use App\Models\District;
use App\Models\Province;
use App\Models\Service;
use App\Support\TurkishText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Kural motoru gövdeye hiç link basamadığında eklenen "ilgili sayfalar" paragrafı.
 *
 * Neden gerekli: kurallar anchor ifadesinin metinde BİREBİR geçmesini bekler ("Çerkezköy
 * mobilya montaj"). AI ve elle yazılan metinler doğal dilde yazıldığından bu kalıplar
 * nadiren geçer; kardeş sitede 125 içerikten yalnız 5'i link alıyordu.
 *
 * Paragraf deterministiktir, uydurma bilgi içermez, yalnız gerçek ve aktif sayfalara
 * işaret eder. Hangi hizmet/ilçelerin seçileceği kayda göre döner — her sayfanın
 * paragrafı aynı linklerden oluşmaz. İşaretli sınıf sayesinde bir kez eklenir (idempotent).
 */
class RelatedLinks
{
    public const MARKER = 'related-links';

    /** Gövdede en az bu kadar iç link varsa paragraf eklenmez. */
    public const MIN_LINKS = 2;

    public function appendIfNeeded(Model $model, string $html): string
    {
        if (trim(strip_tags($html)) === '' || str_contains($html, self::MARKER)) {
            return $html;
        }

        if ($this->internalLinkCount($html) >= self::MIN_LINKS) {
            return $html;
        }

        $paragraph = match (true) {
            $model instanceof Province => $this->forProvince($model),
            $model instanceof District => $this->forDistrict($model->loadMissing('province')),
            $model instanceof Brand => $this->forBrand($model),
            $model instanceof Service => $this->forService($model),
            default => null,
        };

        if (! $paragraph) {
            return $html;
        }

        $block = '<p class="'.self::MARKER.'">'.$paragraph.'</p>';

        // Marka metnindeki hukuki not (<p><small>…</small></p>) en sonda kalmalı.
        if (preg_match('/<p>\s*<small>/u', $html, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];

            return substr($html, 0, $pos).$block."\n".substr($html, $pos);
        }

        return rtrim($html)."\n".$block;
    }

    public function internalLinkCount(string $html): int
    {
        preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $m);
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return collect($m[1] ?? [])
            ->filter(fn ($href) => str_starts_with($href, '/') || ($host !== '' && str_contains($href, $host)))
            ->count();
    }

    private function forProvince(Province $province): ?string
    {
        $districts = $this->rotate($province->activeDistricts()->get(), (int) $province->getKey(), 3);

        if ($districts->isEmpty()) {
            return null;
        }

        $links = $districts->map(fn (District $d) => $this->a(
            '/'.$province->slug.'/'.$d->slug,
            $d->name.' mobilya montaj',
        ))->all();

        return e($province->name).' genelinde ilçe sayfalarımız: '.$this->join($links)
            .'. Montaj türleri için '.$this->a('/hizmetler', 'tüm hizmetlerimizi').' inceleyebilirsiniz.';
    }

    private function forDistrict(District $district): ?string
    {
        $province = $district->province;

        if (! $province || ! $province->is_active) {
            return null;
        }

        $services = $this->rotate(Service::query()->active()->ordered()->get(), (int) $district->getKey(), 2);

        $links = $services->map(fn (Service $s) => $this->a('/'.$s->slug, TurkishText::lower($s->title)))->all();

        $first = $links
            ? e($district->name).' bölgesinde sık istenen işler: '.$this->join($links)
            : e($district->name).' bölgesindeki tüm işler için '.$this->a('/hizmetler', 'hizmetlerimize').' göz atabilirsiniz';

        return $first.'. '.e($province->name).' genelindeki diğer ilçeler için '
            .$this->a('/'.$province->slug, $province->name.' mobilya montaj').' sayfasına bakabilirsiniz.';
    }

    private function forBrand(Brand $brand): ?string
    {
        return 'Tüm montaj türleri için '.$this->a('/hizmetler', 'hizmetlerimizi').' inceleyebilir, ürünlerinizin fotoğrafını '
            .$this->a('/teklif-al', 'teklif formu').' üzerinden iletebilir, diğer markalar için '
            .$this->a('/markalar', 'montajını yaptığımız markalar').' sayfasına göz atabilirsiniz.';
    }

    private function forService(Service $service): ?string
    {
        $siblings = Service::query()->active()->ordered()
            ->whereKeyNot($service->getKey())
            ->get();

        $picked = $this->rotate($siblings, (int) $service->getKey(), 2);

        if ($picked->isEmpty()) {
            return null;
        }

        $links = $picked->map(fn (Service $s) => $this->a('/'.$s->slug, TurkishText::lower($s->title)))->all();

        return 'İlgili hizmetlerimiz: '.$this->join($links).'. Hizmet verdiğimiz ilçeler için '
            .$this->a('/bolgeler', 'bölgeler').' listesine bakabilirsiniz.';
    }

    /** Listeden kayda göre kaydırılmış $count öğe: her kayıt farklı bir alt küme alır. */
    private function rotate(Collection $items, int $seed, int $count): Collection
    {
        $items = $items->values();
        $n = $items->count();

        if ($n === 0) {
            return collect();
        }

        return collect(range(0, min($count, $n) - 1))
            ->map(fn ($i) => $items[($seed + $i * max(1, intdiv($n, $count))) % $n])
            ->unique(fn ($s) => $s->getKey())
            ->values();
    }

    private function a(string $href, string $text): string
    {
        return '<a href="'.e($href).'">'.e($text).'</a>';
    }

    /** @param  array<int, string>  $parts */
    private function join(array $parts): string
    {
        if (count($parts) < 2) {
            return implode('', $parts);
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' ve '.$last;
    }
}
