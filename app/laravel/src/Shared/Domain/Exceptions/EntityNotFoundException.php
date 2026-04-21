<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

/**
 * ENTİTY TAPILMADI XƏTASİ (EntityNotFoundException)
 * ===================================================
 *
 * Bu exception domain səviyyəsində entity ID ilə axtarılıb tapılmadıqda atılır.
 * Məsələn:
 *   - "Sifariş #12345 tapılmadı"
 *   - "Məhsul #99 tapılmadı"
 *
 * DomainException-dan extend edir çünki bu biznes məntiqi xətasıdır —
 * texniki xəta (500) deyil, resursun mövcud olmaması (404) vəziyyətidir.
 *
 * Konstruktor iki parametr qəbul edir:
 *   - $entityType: Entity-nin tipi (məsələn "Məhsul", "Sifariş", "İstifadəçi")
 *   - $id: Axtarılan ID dəyəri
 *
 * İSTİFADƏ NÜMUNƏSİ:
 *   throw new EntityNotFoundException('Sifariş', $orderId);
 *   // Mesaj: "Sifariş (ID: 42) tapılmadı"
 */
final class EntityNotFoundException extends DomainException
{
    public function __construct(
        private readonly string $entityType,
        private readonly string|int $id,
    ) {
        parent::__construct("{$entityType} (ID: {$id}) tapılmadı");
    }

    /**
     * Entity tipini qaytarır (məsələn "Məhsul", "Sifariş")
     */
    public function entityType(): string
    {
        return $this->entityType;
    }

    /**
     * Axtarılan ID dəyərini qaytarır
     */
    public function entityId(): string|int
    {
        return $this->id;
    }
}
