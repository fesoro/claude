<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Entities;

use Src\Payment\Domain\Events\PaymentCompletedEvent;
use Src\Payment\Domain\Events\PaymentCreatedEvent;
use Src\Payment\Domain\Events\PaymentFailedEvent;
use Src\Payment\Domain\ValueObjects\PaymentId;
use Src\Payment\Domain\ValueObjects\PaymentMethod;
use Src\Payment\Domain\ValueObjects\PaymentStatus;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Domain\AggregateRoot;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * PAYMENT AGGREGATE ROOT
 * ======================
 * Payment — ödəniş prosesinin əsas domain obyektidir.
 * Aggregate Root olduğu üçün bütün dəyişikliklər bu sinif vasitəsilə edilir.
 *
 * AGGREGATE ROOT QAYDASI:
 * Xaricdən heç vaxt PaymentStatus-u birbaşa dəyişmək olmaz.
 * Yalnız Payment-in öz metodları (process, complete, fail, refund) vasitəsilə dəyişir.
 * Bu, biznes qaydalarının həmişə yoxlanılmasını təmin edir.
 *
 * RICH DOMAIN MODEL:
 * Bu sinif "anemic" deyil — yəni sadəcə getter/setter-lərdən ibarət deyil.
 * Biznes qaydaları (status keçidləri, validasiya) bu sinfin daxilindədir.
 * Məsələn: PENDING statusundan birbaşa COMPLETED-ə keçmək olmaz.
 *
 * STATUS KEÇİDLƏRİ:
 * PENDING → PROCESSING → COMPLETED
 * PENDING → PROCESSING → FAILED
 * COMPLETED → REFUNDED
 *
 * HƏR STATUS DƏYİŞİKLİYİNDƏ DOMAIN EVENT YARANIR:
 * - create() → PaymentCreatedEvent
 * - complete() → PaymentCompletedEvent
 * - fail() → PaymentFailedEvent
 */
final class Payment extends AggregateRoot
{
    /**
     * transactionId — xarici ödəniş gateway-inin qaytardığı unikal ID.
     * Məsələn: Stripe "ch_1234567890" qaytarır.
     * Bu ID ilə gateway-dən ödəniş haqqında məlumat almaq və ya refund etmək olar.
     */
    private ?string $transactionId = null;

    /**
     * failureReason — ödəniş uğursuz olduqda səbəbi saxlanılır.
     * Məsələn: "Insufficient funds" (balans çatmır), "Card expired" (kartın müddəti bitib).
     */
    private ?string $failureReason = null;

    private function __construct(
        private readonly PaymentId $paymentId,
        private readonly string $orderId,
        private readonly Money $amount,
        private readonly PaymentMethod $method,
        private PaymentStatus $status,
    ) {
        // Aggregate Root-un id sahəsini təyin et (Entity-dən gəlir)
        $this->id = $paymentId->value();
    }

    /**
     * Yeni ödəniş yarat — FACTORY METHOD pattern.
     *
     * NƏYƏ CONSTRUCTOR ƏVƏZINƏ FACTORY METHOD?
     * 1. Adı var: create() — nə etdiyini aydın göstərir
     * 2. Domain Event qeydə alınır — constructor-da bunu etmək düzgün deyil
     * 3. Gələcəkdə validasiya əlavə etmək asandır
     *
     * @param string $orderId Hansı sifariş üçün ödəniş edilir
     * @param Money $amount Ödəniş məbləği (Value Object — currency + amount birlikdə)
     * @param PaymentMethod $method Ödəniş üsulu (kredit kartı, PayPal və s.)
     */
    public static function create(
        string $orderId,
        Money $amount,
        PaymentMethod $method,
    ): self {
        $payment = new self(
            paymentId: PaymentId::generate(),
            orderId: $orderId,
            amount: $amount,
            method: $method,
            status: PaymentStatus::pending(),
        );

        // Ödəniş yaradıldı — bu hadisəni qeydə al
        $payment->recordEvent(new PaymentCreatedEvent(
            paymentId: $payment->paymentId->value(),
            orderId: $orderId,
            amount: $amount->amount(),
            currency: $amount->currency(),
            method: $method->value(),
        ));

        return $payment;
    }

    /**
     * Ödənişi emal üçün göndər — PENDING → PROCESSING.
     *
     * BİZNES QAYDASI:
     * Yalnız PENDING statusundakı ödəniş emal üçün göndərilə bilər.
     * Əgər artıq PROCESSING və ya COMPLETED-dirsə, xəta verilir.
     *
     * @throws DomainException Status keçidi düzgün deyilsə
     */
    public function process(): void
    {
        if (!$this->status->isPending()) {
            throw new DomainException(
                "Ödəniş yalnız PENDING statusundan PROCESSING-ə keçə bilər. Hazırkı status: {$this->status->value()}"
            );
        }

        $this->status = PaymentStatus::processing();
    }

    /**
     * Ödənişi uğurla tamamla — PROCESSING → COMPLETED.
     *
     * @param string $transactionId Gateway-dən gələn tranzaksiya ID-si
     * @throws DomainException Status keçidi düzgün deyilsə
     */
    public function complete(string $transactionId): void
    {
        if (!$this->status->isProcessing()) {
            throw new DomainException(
                "Ödəniş yalnız PROCESSING statusundan COMPLETED-ə keçə bilər. Hazırkı status: {$this->status->value()}"
            );
        }

        $this->status = PaymentStatus::completed();
        $this->transactionId = $transactionId;

        // Ödəniş tamamlandı — bu vacib hadisəni qeydə al
        $this->recordEvent(new PaymentCompletedEvent(
            paymentId: $this->paymentId->value(),
            orderId: $this->orderId,
            transactionId: $transactionId,
        ));
    }

    /**
     * Ödənişi uğursuz olaraq işarələ — PROCESSING → FAILED.
     *
     * @param string $reason Uğursuzluğun səbəbi
     * @throws DomainException Status keçidi düzgün deyilsə
     */
    public function fail(string $reason): void
    {
        if (!$this->status->isProcessing()) {
            throw new DomainException(
                "Ödəniş yalnız PROCESSING statusundan FAILED-ə keçə bilər. Hazırkı status: {$this->status->value()}"
            );
        }

        $this->status = PaymentStatus::failed();
        $this->failureReason = $reason;

        $this->recordEvent(new PaymentFailedEvent(
            paymentId: $this->paymentId->value(),
            orderId: $this->orderId,
            reason: $reason,
        ));
    }

    /**
     * Ödənişi geri qaytar (refund) — COMPLETED → REFUNDED.
     *
     * BİZNES QAYDASI:
     * Yalnız COMPLETED statusundakı ödəniş geri qaytarıla bilər.
     * FAILED və ya PENDING ödənişi refund etmək mümkün deyil.
     *
     * @throws DomainException Status keçidi düzgün deyilsə və ya transactionId yoxdursa
     */
    public function refund(): void
    {
        if (!$this->status->isCompleted()) {
            throw new DomainException(
                "Yalnız COMPLETED statusundakı ödəniş geri qaytarıla bilər. Hazırkı status: {$this->status->value()}"
            );
        }

        if ($this->transactionId === null) {
            throw new DomainException(
                'Refund üçün transactionId lazımdır, amma bu ödənişdə transactionId yoxdur.'
            );
        }

        $this->status = PaymentStatus::refunded();
    }

    // ─── GETTER METODLARI ───────────────────────────────────────────────

    public function paymentId(): PaymentId
    {
        return $this->paymentId;
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function transactionId(): ?string
    {
        return $this->transactionId;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }
}
