<?php

namespace App\Event;

use App\Entity\Transaction;
use Symfony\Contracts\EventDispatcher\Event;

final class TransferCompletedEvent extends Event
{
    public const NAME = 'transfer.completed';

    public function __construct(
        private readonly Transaction $debitTransaction,
        private readonly Transaction $creditTransaction,
        private readonly string $sourceAccountUuid,
        private readonly string $destinationAccountUuid,
        private readonly int $amount,
        private readonly string $idempotencyKey
    ) {
    }

    public function getDebitTransaction(): Transaction
    {
        return $this->debitTransaction;
    }

    public function getCreditTransaction(): Transaction
    {
        return $this->creditTransaction;
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
}
