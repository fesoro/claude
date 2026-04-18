<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

/**
 * DOMAIN EXCEPTION
 * ================
 * Biznes qaydalarının pozulması halında atılan xəta.
 *
 * Məsələn:
 * - InsufficientStockException: Stokda kifayət qədər məhsul yoxdur
 * - OrderCannotBeCancelledException: Sifariş artıq göndərilib, ləğv edilə bilməz
 * - InvalidPaymentException: Ödəniş məbləği yanlışdır
 *
 * NƏYƏ LAZIMDIR?
 * - Teknik xətalardan (RuntimeException) fərqləndirir.
 * - API controller-da fərqli HTTP status code qaytarmaq üçün (422 vs 500).
 */
class DomainException extends \DomainException
{
}
