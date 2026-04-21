<?php

declare(strict_types=1);

namespace Src\Product\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Money - Pul məbləğini təmsil edən ValueObject.
 *
 * Bu sinif məbləğ (amount) və valyuta (currency) birlikdə saxlayır.
 * Niyə? Çünki "100" mənasızdır - "100 AZN" və ya "100 USD" olmalıdır.
 *
 * ÖNƏMLİ KONSEPT - Immutability (Dəyişməzlik):
 * - Money obyekti yaradıldıqdan sonra DƏYİŞDİRİLƏ BİLMƏZ.
 * - add() və subtract() metodları YENİ Money obyekti qaytarır, köhnəni dəyişmir.
 * - Bu "bug" (xəta) yaranmasının qarşısını alır.
 *
 * Misal:
 *   $price = new Money(100, 'AZN');
 *   $newPrice = $price->add(new Money(50, 'AZN'));
 *   // $price hələ 100 AZN-dir (dəyişməyib!)
 *   // $newPrice isə 150 AZN-dir (yeni obyekt)
 *
 * Niyə float əvəzinə int istifadə edirik?
 * - Float ilə hesablama xətaları olur: 0.1 + 0.2 = 0.30000000000000004
 * - Ona görə qəpiklərlə (cent) işləyirik: 100 = 1.00 AZN
 * - Bu "minor units" (kiçik vahidlər) adlanır.
 */
final class Money extends ValueObject
{
    /**
     * @param int    $amount   Məbləğ qəpiklərlə (minor units). Məsələn: 1050 = 10.50 AZN
     * @param string $currency Valyuta kodu (ISO 4217). Məsələn: 'AZN', 'USD', 'EUR'
     * @throws DomainException Əgər məbləğ mənfi olarsa
     */
    public function __construct(
        private readonly int $amount,
        private readonly string $currency
    ) {
        if ($amount < 0) {
            throw new DomainException(
                "Pul məbləği mənfi ola bilməz. Verilən dəyər: {$amount}"
            );
        }

        if (empty($currency)) {
            throw new DomainException("Valyuta kodu boş ola bilməz.");
        }
    }

    /**
     * Məbləği qaytarır (qəpiklərlə).
     */
    public function amount(): int
    {
        return $this->amount;
    }

    /**
     * Valyuta kodunu qaytarır.
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * İki pul məbləğini toplayır və YENİ Money qaytarır.
     *
     * Diqqət: Fərqli valyutaları toplamaq olmaz!
     * Məsələn: 100 AZN + 50 USD = XƏTA
     *
     * @throws DomainException Əgər valyutalar fərqlidirsə
     */
    public function add(Money $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Bir pul məbləğini digərindən çıxarır və YENİ Money qaytarır.
     *
     * @throws DomainException Əgər valyutalar fərqlidirsə və ya nəticə mənfidirsə
     */
    public function subtract(Money $other): self
    {
        $this->ensureSameCurrency($other);

        if ($this->amount < $other->amount) {
            throw new DomainException(
                "Çıxma əməliyyatının nəticəsi mənfi ola bilməz. "
                . "{$this->amount} - {$other->amount} = " . ($this->amount - $other->amount)
            );
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Pul məbləğini tam ədədlə vurur və YENİ Money qaytarır.
     *
     * OrderItem::lineTotal() bu metodu çağırır:
     *   $this->price->multiply($this->quantity)
     *   Məsələn: 1050 qəpik × 3 ədəd = 3150 qəpik (31.50 AZN)
     *
     * @param int $factor Vurucu (miqdar, say və s.)
     * @throws DomainException Əgər factor mənfidirsə
     */
    public function multiply(int $factor): self
    {
        if ($factor < 0) {
            throw new DomainException(
                "Vurma əmsalı mənfi ola bilməz. Verilən dəyər: {$factor}"
            );
        }

        return new self($this->amount * $factor, $this->currency);
    }

    /**
     * Money-ni array-ə çevir — serialization üçün.
     * OrderItem::toArray() bu metodu çağırır.
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    /**
     * Valyutaların eyni olmasını yoxlayır.
     * Private metod - yalnız bu sinifin daxilində istifadə olunur.
     */
    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new DomainException(
                "Fərqli valyutalarla əməliyyat aparmaq olmaz: "
                . "{$this->currency} və {$other->currency}"
            );
        }
    }

    /**
     * İki Money obyektinin bərabər olub-olmadığını yoxlayır.
     * Həm məbləğ, həm valyuta eyni olmalıdır.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        // Qəpikləri manata çeviririk: 1050 -> "10.50 AZN"
        return number_format($this->amount / 100, 2) . ' ' . $this->currency;
    }
}
