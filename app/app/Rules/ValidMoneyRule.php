<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * VALID MONEY RULE — Pul məbləği validasiyası
 * ============================================
 * Bu Custom Rule pul məbləğinin düzgünlüyünü yoxlayır:
 * - Məbləğ müsbət olmalıdır (0-dan böyük)
 * - Valyuta dəstəklənən siyahıda olmalıdır (USD, EUR, AZN)
 *
 * NƏYƏ AYRI RULE?
 * - "numeric|min:0.01" built-in rule-lar ilə məbləği yoxlaya bilərik,
 *   amma valyuta ilə birlikdə yoxlama lazımdırsa, Custom Rule daha təmizdir.
 * - Gələcəkdə valyutaya görə minimum məbləğ əlavə edə bilərik:
 *   məsələn, AZN üçün min 0.01, BTC üçün min 0.00001.
 * - Bir yerdə dəyişiklik etsək, bütün Form Request-lərdə avtomatik tətbiq olunur.
 *
 * İSTİFADƏ NÜMUNƏSİ:
 * 'price' => ['required', 'numeric', new ValidMoneyRule()]
 */
final class ValidMoneyRule implements ValidationRule
{
    /**
     * Dəstəklənən valyutalar.
     * Gələcəkdə domen konfiqurasiyasından və ya verilənlər bazasından gələ bilər.
     */
    private const array SUPPORTED_CURRENCIES = ['USD', 'EUR', 'AZN'];

    /**
     * Constructor vasitəsilə valyutanı ötürə bilərik.
     * Əgər valyuta verilməsə, yalnız məbləğin müsbət olması yoxlanılır.
     *
     * Nümunə: new ValidMoneyRule('USD') — valyuta ilə birlikdə yoxla
     *         new ValidMoneyRule()      — yalnız məbləğ yoxla
     */
    public function __construct(
        private ?string $currency = null,
    ) {}

    /**
     * Pul məbləğini yoxla.
     *
     * Yoxlamalar:
     * 1. Dəyər ədəd olmalıdır (numeric)
     * 2. Məbləğ 0-dan böyük olmalıdır (müsbət)
     * 3. Əgər valyuta verilibsə, dəstəklənən siyahıda olmalıdır
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Ədəd olub-olmadığını yoxla
        if (!is_numeric($value)) {
            $fail(':attribute düzgün pul məbləği olmalıdır.');
            return;
        }

        // Məbləğ müsbət olmalıdır — mənfi və ya sıfır qəbul edilmir
        if ((float) $value <= 0) {
            $fail(':attribute müsbət məbləğ olmalıdır (0-dan böyük).');
            return;
        }

        // Əgər valyuta verilibsə, dəstəklənən siyahıda olub-olmadığını yoxla
        if ($this->currency !== null) {
            $upperCurrency = strtoupper($this->currency);
            if (!in_array($upperCurrency, self::SUPPORTED_CURRENCIES, true)) {
                $fail(
                    ':attribute üçün valyuta dəstəklənmir. '
                    . 'Dəstəklənən valyutalar: ' . implode(', ', self::SUPPORTED_CURRENCIES)
                );
            }
        }
    }
}
