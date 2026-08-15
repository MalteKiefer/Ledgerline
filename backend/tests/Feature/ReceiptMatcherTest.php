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

    public function test_a_net_30_invoice_still_matches_within_the_30_day_window(): void
    {
        // Real case: a netcup invoice dated the 22nd was actually debited on the
        // 10th of the following month — 18 days later. A too-tight window (the
        // original 14 days) missed this real pair; 30 days must catch it.
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        $receipt = $this->receipt(['amount' => 35.37, 'date' => '2026-06-22']);
        $tx = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-10', 'amount' => -35.37,
            'counterparty' => 'netcup GmbH', 'sig' => 'sig-net30',
        ]);

        $result = app(ReceiptMatcher::class)->detect();

        $this->assertCount(1, $result['groups']);
        $this->assertSame($tx->id, $result['groups'][0]['transaction_id']);
        $this->assertSame([$receipt->id], $result['groups'][0]['receipt_ids']);
    }

    public function test_a_receipt_well_outside_the_window_is_not_matched(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        $this->receipt(['amount' => 12.00, 'date' => '2026-01-01']);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-04-01', 'amount' => -12.00,
            'counterparty' => 'Unrelated by now', 'sig' => 'sig-far',
        ]);

        $result = app(ReceiptMatcher::class)->detect();

        $this->assertSame([], $result['groups']);
    }

    public function test_a_receipt_settled_by_two_separate_charges_is_found_by_detect_split_payments(): void
    {
        // The real case that motivated this: an INWX invoice (42.07, eight line
        // items — seven domain transfers + one registration) was billed as one
        // document but debited as two separate "WWW.INWX.DE" charges a week apart:
        // 32.55 (the seven transfers) on the 18th, 9.52 (the registration) on the
        // 25th. No single transaction matches 42.07 at all.
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        $receipt = $this->receipt(['amount' => 42.07, 'date' => '2026-07-31']);
        $txA = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-18', 'amount' => -32.55,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-inwx-a',
        ]);
        $txB = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-25', 'amount' => -9.52,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-inwx-b',
        ]);

        $result = app(ReceiptMatcher::class)->detectSplitPayments();

        $this->assertCount(1, $result);
        $this->assertSame($receipt->id, $result[0]['receipt_id']);
        $this->assertSame('sum', $result[0]['reason']);
        $this->assertEqualsWithDelta(42.07, $result[0]['total'], 0.001);
        $ids = $result[0]['transaction_ids'];
        sort($ids);
        $expected = [$txA->id, $txB->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_a_split_payment_never_combines_transactions_from_different_counterparties(): void
    {
        // A real false positive caught against production data BEFORE it could be
        // applied: four completely unrelated charges from four different companies
        // (two different Amazon orders, INWX, fonial) summed to the exact cent of
        // an unrelated PayPal receipt (16.98 + 9.52 + 19.99 + 17.49 = 63.98) — and
        // in doing so would have stolen the real INWX transaction a genuine split
        // match needed. A combination may only ever come from ONE counterparty.
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        $this->receipt(['amount' => 63.98, 'date' => '2026-07-16']);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-18', 'amount' => -16.98,
            'counterparty' => 'AMAZON* AV7AS9875', 'sig' => 'sig-coincidence-a',
        ]);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-25', 'amount' => -9.52,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-coincidence-b',
        ]);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-06-30', 'amount' => -19.99,
            'counterparty' => 'AMAZON* GC6U83NW5', 'sig' => 'sig-coincidence-c',
        ]);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-08-06', 'amount' => -17.49,
            'counterparty' => 'fonial GmbH', 'sig' => 'sig-coincidence-d',
        ]);

        $result = app(ReceiptMatcher::class)->detectSplitPayments();

        $this->assertSame([], $result);
    }

    public function test_a_transaction_already_claimed_by_another_receipt_is_not_reused_in_a_split(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        $claimedTx = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-18', 'amount' => -32.55,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-claimed',
        ]);
        // Already linked to a DIFFERENT, unrelated receipt — off the table for anyone else.
        $this->receipt(['amount' => 32.55, 'date' => '2026-07-18', 'bank_transaction_id' => $claimedTx->id]);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-25', 'amount' => -9.52,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-unclaimed',
        ]);
        $this->receipt(['amount' => 42.07, 'date' => '2026-07-31']);

        $result = app(ReceiptMatcher::class)->detectSplitPayments();

        $this->assertSame([], $result);
    }

    public function test_a_receipt_already_split_linked_is_never_regrouped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $pm = $this->account();

        $txA = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-18', 'amount' => -32.55,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-linked-a',
        ]);
        $txB = BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-25', 'amount' => -9.52,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-linked-b',
        ]);
        $this->receipt(['amount' => 42.07, 'date' => '2026-07-31', 'linked_transaction_ids' => [$txA->id, $txB->id]]);

        $result = app(ReceiptMatcher::class)->detectSplitPayments();

        $this->assertSame([], $result);
    }

    public function test_split_payment_matches_are_owner_scoped(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a);
        $pm = $this->account();
        $this->receipt(['amount' => 42.07, 'date' => '2026-07-31']);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-18', 'amount' => -32.55,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-owner-scope-a',
        ]);
        BankTransaction::create([
            'payment_method_id' => $pm->id, 'date' => '2026-07-25', 'amount' => -9.52,
            'counterparty' => 'WWW.INWX.DE', 'sig' => 'sig-owner-scope-b',
        ]);

        $this->actingAs($b);
        $result = app(ReceiptMatcher::class)->detectSplitPayments();
        $this->assertSame([], $result);
    }
}
