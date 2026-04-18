<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use PHPUnit\Framework\TestCase;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Money Value Object-in Unit Testləri.
 *
 * Bu testlər Money sinifinin bütün davranışlarını yoxlayır:
 * - Yaradılma (creation) və validasiya
 * - Toplama (add) və çıxma (subtract) əməliyyatları
 * - Bərabərlik yoxlaması (equals)
 * - Dəyişməzlik (immutability) — əməliyyatlar köhnə obyekti dəyişmir
 * - Yanlış dəyərlərlə işləmə (xəta halları)
 *
 * Verilənlər bazası istifadə olunmur — sırf domain logikası test edilir.
 */
class MoneyValueObjectTest extends TestCase
{
    // ===========================
    // YARADILMA (Creation) TESTLƏRİ
    // ===========================

    /**
     * Düzgün dəyərlərlə Money obyekti yaradılmalıdır.
     */
    public function test_can_create_money_with_valid_amount_and_currency(): void
    {
        // Arrange & Act — Money obyekti yaradırıq
        $money = new Money(1500, 'AZN');

        // Assert — dəyərlərin düzgün saxlanıldığını yoxlayırıq
        $this->assertSame(1500, $money->amount());
        $this->assertSame('AZN', $money->currency());
    }

    /**
     * Sıfır məbləğ ilə yaratmaq mümkün olmalıdır (pulsuz məhsul və ya boş sifariş).
     */
    public function test_can_create_money_with_zero_amount(): void
    {
        // Arrange & Act
        $money = new Money(0, 'USD');

        // Assert
        $this->assertSame(0, $money->amount());
    }

    /**
     * Mənfi məbləğ ilə yaratmaq mümkün olmamalıdır — DomainException atılmalıdır.
     */
    public function test_throws_exception_for_negative_amount(): void
    {
        // Assert — Exception gözləyirik
        $this->expectException(DomainException::class);

        // Act — mənfi məbləğ ilə yaratmağa cəhd
        new Money(-100, 'AZN');
    }

    /**
     * Boş valyuta kodu ilə yaratmaq mümkün olmamalıdır.
     */
    public function test_throws_exception_for_empty_currency(): void
    {
        // Assert
        $this->expectException(DomainException::class);

        // Act
        new Money(100, '');
    }

    // ===========================
    // TOPLAMA (add) TESTLƏRİ
    // ===========================

    /**
     * Eyni valyutada iki məbləği toplamaq düzgün nəticə qaytarmalıdır.
     */
    public function test_add_returns_correct_sum(): void
    {
        // Arrange
        $money1 = new Money(1000, 'AZN'); // 10.00 AZN
        $money2 = new Money(500, 'AZN');  // 5.00 AZN

        // Act
        $result = $money1->add($money2);

        // Assert — 10.00 + 5.00 = 15.00 AZN
        $this->assertSame(1500, $result->amount());
        $this->assertSame('AZN', $result->currency());
    }

    /**
     * Fərqli valyutaları toplamaq mümkün olmamalıdır.
     * Məsələn: 100 AZN + 50 USD = XƏTA
     */
    public function test_add_throws_exception_for_different_currencies(): void
    {
        // Arrange
        $azn = new Money(100, 'AZN');
        $usd = new Money(50, 'USD');

        // Assert
        $this->expectException(DomainException::class);

        // Act
        $azn->add($usd);
    }

    // ===========================
    // ÇIXMA (subtract) TESTLƏRİ
    // ===========================

    /**
     * Eyni valyutada çıxma düzgün nəticə qaytarmalıdır.
     */
    public function test_subtract_returns_correct_difference(): void
    {
        // Arrange
        $money1 = new Money(1500, 'AZN'); // 15.00 AZN
        $money2 = new Money(500, 'AZN');  // 5.00 AZN

        // Act
        $result = $money1->subtract($money2);

        // Assert — 15.00 - 5.00 = 10.00 AZN
        $this->assertSame(1000, $result->amount());
    }

    /**
     * Nəticə mənfi olduqda DomainException atılmalıdır.
     * Məsələn: 5.00 AZN - 10.00 AZN = -5.00 (mümkün deyil)
     */
    public function test_subtract_throws_exception_when_result_is_negative(): void
    {
        // Arrange
        $money1 = new Money(500, 'AZN');
        $money2 = new Money(1000, 'AZN');

        // Assert
        $this->expectException(DomainException::class);

        // Act
        $money1->subtract($money2);
    }

    /**
     * Fərqli valyutalarla çıxma mümkün olmamalıdır.
     */
    public function test_subtract_throws_exception_for_different_currencies(): void
    {
        // Arrange
        $azn = new Money(1000, 'AZN');
        $usd = new Money(500, 'USD');

        // Assert
        $this->expectException(DomainException::class);

        // Act
        $azn->subtract($usd);
    }

    // ===========================
    // BƏRABƏRLİK (equals) TESTLƏRİ
    // ===========================

    /**
     * Eyni məbləğ və valyuta olan iki Money bərabər olmalıdır.
     */
    public function test_equals_returns_true_for_same_amount_and_currency(): void
    {
        // Arrange
        $money1 = new Money(1000, 'AZN');
        $money2 = new Money(1000, 'AZN');

        // Act & Assert
        $this->assertTrue($money1->equals($money2));
    }

    /**
     * Fərqli məbləğ olan iki Money bərabər olmamalıdır.
     */
    public function test_equals_returns_false_for_different_amounts(): void
    {
        // Arrange
        $money1 = new Money(1000, 'AZN');
        $money2 = new Money(2000, 'AZN');

        // Act & Assert
        $this->assertFalse($money1->equals($money2));
    }

    /**
     * Fərqli valyuta olan iki Money bərabər olmamalıdır.
     */
    public function test_equals_returns_false_for_different_currencies(): void
    {
        // Arrange
        $money1 = new Money(1000, 'AZN');
        $money2 = new Money(1000, 'USD');

        // Act & Assert
        $this->assertFalse($money1->equals($money2));
    }

    // =====================================
    // DƏYİŞMƏZLİK (Immutability) TESTLƏRİ
    // =====================================

    /**
     * add() metodu YENİ Money qaytarmalı, köhnəni dəyişməməlidir.
     * Bu immutability prinsipinin əsas testidir.
     */
    public function test_add_does_not_mutate_original_money(): void
    {
        // Arrange
        $original = new Money(1000, 'AZN');

        // Act — toplama əməliyyatı
        $result = $original->add(new Money(500, 'AZN'));

        // Assert — orijinal dəyişməyib, yeni obyekt yaranıb
        $this->assertSame(1000, $original->amount()); // Orijinal eynidir!
        $this->assertSame(1500, $result->amount());   // Yeni nəticə
    }

    /**
     * subtract() metodu da köhnə obyekti dəyişməməlidir.
     */
    public function test_subtract_does_not_mutate_original_money(): void
    {
        // Arrange
        $original = new Money(1500, 'AZN');

        // Act
        $result = $original->subtract(new Money(500, 'AZN'));

        // Assert — orijinal dəyişməyib
        $this->assertSame(1500, $original->amount());
        $this->assertSame(1000, $result->amount());
    }

    // ===========================
    // __toString TESTİ
    // ===========================

    /**
     * __toString() metodu qəpikləri manata çevirib düzgün format qaytarmalıdır.
     */
    public function test_to_string_formats_correctly(): void
    {
        // Arrange
        $money = new Money(1050, 'AZN');

        // Act
        $result = (string) $money;

        // Assert — 1050 qəpik = "10.50 AZN"
        $this->assertSame('10.50 AZN', $result);
    }
}
