<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Services\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteLineData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteTotalsView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Quotes\QuoteReferenceResolver;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class QuoteDraftFactory
{
    private const int MAX_LINES = 200;

    public function __construct(
        private DocumentCalculator $calculator,
        private QuoteReferenceResolver $references,
        private QuoteSettings $settings,
        private Clock $clock,
    ) {}

    /**
     * @return array{
     *     partner_id: int|null,
     *     payload: array<string, mixed>,
     *     totals: DocumentTotals,
     *     preview: QuoteTotalsView
     * }
     */
    public function build(int $ownerId, QuoteDraftData $data): array
    {
        if ($ownerId < 1) {
            throw new InvalidArgumentException('Quote owner ID must be positive.');
        }
        if (trim($data->title) === '') {
            throw new InvalidArgumentException('Quote title must not be empty.');
        }
        if ($data->partnerId !== null && $data->partnerId < 1) {
            throw new InvalidArgumentException('Quote partner ID must be positive.');
        }
        if ($data->lines === [] || count($data->lines) > self::MAX_LINES) {
            throw new InvalidArgumentException('Quotes require between 1 and 200 lines.');
        }

        $this->references->assertOwnedPartner($data->partnerId);
        $currency = Money::fromMinor(0, $data->currency)->currency();
        $issueDate = $this->issueDate($ownerId, $data->issueDate);
        $validUntil = $this->validUntil($ownerId, $issueDate, $data->validUntil);
        $domainLines = [];
        $payloadLines = [];
        $productIds = [];

        foreach ($data->lines as $line) {
            if (! $line instanceof QuoteLineData) {
                throw new InvalidArgumentException('Every quote line must be QuoteLineData.');
            }

            $parsed = $this->line($line, $currency);
            $domainLines[] = $parsed['domain'];
            $payloadLines[] = $parsed['payload'];

            if ($line->productId !== null) {
                $productIds[] = $line->productId;
            }
        }

        $this->references->assertOwnedProducts(array_values(array_unique($productIds)));
        $discount = $this->discount($data->discountType, $data->discountValue, $currency);
        $totals = $this->calculator->calculate($domainLines, $discount);
        $this->assertControlTotals($data, $totals, $currency);
        $breakdowns = array_map(
            static fn ($breakdown): array => [
                'tax_rate_basis_points' => $breakdown->taxRateBasisPoints,
                'net_minor' => $breakdown->net->minor(),
                'vat_minor' => $breakdown->vat->minor(),
                'gross_minor' => $breakdown->gross->minor(),
            ],
            $totals->taxBreakdowns,
        );
        $totalsPayload = [
            'net_minor' => $totals->net->minor(),
            'vat_minor' => $totals->vat->minor(),
            'gross_minor' => $totals->gross->minor(),
            'discount_minor' => $totals->discount->minor(),
            'currency' => $currency,
            'tax_breakdowns' => $breakdowns,
        ];
        $preview = new QuoteTotalsView(
            $totals->net->minor(),
            $totals->vat->minor(),
            $totals->gross->minor(),
            $totals->discount->minor(),
            $currency,
            $breakdowns,
            $issueDate,
            $validUntil,
        );

        return [
            'partner_id' => $data->partnerId,
            'payload' => [
                'title' => $data->title,
                'customer' => $this->customer($data->customer),
                'partner_id' => $data->partnerId,
                'issue_date' => $issueDate,
                'valid_until' => $validUntil,
                'currency' => $currency,
                'lines' => $payloadLines,
                'discount' => $this->discountPayload($data->discountType, $data->discountValue, $discount),
                'totals' => $totalsPayload,
                'intro_text' => $data->introText,
                'outro_text' => $data->outroText,
                'internal_note' => $data->internalNote,
            ],
            'totals' => $totals,
            'preview' => $preview,
        ];
    }

    public function requestSha256(QuoteDraftData $data): string
    {
        $request = [
            'title' => $data->title,
            'partner_id' => $data->partnerId,
            'customer' => $this->customer($data->customer),
            'issue_date' => $data->issueDate,
            'valid_until' => $data->validUntil,
            'currency' => $data->currency,
            'lines' => array_map(static fn (QuoteLineData $line): array => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unitPrice,
                'tax_rate' => $line->taxRate,
                'kind' => $line->kind,
                'product_id' => $line->productId,
            ], $data->lines),
            'discount_type' => $data->discountType,
            'discount_value' => $data->discountValue,
            'intro_text' => $data->introText,
            'outro_text' => $data->outroText,
            'internal_note' => $data->internalNote,
            'control_net_minor' => $data->controlNetMinor,
            'control_vat_minor' => $data->controlVatMinor,
            'control_gross_minor' => $data->controlGrossMinor,
        ];

        return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
    }

    /** @return array{domain: DocumentLine, payload: array<string, int|string|null>} */
    private function line(QuoteLineData $line, string $currency): array
    {
        if (trim($line->description) === '' || trim($line->unit) === '') {
            throw new InvalidArgumentException('Quote line description and unit must not be empty.');
        }
        if (! in_array($line->kind, ['service', 'hardware'], true)) {
            throw new InvalidArgumentException('Quote line kind must be service or hardware.');
        }
        if ($line->productId !== null && $line->productId < 1) {
            throw new InvalidArgumentException('Quote product IDs must be positive.');
        }

        $quantity = DecimalQuantity::fromString($line->quantity);
        $unitPrice = Money::fromDecimal($line->unitPrice, $currency);
        $taxRateBasisPoints = Money::fromDecimal($line->taxRate, 'BPS')->minor();

        if ($taxRateBasisPoints < 0 || $taxRateBasisPoints > 10_000) {
            throw new InvalidArgumentException('Quote tax rate must be between 0.00 and 100.00 percent.');
        }

        return [
            'domain' => new DocumentLine(
                $line->description,
                $quantity,
                $unitPrice,
                $taxRateBasisPoints,
            ),
            'payload' => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'quantity_scaled' => $quantity->scaled(),
                'unit' => $line->unit,
                'unit_price' => $line->unitPrice,
                'unit_price_minor' => $unitPrice->minor(),
                'currency' => $currency,
                'tax_rate' => $line->taxRate,
                'tax_rate_basis_points' => $taxRateBasisPoints,
                'kind' => $line->kind,
                'product_id' => $line->productId,
            ],
        ];
    }

    private function discount(string $type, ?string $value, string $currency): Discount
    {
        return match ($type) {
            'none' => $value === null || $value === '0' || $value === '0.00'
                ? Discount::none($currency)
                : throw new InvalidArgumentException('A none discount cannot carry a value.'),
            'percent' => Discount::percentBasisPoints(
                Money::fromDecimal($value ?? throw new InvalidArgumentException('Percent discount value is required.'), 'BPS')->minor(),
                $currency,
            ),
            'fixed' => Discount::fixed(Money::fromDecimal(
                $value ?? throw new InvalidArgumentException('Fixed discount value is required.'),
                $currency,
            )),
            default => throw new InvalidArgumentException('Quote discount type is invalid.'),
        };
    }

    /** @return array{type: string, value: string|null, basis_points?: int, minor?: int, currency: string} */
    private function discountPayload(string $type, ?string $value, Discount $discount): array
    {
        $payload = [
            'type' => $type,
            'value' => $value,
            'currency' => $discount->currency(),
        ];

        if ($type === 'percent') {
            $payload['basis_points'] = $discount->basisPoints();
        }
        if ($type === 'fixed') {
            $payload['minor'] = $discount->fixedMinor();
        }

        return $payload;
    }

    private function assertControlTotals(QuoteDraftData $data, DocumentTotals $totals, string $currency): void
    {
        $matches = $totals->matchesControlTotals(
            $data->controlNetMinor !== null ? Money::fromMinor($data->controlNetMinor, $currency) : null,
            $data->controlVatMinor !== null ? Money::fromMinor($data->controlVatMinor, $currency) : null,
            $data->controlGrossMinor !== null ? Money::fromMinor($data->controlGrossMinor, $currency) : null,
        );

        if (! $matches) {
            throw new InvalidArgumentException('control_totals_mismatch');
        }
    }

    private function issueDate(int $ownerId, ?string $date): string
    {
        if ($date === null) {
            return $this->clock->now()
                ->setTimezone(new DateTimeZone($this->settings->ownerTimezone($ownerId)))
                ->format('Y-m-d');
        }

        return $this->date($date)->format('Y-m-d');
    }

    private function validUntil(int $ownerId, string $issueDate, ?string $date): string
    {
        $issue = $this->date($issueDate);
        $validUntil = $date !== null
            ? $this->date($date)
            : $issue->modify(sprintf('+%d days', $this->settings->defaultValidityDays($ownerId)));

        if ($validUntil < $issue) {
            throw new InvalidArgumentException('Quote validity cannot end before its issue date.');
        }

        return $validUntil->format('Y-m-d');
    }

    private function date(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        if (! $parsed instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Quote dates must use valid YYYY-MM-DD values.');
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    private function customer(array $customer): array
    {
        if (! isset($customer['name']) || ! is_string($customer['name']) || trim($customer['name']) === '') {
            throw new InvalidArgumentException('Quote customer name must not be empty.');
        }

        $canonical = [];

        foreach ($customer as $key => $item) {
            if (is_float($item)
                || (! is_array($item) && ! is_string($item) && ! is_int($item) && ! is_bool($item) && $item !== null)) {
                throw new InvalidArgumentException('Quote customer data must contain JSON values without floats.');
            }

            $canonical[$key] = is_array($item) ? $this->canonicalize($item) : $item;
        }

        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value): array
    {
        $canonical = [];

        foreach ($value as $key => $item) {
            if (is_float($item)
                || (! is_array($item) && ! is_string($item) && ! is_int($item) && ! is_bool($item) && $item !== null)) {
                throw new InvalidArgumentException('Quote customer data must contain JSON values without floats.');
            }

            $canonical[$key] = is_array($item) ? $this->canonicalize($item) : $item;
        }

        if (! array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }
}
