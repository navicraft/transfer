<?php

namespace App\Tests\Unit\ValueObject;

use App\Enum\Currency;
use App\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testFromMinorUnits(): void
    {
        $money = Money::fromMinorUnits(1000, Currency::USD);

        $this->assertSame(1000, $money->getAmount());
        $this->assertSame(Currency::USD, $money->getCurrency());
    }

    public function testFromMajorUnits(): void
    {
        $money = Money::fromMajorUnits(10.50, Currency::USD);

        $this->assertSame(1050, $money->getAmount());
        $this->assertSame(Currency::USD, $money->getCurrency());
    }

    public function testToMajorUnits(): void
    {
        $money = Money::fromMinorUnits(1050, Currency::USD);

        $this->assertSame(10.50, $money->toMajorUnits());
    }

    public function testNegativeAmountThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        Money::fromMinorUnits(-100, Currency::USD);
    }

    public function testAdd(): void
    {
        $money1 = Money::fromMinorUnits(1000, Currency::USD);
        $money2 = Money::fromMinorUnits(500, Currency::USD);

        $result = $money1->add($money2);

        $this->assertSame(1500, $result->getAmount());
    }

    public function testAddDifferentCurrencyThrowsException(): void
    {
        $money1 = Money::fromMinorUnits(1000, Currency::USD);
        $money2 = Money::fromMinorUnits(500, Currency::EUR);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch');

        $money1->add($money2);
    }

    public function testSubtract(): void
    {
        $money1 = Money::fromMinorUnits(1000, Currency::USD);
        $money2 = Money::fromMinorUnits(300, Currency::USD);

        $result = $money1->subtract($money2);

        $this->assertSame(700, $result->getAmount());
    }

    public function testSubtractInsufficientFundsThrowsException(): void
    {
        $money1 = Money::fromMinorUnits(100, Currency::USD);
        $money2 = Money::fromMinorUnits(500, Currency::USD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient funds');

        $money1->subtract($money2);
    }

    public function testIsGreaterThan(): void
    {
        $money1 = Money::fromMinorUnits(1000, Currency::USD);
        $money2 = Money::fromMinorUnits(500, Currency::USD);

        $this->assertTrue($money1->isGreaterThan($money2));
        $this->assertFalse($money2->isGreaterThan($money1));
    }

    public function testIsZero(): void
    {
        $money = Money::fromMinorUnits(0, Currency::USD);

        $this->assertTrue($money->isZero());
    }

    public function testIsPositive(): void
    {
        $money = Money::fromMinorUnits(100, Currency::USD);

        $this->assertTrue($money->isPositive());
    }

    public function testEquals(): void
    {
        $money1 = Money::fromMinorUnits(1000, Currency::USD);
        $money2 = Money::fromMinorUnits(1000, Currency::USD);
        $money3 = Money::fromMinorUnits(1000, Currency::EUR);

        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3));
    }

    public function testHasSameCurrency(): void
    {
        $money1 = Money::fromMinorUnits(1000, Currency::USD);
        $money2 = Money::fromMinorUnits(500, Currency::USD);
        $money3 = Money::fromMinorUnits(1000, Currency::EUR);

        $this->assertTrue($money1->hasSameCurrency($money2));
        $this->assertFalse($money1->hasSameCurrency($money3));
    }

    public function testToString(): void
    {
        $money = Money::fromMinorUnits(1050, Currency::USD);

        $this->assertSame('$ 10.50', (string) $money);
    }
}
