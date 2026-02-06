<?php

namespace App\Exception;

use RuntimeException;

class TransferException extends RuntimeException
{
    public static function accountNotFound(string $uuid): self
    {
        return new self(sprintf('Account with UUID %s not found', $uuid));
    }

    public static function accountNotActive(string $uuid): self
    {
        return new self(sprintf('Account with UUID %s is not active', $uuid));
    }

    public static function insufficientFunds(string $uuid, int $required, int $available): self
    {
        return new self(
            sprintf(
                'Insufficient funds in account %s. Required: %d, Available: %d',
                $uuid,
                $required,
                $available
            )
        );
    }

    public static function currencyMismatch(string $sourceCurrency, string $destinationCurrency): self
    {
        return new self(
            sprintf(
                'Currency mismatch: source account has %s, destination account has %s',
                $sourceCurrency,
                $destinationCurrency
            )
        );
    }

    public static function selfTransferNotAllowed(): self
    {
        return new self('Self-transfer is not allowed');
    }

    public static function duplicateIdempotencyKey(string $key): self
    {
        return new self(sprintf('Duplicate idempotency key: %s', $key));
    }
}
