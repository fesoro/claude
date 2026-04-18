<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use Src\User\Domain\ValueObjects\Email;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Email Value Object-in Unit Testləri.
 *
 * Email sinifi self-validating prinsipini tətbiq edir:
 * - Yanlış email formatı ilə obyekt yaratmaq MÜMKÜNSÜZdür.
 * - Email həmişə lowercase-ə çevrilir (case-insensitive).
 * - Başdakı/sondakı boşluqlar silinir (trim).
 *
 * Factory method: Email::fromString() — yeganə yaradılma yolu.
 * Constructor private olduğu üçün birbaşa new Email() yazmaq olmaz.
 */
class EmailValueObjectTest extends TestCase
{
    // ===========================
    // DÜZGÜN EMAİL TESTLƏRİ
    // ===========================

    /**
     * Düzgün email formatı ilə Email obyekti yaradılmalıdır.
     */
    public function test_can_create_email_from_valid_string(): void
    {
        // Arrange & Act
        $email = Email::fromString('user@example.com');

        // Assert
        $this->assertSame('user@example.com', $email->value());
    }

    /**
     * Böyük hərfli email kiçik hərfə çevrilməlidir (case-insensitive).
     */
    public function test_email_is_normalized_to_lowercase(): void
    {
        // Arrange & Act
        $email = Email::fromString('User@EXAMPLE.COM');

        // Assert — hamısı kiçik hərflə olmalıdır
        $this->assertSame('user@example.com', $email->value());
    }

    /**
     * Başdakı və sondakı boşluqlar silinməlidir (trim).
     */
    public function test_email_is_trimmed(): void
    {
        // Arrange & Act
        $email = Email::fromString('  user@example.com  ');

        // Assert
        $this->assertSame('user@example.com', $email->value());
    }

    // ===========================
    // YANLIQ EMAİL TESTLƏRİ
    // ===========================

    /**
     * @ işarəsi olmayan email yanlışdır.
     */
    public function test_throws_exception_for_email_without_at_sign(): void
    {
        // Assert
        $this->expectException(DomainException::class);

        // Act
        Email::fromString('invalid-email');
    }

    /**
     * Boş string email olaraq qəbul edilməməlidir.
     */
    public function test_throws_exception_for_empty_string(): void
    {
        // Assert
        $this->expectException(DomainException::class);

        // Act
        Email::fromString('');
    }

    /**
     * Domen hissəsi olmayan email yanlışdır.
     */
    public function test_throws_exception_for_email_without_domain(): void
    {
        // Assert
        $this->expectException(DomainException::class);

        // Act
        Email::fromString('user@');
    }

    /**
     * Yalnız boşluqlardan ibarət string qəbul edilməməlidir.
     */
    public function test_throws_exception_for_whitespace_only(): void
    {
        // Assert
        $this->expectException(DomainException::class);

        // Act
        Email::fromString('   ');
    }

    // ===========================
    // BƏRABƏRLİK (equals) TESTLƏRİ
    // ===========================

    /**
     * Eyni dəyərli iki Email bərabər olmalıdır (equality by value).
     */
    public function test_equals_returns_true_for_same_email(): void
    {
        // Arrange
        $email1 = Email::fromString('user@example.com');
        $email2 = Email::fromString('user@example.com');

        // Act & Assert
        $this->assertTrue($email1->equals($email2));
    }

    /**
     * Case fərqi olsa belə, normalize edildikdən sonra bərabər olmalıdır.
     */
    public function test_equals_returns_true_for_different_case(): void
    {
        // Arrange
        $email1 = Email::fromString('User@Example.COM');
        $email2 = Email::fromString('user@example.com');

        // Act & Assert — hər ikisi normalize olunub eyni olmalıdır
        $this->assertTrue($email1->equals($email2));
    }

    /**
     * Fərqli email-lər bərabər olmamalıdır.
     */
    public function test_equals_returns_false_for_different_emails(): void
    {
        // Arrange
        $email1 = Email::fromString('user1@example.com');
        $email2 = Email::fromString('user2@example.com');

        // Act & Assert
        $this->assertFalse($email1->equals($email2));
    }

    // ===========================
    // __toString TESTİ
    // ===========================

    /**
     * __toString() metodu email dəyərini qaytarmalıdır.
     */
    public function test_to_string_returns_email_value(): void
    {
        // Arrange
        $email = Email::fromString('user@example.com');

        // Act
        $result = (string) $email;

        // Assert
        $this->assertSame('user@example.com', $result);
    }
}
