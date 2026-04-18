<?php

declare(strict_types=1);

namespace Src\Payment\Infrastructure\Gateway;

use Src\Payment\Domain\Strategies\GatewayResult;
use Src\Payment\Domain\Strategies\PaymentGatewayInterface;
use Src\Product\Domain\ValueObjects\Money;

/**
 * STRIPE GATEWAY ADAPTER
 * ======================
 * Real Stripe API-ni bizim PaymentGatewayInterface-ə uyğunlaşdıran adapter.
 *
 * ADAPTER PATTERN NƏDİR?
 * ─────────────────────────
 * Adapter — uyğunsuz interface-ləri birləşdirən pattern-dir.
 *
 * ANALOGİYA: Elektrik adapteru kimi.
 * - Avropada Type C (iki dairəvi pin) prizlər var
 * - ABŞ-da Type A (iki yastı pin) prizlər var
 * - Adapter — arada durub Type A cihazı Type C prizə qoşur
 *
 * BİZİM NÜMUNƏMİZDƏ:
 * - Stripe API-nin öz interface-i var (StripeClient, Charge::create)
 * - Bizim domain-in PaymentGatewayInterface-i var
 * - StripeGatewayAdapter — arada durub Stripe-ı bizim interface-ə uyğunlaşdırır
 *
 * ┌─────────────────────────┐     ┌───────────────────────┐     ┌────────────┐
 * │ PaymentGatewayInterface │────→│ StripeGatewayAdapter  │────→│ Stripe API │
 * │ (bizim interface)       │     │ (adapter — çevirici)  │     │ (xarici)   │
 * └─────────────────────────┘     └───────────────────────┘     └────────────┘
 *
 * BU SİMULYASİYADIR:
 * Real Stripe SDK istifadə etmirik (composer require stripe/stripe-php lazım olardı).
 * Amma real proyektdə necə olacağını göstəririk.
 */
final class StripeGatewayAdapter implements PaymentGatewayInterface
{
    /**
     * Stripe API açarı — real proyektdə .env faylından gəlir.
     */
    public function __construct(
        private readonly string $apiKey = 'sk_test_simulated_key',
    ) {
    }

    /**
     * Stripe API ilə ödəniş simulyasiyası.
     *
     * REAL PROYEKTDƏ BU KOD BELƏ OLARDI:
     * ────────────────────────────────────
     * \Stripe\Stripe::setApiKey($this->apiKey);
     *
     * $charge = \Stripe\Charge::create([
     *     'amount' => (int)($amount->amount() * 100), // Stripe cent ilə işləyir!
     *     'currency' => strtolower($amount->currency()),
     *     'source' => $tokenFromFrontend,
     *     'description' => 'Sifariş ödənişi',
     * ]);
     *
     * // Stripe cavabını bizim domain formatına çevir (ADAPTER + ACL)
     * if ($charge->status === 'succeeded') {
     *     return GatewayResult::success($charge->id);
     * }
     * return GatewayResult::failure($charge->failure_message);
     *
     * DİQQƏT: Stripe məbləği CENT ilə qəbul edir!
     * $10.00 → 1000 (cent) olaraq göndərilir.
     * Bu formatı çevirmək Adapter-in vəzifəsidir.
     */
    public function charge(Money $amount): GatewayResult
    {
        // Simulyasiya: Stripe-a HTTP sorğu göndəririk (fake)
        $stripeResponse = $this->simulateStripeApiCall($amount);

        // Stripe cavabını bizim domain formatına çevir — ADAPTER-in əsas vəzifəsi
        if ($stripeResponse['status'] === 'succeeded') {
            return GatewayResult::success($stripeResponse['id']);
        }

        return GatewayResult::failure(
            $stripeResponse['failure_message'] ?? 'Stripe ödənişi uğursuz oldu.'
        );
    }

    /**
     * Stripe ilə refund simulyasiyası.
     */
    public function refund(string $transactionId): bool
    {
        // Simulyasiya — real Stripe refund API çağırışı
        // \Stripe\Refund::create(['charge' => $transactionId]);
        return random_int(1, 10) <= 9;
    }

    /**
     * Stripe API cavabını simulyasiya edir.
     * Real proyektdə bu HTTP sorğu ilə əvəzlənərdi.
     *
     * @return array<string, mixed> Stripe-ın JSON cavabının PHP array versiyası
     */
    private function simulateStripeApiCall(Money $amount): array
    {
        $isSuccessful = random_int(1, 10) <= 9;

        if ($isSuccessful) {
            // Stripe-ın real cavab formatı (sadələşdirilmiş):
            return [
                'id' => 'ch_' . bin2hex(random_bytes(12)),
                'object' => 'charge',
                'amount' => (int) ($amount->amount() * 100), // Cent-ə çevir
                'currency' => strtolower($amount->currency()),
                'status' => 'succeeded',
                'failure_message' => null,
            ];
        }

        return [
            'id' => null,
            'object' => 'charge',
            'amount' => (int) ($amount->amount() * 100),
            'currency' => strtolower($amount->currency()),
            'status' => 'failed',
            'failure_message' => 'Your card was declined.',
        ];
    }
}
