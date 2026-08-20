<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileEntry;
use App\Models\MailAccount;
use App\Models\MailDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Composer autosave. The send path resolves every reference owner-scoped, so a
 * stray id here leaks nothing — but a draft that quietly holds another
 * account's file or signing key is worth refusing where it is written.
 */
class MailDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_round_trip_for_their_owner(): void
    {
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $id = $this->postJson(route('api.mail.drafts.store'), [
            'mode' => 'compose', 'mail_account_id' => $account->id,
            'to' => ['dest@example.com'], 'subject' => 'Draft', 'text_body' => 'body',
        ])->assertCreated()->json('draft.id');

        $this->getJson(route('api.mail.drafts.index'))->assertOk()->assertJsonCount(1, 'drafts');

        $this->putJson(route('api.mail.drafts.update', $id), ['mode' => 'compose', 'subject' => 'Changed'])
            ->assertOk()->assertJsonPath('draft.subject', 'Changed');

        $this->deleteJson(route('api.mail.drafts.destroy', $id))->assertOk();
        $this->assertSame(0, MailDraft::query()->count());
    }

    public function test_a_draft_cannot_reference_another_accounts_rows(): void
    {
        $stranger = User::factory()->create();
        $strangerAccount = MailAccount::factory()->create(['user_id' => $stranger->id]);
        $strangerFile = FileEntry::forceCreate([
            'user_id' => $stranger->id, 'name' => 'theirs.pdf', 'storage_path' => 'files/theirs.pdf',
            'mime' => 'application/pdf', 'size' => 4, 'sha256' => str_repeat('2', 64), 'version' => 0,
        ]);

        $this->actingAs(User::factory()->create());
        $this->postJson(route('api.mail.drafts.store'), ['mode' => 'compose', 'mail_account_id' => $strangerAccount->id])
            ->assertStatus(422);
        $this->postJson(route('api.mail.drafts.store'), ['mode' => 'compose', 'file_ids' => [$strangerFile->id]])
            ->assertStatus(422);

        $this->assertSame(0, MailDraft::query()->withoutGlobalScopes()->count());
    }

    public function test_another_users_draft_is_invisible_and_untouchable(): void
    {
        $owner = User::factory()->create();
        $draft = new MailDraft(['mode' => 'compose', 'subject' => 'Private']);
        $draft->user_id = $owner->id;
        $draft->save();

        $this->actingAs(User::factory()->create());
        $this->getJson(route('api.mail.drafts.index'))->assertOk()->assertJsonCount(0, 'drafts');
        $this->putJson(route('api.mail.drafts.update', $draft->id), ['mode' => 'compose', 'subject' => 'Hijacked'])
            ->assertNotFound();
        $this->deleteJson(route('api.mail.drafts.destroy', $draft->id))->assertNotFound();

        $this->assertSame('Private', (string) MailDraft::query()->withoutGlobalScopes()->findOrFail($draft->id)->subject);
    }
}
