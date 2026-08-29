<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use Illuminate\Support\Facades\Auth;
use LogicException;

final readonly class RetryInvoiceDelivery
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceMailer $mailer,
    ) {}

    public function handle(DeliveryId $failedDelivery, IdempotencyKey $key): DeliveryId
    {
        $ownerId = Auth::id();
        if (! is_int($ownerId) || $ownerId < 1) {
            throw new LogicException('Invoice delivery retry requires an authenticated owner.');
        }
        $replay = $this->invoices->replayDeliveryRetry($failedDelivery, $key);
        if ($replay !== null) {
            if ($this->invoices->deliveryNeedsDispatch($replay)) {
                $this->mailer->dispatch($ownerId, $replay);
            }

            return $replay;
        }
        $candidate = $this->invoices->assertDeliveryRetryReady($failedDelivery);
        $this->mailer->assertConfigured($ownerId);
        $this->mailer->assertDocumentReady($candidate['pdf_path'], $candidate['pdf_sha256']);
        [$retry, $created] = $this->invoices->retryDelivery($failedDelivery, $key);
        if ($created) {
            $this->mailer->dispatch($ownerId, $retry);
        }

        return $retry;
    }
}
