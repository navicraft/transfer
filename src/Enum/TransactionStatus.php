<?php

namespace App\Enum;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED;
    }
}
