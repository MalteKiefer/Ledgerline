<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The fixed set of lifecycle states a project can be in.
 *
 * As with ContactFunction, the backing string value is what is persisted and
 * referenced elsewhere; the label is for display only.
 */
enum ProjectStatus: string
{
    case PLANNED = 'PLANNED';
    case ACTIVE = 'ACTIVE';
    case ON_HOLD = 'ON_HOLD';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    /**
     * Human-readable, English label for display in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planned',
            self::ACTIVE => 'Active',
            self::ON_HOLD => 'On hold',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
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
