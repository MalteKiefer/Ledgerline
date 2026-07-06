<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_requires_auth_and_renders(): void
    {
        $this->get(route('mail.signatures'))->assertRedirect(route('login'));
        $this->signIn();
        $this->get(route('mail.signatures'))->assertOk()->assertSee('mailSignatures', false);
    }

    public function test_crud_and_single_default(): void
    {
        $this->signIn();

        // First signature is forced default.
        $this->postJson(route('mail.signatures.store'), ['name' => 'Work', 'html' => '<p>Regards</p>'])
            ->assertCreated()->assertJson(['name' => 'Work', 'isDefault' => true]);
        $first = MailSignature::firstOrFail();

        // Second, marked default → the first loses default.
        $this->postJson(route('mail.signatures.store'), ['name' => 'Personal', 'html' => '<p>Cheers</p>', 'is_default' => true])
            ->assertCreated()->assertJson(['isDefault' => true]);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, MailSignature::where('is_default', true)->count());

        // Update content.
        $this->putJson(route('mail.signatures.update', $first), ['name' => 'Work 2', 'html' => '<p>BR</p>'])
            ->assertOk()->assertJson(['name' => 'Work 2']);
        $this->assertStringContainsString('BR', $first->fresh()->html);

        // Delete.
        $this->deleteJson(route('mail.signatures.destroy', $first))->assertOk();
        $this->assertNull(MailSignature::find($first->id));
    }

    public function test_signatures_are_owner_scoped(): void
    {
        $other = User::factory()->create();
        $foreign = new MailSignature(['name' => 'Theirs', 'html' => 'x']);
        $foreign->forceFill(['user_id' => $other->id])->save();

        $this->signIn();
        // Global scope hides it → 404 on binding.
        $this->putJson(route('mail.signatures.update', $foreign->id), ['name' => 'Hijack'])->assertNotFound();
        $this->getJson(route('mail.signatures.data'))->assertOk()->assertJsonCount(0, 'signatures');
    }

    public function test_deleting_a_signature_nulls_the_identity_link(): void
    {
        $user = $this->signIn();
        $account = MailAccount::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'secret',
        ]);
        $sig = MailSignature::create(['name' => 'S', 'html' => 'x']);
        $identity = $account->identities()->create(['from_email' => 'me@example.com', 'signature_id' => $sig->id, 'is_default' => true]);

        $this->deleteJson(route('mail.signatures.destroy', $sig))->assertOk();
        $this->assertNull($identity->fresh()->signature_id);
    }

    public function test_identity_can_link_a_signature(): void
    {
        $user = $this->signIn();
        $account = MailAccount::withoutGlobalScopes()->create([
            'user_id' => $user->id, 'name' => 'Work', 'host' => 'imap.example.com', 'port' => 993,
            'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'secret',
        ]);
        $account->identities()->create(['from_email' => 'me@example.com', 'is_default' => true]);
        $sig = MailSignature::create(['name' => 'S', 'html' => 'x']);

        $this->postJson(route('mail.identities.store', $account), [
            'from_email' => 'sales@example.com', 'signature_id' => $sig->id,
        ])->assertCreated()->assertJson(['signatureId' => $sig->id]);

        // The identities page lists it with the linked signature.
        $this->getJson(route('mail.identities.all'))
            ->assertOk()
            ->assertJsonPath('signatures.0.name', 'S');
    }
}
