<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Rules\UniquePublicSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_phone_digits_normalises_turkish_numbers(): void
    {
        $this->assertSame('+905321112233', phone_digits('+90 532 111 22 33'));
        $this->assertSame('+905321112233', phone_digits('0532 111 22 33'));
        $this->assertSame('+905321112233', phone_digits('532 111 22 33'));
        $this->assertSame('+905321112233', phone_digits('(0532) 111-22-33'));
        $this->assertSame('', phone_digits(null));
    }

    public function test_whatsapp_url_uses_settings_and_placeholders(): void
    {
        Setting::set('whatsapp', '905321112233');
        Setting::set('whatsapp_message', 'Merhaba, {hizmet} için fiyat almak istiyorum. Bölge: {bolge}');
        Setting::flush();

        $url = whatsapp_url('Çorlu, Tekirdağ', 'Gardırop Montajı');

        $this->assertStringStartsWith('https://wa.me/905321112233?text=', $url);
        $this->assertStringContainsString(rawurlencode('Gardırop Montajı için fiyat almak istiyorum. Bölge: Çorlu, Tekirdağ'), $url);
    }

    public function test_settings_fall_back_to_env_config(): void
    {
        Setting::where('key', 'phone')->delete();
        Setting::flush();
        config(['site.phone' => '+90 500 000 00 00']);

        $this->assertSame('+90 500 000 00 00', site_phone());
    }

    public function test_public_slugs_cannot_collide(): void
    {
        $check = fn (string $slug, string $table, $ignore = null) => Validator::make(
            ['slug' => $slug],
            ['slug' => [new UniquePublicSlug($table, $ignore)]]
        )->passes();

        $this->assertFalse($check('tekirdag', 'services'), 'il slug\'ı hizmette kullanılamaz');
        $this->assertFalse($check('gardirop-montaji', 'pages'), 'hizmet slug\'ı sayfada kullanılamaz');
        $this->assertFalse($check('galeri', 'services'), 'sabit rota kullanılamaz');
        $this->assertTrue($check('koltuk-montaji', 'services'));

        $ownId = \App\Models\Service::where('slug', 'gardirop-montaji')->value('id');
        $this->assertTrue($check('gardirop-montaji', 'services', $ownId), 'kayıt kendi slug\'ını koruyabilir');
    }
}
