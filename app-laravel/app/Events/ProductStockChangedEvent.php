<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PRODUCT STOCK CHANGED EVENT — Laravel Event
 * =============================================
 *
 * Məhsulun stok miqdarı dəyişəndə dispatch olunur.
 * ProductObserver::updated() metodu bu event-i fire edir.
 *
 * NƏYƏ LAZIMDIR?
 * Stok dəyişikliyini izləmək vacibdir:
 * - Stok azalıbsa → anbar menecerinə xəbərdarlıq (LowStockAlertMail)
 * - Stok 0 olubsa → məhsulu "tükənib" kimi göstərmək
 * - Stok artıbsa → gözləyən müştərilərə bildiriş
 *
 * $oldStock və $newStock — dəyişikliyin istiqamətini müəyyən etməyə imkan verir.
 * Məsələn: oldStock=10, newStock=3 → stok azalıb, xəbərdarlıq lazımdır.
 */
class ProductStockChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $productId,
        public readonly int $oldStock,
        public readonly int $newStock,
    ) {}
}
