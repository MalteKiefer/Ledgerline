<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Invoices;

use App\Services\Invoices\HistoricInvoicePdfParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HistoricInvoicePdfParserTest extends TestCase
{
    #[Test]
    public function it_preserves_modern_invoice_rows_and_wrapped_descriptions(): void
    {
        $text = <<<'TEXT'
Beschreibung                                                Datum Menge Einheit Einzelpreis   USt. % Betrag
Exchange Server aktualisieren; Exchange Server           01.02.2025   0,82       h   40,00 € 19,00 % 39,03 €
Datenfestplatte vergrößern; Exchange Server
Neustarten; Exchange Server Backup kontrollieren und ausführen
Einrichten von RMM Lösung für Monitoring und             01.02.2025   0,23       h   40,00 € 19,00 % 10,95 €
Überwachung auf dem Nextcloud Server
Nettobetrag      49,98 €
TEXT;

        $rows = (new HistoricInvoicePdfParser)->lines($text, 19);

        $this->assertCount(2, $rows);
        $this->assertSame(0.82, $rows[0]['qty']);
        $this->assertSame(39.03, $rows[0]['amount']);
        $this->assertStringContainsString('Datenfestplatte vergrößern', $rows[0]['desc']);
        $this->assertSame(10.95, $rows[1]['amount']);
    }

    #[Test]
    public function it_extracts_legacy_invoice_rows_with_the_invoice_vat_rate(): void
    {
        $text = <<<'TEXT'
Menge        Beschreibung                                           Preis       Steuern                   Kosten
9 Stunden    Installation, Konfiguration & Wartung                  40,00             1                    360,00
             (STN)
             Einrichtung NAS
Netto                                                                                                     360,00
TEXT;

        $rows = (new HistoricInvoicePdfParser)->lines($text, 19);

        $this->assertCount(1, $rows);
        $this->assertSame(9.0, $rows[0]['qty']);
        $this->assertSame('Stunden', $rows[0]['unit']);
        $this->assertSame(40.0, $rows[0]['unitPrice']);
        $this->assertSame(19.0, $rows[0]['vatRate']);
        $this->assertSame(360.0, $rows[0]['amount']);
        $this->assertStringContainsString('Einrichtung NAS', $rows[0]['desc']);
    }

    #[Test]
    public function it_extracts_legacy_rows_when_the_tax_column_is_empty(): void
    {
        $text = <<<'TEXT'
Menge          Beschreibung                                            Preis       Steuern              Kosten
101 Stunde     Programmierung & Entwicklung                            45,00                           4.545,00
Netto                                                                                                  4.545,00
TEXT;

        $rows = (new HistoricInvoicePdfParser)->lines($text, 16);

        $this->assertSame([[
            'desc' => 'Programmierung & Entwicklung', 'qty' => 101.0, 'unit' => 'Stunde',
            'unitPrice' => 45.0, 'vatRate' => 16.0, 'amount' => 4545.0,
        ]], $rows);
    }

    #[Test]
    public function it_extracts_numbered_position_rows(): void
    {
        $text = <<<'TEXT'
Pos    Beschreibung                                                        Einzelpreis    Anzahl          Gesamtpreis
1      .de Domain (15 verschiedene Domains)                                    15,00 €   12 Monate            180,00 €
2      Hosting                                                                  3,99 €   12 Monate             47,88 €
Nettobetrag:                  227,88 €
TEXT;

        $rows = (new HistoricInvoicePdfParser)->lines($text, 19);

        $this->assertCount(2, $rows);
        $this->assertSame('Monate', $rows[0]['unit']);
        $this->assertSame(12.0, $rows[0]['qty']);
        $this->assertSame(15.0, $rows[0]['unitPrice']);
        $this->assertSame(47.88, $rows[1]['amount']);
    }

    #[Test]
    public function it_extracts_wrapped_descriptions_above_quantity_price_amount_rows(): void
    {
        $text = <<<'TEXT'
BESCHREIBUNG                                                         MENGE      EINZELPREIS      BETRAG
Cyberangriff auf Apps Server unterbinden; Bereinigung des Servers
                                                                     2,5        45,00 €         112,50 €

nach ausführlicher Analyse

Härtung und Einrichtung Crowdsec des Cloud Servers
                                                                     1          45,00 €          45,00 €
Zwischensumme:                                                                  157,50 €
TEXT;

        $rows = (new HistoricInvoicePdfParser)->lines($text, 19);

        $this->assertCount(2, $rows);
        $this->assertSame(2.5, $rows[0]['qty']);
        $this->assertSame(45.0, $rows[0]['unitPrice']);
        $this->assertSame(112.5, $rows[0]['amount']);
        $this->assertStringContainsString('nach ausführlicher Analyse', $rows[0]['desc']);
        $this->assertSame(45.0, $rows[1]['amount']);
    }

    #[Test]
    public function it_extracts_rows_with_a_trailing_quantity_unit_price_and_amount(): void
    {
        $text = <<<'TEXT'
Beschreibung                                                   Menge  Einheit  Einzelpreis  Betrag
31.07.2026; Server Speicherplatzbereinigung; Dienste Neustarten 0,3  Std       55,00 €     16,50 €
30.07.2026; Marktprüfung für ThinClient                         0,6  Std       55,00 €     33,00 €
Zu zahlen                                                                  49,50 €
TEXT;

        $rows = (new HistoricInvoicePdfParser)->lines($text, 19);

        $this->assertCount(2, $rows);
        $this->assertSame('Std', $rows[0]['unit']);
        $this->assertSame(0.3, $rows[0]['qty']);
        $this->assertSame(33.0, $rows[1]['amount']);
    }

    #[Test]
    public function it_preserves_dot_decimal_amounts_in_newer_pdf_layouts(): void
    {
        $text = <<<'TEXT'
BESCHREIBUNG                                              MENGE      EINZELPREIS      BETRAG
Server hardening
                                                          2.5        €45.00          €112.50
Zwischensumme:                                                        €112.50
TEXT;

        $rows = (new HistoricInvoicePdfParser)->lines($text, 19);

        $this->assertSame([[
            'desc' => 'Server hardening', 'qty' => 2.5, 'unit' => 'h',
            'unitPrice' => 45.0, 'vatRate' => 19.0, 'amount' => 112.5,
        ]], $rows);
    }

    #[Test]
    public function it_reads_a_german_thousands_separator_without_decimals(): void
    {
        // A dot grouped in threes and no comma is a thousands separator on a
        // German invoice. Reading it as a decimal point would book 1.071 EUR
        // where the document says 1071.
        $text = <<<'TEXT'
Pos Beschreibung                     Einzelpreis   Menge   Betrag
1   Wartungspauschale Jahr           1.071,00 €    1 Stk   1.071,00 €
2   Ersatzteile                      1.500 €       1 Stk   1.500 €
TEXT;

        $rows = (new HistoricInvoicePdfParser)->lines($text, 19);

        $this->assertSame(1071.0, $rows[0]['amount']);
        $this->assertSame(1500.0, $rows[1]['amount']);
    }
}
