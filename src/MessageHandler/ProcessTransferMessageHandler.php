<?php

namespace App\MessageHandler;

use App\Enum\Currency;
use App\Message\ProcessTransferMessage;
use App\Service\TransferService;
use App\ValueObject\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessTransferMessageHandler
{
    public function __construct(
        private TransferService $transferService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessTransferMessage $message): void
    {
        $this->logger->info('Processing transfer message', [
            'source_account' => $message->sourceAccountUuid,
            'destination_account' => $message->destinationAccountUuid,
            'amount' => $message->amount,
            'idempotency_key' => $message->idempotencyKey,
        ]);

        try {
            $currency = Currency::from($message->currency);
            $money = Money::fromMinorUnits($message->amount, $currency);

            $this->transferService->doTransfer(
                sourceAccountUuid: $message->sourceAccountUuid,
                destinationAccountUuid: $message->destinationAccountUuid,
                money: $money,
                idempotencyKey: $message->idempotencyKey,
                description: $message->description
            );

            $this->logger->info('Transfer message processed successfully', [
                'idempotency_key' => $message->idempotencyKey,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process transfer message', [
                'idempotency_key' => $message->idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to let Messenger handle retry logic
            throw $e;
        }
    }
}
