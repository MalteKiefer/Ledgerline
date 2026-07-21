<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\PurgeUserAccount;
use App\Models\ModuleStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function ownedStore(User $user, string $ciphertext): ModuleStore
    {
        // Store v3 splits the workspace into one sealed row per module. The notes
        // module row is keyed by (user_id, module) — exported as ciphertext by
        // StoreData, purged on erase.
        return ModuleStore::query()->create([
            'user_id' => $user->id,
            'module' => 'notes',
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
        $this->assertNull(ModuleStore::query()->where('user_id', $user->id)->first());
        $this->assertNotNull(ModuleStore::query()->where('user_id', $otherUser->id)->first());
        $this->assertNotNull(User::find($otherUser->id));
    }
}
