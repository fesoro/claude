<?php

declare(strict_types=1);

namespace Src\Order\Domain\Policies;

use Src\Order\Domain\Entities\Order;
use Src\Shared\Domain\DomainPolicy;

/**
 * SİFARİŞ LƏĞV ETMƏ POLİCY-si — Domain Səviyyəsində Avtorizasiya
 * ==================================================================
 *
 * BU QAYDALAR:
 * ============
 * 1. Sifarişin sahibi ləğv edə bilər (öz sifarişi).
 * 2. Admin istənilən sifarişi ləğv edə bilər.
 * 3. Yalnız PENDING və ya CONFIRMED statusunda ləğv etmək olar.
 * 4. Ödənilmiş sifariş ləğv edilə bilməz (refund lazımdır).
 *
 * NƏYƏ LARAVEL POLİCY-DƏ DEYİL?
 * ================================
 * Laravel Policy (app/Policies/OrderPolicy.php) HTTP layer-dədir.
 * Amma "3 günlük sifarişi ləğv etmək olmaz" qaydası BİZNES qaydasıdır.
 * Bu qayda console, queue, API, webhook — hər yerdə eyni olmalıdır.
 *
 * Laravel Policy: "Bu istifadəçi authenticated-dirmi?" (sadə icazə)
 * Domain Policy: "Bu istifadəçi BU sifarişi BU statusda ləğv edə bilərmi?" (biznes qaydası)
 *
 * TEST:
 * =====
 * Domain Policy-ni test etmək çox asandır:
 * ```php
 * $policy = new OrderCancellationPolicy();
 * $this->assertTrue($policy->isAllowed($owner, $pendingOrder));
 * $this->assertFalse($policy->isAllowed($otherUser, $pendingOrder));
 * $this->assertFalse($policy->isAllowed($owner, $paidOrder));
 * ```
 * HTTP request, controller, middleware — heç biri lazım deyil.
 */
final class OrderCancellationPolicy extends DomainPolicy
{
    /**
     * @param OrderActor $actor Əməliyyatı icra edən istifadəçi
     * @param Order      $subject Ləğv ediləcək sifariş
     */
    public function isAllowed(mixed $actor, mixed $subject): bool
    {
        /** @var OrderActor $actor */
        /** @var Order $subject */

        // Admin hər şeyi edə bilər
        if ($actor->isAdmin) {
            return true;
        }

        // Sifarişin sahibi olmalıdır
        if ($actor->userId !== $subject->userId()) {
            return false;
        }

        // Yalnız pending və ya confirmed statusunda ləğv etmək olar
        $status = $subject->status();
        if (!$status->isPending() && !$status->isConfirmed()) {
            return false;
        }

        return true;
    }

    protected function denialReason(mixed $actor, mixed $subject): string
    {
        /** @var OrderActor $actor */
        /** @var Order $subject */

        if ($actor->userId !== $subject->userId() && !$actor->isAdmin) {
            return 'Yalnız sifarişin sahibi və ya admin sifarişi ləğv edə bilər.';
        }

        $status = $subject->status();
        if (!$status->isPending() && !$status->isConfirmed()) {
            return "Sifariş '{$status->value()}' statusundadır. Yalnız 'pending' və ya 'confirmed' statusunda ləğv etmək olar.";
        }

        return 'Sifariş ləğv edilə bilməz.';
    }
}

/**
 * ORDER ACTOR — Əməliyyatı icra edən istifadəçinin minimal məlumatları.
 *
 * NƏYƏ AYRI CLASS?
 * User Entity birbaşa Order context-ə gəlməməlidir (bounded context sərhədi).
 * Actor — sadəcə "kim edir?" sualına cavab verən minimal DTO-dur.
 * Bu, Anti-Corruption Layer prinsipinə uyğundur.
 */
final readonly class OrderActor
{
    public function __construct(
        public string $userId,
        public bool $isAdmin = false,
    ) {}
}
