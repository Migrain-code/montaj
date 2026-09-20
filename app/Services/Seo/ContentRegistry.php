<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\District;
use App\Models\Page;
use App\Models\Province;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Skorlanabilir tüm içeriği tek yerden toplar ve ortak ScorableContent biçimine çevirir.
 *
 * Yeni bir içerik tipi eklendiğinde SADECE burası değişir; skorlayıcı bilmez.
 */
class ContentRegistry
{
    public const TYPES = [
        'service' => 'Hizmet',
        'province' => 'İl',
        'district' => 'İlçe',
        'blog' => 'Blog yazısı',
        'page' => 'Sayfa',
    ];

    /**
     * Yalnız ERİŞİLEBİLİR içerik: yayında olan ve gerçekten 200 dönen sayfalar.
     *
     * Yayından kaldırılmış bir ilçe sayfası ziyaretçiye 404 döner; onu skorlamak
     * düzeltilemeyecek uyarı üretir ve ortalamayı yanıltıcı biçimde düşürür.
     * İlçe için il de aktif olmalıdır — aksi hâlde adres yine 404'tür.
     *
     * @return Collection<int, ScorableContent>
     */
    public function reachable(): Collection
    {
        return $this->all()->filter(fn (ScorableContent $c) => $c->published)->values();
    }

    /** Tüm içerik, yayın durumundan bağımsız. @return Collection<int, ScorableContent> */
    public function all(): Collection
    {
        return collect()
            ->merge($this->services())
            ->merge($this->provinces())
            ->merge($this->districts())
            ->merge($this->blogs())
            ->merge($this->pages());
    }

    public function forModel(Model $model): ?ScorableContent
    {
        return match (true) {
            $model instanceof Service => $this->fromService($model),
            $model instanceof Province => $this->fromProvince($model),
            $model instanceof District => $this->fromDistrict($model->loadMissing('province')),
            $model instanceof Blog => $this->fromBlog($model),
            $model instanceof Page => $this->fromPage($model),
            default => null,
        };
    }

    /** İçerik tipi + id'den asıl modeli bulur (panelde "düzenle" bağlantısı için). */
    public function resolve(string $type, int|string $id): ?Model
    {
        return match ($type) {
            'service' => Service::find($id),
            'province' => Province::find($id),
            'district' => District::with('province')->find($id),
            'blog' => Blog::find($id),
            'page' => Page::find($id),
            default => null,
        };
    }

    private function services(): Collection
    {
        return Service::query()->get()->map(fn (Service $s) => $this->fromService($s));
    }

    private function provinces(): Collection
    {
        return Province::query()->get()->map(fn (Province $p) => $this->fromProvince($p));
    }

    private function districts(): Collection
    {
        return District::query()->with('province:id,slug,is_active')->get()->map(fn (District $d) => $this->fromDistrict($d));
    }

    private function blogs(): Collection
    {
        return Blog::query()->get()->map(fn (Blog $b) => $this->fromBlog($b));
    }

    private function pages(): Collection
    {
        return Page::query()->get()->map(fn (Page $p) => $this->fromPage($p));
    }

    private function fromService(Service $service): ScorableContent
    {
        return new ScorableContent(
            contentType: 'service',
            contentId: $service->getKey(),
            title: (string) $service->title,
            url: url('/'.$service->slug),
            metaTitle: $service->meta_title,
            metaDescription: $service->meta_description,
            bodyHtml: $service->description,
            slug: $service->slug,
            published: (bool) $service->is_active,
            hasImage: filled($service->image),
            faqCount: count($service->faqs ?? []),
            extraText: array_merge(
                (array) $service->what_we_do,
                (array) $service->suitable_for,
                collect($service->process_steps ?? [])->flatMap(fn ($s) => [$s['title'] ?? '', $s['description'] ?? ''])->all(),
                collect($service->faqs ?? [])->flatMap(fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''])->all(),
            ),
        );
    }

    private function fromProvince(Province $province): ScorableContent
    {
        return new ScorableContent(
            contentType: 'province',
            contentId: $province->getKey(),
            title: (string) $province->name,
            url: url('/'.$province->slug),
            metaTitle: $province->meta_title,
            metaDescription: $province->meta_description,
            bodyHtml: $province->content,
            slug: $province->slug,
            published: (bool) $province->is_active,
            hasImage: true, // il sayfaları ortak başlık görselini kullanır
            faqCount: count($province->faqs ?? []),
            extraText: collect($province->faqs ?? [])->flatMap(fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''])->all(),
        );
    }

    private function fromDistrict(District $district): ScorableContent
    {
        $provinceSlug = $district->province?->slug;

        return new ScorableContent(
            contentType: 'district',
            contentId: $district->getKey(),
            title: (string) $district->name,
            url: $provinceSlug ? url('/'.$provinceSlug.'/'.$district->slug) : null,
            metaTitle: $district->meta_title,
            metaDescription: $district->meta_description,
            bodyHtml: $district->content,
            slug: $district->slug,
            published: (bool) $district->is_active && (bool) $district->province?->is_active,
            hasImage: true,
            faqCount: count($district->faqs ?? []),
            extraText: array_merge(
                (array) $district->neighborhoods,
                collect($district->faqs ?? [])->flatMap(fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''])->all(),
            ),
        );
    }

    private function fromBlog(Blog $blog): ScorableContent
    {
        return new ScorableContent(
            contentType: 'blog',
            contentId: $blog->getKey(),
            title: (string) $blog->title,
            url: $blog->url,
            metaTitle: $blog->meta_title,
            metaDescription: $blog->meta_description,
            bodyHtml: $blog->body_html,
            slug: $blog->slug,
            published: $blog->is_published,
            hasImage: filled($blog->image),
            faqCount: count($blog->faqs ?? []),
            primaryKeyword: $blog->primary_keyword,
            extraText: collect($blog->faqs ?? [])->flatMap(fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''])->all(),
        );
    }

    private function fromPage(Page $page): ScorableContent
    {
        return new ScorableContent(
            contentType: 'page',
            contentId: $page->getKey(),
            title: (string) $page->title,
            url: url('/'.$page->slug),
            metaTitle: $page->meta_title,
            metaDescription: $page->meta_description,
            bodyHtml: $page->content,
            slug: $page->slug,
            published: (bool) $page->is_active,
            hasImage: true,
            faqCount: 0,
        );
    }
}
