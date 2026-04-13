<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * VALID ORDER STATUS RULE — Sifariş statusu validasiyası
 * =======================================================
 * Bu rule sifariş statusunun icazə verilən dəyərlərdən biri olduğunu yoxlayır.
 *
 * "in:pending,confirmed,..." built-in rule ilə eyni işi görə bilər,
 * amma Custom Rule-un üstünlükləri:
 * - Status siyahısı BİR YERDƏ saxlanılır (Single Source of Truth)
 * - Xəta mesajı daha informativ olur
 * - Gələcəkdə Domain Enum ilə inteqrasiya edə bilərsiniz
 * - State Machine qaydalarını bura əlavə edə bilərsiniz
 *   (məsələn, "cancelled" statusuna yalnız "pending" və ya "confirmed"-dən keçid ola bilər)
 */
final class ValidOrderStatusRule implements ValidationRule
{
    /**
     * İcazə verilən sifariş statusları.
     * Domain Layer-dəki OrderStatus enum ilə sinxron olmalıdır.
     *
     * STATUS STATE MACHINE:
     * pending → confirmed → paid → shipped → delivered
     *                ↓
     *           cancelled (yalnız pending/confirmed-dən)
     */
    private const array VALID_STATUSES = [
        'pending',    // Gözləyir — sifariş yenicə yaradılıb
        'confirmed',  // Təsdiqlənib — operator tərəfindən təsdiq edilib
        'paid',       // Ödənilib — ödəniş uğurla tamamlanıb
        'shipped',    // Göndərilib — kargo şirkətinə verilib
        'delivered',  // Çatdırılıb — müştəri tərəfindən qəbul edilib
        'cancelled',  // Ləğv edilib — sifariş ləğv olunub
    ];

    /**
     * Sifariş statusunu yoxla.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail(':attribute düzgün sifariş statusu olmalıdır.');
            return;
        }

        $status = strtolower($value);

        if (!in_array($status, self::VALID_STATUSES, true)) {
            $fail(
                ':attribute düzgün deyil. İcazə verilən statuslar: '
                . implode(', ', self::VALID_STATUSES)
            );
        }
    }
}
