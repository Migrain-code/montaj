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
}
