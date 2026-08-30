<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class InvoiceDraftTarget
{
    public function __construct(public string $targetReference, public ProjectDocumentSourceRef $source, public ?string $navigationCapability = null) {}
}
