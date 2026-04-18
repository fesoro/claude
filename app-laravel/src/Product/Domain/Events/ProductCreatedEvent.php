<?php

declare(strict_types=1);

namespace Src\Product\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * ProductCreatedEvent - Yeni məhsul yaradılanda baş verən hadisə (event).
 *
 * Domain Event nədir?
 * - Sistemdə baş vermiş vacib bir hadisəni təmsil edir.
 * - Keçmiş zamanda adlandırılır: "ProductCreated" (Məhsul yaradıldı).
 * - Digər hissələr bu hadisəni "dinləyə" (listen) bilər.
 *
 * Niyə Event istifadə edirik?
 * - Sistemin hissələri arasında asılılığı azaldırıq (loose coupling).
 * - Məsələn: Məhsul yaradılanda avtomatik log yazmaq, email göndərmək və s.
 * - Product sinfi email göndərmə barədə heç nə bilmir - bu, Event vasitəsilə olur.
 */
final class ProductCreatedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $productId,
        public readonly string $productName,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly int $stock,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'product.created';
    }

    public function toArray(): array
    {
        return [
            'product_id'     => $this->productId,
            'product_name'   => $this->productName,
            'price_amount'   => $this->priceAmount,
            'price_currency' => $this->priceCurrency,
            'stock'          => $this->stock,
        ];
    }
}
