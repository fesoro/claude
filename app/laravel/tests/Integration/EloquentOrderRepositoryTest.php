<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Order\Domain\Entities\Order;
use Src\Order\Domain\ValueObjects\Address;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Order\Domain\ValueObjects\OrderItem;
use Src\Order\Infrastructure\Repositories\EloquentOrderRepository;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Infrastructure\Bus\EventDispatcher;
use Tests\TestCase;

/**
 * ELOQUENT ORDER REPOSITORY İNTEQRASİYA TESTLƏRİ
 * ================================================
 * Bu testlər EloquentOrderRepository-nin real verilənlər bazası ilə düzgün işlədiyini yoxlayır.
 *
 * Yoxlananlar:
 * - save() + findById() round-trip
 * - addItem() ilə total məbləğin hesablanması
 * - Status dəyişikliyi (confirm, cancel) persist olunur
 * - findByUserId() siyahı qaytarır
 * - Mövcud olmayan ID üçün null qaytarılır
 *
 * RefreshDatabase trait hər testdən əvvəl bazanı təmizləyir.
 */
class EloquentOrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentOrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentOrderRepository(new EventDispatcher());
    }

    private function makeAddress(): Address
    {
        return new Address('İstiqlaliyyət 5', 'Bakı', 'AZ1000', 'AZ');
    }

    private function makeItem(float $price = 29.99, int $qty = 1): OrderItem
    {
        return new OrderItem(
            productId: fake()->uuid(),
            quantity: $qty,
            price: new Money($price, 'AZN'),
        );
    }

    // ============================================================
    // save() + findById() TESTLƏR
    // ============================================================

    public function test_save_persists_new_order_to_database(): void
    {
        $userId = fake()->uuid();
        $order = Order::create($userId, $this->makeAddress());
        $order->addItem($this->makeItem(50.00));

        $this->repository->save($order);

        $found = $this->repository->findById($order->orderId());
        $this->assertNotNull($found);
        $this->assertEquals($userId, $found->userId());
        $this->assertTrue($found->status()->isPending());
        $this->assertCount(1, $found->items());
    }

    public function test_total_amount_is_calculated_correctly(): void
    {
        $order = Order::create(fake()->uuid(), $this->makeAddress());
        $order->addItem($this->makeItem(100.00, 2)); // 2 × 100 = 200
        $order->addItem($this->makeItem(50.00, 1));  // 50

        $this->repository->save($order);

        $found = $this->repository->findById($order->orderId());
        $this->assertNotNull($found);
        $this->assertEquals(250.00, $found->totalAmount()->amount());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $nonExistentId = OrderId::generate();
        $found = $this->repository->findById($nonExistentId);
        $this->assertNull($found);
    }

    // ============================================================
    // STATUS KEÇID TESTLƏRİ
    // ============================================================

    public function test_confirm_status_is_persisted(): void
    {
        $order = Order::create(fake()->uuid(), $this->makeAddress());
        $order->addItem($this->makeItem());
        $this->repository->save($order);

        $order->confirm();
        $this->repository->save($order);

        $found = $this->repository->findById($order->orderId());
        $this->assertNotNull($found);
        $this->assertTrue($found->status()->isConfirmed());
    }

    public function test_cancel_status_is_persisted(): void
    {
        $order = Order::create(fake()->uuid(), $this->makeAddress());
        $order->addItem($this->makeItem());
        $this->repository->save($order);

        $order->cancel('Müştəri ləğv etdi');
        $this->repository->save($order);

        $found = $this->repository->findById($order->orderId());
        $this->assertNotNull($found);
        $this->assertTrue($found->status()->isCancelled());
    }

    // ============================================================
    // findByUserId() TESTLƏRİ
    // ============================================================

    public function test_find_by_user_id_returns_all_user_orders(): void
    {
        $userId = fake()->uuid();

        $order1 = Order::create($userId, $this->makeAddress());
        $order1->addItem($this->makeItem());
        $this->repository->save($order1);

        $order2 = Order::create($userId, $this->makeAddress());
        $order2->addItem($this->makeItem(99.99));
        $this->repository->save($order2);

        // Başqa user-in sifarişi — filtrdə görünməməlidir
        $other = Order::create(fake()->uuid(), $this->makeAddress());
        $other->addItem($this->makeItem());
        $this->repository->save($other);

        $orders = $this->repository->findByUserId($userId);
        $this->assertCount(2, $orders);
        foreach ($orders as $order) {
            $this->assertInstanceOf(Order::class, $order);
            $this->assertEquals($userId, $order->userId());
        }
    }

    public function test_find_by_user_id_returns_empty_array_when_no_orders(): void
    {
        $orders = $this->repository->findByUserId(fake()->uuid());
        $this->assertIsArray($orders);
        $this->assertEmpty($orders);
    }
}
