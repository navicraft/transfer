<?php

namespace App\Enum;

enum AccountStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLOCKED = 'blocked';
    case CLOSED = 'closed';

    public function canTransact(): bool
    {
        return $this === self::ACTIVE;
    }
}
