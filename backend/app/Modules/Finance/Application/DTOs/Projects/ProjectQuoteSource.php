<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProjectQuoteSource
{
    /** @param list<array{kind:string,description:string,quantity:string,unit_price_minor:int,product_reference?:?string}> $lines */
    public function __construct(public string $seriesUuid, public int $revisionId, public string $snapshotSha256, public ?string $number, public ?string $label, public string $title, public ?string $partnerReference, public ?DateTimeImmutable $issuedOn, public ?DateTimeImmutable $validUntil, public string $currency, public int $netMinor, public int $vatMinor, public int $grossMinor, public array $lines)
    {
        if ($revisionId < 1 || preg_match('/\A[0-9a-f]{64}\z/D', $snapshotSha256) !== 1 || trim($title) === '' || preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
            throw new InvalidArgumentException('project_quote_source_invalid');
        }
        Money::fromMinor($netMinor, $currency);
        Money::fromMinor($vatMinor, $currency);
        Money::fromMinor($grossMinor, $currency);
        foreach ($lines as $line) {
            if (self::containsFloat($line) || ! isset($line['kind'],$line['description'],$line['quantity'],$line['unit_price_minor']) || ! is_string($line['kind']) || ! is_string($line['description']) || ! is_string($line['quantity']) || ! is_int($line['unit_price_minor'])) {
                throw new InvalidArgumentException('project_quote_line_invalid');
            } DecimalQuantity::fromString($line['quantity']);
            Money::fromMinor($line['unit_price_minor'], $currency);
            if (isset($line['product_reference']) && ! is_string($line['product_reference'])) {
                throw new InvalidArgumentException('project_quote_line_invalid');
            }
        }
    }

    public function withSnapshotSha256(string $hash): self
    {
        return new self($this->seriesUuid, $this->revisionId, $hash, $this->number, $this->label, $this->title, $this->partnerReference, $this->issuedOn, $this->validUntil, $this->currency, $this->netMinor, $this->vatMinor, $this->grossMinor, $this->lines);
    }

    /** @param array<array-key, mixed> $value */
    private static function containsFloat(array $value): bool
    {
        foreach ($value as $item) {
            if (is_float($item) || (is_array($item) && self::containsFloat($item))) {
                return true;
            }
        }

        return false;
    }
}
