<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CUSTOM RULE NƏDİR?
 * ===================
 * Laravel-də validation rule-lar (qaydalar) iki cür olur:
 *
 * 1. DAXİLİ RULE-LAR (built-in):
 *    'email', 'required', 'min:8', 'unique:users,email' kimi hazır qaydalar.
 *    Bunlar əksər hallarda kifayətdir.
 *
 * 2. CUSTOM RULE-LAR (xüsusi qaydalar):
 *    Öz biznes logikanıza uyğun qaydalar yaratmaq lazım olanda istifadə olunur.
 *    Məsələn: domen səviyyəsindəki validasiya (Domain Value Object ilə eyni logika).
 *
 * NƏ VAXT CUSTOM RULE YAZMALIYIQ?
 * - Built-in rule-lar kifayət etmədikdə (məsələn, xüsusi format yoxlaması).
 * - Eyni validasiya bir neçə yerdə təkrarlananda (DRY prinsipi).
 * - Domain Layer-dəki validasiya ilə HTTP Layer-dəki validasiya sinxron olmalıdırsa.
 * - Kompleks biznes qaydası varsa (məsələn, "AZN valyutasında minimum 1 manat").
 *
 * ValidationRule İNTERFEYSİ NECƏ İŞLƏYİR?
 * =========================================
 * Laravel 10+ versiyasında ValidationRule interfeysi istifadə olunur.
 * Bu interfeys yalnız BİR metod tələb edir: validate()
 *
 * validate() metodu 3 parametr alır:
 * - $attribute: validasiya olunan sahənin adı (məsələn, "email")
 * - $value: sahənin dəyəri (məsələn, "user@example.com")
 * - $fail: xəta mesajı göndərmək üçün Closure — $fail('Xəta mesajı') çağırılır
 *
 * Əgər $fail çağırılmasa — validasiya uğurludur.
 * Əgər $fail('mesaj') çağırılsa — validasiya uğursuzdur və həmin mesaj qaytarılır.
 *
 * BU RULE NƏ EDİR?
 * =================
 * Domain Layer-dəki Email Value Object (Src\User\Domain\ValueObjects\Email) ilə
 * eyni validasiya logikasını HTTP Layer-də tətbiq edir.
 * Beləliklə, yanlış email heç vaxt Domain Layer-ə çatmır — erkən tutulur.
 *
 * Bu yanaşmanın adı "Fail Fast" — xətanı mümkün qədər tez tutmaq.
 * Əvvəlcə HTTP Layer-də (Form Request + Custom Rule) yoxla,
 * sonra Domain Layer-də (Value Object) yenidən yoxla.
 * Belə iki qat qoruma (defense in depth) etibarlı sistem qurur.
 */
final class ValidEmailRule implements ValidationRule
{
    /**
     * Validasiya metodunu icra et.
     *
     * Bu metod Email Value Object-dəki eyni logikani istifadə edir:
     * 1. Trim — baş və sondakı boşluqları sil
     * 2. Lowercase — kiçik hərflərə çevir
     * 3. filter_var — RFC standartına uyğun yoxla
     *
     * @param string $attribute — sahənin adı (məsələn, "email")
     * @param mixed  $value     — sahənin dəyəri
     * @param Closure $fail     — xəta mesajı göndərmək üçün callback
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Əgər dəyər string deyilsə, email ola bilməz
        if (!is_string($value)) {
            $fail(':attribute düzgün email formatında olmalıdır.');
            return;
        }

        // Domain Value Object ilə eyni logika:
        // trim() — boşluqları sil, strtolower() — kiçik hərflərə çevir
        $email = trim(strtolower($value));

        // filter_var — PHP-nin daxili RFC email validasiyası
        // Email Value Object-də (Src\User\Domain\ValueObjects\Email) eyni yoxlama var
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail(':attribute düzgün email formatında olmalıdır. Nümunə: user@example.com');
        }
    }
}
