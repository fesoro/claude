<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Strategies;

use Src\Product\Domain\ValueObjects\Money;

/**
 * PAYPAL GATEWAY — Concrete Strategy
 * ===================================
 * PayPal ilə ödəniş prosesini simulyasiya edir.
 *
 * PAYPAL ÖDƏNIŞ AXINI (real həyatda):
 * 1. Müştəri PayPal-a yönləndirilir
 * 2. PayPal hesabına daxil olur
 * 3. Ödənişi təsdiqləyir
 * 4. Bizim saytımıza geri yönləndirilir
 * 5. Biz PayPal API-dən ödənişi capture edirik
 *
 * Bu proses kredit kartından fərqlidir — redirect (yönləndirmə) olur.
 * Amma Strategy pattern sayəsində çağıran kod bunun fərqinə varmır.
 */
final class PayPalGateway implements PaymentGatewayInterface
{
    public function charge(Money $amount): GatewayResult
    {
        // Simulyasiya — PayPal adətən daha etibarlıdır
        $isSuccessful = random_int(1, 10) <= 9;

        if ($isSuccessful) {
            $transactionId = 'pp_' . bin2hex(random_bytes(12));

            return GatewayResult::success($transactionId);
        }

        return GatewayResult::failure('PayPal hesabında kifayət qədər vəsait yoxdur.');
    }

    public function refund(string $transactionId): bool
    {
        return random_int(1, 20) <= 19;
    }
}
