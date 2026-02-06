<?php

namespace App\DataFixtures;

use App\Entity\Account;
use App\Enum\AccountStatus;
use App\Enum\Currency;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public const SOURCE_ACCOUNT_UUID = '123e4567-e89b-12d3-a456-426614174000';
    public const DEST_ACCOUNT_UUID = '123e4567-e89b-12d3-a456-426614174001';

    public function load(ObjectManager $manager): void
    {
        // Source Account
        $sourceAccount = new Account(
            'John Doe',
            Currency::USD,
            AccountStatus::ACTIVE
        );
        $this->setUuid($sourceAccount, self::SOURCE_ACCOUNT_UUID);
        $sourceAccount->credit(10000); // $100.00
        $manager->persist($sourceAccount);

        // Destination Account
        $destAccount = new Account(
            'Jane Smith',
            Currency::USD,
            AccountStatus::ACTIVE
        );
        $this->setUuid($destAccount, self::DEST_ACCOUNT_UUID);
        $manager->persist($destAccount);

        $manager->flush();
    }

    private function setUuid(Account $account, string $uuid): void
    {
        $reflection = new \ReflectionClass($account);
        $property = $reflection->getProperty('uuid');
        $property->setAccessible(true);
        $property->setValue($account, $uuid);
    }
}
