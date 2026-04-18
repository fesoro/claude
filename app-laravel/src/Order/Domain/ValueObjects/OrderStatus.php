<?php

declare(strict_types=1);

namespace Src\Order\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;

/**
 * ORDER STATUS (Value Object + State Machine Pattern)
 * ====================================================
 * Sifarişin vəziyyətini (statusunu) idarə edən Value Object.
 *
 * STATE MACHINE (VƏZIYYƏT MAŞINİ) NƏDİR?
 * - Obyektin müəyyən vəziyyətləri (state) və keçidləri (transition) var.
 * - Hər vəziyyətdən yalnız müəyyən vəziyyətlərə keçmək olar.
 * - Yanlış keçid mümkün deyil — bu biznes qaydalarını qoruyur.
 *
 * SİFARİŞ VƏZIYYƏT DİAQRAMI:
 * ┌─────────┐     ┌───────────┐     ┌──────┐     ┌─────────┐     ┌───────────┐
 * │ PENDING │────→│ CONFIRMED │────→│ PAID │────→│ SHIPPED │────→│ DELIVERED │
 * └────┬────┘     └─────┬─────┘     └──────┘     └─────────┘     └───────────┘
 *      │                │
 *      │   ┌───────────┐│
 *      └──→│ CANCELLED │←┘
 *          └───────────┘
 *
 * PENDING (Gözləyir):
 *   → CONFIRMED: Admin sifarişi təsdiqləyəndə
 *   → CANCELLED: Müştəri və ya sistem ləğv edəndə
 *
 * CONFIRMED (Təsdiqlənib):
 *   → PAID: Ödəniş uğurla tamamlananda
 *   → CANCELLED: Ödəniş baş tutmayanda
 *
 * PAID (Ödənilib):
 *   → SHIPPED: Karqo göndəriləndə
 *
 * SHIPPED (Göndərilib):
 *   → DELIVERED: Müştəri qəbul edəndə
 *
 * DELIVERED (Çatdırılıb):
 *   → (son vəziyyət, keçid yoxdur)
 *
 * CANCELLED (Ləğv edilib):
 *   → (son vəziyyət, keçid yoxdur)
 *
 * NƏYƏ BU QƏDƏR DETALLİ?
 * - Real e-commerce sistemlərində sifarişin vəziyyəti çox vacibdir.
 * - Yanlış keçid (məs: DELIVERED → PENDING) ciddi biznes xətasıdır.
 * - State Machine bu xətaların qarşısını avtomatik alır.
 */
class OrderStatus extends ValueObject
{
    // Mövcud vəziyyətlər (statuslar)
    public const PENDING = 'pending';       // Sifariş yaradılıb, gözləyir
    public const CONFIRMED = 'confirmed';   // Admin təsdiqləyib
    public const PAID = 'paid';             // Ödəniş tamamlanıb
    public const SHIPPED = 'shipped';       // Karqoya verilib
    public const DELIVERED = 'delivered';   // Müştəriyə çatdırılıb
    public const CANCELLED = 'cancelled';   // Ləğv edilib

    /**
     * İcazə verilən keçidlər — State Machine-in əsas qaydaları.
     * Hər status-dan hansı statuslara keçmək olar, burada müəyyən edilir.
     *
     * Bu array-i oxumaq çox sadədir:
     * 'pending' => ['confirmed', 'cancelled']
     * Yəni: PENDING vəziyyətindən yalnız CONFIRMED və ya CANCELLED-ə keçmək olar.
     */
    private const ALLOWED_TRANSITIONS = [
        self::PENDING   => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED => [self::PAID, self::CANCELLED],
        self::PAID      => [self::SHIPPED],
        self::SHIPPED   => [self::DELIVERED],
        self::DELIVERED => [],  // Son vəziyyət — heç yerə keçmək olmaz
        self::CANCELLED => [],  // Son vəziyyət — heç yerə keçmək olmaz
    ];

    /**
     * Bütün düzgün status dəyərləri.
     */
    private const VALID_STATUSES = [
        self::PENDING,
        self::CONFIRMED,
        self::PAID,
        self::SHIPPED,
        self::DELIVERED,
        self::CANCELLED,
    ];

    /**
     * @param string $value Status dəyəri
     * @throws \InvalidArgumentException Yanlış status dəyəri verildikdə
     */
    public function __construct(
        private readonly string $value,
    ) {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Yanlış sifariş statusu: '{$value}'. İcazə verilən statuslar: " . implode(', ', self::VALID_STATUSES)
            );
        }
    }

    /**
     * Hər statusu yaradan factory method-lar.
     * Bu method-lar kodu daha oxunaqlı edir:
     * OrderStatus::pending()  əvəzinə  new OrderStatus('pending')
     */
    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function confirmed(): self
    {
        return new self(self::CONFIRMED);
    }

    public static function paid(): self
    {
        return new self(self::PAID);
    }

    public static function shipped(): self
    {
        return new self(self::SHIPPED);
    }

    public static function delivered(): self
    {
        return new self(self::DELIVERED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    /**
     * STATE MACHINE-in ən vacib metodu — keçid mümkündürmü?
     *
     * Nümunə:
     *   $status = OrderStatus::pending();
     *   $status->canTransitionTo(OrderStatus::confirmed()); // true ✓
     *   $status->canTransitionTo(OrderStatus::delivered()); // false ✗
     *
     * Bu metod ALLOWED_TRANSITIONS array-indən yoxlayır:
     * 1. Cari statusu tap (məs: 'pending')
     * 2. Onun icazə verilən keçidlər siyahısına bax: ['confirmed', 'cancelled']
     * 3. Hədəf status bu siyahıdadırsa → true, deyilsə → false
     */
    public function canTransitionTo(self $newStatus): bool
    {
        $allowedNextStatuses = self::ALLOWED_TRANSITIONS[$this->value] ?? [];

        return in_array($newStatus->value, $allowedNextStatuses, true);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Status yoxlama method-ları — if/else əvəzinə istifadə olunur.
     * if ($status->value() === 'pending') əvəzinə if ($status->isPending())
     */
    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->value === self::CONFIRMED;
    }

    public function isPaid(): bool
    {
        return $this->value === self::PAID;
    }

    public function isShipped(): bool
    {
        return $this->value === self::SHIPPED;
    }

    public function isDelivered(): bool
    {
        return $this->value === self::DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
