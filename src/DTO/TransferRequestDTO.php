<?php

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TransferRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Source account UUID is required')]
        #[Assert\Uuid(message: 'Source account UUID must be a valid UUID')]
        #[SerializedName('source_account_uuid')]
        public string $sourceAccountUuid,

        #[Assert\NotBlank(message: 'Destination account UUID is required')]
        #[Assert\Uuid(message: 'Destination account UUID must be a valid UUID')]
        #[SerializedName('destination_account_uuid')]
        public string $destinationAccountUuid,

        #[Assert\NotBlank(message: 'Amount is required')]
        #[Assert\Type(type: 'integer', message: 'Amount must be an integer')]
        #[Assert\Positive(message: 'Amount must be positive')]
        public int $amount,

        #[Assert\Length(max: 500, maxMessage: 'Description cannot be longer than {{ limit }} characters')]
        public ?string $description = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sourceAccountUuid: $data['source_account_uuid'] ?? '',
            destinationAccountUuid: $data['destination_account_uuid'] ?? '',
            amount: isset($data['amount']) ? (int) $data['amount'] : 0,
            description: $data['description'] ?? null
        );
    }
}
