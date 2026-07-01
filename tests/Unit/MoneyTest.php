<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_builds_from_a_major_amount(): void
    {
        $this->assertSame(1250, Money::fromAmount('12.50', 'EUR')->cents);
        $this->assertSame(1999, Money::fromAmount(19.99, 'EUR')->cents);
    }

    public function test_it_formats_with_the_currency_code(): void
    {
        $this->assertSame('12.50 EUR', new Money(1250, 'EUR')->format());
        $this->assertSame('1,234.56 USD', new Money(123456, 'USD')->format());
    }

    public function test_amount_returns_major_units(): void
    {
        $this->assertSame(12.5, new Money(1250, 'EUR')->amount());
    }
}
