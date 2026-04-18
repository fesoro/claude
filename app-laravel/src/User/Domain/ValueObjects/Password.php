<?php

declare(strict_types=1);

namespace Src\User\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * PASSWORD VALUE OBJECT
 * =====================
 * Password — istifadəçinin şifrəsini təmsil edən Value Object-dir.
 *
 * TƏHLÜKƏSİZLİK PRİNSİPLƏRİ:
 * - Şifrə heç vaxt açıq (plain text) saxlanmır — həmişə hash-lənir.
 * - Hash-ləmə üçün bcrypt (password_hash) istifadə olunur.
 * - Şifrənin minimum uzunluğu yoxlanılır (biznes qaydası).
 *
 * İKİ YARADILMA YOLU:
 * 1. fromPlainText() — yeni qeydiyyatda: açıq şifrəni hash-ləyir.
 * 2. fromHash() — verilənlər bazasından oxuyanda: artıq hash-lənmiş şifrəni qəbul edir.
 *
 * NƏYƏ VALUE OBJECT?
 * - Şifrə ilə bağlı bütün qaydalar (uzunluq, hash-ləmə, müqayisə) bir yerdə olur.
 * - string $password əvəzinə Password $password yazmaq daha təhlükəsizdir.
 * - Yanlışlıqla açıq şifrəni bazaya yazmaq mümkünsüz olur.
 */
final class Password extends ValueObject
{
    /**
     * Şifrənin minimum simvol sayı — bu biznes qaydadır (business rule).
     * Biznes qaydaları Domain layer-də olmalıdır.
     */
    private const int MIN_LENGTH = 8;

    /**
     * @var string Hash-lənmiş şifrə — heç vaxt açıq şifrə saxlanmır.
     */
    private string $hashedValue;

    private function __construct(string $hashedValue)
    {
        $this->hashedValue = $hashedValue;
    }

    /**
     * Yeni şifrə yaratmaq üçün — qeydiyyat zamanı istifadə olunur.
     *
     * AXIN:
     * 1. Açıq şifrə gəlir → "MyP@ssw0rd"
     * 2. Uzunluq yoxlanılır → minimum 8 simvol
     * 3. Hash-lənir → "$2y$12$..."
     * 4. Password obyekti yaranır (hash ilə)
     *
     * @throws DomainException Şifrə çox qısa olduqda
     */
    public static function fromPlainText(string $plainText): self
    {
        if (mb_strlen($plainText) < self::MIN_LENGTH) {
            throw new DomainException(
                "Şifrə minimum " . self::MIN_LENGTH . " simvol olmalıdır. "
                . "Cari uzunluq: " . mb_strlen($plainText)
            );
        }

        /**
         * password_hash() — PHP-nin daxili hash-ləmə funksiyasıdır.
         * PASSWORD_BCRYPT — bcrypt alqoritmi istifadə edir.
         * Eyni şifrə hər dəfə fərqli hash verir (salt əlavə edir).
         */
        $hashed = password_hash($plainText, PASSWORD_BCRYPT);

        return new self($hashed);
    }

    /**
     * Verilənlər bazasından oxunan hash-i Password obyektinə çevir.
     *
     * Bu metod validasiya etmir — çünki hash artıq əvvəlcədən yoxlanılıb.
     * Yalnız Infrastructure layer-dən (Repository) çağırılmalıdır.
     */
    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    /**
     * Açıq şifrənin bu hash-ə uyğun olub-olmadığını yoxla.
     * Login zamanı istifadə olunur.
     *
     * password_verify() — hash-lənmiş şifrə ilə açıq şifrəni müqayisə edir.
     */
    public function verify(string $plainText): bool
    {
        return password_verify($plainText, $this->hashedValue);
    }

    /**
     * Hash dəyərini qaytar — yalnız bazaya yazmaq üçün istifadə olunur.
     */
    public function hash(): string
    {
        return $this->hashedValue;
    }

    /**
     * İki Password-u müqayisə et.
     * Hash-lər eyni olmalıdır.
     */
    public function equals(ValueObject $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->hashedValue === $other->hashedValue;
    }

    /**
     * TƏHLÜKƏSİZLİK: __toString heç vaxt hash-i qaytarmır!
     * Log-larda və debug-da şifrə görünməsin deyə gizli saxlayırıq.
     */
    public function __toString(): string
    {
        return '********';
    }
}
