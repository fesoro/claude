<?php

declare(strict_types=1);

namespace Src\Payment\Application\ACL;

use Src\Payment\Domain\Strategies\GatewayResult;
use Src\Payment\Domain\Strategies\PaymentGatewayInterface;
use Src\Product\Domain\ValueObjects\Money;

/**
 * ANTI-CORRUPTION LAYER (ACL) — PAYMENT GATEWAY ACL
 * ==================================================
 *
 * ANTI-CORRUPTION LAYER NƏDİR?
 * =============================
 * ACL — xarici sistemlərlə (3rd party API-lər) bizim domain arasında "qoruyucu divar"dır.
 * Domain-i xarici API-lərin dəyişikliklərindən qoruyur.
 *
 * REAL HƏYAT ANALOGİYASI:
 * ─────────────────────────
 * Tərcüməçi (translator) kimi düşün:
 * - Sən Azərbaycan dilində danışırsan (bizim domain)
 * - Qarşı tərəf Yapon dilində danışır (xarici API — Stripe)
 * - Tərcüməçi (ACL) arada durub hər iki tərəfin dilini çevirir
 *
 * Əgər Yapon tərəf danışıq qaydalarını dəyişsə:
 * - Sən heç nə bilmirsən — tərcüməçi adapatasiya edir
 * - SƏNİN kodun (domain) dəyişmir, yalnız TƏRCÜMƏÇİ (ACL) dəyişir
 *
 * NƏYƏ ACL LAZIMDIR?
 * ==================
 *
 * PROBLEM (ACL OLMADAN):
 * ┌──────────┐                    ┌──────────────┐
 * │  Domain   │───birbaşa asılı──→│  Stripe API  │
 * │  (bizim)  │                    │  (xarici)    │
 * └──────────┘                    └──────────────┘
 *
 * Stripe API-ni v2-dən v3-ə yenilədi:
 * - response formatı dəyişdi: "charge_id" → "transaction_identifier"
 * - status kodları dəyişdi: "success" → "completed"
 * - Nəticə: Bizim domain kodumuz SINIR, hər yerdə dəyişiklik lazımdır!
 *
 * HƏLLİ (ACL İLƏ):
 * ┌──────────┐     ┌──────┐     ┌──────────────┐
 * │  Domain   │────→│  ACL │────→│  Stripe API  │
 * │  (bizim)  │     │      │     │  (xarici)    │
 * └──────────┘     └──────┘     └──────────────┘
 *
 * Stripe API dəyişdikdə:
 * - Yalnız ACL dəyişir — domain kodumuz toxunulmaz qalır!
 * - ACL xarici API-nin "charge_id" sahəsini bizim "transactionId"-yə çevirir.
 *
 * ACL-İN VƏZİFƏLƏRİ:
 * ===================
 * 1. DATA ÇEVİRMƏ (Translation):
 *    Xarici API: {"charge_id": "ch_123", "status": "succeeded"}
 *    Bizim domain: GatewayResult::success("ch_123")
 *
 * 2. XƏTALARı ÇEVİRMƏ (Error Translation):
 *    Xarici API: StripeCardDeclinedException
 *    Bizim domain: GatewayResult::failure("Kart rədd edildi")
 *
 * 3. PROTOKOL ÇEVİRMƏ (Protocol Translation):
 *    Xarici API: REST, SOAP, GraphQL — fərqi yoxdur
 *    Bizim domain: yalnız GatewayResult obyekti görür
 *
 * ACL-İ NƏ VAXT İSTİFADƏ ETMƏK?
 * ==============================
 * - Xarici API ilə inteqrasiya edərkən (Stripe, PayPal, SMS servisi)
 * - Legacy (köhnə) sistemlə əlaqə qurarkən
 * - Fərqli bounded context-lər arası əlaqədə
 * - Xarici data formatı bizim domain dilindən fərqli olanda
 *
 * DDD KONTEKSTİNDƏ:
 * ACL, Bounded Context-lər arasında "tərcüməçi" rolunu oynayır.
 * Hər bounded context-in öz "Ubiquitous Language"-i (dili) var.
 * ACL bu dilləri bir-birinə çevirir.
 */
final class PaymentGatewayACL
{
    /**
     * Ödəniş əməliyyatını ACL vasitəsilə icra et.
     *
     * Bu metod gateway-in charge() metodunu çağırır və nəticəni
     * bizim domain-in anlayacağı formata çevirir.
     *
     * Real proyektdə burada:
     * - Xarici API-nin JSON cavabı parse olunardı
     * - Xarici API-nin xəta kodları bizim xəta mesajlarına çevrilərdi
     * - Xarici API-nin data formatı bizim Value Object-lərə çevrilərdi
     *
     * @param PaymentGatewayInterface $gateway Seçilmiş ödəniş gateway-i
     * @param Money $amount Ödəniş məbləği
     * @return GatewayResult Bizim domain-in anlayacağı nəticə
     */
    public function processCharge(PaymentGatewayInterface $gateway, Money $amount): GatewayResult
    {
        try {
            // Gateway-in charge() metodunu çağır
            // Real proyektdə bu xarici HTTP API çağırışı olardı
            $result = $gateway->charge($amount);

            // Nəticəni bizim domain formatına çevir
            // Bu sadə nümunədə artıq GatewayResult qaytarılır,
            // amma real proyektdə xarici API-nin cavabı fərqli formatlı olardı:
            //
            // NÜMUNƏ — Stripe cavabı:
            // {
            //   "id": "ch_1234567890",
            //   "object": "charge",
            //   "status": "succeeded",    ← bizim "success"-ə çeviririk
            //   "amount": 9999,           ← cent-lərdə gəlir, biz dollar-a çeviririk
            //   "currency": "usd"
            // }
            //
            // ACL çevirməsi:
            // GatewayResult::success("ch_1234567890")

            return $result;
        } catch (\Throwable $e) {
            // Xarici API-nin exception-ını bizim domain formatına çevir
            // Real proyektdə burada fərqli exception tipləri olardı:
            //
            // StripeCardException → "Kart rədd edildi"
            // StripeApiException → "Ödəniş xidməti əlçatmazdır"
            // StripeRateLimitException → "Çox sayda sorğu göndərildi"
            //
            // Beləliklə, bizim domain Stripe-ın exception-larından xəbərsiz qalır.
            return GatewayResult::failure(
                'Ödəniş gateway xətası: ' . $e->getMessage()
            );
        }
    }

    /**
     * Geri ödəmə əməliyyatını ACL vasitəsilə icra et.
     */
    public function processRefund(PaymentGatewayInterface $gateway, string $transactionId): bool
    {
        try {
            return $gateway->refund($transactionId);
        } catch (\Throwable) {
            // Xarici API xətasını gizləyirik — domain yalnız true/false görür
            return false;
        }
    }
}
