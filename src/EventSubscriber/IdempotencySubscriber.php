<?php

namespace App\EventSubscriber;

use App\Attribute\Idempotent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class IdempotencySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        // Don't check for main request here, as middlewares might wrap/sub-request.
        // But usually idempotency is for main request. Keep strict if desired.
        /*
        if (!$event->isMainRequest()) {
            return;
        }
        */

        $controller = $event->getController();

        if (is_array($controller)) {
            [$controllerClass, $controllerMethod] = $controller;
            $reflection = new \ReflectionMethod($controllerClass, $controllerMethod);
        } elseif (is_object($controller) && method_exists($controller, '__invoke')) {
            $reflection = new \ReflectionMethod($controller, '__invoke');
        } else {
            return;
        }

        $attribute = $reflection->getAttributes(Idempotent::class)
                     ?: $reflection->getDeclaringClass()->getAttributes(Idempotent::class);

        if (!$attribute) {
            return;
        }

        $request = $event->getRequest();
        $key = $request->headers->get('X-Idempotency-Key');

        if ($key === null || trim($key) === '') {
            throw new BadRequestHttpException('X-Idempotency-Key header is required');
        }
    }
}
