<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailLabel;
use App\Models\MailRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
