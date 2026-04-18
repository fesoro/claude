<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Bus;

use Illuminate\Support\Facades\Log;

/**
 * DOMAIN EVENT SUBSCRIBER — Event Routing Sistemi
 * ==================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Laravel-in Event::listen() sistemi sadə event→listener bağlama edir.
 * Amma DDD-də bir class birdən çox event-i dinləmək istəyə bilər.
 *
 * Laravel yanaşması (cari):
 * Event::listen(OrderCreatedEvent::class, OrderCreatedListener::class);
 * Event::listen(OrderPaidEvent::class, OrderPaidListener::class);
 * Event::listen(OrderCancelledEvent::class, OrderCancelledListener::class);
 * → 3 ayrı listener class yaratmalısan. Hər biri çox kiçikdir. Kod dağılır.
 *
 * Subscriber yanaşması:
 * class OrderProcessSubscriber implements DomainEventSubscriberInterface {
 *     subscribedEvents() → [OrderCreated, OrderPaid, OrderCancelled]
 *     onOrderCreated($event) { ... }
 *     onOrderPaid($event) { ... }
 *     onOrderCancelled($event) { ... }
 * }
 * → Bütün əlaqəli event-lər BİR class-da. Kontekst saxlanılır.
 *
 * SUBSCRIBER vs LISTENER FƏRQI:
 * ==============================
 * Listener: BİR event → BİR handler. Tək event-ə fokuslanır.
 * Subscriber: BİR NEÇƏ event → BİR class. Əlaqəli event-ləri qruplaşdırır.
 *
 * NƏ VAXT SUBSCRIBER İSTİFADƏ ETMƏK?
 * - Bir neçə event arasında shared state lazımdırsa.
 * - Event-lər məntiqi olaraq birlikdədirsə (bir aggregate-in event-ləri).
 * - Process Manager kimi çox-event-li komponentlər üçün.
 *
 * NƏ VAXT LISTENER İSTİFADƏ ETMƏK?
 * - Event-lər müstəqildirsə.
 * - Sadə, bir əməliyyatlıq reaksiyalar üçün.
 *
 * ANALOGİYA:
 * ===========
 * Listener = Siren — yalnız yangın alarmına reaksiya verir.
 * Subscriber = Mühafizəçi — yangın, oğurluq, zəlzələ — hamısına reaksiya verir.
 *
 * SYMFONY-DƏN İLHAM:
 * ==================
 * Bu pattern Symfony EventSubscriberInterface-dən ilhamlanıb.
 * Laravel-in öz subscriber-i var (Event::subscribe), amma DDD-yə uyğunlaşdırdıq.
 */
abstract class DomainEventSubscriber
{
    /**
     * Bu subscriber hansı event-ləri dinləyir.
     *
     * Açar: Event class adı (FQCN).
     * Dəyər: Handler metod adı (bu class-da olmalıdır).
     *
     * @return array<class-string, string>
     *
     * NÜMUNƏ:
     * return [
     *     OrderCreatedEvent::class => 'onOrderCreated',
     *     OrderPaidEvent::class => 'onOrderPaid',
     *     OrderCancelledEvent::class => 'onOrderCancelled',
     * ];
     */
    abstract protected function subscribedEvents(): array;

    /**
     * Gələn event-i emal et.
     *
     * Event-in class adına baxır, subscribedEvents() xəritəsindən uyğun metodu tapır,
     * və onu çağırır.
     *
     * DEFENSIVE PROGRAMMİNG:
     * - Event qeydiyyatda yoxdursa → silent skip (digər subscriber-lərə mane olma).
     * - Handler metodu yoxdursa → xəbərdarlıq log et.
     * - Handler exception atsa → log et, digər event-ləri bloklama.
     */
    public function handle(object $event): void
    {
        $eventClass = get_class($event);
        $map = $this->subscribedEvents();

        if (!isset($map[$eventClass])) {
            return; // Bu event bizə aid deyil
        }

        $method = $map[$eventClass];

        if (!method_exists($this, $method)) {
            Log::warning('Subscriber handler metodu tapılmadı', [
                'subscriber' => static::class,
                'event' => $eventClass,
                'method' => $method,
            ]);
            return;
        }

        try {
            $this->$method($event);
        } catch (\Throwable $e) {
            /**
             * FAIL-SAFE: Subscriber exception atsa, digər subscriber-lər bloklanmasın.
             * Bu vacibdir çünki bir event-ə birdən çox subscriber bağlana bilər.
             * Birinin xətası digərini əngəlləməməlidir.
             *
             * Real layihədə: bu xətanı monitoring sisteminə (Sentry, Bugsnag) göndər.
             */
            Log::error('Subscriber handler xətası', [
                'subscriber' => static::class,
                'event' => $eventClass,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bu subscriber-i EventDispatcher-ə qeydiyyatdan keçir.
     *
     * Hər subscribed event üçün EventDispatcher-ə listener əlavə edir.
     * ServiceProvider-ın boot() metodunda çağırılır:
     *
     * $subscriber = new OrderProcessSubscriber();
     * $subscriber->registerWith($eventDispatcher);
     */
    public function registerWith(EventDispatcher $dispatcher): void
    {
        foreach ($this->subscribedEvents() as $eventClass => $method) {
            $dispatcher->listen($eventClass, function (object $event) {
                $this->handle($event);
            });
        }

        Log::info('Domain Event Subscriber qeydiyyatdan keçdi', [
            'subscriber' => static::class,
            'events' => array_keys($this->subscribedEvents()),
        ]);
    }

    /**
     * Bu subscriber-in dinlədiyi event siyahısını qaytarır.
     * Debug və monitoring üçün faydalıdır.
     *
     * @return string[]
     */
    public function listensTo(): array
    {
        return array_keys($this->subscribedEvents());
    }
}
