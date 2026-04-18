<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * VALID UUID RULE — UUID format validasiyası
 * ==========================================
 * UUID (Universally Unique Identifier) — təkrarolunmaz identifikatordur.
 * Format: 8-4-4-4-12 hex simvollar. Nümunə: 550e8400-e29b-41d4-a716-446655440000
 *
 * DDD-də Entity-lərin ID-si olaraq UUID istifadə olunur (auto-increment əvəzinə).
 * Bu, ID-ni verilənlər bazasından asılı olmadan yaratmağa imkan verir.
 *
 * Laravel-in 'uuid' built-in rule-u da var, amma Custom Rule ilə:
 * - Xəta mesajını öz dilinizdə yaza bilərsiniz
 * - Gələcəkdə UUID versiyasını (v4, v7) yoxlaya bilərsiniz
 * - Layihə boyu vahid standart təmin edirsiniz
 */
final class ValidUuidRule implements ValidationRule
{
    /**
     * UUID v4 regex pattern-i.
     * Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     * Burada x = hex (0-9, a-f), y = 8, 9, a, b
     */
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * UUID formatını yoxla.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail(':attribute düzgün UUID formatında olmalıdır.');
            return;
        }

        if (!preg_match(self::UUID_PATTERN, $value)) {
            $fail(':attribute düzgün UUID formatında olmalıdır. Nümunə: 550e8400-e29b-41d4-a716-446655440000');
        }
    }
}
