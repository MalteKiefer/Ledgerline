<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Invoices;

use InvalidArgumentException;

final readonly class InvoiceDraftSource
{
    private const array SOURCE_TYPES = [
        'quote_revision',
        'legacy_quote_snapshot',
        'project_time_batch',
        'recurring_run',
        'cancellation',
        'legacy_invoice',
    ];

    public function __construct(
        public string $sourceType,
        public string $sourceKey,
        public int $sourceRevisionId,
        public string $sourceSnapshotSha256,
        public InvoiceDraftData $draft,
    ) {
        if (! in_array($sourceType, self::SOURCE_TYPES, true)) {
            throw new InvalidArgumentException('Invoice source type is not supported.');
        }
        if (trim($sourceKey) === '' || trim($sourceKey) !== $sourceKey || strlen($sourceKey) > 255) {
            throw new InvalidArgumentException('Invoice source key must be canonical and at most 255 bytes.');
        }
        if ($sourceRevisionId < 1) {
            throw new InvalidArgumentException('Invoice source revision IDs must be positive.');
        }
        if (preg_match('/\A[0-9a-f]{64}\z/D', $sourceSnapshotSha256) !== 1) {
            throw new InvalidArgumentException('Invoice source snapshot hash must be lowercase SHA-256.');
        }
    }
}
