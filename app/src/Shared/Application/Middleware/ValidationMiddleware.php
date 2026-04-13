<?php

declare(strict_types=1);

namespace Src\Shared\Application\Middleware;

use Src\Shared\Application\Bus\Command;
use Src\Shared\Domain\Exceptions\ValidationException;

/**
 * VALIDATION MIDDLEWARE
 * ====================
 * Command-ın data-sını Handler-ə çatmadan əvvəl yoxlayır.
 *
 * Pipeline-da yeri:
 * Command → [Logging] → [VALIDATION] → [Transaction] → Handler
 *
 * NƏYƏ LAZIMDIR?
 * - Handler yalnız biznes logikasına fokuslanır.
 * - Validation ayrıca middleware-də olur (Single Responsibility).
 * - Yanlış data Handler-ə çatmır.
 */
class ValidationMiddleware implements Middleware
{
    public function handle(Command $command, callable $next): mixed
    {
        // Command-da validate() metodu varsa, çağır
        if (method_exists($command, 'validate')) {
            $errors = $command->validate();

            if (!empty($errors)) {
                throw new ValidationException($errors);
            }
        }

        return $next($command);
    }
}
