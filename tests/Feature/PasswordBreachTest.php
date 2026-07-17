<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordBreachTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->get(route('passwords.breach', ['prefix' => 'ABCDE']))->assertRedirect(route('login'));
    }

    public function test_rejects_a_malformed_prefix_without_fetching(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('passwords.breach', ['prefix' => 'nope']))->assertStatus(422);
        $this->get(route('passwords.breach', ['prefix' => 'ABC']))->assertStatus(422);      // too short
        $this->get(route('passwords.breach', ['prefix' => 'ABCDEF']))->assertStatus(422);   // too long
    }
}
