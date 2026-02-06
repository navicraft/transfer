<?php

namespace App\Controller;

use App\Attribute\Idempotent;
use App\Attribute\RateLimit;
use App\Attribute\RequiresApiKey;
use App\DTO\TransferRequestDTO;
use App\DTO\TransferResponseDTO;
use App\Enum\Currency;
use App\Exception\TransferException;
use App\Message\ProcessTransferMessage;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class TransferController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
    }

    #[Route('/transfers', name: 'api_transfer_create', methods: ['POST'])]
    #[RequiresApiKey]
    #[RateLimit]
    #[Idempotent]
    public function create(
        #[MapRequestPayload('json')] TransferRequestDTO $transferRequest,
        Request $request
    ): JsonResponse {
        // Get idempotency key (validated by IdempotencySubscriber)
        $idempotencyKey = $request->headers->get('X-Idempotency-Key');

        // Dispatch message to Messenger for async processing
        $message = new ProcessTransferMessage(
            sourceAccountUuid: $transferRequest->sourceAccountUuid,
            destinationAccountUuid: $transferRequest->destinationAccountUuid,
            amount: $transferRequest->amount,
            currency: Currency::USD->value,
            idempotencyKey: $idempotencyKey,
            description: $transferRequest->description
        );

        $this->messageBus->dispatch($message);

        return $this->json(
            TransferResponseDTO::success('Transfer processed')->toArray(),
            Response::HTTP_CREATED
        );
    }
}
