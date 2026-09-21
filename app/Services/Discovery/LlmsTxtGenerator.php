<?php

namespace App\Services\Discovery;

use App\Models\Brand;
use App\Models\District;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Province;
use App\Models\Service;
use App\Support\AutomationLog;
use Illuminate\Support\Str;

/**
 * llms.txt / llms-full.txt üreticisi (spec §3.9).
 *
 * İçeriğin TAMAMI veritabanından gelir. Hiçbir hizmet, bölge veya rakam uydurulmaz
 * (spec §10.1). Dosyalar cron'da üretilir, istek anında değil (spec §10.7).
 */
class LlmsTxtGenerator
{
    /** @return array{index_bytes: int, full_bytes: int} */
    public function generate(): array
    {
        $index = $this->buildIndex();
        $full = $this->buildFull();

        file_put_contents(public_path('llms.txt'), $index);
        file_put_contents(public_path('llms-full.txt'), $full);

        AutomationLog::summary('discovery.llms', [
            'index_bytes' => strlen($index),
            'full_bytes' => strlen($full),
        ], changed: true);

        return ['index_bytes' => strlen($index), 'full_bytes' => strlen($full)];
    }

    private function buildIndex(): string
    {
        $name = site_name();
        $lines = [];

        $lines[] = '# '.$name;
        $lines[] = '';
        $lines[] = '> '.$this->oneLiner();
        $lines[] = '';
        $lines[] = 'Bu dosya yapay zeka ajanları içindir. Tüm bilgiler sitenin veritabanından üretilir.';
        $lines[] = 'Son güncelleme: '.now()->toDateString();
        $lines[] = '';

        if ($phone = site_phone()) {
            $lines[] = '## İletişim';
            $lines[] = '';
            $lines[] = '- Telefon: '.$phone;

            if ($wa = whatsapp_number()) {
                $lines[] = '- WhatsApp: +'.$wa;
            }

            if ($email = setting('email')) {
                $lines[] = '- E-posta: '.$email;
            }

            if ($hours = setting('working_hours')) {
                $lines[] = '- Çalışma saatleri: '.$hours;
            }

            $lines[] = '';
        }

        $services = Service::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($services->isNotEmpty()) {
            $lines[] = '## Hizmetler';
            $lines[] = '';

            foreach ($services as $service) {
                $lines[] = '- ['.$service->title.']('.url('/'.$service->slug).'): '.$this->clean($service->short_description);
            }

            $lines[] = '';
        }

        // Kendi hizmet sayfasına bağlı marka LİSTELENMEZ: adresi 301 döner.
        $brands = Brand::query()->where('is_active', true)->whereNull('service_id')->orderBy('sort_order')->get();

        if ($brands->isNotEmpty()) {
            $lines[] = '## Montajını yaptığımız markalar';
            $lines[] = '';
            $lines[] = 'Marka adları sahiplerine aittir; '.$name.' bağımsız montaj hizmeti verir, yetkili servis değildir.';
            $lines[] = '';

            foreach ($brands as $brand) {
                $lines[] = '- ['.$brand->heading.']('.url($brand->path()).'): '.$this->clean($brand->description);
            }

            $lines[] = '';
        }

        $provinces = Province::query()->where('is_active', true)->orderBy('sort_order')
            ->with(['districts' => fn ($q) => $q->where('is_active', true)])->get();

        if ($provinces->isNotEmpty()) {
            $lines[] = '## Hizmet bölgeleri';
            $lines[] = '';

            foreach ($provinces as $province) {
                $lines[] = '- ['.$province->name.']('.url('/'.$province->slug).'): '.$this->clean($province->description);

                foreach ($province->districts as $district) {
                    $lines[] = '  - ['.$district->name.']('.url('/'.$province->slug.'/'.$district->slug).'): '.$this->clean($district->description);
                }
            }

            $lines[] = '';
        }

        if (class_exists(\App\Models\Blog::class)) {
            $posts = \App\Models\Blog::query()->where('status', 1)->orderByDesc('publish_at')->limit(50)->get();

            if ($posts->isNotEmpty()) {
                $lines[] = '## Blog';
                $lines[] = '';

                foreach ($posts as $post) {
                    $lines[] = '- ['.$post->title.']('.url('/blog/'.$post->slug).'): '.$this->clean($post->meta_description);
                }

                $lines[] = '';
            }
        }

        $pages = Page::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($pages->isNotEmpty()) {
            $lines[] = '## Diğer sayfalar';
            $lines[] = '';

            foreach ($pages as $page) {
                $lines[] = '- ['.$page->title.']('.url('/'.$page->slug).')';
            }

            $lines[] = '';
        }

        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- [Tam metin sürüm]('.url('/llms-full.txt').')';
        $lines[] = '- [Site haritası]('.url('/sitemap.xml').')';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function buildFull(): string
    {
        $lines = ['# '.site_name().' — tam metin', '', '> '.$this->oneLiner(), ''];

        foreach (Service::query()->where('is_active', true)->orderBy('sort_order')->get() as $service) {
            $lines[] = '## '.$service->title;
            $lines[] = '';
            $lines[] = 'URL: '.url('/'.$service->slug);
            $lines[] = '';
            $lines[] = $this->clean($service->description, 4000);
            $lines[] = '';

            foreach ((array) $service->what_we_do as $item) {
                $lines[] = '- '.$this->clean($item);
            }

            $lines[] = '';

            foreach ((array) $service->faqs as $faq) {
                if (filled($faq['question'] ?? null)) {
                    $lines[] = '**'.$faq['question'].'** '.$this->clean($faq['answer'] ?? '');
                }
            }

            $lines[] = '';
        }

        foreach (Brand::query()->where('is_active', true)->whereNull('service_id')->orderBy('sort_order')->get() as $brand) {
            $lines[] = '## '.$brand->heading;
            $lines[] = '';
            $lines[] = 'URL: '.url($brand->path());
            $lines[] = '';
            $lines[] = $this->clean($brand->content, 3000);
            $lines[] = '';

            foreach ((array) $brand->products as $item) {
                $lines[] = '- '.$this->clean($item);
            }

            $lines[] = '';

            foreach ((array) $brand->faqs as $faq) {
                if (filled($faq['question'] ?? null)) {
                    $lines[] = '**'.$faq['question'].'** '.$this->clean($faq['answer'] ?? '');
                }
            }

            $lines[] = '';
        }

        foreach (District::query()->where('is_active', true)->with('province')->get() as $district) {
            if (! $district->province?->is_active) {
                continue;
            }

            $lines[] = '## '.$district->name.' ('.$district->province->name.')';
            $lines[] = '';
            $lines[] = 'URL: '.url('/'.$district->province->slug.'/'.$district->slug);
            $lines[] = '';
            $lines[] = $this->clean($district->content, 3000);

            if ($neighborhoods = (array) $district->neighborhoods) {
                $lines[] = '';
                $lines[] = 'Hizmet verilen mahalleler: '.implode(', ', $neighborhoods);
            }

            $lines[] = '';
        }

        $faqs = Faq::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($faqs->isNotEmpty()) {
            $lines[] = '## Sık sorulan sorular';
            $lines[] = '';

            foreach ($faqs as $faq) {
                $lines[] = '**'.$faq->question.'**';
                $lines[] = '';
                $lines[] = $this->clean($faq->answer);
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function oneLiner(): string
    {
        return $this->clean(setting('meta_description') ?: setting('site_tagline'), 300);
    }

    private function clean(?string $html, int $limit = 200): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return Str::limit($text, $limit, '');
    }
}
