<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public static function publicUrls(): array
    {
        return collect([
            '/', '/hizmetler', '/mobilya-montaji', '/ikea-mobilya-montaji', '/gardirop-montaji',
            '/yatak-baza-montaji', '/masa-sandalye-montaji', '/tv-unitesi-montaji', '/kitaplik-montaji',
            '/ofis-mobilyasi-montaji', '/bolgeler', '/tekirdag', '/tekirdag/corlu', '/tekirdag/cerkezkoy',
            '/tekirdag/kapakli', '/edirne', '/edirne/kesan', '/kirklareli', '/kirklareli/luleburgaz',
            '/galeri', '/galeri?kategori=yatak', '/hakkimizda', '/sss', '/iletisim', '/teklif-al',
            '/teklif-al/tesekkurler', '/kvkk-aydinlatma-metni', '/sitemap.xml',
        ])->mapWithKeys(fn ($url) => [$url => [$url]])->all();
    }

    #[DataProvider('publicUrls')]
    public function test_public_page_loads(string $url): void
    {
        $this->get($url)->assertOk();
    }

    public function test_every_seeded_district_page_loads(): void
    {
        foreach (District::with('province')->get() as $district) {
            $this->get('/'.$district->province->slug.'/'.$district->slug)
                ->assertOk()
                ->assertSee($district->name.' Mobilya Montaj Hizmeti');
        }
    }

    public function test_unknown_urls_return_404(): void
    {
        $this->get('/olmayan-sayfa')->assertNotFound();
        $this->get('/tekirdag/olmayan-ilce')->assertNotFound();
        $this->get('/olmayan-il/corlu')->assertNotFound();
    }

    public function test_home_has_primary_conversion_points_and_seo_tags(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee("WhatsApp'tan Teklif Al", false);
        $response->assertSee('Hemen Ara');
        $response->assertSee('https://wa.me/', false);
        $response->assertSee('href="tel:+', false);
        $response->assertSee('<link rel="canonical" href="'.url('/').'">', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('HomeAndConstructionBusiness', false);
        $response->assertSee('loading="lazy"', false);
    }

    public function test_phone_and_whatsapp_come_from_settings_not_code(): void
    {
        Setting::set('phone', '+90 532 111 22 33');
        Setting::set('whatsapp', '905321112233');
        Setting::flush();

        $this->get('/')
            ->assertSee('tel:+905321112233', false)
            ->assertSee('https://wa.me/905321112233', false)
            ->assertSee('+90 532 111 22 33');
    }

    public function test_district_page_has_local_content_and_prefilled_whatsapp_message(): void
    {
        $response = $this->get('/tekirdag/corlu')->assertOk();

        $response->assertSee('Reşadiye');
        $response->assertSee('Çorlu için sık sorulan sorular');
        $response->assertSee(rawurlencode('Çorlu, Tekirdağ'), false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertSee('FAQPage', false);
    }

    public function test_service_page_has_required_sections(): void
    {
        $this->get('/gardirop-montaji')
            ->assertOk()
            ->assertSee('Neler yapıyoruz?')
            ->assertSee('Kimler için uygun?')
            ->assertSee('Çalışma süreci')
            ->assertSee('Sık sorulan sorular')
            ->assertSee('Hizmet verilen bölgeler')
            ->assertSee('"@type":"Service"', false);
    }

    public function test_inactive_service_is_hidden(): void
    {
        Service::where('slug', 'kitaplik-montaji')->update(['is_active' => false]);

        $this->get('/kitaplik-montaji')->assertNotFound();
        $this->get('/hizmetler')->assertDontSee('Kitaplık Montajı');
    }

    public function test_testimonials_section_only_shows_real_reviews(): void
    {
        $this->get('/')->assertDontSee('Müşterilerimiz Ne Diyor?');

        Testimonial::create(['name' => 'Ayşe K.', 'location' => 'Çorlu', 'comment' => 'Gardırobu iki saatte kurdular.', 'rating' => 5]);

        $this->get('/')->assertSee('Müşterilerimiz Ne Diyor?')->assertSee('Gardırobu iki saatte kurdular.');
    }

    public function test_sitemap_lists_services_and_regions(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(url('/gardirop-montaji'), false)
            ->assertSee(url('/tekirdag/corlu'), false)
            ->assertSee(url('/kirklareli/luleburgaz'), false);
    }

    public function test_robots_txt_points_to_absolute_sitemap_and_hides_admin(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertSee('Disallow: /admin', false);
    }
}
