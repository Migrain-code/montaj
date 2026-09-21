<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\Service;
use App\Services\Security\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Google reCAPTCHA.
 *
 * En önemli iki kural burada sınanıyor:
 *   1. Anahtar girilmemişse form AYNEN çalışır (yarım kurulum formu kilitlemez).
 *   2. Google'a ulaşılamazsa gönderim geçer, Google açıkça reddederse geçmez.
 */
class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function enable(string $version = 'v3', float $minScore = 0.5): void
    {
        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
            'services.recaptcha.version' => $version,
            'services.recaptcha.min_score' => $minScore,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        $province = Province::where('slug', 'tekirdag')->first();

        return array_merge([
            'photos' => [UploadedFile::fake()->image('gardirop.jpg', 800, 600)],
            'name' => 'Test Müşteri',
            'phone' => '0532 111 22 33',
            'province_id' => $province->id,
            'district_id' => District::where('province_id', $province->id)->where('slug', 'corlu')->value('id'),
            'service_id' => Service::where('slug', 'gardirop-montaji')->value('id'),
            'kvkk' => '1',
        ], $overrides);
    }

    public function test_it_is_disabled_until_both_keys_are_present(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);
        $this->assertFalse(app(Recaptcha::class)->enabled());

        // Tek anahtar yeterli DEĞİL: yarım yapılandırma formu kilitlememeli.
        config(['services.recaptcha.site_key' => 'yalnizca-site']);
        $this->assertFalse(app(Recaptcha::class)->enabled());

        config(['services.recaptcha.secret_key' => 'gizli']);
        $this->assertTrue(app(Recaptcha::class)->enabled());
    }

    public function test_form_works_normally_when_keys_are_missing(): void
    {
        Storage::fake('local');
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);
        Http::fake(); // Google'a hiç gidilmemeli

        $this->post('/teklif-al', $this->payload())->assertRedirect(route('quote.thanks'));

        $this->assertDatabaseCount('quote_requests', 1);
        Http::assertNothingSent();
    }

    public function test_valid_v3_token_passes(): void
    {
        Storage::fake('local');
        $this->enable();

        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'teklif_formu']),
        ]);

        $this->post('/teklif-al', $this->payload(['g-recaptcha-response' => 'gecerli-jeton']))
            ->assertRedirect(route('quote.thanks'));

        $this->assertDatabaseCount('quote_requests', 1);
    }

    public function test_low_v3_score_is_rejected(): void
    {
        Storage::fake('local');
        $this->enable(minScore: 0.5);

        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.1, 'action' => 'teklif_formu']),
        ]);

        $this->post('/teklif-al', $this->payload(['g-recaptcha-response' => 'bot-jetonu']))
            ->assertSessionHasErrors(Recaptcha::FIELD);

        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_token_from_another_action_is_rejected(): void
    {
        Storage::fake('local');
        $this->enable();

        // Başka bir sayfadan alınmış jeton bu forma taşınamaz.
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'baska_sayfa']),
        ]);

        $this->post('/teklif-al', $this->payload(['g-recaptcha-response' => 'tasinmis-jeton']))
            ->assertSessionHasErrors(Recaptcha::FIELD);
    }

    public function test_missing_token_is_rejected_when_enabled(): void
    {
        Storage::fake('local');
        $this->enable();
        Http::fake();

        $this->post('/teklif-al', $this->payload())->assertSessionHasErrors(Recaptcha::FIELD);

        $this->assertDatabaseCount('quote_requests', 0);
        // Boş jeton için Google'a gitmeye gerek yok.
        Http::assertNothingSent();
    }

    public function test_v2_checkbox_ignores_score(): void
    {
        Storage::fake('local');
        $this->enable('v2');

        // v2 yanıtında puan alanı hiç yoktur; bu bir hata değildir.
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true]),
        ]);

        $this->post('/teklif-al', $this->payload(['g-recaptcha-response' => 'kutucuk-jetonu']))
            ->assertRedirect(route('quote.thanks'));

        $this->assertDatabaseCount('quote_requests', 1);
    }

    public function test_google_rejection_blocks_the_submission(): void
    {
        Storage::fake('local');
        $this->enable('v2');

        Http::fake([
            'www.google.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);

        $this->post('/teklif-al', $this->payload(['g-recaptcha-response' => 'sahte']))
            ->assertSessionHasErrors(Recaptcha::FIELD);

        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_unreachable_google_lets_a_real_customer_through(): void
    {
        Storage::fake('local');
        $this->enable();

        // Ağ hatası gerçek müşteriyi kapıda bırakmamalı (bilinçli fail-open).
        Http::fake(fn () => throw new ConnectionException('bağlanılamadı'));

        $this->post('/teklif-al', $this->payload(['g-recaptcha-response' => 'jeton']))
            ->assertRedirect(route('quote.thanks'));

        $this->assertDatabaseCount('quote_requests', 1);
    }

    public function test_google_server_error_lets_a_real_customer_through(): void
    {
        Storage::fake('local');
        $this->enable();

        Http::fake(['www.google.com/*' => Http::response('', 503)]);

        $this->post('/teklif-al', $this->payload(['g-recaptcha-response' => 'jeton']))
            ->assertRedirect(route('quote.thanks'));

        $this->assertDatabaseCount('quote_requests', 1);
    }

    public function test_out_of_range_score_falls_back_to_the_safe_default(): void
    {
        $this->enable(minScore: 0);
        $this->assertSame(0.5, app(Recaptcha::class)->minScore());

        $this->enable(minScore: 5);
        $this->assertSame(0.5, app(Recaptcha::class)->minScore());
    }

    public function test_v3_renders_a_hidden_field_and_the_required_notice(): void
    {
        $this->enable();

        $response = $this->get('/teklif-al');

        $response->assertOk();
        $response->assertSee('name="'.Recaptcha::FIELD.'"', false);
        $response->assertSee('recaptcha/api.js?render=test-site-key', false);
        // Rozet gizlendiği için Google bu bilgilendirmeyi zorunlu tutuyor.
        $response->assertSee('Gizlilik Politikası', false);
    }

    public function test_v2_renders_the_checkbox_widget(): void
    {
        $this->enable('v2');

        $response = $this->get('/teklif-al');

        $response->assertOk();
        $response->assertSee('g-recaptcha', false);
        $response->assertSee('data-sitekey="test-site-key"', false);
        // v2'de gizli alan OLMAMALI: Google kendi alanını kendisi ekler, çift olur.
        $response->assertDontSee('input type="hidden" name="'.Recaptcha::FIELD.'"', false);
    }

    public function test_nothing_is_rendered_without_keys(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);

        $this->get('/teklif-al')->assertOk()->assertDontSee('recaptcha/api.js', false);
    }
}
