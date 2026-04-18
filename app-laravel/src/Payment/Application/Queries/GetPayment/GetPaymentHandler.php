<?php

declare(strict_types=1);

namespace Src\Payment\Application\Queries\GetPayment;

use Src\Payment\Application\DTOs\PaymentDTO;
use Src\Payment\Domain\Repositories\PaymentRepositoryInterface;
use Src\Payment\Domain\ValueObjects\PaymentId;
use Src\Shared\Application\Bus\Query;
use Src\Shared\Application\Bus\QueryHandler;
use Src\Shared\Domain\Exceptions\EntityNotFoundException;

/**
 * GET PAYMENT HANDLER (CQRS Pattern)
 * ====================================
 * GetPaymentQuery-ni emal edib PaymentDTO qaytarır.
 *
 * GetOrderHandler ilə eyni yanaşma:
 * 1. Repository-dən entity-ni tap.
 * 2. Entity-ni DTO-ya çevir (domain dəyər xaricə çıxmır).
 * 3. Tapılmadıqda EntityNotFoundException at.
 *
 * EntityNotFoundException vs DomainException:
 * - DomainException: Biznes qaydası pozuldu (422).
 * - EntityNotFoundException: Resurs tapılmadı (404). DomainException-un alt sinfidir.
 * bootstrap/app.php-dəki exception handler fərqli HTTP status kod qaytarır.
 */
class GetPaymentHandler implements QueryHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
    ) {}

    public function handle(Query $query): PaymentDTO
    {
        /** @var GetPaymentQuery $query */

        $payment = $this->paymentRepository->findById(
            new PaymentId($query->paymentId()),
        );

        if ($payment === null) {
            throw new EntityNotFoundException("Ödəniş tapılmadı: {$query->paymentId()}");
        }

        return PaymentDTO::fromEntity($payment);
    }
}
