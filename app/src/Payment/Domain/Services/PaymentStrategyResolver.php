<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Services;

use Src\Payment\Domain\Strategies\PaymentGatewayInterface;
use Src\Payment\Domain\ValueObjects\PaymentMethod;

/**
 * PAYMENT STRATEGY RESOLVER — Domain Service
 * ===========================================
 *
 * DOMAIN SERVICE NƏDİR?
 * ======================
 * Domain Service — heç bir Entity və ya Value Object-ə aid olmayan biznes logikasını saxlayır.
 *
 * NƏ VAXT DOMAIN SERVICE İSTİFADƏ OLUNUR?
 * - Logika bir Entity-yə aid deyilsə (iki və ya daha çox Entity-yə toxunursa)
 * - Logika Value Object-ə sığmırsa
 * - Logika domain konseptidir, amma heç bir obyektə "sahib" deyilsə
 *
 * REAL HƏYAT ANALOGİYASI:
 * Pul köçürmək istəyirsən. Bu əməliyyat kimə aiddir?
 * - Göndərən hesaba? (balansı azalır)
 * - Alan hesaba? (balansı artır)
 * - Heç birinə! Bu əməliyyat iki hesab arasındadır → Domain Service.
 *
 * BİZİM NÜMUNƏMİZDƏ:
 * "Hansı ödəniş gateway-ini istifadə etmək?" sualı heç bir Entity-yə aid deyil.
 * Payment Entity bilməməlidir ki, Stripe mövcuddur.
 * Bu seçim məntiqini PaymentStrategyResolver (Domain Service) edir.
 *
 * DOMAIN SERVICE vs APPLICATION SERVICE FƏRQI:
 * - Domain Service: Biznes qaydası icra edir (bu sinif — gateway seçimi)
 * - Application Service: İş axınını koordinasiya edir (PaymentApplicationService)
 *
 * Domain Service heç vaxt:
 * - DB-yə yazmır (Repository-ni çağırmır)
 * - HTTP sorğu göndərmir
 * - Event dispatch etmir
 * Yalnız saf biznes məntiqini yerinə yetirir.
 *
 * @see PaymentGatewayInterface — Strategy pattern interface-i
 */
final class PaymentStrategyResolver
{
    /**
     * Gateway-ləri PaymentMethod dəyərinə görə xəritələyirik (mapping).
     *
     * @param array<string, PaymentGatewayInterface> $gateways
     *   Açar: PaymentMethod dəyəri ('credit_card', 'paypal', 'bank_transfer')
     *   Dəyər: Həmin metodun gateway-i (CreditCardGateway, PayPalGateway, BankTransferGateway)
     *
     * Bu array Laravel ServiceProvider-dən inject olunur.
     * @see \Src\Payment\Infrastructure\Providers\PaymentServiceProvider
     */
    public function __construct(
        private readonly array $gateways,
    ) {
    }

    /**
     * Ödəniş metoduna uyğun gateway-i seç və qaytar.
     *
     * NƏYƏ IF/ELSE İSTİFADƏ ETMİRİK?
     * ─────────────────────────────────
     * PİS YANAŞMA (if/else):
     *   if ($method === 'credit_card') return new CreditCardGateway();
     *   if ($method === 'paypal') return new PayPalGateway();
     *   // Yeni gateway əlavə etmək üçün BU KODU dəyişmək lazımdır!
     *
     * YAXŞI YANAŞMA (Strategy + array mapping):
     *   $this->gateways[$method->value()] — array-dən götürürük.
     *   // Yeni gateway əlavə etmək üçün yalnız ServiceProvider-ə əlavə edirik!
     *
     * Bu, Open/Closed Principle-dir: "Genişlənməyə açıq, dəyişikliyə qapalı."
     *
     * @throws \InvalidArgumentException Dəstəklənməyən ödəniş metodu verildikdə
     */
    public function resolve(PaymentMethod $method): PaymentGatewayInterface
    {
        $gateway = $this->gateways[$method->value()] ?? null;

        if ($gateway === null) {
            throw new \InvalidArgumentException(
                "'{$method->value()}' ödəniş metodu üçün gateway tapılmadı. "
                . 'Mövcud gateway-lər: ' . implode(', ', array_keys($this->gateways))
            );
        }

        return $gateway;
    }
}
