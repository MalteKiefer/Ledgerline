<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a financial record (e.g. an expense) has been paid.
 */
enum PaymentStatus: string
{
    case OPEN = 'OPEN';
    case PAID = 'PAID';

    public function label(): string
    {
        return __('enums.payment_status.'.$this->name);
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
