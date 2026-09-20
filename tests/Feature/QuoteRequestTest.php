<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestReceived;
use App\Models\District;
use App\Models\Province;
use App\Models\QuoteRequest;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuoteRequestTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function validPayload(array $overrides = []): array
    {
        $province = Province::where('slug', 'tekirdag')->first();

        return array_merge([
            'name' => 'Test Müşteri',
            'phone' => '0532 111 22 33',
            'province_id' => $province->id,
            'district_id' => District::where('province_id', $province->id)->where('slug', 'corlu')->value('id'),
            'service_id' => Service::where('slug', 'gardirop-montaji')->value('id'),
            'message' => '3 kapaklı sürgülü gardırop.',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'kvkk' => '1',
        ], $overrides);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->post('/teklif-al', [])
            ->assertSessionHasErrors(['name', 'phone', 'kvkk']);

        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_valid_request_is_stored_with_photos_and_notification_mail(): void
    {
        Storage::fake('local');
        Mail::fake();
        Setting::set('notification_email', 'bildirim@example.com');
        Setting::flush();

        $response = $this->post('/teklif-al', $this->validPayload([
            'photos' => [
                UploadedFile::fake()->image('gardirop.jpg', 800, 600),
                UploadedFile::fake()->image('kutu.webp', 800, 600),
            ],
        ]));

        $response->assertRedirect(route('quote.thanks'));

        $quote = QuoteRequest::first();
        $this->assertNotNull($quote);
        $this->assertSame('Test Müşteri', $quote->name);
        $this->assertSame(QuoteRequest::STATUS_NEW, $quote->status);
        $this->assertTrue($quote->kvkk_accepted);
        $this->assertSame('Çorlu / Tekirdağ', $quote->location_label);
        $this->assertCount(2, $quote->photos);

        foreach ($quote->photos as $path) {
            Storage::disk('local')->assertExists($path);
        }

        Mail::assertSent(QuoteRequestReceived::class, fn ($mail) => $mail->hasTo('bildirim@example.com'));
    }

    public function test_request_without_optional_fields_is_accepted(): void
    {
        $this->post('/teklif-al', ['name' => 'Ali', 'phone' => '+90 532 111 22 33', 'kvkk' => '1'])
            ->assertRedirect(route('quote.thanks'));

        $this->assertDatabaseCount('quote_requests', 1);
    }

    public function test_disallowed_file_types_and_too_many_photos_are_rejected(): void
    {
        Storage::fake('local');

        $this->post('/teklif-al', $this->validPayload([
            'photos' => [UploadedFile::fake()->create('belge.pdf', 100, 'application/pdf')],
        ]))->assertSessionHasErrors('photos.0');

        $this->post('/teklif-al', $this->validPayload([
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 6)),
        ]))->assertSessionHasErrors('photos');

        $this->post('/teklif-al', $this->validPayload([
            'photos' => [UploadedFile::fake()->image('buyuk.jpg')->size(6000)],
        ]))->assertSessionHasErrors('photos.0');

        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_honeypot_and_invalid_inputs_are_rejected(): void
    {
        $this->post('/teklif-al', $this->validPayload(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->post('/teklif-al', $this->validPayload(['phone' => 'abc']))
            ->assertSessionHasErrors('phone');

        $this->post('/teklif-al', $this->validPayload(['preferred_date' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('preferred_date');

        $otherDistrict = District::whereHas('province', fn ($q) => $q->where('slug', 'edirne'))->value('id');
        $this->post('/teklif-al', $this->validPayload(['district_id' => $otherDistrict]))
            ->assertSessionHasErrors('district_id');

        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_page_url_only_accepts_same_site_http_urls(): void
    {
        $this->post('/teklif-al', $this->validPayload(['page_url' => 'javascript:alert(1)']));
        $this->assertNull(QuoteRequest::latest('id')->first()->page_url);

        $this->post('/teklif-al', $this->validPayload(['page_url' => 'https://evil.example/x']));
        $this->assertNull(QuoteRequest::latest('id')->first()->page_url);

        $this->post('/teklif-al', $this->validPayload(['page_url' => url('/tekirdag/corlu')]));
        $this->assertSame(url('/tekirdag/corlu'), QuoteRequest::latest('id')->first()->page_url);
    }
}
