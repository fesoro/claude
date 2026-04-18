<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Strategies;

use Src\Product\Domain\ValueObjects\Money;

/**
 * PAYMENT GATEWAY INTERFACE — STRATEGY PATTERN
 * =============================================
 *
 * STRATEGY PATTERN NƏDİR?
 * ========================
 * Strategy — davranış (behavior) design pattern-dir.
 * Eyni işi fərqli yollarla etmək lazım olanda istifadə olunur.
 *
 * REAL HƏYAT ANALOGİYASI:
 * Bakıdan İstanbula getmək istəyirsən. Neçə yol var?
 * - Təyyarə ilə (sürətli, baha)
 * - Avtobus ilə (yavaş, ucuz)
 * - Maşın ilə (orta)
 * Hər biri "nəqliyyat strategiyası"-dır. Nəticə eynidir (İstanbula çatırsan), amma yol fərqlidir.
 *
 * BİZİM NÜMUNƏMİZDƏ:
 * Müştəri ödəniş etmək istəyir. Neçə yol var?
 * - Kredit kartı ilə (CreditCardGateway)
 * - PayPal ilə (PayPalGateway)
 * - Bank köçürməsi ilə (BankTransferGateway)
 * Nəticə eynidir (pul alınır), amma proses fərqlidir.
 *
 * STRATEGY PATTERN-İN STRUKTURU:
 * ┌──────────────────────────────┐
 * │  PaymentGatewayInterface     │ ← Strategy Interface (bu fayl)
 * │  + charge(Money): Result     │
 * │  + refund(txId): bool        │
 * └──────────────┬───────────────┘
 *                │ implements
 *    ┌───────────┼───────────────┐
 *    │           │               │
 *    ▼           ▼               ▼
 * ┌────────┐ ┌────────┐ ┌──────────────┐
 * │CreditCard│ │ PayPal │ │ BankTransfer │ ← Concrete Strategies
 * │Gateway  │ │Gateway │ │ Gateway      │
 * └────────┘ └────────┘ └──────────────┘
 *
 * STRATEGY PATTERN-İN ÜSTÜNLÜKLƏR:
 * 1. Open/Closed Principle: Yeni gateway əlavə etmək üçün mövcud kodu dəyişmirik,
 *    yeni sinif yaradırıq (məsələn: CryptoGateway).
 * 2. if/else zəncirindən qurtarırıq:
 *    ƏVVƏLKİ (pis):  if (method == 'card') { ... } elseif (method == 'paypal') { ... }
 *    İNDİKİ (yaxşı): $gateway->charge($money)  — hansı gateway olduğu əhəmiyyətli deyil.
 * 3. Test yazmaq asandır: Hər gateway-i ayrıca test edə bilərik.
 *
 * STRATEGY PATTERN-İ NƏ VAXT İSTİFADƏ ETMƏK?
 * - Eyni işi fərqli alqoritmlərlə etmək lazımdırsa
 * - Runtime-da (proqram işləyərkən) alqoritm dəyişə bilərsə
 * - Çoxlu if/else və ya switch/case varsa
 *
 * @see PaymentStrategyResolver — hansı gateway-in seçilməsini idarə edir
 */
interface PaymentGatewayInterface
{
    /**
     * Ödəniş əməliyyatı — pulu müştəridən al.
     *
     * @param Money $amount Alınacaq məbləğ
     * @return GatewayResult Əməliyyatın nəticəsi (uğurlu/uğursuz, transactionId)
     */
    public function charge(Money $amount): GatewayResult;

    /**
     * Geri ödəmə — pulu müştəriyə qaytar.
     *
     * @param string $transactionId Əvvəlki charge əməliyyatının ID-si
     * @return bool Geri ödəmə uğurludursa true
     */
    public function refund(string $transactionId): bool;
}
