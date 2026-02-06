<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounts and transactions tables with indexes';
    }

    public function up(Schema $schema): void
    {
        // Create accounts table
        $this->addSql('
            CREATE TABLE accounts (
                id INT AUTO_INCREMENT NOT NULL,
                uuid VARCHAR(36) NOT NULL,
                account_number VARCHAR(20) NOT NULL,
                holder_name VARCHAR(255) NOT NULL,
                currency VARCHAR(3) NOT NULL,
                balance BIGINT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                version INT NOT NULL DEFAULT 1,
                UNIQUE INDEX UNIQ_CAC89EACD17F50A6 (uuid),
                UNIQUE INDEX UNIQ_CAC89EAC53B48DB0 (account_number),
                INDEX idx_account_uuid (uuid),
                INDEX idx_account_number (account_number),
                INDEX idx_account_status (status),
                INDEX idx_account_currency (currency),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create transactions table
        $this->addSql('
            CREATE TABLE transactions (
                id INT AUTO_INCREMENT NOT NULL,
                uuid VARCHAR(36) NOT NULL,
                account_id INT NOT NULL,
                related_transaction_id INT DEFAULT NULL,
                amount BIGINT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL,
                description TEXT DEFAULT NULL,
                idempotency_key VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX UNIQ_EAA81A4CD17F50A6 (uuid),
                UNIQUE INDEX UNIQ_EAA81A4C7D2D4D1E (idempotency_key),
                INDEX idx_transaction_uuid (uuid),
                INDEX idx_transaction_account (account_id),
                INDEX idx_transaction_status (status),
                INDEX idx_transaction_idempotency (idempotency_key),
                INDEX idx_transaction_related (related_transaction_id),
                INDEX idx_transaction_created (created_at),
                INDEX idx_transaction_completed (completed_at),
                INDEX idx_transaction_account_status (account_id, status),
                INDEX IDX_EAA81A4C9B6B5FBA (account_id),
                INDEX IDX_EAA81A4C5FEE3342 (related_transaction_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Add foreign key constraints
        $this->addSql('
            ALTER TABLE transactions
            ADD CONSTRAINT FK_EAA81A4C9B6B5FBA
            FOREIGN KEY (account_id)
            REFERENCES accounts (id)
            ON DELETE RESTRICT
        ');

        $this->addSql('
            ALTER TABLE transactions
            ADD CONSTRAINT FK_EAA81A4C5FEE3342
            FOREIGN KEY (related_transaction_id)
            REFERENCES transactions (id)
            ON DELETE SET NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4C9B6B5FBA');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4C5FEE3342');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('DROP TABLE accounts');
    }
}
