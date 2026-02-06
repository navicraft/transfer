<?php

namespace App\Message;

final readonly class ProcessTransferMessage
{
    public function __construct(
        public string $sourceAccountUuid,
        public string $destinationAccountUuid,
        public int $amount,
        public string $currency,
        public string $idempotencyKey,
        public ?string $description = null
    ) {
    }
}
