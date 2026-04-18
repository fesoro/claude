<?php

declare(strict_types=1);

namespace Src\Order\Application\Queries\GetOrder;

use Src\Order\Application\DTOs\OrderDTO;
use Src\Order\Domain\Repositories\OrderRepositoryInterface;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Shared\Application\Bus\Query;
use Src\Shared\Application\Bus\QueryHandler;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * GET ORDER HANDLER (CQRS Pattern)
 * ==================================
 * GetOrderQuery-ni emal edib OrderDTO qaytarır.
 *
 * QUERY HANDLER QAYDALARI:
 * 1. Datanı YALNIZ oxuyur — heç vaxt dəyişmir.
 * 2. DTO qaytarır — Entity qaytarmır (domain obyektləri xaricə çıxmaz).
 * 3. Sadə və sürətlidir — mürəkkəb biznes logikası yoxdur.
 *
 * CQRS OPTİMALLAŞDIRMA İMKANLARI (gələcək):
 * - Read model üçün ayrı DB table/view istifadə edə bilərsən.
 * - Redis cache ilə oxuma sürətini artıra bilərsən.
 * - Elasticsearch ilə mürəkkəb axtarış əlavə edə bilərsən.
 */
class GetOrderHandler implements QueryHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /**
     * @param Query $query GetOrderQuery olmalıdır
     * @return OrderDTO Sifarişin DTO-su
     * @throws DomainException Sifariş tapılmadıqda
     */
    public function handle(Query $query): OrderDTO
    {
        /** @var GetOrderQuery $query */

        $order = $this->orderRepository->findById(
            OrderId::fromString($query->orderId()),
        );

        if ($order === null) {
            throw new DomainException("Sifariş tapılmadı: {$query->orderId()}");
        }

        // Domain Entity-ni DTO-ya çevir — domain obyekti xaricə çıxmır
        return OrderDTO::fromEntity($order);
    }
}
