<?php

declare(strict_types=1);

namespace Src\Payment\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Src\Payment\Domain\Entities\Payment;
use Src\Payment\Domain\Repositories\PaymentRepositoryInterface;
use Src\Payment\Domain\ValueObjects\PaymentId;
use Src\Payment\Domain\ValueObjects\PaymentMethod;
use Src\Payment\Domain\ValueObjects\PaymentStatus;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Infrastructure\Bus\EventDispatcher;

/**
 * ELOQUENT PAYMENT REPOSITORY
 * ============================
 * PaymentRepositoryInterface-in Eloquent/DB implementasiyası.
 *
 * Infrastructure layer-dədir — Domain layer bu sinfi tanımır.
 * Domain yalnız PaymentRepositoryInterface-i bilir.
 * Laravel ServiceProvider bu sinfi interface-ə bind edir.
 *
 * NƏYƏ BİRBAŞA ELOQUENT MODEL İSTİFADƏ ETMİRİK?
 * - Eloquent Model = Infrastructure concern (DB-yə bağlıdır)
 * - Domain Entity = Biznes qaydalarını saxlayır
 * - Repository ikisi arasında köprü rolunu oynayır
 * - Bu, Clean Architecture prinsipinə uyğundur
 *
 * QEYD: Bu sadələşdirilmiş implementasiyadır.
 * Real proyektdə Eloquent Model istifadə olunardı.
 * Burada DB facade ilə sadə nümunə göstəririk.
 */
final class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}
    /**
     * Ödənişi ID-sinə görə tap.
     */
    public function findById(PaymentId $paymentId): ?Payment
    {
        $row = DB::table('payments')
            ->where('id', $paymentId->value())
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    /**
     * Ödənişi verilənlər bazasına saxla.
     *
     * Upsert pattern istifadə edirik:
     * - Əgər ödəniş mövcuddursa, yenilə (update)
     * - Əgər mövcud deyilsə, yarat (insert)
     *
     * Saxladıqdan sonra Domain Event-ləri pull edirik.
     * Real proyektdə bu event-lər event dispatcher-ə göndərilərdi.
     */
    public function save(Payment $payment): void
    {
        $data = [
            'id' => $payment->paymentId()->value(),
            'order_id' => $payment->orderId(),
            'amount' => $payment->amount()->amount(),
            'currency' => $payment->amount()->currency(),
            'method' => $payment->method()->value(),
            'status' => $payment->status()->value(),
            'transaction_id' => $payment->transactionId(),
            'failure_reason' => $payment->failureReason(),
            'updated_at' => now(),
        ];

        // Upsert — mövcuddursa yenilə, yoxdursa yarat
        DB::table('payments')->updateOrInsert(
            ['id' => $payment->paymentId()->value()],
            array_merge($data, ['created_at' => now()]),
        );

        // Domain Event-ləri dispatch et — persist uğurlu olduqdan sonra
        // EventDispatcher sinxron (Laravel listener) + asinxron (RabbitMQ) göndərir
        $events = $payment->pullDomainEvents();
        $this->eventDispatcher->dispatch($events);
    }

    /**
     * Sifariş ID-sinə görə ödənişi tap.
     */
    public function findByOrderId(string $orderId): ?Payment
    {
        $row = DB::table('payments')
            ->where('order_id', $orderId)
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    /**
     * Verilənlər bazası sətirini Domain Entity-yə çevir.
     *
     * Bu metod "hydration" adlanır — ham data-dan domain obyekti yaratmaq.
     * ACL ilə oxşardır, amma burada xarici API əvəzinə DB-dən gələn data çevrilir.
     *
     * @param object $row DB sətiri (stdClass)
     */
    private function toDomainEntity(object $row): Payment
    {
        // Reflection istifadə edirik çünki Payment-in constructor-u private-dir.
        // Real proyektdə bunu etmək üçün Payment-ə reconstruct() factory metodu əlavə olunardı.
        $reflection = new \ReflectionClass(Payment::class);
        $payment = $reflection->newInstanceWithoutConstructor();

        // Private sahələri təyin et
        $this->setProperty($payment, 'id', $row->id);
        $this->setProperty($payment, 'paymentId', PaymentId::fromString($row->id));
        $this->setProperty($payment, 'orderId', $row->order_id);
        $this->setProperty($payment, 'amount', new Money((int) $row->amount, $row->currency));
        $this->setProperty($payment, 'method', PaymentMethod::fromString($row->method));
        $this->setProperty($payment, 'status', PaymentStatus::fromString($row->status));
        $this->setProperty($payment, 'transactionId', $row->transaction_id ?? null);
        $this->setProperty($payment, 'failureReason', $row->failure_reason ?? null);

        return $payment;
    }

    /**
     * Reflection ilə private/readonly property-yə dəyər təyin et.
     * Bu, yalnız Repository-nin entity reconstruct etməsi üçün istifadə olunur.
     */
    private function setProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}
