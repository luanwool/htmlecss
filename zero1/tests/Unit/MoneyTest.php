<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_brazilian_and_plain_formats(): void
    {
        $this->assertSame(4500, Money::parse('45'));
        $this->assertSame(4550, Money::parse('45,50'));
        $this->assertSame(123456, Money::parse('1.234,56'));
        $this->assertSame(4550, Money::parse('45.50'));
        $this->assertSame(4500, Money::parse('R$ 45,00'));
        $this->assertNull(Money::parse(''));
        $this->assertNull(Money::parse(null));
    }

    public function test_formats_in_brl(): void
    {
        $this->assertSame('R$ 1.234,56', Money::format(123456));
        $this->assertSame('45,00', Money::format(4500, false));
        $this->assertSame('Sob consulta', Money::format(null));
    }
}
