<?php

declare(strict_types=1);

namespace Src\Shared\Application\Middleware;

use Illuminate\Support\Facades\Log;
use Src\Shared\Application\Bus\Command;

/**
 * LOGGING MIDDLEWARE
 * =================
 * Hər Command-ın başlanğıc və bitmə vaxtını log edir.
 * Debug və monitoring üçün çox faydalıdır.
 *
 * Pipeline-da yeri:
 * Command → [LOGGING] → [Validation] → [Transaction] → Handler
 */
class LoggingMiddleware implements Middleware
{
    public function handle(Command $command, callable $next): mixed
    {
        $commandName = get_class($command);

        // Command başlayanda log
        Log::info("Command başladı: {$commandName}", [
            'command' => get_object_vars($command),
            'timestamp' => now()->toISOString(),
        ]);

        $startTime = microtime(true);

        try {
            // Növbəti middleware-ə ötür (və ya Handler-ə)
            $result = $next($command);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info("Command uğurla tamamlandı: {$commandName}", [
                'duration_ms' => $duration,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error("Command xəta ilə bitdi: {$commandName}", [
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
