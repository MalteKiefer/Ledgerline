<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Invoicing\PdfInvoiceParser;
use PHPUnit\Framework\TestCase;

class PdfInvoiceParserTest extends TestCase
{
    private function parser(): PdfInvoiceParser
    {
        return new PdfInvoiceParser;
    }

    public function test_it_reads_the_older_beleg_template(): void
    {
        $text = implode("\n", [
            'USt-IdNr.: DE 347 51 73 86',
            'Beleg',
            'Für Rechnungsnr.: 2025-5',
            "Datum:\t12.06.2025",
            'Kiefer Networks',
            'Malte Kiefer',
            'Adalbert-Stifter-Str. 6• 95512• Neudrossenfeld',
            'Acme GmbH',
            'Contact Person',
            'Example Street 1',
            '53797 Lohmar',
            "Beschreibung\tDatumMengeEinheitEinzelpreisUSt. %Betrag",
            'Task one 02.04.2025 0,43 h 40,00 €19,00 %20,47 €',
            "Task two\t12.06.2025 0,50 h 40,00 €19,00 %23,80 €",
            '37,20 €',
            '7,07 €',
            '44,27 €',
            'Nettobetrag',
            'USt.',
            'Gesamt',
        ]);

        $r = $this->parser()->parse($text);

        $this->assertSame('2025-5', $r['number']);
        $this->assertSame('2025-06-12', $r['issue_date']);
        $this->assertSame(37.20, $r['net']);
        $this->assertSame(44.27, $r['gross']);
        $this->assertSame('Acme GmbH', $r['customer']['name']);
        $this->assertSame('53797', $r['customer']['postal_code']);
        $this->assertSame('Lohmar', $r['customer']['city']);
        $this->assertCount(2, $r['lines']);
        $this->assertSame('Task one', $r['lines'][0]['description']);
        $this->assertSame(0.43, $r['lines'][0]['quantity']);
        $this->assertSame(40.0, $r['lines'][0]['unit_price']);
    }

    public function test_it_reads_the_newer_rechnung_template(): void
    {
        $text = implode("\n", [
            'Rechnung',
            'Kiefer Networks',
            "Rechnung Nr.:\t2026-003",
            "Rechnungsdatum:\t30.04.2026",
            "Fällig am:\t14.05.2026",
            'RECHNUNG AN',
            'Globex GmbH',
            'Some Person',
            'person@globex.test',
            'Example Way 5, 12345 Berlin',
            'Deutschland',
            'USt-IdNr.: DE304323922',
            'RECHNUNGSDETAILS',
            "BESCHREIBUNG\tMENGE\tEINZELPREIS\tBETRAG",
            'Task A 0,18 45,00 € 8,10 €',
            'Task B 1 45,00 € 45,00 €',
            'Zwischensumme 53,10 €',
            'Steuer (19%) 10,09 €',
            'Gesamt 63,19 €',
        ]);

        $r = $this->parser()->parse($text);

        $this->assertSame('2026-003', $r['number']);
        $this->assertSame('2026-04-30', $r['issue_date']);
        $this->assertSame('2026-05-14', $r['due_date']);
        $this->assertSame(53.10, $r['net']);
        $this->assertSame(63.19, $r['gross']);
        $this->assertSame('Globex GmbH', $r['customer']['name']);
        $this->assertSame('12345', $r['customer']['postal_code']);
        $this->assertSame('Berlin', $r['customer']['city']);
        $this->assertSame('DE304323922', $r['customer']['vat_id']);
        $this->assertCount(2, $r['lines']);
        $this->assertSame(45.0, $r['lines'][0]['unit_price']);
    }

    public function test_it_falls_back_to_the_file_name(): void
    {
        $r = $this->parser()->parse('', '20190114_ Kiefer Networks_ Rechnung R-00072 - STN Nürnberg.pdf');

        $this->assertSame('R-00072', $r['number']);
        $this->assertSame('2019-01-14', $r['issue_date']);
        $this->assertSame('STN Nürnberg', $r['customer']['name']);
    }

    public function test_it_detects_small_business(): void
    {
        $text = implode("\n", [
            'Rechnung Nr.: 1',
            'Rechnungsdatum: 10.04.2014',
            'Gesamt EUR 146,58',
            'Gemäß § 19 (1) UStG erheben wir keine Umsatzsteuer.',
        ]);

        $r = $this->parser()->parse($text);

        $this->assertTrue($r['small_business']);
        $this->assertSame(0, $r['tax_rate']);
        $this->assertSame(146.58, $r['net']);
        $this->assertSame(146.58, $r['gross']);
    }

    public function test_it_parses_english_and_german_number_formats(): void
    {
        // € prefix, dot decimal.
        $en = $this->parser()->parse("Rechnung #: 2026-001\nRechnungsdatum: 02.02.2026\nZwischensumme: €157.50\nGesamt: €187.43");
        $this->assertSame('2026-001', $en['number']);
        $this->assertSame(157.50, $en['net']);

        // German thousands + comma decimal.
        $de = $this->parser()->parse("Rechnung Nr.: 2026-005\nRechnungsdatum: 12.06.2026\nZwischensumme 900,00 €\nGESAMT 1.071,00 €");
        $this->assertSame(1071.0, $de['gross']);
    }

    public function test_a_captured_number_must_contain_a_digit(): void
    {
        // "Rechnung Nr. Rechnungsdatum" table header must not be taken as the number.
        $r = $this->parser()->parse('Rechnung Nr. Rechnungsdatum');
        $this->assertNull($r['number']);
    }
}
