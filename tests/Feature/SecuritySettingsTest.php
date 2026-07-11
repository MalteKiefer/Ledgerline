<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_the_vault_lock_policy(): void
    {
        $this->signIn(); // single-user install = admin

        $this->get(route('settings.security.edit'))->assertOk();

        $this->put(route('settings.security.update'), [
            'vault_remember_days' => 14,
            'vault_public_idle_minutes' => 5,
        ])->assertRedirect();

        $s = AppSettings::current();
        $this->assertSame(14, $s->vault_remember_days);
        $this->assertSame(5, $s->vault_public_idle_minutes);
    }

    public function test_it_validates_the_ranges(): void
    {
        $this->signIn();
        $this->put(route('settings.security.update'), ['vault_remember_days' => 0, 'vault_public_idle_minutes' => 99999])
            ->assertSessionHasErrors(['vault_remember_days', 'vault_public_idle_minutes']);
    }
}
