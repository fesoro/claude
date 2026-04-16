<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Src\Order\Domain\Entities\Order;
use Src\Order\Domain\Repositories\OrderRepositoryInterface;
use Src\Order\Domain\ValueObjects\Address;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Order\Domain\ValueObjects\OrderItem;
use Src\Order\Domain\ValueObjects\OrderStatus;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Infrastructure\Bus\EventDispatcher;

/**
 * ELOQUENT ORDER REPOSITORY (Infrastructure Layer)
 * ==================================================
 * OrderRepositoryInterface-in Laravel Eloquent ilə implementasiyası.
 *
 * INFRASTRUCTURE LAYER NƏDİR?
 * - Domain layer-in interfeyslərini REAL texnologiya ilə implement edir.
 * - Domain "mənə sifarişi ver" deyir, bu class MySQL-dən alır.
 * - Domain MySQL-i bilmir — yalnız interfeysi bilir.
 *
 * NƏYƏ ELOQUENT MODEL İSTİFADƏ ETMİRİK?
 * DDD-də iki yanaşma var:
 * 1. Eloquent Model = Domain Entity (sadə, amma domain təmiz deyil)
 * 2. Ayrı Domain Entity + Eloquent sadəcə persistence üçün (təmiz DDD)
 *
 * Biz 2-ci yanaşmanı istifadə edirik:
 * - Order entity HEÇ BİR framework-dən asılı deyil.
 * - Bu repository Order entity-ni DB-yə yazır/oxuyur (mapper rolunu oynayır).
 * - Laravel dəyişsə belə, Domain layer dəyişmir.
 *
 * EVENT DISPATCH:
 * save() metodu Order-in domain event-lərini pullDomainEvents() ilə alır
 * və EventDispatcher vasitəsilə göndərir.
 */
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    /**
     * Sifarişi ID-sinə görə DB-dən tap.
     *
     * AXIN:
     * 1. Orders cədvəlindən sifarişi tap.
     * 2. Order_items cədvəlindən məhsulları tap.
     * 3. DB datası → Domain Entity-yə çevir (reconstitution).
     */
    public function findById(OrderId $orderId): ?Order
    {
        // DB-dən sifarişi tap
        $row = DB::table('orders')
            ->where('id', $orderId->value())
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->toEntity($row);
    }

    /**
     * Sifarişi DB-yə saxla (yarat və ya yenilə).
     *
     * VACIB: Domain Event-lər save-dən SONRA dispatch olunur.
     * Əgər DB yazması uğursuz olarsa, event-lər göndərilmir.
     * Bu data consistency təmin edir.
     */
    public function save(Order $order): void
    {
        $orderData = [
            'id'           => $order->orderId()->value(),
            'user_id'      => $order->userId(),
            'status'       => $order->status()->value(),
            'total_amount' => $order->totalAmount()->amount(),
            'currency'     => $order->totalAmount()->currency(),
            'street'       => $order->address()->street(),
            'city'         => $order->address()->city(),
            'zip'          => $order->address()->zip(),
            'country'      => $order->address()->country(),
            'created_at'   => $order->createdAt()->format('Y-m-d H:i:s'),
            'updated_at'   => now()->format('Y-m-d H:i:s'),
        ];

        // Upsert — varsa yenilə, yoxdursa yarat
        DB::table('orders')->updateOrInsert(
            ['id' => $order->orderId()->value()],
            $orderData,
        );

        // Sifariş məhsullarını (items) saxla
        // Əvvəlcə köhnə items-ləri sil, sonra yenilərini yaz
        DB::table('order_items')
            ->where('order_id', $order->orderId()->value())
            ->delete();

        foreach ($order->items() as $item) {
            DB::table('order_items')->insert([
                'order_id'   => $order->orderId()->value(),
                'product_id' => $item->productId(),
                'quantity'   => $item->quantity(),
                'price'      => $item->price()->amount(),
                'currency'   => $item->price()->currency(),
            ]);
        }

        // Domain Event-ləri dispatch et
        // pullDomainEvents() event-ləri qaytarır VƏ siyahını təmizləyir
        // dispatch() array gözləyir — tək event deyil, bütün event siyahısını göndəririk
        $events = $order->pullDomainEvents();
        $this->eventDispatcher->dispatch($events);
    }

    /**
     * İstifadəçinin bütün sifarişlərini tap.
     *
     * @return Order[]
     */
    public function findByUserId(string $userId): array
    {
        $rows = DB::table('orders')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->toEntity($row))->all();
    }

    /**
     * DB sətirini Domain Entity-yə çevir (reconstitution/hydration).
     *
     * Bu proses "Object-Relational Mapping" (ORM) adlanır:
     * - DB-dəki düz (flat) data → iç-içə Domain obyektlərə çevrilir.
     * - Value Object-lər yaradılır (OrderId, Address, OrderStatus, Money).
     * - OrderItem-lər ayrı cədvəldən oxunur.
     */
    private function toEntity(object $row): Order
    {
        // Sifarişin məhsullarını (items) oxu
        $itemRows = DB::table('order_items')
            ->where('order_id', $row->id)
            ->get();

        $items = $itemRows->map(fn ($itemRow) => new OrderItem(
            productId: $itemRow->product_id,
            quantity: (int) $itemRow->quantity,
            price: new Money((int) $itemRow->price, $itemRow->currency),
        ))->all();

        // Order entity-ni reconstitute et (event qeydə almadan)
        return Order::reconstitute(
            orderId: OrderId::fromString($row->id),
            userId: $row->user_id,
            address: new Address(
                street: $row->street,
                city: $row->city,
                zip: $row->zip,
                country: $row->country,
            ),
            status: new OrderStatus($row->status),
            totalAmount: new Money((int) $row->total_amount, $row->currency),
            items: $items,
            createdAt: new \DateTimeImmutable($row->created_at),
        );
    }
}
