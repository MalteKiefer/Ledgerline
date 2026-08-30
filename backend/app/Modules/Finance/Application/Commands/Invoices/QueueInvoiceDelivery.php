<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use Illuminate\Support\Facades\Auth;
use LogicException;

final readonly class QueueInvoiceDelivery
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceMailer $mailer,
    ) {}

    public function handle(
        InvoiceId $invoiceId,
        ?string $recipient,
        IdempotencyKey $key,
    ): DeliveryId {
        $ownerId = Auth::id();
        if (! is_int($ownerId) || $ownerId < 1) {
            throw new LogicException('Invoice delivery requires an authenticated owner.');
        }
        $replay = $this->invoices->replayDelivery($invoiceId, 'invoice', $recipient, $key);
        if ($replay !== null) {
            if ($this->invoices->deliveryNeedsDispatch($replay)) {
                $this->mailer->dispatch($ownerId, $replay);
            }

            return $replay;
        }

        $candidate = $this->invoices->assertDeliveryReady($invoiceId, $recipient, 'invoice');
        $this->mailer->assertConfigured($ownerId);
        $this->mailer->assertDocumentReady($candidate['pdf_path'], $candidate['pdf_sha256']);
        [$deliveryId, $created] = $this->invoices->queueDelivery(
            $invoiceId,
            'invoice',
            $candidate['recipient'],
            $key,
        );
        if ($created) {
            $this->mailer->dispatch($ownerId, $deliveryId);
        }

        return $deliveryId;
    }
}
