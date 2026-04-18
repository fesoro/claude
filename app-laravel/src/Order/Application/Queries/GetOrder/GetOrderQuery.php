<?php

declare(strict_types=1);

namespace Src\Order\Application\Queries\GetOrder;

use Src\Shared\Application\Bus\Query;

/**
 * GET ORDER QUERY (CQRS Pattern)
 * ================================
 * Bir sifarişin datası oxumaq üçün sorğu.
 *
 * QUERY XATIRLATMASI:
 * - Query = "Mənə göstər!" (sual, sorğu).
 * - Datanı OXUYUR, heç vaxt DƏYİŞMİR (side-effect free).
 * - DTO qaytarır (Entity deyil!).
 *
 * CQRS-in faydası burada aydın görünür:
 * - Command (yazma) və Query (oxuma) tamamilə AYRIDIR.
 * - Oxuma üçün optimallaşdırılmış ayrı DB view istifadə edə bilərsən.
 * - Yazma yükü oxumanı təsir etmir (scalability).
 */
class GetOrderQuery implements Query
{
    public function __construct(
        private readonly string $orderId,
    ) {}

    public function orderId(): string
    {
        return $this->orderId;
    }
}
