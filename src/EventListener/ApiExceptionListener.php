<?php

namespace App\EventListener;

use App\DTO\TransferResponseDTO;
use App\Exception\TransferException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener]
final readonly class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Unwrap MessageBus exceptions (for sync transport/tests)
        if ($exception instanceof HandlerFailedException) {
            $exception = $this->unwrapHandlerFailedException($exception);
        }

        if ($exception instanceof TransferException) {
            $response = new JsonResponse(
                TransferResponseDTO::error($exception->getMessage())->toArray(),
                Response::HTTP_BAD_REQUEST
            );
            $event->setResponse($response);
            return;
        }

        if ($exception instanceof ValidationFailedException) {
            $errors = [];
            foreach ($exception->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            $response = new JsonResponse(
                TransferResponseDTO::error('Validation failed', $errors)->toArray(),
                Response::HTTP_BAD_REQUEST
            );
            $event->setResponse($response);
            return;
        }

        // Handle serialization errors (e.g. missing fields)
        if ($exception instanceof MissingConstructorArgumentsException) {
             // Extract missing fields from message if possible or just generic
             $response = new JsonResponse(
                TransferResponseDTO::error('Invalid JSON payload: ' . $exception->getMessage())->toArray(),
                Response::HTTP_BAD_REQUEST
            );
            $event->setResponse($response);
            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $response = new JsonResponse(
                TransferResponseDTO::error($exception->getMessage())->toArray(),
                $exception->getStatusCode()
            );
            $event->setResponse($response);
            return;
        }

        // Generic fallback
        $this->logger->error('Internal server error', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $response = new JsonResponse(
            TransferResponseDTO::error('Internal server error')->toArray(),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
        $event->setResponse($response);
    }

    private function unwrapHandlerFailedException(HandlerFailedException $e): \Throwable
    {
        $previous = $e->getPrevious();
        while ($previous instanceof HandlerFailedException) {
            $previous = $previous->getPrevious();
        }

        return $previous ?? $e;
    }
}
