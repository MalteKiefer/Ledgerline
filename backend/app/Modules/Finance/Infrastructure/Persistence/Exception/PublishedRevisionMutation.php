<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Exception;

use RuntimeException;

final class PublishedRevisionMutation extends RuntimeException
{
    public static function revision(): self
    {
        return new self('Published document revisions are immutable.');
    }

    public static function activity(): self
    {
        return new self('Document activities are append-only.');
    }
}
