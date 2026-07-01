<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The fixed set of project types (the primary category of work).
 *
 * Stored as the backing string value; the label is for display only.
 */
enum ProjectType: string
{
    case CONSULTING = 'CONSULTING';
    case DEVELOPMENT = 'DEVELOPMENT';
    case NETWORK = 'NETWORK';
    case MAINTENANCE = 'MAINTENANCE';
    case SUPPORT = 'SUPPORT';
    case OTHER = 'OTHER';

    /**
     * Human-readable, English label for display in the UI.
     */
    public function label(): string
    {
        return __('enums.project_type.'.$this->name);
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
