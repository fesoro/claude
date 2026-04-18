<?php

/**
 * CIRCUIT BREAKER KONFİQURASİYASI
 * ================================
 * Circuit Breaker pattern-in parametrləri.
 *
 * failure_threshold: Bu qədər ardıcıl uğursuz cəhddən sonra circuit "açılır" (OPEN).
 * recovery_timeout:  Circuit açıldıqdan sonra bu qədər saniyə gözlənir, sonra HALF_OPEN olur.
 * retry_attempts:    Uğursuz əməliyyat neçə dəfə yenidən cəhd edilir.
 * retry_delay_ms:    Hər retry arasındakı gözləmə (millisaniyə), exponential artır.
 */
return [
    'failure_threshold' => (int) env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
    'recovery_timeout' => (int) env('CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 30),
    'retry_attempts' => (int) env('RETRY_ATTEMPTS', 3),
    'retry_delay_ms' => (int) env('RETRY_DELAY_MS', 1000),
];
