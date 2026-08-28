<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\StoredDocument;

interface DocumentStorage
{
    public function putPdf(string $seriesUuid, string $bytes): StoredDocument;

    public function delete(string $path): void;
}
