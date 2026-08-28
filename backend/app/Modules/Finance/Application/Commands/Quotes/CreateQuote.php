<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\OperationReservation;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteOperationRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Services\Quotes\QuoteDraftFactory;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use LogicException;
use Throwable;

final readonly class CreateQuote
{
    public function __construct(
        private QuoteDraftFactory $factory,
        private QuoteRepository $quotes,
        private QuoteOperationRepository $operations,
    ) {}

    public function handle(int $ownerId, string $idempotencyKey, QuoteDraftData $data): QuoteView
    {
        $requestSha256 = $this->factory->requestSha256($data);
        $reservation = $this->operations->reserve(
            $ownerId,
            'create',
            $idempotencyKey,
            $requestSha256,
            null,
        );

        if ($reservation->status !== 'new') {
            return $this->replay($reservation);
        }

        try {
            $draft = $this->factory->build($ownerId, $data);
            $quote = $this->quotes->createDraft(
                $ownerId,
                $draft['payload'],
                $draft['totals'],
                $draft['partner_id'],
            );
            $this->operations->succeed($reservation, ['quote_uuid' => $quote->id->uuid]);

            return $quote;
        } catch (Throwable $exception) {
            $this->operations->fail($reservation, 'quote_create_failed');

            throw $exception;
        }
    }

    private function replay(OperationReservation $reservation): QuoteView
    {
        if ($reservation->status === 'in_progress') {
            throw new InvalidQuoteAction('operation_in_progress');
        }
        if ($reservation->status === 'failed') {
            throw new InvalidQuoteAction($reservation->errorCode ?? 'quote_create_failed');
        }

        $uuid = $reservation->result['quote_uuid'] ?? null;

        if ($reservation->status !== 'replay' || ! is_string($uuid)) {
            throw new LogicException('Completed quote creation has no replay identity.');
        }

        return $this->quotes->get(new QuoteId($reservation->ownerId, $uuid));
    }
}
