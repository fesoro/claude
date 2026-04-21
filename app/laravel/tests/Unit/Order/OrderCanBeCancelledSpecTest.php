<?php

declare(strict_types=1);

namespace Tests\Unit\Order;

use PHPUnit\Framework\TestCase;
use Src\Order\Domain\Entities\Order;
use Src\Order\Domain\Specifications\OrderCanBeCancelledSpec;
use Src\Order\Domain\ValueObjects\Address;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Order\Domain\ValueObjects\OrderItem;
use Src\Order\Domain\ValueObjects\OrderStatus;
use Src\Product\Domain\ValueObjects\Money;

/**
 * OrderCanBeCancelledSpec Specification-ının Unit Testləri.
 *
 * Bu specification sifarişin ləğv edilə bilib-bilməyəcəyini yoxlayır.
 * QAYDA: Yalnız PENDING və ya CONFIRMED statusunda ləğv etmək olar.
 *
 * Test etdiyimiz ssenarilar:
 * - PENDING → ləğv edilə bilər (true)
 * - CONFIRMED → ləğv edilə bilər (true)
 * - PAID → ləğv edilə bilməz (false)
 * - SHIPPED → ləğv edilə bilməz (false)
 * - DELIVERED → ləğv edilə bilməz (false)
 * - CANCELLED → artıq ləğv edilib (false)
 * - Order olmayan obyekt → false
 */
class OrderCanBeCancelledSpecTest extends TestCase
{
    /**
     * Specification instansı — hər testdə istifadə olunur.
     */
    private OrderCanBeCancelledSpec $spec;

    /**
     * setUp() — hər testdən əvvəl avtomatik çağırılır.
     * Burada hər test üçün təmiz specification yaradırıq.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->spec = new OrderCanBeCancelledSpec();
    }

    /**
     * PENDING statuslu sifariş ləğv edilə bilər.
     */
    public function test_pending_order_can_be_cancelled(): void
    {
        // Arrange — PENDING statuslu sifariş yaradırıq
        $order = $this->createOrderWithStatus(OrderStatus::PENDING);

        // Act
        $result = $this->spec->isSatisfiedBy($order);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * CONFIRMED statuslu sifariş ləğv edilə bilər.
     */
    public function test_confirmed_order_can_be_cancelled(): void
    {
        // Arrange — CONFIRMED statuslu sifariş
        $order = $this->createOrderWithStatus(OrderStatus::PENDING);
        // PENDING → CONFIRMED keçidi
        $order->confirm();

        // Act
        $result = $this->spec->isSatisfiedBy($order);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * PAID statuslu sifariş ləğv edilə bilməz (refund lazımdır).
     */
    public function test_paid_order_cannot_be_cancelled(): void
    {
        // Arrange — PAID statuslu sifariş
        $order = $this->createOrderWithStatus(OrderStatus::PENDING);
        $order->confirm();    // PENDING → CONFIRMED
        $order->markAsPaid(); // CONFIRMED → PAID

        // Act
        $result = $this->spec->isSatisfiedBy($order);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * SHIPPED statuslu sifariş ləğv edilə bilməz.
     */
    public function test_shipped_order_cannot_be_cancelled(): void
    {
        // Arrange
        $order = $this->createOrderWithStatus(OrderStatus::PENDING);
        $order->confirm();
        $order->markAsPaid();
        $order->ship(); // PAID → SHIPPED

        // Act
        $result = $this->spec->isSatisfiedBy($order);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * DELIVERED statuslu sifariş ləğv edilə bilməz.
     */
    public function test_delivered_order_cannot_be_cancelled(): void
    {
        // Arrange
        $order = $this->createOrderWithStatus(OrderStatus::PENDING);
        $order->confirm();
        $order->markAsPaid();
        $order->ship();
        $order->deliver(); // SHIPPED → DELIVERED

        // Act
        $result = $this->spec->isSatisfiedBy($order);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Artıq CANCELLED olan sifariş yenidən ləğv edilə bilməz.
     */
    public function test_already_cancelled_order_cannot_be_cancelled(): void
    {
        // Arrange
        $order = $this->createOrderWithStatus(OrderStatus::PENDING);
        $order->cancel('Test ləğvi'); // PENDING → CANCELLED

        // Act
        $result = $this->spec->isSatisfiedBy($order);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Order olmayan obyekt ötürüldükdə false qaytarmalıdır.
     * Specification yalnız Order entity-si ilə işləyir.
     */
    public function test_returns_false_for_non_order_candidate(): void
    {
        // Arrange — Order olmayan obyekt
        $notAnOrder = 'not an order';

        // Act
        $result = $this->spec->isSatisfiedBy($notAnOrder);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * null ötürüldükdə false qaytarmalıdır.
     */
    public function test_returns_false_for_null_candidate(): void
    {
        // Act
        $result = $this->spec->isSatisfiedBy(null);

        // Assert
        $this->assertFalse($result);
    }

    // ===========================
    // KÖMƏKÇI METOD
    // ===========================

    /**
     * Verilən statusla test sifarişi yaratmaq üçün köməkçi metod.
     * Order::create() həmişə PENDING statusla yaradır.
     */
    private function createOrderWithStatus(string $status): Order
    {
        // Address Value Object yaradırıq
        $address = new Address(
            street: 'Test küçəsi 1',
            city: 'Bakı',
            zip: 'AZ1000',
            country: 'Azərbaycan',
        );

        // Order yaradırıq — həmişə PENDING statusla başlayır
        return Order::create(
            userId: 'test-user-id',
            address: $address,
        );
    }
}
