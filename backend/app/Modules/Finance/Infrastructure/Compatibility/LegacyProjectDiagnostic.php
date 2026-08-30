<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

/**
 * One finding from {@see LegacyProjectMapper}. A `blocking` diagnostic means
 * the affected legacy row must not be migrated as-is — the global migration
 * plan's activation gate requires zero blocking diagnostics before cutover.
 * A non-blocking diagnostic is informational (an unresolved cross-module
 * pointer that a later plan resolves, for example) and does not stop
 * mapping the rest of the project.
 */
final readonly class LegacyProjectDiagnostic
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $code,
        public bool $blocking,
        public string $message,
        public array $context = [],
    ) {}
}
