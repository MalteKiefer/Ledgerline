<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\MailLabel;
use App\Models\MailMessage;
use App\Models\MailRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ingest rules decide whether an arriving message is labelled, marked read,
 * trashed or dropped entirely, so a rule that could be created or edited across
 * accounts would silently act on someone else's mail.
 */
class MailRuleTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Newsletters',
            'match' => ['from' => 'news@example.com'],
            'action' => ['mark_read' => true],
        ], $overrides);
    }

    public function test_rules_are_created_listed_updated_and_deleted_for_their_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->postJson(route('mail.rules.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('rule.name', 'Newsletters')
            ->assertJsonPath('rule.enabled', true)
            ->json('rule.id');

        $this->getJson(route('mail.rules.index'))->assertOk()->assertJsonCount(1, 'rules');

        $this->putJson(route('mail.rules.update', $id), $this->payload(['name' => 'Renamed', 'enabled' => false]))
            ->assertOk()
            ->assertJsonPath('rule.name', 'Renamed')
            ->assertJsonPath('rule.enabled', false);

        $this->deleteJson(route('mail.rules.destroy', $id))->assertNoContent();
        $this->assertSame(0, MailRule::query()->count());
    }

    public function test_another_users_rule_is_not_readable_editable_or_deletable(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $id = $this->postJson(route('mail.rules.store'), $this->payload())->assertCreated()->json('rule.id');

        $this->actingAs(User::factory()->create());
        $this->getJson(route('mail.rules.index'))->assertOk()->assertJsonCount(0, 'rules');
        $this->putJson(route('mail.rules.update', $id), $this->payload(['name' => 'Hijacked']))->assertNotFound();
        $this->deleteJson(route('mail.rules.destroy', $id))->assertNotFound();

        $this->assertSame('Newsletters', (string) MailRule::query()->withoutGlobalScopes()->findOrFail($id)->name);
    }

    public function test_a_rule_cannot_reference_another_users_label(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger);
        $foreignLabel = MailLabel::create(['name' => 'Theirs', 'color' => '#ff0000']);

        $user = User::factory()->create();
        $this->actingAs($user);
        // The JSON contract is the API twin; the web route answers a validation
        // failure with a redirect.
        $this->postJson(route('api.mail.rules.store'), $this->payload(['action' => ['add_label' => $foreignLabel->id]]))
            ->assertStatus(422);
        $this->assertSame(0, MailRule::query()->count());
    }

    public function test_rules_require_authentication(): void
    {
        $this->getJson(route('api.mail.rules.index'))->assertUnauthorized();
    }

    public function test_every_condition_and_action_the_form_offers_round_trips(): void
    {
        // The form only offered from/subject and three actions, so the recipient,
        // folder and attachment conditions and the file-as-receipt action were
        // reachable only by editing the database — including the automatic
        // mail-to-receipt filing, which was the point of building it.
        $user = User::factory()->create();
        $this->actingAs($user);

        $rule = $this->postJson(route('mail.rules.store'), $this->payload([
            'name' => 'Invoices',
            'match' => ['from' => 'billing@', 'to' => 'me@', 'subject' => 'invoice', 'folder' => 'INBOX', 'has_attachment' => true],
            'action' => ['mark_read' => true, 'file_receipt' => true],
        ]))->assertCreated()->json('rule');

        $this->assertSame('billing@', $rule['match']['from']);
        $this->assertSame('me@', $rule['match']['to']);
        $this->assertSame('invoice', $rule['match']['subject']);
        $this->assertSame('INBOX', $rule['match']['folder']);
        $this->assertTrue($rule['match']['has_attachment']);
        $this->assertTrue($rule['action']['file_receipt']);
    }

    public function test_applying_rules_to_existing_mail_marks_labels_and_trashes_but_never_skips(): void
    {
        // Rules only ran at ingest, so a rule written today did nothing about the
        // mail already archived - which is usually why it was written.
        $user = User::factory()->create();
        $account = MailAccount::factory()->create(['user_id' => $user->id]);
        $label = MailLabel::query()->forceCreate(['user_id' => $user->id, 'name' => 'Bills', 'color' => '#f00']);
        $this->actingAs($user);

        $seed = function (string $subject, string $from) use ($user, $account): MailMessage {
            $m = new MailMessage;
            $m->forceFill([
                'id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $account->id,
                'folder' => 'INBOX', 'subject' => $subject, 'from_email' => $from, 'from_name' => '',
                'to_json' => [], 'seen' => false, 'size' => 10, 'content_hash' => (string) Str::uuid(),
                'date' => now(), 'created_at' => now(),
            ])->save();

            return $m;
        };
        $hit = $seed('Invoice 5', 'billing@netcup.de');
        $miss = $seed('Hello', 'friend@example.org');

        $this->postJson(route('mail.rules.store'), $this->payload([
            'name' => 'Bills', 'match' => ['from' => 'netcup'],
            'action' => ['mark_read' => true, 'add_label' => $label->id],
        ]))->assertCreated();
        // skip must be ignored: it means "do not archive", and this is archived.
        $this->postJson(route('mail.rules.store'), $this->payload([
            'name' => 'Would skip', 'match' => ['from' => 'netcup'], 'action' => ['skip' => true],
        ]))->assertCreated();

        $this->postJson(route('mail.rules.apply-all'))->assertOk()->assertJson(['dispatched' => true]);

        $this->assertTrue((bool) $hit->fresh()?->seen, 'the matching message was marked read');
        $this->assertSame([$label->id], $hit->fresh()?->labels->pluck('id')->all());
        $this->assertNull($hit->fresh()?->trashed_at, 'skip never deletes what is already archived');
        $this->assertFalse((bool) $miss->fresh()?->seen, 'a non-matching message is untouched');
        $this->assertCount(0, $miss->fresh()?->labels ?? []);
    }

    public function test_applying_a_foreign_rule_is_a_404(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $id = $this->postJson(route('mail.rules.store'), $this->payload())->assertCreated()->json('rule.id');

        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create())
            ->postJson(route('mail.rules.apply', ['rule' => $id]))
            ->assertNotFound();
    }
}
