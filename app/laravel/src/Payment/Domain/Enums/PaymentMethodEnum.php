<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Enums;

use Src\Payment\Domain\Strategies\CreditCardGateway;
use Src\Payment\Domain\Strategies\PayPalGateway;
use Src\Payment\Domain\Strategies\BankTransferGateway;

/**
 * ÖDƏNİŞ METODU ENUM
 * ====================
 * Dəstəklənən ödəniş üsullarını təmsil edən backed enum.
 *
 * STRATEGY PATTERN İLƏ İNTEQRASİYA:
 * Hər ödəniş metodunun öz gateway class-ı var (Strategy pattern).
 * gatewayClass() metodu hansı strategiyanın istifadə ediləcəyini qaytarır.
 * Bu sayədə yeni ödəniş metodu əlavə etmək çox asandır:
 *   1. Yeni case əlavə et
 *   2. Yeni gateway class yarat
 *   3. gatewayClass()-a əlavə et
 * Mövcud kodu dəyişmək lazım deyil (Open/Closed Principle).
 */
enum PaymentMethodEnum: string
{
    /**
     * Kredit/debit kart ilə ödəniş.
     * Stripe, PayTR kimi gateway-lər vasitəsilə emal olunur.
     */
    case CREDIT_CARD = 'credit_card';

    /**
     * PayPal vasitəsilə ödəniş.
     * Müştəri PayPal hesabına yönləndirilir, ödəniş orada tamamlanır.
     */
    case PAYPAL = 'paypal';

    /**
     * Bank köçürməsi ilə ödəniş.
     * Müştəri bank hesabından birbaşa köçürmə edir — təsdiq manual olur.
     */
    case BANK_TRANSFER = 'bank_transfer';

    /**
     * Bu ödəniş metodu üçün uyğun gateway (strategiya) class-ını qaytarır.
     *
     * STRATEGY PATTERN:
     * Hər ödəniş metodunun emal məntiqi fərqlidir (kart, PayPal, bank).
     * Amma PaymentService bu fərqi bilməməlidir — o yalnız interface-ə baxır.
     * gatewayClass() düzgün implementasiyanı seçir.
     *
     * İSTİFADƏ NÜMUNƏSİ:
     *   $method = PaymentMethodEnum::CREDIT_CARD;
     *   $gatewayClass = $method->gatewayClass();
     *   $gateway = app($gatewayClass);          // Laravel container-dən resolve et
     *   $result = $gateway->charge($amount);     // Ödənişi emal et
     *
     * @return string - Gateway class-ının tam adı (FQCN)
     */
    public function gatewayClass(): string
    {
        return match ($this) {
            self::CREDIT_CARD   => CreditCardGateway::class,
            self::PAYPAL        => PayPalGateway::class,
            self::BANK_TRANSFER => BankTransferGateway::class,
        };
    }

    /**
     * Ödəniş metodunun Azərbaycan dilində etiketini qaytarır.
     */
    public function label(): string
    {
        return match ($this) {
            self::CREDIT_CARD   => 'Kredit kartı',
            self::PAYPAL        => 'PayPal',
            self::BANK_TRANSFER => 'Bank köçürməsi',
        };
    }
}
