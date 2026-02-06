<?php

namespace App\Repository;

use App\Entity\Account;
use App\Enum\AccountStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findByUuid(string $uuid): ?Account
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findByAccountNumber(string $accountNumber): ?Account
    {
        return $this->findOneBy(['accountNumber' => $accountNumber]);
    }

    /**
     * Find account by UUID with pessimistic write lock
     * This prevents concurrent modifications during transfer
     */
    public function findByUuidWithLock(string $uuid, EntityManagerInterface $em): ?Account
    {
        return $em->find(
            Account::class,
            $this->findByUuid($uuid)?->getId(),
            LockMode::PESSIMISTIC_WRITE
        );
    }

    /**
     * Find multiple accounts by UUIDs with pessimistic write lock
     * Locks are acquired in a consistent order to prevent deadlocks
     */
    public function findByUuidsWithLock(array $uuids, EntityManagerInterface $em): array
    {
        // Sort UUIDs to ensure consistent lock ordering
        sort($uuids);

        $accounts = [];
        foreach ($uuids as $uuid) {
            $account = $this->findByUuid($uuid);
            if ($account !== null) {
                // Lock the account
                $lockedAccount = $em->find(
                    Account::class,
                    $account->getId(),
                    LockMode::PESSIMISTIC_WRITE
                );
                if ($lockedAccount !== null) {
                    $accounts[$uuid] = $lockedAccount;
                }
            }
        }

        return $accounts;
    }

    public function findActiveAccounts(): array
    {
        return $this->findBy(['status' => AccountStatus::ACTIVE]);
    }

    public function save(Account $account): void
    {
        $this->getEntityManager()->persist($account);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
