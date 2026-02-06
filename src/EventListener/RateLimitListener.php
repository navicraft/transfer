<?php

namespace App\EventListener;

use App\Attribute\RateLimit;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
final readonly class RateLimitListener
{
    public function __construct(
        private RateLimiterFactory $transferApiLimiter // Note: Defaulting to this variable name for now, assuming DI works.
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (is_array($controller)) {
            [$controllerClass, $controllerMethod] = $controller;
            $reflection = new \ReflectionMethod($controllerClass, $controllerMethod);
        } elseif (is_object($controller) && method_exists($controller, '__invoke')) {
            $reflection = new \ReflectionMethod($controller, '__invoke');
        } else {
            return;
        }

        $attributes = $reflection->getAttributes(RateLimit::class)
                      ?: $reflection->getDeclaringClass()->getAttributes(RateLimit::class);

        if (!$attributes) {
            return;
        }

        /** @var RateLimit $rateLimitAttribute */
        $rateLimitAttribute = $attributes[0]->newInstance();
        // Ideally we would support dynamic factory injection based on config name,
        // but for now we rely on the injected $transferApiLimiter as per existing code.

        $request = $event->getRequest();
        $identifier = $request->headers->get('X-API-Key') ?? $request->getClientIp();

        $limiter = $this->transferApiLimiter->create($identifier);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp());
        }
    }
}
