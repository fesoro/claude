<?php

declare(strict_types=1);

namespace Src\Order\Domain\Services;

use Src\Order\Domain\Entities\Order;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * ORDER DOMAIN SERVICE — Aggregate-lər Arası Biznes Məntiqi
 * ==========================================================
 *
 * DOMAIN SERVICE NƏDİR?
 * =====================
 * Domain Service — heç bir aggregate-ə aid olmayan, amma domain-ə aid olan biznes məntiqidir.
 *
 * Entity/Aggregate Root-a aid olan məntiq → Entity-nin metodu olur.
 * Bir neçə aggregate-ə toxunan və ya heç bir entity-yə aid olmayan məntiq → Domain Service olur.
 *
 * NÜMUNƏLƏR:
 * - Qiymət hesablama (Product qiymətinə + Order endiriminə bağlıdır)
 * - Transfer (iki Account aggregate arasında pul köçürmə)
 * - Uyğunluq yoxlaması (Rider + Driver matching)
 *
 * DOMAIN SERVICE ≠ APPLICATION SERVICE:
 * =====================================
 * Domain Service: Biznes qaydası (endirim hesablama, qiymət müqayisəsi)
 * Application Service: Use case orkestrasyonu (command handle etmə, repository çağırma)
 *
 * Domain Service heç vaxt:
 * - Repository çağırmır (data almır — ona verilir)
 * - Transaction idarə etmir
 * - Event dispatch etmir
 * Bu işləri Application layer edir.
 *
 * ANALOGİYA:
 * Restoranda: Aşpaz (Domain Service) yeməyi hazırlayır — ona materiallar verilir.
 * Ofisiant (Application Service) sifarişi alır, materialları gətirir, yeməyi müştəriyə aparır.
 * Aşpaz heç vaxt anbara getmir — ona lazımi materiallar verilir.
 *
 * STATELESS:
 * ==========
 * Domain Service-in heç bir internal state-i yoxdur.
 * Eyni input → həmişə eyni output. Heç nə yadda saxlamır.
 * Bu, test yazmağı çox asanlaşdırır.
 */
class OrderDomainService
{
    /**
     * ENDİRİM QATEQORİYALARI
     *
     * Real layihədə bunlar DB-dən gələrdi. Burada domain qaydası kimi hardcode edirik
     * çünki bu biznes qaydası — "1000 AZN-dən yuxarı sifarişlərə 10% endirim"
     * məntiqi domain-ə aiddir, konfiqurasiyaya deyil.
     */
    private const BULK_DISCOUNT_THRESHOLD = 100000; // 1000 AZN (qəpiklərlə)
    private const BULK_DISCOUNT_PERCENTAGE = 10;

    private const VIP_DISCOUNT_PERCENTAGE = 15;

    private const MAX_TOTAL_DISCOUNT_PERCENTAGE = 25;

    /**
     * SİFARİŞ ÜÇÜN SON QİYMƏTİ HESABLA
     * ===================================
     * Bu metod bir neçə aggregate-dən gələn məlumatı birləşdirib endirim hesablayır.
     * Order aggregate-in öz metodu ola bilməz çünki:
     * 1. User-in VIP olub-olmadığını Order bilmir (User aggregate-ə aiddir).
     * 2. Kampaniya endirimlərini Order bilmir (ayrı bounded context ola bilər).
     *
     * @param int  $subtotal       Sifariş cəmi (qəpiklərlə)
     * @param bool $isVipCustomer  Müştəri VIP-dirmi (User context-dən gəlir)
     * @param int  $orderCount     Müştərinin keçmiş sifariş sayı (statistika üçün)
     *
     * @return OrderPriceCalculation Hesablanmış qiymət detalları
     */
    public function calculateOrderPrice(
        int $subtotal,
        bool $isVipCustomer,
        int $orderCount,
    ): OrderPriceCalculation {
        $discounts = [];

        // Topdan endirim — böyük sifarişlər üçün
        if ($subtotal >= self::BULK_DISCOUNT_THRESHOLD) {
            $discounts[] = new DiscountLine(
                type: 'bulk',
                percentage: self::BULK_DISCOUNT_PERCENTAGE,
                reason: '1000 AZN-dən yuxarı sifariş endirimi',
            );
        }

        // VIP müştəri endirimi
        if ($isVipCustomer) {
            $discounts[] = new DiscountLine(
                type: 'vip',
                percentage: self::VIP_DISCOUNT_PERCENTAGE,
                reason: 'VIP müştəri endirimi',
            );
        }

        // Sadiqlik endirimi — 10-dan çox sifariş vermiş müştərilər
        if ($orderCount >= 10) {
            $discounts[] = new DiscountLine(
                type: 'loyalty',
                percentage: 5,
                reason: '10+ sifariş sadiqlik endirimi',
            );
        }

        // Cəmi endirim faizi — maksimum həddlə məhdudlaşdırılır
        $totalDiscountPercentage = array_sum(array_map(
            fn (DiscountLine $d) => $d->percentage,
            $discounts,
        ));

        /**
         * BİZNES QAYDASI: Endirim heç vaxt 25%-i keçə bilməz.
         * Bu, "invariant" adlanır — heç bir halda pozula bilməyən qayda.
         * Invariant domain-ə aiddir, UI-a və ya application-a deyil.
         */
        $totalDiscountPercentage = min($totalDiscountPercentage, self::MAX_TOTAL_DISCOUNT_PERCENTAGE);

        $discountAmount = (int) round($subtotal * $totalDiscountPercentage / 100);
        $finalAmount = $subtotal - $discountAmount;

        return new OrderPriceCalculation(
            subtotal: $subtotal,
            discountAmount: $discountAmount,
            discountPercentage: $totalDiscountPercentage,
            finalAmount: $finalAmount,
            appliedDiscounts: $discounts,
        );
    }

    /**
     * SİFARİŞ BÖLÜNMƏSİ MÜMKÜNDÜRmü?
     * ==================================
     * Bir sifarişi bir neçə göndərişə bölmək lazım olanda yoxlanılır.
     * Bu məntiq Order aggregate-ə aid deyil çünki göndəriş xərcləri
     * Shipping bounded context-dən gəlir.
     *
     * @param array<array{weight: float, fragile: bool}> $items Sifariş itemləri
     * @param float $maxWeight Bir göndəriş üçün max çəki (kq)
     *
     * @return array<array<int>> Bölünmüş qruplar (item index-ləri)
     */
    public function splitOrderForShipping(array $items, float $maxWeight = 30.0): array
    {
        // Ağır/kövrək əşyaları ayır
        $fragileItems = [];
        $normalItems = [];

        foreach ($items as $index => $item) {
            if ($item['fragile']) {
                $fragileItems[] = $index;
            } else {
                $normalItems[] = $index;
            }
        }

        $groups = [];

        // Kövrək əşyalar ayrı göndərişdə
        if (!empty($fragileItems)) {
            $groups[] = $fragileItems;
        }

        // Normal əşyaları çəki limitinə görə qrupla
        $currentGroup = [];
        $currentWeight = 0.0;

        foreach ($normalItems as $index) {
            $itemWeight = $items[$index]['weight'];

            if ($currentWeight + $itemWeight > $maxWeight && !empty($currentGroup)) {
                $groups[] = $currentGroup;
                $currentGroup = [];
                $currentWeight = 0.0;
            }

            $currentGroup[] = $index;
            $currentWeight += $itemWeight;
        }

        if (!empty($currentGroup)) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }
}

/**
 * ENDİRİM SƏTRİ — Value Object
 * Hər tətbiq olunan endirimin detallarını saxlayır.
 */
final readonly class DiscountLine
{
    public function __construct(
        public string $type,
        public int $percentage,
        public string $reason,
    ) {}
}

/**
 * QİYMƏT HESABLAMA NƏTİCƏSİ — Value Object
 * Domain Service-in qaytardığı hesablama detalları.
 * Immutable — yaradıldıqdan sonra dəyişdirilə bilməz.
 */
final readonly class OrderPriceCalculation
{
    /**
     * @param DiscountLine[] $appliedDiscounts
     */
    public function __construct(
        public int $subtotal,
        public int $discountAmount,
        public int $discountPercentage,
        public int $finalAmount,
        public array $appliedDiscounts,
    ) {}

    public function hasDiscount(): bool
    {
        return $this->discountAmount > 0;
    }
}
