<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use InvalidArgumentException;

final readonly class ProjectDocumentSourceRef
{
    public string $sourceType;

    public string $sourceReference;

    public ?int $pinnedRevisionId;

    public function __construct(
        string $sourceType,
        string $sourceReference,
        ?int $pinnedRevisionId = null,
    ) {
        if ($sourceType === 'finance_series') {
            $sourceReference = strtolower($sourceReference);
        } elseif ($sourceType === 'bank_transaction_receipt') {
            [$prefix, $transactionId, $receiptUuid] = array_pad(explode(':', $sourceReference, 3), 3, '');
            $sourceReference = $prefix.':'.$transactionId.':'.strtolower($receiptUuid);
        }
        $valid = match ($sourceType) {
            'finance_series' => preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/Di', $sourceReference) === 1,
            'legacy_invoice' => preg_match('/\Alegacy-invoice:[1-9][0-9]*\z/D', $sourceReference) === 1,
            'file' => preg_match('/\Afile:[1-9][0-9]*\z/D', $sourceReference) === 1,
            'gallery_photo' => preg_match('/\Agallery-photo:[1-9][0-9]*\z/D', $sourceReference) === 1,
            'finance_receipt' => preg_match('/\Afinance-receipt:[1-9][0-9]*\z/D', $sourceReference) === 1,
            'bank_transaction' => preg_match('/\Abank-transaction:[1-9][0-9]*\z/D', $sourceReference) === 1,
            'bank_transaction_receipt' => preg_match('/\Abank-transaction-receipt:[1-9][0-9]*:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/Di', $sourceReference) === 1,
            default => false,
        };
        if (! $valid || ($sourceType === 'finance_series') !== ($pinnedRevisionId !== null)) {
            throw new InvalidArgumentException('Project document source reference is invalid.');
        }
        if ($pinnedRevisionId !== null && $pinnedRevisionId < 1) {
            throw new InvalidArgumentException('Pinned document revision ID must be positive.');
        }

        $this->sourceType = $sourceType;
        $this->sourceReference = $sourceReference;
        $this->pinnedRevisionId = $pinnedRevisionId;
    }
}
