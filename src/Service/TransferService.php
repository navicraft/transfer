<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Event\TransferCompletedEvent;
use App\Event\TransferFailedEvent;
use App\Exception\TransferException;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TransferService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accountRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly IdempotencyService $idempotencyService,
        private readonly DistributedLockService $lockService
    ) {
    }

    /**
     * Execute a fund transfer between two accounts
     *
     * @throws TransferException
     */
    public function doTransfer(
        string $sourceAccountUuid,
        string $destinationAccountUuid,
        Money $money,
        string $idempotencyKey,
        ?string $description = null
    ): array {
        // Fast idempotency check using Redis (<1ms vs 10-50ms DB query)
        if ($this->idempotencyService->exists($idempotencyKey)) {
            // Check if we have a cached result
            $cachedResult = $this->idempotencyService->getResult($idempotencyKey);
            if ($cachedResult && $cachedResult['status'] === 'completed') {
                $this->logger->info('Returning cached transfer result', [
                    'idempotency_key' => $idempotencyKey,
                ]);
                return $cachedResult['result'];
            }

            throw TransferException::duplicateIdempotencyKey($idempotencyKey);
        }

        // Mark as processing in Redis
        if (!$this->idempotencyService->markAsProcessing($idempotencyKey)) {
            throw TransferException::duplicateIdempotencyKey($idempotencyKey);
        }

        // Validate not self-transfer
        if ($sourceAccountUuid === $destinationAccountUuid) {
            $this->idempotencyService->storeFailure($idempotencyKey, 'Self-transfer not allowed');
            throw TransferException::selfTransferNotAllowed();
        }

        // Acquire distributed locks on both accounts (prevents concurrent transfers)
        // Locks are acquired in sorted order to prevent deadlocks
        $locks = $this->lockService->acquireMultipleLocks([
            $sourceAccountUuid,
            $destinationAccountUuid
        ]);

        if (empty($locks)) {
            $this->idempotencyService->storeFailure($idempotencyKey, 'Failed to acquire locks');
            throw new TransferException('Failed to acquire account locks. Please try again.');
        }

        try {
            $this->entityManager->beginTransaction();

            // Fetch accounts WITHOUT database locks (we have Redis locks)
            $sourceAccount = $this->accountRepository->findByUuid($sourceAccountUuid);
            $destinationAccount = $this->accountRepository->findByUuid($destinationAccountUuid);

            // Validate source account exists
            if ($sourceAccount === null) {
                throw TransferException::accountNotFound($sourceAccountUuid);
            }

            // Validate destination account exists
            if ($destinationAccount === null) {
                throw TransferException::accountNotFound($destinationAccountUuid);
            }

            // Validate accounts are active
            if (!$sourceAccount->isActive()) {
                throw TransferException::accountNotActive($sourceAccountUuid);
            }

            if (!$destinationAccount->isActive()) {
                throw TransferException::accountNotActive($destinationAccountUuid);
            }

            // Validate same currency
            if ($sourceAccount->getCurrency() !== $destinationAccount->getCurrency()) {
                throw TransferException::currencyMismatch(
                    $sourceAccount->getCurrency()->value,
                    $destinationAccount->getCurrency()->value
                );
            }

            // Validate currency matches money object
            if ($sourceAccount->getCurrency() !== $money->getCurrency()) {
                throw TransferException::currencyMismatch(
                    $sourceAccount->getCurrency()->value,
                    $money->getCurrency()->value
                );
            }

            // Validate sufficient funds
            if ($sourceAccount->getBalance() < $money->getAmount()) {
                throw TransferException::insufficientFunds(
                    $sourceAccountUuid,
                    $money->getAmount(),
                    $sourceAccount->getBalance()
                );
            }

            // Create debit transaction (negative amount)
            $debitTransaction = new Transaction(
                account: $sourceAccount,
                amount: -$money->getAmount(),
                currency: $money->getCurrency(),
                idempotencyKey: $idempotencyKey,
                description: $description
            );

            // Create credit transaction (positive amount)
            $creditTransaction = new Transaction(
                account: $destinationAccount,
                amount: $money->getAmount(),
                currency: $money->getCurrency(),
                idempotencyKey: $idempotencyKey . '_credit',
                description: $description
            );

            // Link transactions (double-entry bookkeeping)
            $debitTransaction->setRelatedTransaction($creditTransaction);
            $creditTransaction->setRelatedTransaction($debitTransaction);

            // Update account balances
            $sourceAccount->debit($money->getAmount());
            $destinationAccount->credit($money->getAmount());

            // Mark transactions as completed
            $debitTransaction->markAsCompleted();
            $creditTransaction->markAsCompleted();

            // Persist transactions
            $this->transactionRepository->save($debitTransaction);
            $this->transactionRepository->save($creditTransaction);

            // Single flush - commit all changes atomically
            $this->entityManager->flush();
            $this->entityManager->commit();

            $result = [
                'debit_transaction_uuid' => $debitTransaction->getUuid(),
                'credit_transaction_uuid' => $creditTransaction->getUuid(),
            ];

            // Cache successful result in Redis for 24 hours
            $this->idempotencyService->storeResult($idempotencyKey, $result);

            $this->logger->info('Transfer completed successfully', [
                'source_account' => $sourceAccountUuid,
                'destination_account' => $destinationAccountUuid,
                'amount' => $money->getAmount(),
                'currency' => $money->getCurrency()->value,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Dispatch success event
            $this->eventDispatcher->dispatch(
                new TransferCompletedEvent(
                    debitTransaction: $debitTransaction,
                    creditTransaction: $creditTransaction,
                    sourceAccountUuid: $sourceAccountUuid,
                    destinationAccountUuid: $destinationAccountUuid,
                    amount: $money->getAmount(),
                    idempotencyKey: $idempotencyKey
                ),
                TransferCompletedEvent::NAME
            );

            return $result;

        } catch (TransferException $e) {
            $this->entityManager->rollback();

            // Store failure in Redis
            $this->idempotencyService->storeFailure($idempotencyKey, $e->getMessage());

            $this->logger->warning('Transfer failed', [
                'source_account' => $sourceAccountUuid,
                'destination_account' => $destinationAccountUuid,
                'amount' => $money->getAmount(),
                'reason' => $e->getMessage(),
            ]);

            // Dispatch failure event
            $this->eventDispatcher->dispatch(
                new TransferFailedEvent(
                    sourceAccountUuid: $sourceAccountUuid,
                    destinationAccountUuid: $destinationAccountUuid,
                    amount: $money->getAmount(),
                    idempotencyKey: $idempotencyKey,
                    reason: $e->getMessage(),
                    exception: $e
                ),
                TransferFailedEvent::NAME
            );

            throw $e;
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            // Store failure in Redis
            $this->idempotencyService->storeFailure($idempotencyKey, 'Internal server error');

            $this->logger->error('Transfer failed with unexpected error', [
                'source_account' => $sourceAccountUuid,
                'destination_account' => $destinationAccountUuid,
                'amount' => $money->getAmount(),
                'error' => $e->getMessage(),
            ]);

            // Dispatch failure event
            $this->eventDispatcher->dispatch(
                new TransferFailedEvent(
                    sourceAccountUuid: $sourceAccountUuid,
                    destinationAccountUuid: $destinationAccountUuid,
                    amount: $money->getAmount(),
                    idempotencyKey: $idempotencyKey,
                    reason: 'Internal server error',
                    exception: $e
                ),
                TransferFailedEvent::NAME
            );

            throw new TransferException('Transfer failed: ' . $e->getMessage(), 0, $e);
        } finally {
            // Always release distributed locks
            $this->lockService->releaseMultipleLocks($locks ?? []);
        }
    }
}
