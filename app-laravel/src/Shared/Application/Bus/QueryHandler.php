<?php

declare(strict_types=1);

namespace Src\Shared\Application\Bus;

/**
 * QUERY HANDLER (CQRS Pattern)
 * ============================
 * Query Handler — Query-ni qəbul edib nəticəni qaytarır.
 *
 * QAYDALAR:
 * 1. Hər Query-nin BİR Handler-i olur.
 * 2. Handler datanı YALNIZ oxuyur, heç vaxt dəyişmir.
 * 3. Handler DTO qaytarır (Entity deyil!) — domain obyektləri xaricə çıxmamalıdır.
 */
interface QueryHandler
{
    public function handle(Query $query): mixed;
}
