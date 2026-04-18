<?php

declare(strict_types=1);

namespace Src\Payment\Infrastructure\CircuitBreaker;

/**
 * Circuit Breaker OPEN state-də olduqda atılan exception.
 * Bu exception çağıran koda bildirir ki, xarici xidmət əlçatmazdır
 * və sorğu xarici API-yə göndərilmədən rədd edildi.
 */
final class CircuitBreakerOpenException extends \RuntimeException
{
}
