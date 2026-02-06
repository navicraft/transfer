<?php

namespace App\EventSubscriber;

use App\Event\TransferCompletedEvent;
use App\Event\TransferFailedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Example event subscriber demonstrating how to listen to transfer events
 * Other parts of the application can subscribe to these events for:
 * - Sending notifications
 * - Updating analytics
 * - Triggering webhooks
 * - Audit logging
 * - etc.
 */
class TransferEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TransferCompletedEvent::NAME => 'onTransferCompleted',
            TransferFailedEvent::NAME => 'onTransferFailed',
        ];
    }

    public function onTransferCompleted(TransferCompletedEvent $event): void
    {
        $this->logger->info('Transfer completed event received', [
            'source_account' => $event->getSourceAccountUuid(),
            'destination_account' => $event->getDestinationAccountUuid(),
            'amount' => $event->getAmount(),
            'debit_transaction' => $event->getDebitTransaction()->getUuid(),
            'credit_transaction' => $event->getCreditTransaction()->getUuid(),
        ]);

        // Here you could:
        // - Send email notification
        // - Update analytics dashboard
        // - Trigger webhook to external system
        // - Create audit log entry
        // - etc.
    }

    public function onTransferFailed(TransferFailedEvent $event): void
    {
        $this->logger->warning('Transfer failed event received', [
            'source_account' => $event->getSourceAccountUuid(),
            'destination_account' => $event->getDestinationAccountUuid(),
            'amount' => $event->getAmount(),
            'reason' => $event->getReason(),
        ]);

        // Here you could:
        // - Send alert to monitoring system
        // - Log to error tracking service (Sentry, etc.)
        // - Notify customer support
        // - etc.
    }
}
