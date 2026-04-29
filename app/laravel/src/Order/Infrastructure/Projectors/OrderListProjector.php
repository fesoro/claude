<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Projectors;

use Illuminate\Support\Facades\DB;
use Src\Order\Domain\Events\OrderCancelledEvent;
use Src\Order\Domain\Events\OrderConfirmedEvent;
use Src\Order\Domain\Events\OrderCreatedEvent;
use Src\Order\Domain\Events\OrderItemAddedEvent;
use Src\Order\Domain\Events\OrderPaidEvent;
use Src\Shared\Infrastructure\EventSourcing\Projector;

/**
 * ORDER LIST PROJECTOR — Sifariş Siyahısı Proyeksiyası
 * ======================================================
 *
 * Bu, Projector abstract class-ının konkret implementasiyasıdır.
 * Order event-lərini dinləyib `order_read_models` cədvəlini yeniləyir.
 *
 * HƏR EVENT ÜÇÜN NƏ BAŞ VERİR:
 * ==============================
 * OrderCreated  → INSERT: yeni sətir yaradılır (status: pending, total: 0)
 * ItemAdded     → UPDATE: total artırılır, item_count artırılır
 * Confirmed     → UPDATE: status → confirmed
 * Paid          → UPDATE: status → paid
 * Cancelled     → UPDATE: status → cancelled
 *
 * Bu proyeksiya "UI siyahısı" üçündür — minimal data, sürətli sorğu.
 * Dashboard statistikası üçün ayrı OrderStatisticsProjector yaradıla bilər.
 */
class OrderListProjector extends Projector
{
    protected function eventHandlerMap(): array
    {
        return [
            OrderCreatedEvent::class   => 'onOrderCreated',
            OrderItemAddedEvent::class => 'onItemAdded',
            OrderConfirmedEvent::class => 'onOrderConfirmed',
            OrderPaidEvent::class      => 'onOrderPaid',
            OrderCancelledEvent::class => 'onOrderCancelled',
        ];
    }

    protected function tableName(): string
    {
        return 'order_read_models';
    }

    /**
     * Yeni sifariş yaradıldı — read model-ə ilkin sətir əlavə et.
     */
    protected function onOrderCreated(OrderCreatedEvent $event): void
    {
        DB::connection($this->connection())->table($this->tableName())->updateOrInsert(
            ['order_id' => $event->orderId()],
            [
                'user_id'      => $event->userId(),
                'status'       => 'pending',
                'total_amount' => 0,
                'item_count'   => 0,
                'currency'     => 'AZN',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        );
    }

    /**
     * Məhsul əlavə olundu — total və item_count artır.
     *
     * updateOrInsert deyil, UPDATE istifadə edirik çünki OrderCreated əvvəl gəlməlidir.
     * Əgər sətir yoxdursa — bu, event sırası pozulub deməkdir (log et, ignore et).
     */
    protected function onItemAdded(OrderItemAddedEvent $event): void
    {
        $lineTotal = (int) ($event->priceAmount() * $event->quantity());

        // increment() — tam ədəd artım üçün Laravel-in parametrized metodu
        DB::connection($this->connection())->table($this->tableName())
            ->where('order_id', $event->orderId())
            ->increment('total_amount', $lineTotal, [
                'item_count' => DB::raw('item_count + 1'),
                'currency'   => $event->priceCurrency(),
                'updated_at' => now(),
            ]);
    }

    protected function onOrderConfirmed(OrderConfirmedEvent $event): void
    {
        $this->updateStatus($event->orderId(), 'confirmed');
    }

    protected function onOrderPaid(OrderPaidEvent $event): void
    {
        $this->updateStatus($event->orderId(), 'paid');
    }

    protected function onOrderCancelled(OrderCancelledEvent $event): void
    {
        $this->updateStatus($event->orderId(), 'cancelled');
    }

    private function updateStatus(string $orderId, string $status): void
    {
        DB::connection($this->connection())->table($this->tableName())
            ->where('order_id', $orderId)
            ->update([
                'status'     => $status,
                'updated_at' => now(),
            ]);
    }
}
