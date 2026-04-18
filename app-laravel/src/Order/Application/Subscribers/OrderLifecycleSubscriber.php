<?php

declare(strict_types=1);

namespace Src\Order\Application\Subscribers;

use Illuminate\Support\Facades\Log;
use Src\Order\Domain\Events\OrderCancelledEvent;
use Src\Order\Domain\Events\OrderConfirmedEvent;
use Src\Order\Domain\Events\OrderCreatedEvent;
use Src\Order\Domain\Events\OrderPaidEvent;
use Src\Shared\Infrastructure\Bus\DomainEventSubscriber;

/**
 * ORDER LIFECYCLE SUBSCRIBER — Sifariş Həyat Dövrü İzləyicisi
 * ==============================================================
 *
 * Bu class, DomainEventSubscriber abstract class-ının konkret implementasiyasıdır.
 * Sifarişin bütün həyat dövrü event-lərini BİR class-da dinləyir.
 *
 * NƏYƏ AYRI LISTENER-LƏR DEYİL?
 * Sifariş lifecycle-ı məntiqi bir bütövdür:
 * Created → Confirmed → Paid → Cancelled
 * Bu event-ləri ayrı-ayrı listener-lərdə yazsaq, bir-birilə əlaqəsini görmək çətinləşir.
 * Subscriber hər şeyi bir yerdə saxlayır — "Single Responsibility" amma bütövlük ilə.
 *
 * İSTİFADƏ:
 * OrderServiceProvider-da:
 * $subscriber = $app->make(OrderLifecycleSubscriber::class);
 * $subscriber->registerWith($app->make(EventDispatcher::class));
 */
class OrderLifecycleSubscriber extends DomainEventSubscriber
{
    /**
     * Bu subscriber hansı event-ləri dinləyir?
     * Hər event üçün handler metod adı təyin olunur.
     */
    protected function subscribedEvents(): array
    {
        return [
            OrderCreatedEvent::class   => 'onOrderCreated',
            OrderConfirmedEvent::class => 'onOrderConfirmed',
            OrderPaidEvent::class      => 'onOrderPaid',
            OrderCancelledEvent::class => 'onOrderCancelled',
        ];
    }

    /**
     * Sifariş yaradıldı — audit log yaz + analitikaya göndər.
     */
    protected function onOrderCreated(OrderCreatedEvent $event): void
    {
        Log::info('[OrderLifecycle] Yeni sifariş yaradıldı', [
            'order_id' => $event->orderId(),
            'user_id'  => $event->userId(),
            'stage'    => 'created',
        ]);
    }

    /**
     * Sifariş təsdiqləndi — müştəriyə bildiriş göndər.
     */
    protected function onOrderConfirmed(OrderConfirmedEvent $event): void
    {
        Log::info('[OrderLifecycle] Sifariş təsdiqləndi', [
            'order_id' => $event->orderId(),
            'stage'    => 'confirmed',
        ]);
    }

    /**
     * Sifariş ödənildi — göndəriş prosesini başlat.
     */
    protected function onOrderPaid(OrderPaidEvent $event): void
    {
        Log::info('[OrderLifecycle] Sifariş ödənildi', [
            'order_id'     => $event->orderId(),
            'total_amount' => $event->totalAmount(),
            'stage'        => 'paid',
        ]);
    }

    /**
     * Sifariş ləğv edildi — statistikanı yenilə.
     */
    protected function onOrderCancelled(OrderCancelledEvent $event): void
    {
        Log::info('[OrderLifecycle] Sifariş ləğv edildi', [
            'order_id' => $event->orderId(),
            'reason'   => $event->reason(),
            'stage'    => 'cancelled',
        ]);
    }
}
