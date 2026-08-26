<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DocumentNumber;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The document number template, in both directions.
 *
 * Rendering and reading back must agree: a template that prints AN-2026-0007
 * has to parse it too, or the next sequence is derived from a number nothing
 * recognises.
 */
class DocumentNumberTest extends TestCase
{
    private function may(): Carbon
    {
        return Carbon::create(2026, 5, 4) ?? Carbon::now();
    }

    public function test_a_letter_n_in_the_prefix_is_not_mistaken_for_the_sequence(): void
    {
        // The bug this guards: taking the FIRST run of N's ate the N in "AN-"
        // and produced A7-2026-0007 — a letter silently replaced by a digit in
        // the one field that identifies the document.
        $this->assertSame('AN-2026-0007', DocumentNumber::format('AN-YYYY-NNNN', 7, $this->may()));
        $this->assertSame('RN-2026-0007', DocumentNumber::format('RN-YYYY-NNNN', 7, $this->may()));
        $this->assertSame(7, DocumentNumber::sequenceFrom('AN-YYYY-NNNN', 'AN-2026-0007', $this->may()));
    }

    public function test_the_invoice_default_is_unchanged(): void
    {
        $this->assertSame('2026-0042', DocumentNumber::format('YYYY-NNNN', 42, $this->may()));
        $this->assertSame('2026-0042', DocumentNumber::format(null, 42, $this->may()));
        $this->assertSame(42, DocumentNumber::sequenceFrom(null, '2026-0042', $this->may()));
    }

    public function test_date_tokens_come_from_the_documents_own_date(): void
    {
        $this->assertSame('R-26/05/04-007', DocumentNumber::format('R-YY/MM/DD-NNN', 7, $this->may()));
        $this->assertSame(7, DocumentNumber::sequenceFrom('R-YY/MM/DD-NNN', 'R-26/05/04-007', $this->may()));
    }

    public function test_a_tie_resolves_to_the_rightmost_run(): void
    {
        // A sequence sits at the end by convention.
        $this->assertSame('AN-7', DocumentNumber::format('AN-N', 7, $this->may()));
    }

    public function test_an_unpadded_sequence_keeps_its_own_width(): void
    {
        $this->assertSame('R-12345', DocumentNumber::format('R-NNN', 12345, $this->may()));
    }

    public function test_a_number_from_another_format_reads_as_unknown(): void
    {
        // Null is the point: it marks a number as unreadable instead of quietly
        // counting it as sequence zero and reusing a live number.
        $this->assertNull(DocumentNumber::sequenceFrom('YYYY-NNNN', 'R-00047', $this->may()));
        $this->assertNull(DocumentNumber::sequenceFrom('YYYY-NNNN', '2026-0000', $this->may()));
    }

    public function test_a_template_without_a_sequence_falls_back_to_the_bare_number(): void
    {
        // No run of N's means the template names no sequence, so there is
        // nothing to place it into and the bare number is all that is left.
        $this->assertSame('7', DocumentNumber::format('BELEG', 7, $this->may()));
        $this->assertNull(DocumentNumber::sequenceFrom('BELEG', 'BELEG', $this->may()));
    }

    public function test_a_template_whose_only_runs_are_single_letters_resolves_to_the_last(): void
    {
        // Genuinely ambiguous: the sequence has to be written as N's, so a
        // template with several single N's cannot say which one it means. The
        // rightmost is chosen because that is where a sequence goes — which also
        // means a word that happens to contain N is not a usable template.
        $this->assertSame('RECHNU7G', DocumentNumber::format('RECHNUNG', 7, $this->may()));
    }
}
