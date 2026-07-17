<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_passwords_page_requires_authentication(): void
    {
        $this->get(route('passwords.index'))->assertRedirect(route('login'));
    }

    public function test_passwords_page_renders_for_a_signed_in_user(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('passwords.index'))->assertOk()->assertSee('passwords', false);
    }
}
