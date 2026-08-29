<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Infrastructure\Pdf\AtomicDocumentObjectStore;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class OrphanDocumentReconciler
{
    public function __construct(
        private AtomicDocumentObjectStore $objects,
        private int $graceSeconds = 86_400,
    ) {
        if ($graceSeconds < 1) {
            throw new InvalidArgumentException('Document orphan grace must be positive.');
        }
    }

    public function reconcile(DateTimeImmutable $now): int
    {
        $cutoff = $now->modify('-'.$this->graceSeconds.' seconds');
        $deleted = 0;

        foreach ($this->objects->ownedBefore($cutoff) as $candidate) {
            if (DB::table('finance_document_revisions')
                ->where('pdf_path', $candidate['path'])
                ->exists()) {
                continue;
            }

            $this->objects->deleteIfOwned($candidate['path'], $candidate['write']);
            $deleted++;
        }

        return $deleted;
    }
}
