<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Province;
use App\Models\Service;

/**
 * JSON-LD (schema.org) yapılandırılmış veri üreticileri.
 */
class SchemaOrg
{
    public static function localBusiness(): array
    {
        $sameAs = collect([setting('facebook_url'), setting('instagram_url'), setting('youtube_url')])->filter()->values()->all();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'HomeAndConstructionBusiness',
            '@id' => url('/').'#business',
            'name' => site_name(),
            'description' => (string) setting('meta_description'),
            'url' => url('/'),
            'telephone' => phone_digits(site_phone()),
            'image' => media_url(setting('hero_image'), asset('images/placeholder.svg')),
            'priceRange' => '₺₺',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => (string) setting('address'),
                'addressCountry' => 'TR',
            ],
            'areaServed' => Province::query()->active()->ordered()->pluck('name')->map(fn ($name) => [
                '@type' => 'State',
                'name' => $name,
            ])->values()->all(),
        ];

        if ($email = setting('email')) {
            $data['email'] = $email;
        }

        if ($hours = setting('working_hours')) {
            $data['openingHours'] = $hours;
        }

        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /**
     * @param  iterable<int, array{question: string, answer: string}>  $faqs
     */
    public static function faq(iterable $faqs): ?array
    {
        $entities = collect($faqs)
            ->filter(fn ($faq) => filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))
            ->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq['answer'])],
            ])->values()->all();

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Blog yazısı için Article şeması.
     *
     * DİKKAT: aggregateRating / reviewCount BASILMAZ. Gerçek, doğrulanabilir veri
     * olmadan puan basmak manuel ceza riskidir (spec §3.9, §10.1).
     */
    public static function article(Blog $post): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->meta_description ?: $post->summary,
            'url' => $post->url,
            'inLanguage' => 'tr-TR',
            'datePublished' => optional($post->publish_at ?? $post->created_at)->toAtomString(),
            'dateModified' => optional($post->updated_at)->toAtomString(),
            'publisher' => ['@id' => url('/').'#business'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $post->url],
        ];

        if (filled($post->image)) {
            $data['image'] = $post->image_url;
        }

        if ($post->category) {
            $data['articleSection'] = $post->category->name;
        }

        return $data;
    }

    public static function service(Service $service, ?string $areaName = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $service->title.($areaName ? ' - '.$areaName : ''),
            'serviceType' => $service->title,
            'description' => $service->meta_description ?: $service->short_description,
            'url' => $service->url,
            'image' => $service->image_url,
            'provider' => ['@id' => url('/').'#business'],
            'areaServed' => $areaName ?: (string) setting('service_area_text'),
        ];
    }
}
