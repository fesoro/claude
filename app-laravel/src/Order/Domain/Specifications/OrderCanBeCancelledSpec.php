<?php

declare(strict_types=1);

namespace Src\Order\Domain\Specifications;

use Src\Order\Domain\Entities\Order;
use Src\Shared\Domain\Specification;

/**
 * ORDER CAN BE CANCELLED SPECIFICATION
 * =====================================
 * Sifarişin ləğv edilə bilib-bilməyəcəyini yoxlayan Specification.
 *
 * SPECIFICATION PATTERN XATIRLATMASI:
 * - Biznes qaydası ayrı class-da olur.
 * - Yenidən istifadə edilə bilər (reusable).
 * - Test yazmaq çox asandır.
 * - if/else zəncirlərini azaldır.
 *
 * QAYDA:
 * Sifariş yalnız PENDING (gözləyir) və ya CONFIRMED (təsdiqlənib) statusunda
 * olduqda ləğv edilə bilər. Ödəniş edildikdən sonra (PAID, SHIPPED, DELIVERED)
 * ləğv etmək olmaz — bunun üçün refund prosesi lazımdır.
 *
 * İSTİFADƏ NÜMUNƏSI:
 *   $spec = new OrderCanBeCancelledSpec();
 *
 *   if ($spec->isSatisfiedBy($order)) {
 *       $order->cancel('Müştəri ləğv etdi');
 *   } else {
 *       throw new DomainException('Sifariş ləğv edilə bilməz');
 *   }
 *
 * KOMBİNASİYA NÜMUNƏSI:
 *   $canCancel = (new OrderCanBeCancelledSpec())->and(new OrderBelongsToUserSpec($userId));
 *   // Sifariş ləğv edilə bilər VƏ bu istifadəçiyə aiddir
 */
class OrderCanBeCancelledSpec extends Specification
{
    /**
     * Sifarişin ləğv edilə bilməsini yoxla.
     *
     * Yalnız bu statuslarda ləğv etmək olar:
     * - PENDING: Sifariş hələ təsdiqlənməyib, asanlıqla ləğv olunur.
     * - CONFIRMED: Təsdiqlənib amma ödəniş hələ edilməyib, ləğv oluna bilər.
     *
     * Bu statuslarda ləğv etmək OLMAZ:
     * - PAID: Ödəniş edilib, refund lazımdır (ayrı proses).
     * - SHIPPED: Artıq göndərilib, geri qaytarma lazımdır.
     * - DELIVERED: Çatdırılıb, geri qaytarma lazımdır.
     * - CANCELLED: Artıq ləğv edilib.
     *
     * @param mixed $candidate Order entity-si
     * @return bool Ləğv edilə bilərsə true, əks halda false
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (!$candidate instanceof Order) {
            return false;
        }

        // Yalnız PENDING və ya CONFIRMED statusunda ləğv etmək olar
        return $candidate->status()->isPending()
            || $candidate->status()->isConfirmed();
    }
}
