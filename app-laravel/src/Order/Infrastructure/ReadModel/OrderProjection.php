<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\ReadModel;

use Src\Order\Domain\Events\OrderCancelledEvent;
use Src\Order\Domain\Events\OrderConfirmedEvent;
use Src\Order\Domain\Events\OrderCreatedEvent;
use Src\Order\Domain\Events\OrderItemAddedEvent;
use Src\Order\Domain\Events\OrderPaidEvent;

/**
 * ORDER PROJECTİON — Event-ləri Read Model-ə Çevirən Mexanizm
 * =============================================================
 *
 * PROJEKSİYA (Projection) PATTERN-İ NƏDİR?
 * ==========================================
 * Projeksiyon — Event Store-dakı event-ləri dinləyib Read Model-i yeniləyən komponentdir.
 * Bu, CQRS arxitekturasının ən vacib hissələrindən biridir.
 *
 * ANALOGİYA — KİNO PROYEKTORu:
 * ============================
 * Kino film lenti (event-lər) proyektora (Projection) qoyulur.
 * Proyektor film lentini ekrana (Read Model) çevirir.
 * - Film lenti = Event Store (dəyişməz, ardıcıl hadisələr)
 * - Proyektor = Bu class (hadisələri oxuyub şəkil yaradır)
 * - Ekran = Read Model (istifadəçinin gördüyü nəticə)
 *
 * Əgər ekran sönsə (Read Model silinərsə), film lenti (Event Store) sağlamdır.
 * Proyektoru yenidən işə salıb ekrana yenidən proyeksiya edə bilərsiniz (rebuild).
 * Bu, Event Sourcing-in ən güclü cəhətlərindən biridir!
 *
 * PROJEKSİYANIN İŞ PRİNSİPİ:
 * ===========================
 * 1. Event Store-a yeni event yazılır (OrderCreated, OrderPaid, ...).
 * 2. Event dispatcher bu event-i Projeksiyon-a göndərir.
 * 3. Projeksiyon event tipinə görə müvafiq on*() metodunu çağırır.
 * 4. on*() metodu Read Model-i yeniləyir (INSERT, UPDATE).
 *
 *   Event Store          Projection           Read Model
 *   ┌──────────┐        ┌──────────┐        ┌──────────────┐
 *   │OrderCreated│──────→│onCreated()│──────→│INSERT new row│
 *   │OrderPaid   │──────→│onPaid()   │──────→│UPDATE status │
 *   │OrderCancel │──────→│onCancel() │──────→│UPDATE status │
 *   └──────────┘        └──────────┘        └──────────────┘
 *
 * BİR NEÇƏ PROJEKSİYA OLA BİLƏR:
 * ================================
 * Eyni event-lərdən fərqli Read Model-lər yarada bilərsiniz:
 * - OrderProjection → order_read_model (sifarişlərin siyahısı)
 * - OrderStatisticsProjection → order_statistics (günlük/aylıq statistika)
 * - UserOrderSummaryProjection → user_order_summary (istifadəçi bazında xülasə)
 *
 * Hər biri eyni event-ləri dinləyir, amma fərqli Read Model-i yeniləyir.
 * Bu, Event Sourcing-in "bir dəfə yaz, çox yerə proyeksiya et" prinsipdir.
 *
 * EVENTUAL CONSISTENCY PROBLEMI:
 * ==============================
 * Event yazılır → Projeksiyon emal edir → Read Model yenilənir.
 * Bu prosesdə bir az gecikmə (latency) ola bilər (millisaniyələr).
 *
 * Nəticə: İstifadəçi sifariş yaradır, amma Read Model-dən sorğu atanda
 * hələ görmməyə bilər (çox nadir hal, amma mümkündür).
 *
 * HƏLLİ:
 * 1. Sinxron projeksiyon: event yazıldıqda dərhal emal olunur (bu layihədə belə).
 * 2. UI-da optimistic update: server cavab verməzdən əvvəl UI-ı yeniləmək.
 * 3. Polling: müəyyən interval ilə yenidən sorğu atmaq.
 *
 * İDEMPOTENTLİK (Idempotency):
 * =============================
 * Projeksiyon İDEMPOTENT olmalıdır — eyni event iki dəfə emal olunsa, nəticə dəyişməməlidir.
 * Burada updateOrCreate() istifadə edirik — eyni order_id ilə iki dəfə çağırılsa,
 * ikinci dəfə sadəcə mövcud sətri yeniləyir, duplikat yaratmır.
 *
 * ANALOGİYA: Lift düyməsinə iki dəfə bassanız, lift iki dəfə gəlmir — bir dəfə gəlir.
 * Eyni event iki dəfə emal olunsa da, Read Model düzgün qalır.
 */
class OrderProjection
{
    /**
     * SİFARİŞ YARADILDI EVENT-İNİ EMAL ET
     * =====================================
     * Yeni sifariş yaradılanda Read Model-ə yeni sətir əlavə edir.
     *
     * updateOrCreate() istifadə edirik:
     * - Əgər bu order_id ilə sətir YOXDURSA → INSERT (yeni sətir yaradılır).
     * - Əgər bu order_id ilə sətir VARSA → UPDATE (mövcud sətir yenilənir).
     * Bu, idempotentliyi təmin edir.
     *
     * DİQQƏT: user_name sahəsi boş qoyulur çünki OrderCreatedEvent-də user adı yoxdur.
     * Real layihədə bu məlumat User bounded context-dən alınardı:
     * - Sinxron: UserService::getName($userId) — amma bounded context-lər arası birbaşa çağırış pis təcrübədir.
     * - Asinxron: UserCreated event-ini dinləyib user_name-i saxlamaq — daha yaxşı yanaşma.
     *
     * @param OrderCreatedEvent $event Sifariş yaradılma hadisəsi
     */
    public function onOrderCreated(OrderCreatedEvent $event): void
    {
        OrderReadModel::updateOrCreate(
            /** Axtarış şərti — bu order_id ilə sətir tap */
            ['order_id' => $event->orderId()],
            /** Təyin ediləcək dəyərlər */
            [
                'user_id'         => $event->userId(),
                'user_name'       => '',
                'status'          => 'pending',
                'total_amount'    => 0,
                'currency'        => 'AZN',
                'item_count'      => 0,
                'last_updated_at' => now(),
            ]
        );
    }

    /**
     * MƏHSUL ƏLAVƏ EDİLDİ EVENT-İNİ EMAL ET
     * ========================================
     * Sifarişə yeni məhsul əlavə edildikdə:
     * - item_count artırılır
     * - total_amount yenidən hesablanır
     *
     * DİQQƏT: Burada increment() istifadə edirik — atomik əməliyyatdır.
     * İki proses eyni anda artırsa belə, düzgün nəticə verir.
     *
     * @param OrderItemAddedEvent $event Məhsul əlavə edilmə hadisəsi
     */
    public function onOrderItemAdded(OrderItemAddedEvent $event): void
    {
        $order = OrderReadModel::find($event->orderId());

        if ($order === null) {
            /**
             * Əgər Read Model-də bu sifariş yoxdursa — eventual consistency problemidir.
             * OrderCreated event-i hələ emal olunmaya bilər.
             * Real layihədə retry mexanizmi olardı.
             */
            return;
        }

        /** Xətt cəmi: qiymət x miqdar */
        $lineTotal = $event->priceAmount() * $event->quantity();

        $order->update([
            'total_amount'    => $order->total_amount + $lineTotal,
            'currency'        => $event->priceCurrency(),
            'item_count'      => $order->item_count + 1,
            'last_updated_at' => now(),
        ]);
    }

    /**
     * SİFARİŞ ÖDƏNİLDİ EVENT-İNİ EMAL ET
     * =====================================
     * Ödəniş tamamlandıqda Read Model-dəki statusu 'paid'-ə dəyişir.
     *
     * Sadəcə status yeniləməsidir — başqa heç nə dəyişmir.
     * Amma real layihədə əlavə sahələr ola bilər:
     * - paid_at: ödəniş vaxtı
     * - payment_method: ödəniş üsulu (kart, nağd, ...)
     * - payment_reference: ödəniş referans nömrəsi
     *
     * @param OrderPaidEvent $event Ödəniş tamamlanma hadisəsi
     */
    public function onOrderPaid(OrderPaidEvent $event): void
    {
        OrderReadModel::where('order_id', $event->orderId())
            ->update([
                'status'          => 'paid',
                'last_updated_at' => now(),
            ]);
    }

    /**
     * SİFARİŞ TƏSDİQLƏNDİ EVENT-İNİ EMAL ET
     * ========================================
     * Admin sifarişi təsdiqləyəndə status 'confirmed'-ə dəyişir.
     *
     * @param OrderConfirmedEvent $event Sifariş təsdiqlənmə hadisəsi
     */
    public function onOrderConfirmed(OrderConfirmedEvent $event): void
    {
        OrderReadModel::where('order_id', $event->orderId())
            ->update([
                'status'          => 'confirmed',
                'last_updated_at' => now(),
            ]);
    }

    /**
     * SİFARİŞ LƏĞV EDİLDİ EVENT-İNİ EMAL ET
     * ========================================
     * Sifariş ləğv edildikdə status 'cancelled'-ə dəyişir.
     *
     * DİQQƏT: Ləğv etmə səbəbi (reason) Read Model-də saxlanmır.
     * Çünki Read Model sadəcə cari vəziyyəti göstərir.
     * Səbəbi bilmək lazım olanda Event Store-a müraciət olunur.
     *
     * Real layihədə 'cancellation_reason' sahəsi əlavə etmək olar —
     * bu, Read Model-in dizayn qərarıdır. Event Store-dakı data əsasında
     * istədiyiniz sahəni Read Model-ə əlavə edə bilərsiniz.
     *
     * @param OrderCancelledEvent $event Sifariş ləğv edilmə hadisəsi
     */
    public function onOrderCancelled(OrderCancelledEvent $event): void
    {
        OrderReadModel::where('order_id', $event->orderId())
            ->update([
                'status'          => 'cancelled',
                'last_updated_at' => now(),
            ]);
    }

    /**
     * İSTƏNİLƏN EVENT-İ TİPİNƏ GÖRƏ EMAL ET
     * =========================================
     * Bu metod ProjectionRebuilder tərəfindən istifadə olunur.
     * Event Store-dan oxunan event-in tipinə görə müvafiq on*() metodunu çağırır.
     *
     * NƏYƏ BU METOD LAZIMDIR?
     * Event Store-da event-lər JSON + event_type kimi saxlanılır.
     * Rebuild zamanı hər event üçün hansı metodu çağırmaq lazım olduğunu
     * event_type sahəsinə əsasən müəyyən edirik.
     *
     * @param string $eventType Event-in class adı (FQCN)
     * @param array  $payload   Event-in JSON-dan decode olunmuş datası
     */
    public function handleRawEvent(string $eventType, array $payload): void
    {
        /**
         * Event tipinə görə müvafiq on*() metodunu çağırırıq.
         * match() PHP 8.0+ xüsusiyyətidir — switch-in daha qısa versiyası.
         *
         * DİQQƏT: Burada DomainEvent obyekti yaratmaq əvəzinə
         * birbaşa payload ilə işləyirik. Bu, rebuild prosesini sadələşdirir.
         * Amma bu o deməkdir ki, hər event class-ının constructor-unu
         * burada əl ilə çağırmalıyıq.
         */
        match ($eventType) {
            OrderCreatedEvent::class => $this->onOrderCreated(
                new OrderCreatedEvent(
                    orderId: $payload['order_id'],
                    userId: $payload['user_id'],
                )
            ),
            OrderItemAddedEvent::class => $this->onOrderItemAdded(
                new OrderItemAddedEvent(
                    orderId: $payload['order_id'],
                    productId: $payload['product_id'],
                    quantity: $payload['quantity'],
                    priceAmount: $payload['price_amount'],
                    priceCurrency: $payload['price_currency'],
                )
            ),
            OrderConfirmedEvent::class => $this->onOrderConfirmed(
                new OrderConfirmedEvent(
                    orderId: $payload['order_id'],
                )
            ),
            OrderPaidEvent::class => $this->onOrderPaid(
                new OrderPaidEvent(
                    orderId: $payload['order_id'],
                    totalAmount: $payload['total_amount'],
                )
            ),
            OrderCancelledEvent::class => $this->onOrderCancelled(
                new OrderCancelledEvent(
                    orderId: $payload['order_id'],
                    reason: $payload['reason'],
                )
            ),
            /**
             * Tanınmayan event tipi — xəta yoxdur, sadəcə keçirik.
             * Çünki Event Store-da fərqli aggregate-lərin event-ləri ola bilər.
             * Bu projeksiyon yalnız Order event-lərini emal edir.
             */
            default => null,
        };
    }
}
