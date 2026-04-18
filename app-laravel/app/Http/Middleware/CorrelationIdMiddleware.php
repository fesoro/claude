<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Src\Shared\Infrastructure\Logging\StructuredLogger;

/**
 * CORRELATION ID MIDDLEWARE
 * =========================
 * Hər HTTP sorğusuna unikal correlation ID təyin edir.
 *
 * BU MIDDLEWARE NƏ EDİR?
 * ======================
 * 1. Request header-ində X-Correlation-ID varsa → onu istifadə et
 *    (microservice zəncirində əvvəlki servis göndərib)
 * 2. Yoxdursa → yeni correlation ID yarat
 * 3. StructuredLogger-ə bu ID-ni təyin et
 * 4. Response header-inə də əlavə et (debug üçün)
 *
 * MİCROSERVİCE ZƏNCİRİ:
 * ======================
 * İstifadəçi → API Gateway → Order Service → Payment Service → Notification Service
 *
 * Hər servis eyni correlation_id istifadə edir.
 * Bütün servislər üzrə bir sorğunun yolunu izləyə bilərsən.
 *
 * Kibana-da: correlation_id = "req_abc123" → bütün servislərdən logları gör.
 */
class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Header-dən gələn correlation ID (microservice zəncirindən)
        $correlationId = $request->header('X-Correlation-ID');

        // StructuredLogger-ə təyin et
        StructuredLogger::setCorrelationId($correlationId);

        // Auth olan istifadəçini global kontekstə əlavə et
        if ($request->user()) {
            StructuredLogger::addGlobalContext('user_id', $request->user()->id);
        }

        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (microtime(true) - $startTime) * 1000;

        // HTTP request logla
        StructuredLogger::logRequest(
            method: $request->method(),
            uri: $request->path(),
            statusCode: $response->getStatusCode(),
            durationMs: $durationMs,
            userId: $request->user()?->id,
        );

        // Response header-inə correlation ID əlavə et
        // Frontend/client debug üçün faydalıdır:
        // "Bu sorğuda problem var" → correlation ID-ni göndər → backend-çi logu tapa bilər
        $response->headers->set('X-Correlation-ID', StructuredLogger::getCorrelationId());

        return $response;
    }

    /**
     * Request bitdikdən sonra konteksti sıfırla.
     * Bu, PHP-FPM/Octane-da memory leak-in qarşısını alır.
     */
    public function terminate(Request $request, Response $response): void
    {
        StructuredLogger::reset();
    }
}
