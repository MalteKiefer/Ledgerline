<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle status of an invoice.
 */
enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case SENT = 'SENT';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return __('enums.invoice_status.'.$this->name);
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
