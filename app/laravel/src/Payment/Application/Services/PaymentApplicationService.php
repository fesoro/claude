<?php

declare(strict_types=1);

namespace Src\Payment\Application\Services;

use Src\Payment\Application\Commands\ProcessPayment\ProcessPaymentCommand;
use Src\Payment\Application\DTOs\PaymentDTO;
use Src\Payment\Application\DTOs\ProcessPaymentDTO;
use Src\Payment\Domain\Repositories\PaymentRepositoryInterface;
use Src\Payment\Domain\ValueObjects\PaymentId;
use Src\Shared\Application\Bus\CommandBus;

/**
 * PAYMENT APPLICATION SERVICE
 * ===========================
 * Ödəniş iş axınını (workflow) koordinasiya edən servisdir.
 *
 * APPLICATION SERVICE vs DOMAIN SERVICE FƏRQI:
 * ─────────────────────────────────────────────
 * APPLICATION SERVICE (bu sinif):
 * - İş axınını idarə edir (orchestration)
 * - DTO-nu Command-a çevirir
 * - CommandBus vasitəsilə Handler-i çağırır
 * - Transaction idarə edir
 * - Biznes qaydası YOX — yalnız koordinasiya
 *
 * DOMAIN SERVICE (PaymentStrategyResolver):
 * - Biznes qaydası icra edir (hansı gateway-i seçmək)
 * - Domain obyektləri ilə işləyir
 * - Heç bir infrastructure asılılığı yoxdur
 *
 * ANALOGİYA:
 * Application Service = Restoran meneceri (sifarişi qəbul edir, aşpaza ötürür, müştəriyə verir)
 * Domain Service = Aşpaz (yeməyi hazırlayır — əsl biznes logikası)
 */
final class PaymentApplicationService
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly PaymentRepositoryInterface $paymentRepository,
    ) {
    }

    /**
     * Yeni ödəniş prosesini başlat.
     *
     * AXIN:
     * 1. ProcessPaymentDTO-dan ProcessPaymentCommand yarat
     * 2. CommandBus vasitəsilə Handler-ə göndər
     * 3. Handler ödənişi emal edir və ID qaytarır
     * 4. ID ilə ödənişi DB-dən oxu
     * 5. PaymentDTO olaraq qaytar
     */
    public function processPayment(ProcessPaymentDTO $dto): PaymentDTO
    {
        // DTO-dan Command yarat — Application layer data-nı Command-a çevirir
        $command = new ProcessPaymentCommand(
            orderId: $dto->orderId,
            amount: $dto->amount,
            currency: $dto->currency,
            paymentMethod: $dto->paymentMethod,
        );

        // CommandBus Command-ı uyğun Handler-ə yönləndirir
        /** @var string $paymentId */
        $paymentId = $this->commandBus->dispatch($command);

        // Yaradılmış ödənişi DB-dən oxu və DTO olaraq qaytar
        $payment = $this->paymentRepository->findById(
            PaymentId::fromString($paymentId)
        );

        return PaymentDTO::fromEntity($payment);
    }

    /**
     * Ödənişi ID-sinə görə tap.
     *
     * @return PaymentDTO|null Tapılmazsa null qaytarır
     */
    public function findPayment(string $paymentId): ?PaymentDTO
    {
        $payment = $this->paymentRepository->findById(
            PaymentId::fromString($paymentId)
        );

        if ($payment === null) {
            return null;
        }

        return PaymentDTO::fromEntity($payment);
    }
}
