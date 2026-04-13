<?php

declare(strict_types=1);

namespace Src\Shared\Application\Bus;

/**
 * COMMAND (CQRS Pattern)
 * ======================
 * Command — sistemdə dəyişiklik etmək üçün göndərilən sorğudur (intent).
 *
 * ƏSAS QAYDALAR:
 * 1. Bir Command = bir əməliyyat: CreateOrder, CancelOrder, ProcessPayment
 * 2. Command imperativ formada adlandırılır (əmr forması).
 * 3. Command immutable (dəyişməz) olmalıdır — yaradıldıqdan sonra dəyişmir.
 * 4. Command heç vaxt data qaytarmır (void) — yalnız Query data qaytarır.
 *    (Praktikada bəzən ID qaytarılır, amma bu əsas prinsipdir)
 *
 * NÜMUNƏ:
 * CreateOrderCommand → sifarişi yarat
 * CancelOrderCommand → sifarişi ləğv et
 *
 * Command vs Event FƏRQI:
 * - Command: "Bunu ET!" (gələcək, əmr)
 * - Event: "Bu OLDU!" (keçmiş, fakt)
 */
interface Command
{
}
