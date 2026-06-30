<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The fixed set of project priorities.
 *
 * Stored as the backing string value; the label is for display only.
 */
enum ProjectPriority: string
{
    case LOW = 'LOW';
    case NORMAL = 'NORMAL';
    case HIGH = 'HIGH';
    case URGENT = 'URGENT';

    /**
     * Human-readable, English label for display in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }

    /**
     * All cases as value/label pairs, suitable for select inputs.
     *
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
