<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Strategies;

use Src\Product\Domain\ValueObjects\Money;

/**
 * BANK TRANSFER GATEWAY — Concrete Strategy
 * ==========================================
 * Bank köçürməsi ilə ödəniş prosesini simulyasiya edir.
 *
 * BANK KÖÇÜRMƏSİ XÜSUSİYYƏTLƏRİ:
 * - Kredit kartı və PayPal-dan daha yavaşdır (1-3 iş günü)
 * - Amma komissiya daha azdır
 * - Adətən böyük məbləğli ödənişlər üçün istifadə olunur
 *
 * STRATEGİYA PATTERN-İN GÜCLÜLÜYÜNÜYÜ BURADA GÖRMƏK OLAR:
 * Hər gateway-in öz xüsusiyyətləri var, amma hamısı eyni interface-i implementasiya edir.
 * Çağıran kod (PaymentApplicationService) hansı gateway istifadə olunduğunu bilmir.
 * $gateway->charge($money) — bu kredit kartı da ola bilər, bank köçürməsi də.
 */
final class BankTransferGateway implements PaymentGatewayInterface
{
    public function charge(Money $amount): GatewayResult
    {
        // Simulyasiya — bank köçürməsi daha etibarlıdır
        $isSuccessful = random_int(1, 10) <= 9;

        if ($isSuccessful) {
            // "bt_" prefiksi bank transfer olduğunu göstərir
            $transactionId = 'bt_' . bin2hex(random_bytes(12));

            return GatewayResult::success($transactionId);
        }

        return GatewayResult::failure('Bank köçürməsi uğursuz oldu: hesab məlumatları yanlışdır.');
    }

    public function refund(string $transactionId): bool
    {
        // Bank köçürməsində refund daha çətin ola bilər
        return random_int(1, 10) <= 8; // 80% uğur ehtimalı
    }
}
