<?php

declare(strict_types=1);

namespace Src\Order\Application\Commands\CreateOrder;

use Src\Order\Application\DTOs\CreateOrderDTO;
use Src\Shared\Application\Bus\Command;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * CREATE ORDER COMMAND (CQRS Pattern)
 * =====================================
 * "Yeni sifariş yarat" əmri — Controller-dən Application layer-ə göndərilir.
 *
 * COMMAND XATIRLATMASI:
 * - Command = "Bunu ET!" (əmr, imperativ).
 * - Datanı DƏYİŞİR (yeni sifariş yaradır).
 * - İmmutable-dir (readonly) — yaradıldıqdan sonra dəyişmir.
 *
 * AXIN:
 * Controller → CreateOrderCommand → CommandBus → CreateOrderHandler
 *
 * VALİDASİYA:
 * Command-da validate() metodu var — Command Bus-a göndərilmədən əvvəl yoxlanır.
 * Bu "fail fast" (tez xəta ver) prinsipinə uyğundur:
 * - Yanlış data DB-yə qədər getməsin, əvvəldən yoxlansın.
 */
class CreateOrderCommand implements Command
{
    public function __construct(
        private readonly CreateOrderDTO $dto,
    ) {}

    public function dto(): CreateOrderDTO
    {
        return $this->dto;
    }

    /**
     * Command-ın düzgünlüyünü yoxla — əsas validasiya qaydaları.
     *
     * DİQQƏT: Bu application-level validasiyadır, domain-level deyil.
     * - Application validation: "userId boş olmamalıdır" (format yoxlaması).
     * - Domain validation: "Bu istifadəçi aktiv olmalıdır" (biznes qaydası).
     *
     * @throws DomainException Validasiya uğursuz olduqda
     */
    public function validate(): void
    {
        if (empty($this->dto->userId)) {
            throw new DomainException('İstifadəçi ID-si boş ola bilməz.');
        }

        if (empty($this->dto->street) || empty($this->dto->city)) {
            throw new DomainException('Çatdırılma ünvanı tam doldurulmalıdır.');
        }

        if (empty($this->dto->items)) {
            throw new DomainException('Sifariş ən azı bir məhsul ehtiva etməlidir.');
        }

        // Hər item-in düzgünlüyünü yoxla
        foreach ($this->dto->items as $index => $item) {
            if (empty($item->productId)) {
                throw new DomainException("Sətir #{$index}: Məhsul ID-si boş ola bilməz.");
            }

            if ($item->quantity <= 0) {
                throw new DomainException("Sətir #{$index}: Miqdar müsbət olmalıdır.");
            }

            if ($item->price < 0) {
                throw new DomainException("Sətir #{$index}: Qiymət mənfi ola bilməz.");
            }
        }
    }
}
