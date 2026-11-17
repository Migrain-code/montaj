<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Canlıda www sürümü ayrı sayfa gibi dizine giriyordu. */
class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_www_is_permanently_redirected_to_the_bare_domain(): void
    {
        config(['app.url' => 'https://ornek.test']);

        $this->get('https://www.ornek.test/tekirdag?x=1')
            ->assertStatus(301)
            ->assertRedirect('https://ornek.test/tekirdag?x=1');
    }

    public function test_the_bare_domain_and_other_hosts_are_left_alone(): void
    {
        config(['app.url' => 'https://ornek.test']);

        $this->get('https://ornek.test/')->assertOk();
        $this->get('http://localhost/')->assertOk();
    }

    public function test_http_is_redirected_to_https_when_forced(): void
    {
        config(['app.url' => 'https://ornek.test', 'app.force_https' => true]);

        $this->get('http://ornek.test/tekirdag?x=1')
            ->assertStatus(301)
            ->assertRedirect('https://ornek.test/tekirdag?x=1');

        // www + http tek adımda kanonik adrese gider.
        $this->get('http://www.ornek.test/tekirdag')->assertRedirect('https://ornek.test/tekirdag');

        $this->get('https://ornek.test/')->assertOk();
        // Vekil sunucu TLS'i sonlandırıyorsa döngü olmaz.
        $this->get('http://ornek.test/', ['X-Forwarded-Proto' => 'https'])->assertOk();
    }

    public function test_https_is_not_forced_locally_or_for_form_posts(): void
    {
        config(['app.url' => 'https://ornek.test', 'app.force_https' => true]);

        $this->get('http://localhost/')->assertOk();
        $this->get('http://127.0.0.1/')->assertOk();
        // Gövdeli istek 301 ile yönlenmez (form verisi kaybolurdu); doğrulama yanıtı döner.
        $this->assertNotSame(301, $this->post('http://ornek.test/teklif-al', [])->getStatusCode());
    }

    public function test_https_is_off_by_default_for_an_http_app_url(): void
    {
        config(['app.url' => 'http://ornek.test', 'app.force_https' => false]);

        $this->get('http://ornek.test/')->assertOk();
    }
}
