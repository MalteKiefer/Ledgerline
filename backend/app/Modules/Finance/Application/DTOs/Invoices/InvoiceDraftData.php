<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use App\Modules\Finance\Domain\Shared\Discount;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class InvoiceDraftData
{
    /**
     * @param  array<string, mixed>  $customer
     * @param  list<InvoiceLineData>  $lines
     */
    public function __construct(
        public DateTimeImmutable $issueDate,
        public DateTimeImmutable $dueDate,
        public string $currency,
        public array $customer,
        public array $lines,
        public Discount $discount,
        public ?int $controlNetMinor = null,
        public ?int $controlVatMinor = null,
        public ?int $controlGrossMinor = null,
        public ?int $partnerId = null,
        public ?int $projectId = null,
    ) {
        if ($dueDate < $issueDate) {
            throw new InvalidArgumentException('Invoice due date cannot precede its issue date.');
        }
        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1 || $discount->currency() !== $currency) {
            throw new InvalidArgumentException('Invoice currency must be canonical and match the discount.');
        }
        if (! isset($customer['name']) || ! is_string($customer['name']) || trim($customer['name']) === '') {
            throw new InvalidArgumentException('Invoice customer name must not be empty.');
        }
        $this->assertExactJson($customer);
        if ($lines === []) {
            throw new InvalidArgumentException('Invoices require at least one line.');
        }
        foreach ($lines as $line) {
            if (! $line instanceof InvoiceLineData) {
                throw new InvalidArgumentException('Every invoice line must be InvoiceLineData.');
            }
        }
        foreach ([$partnerId, $projectId] as $referenceId) {
            if ($referenceId !== null && $referenceId < 1) {
                throw new InvalidArgumentException('Invoice reference IDs must be positive.');
            }
        }
    }

    /** @param array<array-key, mixed> $value */
    private function assertExactJson(array $value): void
    {
        foreach ($value as $item) {
            if (is_float($item)
                || (! is_array($item) && ! is_string($item) && ! is_int($item) && ! is_bool($item) && $item !== null)) {
                throw new InvalidArgumentException('Invoice customer data must contain JSON values without floats.');
            }

            if (is_array($item)) {
                $this->assertExactJson($item);
            }
        }
    }
}
