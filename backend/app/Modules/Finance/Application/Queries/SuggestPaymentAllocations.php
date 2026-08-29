<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries;

use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Payments\PaymentId;
use App\Modules\Finance\Application\DTOs\Payments\PaymentSuggestionCandidate;
use App\Modules\Finance\Application\DTOs\Payments\PaymentSuggestionResult;
use App\Modules\Finance\Application\Ports\PaymentRepository;
use DateTimeImmutable;

final readonly class SuggestPaymentAllocations
{
    public function __construct(private PaymentRepository $payments) {}

    public function forPayment(PaymentId $paymentId): PaymentSuggestionResult
    {
        $context = $this->payments->suggestionContext($paymentId);
        $payment = $context['payment'];
        $reference = $this->normalize($payment->reference);
        $counterparty = $this->normalize($payment->counterparty);
        $scored = [];

        foreach ($context['invoices'] as $invoice) {
            if ($invoice['currency'] !== $payment->currency
                || ($invoice['open_minor'] <=> 0) !== ($payment->unappliedMinor <=> 0)) {
                continue;
            }
            $number = $this->normalize($invoice['number']);
            $customer = $this->normalize($invoice['customer']);
            $exactReference = $reference !== '' && hash_equals($number, $reference);
            $referenceContains = ! $exactReference
                && $reference !== ''
                && $number !== ''
                && str_contains($reference, $number);
            $exactAmount = $invoice['open_minor'] === $payment->unappliedMinor;
            $customerMatch = $counterparty !== '' && hash_equals($customer, $counterparty);
            $days = (int) $invoice['issue_date']->diff($payment->receivedAt)->format('%r%a');
            $dateMatch = $days >= 0 && $days <= 120;
            $score = ($exactReference ? 10_000 : ($referenceContains ? 7_000 : 0))
                + ($exactAmount ? 1_000 : 0)
                + ($customerMatch ? 100 : 0)
                + ($dateMatch ? 25 : 0);
            if ($score === 0) {
                continue;
            }
            $scored[] = [
                ...$invoice,
                'score' => $score,
                'exact_reference' => $exactReference,
                'reference_contains' => $referenceContains,
                'exact_amount' => $exactAmount,
                'customer_match' => $customerMatch,
                'date_match' => $dateMatch,
            ];
        }
        usort($scored, static fn (array $left, array $right): int => [
            -$left['score'], $left['number'], $left['invoice_id'],
        ] <=> [
            -$right['score'], $right['number'], $right['invoice_id'],
        ]);
        if ($scored === []) {
            return new PaymentSuggestionResult('none', []);
        }
        $topScore = $scored[0]['score'];
        $uniqueTop = count(array_filter(
            $scored,
            static fn (array $candidate): bool => $candidate['score'] === $topScore,
        )) === 1;
        $candidates = array_map(
            fn (array $candidate): PaymentSuggestionCandidate => new PaymentSuggestionCandidate(
                new InvoiceId($candidate['invoice_id']),
                $candidate['number'],
                $candidate['open_minor'],
                $candidate['currency'],
                $candidate['score'],
                $this->reason($candidate, $uniqueTop && $candidate['score'] === $topScore),
            ),
            $scored,
        );

        return new PaymentSuggestionResult($uniqueTop ? 'suggested' : 'ambiguous', $candidates);
    }

    private function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value))) ?? '';
    }

    /** @param array<string, int|string|bool|DateTimeImmutable> $candidate */
    private function reason(array $candidate, bool $uniqueTop): string
    {
        if ($candidate['exact_reference'] === true && $candidate['exact_amount'] === true) {
            return $uniqueTop ? 'unique_reference_and_amount' : 'reference_and_amount';
        }
        if ($candidate['exact_reference'] === true) {
            return $uniqueTop ? 'unique_reference' : 'reference_match';
        }
        if ($candidate['reference_contains'] === true) {
            return 'reference_match';
        }
        if ($candidate['exact_amount'] === true) {
            return 'exact_currency_and_remaining';
        }
        if ($candidate['customer_match'] === true && $candidate['date_match'] === true) {
            return 'customer_and_date';
        }

        return $candidate['customer_match'] === true ? 'customer_match' : 'date_proximity';
    }
}
