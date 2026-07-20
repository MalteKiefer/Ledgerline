<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SharedVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedVaultKindTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_defaults_to_password_kind(): void
    {
        $this->signIn();
        $id = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk'])
            ->assertCreated()->json('id');
        $this->assertSame('password', SharedVault::find($id)->kind);
    }

    public function test_store_can_create_a_folder_vault(): void
    {
        $this->signIn();
        $id = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk', 'kind' => 'folder'])
            ->assertCreated()->json('id');
        $this->assertSame('folder', SharedVault::find($id)->kind);
    }

    public function test_store_rejects_unknown_kind(): void
    {
        $this->signIn();
        $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk', 'kind' => 'bogus'])
            ->assertStatus(422);
    }

    public function test_index_filters_by_kind(): void
    {
        $user = $this->signIn();
        $pw = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk'])->json('id');
        $fd = $this->postJson(route('vaults.store'), ['wrapped_vault_key' => 'wk', 'kind' => 'folder'])->json('id');

        $folderRows = $this->getJson(route('vaults.index', ['kind' => 'folder']))->assertOk()->json();
        $ids = array_column($folderRows, 'vault_id');
        $this->assertContains($fd, $ids);
        $this->assertNotContains($pw, $ids);

        $all = $this->getJson(route('vaults.index'))->assertOk()->json();
        $this->assertContains('kind', array_keys($all[0]));
    }
}
