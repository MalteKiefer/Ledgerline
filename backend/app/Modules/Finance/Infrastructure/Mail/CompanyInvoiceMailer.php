<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Mail;

use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\Ports\InvoiceMailer;
use App\Modules\Finance\Infrastructure\Scheduling\SendInvoiceDeliveryJob;
use DomainException;
use Illuminate\Support\Facades\Storage;

final readonly class CompanyInvoiceMailer implements InvoiceMailer
{
    public function __construct(private CompanySmtpMailer $smtp) {}

    public function assertConfigured(int $ownerId): void
    {
        if (! $this->smtp->configured($ownerId)) {
            throw new DomainException('delivery_smtp_unavailable');
        }
    }

    public function dispatch(int $ownerId, DeliveryId $deliveryId): void
    {
        SendInvoiceDeliveryJob::dispatch($ownerId, $deliveryId->value)->afterCommit();
    }

    public function assertDocumentReady(string $path, string $sha256): void
    {
        $diskName = config('files.disk');
        $disk = Storage::disk(is_string($diskName) ? $diskName : 'files');
        if (! $disk->exists($path)) {
            throw new DomainException('delivery_pdf_unavailable');
        }
        $bytes = $disk->get($path);
        if (! is_string($bytes)
            || ! str_starts_with($bytes, '%PDF-')
            || ! hash_equals($sha256, hash('sha256', $bytes))) {
            throw new DomainException('delivery_pdf_unavailable');
        }
    }

    public function send(
        int $ownerId,
        string $recipient,
        InvoiceRevisionMail $mail,
    ): CompanyMailTransportResult {
        return $this->smtp->send($ownerId, $recipient, $mail);
    }

    /** @return array{name: string, address: string} */
    public function senderIdentity(int $ownerId): array
    {
        return $this->smtp->senderIdentity($ownerId);
    }
}
