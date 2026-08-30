<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

/**
 * A blocking reason `LegacyQuoteMapper::map()` returns instead of a mapped
 * row.
 *
 * The later resumable migration plan must treat every diagnostic as a hard
 * stop for that one legacy quote: it is never partially migrated and never
 * silently skipped. `code` is drawn from a fixed vocabulary (the class
 * constants) so the migration can produce exact counts per failure kind, as
 * the activation gate in `docs/finance/quotes-workflow.md` requires.
 */
final readonly class LegacyQuoteDiagnostic
{
    public const string UNKNOWN_CURRENCY = 'unknown_currency';

    public const string FOREIGN_PARTNER = 'foreign_partner';

    public const string FOREIGN_PRODUCT = 'foreign_product';

    public const string UNSUPPORTED_NUMERIC_SCALE = 'unsupported_numeric_scale';

    public const string SERVER_TOTAL_MISMATCH = 'server_total_mismatch';

    public const string MISSING_PDF = 'missing_pdf';

    public const string INVALID_PDF_PATH = 'invalid_pdf_path';

    public const string INVALID_PDF_MIME = 'invalid_pdf_mime';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $code,
        public string $message,
        public array $context = [],
    ) {}
}
