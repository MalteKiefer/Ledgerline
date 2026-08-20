<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Signatures are attached per account and one of them is the default that
 * compose picks up, so both the ownership of the account and the "exactly one
 * default" invariant have to hold.
 */
class MailSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user): MailAccount
    {
        return MailAccount::factory()->create(['user_id' => $user->id]);
    }

    public function test_signature_is_created_assigned_and_flagged_default(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->actingAs($user);

        $id = $this->postJson(route('api.mail.signatures.store'), [
            'name' => 'Work',
            'html' => '<p>Regards</p>',
            'account_ids' => [$account->id],
            'default_account_ids' => [$account->id],
        ])->assertCreated()->json('signature.id');

        $this->getJson(route('api.mail.signatures.index'))
            ->assertOk()
            ->assertJsonPath('signatures.0.name', 'Work')
            ->assertJsonPath('signatures.0.default_account_ids', [$account->id]);

        $this->assertSame(1, DB::table('mail_account_signatures')
            ->where(['mail_account_id' => $account->id, 'is_default' => true])->count());

        $this->deleteJson(route('api.mail.signatures.destroy', $id))->assertNoContent();
    }

    public function test_only_one_signature_per_account_stays_the_default(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $this->actingAs($user);

        foreach (['First', 'Second'] as $name) {
            $this->postJson(route('api.mail.signatures.store'), [
                'name' => $name, 'html' => "<p>{$name}</p>",
                'account_ids' => [$account->id], 'default_account_ids' => [$account->id],
            ])->assertCreated();
        }

        $defaults = DB::table('mail_account_signatures')
            ->where(['mail_account_id' => $account->id, 'is_default' => true])->count();
        $this->assertSame(1, $defaults, 'a second default must replace the first, not join it');
    }

    public function test_html_is_sanitised_and_scripts_never_survive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $html = (string) $this->postJson(route('api.mail.signatures.store'), [
            'name' => 'Nasty',
            'html' => '<p onclick="steal()">Hi<script>alert(1)</script><img src="x" onerror="alert(2)"></p>',
        ])->assertCreated()->json('signature.html');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_another_users_signature_and_account_are_out_of_reach(): void
    {
        // Seed the other account's rows directly: switching actingAs mid-test
        // does not re-authenticate cleanly against the sanctum-guarded routes.
        $owner = User::factory()->create();
        $ownerAccount = $this->account($owner);
        $foreign = new MailSignature(['name' => 'Mine', 'html' => '<p>x</p>']);
        $foreign->user_id = $owner->id;
        $foreign->save();

        $this->actingAs(User::factory()->create());
        $this->getJson(route('api.mail.signatures.index'))->assertOk()->assertJsonCount(0, 'signatures');
        $this->putJson(route('api.mail.signatures.update', $foreign->id), ['name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson(route('api.mail.signatures.destroy', $foreign->id))->assertNotFound();

        // Nor may they bind a signature of their own to someone else's account.
        $this->postJson(route('api.mail.signatures.store'), [
            'name' => 'Theirs', 'html' => '<p>x</p>', 'account_ids' => [$ownerAccount->id],
        ])->assertStatus(422);

        $this->assertSame('Mine', (string) MailSignature::query()->withoutGlobalScopes()->findOrFail($foreign->id)->name);
    }
}
