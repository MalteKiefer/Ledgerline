<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The payment-method `holder` and `note` fields were restored in v1.518.0.
 * Prove they validate, persist, round-trip through the model, and (post
 * encryption-removal) live plaintext at rest — none of which FinanceRelationalTest
 * asserts.
 */
class PaymentMethodFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_holder_and_note_round_trip_and_are_plaintext_at_rest(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('finance.payment-methods.store'), [
            'type' => 'bank',
            'name' => 'Main',
            'holder' => 'Malte Mustermann',
            'note' => 'primary business account',
            'iban' => 'DE89370400440532013000',
        ])->assertCreated()
            ->assertJsonPath('payment_method.holder', 'Malte Mustermann')
            ->assertJsonPath('payment_method.note', 'primary business account')
            ->json('payment_method.id');

        $pm = PaymentMethod::findOrFail($id);
        $this->assertSame('Malte Mustermann', $pm->holder);
        $this->assertSame('primary business account', $pm->note);

        $raw = DB::table('payment_methods')->where('id', $id)->first();
        $this->assertNotNull($raw);
        $this->assertStringContainsString('Malte Mustermann', (string) $raw->holder);
        $this->assertStringContainsString('primary business account', (string) $raw->note);
    }

    public function test_holder_and_note_are_updatable_and_clearable(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson(route('finance.payment-methods.store'), [
            'type' => 'bank', 'name' => 'Main', 'holder' => 'Old Holder', 'note' => 'old',
        ])->json('payment_method.id');

        $this->putJson(route('finance.payment-methods.update', $id), [
            'holder' => 'New Holder', 'note' => '', 'version' => 0,
        ])->assertOk()->assertJsonPath('payment_method.holder', 'New Holder');

        $pm = PaymentMethod::findOrFail($id);
        $this->assertSame('New Holder', $pm->holder);
        $this->assertTrue($pm->note === null || $pm->note === '');
    }

    public function test_over_long_holder_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('finance.payment-methods.store'), [
            'type' => 'bank', 'name' => 'Main', 'holder' => str_repeat('x', 201),
        ])->assertInvalid(['holder']);
    }
}
