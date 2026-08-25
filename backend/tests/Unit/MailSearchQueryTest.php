<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Mail\SearchQuery;
use Tests\TestCase;

/**
 * The search grammar, tested against what people type.
 *
 * Paired on purpose: what MUST be recognised, and what must be left alone. A
 * search box that silently turns a typo into a filter returns nothing and gives
 * no reason — which is worse than searching for the text.
 */
class MailSearchQueryTest extends TestCase
{
    public function test_plain_text_stays_plain_text(): void
    {
        $q = SearchQuery::parse('  jahresabschluss 2026  ');

        $this->assertSame('jahresabschluss 2026', $q->free);
        $this->assertSame([], $q->text);
        $this->assertSame([], $q->flags);
        $this->assertFalse($q->isEmpty());
    }

    public function test_address_and_subject_fields(): void
    {
        $q = SearchQuery::parse('from:netcup subject:rechnung offen');

        $this->assertSame(['netcup'], $q->text['from']);
        $this->assertSame(['rechnung'], $q->text['subject']);
        $this->assertSame('offen', $q->free);
    }

    public function test_a_quoted_value_keeps_its_spaces(): void
    {
        $q = SearchQuery::parse('subject:"jahres abschluss" from:"Kiefer Networks"');

        $this->assertSame(['jahres abschluss'], $q->text['subject']);
        $this->assertSame(['Kiefer Networks'], $q->text['from']);
        $this->assertSame('', $q->free);
    }

    public function test_a_repeated_field_narrows_rather_than_replacing(): void
    {
        $q = SearchQuery::parse('from:telekom from:gmbh');

        $this->assertSame(['telekom', 'gmbh'], $q->text['from']);
    }

    public function test_flags_and_attachment(): void
    {
        $q = SearchQuery::parse('is:unread is:starred has:attachment');

        $this->assertSame(['seen' => false, 'flagged' => true], $q->flags);
        $this->assertTrue($q->hasAttachment);
        $this->assertSame('', $q->free);
    }

    public function test_dates_are_inclusive_at_both_ends(): void
    {
        $q = SearchQuery::parse('after:2026-01-01 before:31.12.2026');

        $this->assertNotNull($q->after);
        $this->assertNotNull($q->before);
        $this->assertSame('2026-01-01 00:00:00', $q->after->toDateTimeString());
        $this->assertSame('2026-12-31 23:59:59', $q->before->toDateTimeString());
    }

    public function test_an_unknown_field_is_searched_as_text_not_swallowed(): void
    {
        // A typo must not narrow the query to nothing without saying so.
        $q = SearchQuery::parse('fom:netcup is:banana has:money');

        $this->assertSame('fom:netcup is:banana has:money', $q->free);
        $this->assertSame([], $q->text);
        $this->assertSame([], $q->flags);
        $this->assertNull($q->hasAttachment);
    }

    public function test_a_time_or_url_is_not_mistaken_for_a_field(): void
    {
        $q = SearchQuery::parse('10:30 https://example.com/x');

        $this->assertSame('10:30 https://example.com/x', $q->free);
        $this->assertSame([], $q->text);
    }

    public function test_an_impossible_date_is_left_as_text(): void
    {
        $q = SearchQuery::parse('before:31.02.2026');

        $this->assertNull($q->before);
        $this->assertSame('before:31.02.2026', $q->free);
    }

    public function test_a_bare_field_with_no_value_is_text(): void
    {
        // Mid-typing: "from:" alone must not filter to an empty address.
        $q = SearchQuery::parse('from:');

        $this->assertSame([], $q->text);
        $this->assertSame('from:', $q->free);
    }

    public function test_an_empty_box_is_empty(): void
    {
        $this->assertTrue(SearchQuery::parse('   ')->isEmpty());
    }
}
