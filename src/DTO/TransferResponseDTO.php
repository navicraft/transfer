<?php

namespace App\DTO;

final readonly class TransferResponseDTO
{
    public function __construct(
        public string $status,
        public string $message,
        public ?array $data = null
    ) {
    }

    public static function success(string $message = 'Transfer processed', ?array $data = null): self
    {
        return new self('success', $message, $data);
    }

    public static function error(string $message, ?array $data = null): self
    {
        return new self('error', $message, $data);
    }

    public function toArray(): array
    {
        $response = [
            'status' => $this->status,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        return $response;
    }
}
