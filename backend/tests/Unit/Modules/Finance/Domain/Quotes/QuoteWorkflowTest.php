<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Quotes;

use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Domain\Quotes\QuoteNumber;
use App\Modules\Finance\Domain\Quotes\QuoteRevisionState;
use App\Modules\Finance\Domain\Quotes\QuoteStatus;
use App\Modules\Finance\Domain\Quotes\QuoteWorkflow;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuoteWorkflowTest extends TestCase
{
    private QuoteWorkflow $workflow;

    protected function setUp(): void
    {
        $this->workflow = new QuoteWorkflow;
    }

    public function test_initial_and_later_version_drafts_are_editable(): void
    {
        $this->workflow->assertDraftEditable(QuoteStatus::Draft, hasPendingDraft: true);
        $this->workflow->assertVersionMayStart(QuoteStatus::Sent, hasPendingDraft: false);
        $this->workflow->assertDraftEditable(QuoteStatus::Sent, hasPendingDraft: true);

        $this->addToAssertionCount(3);
    }

    public function test_initial_draft_publication_performs_draft_to_sent(): void
    {
        $this->workflow->assertDraftMayBePublished(QuoteStatus::Draft, isLaterVersion: false);

        $this->addToAssertionCount(1);
    }

    public function test_later_version_publication_explicitly_keeps_the_series_sent(): void
    {
        $this->workflow->assertDraftMayBePublished(QuoteStatus::Sent, isLaterVersion: true);

        $this->assertInvalidQuoteAction('invalid_transition', function (): void {
            $this->workflow->assertTransition(QuoteStatus::Sent, QuoteStatus::Sent);
        });
    }

    #[DataProvider('invalidPublicationContexts')]
    public function test_publication_rejects_a_status_that_does_not_match_the_draft_kind(
        QuoteStatus $status,
        bool $isLaterVersion,
    ): void {
        $this->assertInvalidQuoteAction('invalid_transition', function () use ($status, $isLaterVersion): void {
            $this->workflow->assertDraftMayBePublished($status, $isLaterVersion);
        });
    }

    /** @return iterable<string, array{QuoteStatus, bool}> */
    public static function invalidPublicationContexts(): iterable
    {
        yield 'initial publication requires draft series' => [QuoteStatus::Sent, false];
        yield 'later publication requires sent series' => [QuoteStatus::Draft, true];
        yield 'accepted is locked' => [QuoteStatus::Accepted, true];
        yield 'declined is locked' => [QuoteStatus::Declined, true];
        yield 'converted is locked' => [QuoteStatus::Converted, true];
    }

    public function test_sent_quotes_may_be_accepted_or_declined(): void
    {
        foreach ([QuoteStatus::Accepted, QuoteStatus::Declined] as $decision) {
            $this->workflow->assertCurrentRevisionMayBeDecided(
                QuoteStatus::Sent,
                $decision,
                expectedRevisionId: 41,
                currentRevisionId: 41,
                validUntil: new DateTimeImmutable('2026-09-30'),
                now: new DateTimeImmutable('2026-09-01'),
                hasPendingDraft: false,
            );
        }

        $this->addToAssertionCount(2);
    }

    public function test_accepted_current_revision_may_be_converted(): void
    {
        $this->workflow->assertCurrentRevisionMayBeConverted(
            QuoteStatus::Accepted,
            expectedRevisionId: 41,
            currentRevisionId: 41,
            validUntil: new DateTimeImmutable('2026-09-30'),
            now: new DateTimeImmutable('2026-09-01'),
            hasPendingDraft: false,
        );

        $this->workflow->assertTransition(QuoteStatus::Accepted, QuoteStatus::Converted);

        $this->addToAssertionCount(2);
    }

    #[DataProvider('invalidTransitions')]
    public function test_reverse_self_and_terminal_transitions_are_rejected(
        QuoteStatus $from,
        QuoteStatus $to,
    ): void {
        $this->assertInvalidQuoteAction('invalid_transition', function () use ($from, $to): void {
            $this->workflow->assertTransition($from, $to);
        });
    }

    /** @return iterable<string, array{QuoteStatus, QuoteStatus}> */
    public static function invalidTransitions(): iterable
    {
        yield 'reverse accepted to sent' => [QuoteStatus::Accepted, QuoteStatus::Sent];
        yield 'reverse declined to sent' => [QuoteStatus::Declined, QuoteStatus::Sent];
        yield 'accepted cannot become declined' => [QuoteStatus::Accepted, QuoteStatus::Declined];
        yield 'self sent' => [QuoteStatus::Sent, QuoteStatus::Sent];
        yield 'self accepted' => [QuoteStatus::Accepted, QuoteStatus::Accepted];
        yield 'terminal declined' => [QuoteStatus::Declined, QuoteStatus::Converted];
        yield 'terminal converted' => [QuoteStatus::Converted, QuoteStatus::Sent];
    }

    #[DataProvider('lockedDraftStates')]
    public function test_only_an_initial_or_pending_later_draft_is_editable(
        QuoteStatus $status,
        bool $hasPendingDraft,
    ): void {
        $this->assertInvalidQuoteAction('quote_locked', function () use ($status, $hasPendingDraft): void {
            $this->workflow->assertDraftEditable($status, $hasPendingDraft);
        });
    }

    /** @return iterable<string, array{QuoteStatus, bool}> */
    public static function lockedDraftStates(): iterable
    {
        yield 'published quote without later draft' => [QuoteStatus::Sent, false];
        yield 'accepted' => [QuoteStatus::Accepted, true];
        yield 'declined' => [QuoteStatus::Declined, true];
        yield 'converted' => [QuoteStatus::Converted, true];
    }

    #[DataProvider('lockedVersionStates')]
    public function test_only_sent_quotes_without_a_pending_draft_may_start_a_version(QuoteStatus $status): void
    {
        $this->assertInvalidQuoteAction('quote_locked', function () use ($status): void {
            $this->workflow->assertVersionMayStart($status, hasPendingDraft: false);
        });
    }

    /** @return iterable<string, array{QuoteStatus}> */
    public static function lockedVersionStates(): iterable
    {
        yield 'initial draft' => [QuoteStatus::Draft];
        yield 'accepted' => [QuoteStatus::Accepted];
        yield 'declined' => [QuoteStatus::Declined];
        yield 'converted' => [QuoteStatus::Converted];
    }

    public function test_a_second_pending_version_is_rejected(): void
    {
        $this->assertInvalidQuoteAction('quote_draft_pending', function (): void {
            $this->workflow->assertVersionMayStart(QuoteStatus::Sent, hasPendingDraft: true);
        });
    }

    public function test_an_unpublished_quote_cannot_be_decided_or_converted(): void
    {
        $this->assertDecisionAndConversionFail(
            'quote_not_published',
            QuoteStatus::Draft,
            currentRevisionId: null,
        );
    }

    public function test_a_stale_expected_revision_cannot_be_decided_or_converted(): void
    {
        $this->assertDecisionAndConversionFail(
            'quote_revision_stale',
            QuoteStatus::Accepted,
            expectedRevisionId: 40,
            currentRevisionId: 41,
        );
    }

    public function test_replaced_revision_takes_precedence_over_a_stale_expected_revision(): void
    {
        $this->assertDecisionAndConversionFail(
            'quote_revision_replaced',
            QuoteStatus::Accepted,
            expectedRevisionId: 40,
            currentRevisionId: 41,
            revisionState: QuoteRevisionState::Replaced,
        );
    }

    public function test_an_expired_revision_cannot_be_decided_or_converted(): void
    {
        $this->assertDecisionAndConversionFail(
            'quote_expired',
            QuoteStatus::Accepted,
            validUntil: new DateTimeImmutable('2026-09-30'),
            now: new DateTimeImmutable('2026-10-01 00:00:00'),
        );
    }

    public function test_a_revision_remains_valid_through_the_end_of_its_valid_until_day(): void
    {
        $validUntil = new DateTimeImmutable('2026-09-30');
        $now = new DateTimeImmutable('2026-09-30 23:59:59.999999');

        $this->workflow->assertCurrentRevisionMayBeDecided(
            QuoteStatus::Sent,
            QuoteStatus::Accepted,
            expectedRevisionId: 41,
            currentRevisionId: 41,
            validUntil: $validUntil,
            now: $now,
            hasPendingDraft: false,
        );
        $this->workflow->assertCurrentRevisionMayBeConverted(
            QuoteStatus::Accepted,
            expectedRevisionId: 41,
            currentRevisionId: 41,
            validUntil: $validUntil,
            now: $now,
            hasPendingDraft: false,
        );

        $this->addToAssertionCount(2);
    }

    public function test_a_pending_later_draft_blocks_decision_and_conversion(): void
    {
        $this->assertDecisionAndConversionFail(
            'quote_draft_pending',
            QuoteStatus::Accepted,
            hasPendingDraft: true,
        );
    }

    #[DataProvider('statusesThatAreNotAccepted')]
    public function test_conversion_requires_an_accepted_status(QuoteStatus $status): void
    {
        $this->assertInvalidQuoteAction('quote_not_accepted', function () use ($status): void {
            $this->workflow->assertCurrentRevisionMayBeConverted(
                $status,
                expectedRevisionId: 41,
                currentRevisionId: 41,
                validUntil: new DateTimeImmutable('2026-09-30'),
                now: new DateTimeImmutable('2026-09-01'),
                hasPendingDraft: false,
            );
        });
    }

    /** @return iterable<string, array{QuoteStatus}> */
    public static function statusesThatAreNotAccepted(): iterable
    {
        yield 'sent' => [QuoteStatus::Sent];
        yield 'declined' => [QuoteStatus::Declined];
        yield 'converted' => [QuoteStatus::Converted];
    }

    public function test_quote_number_uses_the_base_for_revision_one_and_suffixes_later_revisions(): void
    {
        $number = new QuoteNumber('AN-2026-0007');

        $this->assertSame('AN-2026-0007', $number->base());
        $this->assertSame('AN-2026-0007', $number->revisionLabel(1));
        $this->assertSame('AN-2026-0007-R2', $number->revisionLabel(2));
        $this->assertSame('AN-2026-0007-R17', $number->revisionLabel(17));
    }

    #[DataProvider('invalidQuoteNumbers')]
    public function test_quote_number_rejects_empty_base_numbers(string $base): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QuoteNumber($base);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidQuoteNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => [" \t\n"];
    }

    public function test_quote_number_rejects_non_positive_revision_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QuoteNumber('AN-2026-0007')->revisionLabel(0);
    }

    private function assertDecisionAndConversionFail(
        string $errorCode,
        QuoteStatus $conversionStatus,
        int $expectedRevisionId = 41,
        ?int $currentRevisionId = 41,
        DateTimeImmutable $validUntil = new DateTimeImmutable('2026-09-30'),
        DateTimeImmutable $now = new DateTimeImmutable('2026-09-01'),
        bool $hasPendingDraft = false,
        QuoteRevisionState $revisionState = QuoteRevisionState::Current,
    ): void {
        $this->assertInvalidQuoteAction($errorCode, function () use (
            $expectedRevisionId,
            $currentRevisionId,
            $validUntil,
            $now,
            $hasPendingDraft,
            $revisionState,
        ): void {
            $this->workflow->assertCurrentRevisionMayBeDecided(
                QuoteStatus::Sent,
                QuoteStatus::Accepted,
                expectedRevisionId: $expectedRevisionId,
                currentRevisionId: $currentRevisionId,
                validUntil: $validUntil,
                now: $now,
                hasPendingDraft: $hasPendingDraft,
                revisionState: $revisionState,
            );
        });

        $this->assertInvalidQuoteAction($errorCode, function () use (
            $conversionStatus,
            $expectedRevisionId,
            $currentRevisionId,
            $validUntil,
            $now,
            $hasPendingDraft,
            $revisionState,
        ): void {
            $this->workflow->assertCurrentRevisionMayBeConverted(
                $conversionStatus,
                expectedRevisionId: $expectedRevisionId,
                currentRevisionId: $currentRevisionId,
                validUntil: $validUntil,
                now: $now,
                hasPendingDraft: $hasPendingDraft,
                revisionState: $revisionState,
            );
        });
    }

    private function assertInvalidQuoteAction(string $errorCode, callable $action): void
    {
        try {
            $action();
            $this->fail(sprintf('Expected quote action to fail with "%s".', $errorCode));
        } catch (InvalidQuoteAction $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
        }
    }
}
