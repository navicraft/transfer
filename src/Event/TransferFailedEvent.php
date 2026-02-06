<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class TransferFailedEvent extends Event
{
    public const NAME = 'transfer.failed';

    public function __construct(
        private readonly string $sourceAccountUuid,
        private readonly string $destinationAccountUuid,
        private readonly int $amount,
        private readonly string $idempotencyKey,
        private readonly string $reason,
        private readonly ?\Throwable $exception = null
    ) {
    }

    public function getSourceAccountUuid(): string
    {
        return $this->sourceAccountUuid;
    }

    public function getDestinationAccountUuid(): string
    {
        return $this->destinationAccountUuid;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
}
