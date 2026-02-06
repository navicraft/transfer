<?php

namespace App\Entity;

use App\Enum\Currency;
use App\Enum\TransactionStatus;
use App\Repository\TransactionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(columns: ['uuid'], name: 'idx_transaction_uuid')]
#[ORM\Index(columns: ['account_id'], name: 'idx_transaction_account')]
#[ORM\Index(columns: ['status'], name: 'idx_transaction_status')]
#[ORM\Index(columns: ['idempotency_key'], name: 'idx_transaction_idempotency')]
#[ORM\Index(columns: ['related_transaction_id'], name: 'idx_transaction_related')]
#[ORM\Index(columns: ['created_at'], name: 'idx_transaction_created')]
#[ORM\Index(columns: ['completed_at'], name: 'idx_transaction_completed')]
#[ORM\Index(columns: ['account_id', 'status'], name: 'idx_transaction_account_status')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true, nullable: false)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Account $account;

    #[ORM\Column(type: 'bigint', nullable: false)]
    private int $amount;

    #[ORM\Column(type: 'string', length: 3, nullable: false, enumType: Currency::class)]
    private Currency $currency;

    #[ORM\Column(type: 'string', length: 20, nullable: false, enumType: TransactionStatus::class)]
    private TransactionStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: false)]
    private string $idempotencyKey;

    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Transaction $relatedTransaction = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    public function __construct(
        Account $account,
        int $amount,
        Currency $currency,
        string $idempotencyKey,
        ?string $description = null
    ) {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->account = $account;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->idempotencyKey = $idempotencyKey;
        $this->description = $description;
        $this->status = TransactionStatus::PENDING;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function setStatus(TransactionStatus $status): self
    {
        $this->status = $status;

        if ($status->isTerminal() && $this->completedAt === null) {
            $this->completedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function markAsCompleted(): self
    {
        return $this->setStatus(TransactionStatus::COMPLETED);
    }

    public function markAsFailed(): self
    {
        return $this->setStatus(TransactionStatus::FAILED);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getRelatedTransaction(): ?Transaction
    {
        return $this->relatedTransaction;
    }

    public function setRelatedTransaction(?Transaction $relatedTransaction): self
    {
        $this->relatedTransaction = $relatedTransaction;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }
}
