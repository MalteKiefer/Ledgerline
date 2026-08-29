<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Invoices;

use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Queries\InvoiceAgingQuery;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use LogicException;

final readonly class QueueInvoiceReminder
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceMailer $mailer,
        private InvoiceAgingQuery $aging,
    ) {}

    public function handle(
        InvoiceId $invoiceId,
        int $level,
        ?string $recipient,
        ?DateTimeImmutable $asOf = null,
    ): DeliveryId {
        if ($level < 1 || $level > 3) {
            throw new InvalidArgumentException('Invoice reminder levels must be between 1 and 3.');
        }
        if (! $this->aging->contains($invoiceId->value, $asOf)) {
            throw new DomainException('invoice_not_overdue');
        }
        $ownerId = Auth::id();
        if (! is_int($ownerId) || $ownerId < 1) {
            throw new LogicException('Invoice reminder requires an authenticated owner.');
        }
        $candidate = $this->invoices->assertDeliveryReady($invoiceId, $recipient, 'reminder');
        $this->mailer->assertConfigured($ownerId);
        $this->mailer->assertDocumentReady($candidate['pdf_path'], $candidate['pdf_sha256']);
        [$delivery, $created] = $this->invoices->queueDelivery(
            $invoiceId,
            'reminder',
            $candidate['recipient'],
            new IdempotencyKey('invoice-reminder:'.$invoiceId->value.':'.$level),
            ['level' => $level],
        );
        if ($created) {
            $this->mailer->dispatch($ownerId, $delivery);
        }

        return $delivery;
    }
}
