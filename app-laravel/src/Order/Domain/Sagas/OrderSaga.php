<?php

declare(strict_types=1);

namespace Src\Order\Domain\Sagas;

use Src\Order\Domain\Entities\Order;
use Src\Order\Domain\Repositories\OrderRepositoryInterface;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * ORDER SAGA (Saga Pattern)
 * ==========================
 * Sifariş prosesini addım-addım idarə edən Saga.
 *
 * ═══════════════════════════════════════════════════════════════
 * SAGA PATTERN NƏDİR?
 * ═══════════════════════════════════════════════════════════════
 *
 * Saga — bir neçə bounded context-i əhatə edən uzun biznes prosesini
 * idarə edən pattern-dir. Hər addım müstəqil bir əməliyyatdır və
 * əgər hər hansı addım UĞURSUZ olarsa, əvvəlki addımlar GERİ ALINIR.
 *
 * PROBLEM (Saga olmadan):
 * ┌──────────────────────────────────────────────────────────────┐
 * │ Adi verilənlər bazası transaction-ı yalnız BİR DB-də işləyir. │
 * │ Amma sifariş prosesi bir neçə modulu əhatə edir:            │
 * │ - Order modulu (sifariş yaratmaq)                           │
 * │ - Payment modulu (ödəniş emal etmək)                       │
 * │ - Notification modulu (bildiriş göndərmək)                  │
 * │ Hər modul öz DB-si olan ayrı servisdir.                     │
 * │ Bütün bunları BİR transaction-da etmək mümkün deyil!        │
 * └──────────────────────────────────────────────────────────────┘
 *
 * HƏLL (Saga ilə):
 * ┌──────────────────────────────────────────────────────────────┐
 * │ Hər addımı ayrı-ayrı icra et:                               │
 * │ 1. Sifariş yarat ✓                                          │
 * │ 2. Ödəniş et ✓ / ✗ (uğursuz olarsa → 1-i geri al)         │
 * │ 3. Bildiriş göndər ✓                                        │
 * │                                                              │
 * │ Əgər addım 2 uğursuz olarsa:                                │
 * │ → Compensating Transaction: Sifarişi LƏĞV ET (geri al)      │
 * └──────────────────────────────────────────────────────────────┘
 *
 * ═══════════════════════════════════════════════════════════════
 * COMPENSATING TRANSACTION (əks əməliyyat) NƏDİR?
 * ═══════════════════════════════════════════════════════════════
 *
 * DB-dəki ROLLBACK əvəzinə, əks əməliyyat icra olunur:
 * - Sifariş yaradılıb? → Ləğv et (cancel)
 * - Stok azaldılıb? → Geri artır
 * - Ödəniş edilib? → Refund et
 *
 * Bu "ROLLBACK" deyil — tamamilə yeni bir əməliyyatdır.
 * Fərq: ROLLBACK heç nə olmamış kimi geri qaytarır.
 *       Compensating Transaction isə "bu baş verdi, amma geri aldıq" qeyd edir.
 *
 * ═══════════════════════════════════════════════════════════════
 * SİFARİŞ SAGA AXINI:
 * ═══════════════════════════════════════════════════════════════
 *
 * UĞURLU AXIN (Happy Path):
 * ┌────────────────┐    ┌──────────────────┐    ┌──────────────────┐
 * │ 1. CreateOrder │───→│ 2. ProcessPayment│───→│ 3. SendNotify    │
 * │    (yaratmaq)  │    │    (ödəniş)      │    │    (bildiriş)    │
 * └────────────────┘    └──────────────────┘    └──────────────────┘
 *
 * UĞURSUZ AXIN (ödəniş uğursuz):
 * ┌────────────────┐    ┌──────────────────┐
 * │ 1. CreateOrder │───→│ 2. ProcessPayment│──→ UĞURSUZ!
 * │    (yaratmaq) ✓│    │    (ödəniş)     ✗│
 * └───────┬────────┘    └──────────────────┘
 *         │
 *         ↓ Compensating Transaction
 * ┌────────────────┐
 * │  CancelOrder   │ ← Sifarişi ləğv et (geri al)
 * │  (ləğv etmək)  │
 * └────────────────┘
 *
 * ═══════════════════════════════════════════════════════════════
 * SAGA NÖVLƏRİ:
 * ═══════════════════════════════════════════════════════════════
 * 1. Choreography (xoreografiya): Hər servis event göndərir, növbəti dinləyir.
 *    Order → event → Payment → event → Notification
 *    Üstünlük: sadə. Çatışmazlıq: mürəkkəb axınları izləmək çətindir.
 *
 * 2. Orchestration (orkestrləşdirmə): Mərkəzi Saga coordinator idarə edir.
 *    Saga → Order-a əmr ver → Payment-ə əmr ver → Notification-a əmr ver
 *    Üstünlük: axın aydındır. Çatışmazlıq: Saga single point of failure ola bilər.
 *
 * Biz burada ORCHESTRATION yanaşmasını istifadə edirik — OrderSaga coordinator-dur.
 */
class OrderSaga
{
    /**
     * Saga-nın vəziyyəti — hər addımın nəticəsini izləyir.
     * Bu, hansı compensating transaction-ların lazım olduğunu bilmək üçündür.
     */
    private array $completedSteps = [];

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * SAGA-NI BAŞLAT — bütün addımları ardıcıl icra et.
     *
     * Bu metod sifariş prosesinin bütün addımlarını koordinasiya edir:
     * 1. Sifarişi təsdiqlə (confirm)
     * 2. Ödənişi emal et (processPayment)
     * 3. Bildiriş göndər (sendNotification)
     *
     * Əgər hər hansı addım uğursuz olarsa, compensate() çağırılır
     * və əvvəlki addımlar geri alınır.
     *
     * @param string $orderId Emal olunacaq sifarişin ID-si
     * @throws DomainException Sifariş tapılmadıqda
     */
    public function handle(string $orderId): void
    {
        $order = $this->orderRepository->findById(OrderId::fromString($orderId));

        if ($order === null) {
            throw new DomainException("Sifariş tapılmadı: {$orderId}");
        }

        try {
            // ADDIM 1: Sifarişi təsdiqlə
            $this->confirmOrder($order);
            $this->completedSteps[] = 'confirm_order';

            // ADDIM 2: Ödənişi emal et
            $this->processPayment($order);
            $this->completedSteps[] = 'process_payment';

            // ADDIM 3: Bildiriş göndər
            $this->sendNotification($order);
            $this->completedSteps[] = 'send_notification';

        } catch (\Throwable $exception) {
            // UĞURSUZLUQ! Compensating transaction-ları icra et.
            // Əvvəlki uğurlu addımları geri al.
            $this->compensate($order, $exception);

            throw $exception;
        }
    }

    /**
     * ADDIM 1: Sifarişi təsdiqlə (PENDING → CONFIRMED)
     */
    private function confirmOrder(Order $order): void
    {
        $order->confirm();
        $this->orderRepository->save($order);
    }

    /**
     * ADDIM 2: Ödənişi emal et (CONFIRMED → PAID)
     *
     * REAL PROYEKTDƏ:
     * - Bu metod Payment bounded context-ə Integration Event göndərər.
     * - Payment servisi Stripe/PayPal API ilə ödənişi emal edər.
     * - Nəticəni event ilə geri qaytarar.
     *
     * Bu nümunədə sadələşdirilmiş versiya göstərilir.
     *
     * @throws DomainException Ödəniş uğursuz olduqda (simulyasiya)
     */
    private function processPayment(Order $order): void
    {
        // REAL PROYEKTDƏ: Payment servisi çağırılacaq
        // $paymentResult = $this->paymentService->charge($order->totalAmount());
        // if (!$paymentResult->isSuccessful()) { throw ... }

        // Simulyasiya: ödəniş uğurlu hesab edirik
        $order->markAsPaid();
        $this->orderRepository->save($order);
    }

    /**
     * ADDIM 3: Müştəriyə bildiriş göndər
     *
     * REAL PROYEKTDƏ:
     * - Notification bounded context-ə Integration Event göndəriləcək.
     * - Email, SMS, push notification göndəriləcək.
     */
    private function sendNotification(Order $order): void
    {
        // REAL PROYEKTDƏ: Notification servisi çağırılacaq
        // $this->notificationService->send(new OrderPaidNotification($order));

        // Simulyasiya: bildiriş göndərildi hesab edirik
    }

    /**
     * COMPENSATING TRANSACTION — uğursuz addımların əksini icra et.
     * ==============================================================
     *
     * Bu metod Saga-nın ən vacib hissəsidir!
     *
     * Əgər processPayment() uğursuz olarsa:
     * 1. 'confirm_order' tamamlanıb → sifarişi ləğv et (compensate)
     * 2. Stok azaldılıbsa → stoku geri artır
     *
     * HƏR ADIMIN ƏKS ƏMƏLİYYATI:
     * ┌────────────────────┬───────────────────────────┐
     * │ Uğurlu addım      │ Compensating Transaction  │
     * ├────────────────────┼───────────────────────────┤
     * │ confirm_order      │ cancel_order (ləğv et)    │
     * │ process_payment    │ refund_payment (geri al)  │
     * │ send_notification  │ (geri almaq lazım deyil)  │
     * └────────────────────┴───────────────────────────┘
     *
     * @param Order      $order     Emal olunan sifariş
     * @param \Throwable $exception Baş verən xəta
     */
    private function compensate(Order $order, \Throwable $exception): void
    {
        // Tamamlanmış addımları TƏRSİNƏ sıra ilə geri al
        // (ən son tamamlanan addım birinci geri alınır — LIFO/stack prinsip)
        $stepsToCompensate = array_reverse($this->completedSteps);

        foreach ($stepsToCompensate as $step) {
            match ($step) {
                'confirm_order' => $this->compensateConfirmOrder($order, $exception),
                'process_payment' => $this->compensateProcessPayment($order),
                'send_notification' => null, // Bildirişi geri almaq lazım deyil
            };
        }
    }

    /**
     * Sifarişin təsdiqlənməsini geri al — sifarişi LƏĞV ET.
     * Bu ən çox istifadə olunan compensating transaction-dır.
     */
    private function compensateConfirmOrder(Order $order, \Throwable $exception): void
    {
        // DİQQƏT: Order-in cancel() metodu yalnız PENDING və ya CONFIRMED-dən işləyir.
        // Əgər sifariş artıq PAID statusundadırsa, ayrı refund prosesi lazımdır.
        try {
            // Sifarişi yenidən DB-dən oxu (ən son vəziyyəti al)
            $freshOrder = $this->orderRepository->findById($order->orderId());

            if ($freshOrder !== null && ($freshOrder->status()->isPending() || $freshOrder->status()->isConfirmed())) {
                $freshOrder->cancel("Saga compensating: {$exception->getMessage()}");
                $this->orderRepository->save($freshOrder);
            }
        } catch (\Throwable $compensationError) {
            // Compensation da uğursuz oldu — bu ciddi problem!
            // REAL PROYEKTDƏ: bunu log-layıb manual müdaxilə üçün alert göndərmək lazımdır.
            // Dead Letter Queue-ya yazmaq olar.
        }
    }

    /**
     * Ödənişi geri al — REFUND et.
     * REAL PROYEKTDƏ Payment servisi ilə refund əməliyyatı ediləcək.
     */
    private function compensateProcessPayment(Order $order): void
    {
        // REAL PROYEKTDƏ:
        // $this->paymentService->refund($order->orderId());
    }
}
