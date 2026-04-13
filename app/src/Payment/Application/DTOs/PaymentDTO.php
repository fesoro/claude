<?php

declare(strict_types=1);

namespace Src\Payment\Application\DTOs;

use Src\Payment\Domain\Entities\Payment;

/**
 * PAYMENT DTO (Data Transfer Object)
 * ===================================
 * Domain Entity-ni (Payment) xaricə (API, controller) göndərərkən istifadə olunur.
 *
 * NƏYƏ ENTITY-Nİ BİRBAŞA GÖNDƏRMİRİK?
 * - Entity-nin daxili strukturunu gizləmək lazımdır (Encapsulation)
 * - API cavabında lazım olmayan sahələr ola bilər
 * - Entity dəyişsə, API cavabı dəyişməməlidir (stability)
 * - DTO readonly-dir — təsadüfən dəyişdirilə bilməz
 */
final readonly class PaymentDTO
{
    public function __construct(
        public string $id,
        public string $orderId,
        public float $amount,
        public string $currency,
        public string $method,
        public string $status,
        public ?string $transactionId,
        public ?string $failureReason,
    ) {
    }

    /**
     * Payment Entity-dən DTO yarat.
     * Bu pattern "factory method" adlanır — Entity-ni DTO-ya çevirir.
     */
    public static function fromEntity(Payment $payment): self
    {
        return new self(
            id: $payment->paymentId()->value(),
            orderId: $payment->orderId(),
            amount: $payment->amount()->amount(),
            currency: $payment->amount()->currency(),
            method: $payment->method()->value(),
            status: $payment->status()->value(),
            transactionId: $payment->transactionId(),
            failureReason: $payment->failureReason(),
        );
    }
}
