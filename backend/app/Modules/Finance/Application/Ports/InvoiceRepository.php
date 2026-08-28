<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\FinalizedInvoice;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceView;
use Closure;
use DateTimeImmutable;

interface InvoiceRepository
{
    public function get(InvoiceId $id): InvoiceView;

    public function createDraft(InvoiceDraftData $data): InvoiceId;

    public function updateDraft(InvoiceId $id, InvoiceDraftData $data, int $expectedVersion): InvoiceView;

    public function finalize(InvoiceId $id, IdempotencyKey $key, Closure $publish): FinalizedInvoice;

    public function markDeliverySent(DeliveryId $deliveryId, DateTimeImmutable $at): InvoiceView;
}
