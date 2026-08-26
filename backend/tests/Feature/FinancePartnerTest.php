<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinancePartner;
use App\Models\FinancePartnerNote;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_gets_a_number_from_the_configured_template(): void
    {
        $user = $this->signIn();
        UserSetting::for($user->id)->forceFill(['customer_number_format' => 'K-NNNN', 'customer_next_number' => 5])->save();

        $this->postJson(route('api.finance.partners.store'), ['name' => 'IntellyTec GmbH', 'kind' => 'customer'])
            ->assertCreated()
            ->assertJsonPath('partner.customer_number', 'K-0005');

        $this->postJson(route('api.finance.partners.store'), ['name' => 'Zweiter', 'kind' => 'customer'])
            ->assertCreated()
            ->assertJsonPath('partner.customer_number', 'K-0006');
    }

    public function test_a_supplier_gets_no_customer_number(): void
    {
        // Ours would mean nothing to them.
        $this->signIn();
        $this->postJson(route('api.finance.partners.store'), ['name' => 'Lieferant', 'kind' => 'supplier'])
            ->assertCreated()
            ->assertJsonPath('partner.customer_number', null);
    }

    public function test_a_supplied_number_is_kept_as_given(): void
    {
        // Migrating from another system: the numbers already exist and must not
        // be renumbered.
        $this->signIn();
        $this->postJson(route('api.finance.partners.store'), ['name' => 'Alt', 'kind' => 'customer', 'customer_number' => '10042'])
            ->assertCreated()
            ->assertJsonPath('partner.customer_number', '10042');
    }

    public function test_a_binned_partner_keeps_its_number_out_of_circulation(): void
    {
        // Two parties sharing an identifier is worse than a gap in the sequence.
        $this->signIn();
        $first = $this->postJson(route('api.finance.partners.store'), ['name' => 'Erster', 'kind' => 'customer'])->json('partner');
        $this->deleteJson(route('api.finance.partners.destroy', $first['id']))->assertOk();

        $second = $this->postJson(route('api.finance.partners.store'), ['name' => 'Zweiter', 'kind' => 'customer'])->json('partner');
        $this->assertNotSame($first['customer_number'], $second['customer_number']);
    }

    public function test_the_new_customer_fields_round_trip(): void
    {
        $this->signIn();
        $partner = $this->postJson(route('api.finance.partners.store'), [
            'name' => 'IntellyTec GmbH',
            'kind' => 'both',
            'address' => "Grünenborn 1\n53797 Lohmar",
            'delivery_address' => "Lager Ost\n53797 Lohmar",
            'payment_terms_days' => 30,
            'discount_percent' => 5,
        ])->assertCreated()->json('partner');

        $this->assertSame('both', $partner['kind']);
        $this->assertSame(30, $partner['payment_terms_days']);
        $this->assertSame('5.00', $partner['discount_percent']);
        $this->assertStringContainsString('Lager Ost', (string) $partner['delivery_address']);

        // A partial update leaves what it does not send alone.
        $this->putJson(route('api.finance.partners.update', $partner['id']), ['payment_terms_days' => 14])->assertOk();
        $fresh = FinancePartner::query()->findOrFail($partner['id']);
        $this->assertSame(14, $fresh->payment_terms_days);
        $this->assertStringContainsString('Lager Ost', (string) $fresh->delivery_address);
    }

    public function test_an_unknown_partner_kind_is_refused(): void
    {
        $this->signIn();
        $this->postJson(route('api.finance.partners.store'), ['name' => 'X', 'kind' => 'whatever'])->assertStatus(422);
    }

    public function test_archiving_hides_a_partner_without_losing_it(): void
    {
        $this->signIn();
        $partner = $this->postJson(route('api.finance.partners.store'), ['name' => 'Alter Kunde', 'kind' => 'customer'])->json('partner');

        $this->postJson(route('api.finance.partners.archive', $partner['id']), ['archived' => true])
            ->assertOk()
            ->assertJsonPath('partner.name', 'Alter Kunde');
        $this->assertTrue(FinancePartner::query()->findOrFail($partner['id'])->isArchived());

        // Still there, still listed — its documents keep pointing at it.
        $this->getJson(route('api.finance.data'))->assertOk()->assertJsonPath('partners.0.name', 'Alter Kunde');

        $this->postJson(route('api.finance.partners.archive', $partner['id']), ['archived' => false])->assertOk();
        $this->assertFalse(FinancePartner::query()->findOrFail($partner['id'])->isArchived());
    }

    public function test_the_contact_log_sorts_by_when_it_happened_not_when_it_was_typed(): void
    {
        $this->signIn();
        $partner = $this->postJson(route('api.finance.partners.store'), ['name' => 'Kunde', 'kind' => 'customer'])->json('partner');

        // Logged in the other order: the meeting is typed up after the later call.
        $this->postJson(route('api.finance.partners.notes.store', $partner['id']), [
            'kind' => 'call', 'body' => 'Rückruf vereinbart', 'occurred_at' => '2026-08-20 10:00',
        ])->assertCreated();
        $this->postJson(route('api.finance.partners.notes.store', $partner['id']), [
            'kind' => 'meeting', 'body' => 'Vor Ort', 'occurred_at' => '2026-08-10 09:00',
        ])->assertCreated();

        $notes = $this->getJson(route('api.finance.partners.notes', $partner['id']))->assertOk()->json('notes');
        $this->assertSame('Rückruf vereinbart', $notes[0]['body']);
        $this->assertSame('Vor Ort', $notes[1]['body']);
    }

    public function test_a_note_cannot_be_deleted_through_another_partner(): void
    {
        $this->signIn();
        $a = $this->postJson(route('api.finance.partners.store'), ['name' => 'A', 'kind' => 'customer'])->json('partner');
        $b = $this->postJson(route('api.finance.partners.store'), ['name' => 'B', 'kind' => 'customer'])->json('partner');
        $note = $this->postJson(route('api.finance.partners.notes.store', $a['id']), ['body' => 'Vertraulich'])->json('note');

        // Scoped through the partner as well as the owner: a note id alone must
        // not reach into another partner's log.
        $this->deleteJson(route('api.finance.partners.notes.destroy', ['partner' => $b['id'], 'note' => $note['id']]))->assertNotFound();
        $this->assertSame(1, FinancePartnerNote::query()->count());

        $this->deleteJson(route('api.finance.partners.notes.destroy', ['partner' => $a['id'], 'note' => $note['id']]))->assertOk();
        $this->assertSame(0, FinancePartnerNote::query()->count());
    }

    public function test_another_owner_reaches_neither_the_partner_nor_its_log(): void
    {
        $this->signIn();
        $partner = $this->postJson(route('api.finance.partners.store'), ['name' => 'Mein Kunde', 'kind' => 'customer'])->json('partner');
        $this->postJson(route('api.finance.partners.notes.store', $partner['id']), ['body' => 'Intern'])->assertCreated();

        app('auth')->forgetGuards();
        $this->signIn(User::factory()->create());

        $this->getJson(route('api.finance.partners.notes', $partner['id']))->assertNotFound();
        $this->postJson(route('api.finance.partners.notes.store', $partner['id']), ['body' => 'x'])->assertNotFound();
        $this->postJson(route('api.finance.partners.archive', $partner['id']), ['archived' => true])->assertNotFound();
    }
}
