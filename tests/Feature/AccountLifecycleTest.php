<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\PurgeUserAccount;
use App\Models\User;
use App\Models\VaultStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function ownedStore(User $user, string $ciphertext): VaultStore
    {
        // The user's whole sealed workspace (notes live here now) is one row in
        // vault_store, keyed by user_id — exported as ciphertext, purged on erase.
        return VaultStore::query()->create([
            'user_id' => $user->id,
            'ciphertext' => $ciphertext,
            'version' => 1,
        ]);
    }

    public function test_export_streams_a_zip_of_all_modules(): void
    {
        $user = User::factory()->create();
        $this->ownedStore($user, 'mine-sealed-blob');

        $res = $this->actingAs($user)->get(route('account.export'));
        $res->assertOk();
        $this->assertSame('application/zip', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('.zip', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_wrong_confirmation_does_not_delete(): void
    {
        $user = User::factory()->create(['email' => 'gdpr@example.com']);

        $this->actingAs($user)->delete(route('account.destroy'), ['confirmation' => 'nope'])
            ->assertSessionHasErrors('confirmation');
        $this->assertNotNull(User::find($user->id));
    }

    public function test_purge_action_erases_the_user_and_their_data(): void
    {
        $user = User::factory()->create(['email' => 'gdpr@example.com']);
        $this->ownedStore($user, 'secret-sealed-blob');
        $otherUser = User::factory()->create();
        $this->ownedStore($otherUser, 'keep-sealed-blob');

        app(PurgeUserAccount::class)->handle($user);

        $this->assertNull(User::find($user->id));
        $this->assertNull(VaultStore::query()->find($user->id));
        $this->assertNotNull(VaultStore::query()->find($otherUser->id));
        $this->assertNotNull(User::find($otherUser->id));
    }
}
