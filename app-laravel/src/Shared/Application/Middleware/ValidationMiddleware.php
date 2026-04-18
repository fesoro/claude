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
    /**
     * İKİ VALİDASİYA YANAŞMASINI DƏSTƏKLƏ:
     *
     * 1. Exception-based: validate() void qaytarır, xəta olduqda exception atır.
     *    Bu yanaşma daha DDD-yə uyğundur — domain qaydası pozuldu = exception.
     *
     * 2. Error-array-based: validate() array qaytarır, boş deyilsə xəta var.
     *    Bu yanaşma daha REST API-yə uyğundur — birdən çox xəta qaytarıla bilər.
     *
     * Bu middleware hər ikisini dəstəkləyir.
     */
    public function handle(Command $command, callable $next): mixed
    {
        if (method_exists($command, 'validate')) {
            // validate() exception ata bilər (void return) və ya error array qaytara bilər
            $result = $command->validate();

            // Əgər array qaytarıbsa və boş deyilsə — xəta var
            if (is_array($result) && !empty($result)) {
                throw new ValidationException($result);
            }
        }

        return $next($command);
    }
}
