<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Repositories;

use Src\Payment\Domain\Entities\Payment;
use Src\Payment\Domain\ValueObjects\PaymentId;

/**
 * PAYMENT REPOSITORY INTERFACE (DDD Repository Pattern)
 * =====================================================
 * Bu interface Domain layer-dədir — Infrastructure-dan asılılığı yoxdur.
 *
 * REPOSITORY PATTERN QAYDASI:
 * - Domain layer yalnız interface-i tanıyır (BU FAYL).
 * - Konkret implementasiya (Eloquent, Doctrine) Infrastructure layer-dədir.
 * - Bu, Dependency Inversion Principle (SOLID-in D-si) tətbiqidir:
 *   "Yuxarı səviyyə modulları aşağı səviyyə modullarından asılı olmamalıdır."
 *
 * NƏYƏ İNTERFACE?
 * - Test yazanda real DB əvəzinə InMemoryPaymentRepository istifadə edə bilərik.
 * - Gələcəkdə Eloquent-dən Doctrine-ə keçsək, yalnız Infrastructure dəyişir.
 * - Domain kodu heç vaxt dəyişmir.
 */
interface PaymentRepositoryInterface
{
    /**
     * Ödənişi ID-sinə görə tap.
     *
     * @return Payment|null Tapılmazsa null qaytarır
     */
    public function findById(PaymentId $paymentId): ?Payment;

    /**
     * Ödənişi verilənlər bazasına saxla (yeni yaratmaq və ya mövcudu yeniləmək).
     *
     * Saxladıqdan sonra Domain Event-ləri dispatch etmək Repository-nin vəzifəsidir.
     * Çünki event-lər yalnız uğurlu persist-dən sonra göndərilməlidir.
     */
    public function save(Payment $payment): void;

    /**
     * Sifariş ID-sinə görə ödənişi tap.
     * Bir sifarişin bir ödənişi olur (bu sadələşdirilmiş modeldir).
     *
     * @return Payment|null Tapılmazsa null qaytarır
     */
    public function findByOrderId(string $orderId): ?Payment;
}
