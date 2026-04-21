<?php

declare(strict_types=1);

namespace Src\User\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * EMAIL VALUE OBJECT
 * ==================
 * Email — istifadəçinin elektron poçt ünvanını təmsil edən Value Object-dir.
 *
 * NƏYƏ VALUE OBJECT?
 * - Email-in öz ID-si yoxdur — dəyərinə görə müəyyən olunur.
 * - "test@mail.com" == "test@mail.com" → həmişə eynidir (equality by value).
 * - Immutable-dir: yaradıldıqdan sonra dəyişdirilə bilməz.
 *
 * NƏYƏ STRING İSTİFADƏ ETMİRİK?
 * - Primitive Obsession anti-pattern-dən qaçırıq.
 * - Əgər hər yerdə string $email yazsaq, validasiya hər yerdə təkrarlanır.
 * - Email Value Object daxilində validasiya BİR DƏFƏ yazılır.
 * - Tip təhlükəsizliyi: funksiya Email $email qəbul edirsə,
 *   ora yanlışlıqla telefon nömrəsi göndərə bilməzsən.
 *
 * SELF-VALIDATING PRİNSİPİ:
 * - Value Object yaradılan anda öz qaydalarını yoxlayır.
 * - Yanlış email ilə Email obyekti yaratmaq MÜMKÜNSÜZdür.
 * - Bu o deməkdir ki, əgər Email obyektin varsa, o MÜTLƏQvaliddir.
 */
final class Email extends ValueObject
{
    /**
     * @var string Email ünvanı — private saxlanılır ki, xaricdən dəyişdirilə bilməsin.
     */
    private string $value;

    /**
     * Constructor private-dir — birbaşa new Email() yazmaq olmaz.
     * Bunun əvəzinə Email::fromString() factory metodu istifadə olunur.
     *
     * NƏYƏ PRİVATE CONSTRUCTOR?
     * - Yaradılma prosesini kontrol edirik.
     * - Validasiya mütləq baş verir — onu bypass etmək mümkün deyil.
     * - Named constructor (fromString) kodu daha oxunaqlı edir.
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * FACTORY METHOD — Email obyekti yaratmağın YEGANƏyolu.
     *
     * Nümunə: $email = Email::fromString('user@example.com');
     *
     * Əgər email yanlışdırsa, DomainException atılır.
     * Bu o deməkdir ki, Email obyekti həmişə valid (düzgün) olacaq.
     */
    public static function fromString(string $email): self
    {
        /**
         * trim() — başdakı və sondakı boşluqları silir.
         * strtolower() — böyük hərfləri kiçik edir (email case-insensitive-dir).
         */
        $email = trim(strtolower($email));

        /**
         * filter_var — PHP-nin daxili email validasiya funksiyasıdır.
         * RFC standartına uyğun yoxlayır.
         */
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException(
                "Yanlış email formatı: {$email}. Düzgün format: user@example.com"
            );
        }

        return new self($email);
    }

    /**
     * Email dəyərini string olaraq qaytar.
     * Getter metodu — dəyəri oxumaq üçün, dəyişmək üçün deyil.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * İki Email Value Object-i müqayisə et.
     *
     * Value Object-lər dəyərlərinə görə müqayisə olunur:
     * Email::fromString('a@b.com')->equals(Email::fromString('a@b.com')) → true
     */
    public function equals(ValueObject $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->value === $other->value;
    }

    /**
     * Email-i string-ə çevir — debug, log və ya ekrana çıxarmaq üçün.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
