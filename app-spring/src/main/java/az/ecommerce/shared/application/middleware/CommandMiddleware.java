package az.ecommerce.shared.application.middleware;

import az.ecommerce.shared.application.bus.Command;

/**
 * Laravel: src/Shared/Application/Middleware/Middleware.php
 * Spring: pipeline pattern (oxşar Laravel middleware-ə).
 *
 * Sıra (Laravel-dəki kimi):
 *   Logging → Idempotency → Validation → Transaction → RetryOnConcurrency → Handler
 */
public interface CommandMiddleware {

    <R> R handle(Command<R> command, CommandPipeline<R> next);

    /**
     * @Order qiyməti — daha kiçik = daha əvvəl çağrılır.
     * Default sıra:
     *   10 — Logging
     *   20 — Idempotency
     *   30 — Validation
     *   40 — Transaction
     *   50 — RetryOnConcurrency
     */
    int order();
}
