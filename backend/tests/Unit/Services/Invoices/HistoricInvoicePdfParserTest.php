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
}
