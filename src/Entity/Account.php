<?php

namespace App\Entity;

use App\Enum\AccountStatus;
use App\Enum\Currency;
use App\Repository\AccountRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(columns: ['uuid'], name: 'idx_account_uuid')]
#[ORM\Index(columns: ['account_number'], name: 'idx_account_number')]
#[ORM\Index(columns: ['status'], name: 'idx_account_status')]
#[ORM\Index(columns: ['currency'], name: 'idx_account_currency')]
#[ORM\HasLifecycleCallbacks]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true, nullable: false)]
    private string $uuid;

    #[ORM\Column(type: 'string', length: 20, unique: true, nullable: false)]
    private string $accountNumber;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $holderName;

    #[ORM\Column(type: 'string', length: 3, nullable: false, enumType: Currency::class)]
    private Currency $currency;

    #[ORM\Column(type: 'bigint', nullable: false)]
    private int $balance = 0;

    #[ORM\Column(type: 'string', length: 20, nullable: false, enumType: AccountStatus::class)]
    private AccountStatus $status;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $version = 1;

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'account')]
    private Collection $transactions;

    public function __construct(
        string $holderName,
        Currency $currency,
        AccountStatus $status = AccountStatus::ACTIVE
    ) {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->accountNumber = $this->generateAccountNumber();
        $this->holderName = $holderName;
        $this->currency = $currency;
        $this->status = $status;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->transactions = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
        $this->version++;
    }

    private function generateAccountNumber(): string
    {
        // Generate a random 16-digit account number
        return sprintf(
            '%04d%04d%04d%04d',
            random_int(1000, 9999),
            random_int(1000, 9999),
            random_int(1000, 9999),
            random_int(1000, 9999)
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getHolderName(): string
    {
        return $this->holderName;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function setBalance(int $balance): self
    {
        $this->balance = $balance;
        return $this;
    }

    public function debit(int $amount): self
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive');
        }

        if ($this->balance < $amount) {
            throw new \DomainException('Insufficient funds');
        }

        $this->balance -= $amount;
        return $this;
    }

    public function credit(int $amount): self
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        $this->balance += $amount;
        return $this;
    }

    public function getStatus(): AccountStatus
    {
        return $this->status;
    }

    public function setStatus(AccountStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status->canTransact();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }
}
