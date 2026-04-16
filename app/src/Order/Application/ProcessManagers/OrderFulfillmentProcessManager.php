<?php

declare(strict_types=1);

namespace Src\Order\Application\ProcessManagers;

use Illuminate\Support\Facades\Log;
use Src\Order\Domain\Repositories\OrderRepositoryInterface;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Infrastructure\Bus\EventDispatcher;
use Src\Shared\Infrastructure\Locking\DistributedLock;
use Src\Shared\Infrastructure\Messaging\IdempotentConsumer;

/**
 * ORDER FULFILLMENT PROCESS MANAGER
 * ====================================
 *
 * PROCESS MANAGER NƏDİR?
 * =======================
 * Process Manager — birdən çox bounded context-i əhatə edən, çox addımlı biznes
 * prosesini koordinasiya edən komponentdir. Saga-ya bənzəyir, amma daha güclüdür.
 *
 * SAGA vs PROCESS MANAGER FƏRQI:
 * ================================
 * SAGA:
 * - Reaktivdir — yalnız event-lərə reaksiya göstərir.
 * - State-i yoxdur (və ya çox sadədir).
 * - Kompensasiya əməliyyatları (undo) ilə məşğuldur.
 * - Nümunə: "Ödəniş uğursuz oldu → sifarişi ləğv et."
 *
 * PROCESS MANAGER:
 * - Proaktivdir — prosesin harada olduğunu bilir və növbəti addımı müəyyən edir.
 * - State-i var — prosesin cari vəziyyətini saxlayır (DB-də persist oluna bilər).
 * - Routing logic var — hansı event-dən sonra nə edəcəyini bilir.
 * - Timeout idarəsi var — addım çox uzun çəkərsə nə edəcəyini bilir.
 * - Nümunə: "Sifariş yaradıldı → ödəniş al → stoku azalt → göndəriş yarat → müştərini bildir."
 *
 * ANALOGİYA:
 * ===========
 * Saga = Domino daşları: biri yıxılır → növbəti avtomatik yıxılır.
 *   Əgər biri yıxılmazsa — kompensasiya (geri qaldırma).
 *
 * Process Manager = Dirijor: orkestri idarə edir.
 *   Hər musiçinin nə vaxt çalacağını bilir, prosesin harada olduğunu izləyir.
 *   Skripka hissəsi bitdikdə → truba başlayır. Truba geciksə → gözləyir, xəbərdarlıq edir.
 *
 * CHOREOGRAPHY vs ORCHESTRATION:
 * ================================
 * Choreography (xoreografiya): Hər servis özü bilir nə edəcəyini.
 *   OrderCreated → Payment service dinləyir, özü ödənişi edir.
 *   PaymentCompleted → Shipping service dinləyir, özü göndərir.
 *   Problem: Prosesin bütövlüyünü görmək çətindir, debug çətin.
 *
 * Orchestration (orkestratsiya): Process Manager hamını idarə edir.
 *   Process Manager: "İndi ödəniş al" → "Tamam, indi stoku azalt" → "Tamam, indi göndər"
 *   Üstünlük: Prosesi bir yerdən izləmək olar, debug asan.
 *
 * Bu layihədə Orchestration yanaşmasını göstəririk.
 *
 * STATE MAŞINı (Finite State Machine):
 * ======================================
 * Process Manager daxilində bir state maşını işləyir:
 *
 * INITIATED → PAYMENT_PENDING → PAYMENT_COMPLETED → STOCK_RESERVED
 *          → SHIPMENT_CREATED → COMPLETED
 *
 * Hər addımda:
 * 1. Cari state-ə baxır.
 * 2. Gələn event-ə baxır.
 * 3. Uyğun transition (keçid) varsa — növbəti addımı icra edir.
 * 4. State-i yeniləyir.
 *
 * ÖMR DÖVRÜSÜ:
 * ==============
 * 1. OrderCreated event → Process Manager yaranır (INITIATED).
 * 2. Ödəniş command göndərir → PAYMENT_PENDING.
 * 3. PaymentCompleted event → stok azaltma command göndərir → STOCK_RESERVED.
 * 4. StockReserved event → göndəriş command göndərir → SHIPMENT_CREATED.
 * 5. ShipmentCreated event → müştəriyə bildiriş → COMPLETED.
 *
 * UĞURSUZLUQ HALLARı:
 * - PaymentFailed → sifarişi ləğv et (kompensasiya).
 * - StockInsufficient → ödənişi geri qaytar + sifarişi ləğv et.
 * - Timeout → admin-ə xəbərdarlıq göndər.
 */
class OrderFulfillmentProcessManager
{
    /**
     * Prosesin mümkün vəziyyətləri (state-ləri).
     * Enum istifadə edə bilərdik, amma string sabiti daha sadədir nümayiş üçün.
     */
    public const STATE_INITIATED = 'initiated';
    public const STATE_PAYMENT_PENDING = 'payment_pending';
    public const STATE_PAYMENT_COMPLETED = 'payment_completed';
    public const STATE_STOCK_RESERVED = 'stock_reserved';
    public const STATE_SHIPMENT_CREATED = 'shipment_created';
    public const STATE_COMPLETED = 'completed';
    public const STATE_FAILED = 'failed';
    public const STATE_COMPENSATING = 'compensating';

    /**
     * Prosesin cari vəziyyəti.
     * Hər event emal edildikdə yenilənir.
     */
    private string $state = self::STATE_INITIATED;

    /**
     * Prosesə aid data — proses boyu toplanır.
     * Hər addımda yeni məlumat əlavə olunur.
     *
     * @var array<string, mixed>
     */
    private array $processData = [];

    /**
     * Tamamlanmış addımlar — kompensasiya zamanı lazımdır.
     * Hansı addımların geri alınacağını bilmək üçün.
     *
     * @var string[]
     */
    private array $completedSteps = [];

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventDispatcher $eventDispatcher,
        private readonly DistributedLock $lock,
        private readonly IdempotentConsumer $idempotentConsumer,
        private readonly CommandBus $commandBus,
    ) {}

    /**
     * SİFARİŞ YARADILDI — Prosesi başlat
     * ======================================
     * Bu, Process Manager-in entry point-idir.
     * Sifariş yaradıldıqda çağırılır və ödəniş prosesini başladır.
     *
     * DistributedLock istifadə edirik ki, eyni sifariş üçün iki proses
     * eyni anda işləməsin (race condition prevention).
     */
    public function handleOrderCreated(string $orderId, string $userId, int $totalAmount, string $currency): void
    {
        /**
         * IdempotentConsumer — eyni event iki dəfə emal olunmasın.
         * Distributed sistemlərdə event-lər dublikat gələ bilər (at-least-once delivery).
         * Bu, prosesin iki dəfə başlamasının qarşısını alır.
         */
        $this->idempotentConsumer->process(
            messageId: "order-fulfillment-created-{$orderId}",
            type: 'order.fulfillment.created',
            handler: function () use ($orderId, $userId, $totalAmount, $currency) {
                $this->lock->execute("order-fulfillment:{$orderId}", function () use ($orderId, $userId, $totalAmount, $currency) {
                    $this->processData = [
                        'order_id' => $orderId,
                        'user_id' => $userId,
                        'total_amount' => $totalAmount,
                        'currency' => $currency,
                    ];

                    $this->transitionTo(self::STATE_PAYMENT_PENDING);
                    $this->requestPayment($orderId, $totalAmount, $currency);

                    Log::info('Order Fulfillment Process başladı', [
                        'order_id' => $orderId,
                        'state' => $this->state,
                    ]);
                });
            },
        );
    }

    /**
     * ÖDƏNİŞ TAMAMLANDI — Stok ayırmasına keç
     * ==========================================
     * Payment bounded context-dən PaymentCompleted event gəldikdə çağırılır.
     * State: PAYMENT_PENDING → PAYMENT_COMPLETED
     * Növbəti addım: Stok azaltma command göndər.
     */
    public function handlePaymentCompleted(string $orderId, string $paymentId): void
    {
        $this->idempotentConsumer->process(
            messageId: "order-fulfillment-paid-{$orderId}",
            type: 'order.fulfillment.paid',
            handler: function () use ($orderId, $paymentId) {
                $this->lock->execute("order-fulfillment:{$orderId}", function () use ($orderId, $paymentId) {
                    $this->assertState(self::STATE_PAYMENT_PENDING);

                    $this->processData['payment_id'] = $paymentId;
                    $this->completedSteps[] = 'payment';

                    $this->transitionTo(self::STATE_PAYMENT_COMPLETED);
                    $this->requestStockReservation($orderId);
                });
            },
        );
    }

    /**
     * ÖDƏNİŞ UĞURSUZ — Kompensasiya prosesini başlat
     * ==================================================
     * Ödəniş uğursuz olduqda sifarişi ləğv edirik.
     * Bu, Saga pattern-dəki compensating transaction-dur.
     *
     * KOMPENSASİYA NECƏ İŞLƏYİR?
     * completedSteps siyahısına baxırıq — hansı addımlar tamamlanıbsa,
     * onları TƏRSİNƏ geri alırıq.
     *
     * Nümunə: Ödəniş olub amma stok yoxdur.
     * → completedSteps = ['payment']
     * → Kompensasiya: ödənişi geri qaytar (refund).
     */
    public function handlePaymentFailed(string $orderId, string $reason): void
    {
        $this->idempotentConsumer->process(
            messageId: "order-fulfillment-payment-failed-{$orderId}",
            type: 'order.fulfillment.payment_failed',
            handler: function () use ($orderId, $reason) {
                $this->lock->execute("order-fulfillment:{$orderId}", function () use ($orderId, $reason) {
                    Log::warning('Ödəniş uğursuz — kompensasiya başlayır', [
                        'order_id' => $orderId,
                        'reason' => $reason,
                        'completed_steps' => $this->completedSteps,
                    ]);

                    $this->transitionTo(self::STATE_COMPENSATING);
                    $this->compensate($orderId, $reason);
                    $this->transitionTo(self::STATE_FAILED);
                });
            },
        );
    }

    /**
     * STOK AYRILDI — Göndəriş yaratmağa keç
     */
    public function handleStockReserved(string $orderId): void
    {
        $this->idempotentConsumer->process(
            messageId: "order-fulfillment-stock-reserved-{$orderId}",
            type: 'order.fulfillment.stock_reserved',
            handler: function () use ($orderId) {
                $this->lock->execute("order-fulfillment:{$orderId}", function () use ($orderId) {
                    $this->assertState(self::STATE_PAYMENT_COMPLETED);

                    $this->completedSteps[] = 'stock';
                    $this->transitionTo(self::STATE_STOCK_RESERVED);
                    $this->requestShipmentCreation($orderId);
                });
            },
        );
    }

    /**
     * GÖNDƏRİŞ YARADILDI — Proses tamamlandı
     */
    public function handleShipmentCreated(string $orderId, string $trackingNumber): void
    {
        $this->idempotentConsumer->process(
            messageId: "order-fulfillment-shipped-{$orderId}",
            type: 'order.fulfillment.shipped',
            handler: function () use ($orderId, $trackingNumber) {
                $this->lock->execute("order-fulfillment:{$orderId}", function () use ($orderId, $trackingNumber) {
                    $this->assertState(self::STATE_STOCK_RESERVED);

                    $this->completedSteps[] = 'shipment';
                    $this->processData['tracking_number'] = $trackingNumber;
                    $this->transitionTo(self::STATE_COMPLETED);

                    Log::info('Order Fulfillment Process tamamlandı', [
                        'order_id' => $orderId,
                        'tracking_number' => $trackingNumber,
                        'completed_steps' => $this->completedSteps,
                    ]);
                });
            },
        );
    }

    // =========================================================================
    // DAXİLİ METODLAR — Command göndərmə və kompensasiya
    // =========================================================================

    /**
     * ÖDƏNİŞ TƏLƏBİ — CommandBus vasitəsilə Payment context-ə command göndər.
     *
     * Process Manager əməliyyatı birbaşa icra etmir — command göndərir.
     * Bu, bounded context ayrılığını qoruyur:
     * Order context Payment context-in daxili məntiqini bilmir.
     */
    private function requestPayment(string $orderId, int $amount, string $currency): void
    {
        Log::info('Ödəniş tələb olunur (Process Manager)', [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        $this->commandBus->dispatch(
            new \Src\Payment\Application\Commands\ProcessPayment\ProcessPaymentCommand(
                orderId: $orderId,
                amount: (float) $amount / 100, // qəpikdən AZN-ə çevir
                currency: $currency,
                paymentMethod: $this->processData['payment_method'] ?? 'credit_card',
            ),
        );
    }

    /**
     * STOK AYIRMASI — Product context-ə stok azaltma command-ı göndər.
     */
    private function requestStockReservation(string $orderId): void
    {
        Log::info('Stok ayırması tələb olunur (Process Manager)', ['order_id' => $orderId]);

        // Order-in item-lərini oxu və hər biri üçün stok azaltma command-ı göndər
        $order = $this->orderRepository->findById(OrderId::fromString($orderId));

        if ($order !== null) {
            foreach ($order->items() as $item) {
                $this->commandBus->dispatch(
                    new \Src\Product\Application\Commands\UpdateStock\UpdateStockCommand(
                        productId: $item->productId(),
                        amount: $item->quantity(),
                        type: 'decrease',
                    ),
                );
            }
        }
    }

    /**
     * GÖNDƏRİŞ YARATMA — Shipping context-ə command göndər.
     *
     * Bu layihədə Shipping bounded context yoxdur (sadələşdirmə).
     * Real layihədə CreateShipmentCommand dispatch edilərdi.
     * Burada Order statusunu yeniləyirik.
     */
    private function requestShipmentCreation(string $orderId): void
    {
        Log::info('Göndəriş yaradılması tələb olunur (Process Manager)', ['order_id' => $orderId]);

        $this->commandBus->dispatch(
            new \Src\Order\Application\Commands\UpdateOrderStatus\UpdateOrderStatusCommand(
                orderId: $orderId,
                newStatus: 'shipped',
            ),
        );
    }

    /**
     * KOMPENSASİYA — Tamamlanmış addımları TƏRSİNƏ geri al
     * ======================================================
     * completedSteps siyahısını tərsinə oxuyub hər birini undo edir.
     * Bu, Saga compensating transaction pattern-dir.
     *
     * Sıra vacibdir: göndərişi geri al → stoku geri qaytar → ödənişi refund et → sifarişi ləğv et.
     * (əksinə olsa: sifariş ləğv, amma stok hələ ayrılmış qalar)
     */
    private function compensate(string $orderId, string $reason): void
    {
        $stepsToUndo = array_reverse($this->completedSteps);

        foreach ($stepsToUndo as $step) {
            match ($step) {
                'shipment' => $this->cancelShipment($orderId),
                'stock' => $this->releaseStock($orderId),
                'payment' => $this->refundPayment($orderId),
                default => Log::warning("Naməlum kompensasiya addımı: {$step}"),
            };
        }

        $this->cancelOrder($orderId, $reason);
    }

    private function cancelShipment(string $orderId): void
    {
        Log::info('Kompensasiya: göndəriş ləğv edilir', ['order_id' => $orderId]);
    }

    private function releaseStock(string $orderId): void
    {
        Log::info('Kompensasiya: stok geri qaytarılır', ['order_id' => $orderId]);
    }

    private function refundPayment(string $orderId): void
    {
        Log::info('Kompensasiya: ödəniş geri qaytarılır (refund)', ['order_id' => $orderId]);
    }

    private function cancelOrder(string $orderId, string $reason): void
    {
        Log::info('Kompensasiya: sifariş ləğv edilir', [
            'order_id' => $orderId,
            'reason' => $reason,
        ]);
    }

    /**
     * STATE TRANSITION — Vəziyyət keçidi
     *
     * Keçid qanuniliyi burada yoxlanıla bilər.
     * Real layihədə: state + event → allowed transitions map.
     */
    private function transitionTo(string $newState): void
    {
        Log::info('Process Manager state keçidi', [
            'from' => $this->state,
            'to' => $newState,
        ]);

        $this->state = $newState;
    }

    /**
     * CARİ STATE-İ YOXLA
     * State-i yoxlamaq vacibdir — yanlış sırada gələn event-ləri rədd etmək üçün.
     *
     * @throws \RuntimeException Gözlənilməyən state-dədirsə
     */
    private function assertState(string $expectedState): void
    {
        if ($this->state !== $expectedState) {
            throw new \RuntimeException(
                "Process Manager yanlış state-dədir. Gözlənilən: {$expectedState}, Cari: {$this->state}"
            );
        }
    }

    public function currentState(): string
    {
        return $this->state;
    }

    public function completedSteps(): array
    {
        return $this->completedSteps;
    }
}
