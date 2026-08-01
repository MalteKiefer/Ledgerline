<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\ModuleStore;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUserCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        ModuleStore::query()->create(['user_id' => $user->id, 'module' => 'notes', 'ciphertext' => 'sealed', 'version' => 1]);
        $vault = new Vault(['salt' => 's', 'kdf_ops' => 3, 'kdf_mem' => 67108864, 'wrapped_vault_key' => 'w', 'wrap_nonce' => 'n']);
        $vault->user_id = $user->id; // non-fillable — stamped explicitly
        $vault->save();

        return $user;
    }

    public function test_force_deletes_the_user_and_all_their_data(): void
    {
        $user = $this->seedUser('gone@example.test');
        $other = $this->seedUser('keep@example.test');

        $this->artisan('user:delete', ['email' => 'gone@example.test', '--force' => true])->assertSuccessful();

        $this->assertNull(User::find($user->id));
        $this->assertNull(ModuleStore::query()->where('user_id', $user->id)->first());
        $this->assertNull(Vault::query()->where('user_id', $user->id)->first());

        // The other user is untouched.
        $this->assertNotNull(User::find($other->id));
        $this->assertNotNull(ModuleStore::query()->where('user_id', $other->id)->first());
    }

    public function test_prompt_can_be_declined(): void
    {
        $user = $this->seedUser('safe@example.test');

        $this->artisan('user:delete', ['email' => 'safe@example.test'])
            ->expectsConfirmation('Delete this user and all their data?', 'no')
            ->assertSuccessful();

        $this->assertNotNull(User::find($user->id));
    }

    public function test_unknown_email_fails(): void
    {
        $this->artisan('user:delete', ['email' => 'nobody@example.test', '--force' => true])->assertFailed();
    }
}
