<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Strategies;

use Src\Product\Domain\ValueObjects\Money;

/**
 * CREDIT CARD GATEWAY — Concrete Strategy
 * ========================================
 * Strategy pattern-in konkret implementasiyasıdır.
 * Kredit kartı ilə ödəniş prosesini simulyasiya edir.
 *
 * REAL PROYEKTDƏ:
 * Bu sinif Stripe, PayTR, Iyzico kimi real gateway-lərlə əlaqə qurardı.
 * Amma biz öyrənmə məqsədilə simulyasiya edirik.
 *
 * DİQQƏT: Bu sinif Domain layer-dədir, çünki interface buradadır.
 * Real API çağırışları Infrastructure layer-də olmalıdır (StripeGatewayAdapter).
 * Bu simulyasiya yalnız pattern-i göstərmək üçündür.
 */
final class CreditCardGateway implements PaymentGatewayInterface
{
    /**
     * Kredit kartı ilə ödəniş simulyasiyası.
     *
     * Real həyatda bu metod:
     * 1. Kart məlumatlarını şifrələyir
     * 2. Bank API-sinə sorğu göndərir
     * 3. 3D Secure yoxlaması edir (əlavə təhlükəsizlik)
     * 4. Cavabı qaytarır
     *
     * Simulyasiyada: 90% uğurlu, 10% uğursuz (random nəticə).
     */
    public function charge(Money $amount): GatewayResult
    {
        // Simulyasiya — real proyektdə burada API çağırışı olardı
        // random_int(1, 10) 1-dən 10-a qədər rəqəm qaytarır
        $isSuccessful = random_int(1, 10) <= 9; // 90% uğur ehtimalı

        if ($isSuccessful) {
            // Uğurlu — unikal tranzaksiya ID-si yaradırıq
            // Real-da bu ID gateway-dən gəlir (məsələn: "ch_1234567890")
            $transactionId = 'cc_' . bin2hex(random_bytes(12));

            return GatewayResult::success($transactionId);
        }

        // Uğursuz — kredit kartı rədd edildi
        return GatewayResult::failure('Kredit kartı rədd edildi: balans çatmır.');
    }

    /**
     * Kredit kartına geri ödəmə simulyasiyası.
     *
     * Real həyatda:
     * - Bank API-sinə refund sorğusu göndərilir
     * - Pul 3-5 iş günü ərzində müştərinin kartına qaytarılır
     */
    public function refund(string $transactionId): bool
    {
        // Simulyasiya — 95% uğur ehtimalı
        return random_int(1, 20) <= 19;
    }
}
