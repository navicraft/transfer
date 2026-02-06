<?php

namespace App\ValueObject;

use App\Enum\Currency;
use InvalidArgumentException;

final readonly class Money
{
    private function __construct(
        private int $amount,
        private Currency $currency
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }

    public static function fromMinorUnits(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    public static function fromMajorUnits(float $amount, Currency $currency): self
    {
        $minorUnit = $currency->getMinorUnit();
        $multiplier = 10 ** $minorUnit;

        return new self((int) round($amount * $multiplier), $currency);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function toMajorUnits(): float
    {
        $minorUnit = $this->currency->getMinorUnit();
        $divisor = 10 ** $minorUnit;

        return $this->amount / $divisor;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        if ($this->amount < $other->amount) {
            throw new InvalidArgumentException('Insufficient funds for subtraction');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function isGreaterThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount >= $other->amount;
    }

    public function isLessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function hasSameCurrency(Money $other): bool
    {
        return $this->currency === $other->currency;
    }

    private function assertSameCurrency(Money $other): void
    {
        if (!$this->hasSameCurrency($other)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Currency mismatch: %s vs %s',
                    $this->currency->value,
                    $other->currency->value
                )
            );
        }
    }

    public function __toString(): string
    {
        return sprintf(
            '%s %s',
            $this->currency->getSymbol(),
            number_format($this->toMajorUnits(), $this->currency->getMinorUnit())
        );
    }
}
