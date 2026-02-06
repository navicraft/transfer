<?php

namespace App\EventListener;

use App\Attribute\RequiresApiKey;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
final readonly class AuthenticationListener
{
    public function __construct(
        private string $authApiKey
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();

        // Standardize controller to an array if possible for reflection
        if (is_array($controller)) {
            [$controllerClass, $controllerMethod] = $controller;
            $reflection = new \ReflectionMethod($controllerClass, $controllerMethod);
        } elseif (is_object($controller) && method_exists($controller, '__invoke')) {
            $reflection = new \ReflectionMethod($controller, '__invoke');
        } else {
            // Unlikely in standard Symfony controller usage but safe fallback
            return;
        }

        // Check for attribute on method or class
        $attribute = $reflection->getAttributes(RequiresApiKey::class)
                     ?: $reflection->getDeclaringClass()->getAttributes(RequiresApiKey::class);

        if (!$attribute) {
            return;
        }

        $request = $event->getRequest();
        $providedKey = $request->headers->get('X-API-Key');

        if ($providedKey === null || $providedKey !== $this->authApiKey) {
            throw new UnauthorizedHttpException('ApiKey', 'Unauthorized: Invalid or missing API key');
        }
    }
}
