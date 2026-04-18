<?php

declare(strict_types=1);

namespace Src\Product\Application\Commands\UpdateStock;

use Src\Shared\Application\Bus\Command;

/**
 * UpdateStockCommand - Məhsul stokunu yeniləmə əmri.
 *
 * Bu command stoku artırmaq və ya azaltmaq üçün istifadə olunur.
 * "type" sahəsi əməliyyat növünü müəyyən edir: 'increase' və ya 'decrease'.
 */
final class UpdateStockCommand implements Command
{
    /**
     * @param string $productId Məhsulun ID-si
     * @param int    $amount    Dəyişiklik miqdarı (müsbət rəqəm)
     * @param string $type      Əməliyyat növü: 'increase' (artır) və ya 'decrease' (azalt)
     */
    public function __construct(
        public readonly string $productId,
        public readonly int $amount,
        public readonly string $type, // 'increase' və ya 'decrease'
    ) {
    }
}
