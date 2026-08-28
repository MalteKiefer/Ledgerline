<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands;

use App\Modules\Finance\Application\DTOs\CreateRevisionData;
use App\Modules\Finance\Application\DTOs\DocumentRevisionId;
use App\Modules\Finance\Application\Ports\DocumentRevisionRepository;
use App\Modules\Finance\Application\Services\CanonicalDocumentSnapshot;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use InvalidArgumentException;

final readonly class CreateDocumentRevision
{
    public function __construct(
        private DocumentRevisionRepository $revisions,
        private DocumentCalculator $calculator,
        private CanonicalDocumentSnapshot $snapshots,
    ) {}

    public function handle(CreateRevisionData $data): DocumentRevisionId
    {
        return $this->create($data, null);
    }

    public function handleIdempotently(CreateRevisionData $data, string $creationKey): DocumentRevisionId
    {
        if (trim($creationKey) === '' || strlen($creationKey) > 255) {
            throw new InvalidArgumentException('Revision creation keys must contain between 1 and 255 bytes.');
        }

        return $this->create($data, $creationKey);
    }

    public function preflight(CreateRevisionData $data): DocumentTotals
    {
        $totals = $this->calculator->calculate($data->lines, $data->discount);
        $this->snapshots->build($data, $totals);

        return $totals;
    }

    public function handlePreparedIdempotently(
        CreateRevisionData $data,
        DocumentTotals $totals,
        string $creationKey,
    ): DocumentRevisionId {
        if (trim($creationKey) === '' || strlen($creationKey) > 255) {
            throw new InvalidArgumentException('Revision creation keys must contain between 1 and 255 bytes.');
        }

        return $this->persist($data, $totals, $creationKey);
    }

    private function create(CreateRevisionData $data, ?string $creationKey): DocumentRevisionId
    {
        $totals = $this->calculator->calculate($data->lines, $data->discount);

        return $this->persist($data, $totals, $creationKey);
    }

    private function persist(
        CreateRevisionData $data,
        DocumentTotals $totals,
        ?string $creationKey,
    ): DocumentRevisionId {
        $canonicalSnapshot = $this->snapshots->build($data, $totals);
        $canonicalJson = json_encode($canonicalSnapshot, JSON_THROW_ON_ERROR);
        $snapshotSha256 = hash('sha256', $canonicalJson);

        if ($creationKey !== null) {
            return $this->revisions->createIdempotently(
                $data,
                $totals,
                $canonicalSnapshot,
                $snapshotSha256,
                $creationKey,
            );
        }

        return $this->revisions->create(
            $data,
            $totals,
            $canonicalSnapshot,
            $snapshotSha256,
        );
    }
}
