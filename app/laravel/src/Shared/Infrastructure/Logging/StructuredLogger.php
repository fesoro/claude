<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Logging;

use Illuminate\Support\Facades\Log;

/**
 * STRUCTURED LOGGING — Strukturlaşdırılmış Log Sistemi
 * ======================================================
 *
 * ═══════════════════════════════════════════════════════════════
 * STRUCTURED LOGGING NƏDİR?
 * ═══════════════════════════════════════════════════════════════
 *
 * ƏNƏNƏVI LOG (plain text):
 *   [2024-01-15 14:30:22] Sifariş yaradıldı. User: 42, Order: 123, Məbləğ: 150.00 AZN
 *
 *   Problemlər:
 *   - grep ilə axtarış çətindir (hər log fərqli formatda)
 *   - Maşın oxuya bilmir (regular expression lazımdır)
 *   - Grafana/Kibana/Datadog bu logu parse edə bilmir
 *   - Filtr etmək mümkün deyil (user_id=42 olan logları göstər)
 *
 * STRUCTURED LOG (JSON):
 *   {
 *     "timestamp": "2024-01-15T14:30:22Z",
 *     "level": "info",
 *     "message": "order.created",
 *     "context": {
 *       "user_id": 42,
 *       "order_id": "ord_123",
 *       "amount": 150.00,
 *       "currency": "AZN",
 *       "correlation_id": "req_abc123",
 *       "trace_id": "span_xyz789"
 *     }
 *   }
 *
 *   Üstünlüklər:
 *   ✅ Maşın oxuya bilir (JSON parse)
 *   ✅ Kibana/Grafana-da filtr etmək asan: context.user_id = 42
 *   ✅ Bütün loglar eyni formatda
 *   ✅ Correlation ID ilə bir sorğunun bütün loglarını tapa bilərsən
 *
 * ═══════════════════════════════════════════════════════════════
 * LOG SƏVİYYƏLƏRİ (RFC 5424)
 * ═══════════════════════════════════════════════════════════════
 *
 * emergency: Sistem tamamilə çöküb. Hər kəs bilməlidir!
 *   Nümunə: Database serveri tamamilə əlçatmazdır.
 *
 * alert: Dərhal müdaxilə lazımdır.
 *   Nümunə: Disk sahəsi 95% dolub.
 *
 * critical: Kritik xəta, amma sistem hələ işləyir.
 *   Nümunə: Ödəniş gateway-i çöküb, amma digər funksiyalar işləyir.
 *
 * error: Xəta baş verdi, amma sistem çökmədi.
 *   Nümunə: İstifadəçinin ödənişi uğursuz oldu.
 *
 * warning: Potensial problem, amma hələ xəta deyil.
 *   Nümunə: API rate limit 80%-ə çatdı.
 *
 * notice: Normal, amma diqqətəlayiq hadisə.
 *   Nümunə: İstifadəçi parolunu dəyişdi.
 *
 * info: Adi əməliyyat logları.
 *   Nümunə: Sifariş yaradıldı, email göndərildi.
 *
 * debug: Development üçün ətraflı məlumat.
 *   Nümunə: SQL sorğusu, request payload, response body.
 *
 * ═══════════════════════════════════════════════════════════════
 * CORRELATION ID — Sorğu İzləmə
 * ═══════════════════════════════════════════════════════════════
 *
 * Bir HTTP sorğu zamanı bir neçə log yazılır:
 *   1. Request alındı
 *   2. Database sorğusu icra edildi
 *   3. Email göndərildi
 *   4. Response qaytarıldı
 *
 * Bu 4 log arasında əlaqə necə qurulur?
 * CORRELATION ID — hər 4 logda eyni olan unikal identifikator.
 *
 * correlation_id olmadan:
 *   1000 istifadəçi eyni anda sorğu göndərir → 4000 log
 *   "User 42-nin sorğusu harada xəta verdi?" → TAP GÖRÜM!
 *
 * correlation_id ilə:
 *   Filtr: correlation_id = "req_abc123" → yalnız 4 log
 *   Tam mənzərəni görürsən — request-dən response-a qədər.
 *
 * ═══════════════════════════════════════════════════════════════
 * ELK STACK (Elasticsearch + Logstash + Kibana)
 * ═══════════════════════════════════════════════════════════════
 *
 * Production-da loglar ELK stack-ə göndərilir:
 *
 * 1. App → JSON log yazır (bu class)
 * 2. Logstash/Filebeat → log faylını oxuyur, Elasticsearch-ə göndərir
 * 3. Elasticsearch → logları indeksləyir (axtarış üçün)
 * 4. Kibana → vizual dashboard göstərir (qrafik, filtr, alert)
 *
 * ALTERNATİVLƏR:
 * - Datadog: SaaS log management (asan quraşdırma, pullu)
 * - Grafana Loki: Prometheus ekosistemi ilə inteqrasiya
 * - AWS CloudWatch: AWS infrastrukturunda
 */
class StructuredLogger
{
    /** Correlation ID — bir sorğunun bütün loglarını birləşdirir */
    private static ?string $correlationId = null;

    /** Bütün loglara əlavə olunan global kontekst */
    private static array $globalContext = [];

    /**
     * Correlation ID təyin et.
     * Adətən middleware-də request başlanğıcında çağırılır.
     *
     * @param string|null $correlationId Əgər null verilsə, avtomatik yaradılır
     */
    public static function setCorrelationId(?string $correlationId = null): void
    {
        // Əgər request header-ində gəlibsə, onu istifadə et (microservice zənciri)
        // Əgər yoxdursa, yeni yarat
        self::$correlationId = $correlationId ?? self::generateId('req');
    }

    public static function getCorrelationId(): string
    {
        if (self::$correlationId === null) {
            self::setCorrelationId();
        }

        return self::$correlationId;
    }

    /**
     * Global kontekst əlavə et — bütün loglar bu məlumatı daşıyacaq.
     *
     * İstifadə nümunəsi (auth middleware-də):
     *   StructuredLogger::addGlobalContext('user_id', $user->id);
     *   StructuredLogger::addGlobalContext('tenant_id', $tenant->id);
     *
     * Bundan sonra BÜTÜN loglarda user_id və tenant_id olacaq.
     */
    public static function addGlobalContext(string $key, mixed $value): void
    {
        self::$globalContext[$key] = $value;
    }

    /**
     * Biznes əməliyyatı logu — ən çox istifadə olunan metod.
     *
     * @param string $event Event adı (dot notation: "order.created", "payment.failed")
     * @param array $context Əlavə məlumat (order_id, user_id, amount, ...)
     * @param string $level Log səviyyəsi (info, warning, error, ...)
     */
    public static function log(string $event, array $context = [], string $level = 'info'): void
    {
        $structuredContext = array_merge(
            [
                'event' => $event,
                'correlation_id' => self::getCorrelationId(),
                'timestamp' => now()->toIso8601String(),
                'environment' => config('app.env'),
                'service' => config('app.name'),
            ],
            self::$globalContext,
            $context,
        );

        Log::channel('structured')->{$level}($event, $structuredContext);
    }

    /**
     * HTTP request logla — middleware-də istifadə olunur.
     */
    public static function logRequest(
        string $method,
        string $uri,
        int $statusCode,
        float $durationMs,
        ?int $userId = null,
    ): void {
        self::log('http.request', [
            'http_method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'duration_ms' => round($durationMs, 2),
            'user_id' => $userId,
        ]);
    }

    /**
     * Database sorğu logla — yavaş sorğu aşkarlama.
     */
    public static function logQuery(string $sql, float $timeMs, string $connection): void
    {
        $level = $timeMs > 100 ? 'warning' : 'debug';

        self::log('db.query', [
            'sql' => $sql,
            'time_ms' => round($timeMs, 2),
            'connection' => $connection,
            'slow' => $timeMs > 100,
        ], $level);
    }

    /**
     * Xarici servis çağırışı logla — 3rd party API monitoring.
     */
    public static function logExternalCall(
        string $service,
        string $endpoint,
        int $statusCode,
        float $durationMs,
        bool $success,
    ): void {
        $level = $success ? 'info' : 'error';

        self::log('external.call', [
            'service' => $service,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'duration_ms' => round($durationMs, 2),
            'success' => $success,
        ], $level);
    }

    /**
     * Təhlükəsizlik hadisəsi logla — audit trail üçün.
     */
    public static function logSecurity(string $action, array $context = []): void
    {
        self::log("security.{$action}", array_merge($context, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]), 'notice');
    }

    /**
     * Konteksti sıfırla — hər request-in sonunda çağırılmalıdır.
     * Middleware-in terminate() metodunda istifadə olunur.
     */
    public static function reset(): void
    {
        self::$correlationId = null;
        self::$globalContext = [];
    }

    private static function generateId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(12));
    }
}
