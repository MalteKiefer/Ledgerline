<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility\Exception;

use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectDiagnostic;
use App\Modules\Finance\Infrastructure\Compatibility\LegacyProjectExpenseParser;
use RuntimeException;

/**
 * Raised by {@see LegacyProjectExpenseParser}
 * when a legacy expense row cannot be represented exactly. The mapper turns
 * this into a blocking {@see LegacyProjectDiagnostic}
 * rather than letting it escape — a malformed row must never be silently
 * skipped or coerced through a float.
 */
final class LegacyProjectExpenseMalformed extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
