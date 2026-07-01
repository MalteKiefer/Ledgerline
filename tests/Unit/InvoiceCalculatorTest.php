<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TaxMode;
use App\Services\Invoicing\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    private function lines(): array
    {
        return [
            ['quantity' => 2, 'unit_price_cents' => 5000, 'tax_rate' => 19],
            ['quantity' => 1, 'unit_price_cents' => 10000, 'tax_rate' => 7],
        ];
    }

    public function test_standard_totals(): void
    {
        $r = new InvoiceCalculator()->compute($this->lines(), TaxMode::STANDARD, 0);

        $this->assertSame(20000, $r['net_cents']);
        $this->assertSame(2600, $r['tax_cents']); // 1900 + 700
        $this->assertSame(22600, $r['gross_cents']);
    }

    public function test_discount_reduces_net_and_scales_tax(): void
    {
        $r = new InvoiceCalculator()->compute($this->lines(), TaxMode::STANDARD, 2000);

        $this->assertSame(18000, $r['net_cents']);
        $this->assertSame(2340, $r['tax_cents']); // 2600 * 18000/20000
        $this->assertSame(20340, $r['gross_cents']);
        $this->assertSame(2000, $r['discount_cents']);
    }

    public function test_reverse_charge_has_no_tax(): void
    {
        $r = new InvoiceCalculator()->compute($this->lines(), TaxMode::REVERSE_CHARGE, 0);

        $this->assertSame(20000, $r['net_cents']);
        $this->assertSame(0, $r['tax_cents']);
        $this->assertSame(20000, $r['gross_cents']);
    }
}
