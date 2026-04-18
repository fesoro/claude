<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * VALUE OBJECT (DDD Pattern)
 * ==========================
 * Value Object — dəyəri ilə müəyyən edilən, dəyişməz (immutable) obyektdir.
 *
 * ƏSAS XÜSUSIYYƏTLƏR:
 * 1. Immutable (dəyişməz): Yaradıldıqdan sonra dəyişdirilə bilməz.
 *    Dəyişiklik lazımdırsa, yeni obyekt yaradılır.
 *
 * 2. Equality by value: ID-si yoxdur, dəyərlərinə görə müqayisə olunur.
 *    Money(100, 'USD') == Money(100, 'USD') → true
 *
 * 3. Self-validating: Yaradıldıqda öz qaydalarını yoxlayır.
 *    Email('invalid') → Exception atacaq.
 *
 * NÜMUNƏLƏR:
 * - Email, Money, Address, PhoneNumber, OrderStatus
 * - Bunların heç birinin ayrıca ID-si yoxdur.
 *
 * NƏYƏ LAZIMDIR?
 * - Primitive Obsession anti-pattern-dən qurtarır.
 *   string $email əvəzinə Email $email istifadə edirsən.
 * - Biznes qaydaları Value Object daxilində olur (məs: email formatı).
 */
abstract class ValueObject
{
    /**
     * İki Value Object-i müqayisə et.
     * Bütün dəyərləri eyni olan iki Value Object bərabərdir.
     */
    abstract public function equals(ValueObject $other): bool;

    /**
     * Value Object-i string-ə çevir (debug və log üçün).
     */
    abstract public function __toString(): string;
}
