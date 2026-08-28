<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands;

use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;

final readonly class CreateDocumentRevision
{
    public function __construct(
        private DocumentRevisionRepository $revisions,
        private DocumentCalculator $calculator,
    ) {}

    public function handle(CreateRevisionData $data): DocumentRevisionId
    {
        $totals = $this->calculator->calculate($data->lines, $data->discount);
        $canonicalSnapshot = $this->canonicalize($data->snapshot);
        $canonicalJson = json_encode($canonicalSnapshot, JSON_THROW_ON_ERROR);

        return $this->revisions->create(
            $data,
            $totals,
            $canonicalSnapshot,
            hash('sha256', $canonicalJson),
        );
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value): array
    {
        $canonical = [];

        foreach ($value as $key => $item) {
            $canonical[$key] = is_array($item) ? $this->canonicalize($item) : $item;
        }

        if (! array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }
}
