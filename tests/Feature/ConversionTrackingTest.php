<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\District;
use App\Models\Province;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * GA4 dönüşüm olayları (telefon, WhatsApp, e-posta tıklaması ve talep). Görünür tasarım
 * değişmez: yalnız data-track-area / data-page-type öznitelikleri ve teşekkür sayfasındaki
 * generate_lead çağrısı eklenir.
 */
class ConversionTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function enableAnalytics(): void
    {
        Setting::set('google_analytics_id', 'G-TEST123');
        Setting::flush();
    }

    private function payload(array $overrides = []): array
    {
        $province = Province::where('slug', 'tekirdag')->first();

        return array_merge([
            'photos' => [UploadedFile::fake()->image('gardirop.jpg', 800, 600)],
            'name' => 'Gizli Müşteri',
            'phone' => '0532 987 65 43',
            'email' => 'gizli@example.com',
            'province_id' => $province->id,
            'district_id' => District::where('province_id', $province->id)->where('slug', 'corlu')->value('id'),
            'service_id' => Service::where('slug', 'gardirop-montaji')->value('id'),
            'message' => 'Gizli açıklama metni',
            'kvkk' => '1',
            'page_url' => url('/gardirop-montaji'),
            'page_type' => 'service',
        ], $overrides);
    }

    public function test_successful_quote_fires_generate_lead_once_without_pii(): void
    {
        $this->enableAnalytics();

        $this->post('/teklif-al', $this->payload())->assertRedirect(route('quote.thanks'));

        $html = $this->get(route('quote.thanks'))->assertOk()->getContent();

        $this->assertStringContainsString("gtag('event', 'generate_lead'", $html);
        $this->assertStringContainsString('"form":"quote"', $html);
        $this->assertStringContainsString('"service":"gardirop-montaji"', $html);
        $this->assertStringContainsString('"page_type":"service"', $html);
        $this->assertMatchesRegularExpression('#"page_path":"\\\\?/gardirop-montaji"#', $html);

        // Kişisel veri olay gövdesine girmez.
        $event = $this->eventPayload($html);
        foreach (['Gizli', '0532', '987 65 43', 'gizli@example.com', 'açıklama'] as $pii) {
            $this->assertStringNotContainsString($pii, $event);
        }
        $this->assertStringNotContainsString('name', $event);
        $this->assertStringNotContainsString('phone', $event);
        $this->assertStringNotContainsString('email', $event);

        // Sayfa yenilenince (flash tükendi) olay tekrar gönderilmez.
        $this->get(route('quote.thanks'))->assertOk()->assertDontSee('generate_lead', false);
    }

    public function test_thanks_page_without_flash_has_no_lead_event(): void
    {
        $this->enableAnalytics();

        $this->get(route('quote.thanks'))->assertOk()->assertDontSee('generate_lead', false);
    }

    public function test_no_lead_event_when_analytics_is_not_configured(): void
    {
        $this->withSession(['lead' => ['form' => 'quote']])
            ->get(route('quote.thanks'))
            ->assertOk()
            ->assertDontSee('generate_lead', false)
            ->assertDontSee('googletagmanager.com', false);
    }

    public function test_contact_form_is_reported_as_contact(): void
    {
        $this->enableAnalytics();

        $this->post('/teklif-al', $this->payload(['source' => 'contact', 'page_url' => url('/iletisim'), 'page_type' => 'contact']));

        $this->get(route('quote.thanks'))->assertSee('"form":"contact"', false)->assertSee('"page_type":"contact"', false);
    }

    public function test_untrusted_page_values_are_dropped(): void
    {
        $this->enableAnalytics();

        $this->post('/teklif-al', $this->payload([
            'page_url' => 'https://baska-site.example/</script><script>alert(1)',
            'page_type' => '"><script>',
            'service_id' => null,
        ]));

        $html = $this->get(route('quote.thanks'))->getContent();
        $event = $this->eventPayload($html);

        $this->assertStringContainsString('"form":"quote"', $event);
        $this->assertStringNotContainsString('page_path', $event);
        $this->assertStringNotContainsString('page_type', $event);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_body_carries_page_type(): void
    {
        $brand = Brand::query()->active()->whereNull('service_id')->firstOrFail();

        $cases = [
            '/' => 'home',
            '/gardirop-montaji' => 'service',
            '/tekirdag' => 'province',
            '/tekirdag/corlu' => 'district',
            $brand->path() => 'brand',
            '/blog' => 'blog',
            '/teklif-al' => 'quote',
            '/iletisim' => 'contact',
            '/sss' => 'page',
            '/hizmetler' => 'service_list',
            '/bolgeler' => 'region_list',
            '/galeri' => 'gallery',
        ];

        foreach ($cases as $url => $type) {
            $this->get($url)->assertOk()->assertSee('data-page-type="'.$type.'"', false);
        }

        // Teklif formu, doldurulduğu sayfanın türünü gizli alanla taşır.
        $this->get('/')->assertSee('name="page_type" value="home"', false);
        $this->get('/iletisim')->assertSee('name="page_type" value="contact"', false);
    }

    public function test_layout_marks_tracking_areas(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['header', 'footer', 'mobile_menu', 'mobile_cta_bar', 'floating_whatsapp', 'hero', 'quote_form'] as $area) {
            $this->assertStringContainsString('data-track-area="'.$area.'"', $html, $area);
        }

        $html = $this->get('/gardirop-montaji')->assertOk()->getContent();
        foreach (['page_banner'] as $area) {
            $this->assertStringContainsString('data-track-area="'.$area.'"', $html, $area);
        }
    }

    /** Teşekkür sayfasındaki generate_lead çağrısının parametre nesnesi. */
    private function eventPayload(string $html): string
    {
        $this->assertSame(1, preg_match("/gtag\\('event', 'generate_lead', (\\{.*?\\})\\);/s", $html, $m), 'generate_lead çağrısı bulunamadı');

        return json_encode(json_decode($m[1], true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
