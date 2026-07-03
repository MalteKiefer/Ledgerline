<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodosTest extends TestCase
{
    use RefreshDatabase;

    private function makeVault(): void
    {
        Vault::create([
            'salt' => 'c2FsdA==', 'kdf_ops' => 3, 'kdf_mem' => 67108864,
            'wrapped_vault_key' => 'd3JhcHBlZA==', 'wrap_nonce' => 'bm9uY2U=',
            'wrapped_vault_key_recovery' => 'cg==', 'recovery_nonce' => 'cm4=',
        ]);
    }

    public function test_the_todos_page_loads(): void
    {
        $this->signIn();
        $this->get(route('todos.index'))->assertOk();
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('todos.index'))->assertRedirect(route('login'));
    }

    public function test_the_todos_manifest_name_is_accepted(): void
    {
        $this->signIn();
        $this->makeVault();
        // The "todos" manifest is allowed alongside files/notes/bookmarks/mail
        // (route constraint) and returns an empty manifest, not a 404.
        $this->getJson(route('vault.manifest.show', ['name' => 'todos']))
            ->assertOk()->assertJsonStructure(['cipher', 'nonce', 'version']);
    }
}
