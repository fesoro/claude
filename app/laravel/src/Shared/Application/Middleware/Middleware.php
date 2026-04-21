<?php

declare(strict_types=1);

namespace Src\Shared\Application\Middleware;

use Src\Shared\Application\Bus\Command;

/**
 * MIDDLEWARE PIPELINE PATTERN
 * ===========================
 * Middleware — Command Handler-dən əvvəl və sonra işləyən əlavə əməliyyatlardır.
 *
 * NECƏ İŞLƏYİR?
 * Command → [LoggingMiddleware] → [ValidationMiddleware] → [TransactionMiddleware] → Handler
 *
 * Hər middleware:
 * 1. Command-ı alır
 * 2. Öz işini görür (log, validate, transaction aç)
 * 3. $next() çağırıb növbəti middleware-ə ötürür
 * 4. Nəticəni geri qaytarır
 *
 * REAL NÜMUNƏ (Laravel HTTP Middleware ilə oxşardır):
 * HTTP Request → [AuthMiddleware] → [RateLimitMiddleware] → Controller
 * Command     → [LogMiddleware]  → [ValidateMiddleware]  → Handler
 */
interface Middleware
{
    /**
     * @param Command $command Emal olunan command
     * @param callable $next Növbəti middleware-i çağıran funksiya
     * @return mixed Handler-in nəticəsi
     */
    public function handle(Command $command, callable $next): mixed;
}
