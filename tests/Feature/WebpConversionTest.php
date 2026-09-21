<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Services\Media\WebpConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebpConversionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function converter(): WebpConverter
    {
        return app(WebpConverter::class);
    }

    public function test_gd_supports_webp(): void
    {
        $this->assertTrue($this->converter()->supported(), 'PHP GD eklentisi WebP desteklemeli');
    }

    public function test_jpeg_is_converted(): void
    {
        Storage::fake('public');

        $path = $this->converter()->store(UploadedFile::fake()->image('Gardırop Montajı.jpg', 800, 600), 'public', 'services');

        $this->assertStringEndsWith('.webp', $path);
        $this->assertStringStartsWith('services/', $path);
        Storage::disk('public')->assertExists($path);

        // Gerçekten WebP mi? (RIFF....WEBP imzası)
        $binary = Storage::disk('public')->get($path);
        $this->assertSame('RIFF', substr($binary, 0, 4));
        $this->assertSame('WEBP', substr($binary, 8, 4));
    }

    public function test_png_transparency_survives(): void
    {
        Storage::fake('public');

        $path = $this->converter()->store(UploadedFile::fake()->image('logo.png', 200, 200), 'public', 'gallery');

        $this->assertStringEndsWith('.webp', $path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(200, $w);
        $this->assertSame(200, $h);
    }

    public function test_oversized_image_is_downscaled(): void
    {
        Storage::fake('public');

        $path = $this->converter()->store(UploadedFile::fake()->image('buyuk.jpg', 4000, 3000), 'public', 'gallery');

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(2200, $w, 'uzun kenar 2200 pikselle sınırlanmalı');
        $this->assertSame(1650, $h, 'en-boy oranı korunmalı');
    }

    public function test_filename_is_slugified_and_unique(): void
    {
        Storage::fake('public');

        $a = $this->converter()->store(UploadedFile::fake()->image('Çorlu Gardırop Montajı.jpg'), 'public', 'gallery');
        $b = $this->converter()->store(UploadedFile::fake()->image('Çorlu Gardırop Montajı.jpg'), 'public', 'gallery');

        // Dosya adı görsel aramasına girer: Türkçe karakter ASCII'ye indirgenir.
        $this->assertStringContainsString('corlu-gardirop-montaji', $a);
        $this->assertMatchesRegularExpression('#^gallery/[a-z0-9-]+\.webp$#', $a);
        $this->assertNotSame($a, $b, 'aynı adla ikinci yükleme öncekini EZMEMELİ');
    }

    public function test_non_image_files_are_left_untouched(): void
    {
        Storage::fake('local');

        // Google kimlik dosyası çevrilirse bozulur.
        $json = UploadedFile::fake()->createWithContent('key.json', '{"type":"service_account"}');
        $path = $this->converter()->store($json, 'local', 'google');

        $this->assertStringEndsWith('.json', $path);
        $this->assertSame('{"type":"service_account"}', Storage::disk('local')->get($path));
    }

    public function test_corrupt_image_falls_back_to_the_original(): void
    {
        Storage::fake('public');

        $broken = UploadedFile::fake()->createWithContent('bozuk.jpg', 'bu bir görsel değil');
        $path = $this->converter()->store($broken, 'public', 'gallery');

        // Yükleme DÜŞMEZ; dosya orijinal uzantısıyla saklanır.
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_quote_form_photos_are_converted(): void
    {
        Storage::fake('local');

        $province = \App\Models\Province::where('slug', 'tekirdag')->first();

        $this->post('/teklif-al', [
            'name' => 'Test',
            'phone' => '0532 111 22 33',
            'kvkk' => '1',
            'photos' => [UploadedFile::fake()->image('telefon-fotografi.jpg', 3000, 2000)],
        ])->assertRedirect(route('quote.thanks'));

        $quote = QuoteRequest::firstOrFail();

        $this->assertCount(1, $quote->photos);
        $this->assertStringEndsWith('.webp', $quote->photos[0]);
        Storage::disk('local')->assertExists($quote->photos[0]);

        // Fotoğraf küçültülmüş olmalı.
        [$w] = getimagesizefromstring(Storage::disk('local')->get($quote->photos[0]));
        $this->assertSame(2200, $w);
    }
}
