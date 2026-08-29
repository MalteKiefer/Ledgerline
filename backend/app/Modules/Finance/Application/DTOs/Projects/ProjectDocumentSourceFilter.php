<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProjectDocumentSourceFilter
{
    public const array TYPES = ['finance_series', 'legacy_invoice', 'file', 'gallery_photo', 'finance_receipt', 'bank_transaction', 'bank_transaction_receipt'];

    /**
     * @param  list<string>  $sourceTypes
     * @param  list<string>  $mimeGroups
     */
    public function __construct(
        public int $ownerId,
        public ?string $q = null,
        public array $sourceTypes = [],
        public array $mimeGroups = [],
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public ?string $cursor = null,
        public int $perPage = 50,
    ) {
        if ($ownerId < 1 || $perPage < 1 || $perPage > 100 || array_diff($sourceTypes, self::TYPES) !== []
            || array_diff($mimeGroups, ['pdf', 'image', 'other']) !== []
            || ($from !== null && $to !== null && $from > $to)
            || ($q !== null && mb_strlen(trim($q)) > 255)
            || ($cursor !== null && strlen($cursor) > 512)) {
            throw new InvalidArgumentException('Project document source filter is invalid.');
        }
    }
}
