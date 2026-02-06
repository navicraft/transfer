<?php

namespace App\Enum;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case INR = 'INR';
    case JPY = 'JPY';
    case AUD = 'AUD';
    case CAD = 'CAD';
    case CHF = 'CHF';

    public function getSymbol(): string
    {
        return match($this) {
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::INR => '₹',
            self::JPY => '¥',
            self::AUD => 'A$',
            self::CAD => 'C$',
            self::CHF => 'CHF',
        };
    }

    public function getMinorUnit(): int
    {
        return match($this) {
            self::JPY => 0, // Japanese Yen has no minor units
            default => 2,
        };
    }
}
