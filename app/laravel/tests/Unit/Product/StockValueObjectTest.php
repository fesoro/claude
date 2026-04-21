<?php

declare(strict_types=1);

namespace Tests\Unit\Product;

use PHPUnit\Framework\TestCase;
use Src\Product\Domain\ValueObjects\Stock;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Stock Value Object-in Unit Testləri.
 *
 * Stock — anbardakı məhsul miqdarını təmsil edir.
 * Biznes qaydası: stok mənfi ola bilməz, azaltma və artırma əməliyyatları
 * yeni Stock qaytarır (immutability).
 */
class StockValueObjectTest extends TestCase
{
    // ===========================
    // YARADILMA TESTLƏRİ
    // ===========================

    /**
     * Düzgün miqdarla Stock yaradılmalıdır.
     */
    public function test_can_create_stock_with_valid_quantity(): void
    {
        // Arrange & Act
        $stock = new Stock(50);

        // Assert
        $this->assertSame(50, $stock->quantity());
    }

    /**
     * Sıfır miqdarla yaratmaq mümkündür (stok tükənib).
     */
    public function test_can_create_stock_with_zero_quantity(): void
    {
        // Arrange & Act
        $stock = new Stock(0);

        // Assert
        $this->assertSame(0, $stock->quantity());
        $this->assertTrue($stock->isEmpty());
    }

    /**
     * Mənfi miqdarla yaratmaq mümkün deyil — DomainException atılır.
     */
    public function test_throws_exception_for_negative_quantity(): void
    {
        // Assert
        $this->expectException(DomainException::class);

        // Act
        new Stock(-1);
    }

    // ===========================
    // ARTIRMA (increase) TESTLƏRİ
    // ===========================

    /**
     * increase() düzgün nəticə qaytarmalıdır.
     */
    public function test_increase_returns_stock_with_added_quantity(): void
    {
        // Arrange
        $stock = new Stock(10);

        // Act
        $result = $stock->increase(5);

        // Assert
        $this->assertSame(15, $result->quantity());
    }

    /**
     * increase() orijinal obyekti dəyişməməlidir (immutability).
     */
    public function test_increase_does_not_mutate_original(): void
    {
        // Arrange
        $original = new Stock(10);

        // Act
        $original->increase(5);

        // Assert — orijinal dəyişməyib
        $this->assertSame(10, $original->quantity());
    }

    /**
     * Sıfır və ya mənfi miqdarla artırmaq mümkün deyil.
     */
    public function test_increase_throws_exception_for_zero_amount(): void
    {
        // Arrange
        $stock = new Stock(10);

        // Assert
        $this->expectException(DomainException::class);

        // Act
        $stock->increase(0);
    }

    /**
     * Mənfi miqdarla artırmaq mümkün deyil.
     */
    public function test_increase_throws_exception_for_negative_amount(): void
    {
        // Arrange
        $stock = new Stock(10);

        // Assert
        $this->expectException(DomainException::class);

        // Act
        $stock->increase(-5);
    }

    // ===========================
    // AZALTMA (decrease) TESTLƏRİ
    // ===========================

    /**
     * decrease() düzgün nəticə qaytarmalıdır.
     */
    public function test_decrease_returns_stock_with_reduced_quantity(): void
    {
        // Arrange
        $stock = new Stock(10);

        // Act
        $result = $stock->decrease(3);

        // Assert
        $this->assertSame(7, $result->quantity());
    }

    /**
     * decrease() orijinal obyekti dəyişməməlidir (immutability).
     */
    public function test_decrease_does_not_mutate_original(): void
    {
        // Arrange
        $original = new Stock(10);

        // Act
        $original->decrease(3);

        // Assert
        $this->assertSame(10, $original->quantity());
    }

    /**
     * Stokdan çox azaltmaq mümkün deyil — DomainException atılır.
     * Məsələn: 5 ədəd varsa, 10 ədəd azaltmaq olmaz.
     */
    public function test_decrease_throws_exception_when_insufficient_stock(): void
    {
        // Arrange
        $stock = new Stock(5);

        // Assert
        $this->expectException(DomainException::class);

        // Act — stokda olan miqdardan çox azaltmağa cəhd
        $stock->decrease(10);
    }

    /**
     * Sıfır miqdarla azaltmaq mümkün deyil.
     */
    public function test_decrease_throws_exception_for_zero_amount(): void
    {
        // Arrange
        $stock = new Stock(10);

        // Assert
        $this->expectException(DomainException::class);

        // Act
        $stock->decrease(0);
    }

    /**
     * Mənfi miqdarla azaltmaq mümkün deyil.
     */
    public function test_decrease_throws_exception_for_negative_amount(): void
    {
        // Arrange
        $stock = new Stock(10);

        // Assert
        $this->expectException(DomainException::class);

        // Act
        $stock->decrease(-5);
    }

    /**
     * Tam miqdarı azaltmaq stoku sıfıra endirməlidir.
     */
    public function test_decrease_to_zero_is_allowed(): void
    {
        // Arrange
        $stock = new Stock(5);

        // Act
        $result = $stock->decrease(5);

        // Assert
        $this->assertSame(0, $result->quantity());
        $this->assertTrue($result->isEmpty());
    }

    // ===========================
    // isEmpty() TESTLƏRİ
    // ===========================

    /**
     * Sıfır miqdar üçün isEmpty() true qaytarmalıdır.
     */
    public function test_is_empty_returns_true_for_zero_stock(): void
    {
        // Arrange & Act
        $stock = new Stock(0);

        // Assert
        $this->assertTrue($stock->isEmpty());
    }

    /**
     * Müsbət miqdar üçün isEmpty() false qaytarmalıdır.
     */
    public function test_is_empty_returns_false_for_positive_stock(): void
    {
        // Arrange & Act
        $stock = new Stock(1);

        // Assert
        $this->assertFalse($stock->isEmpty());
    }

    // ===========================
    // BƏRABƏRLİK (equals) TESTİ
    // ===========================

    /**
     * Eyni miqdar olan iki Stock bərabər olmalıdır.
     */
    public function test_equals_returns_true_for_same_quantity(): void
    {
        // Arrange
        $stock1 = new Stock(10);
        $stock2 = new Stock(10);

        // Act & Assert
        $this->assertTrue($stock1->equals($stock2));
    }

    /**
     * Fərqli miqdar olan iki Stock bərabər olmamalıdır.
     */
    public function test_equals_returns_false_for_different_quantity(): void
    {
        // Arrange
        $stock1 = new Stock(10);
        $stock2 = new Stock(20);

        // Act & Assert
        $this->assertFalse($stock1->equals($stock2));
    }
}
