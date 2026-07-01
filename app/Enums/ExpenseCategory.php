<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Preset expense categories. Users can also enter a free custom category on the
 * expense itself, so this list stays tight.
 */
enum ExpenseCategory: string
{
    case HARDWARE = 'HARDWARE';
    case SOFTWARE = 'SOFTWARE';
    case SUBSCRIPTION = 'SUBSCRIPTION';
    case HOSTING = 'HOSTING';
    case TRAVEL = 'TRAVEL';
    case OFFICE = 'OFFICE';
    case MARKETING = 'MARKETING';
    case FEES = 'FEES';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::HARDWARE => 'Hardware',
            self::SOFTWARE => 'Software',
            self::SUBSCRIPTION => 'Subscription',
            self::HOSTING => 'Hosting',
            self::TRAVEL => 'Travel',
            self::OFFICE => 'Office',
            self::MARKETING => 'Marketing',
            self::FEES => 'Fees',
            self::OTHER => 'Other',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
