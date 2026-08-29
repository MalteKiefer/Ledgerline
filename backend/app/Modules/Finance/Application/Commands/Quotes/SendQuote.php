<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\OperationReservation;
use App\Modules\Finance\Application\DTOs\Quotes\PublishQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\DTOs\Quotes\SendQuoteData;
use App\Modules\Finance\Application\DTOs\Quotes\SendQuoteResult;
use App\Modules\Finance\Application\Ports\Quotes\QuoteMailer;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use LogicException;

final readonly class SendQuote
{
    public function __construct(
        private QuoteRepository $quotes,
        private QuoteOperationRepository $operations,
        private QuoteMailer $mailer,
        private PublishQuote $publish,
    ) {}

    public function handle(SendQuoteData $data): QuoteView
    {
        return $this->handleResult($data)->quote;
    }

    public function handleResult(SendQuoteData $data): SendQuoteResult
    {
        $requestSha256 = hash('sha256', json_encode([
            'change_reason' => $data->changeReason === null ? null : trim($data->changeReason),
            'expected_version' => $data->expectedVersion,
            'quote_uuid' => $data->quoteId->uuid,
            'recipient' => $data->recipient === null ? null : trim($data->recipient),
        ], JSON_THROW_ON_ERROR));
        $operation = $this->operations->existing(
            $data->quoteId->ownerId,
            'send',
            $data->idempotencyKey,
            $requestSha256,
            $data->quoteId,
        );
        if ($operation?->status === 'replay') {
            return new SendQuoteResult($this->quotes->get($data->quoteId), true);
        }

        $candidate = $this->quotes->get($data->quoteId);
        $recipient = $this->recipient($data, $candidate);
        $this->mailer->assertConfigured($data->quoteId->ownerId);
        $operation ??= $this->operations->reserve(
            $data->quoteId->ownerId,
            'send',
            $data->idempotencyKey,
            $requestSha256,
            $data->quoteId,
        );

        if ($operation->status === 'failed') {
            throw new InvalidQuoteAction($operation->errorCode ?? 'quote_send_failed');
        }

        $revisionId = $this->resultInt($operation, 'revision_id');
        $quote = $candidate;
        if ($revisionId === null) {
            $quote = $this->publishedQuote($data, $candidate);
            $revision = $quote->currentRevision
                ?? throw new LogicException('Sent quote has no current revision.');
            $revisionId = $revision->id;
            $operation = $this->operations->checkpoint($operation, [
                'revision_id' => $revisionId,
                'recipient' => $recipient,
            ]);
        }

        $quote = $this->quotes->get($data->quoteId);
        $revision = $quote->currentRevision;
        if ($revision === null || $revision->id !== $revisionId) {
            throw new InvalidQuoteAction('quote_revision_stale');
        }
        $this->mailer->assertRevisionReady($revision);
        $deliveryUuid = $this->deliveryUuid($operation);
        $messageId = '<'.$deliveryUuid.'@quotes.ledgerline>';
        $deliveryId = $this->quotes->queueDelivery(
            $data->quoteId,
            $revisionId,
            $operation->recordId,
            $recipient,
            $deliveryUuid,
            $messageId,
        );
        $operation = $this->operations->checkpoint($operation, [
            'revision_id' => $revisionId,
            'delivery_id' => $deliveryId,
        ]);
        $this->mailer->dispatch($data->quoteId->ownerId, $deliveryId);
        $this->operations->succeed($operation, [
            'quote_uuid' => $data->quoteId->uuid,
            'revision_id' => $revisionId,
            'delivery_id' => $deliveryId,
        ]);

        return new SendQuoteResult($this->quotes->get($data->quoteId), false);
    }

    private function publishedQuote(SendQuoteData $data, QuoteView $candidate): QuoteView
    {
        if ($candidate->draft === null) {
            if ($candidate->status !== 'sent' || $candidate->currentRevision === null) {
                throw new InvalidQuoteAction('version_conflict');
            }

            if ($candidate->version === $data->expectedVersion) {
                return $candidate;
            }

            if ($candidate->version !== $data->expectedVersion + 1) {
                throw new InvalidQuoteAction('version_conflict');
            }
        }

        return $this->publish->handle(new PublishQuoteData(
            $data->quoteId,
            $data->expectedVersion,
            'send-publish-'.hash('sha256', $data->idempotencyKey),
            $data->changeReason,
        ));
    }

    private function recipient(SendQuoteData $data, QuoteView $quote): string
    {
        $recipient = $data->recipient;
        if ($recipient === null) {
            $source = $quote->draft ?? $quote->currentRevision?->snapshot ?? [];
            $customer = is_array($source['customer'] ?? null) ? $source['customer'] : [];
            $recipient = is_string($customer['email'] ?? null) ? $customer['email'] : null;
        }
        $recipient = is_string($recipient) ? trim($recipient) : '';

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidQuoteAction('no_recipient');
        }

        return $recipient;
    }

    private function deliveryUuid(OperationReservation $operation): string
    {
        $hex = substr(hash('sha256', 'ledgerline-quote-delivery:'.$operation->ownerId.':'.$operation->recordId), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );

        return $uuid;
    }

    private function resultInt(OperationReservation $operation, string $key): ?int
    {
        $value = $operation->result[$key] ?? null;

        return is_int($value) && $value > 0 ? $value : null;
    }
}
