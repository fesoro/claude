<?php

declare(strict_types=1);

namespace Src\Payment\Domain\Enums;

/**
 * ÖDƏNİŞ STATUSU ENUM
 * =====================
 * Ödəniş prosesinin müxtəlif mərhələlərini təmsil edən backed enum.
 *
 * STATUS AXINI:
 *   PENDING → PROCESSING → COMPLETED (uğurlu)
 *   PENDING → PROCESSING → FAILED (uğursuz)
 *   COMPLETED → REFUNDED (geri qaytarma)
 *
 * Hər ödəniş PENDING ilə başlayır — gateway-ə göndərildikdə PROCESSING olur,
 * nəticəyə görə COMPLETED və ya FAILED olur.
 */
enum PaymentStatusEnum: string
{
    /**
     * Ödəniş yaradılıb, hələ emal olunmayıb.
     * Bu ilkin statusdur — ödəniş gateway-ə göndərilməmişdir.
     */
    case PENDING = 'pending';

    /**
     * Ödəniş gateway tərəfindən emal olunur.
     * Bu aralıq statusdur — nəticə hələ məlum deyil.
     */
    case PROCESSING = 'processing';

    /**
     * Ödəniş uğurla tamamlanıb.
     * Pul müştərinin hesabından çıxılıb, satıcının hesabına daxil olub.
     */
    case COMPLETED = 'completed';

    /**
     * Ödəniş uğursuz olub.
     * Səbəb: balans çatışmazlığı, kartın müddəti bitib, gateway xətası və s.
     */
    case FAILED = 'failed';

    /**
     * Ödəniş geri qaytarılıb (refund).
     * Yalnız COMPLETED statusdan REFUNDED-ə keçid mümkündür.
     */
    case REFUNDED = 'refunded';

    /**
     * Statusun Azərbaycan dilində etiketini qaytarır.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING    => 'Gözləmədə',
            self::PROCESSING => 'Emal olunur',
            self::COMPLETED  => 'Tamamlandı',
            self::FAILED     => 'Uğursuz',
            self::REFUNDED   => 'Geri qaytarıldı',
        };
    }

    /**
     * Bu statusun son (terminal) status olub-olmadığını yoxlayır.
     * Terminal statuslar: FAILED, REFUNDED — bunlardan sonra keçid yoxdur.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::FAILED, self::REFUNDED], strict: true);
    }
}
