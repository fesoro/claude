<?php

declare(strict_types=1);

namespace Tests\Unit\Order;

use PHPUnit\Framework\TestCase;
use Src\Order\Domain\ValueObjects\OrderStatus;

/**
 * OrderStatus Value Object-in Unit Testləri.
 *
 * OrderStatus State Machine pattern-i tətbiq edir:
 * - Hər statusdan yalnız müəyyən statuslara keçmək mümkündür.
 * - Yanlış keçidlər bloklanır (canTransitionTo).
 *
 * State Diaqramı:
 * PENDING → CONFIRMED → PAID → SHIPPED → DELIVERED
 * PENDING → CANCELLED
 * CONFIRMED → CANCELLED
 * DELIVERED → (heç yerə — son vəziyyət)
 * CANCELLED → (heç yerə — son vəziyyət)
 */
class OrderStatusTest extends TestCase
{
    // ===========================
    // YARADILMA TESTLƏRİ
    // ===========================

    /**
     * Factory method-lar ilə düzgün status yaradılmalıdır.
     */
    public function test_can_create_all_valid_statuses(): void
    {
        // Act & Assert — hər factory method düzgün status qaytarmalıdır
        $this->assertSame('pending', OrderStatus::pending()->value());
        $this->assertSame('confirmed', OrderStatus::confirmed()->value());
        $this->assertSame('paid', OrderStatus::paid()->value());
        $this->assertSame('shipped', OrderStatus::shipped()->value());
        $this->assertSame('delivered', OrderStatus::delivered()->value());
        $this->assertSame('cancelled', OrderStatus::cancelled()->value());
    }

    /**
     * Yanlış status dəyəri ilə yaratmaq mümkün olmamalıdır.
     */
    public function test_throws_exception_for_invalid_status(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act — mövcud olmayan status
        new OrderStatus('invalid_status');
    }

    // ============================================
    // DÜZGÜN KEÇİDLƏR (Valid Transitions) TESTLƏRİ
    // ============================================

    /**
     * PENDING → CONFIRMED keçidi mümkündür.
     */
    public function test_pending_can_transition_to_confirmed(): void
    {
        // Arrange
        $pending = OrderStatus::pending();

        // Act & Assert
        $this->assertTrue($pending->canTransitionTo(OrderStatus::confirmed()));
    }

    /**
     * PENDING → CANCELLED keçidi mümkündür.
     */
    public function test_pending_can_transition_to_cancelled(): void
    {
        // Arrange
        $pending = OrderStatus::pending();

        // Act & Assert
        $this->assertTrue($pending->canTransitionTo(OrderStatus::cancelled()));
    }

    /**
     * CONFIRMED → PAID keçidi mümkündür.
     */
    public function test_confirmed_can_transition_to_paid(): void
    {
        // Arrange
        $confirmed = OrderStatus::confirmed();

        // Act & Assert
        $this->assertTrue($confirmed->canTransitionTo(OrderStatus::paid()));
    }

    /**
     * CONFIRMED → CANCELLED keçidi mümkündür.
     */
    public function test_confirmed_can_transition_to_cancelled(): void
    {
        // Arrange
        $confirmed = OrderStatus::confirmed();

        // Act & Assert
        $this->assertTrue($confirmed->canTransitionTo(OrderStatus::cancelled()));
    }

    /**
     * PAID → SHIPPED keçidi mümkündür.
     */
    public function test_paid_can_transition_to_shipped(): void
    {
        // Arrange
        $paid = OrderStatus::paid();

        // Act & Assert
        $this->assertTrue($paid->canTransitionTo(OrderStatus::shipped()));
    }

    /**
     * SHIPPED → DELIVERED keçidi mümkündür.
     */
    public function test_shipped_can_transition_to_delivered(): void
    {
        // Arrange
        $shipped = OrderStatus::shipped();

        // Act & Assert
        $this->assertTrue($shipped->canTransitionTo(OrderStatus::delivered()));
    }

    // ============================================
    // YANLIQ KEÇİDLƏR (Invalid Transitions) TESTLƏRİ
    // ============================================

    /**
     * PENDING → PAID birbaşa keçid mümkün deyil (əvvəlcə CONFIRMED olmalıdır).
     */
    public function test_pending_cannot_transition_to_paid(): void
    {
        // Arrange
        $pending = OrderStatus::pending();

        // Act & Assert
        $this->assertFalse($pending->canTransitionTo(OrderStatus::paid()));
    }

    /**
     * PENDING → DELIVERED birbaşa keçid mümkün deyil.
     */
    public function test_pending_cannot_transition_to_delivered(): void
    {
        // Arrange
        $pending = OrderStatus::pending();

        // Act & Assert
        $this->assertFalse($pending->canTransitionTo(OrderStatus::delivered()));
    }

    /**
     * PAID → CANCELLED keçid mümkün deyil (ödəniş edildikdən sonra ləğv olmaz).
     */
    public function test_paid_cannot_transition_to_cancelled(): void
    {
        // Arrange
        $paid = OrderStatus::paid();

        // Act & Assert
        $this->assertFalse($paid->canTransitionTo(OrderStatus::cancelled()));
    }

    /**
     * DELIVERED son vəziyyətdir — heç yerə keçid mümkün deyil.
     */
    public function test_delivered_cannot_transition_to_any_status(): void
    {
        // Arrange
        $delivered = OrderStatus::delivered();

        // Act & Assert — heç bir statusa keçmək olmaz
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::pending()));
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::confirmed()));
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::paid()));
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::shipped()));
        $this->assertFalse($delivered->canTransitionTo(OrderStatus::cancelled()));
    }

    /**
     * CANCELLED son vəziyyətdir — heç yerə keçid mümkün deyil.
     */
    public function test_cancelled_cannot_transition_to_any_status(): void
    {
        // Arrange
        $cancelled = OrderStatus::cancelled();

        // Act & Assert
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::pending()));
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::confirmed()));
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::paid()));
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::shipped()));
        $this->assertFalse($cancelled->canTransitionTo(OrderStatus::delivered()));
    }

    // ===========================
    // STATUS YOXLAMA TESTLƏRİ
    // ===========================

    /**
     * isPending() metodu yalnız PENDING statusda true qaytarmalıdır.
     */
    public function test_is_pending_returns_true_only_for_pending(): void
    {
        // Act & Assert
        $this->assertTrue(OrderStatus::pending()->isPending());
        $this->assertFalse(OrderStatus::confirmed()->isPending());
        $this->assertFalse(OrderStatus::cancelled()->isPending());
    }

    /**
     * isCancelled() metodu yalnız CANCELLED statusda true qaytarmalıdır.
     */
    public function test_is_cancelled_returns_true_only_for_cancelled(): void
    {
        // Act & Assert
        $this->assertTrue(OrderStatus::cancelled()->isCancelled());
        $this->assertFalse(OrderStatus::pending()->isCancelled());
    }

    // ===========================
    // BƏRABƏRLİK TESTİ
    // ===========================

    /**
     * Eyni status olan iki OrderStatus bərabər olmalıdır.
     */
    public function test_equals_returns_true_for_same_status(): void
    {
        // Arrange
        $status1 = OrderStatus::pending();
        $status2 = OrderStatus::pending();

        // Act & Assert
        $this->assertTrue($status1->equals($status2));
    }

    /**
     * Fərqli status olan iki OrderStatus bərabər olmamalıdır.
     */
    public function test_equals_returns_false_for_different_status(): void
    {
        // Arrange
        $pending = OrderStatus::pending();
        $confirmed = OrderStatus::confirmed();

        // Act & Assert
        $this->assertFalse($pending->equals($confirmed));
    }

    // ===========================
    // __toString TESTİ
    // ===========================

    /**
     * __toString() status dəyərini qaytarmalıdır.
     */
    public function test_to_string_returns_status_value(): void
    {
        // Act & Assert
        $this->assertSame('pending', (string) OrderStatus::pending());
        $this->assertSame('cancelled', (string) OrderStatus::cancelled());
    }
}
