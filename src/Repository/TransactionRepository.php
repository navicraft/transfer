<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Enum\TransactionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findByUuid(string $uuid): ?Transaction
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Transaction
    {
        return $this->findOneBy(['idempotencyKey' => $idempotencyKey]);
    }

    public function existsByIdempotencyKey(string $idempotencyKey): bool
    {
        return $this->count(['idempotencyKey' => $idempotencyKey]) > 0;
    }

    public function findByAccountId(int $accountId, ?TransactionStatus $status = null): array
    {
        $criteria = ['account' => $accountId];

        if ($status !== null) {
            $criteria['status'] = $status;
        }

        return $this->findBy($criteria, ['createdAt' => 'DESC']);
    }

    public function save(Transaction $transaction): void
    {
        $this->getEntityManager()->persist($transaction);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
