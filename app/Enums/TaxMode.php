<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How VAT is applied to an invoice.
 *
 * STANDARD applies each line's tax rate. REVERSE_CHARGE (EU B2B) and
 * SMALL_BUSINESS (§19 UStG) both apply 0% and print an explanatory note.
 */
enum TaxMode: string
{
    case STANDARD = 'STANDARD';
    case REVERSE_CHARGE = 'REVERSE_CHARGE';
    case SMALL_BUSINESS = 'SMALL_BUSINESS';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard VAT',
            self::REVERSE_CHARGE => 'Reverse charge (EU)',
            self::SMALL_BUSINESS => 'Small business (§19 UStG)',
        };
    }

    /**
     * Whether VAT is charged in this mode.
     */
    public function chargesTax(): bool
    {
        return $this === self::STANDARD;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $c): array => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
