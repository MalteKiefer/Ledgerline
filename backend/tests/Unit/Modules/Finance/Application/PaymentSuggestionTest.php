<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Application;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Payments\AllocatePaymentData;
use App\Modules\Finance\Application\DTOs\Payments\AllocationId;
use App\Modules\Finance\Application\DTOs\Payments\AllocationResult;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\PaymentView;
use App\Modules\Finance\Application\DTOs\Payments\RecordPaymentData;
use App\Modules\Finance\Application\Ports\PaymentRepository;
use App\Modules\Finance\Application\Queries\SuggestPaymentAllocations;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class PaymentSuggestionTest extends TestCase
{
    public function test_exact_normalized_reference_and_amount_is_the_unique_best_suggestion(): void
    {
        $query = new SuggestPaymentAllocations(new SuggestionPaymentRepository(
            $this->payment('RE 2026 0042', 'Customer GmbH'),
            [
                $this->invoice(42, 'RE-2026-0042', 11_900, 'EUR', 'Customer GmbH'),
                $this->invoice(43, 'RE-2026-0043', 11_900, 'EUR', 'Other GmbH'),
                $this->invoice(44, 'RE-2026-0044', 11_900, 'USD', 'Customer GmbH'),
            ],
        ));

        $result = $query->forPayment(new PaymentId(7));

        $this->assertSame('suggested', $result->status);
        $this->assertTrue($result->requiresConfirmation);
        $this->assertSame(42, $result->candidates[0]->invoiceId->value);
        $this->assertSame('unique_reference_and_amount', $result->candidates[0]->reason);
        $this->assertSame('EUR', $result->candidates[0]->currency);
        $this->assertSame(11_900, $result->candidates[0]->openMinor);
    }

    public function test_equal_amount_candidates_are_ambiguous_and_ordered_by_number_then_id(): void
    {
        $query = new SuggestPaymentAllocations(new SuggestionPaymentRepository(
            $this->payment(null, null),
            [
                $this->invoice(43, 'RE-2026-0043', 11_900, 'EUR', 'Other GmbH'),
                $this->invoice(42, 'RE-2026-0042', 11_900, 'EUR', 'Customer GmbH'),
            ],
        ));

        $result = $query->forPayment(new PaymentId(7));

        $this->assertSame('ambiguous', $result->status);
        $this->assertSame(
            [42, 43],
            array_map(static fn ($candidate): int => $candidate->invoiceId->value, $result->candidates),
        );
        $this->assertSame(
            ['exact_currency_and_remaining', 'exact_currency_and_remaining'],
            array_map(static fn ($candidate): string => $candidate->reason, $result->candidates),
        );
        $this->assertTrue($result->requiresConfirmation);
    }

    public function test_customer_and_date_evidence_breaks_a_non_amount_tie_without_cross_currency_candidates(): void
    {
        $query = new SuggestPaymentAllocations(new SuggestionPaymentRepository(
            $this->payment(null, 'Customer GmbH'),
            [
                $this->invoice(45, 'RE-2026-0045', 8_000, 'EUR', 'Customer GmbH'),
                $this->invoice(46, 'RE-2026-0046', 9_000, 'EUR', 'Other GmbH'),
                $this->invoice(47, 'RE-2026-0047', 11_900, 'USD', 'Customer GmbH'),
            ],
        ));

        $result = $query->forPayment(new PaymentId(7));

        $this->assertSame('suggested', $result->status);
        $this->assertSame(45, $result->candidates[0]->invoiceId->value);
        $this->assertSame('customer_and_date', $result->candidates[0]->reason);
        $this->assertSame([45, 46], array_map(
            static fn ($candidate): int => $candidate->invoiceId->value,
            $result->candidates,
        ));
    }

    public function test_exact_reference_outranks_combined_partial_reference_amount_customer_and_date_evidence(): void
    {
        $query = new SuggestPaymentAllocations(new SuggestionPaymentRepository(
            $this->payment('RE-2026-0042', 'Customer GmbH'),
            [
                $this->invoice(42, 'RE-2026-0042', 8_000, 'EUR', 'Other GmbH'),
                $this->invoice(48, '2026', 11_900, 'EUR', 'Customer GmbH'),
            ],
        ));

        $result = $query->forPayment(new PaymentId(7));

        $this->assertSame('suggested', $result->status);
        $this->assertSame(42, $result->candidates[0]->invoiceId->value);
        $this->assertSame('unique_reference', $result->candidates[0]->reason);
    }

    /** @return array{invoice_id:int,number:string,currency:string,open_minor:int,issue_date:DateTimeImmutable,customer:string} */
    private function invoice(
        int $id,
        string $number,
        int $openMinor,
        string $currency,
        string $customer,
    ): array {
        return [
            'invoice_id' => $id,
            'number' => $number,
            'currency' => $currency,
            'open_minor' => $openMinor,
            'issue_date' => new DateTimeImmutable('2026-08-01T00:00:00+00:00'),
            'customer' => $customer,
        ];
    }

    private function payment(?string $reference, ?string $counterparty): PaymentView
    {
        return new PaymentView(
            new PaymentId(7),
            '018f4ca3-224d-7d8d-9f00-000000000007',
            11_900,
            0,
            11_900,
            'EUR',
            new DateTimeImmutable('2026-08-29T10:15:00+00:00'),
            $reference,
            $counterparty,
            0,
        );
    }
}

final readonly class SuggestionPaymentRepository implements PaymentRepository
{
    /**
     * @param  list<array{invoice_id:int,number:string,currency:string,open_minor:int,issue_date:DateTimeImmutable,customer:string}>  $invoices
     */
    public function __construct(private PaymentView $payment, private array $invoices) {}

    public function get(PaymentId $id): PaymentView
    {
        return $this->payment;
    }

    public function record(RecordPaymentData $data, IdempotencyKey $key): PaymentView
    {
        throw new LogicException('Suggestion queries must not record payments.');
    }

    public function allocate(AllocatePaymentData $data, IdempotencyKey $key): AllocationResult
    {
        throw new LogicException('Suggestion queries must not allocate payments.');
    }

    public function reverse(
        AllocationId $id,
        IdempotencyKey $key,
        ?int $expectedPaymentVersion = null,
    ): AllocationResult {
        throw new LogicException('Suggestion queries must not reverse allocations.');
    }

    /**
     * @return array{
     *   payment: PaymentView,
     *   invoices: list<array{invoice_id:int,number:string,currency:string,open_minor:int,issue_date:DateTimeImmutable,customer:string}>
     * }
     */
    public function suggestionContext(PaymentId $id): array
    {
        return ['payment' => $this->payment, 'invoices' => $this->invoices];
    }
}
