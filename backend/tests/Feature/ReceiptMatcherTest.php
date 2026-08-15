<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\FinanceReceipt;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\ReceiptMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the receipt<->transaction matcher against a real scenario found in the
 * owner's actual Amazon receipts (August 2026): a single card charge can be settled
 * by SEVERAL shipment invoices sharing Amazon's printed "Zahlungsreferenznummer",
 * and the same document sometimes gets uploaded twice by accident.
 */
class ReceiptMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function receipt(array $attrs): FinanceReceipt
    {
        $r = new FinanceReceipt;
        $r->forceFill(array_merge([
            'user_id' => auth()->id(),
            'blob_path' => 'invoices/'.uniqid('', true),
            'name' => 'receipt.pdf',
            'mime' => 'application/pdf',
            'size' => 1000,
            'kind' => 'receipt',
        ], $attrs));
        $r->save();

        return $r;
    }

    private function account(): PaymentMethod
    {
        return PaymentMethod::create(['type' => 'bank', 'name' => 'Business', 'business' => true]);
    }

    public function test_receipts_sharing_an_order_ref_are_summed_and_duplicates_are_reported(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        // The real case: memoryking invoice (11.99) uploaded twice by accident, plus
        // the Amazon-EU invoice (7.90) also uploaded twice — both share Amazon's
        // Zahlungsreferenznummer. Distinct sum 11.99 + 7.90 = 19.89, matching the
        // card statement line "AMAZON* GK78B2SX5 -19,89" two days later.
        $canonicalA = $this->receipt(['amount' => 11.99, 'date' => '2026-07-15', 'order_ref' => 'REF1', 'doc_number' => 'DE60GTQMP053RU']);
        $this->receipt(['amount' => 11.99, 'date' => '2026-07-15', 'order_ref' => 'REF1', 'doc_number' => 'DE60GTQMP053RU']); // dup
        $canonicalB = $this->receipt(['amount' => 7.90, 'date' => '2026-07-15', 'order_ref' => 'REF1', 'doc_number' => 'LU63VXS7WAEUI']);
        $this->receipt(['amount' => 7.90, 'date' => '2026-07-15', 'order_ref' => 'REF1', 'doc_number' => 'LU63VXS7WAEUI']); // dup

        $tx = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-17', 'amount' => -19.89,
            'counterparty' => 'AMAZON* GK78B2SX5', 'sig' => 'sig-order-ref',
        ]);

        $result = app(ReceiptMatcher::class)->detect();

        $this->assertCount(2, $result['duplicates']);
        $this->assertCount(1, $result['groups']);
        $group = $result['groups'][0];
        $this->assertSame('order_ref', $group['reason']);
        $this->assertSame($tx->id, $group['transaction_id']);
        $this->assertEqualsWithDelta(19.89, $group['total'], 0.001);
        sort($group['receipt_ids']);
        $expected = [$canonicalA->id, $canonicalB->id];
        sort($expected);
        $this->assertSame($expected, $group['receipt_ids']);
    }

    public function test_a_single_unambiguous_amount_match_is_reported_as_exact(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        $receipt = $this->receipt(['amount' => 19.99, 'date' => '2026-06-29']);
        $tx = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-06-30', 'amount' => -19.99,
            'counterparty' => 'AMAZON* GC6U83NW5', 'sig' => 'sig-exact',
        ]);

        $result = app(ReceiptMatcher::class)->detect();

        $this->assertCount(1, $result['groups']);
        $this->assertSame('exact', $result['groups'][0]['reason']);
        $this->assertSame($tx->id, $result['groups'][0]['transaction_id']);
        $this->assertSame([$receipt->id], $result['groups'][0]['receipt_ids']);
    }

    public function test_two_receipts_without_a_shared_reference_are_found_by_subset_sum(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        // Two different sellers, no order_ref/doc_number on either — only the sum
        // (4.99 + 11.99 = 16.98) identifies the charge. The generic fallback for any
        // merchant that splits a charge without printing a shared reference.
        $a = $this->receipt(['amount' => 4.99, 'date' => '2026-07-17']);
        $b = $this->receipt(['amount' => 11.99, 'date' => '2026-07-17']);
        $tx = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-18', 'amount' => -16.98,
            'counterparty' => 'AMAZON* AV7AS9875', 'sig' => 'sig-sum',
        ]);

        $result = app(ReceiptMatcher::class)->detect();

        $this->assertCount(1, $result['groups']);
        $group = $result['groups'][0];
        $this->assertSame('sum', $group['reason']);
        $this->assertSame($tx->id, $group['transaction_id']);
        sort($group['receipt_ids']);
        $expected = [$a->id, $b->id];
        sort($expected);
        $this->assertSame($expected, $group['receipt_ids']);
    }

    public function test_a_transaction_already_documented_is_never_suggested_again(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        // Embedded receipts already attached directly on the transaction -> documented.
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-05-01', 'amount' => -42.00,
            'counterparty' => 'Already documented', 'sig' => 'sig-doc-1',
            'receipts' => [['id' => 'abc', 'name' => 'existing.pdf']],
        ]);
        $this->receipt(['amount' => 42.00, 'date' => '2026-05-01']);

        $result = app(ReceiptMatcher::class)->detect();

        $this->assertSame([], $result['groups']);
    }

    public function test_a_receipt_already_linked_to_a_transaction_is_never_regrouped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        $existingTx = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-05-01', 'amount' => -30.00,
            'counterparty' => 'Linked target', 'sig' => 'sig-linked-target',
        ]);
        $this->receipt(['amount' => 30.00, 'date' => '2026-05-01', 'bank_transaction_id' => $existingTx->id]);

        $result = app(ReceiptMatcher::class)->detect();

        $this->assertSame([], $result['groups']);
    }

    public function test_matches_are_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a);
        $pm = $this->account();
        $this->receipt(['amount' => 19.99, 'date' => '2026-06-29']);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-06-30', 'amount' => -19.99,
            'counterparty' => 'AMAZON* OWNER-A', 'sig' => 'sig-owner-a',
        ]);

        $this->actingAs($b);
        $result = app(ReceiptMatcher::class)->detect();
        $this->assertSame([], $result['groups']);
        $this->assertSame([], $result['duplicates']);
    }
}
