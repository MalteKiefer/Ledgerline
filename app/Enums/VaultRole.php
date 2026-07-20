<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Membership roles for a shared password-Tresor.
 *
 * DB stores the backing string value (viewer/editor/manager).
 * Higher rank = more permissive. Roles are additive upward:
 * a manager can do everything an editor can, and so on.
 */
enum VaultRole: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';
    case Manager = 'manager';

    /**
     * Numeric rank — higher means more permissive.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Viewer => 1,
            self::Editor => 2,
            self::Manager => 3,
        };
    }

    /**
     * Whether this role is at least as permissive as $other.
     */
    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * All valid backing values in ascending rank order.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return ['viewer', 'editor', 'manager'];
    }
}
