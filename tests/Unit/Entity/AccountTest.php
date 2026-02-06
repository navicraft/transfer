<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Account;
use App\Enum\AccountStatus;
use App\Enum\Currency;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AccountTest extends TestCase
{
    public function testAccountCreation(): void
    {
        $account = new Account('John Doe', Currency::USD);

        $this->assertNotEmpty($account->getUuid());
        $this->assertNotEmpty($account->getAccountNumber());
        $this->assertSame('John Doe', $account->getHolderName());
        $this->assertSame(Currency::USD, $account->getCurrency());
        $this->assertSame(0, $account->getBalance());
        $this->assertSame(AccountStatus::ACTIVE, $account->getStatus());
        $this->assertTrue($account->isActive());
    }

    public function testCredit(): void
    {
        $account = new Account('John Doe', Currency::USD);
        $account->credit(1000);

        $this->assertSame(1000, $account->getBalance());
    }

    public function testDebit(): void
    {
        $account = new Account('John Doe', Currency::USD);
        $account->credit(1000);
        $account->debit(300);

        $this->assertSame(700, $account->getBalance());
    }

    public function testDebitInsufficientFunds(): void
    {
        $account = new Account('John Doe', Currency::USD);
        $account->credit(100);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient funds');

        $account->debit(500);
    }

    public function testCreditNegativeAmount(): void
    {
        $account = new Account('John Doe', Currency::USD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit amount must be positive');

        $account->credit(-100);
    }

    public function testDebitNegativeAmount(): void
    {
        $account = new Account('John Doe', Currency::USD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debit amount must be positive');

        $account->debit(-100);
    }

    public function testInactiveAccount(): void
    {
        $account = new Account('John Doe', Currency::USD, AccountStatus::INACTIVE);

        $this->assertFalse($account->isActive());
    }

    public function testBlockedAccount(): void
    {
        $account = new Account('John Doe', Currency::USD);
        $account->setStatus(AccountStatus::BLOCKED);

        $this->assertFalse($account->isActive());
    }
}
