<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Quotes;

use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Domain\Shared\Workflow\Exception\InvalidTransition;
use App\Modules\Finance\Domain\Shared\Workflow\StateMachine;
use DateTimeImmutable;

final readonly class QuoteWorkflow
{
    private StateMachine $statusTransitions;

    public function __construct()
    {
        $this->statusTransitions = new StateMachine([
            QuoteStatus::Draft->value => [QuoteStatus::Sent->value],
            QuoteStatus::Sent->value => [QuoteStatus::Accepted->value, QuoteStatus::Declined->value],
            QuoteStatus::Accepted->value => [QuoteStatus::Converted->value],
            QuoteStatus::Declined->value => [],
            QuoteStatus::Converted->value => [],
        ]);
    }

    public function assertTransition(QuoteStatus $from, QuoteStatus $to): void
    {
        try {
            $this->statusTransitions->assertCan($from->value, $to->value);
        } catch (InvalidTransition $exception) {
            throw new InvalidQuoteAction('invalid_transition', $exception);
        }
    }

    public function assertDraftMayBePublished(QuoteStatus $status, bool $isLaterVersion): void
    {
        if ($isLaterVersion) {
            if ($status === QuoteStatus::Sent) {
                return;
            }

            throw new InvalidQuoteAction('invalid_transition');
        }

        $this->assertTransition($status, QuoteStatus::Sent);
    }

    public function assertDraftEditable(QuoteStatus $status, bool $hasPendingDraft): void
    {
        if ($status === QuoteStatus::Draft || ($status === QuoteStatus::Sent && $hasPendingDraft)) {
            return;
        }

        throw new InvalidQuoteAction('quote_locked');
    }

    public function assertVersionMayStart(QuoteStatus $status, bool $hasPendingDraft): void
    {
        if ($status !== QuoteStatus::Sent) {
            throw new InvalidQuoteAction('quote_locked');
        }

        if ($hasPendingDraft) {
            throw new InvalidQuoteAction('quote_draft_pending');
        }
    }

    public function assertCurrentRevisionMayBeDecided(
        QuoteStatus $status,
        QuoteStatus $decision,
        int $expectedRevisionId,
        ?int $currentRevisionId,
        DateTimeImmutable $validUntil,
        DateTimeImmutable $now,
        bool $hasPendingDraft,
        QuoteRevisionState $revisionState = QuoteRevisionState::Current,
    ): void {
        $this->assertCurrentRevisionIsActionable(
            $status,
            $expectedRevisionId,
            $currentRevisionId,
            $validUntil,
            $now,
            $hasPendingDraft,
            $revisionState,
        );
        $this->assertTransition($status, $decision);
    }

    public function assertCurrentRevisionMayBeConverted(
        QuoteStatus $status,
        int $expectedRevisionId,
        ?int $currentRevisionId,
        DateTimeImmutable $validUntil,
        DateTimeImmutable $now,
        bool $hasPendingDraft,
        QuoteRevisionState $revisionState = QuoteRevisionState::Current,
    ): void {
        $this->assertCurrentRevisionIsActionable(
            $status,
            $expectedRevisionId,
            $currentRevisionId,
            $validUntil,
            $now,
            $hasPendingDraft,
            $revisionState,
        );

        if ($status !== QuoteStatus::Accepted) {
            throw new InvalidQuoteAction('quote_not_accepted');
        }

        $this->assertTransition($status, QuoteStatus::Converted);
    }

    private function assertCurrentRevisionIsActionable(
        QuoteStatus $status,
        int $expectedRevisionId,
        ?int $currentRevisionId,
        DateTimeImmutable $validUntil,
        DateTimeImmutable $now,
        bool $hasPendingDraft,
        QuoteRevisionState $revisionState,
    ): void {
        if ($status === QuoteStatus::Draft || $currentRevisionId === null) {
            throw new InvalidQuoteAction('quote_not_published');
        }

        if ($hasPendingDraft) {
            throw new InvalidQuoteAction('quote_draft_pending');
        }

        if ($revisionState === QuoteRevisionState::Replaced) {
            throw new InvalidQuoteAction('quote_revision_replaced');
        }

        if ($expectedRevisionId !== $currentRevisionId) {
            throw new InvalidQuoteAction('quote_revision_stale');
        }

        if ($now > $validUntil->setTime(23, 59, 59, 999999)) {
            throw new InvalidQuoteAction('quote_expired');
        }
    }
}
