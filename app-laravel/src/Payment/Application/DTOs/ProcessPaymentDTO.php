<?php

declare(strict_types=1);

namespace Src\Payment\Application\DTOs;

/**
 * PROCESS PAYMENT DTO (Input DTO)
 * ================================
 * Ödəniş etmək üçün lazım olan giriş (input) məlumatlarını daşıyır.
 *
 * INPUT DTO vs OUTPUT DTO:
 * - Input DTO: Controller-dən Application-a məlumat daşıyır (BU FAYL)
 * - Output DTO: Application-dan Controller-ə məlumat daşıyır (PaymentDTO)
 *
 * NƏYƏ DTO İSTİFADƏ EDİRİK?
 * - Controller-dan gələn ham (raw) data-nı strukturlaşdırırıq
 * - Validasiya qaydalarını burada tətbiq edə bilərik
 * - Application Service-in hansı data-ya ehtiyacı olduğunu aydın göstərir
 */
final readonly class ProcessPaymentDTO
{
    public function __construct(
        /** Sifariş ID-si — hansı sifariş üçün ödəniş edilir */
        public string $orderId,

        /** Ödəniş məbləği */
        public float $amount,

        /** Valyuta kodu: 'USD', 'AZN', 'EUR' */
        public string $currency,

        /** Ödəniş üsulu: 'credit_card', 'paypal', 'bank_transfer' */
        public string $paymentMethod,
    ) {
    }
}
