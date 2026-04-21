<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Locking;

use RuntimeException;

/**
 * Lock əldə edilə bilmədikdə atılan exception.
 *
 * Bu exception distributed lock timeout olduqda yaranır:
 * - Başqa proses lock-u saxlayır və vaxtında buraxmır
 * - Maksimum retry sayı aşılıb
 *
 * Handle etmə strategiyaları:
 * 1. Controller-də tutub 409 Conflict qaytarmaq
 * 2. Queue job-da tutub retry etmək
 * 3. Middleware-də tutub "xahiş edin gözləyin" cavabı qaytarmaq
 */
class LockNotAcquiredException extends RuntimeException
{
}
