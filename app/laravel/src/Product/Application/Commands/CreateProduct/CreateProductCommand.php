<?php

declare(strict_types=1);

namespace Src\Product\Application\Commands\CreateProduct;

use Src\Shared\Application\Bus\Command;
use Src\Product\Application\DTOs\CreateProductDTO;

/**
 * CreateProductCommand - Yeni məhsul yaratma əmri.
 *
 * Command nədir? (CQRS pattern-in bir hissəsi)
 * - Sistemdə dəyişiklik etmək üçün istifadə olunur (yazma əməliyyatı).
 * - Command heç vaxt məlumat qaytarmır (void) - yalnız əmr verir.
 * - Hər Command-ın bir Handler-i olur.
 *
 * CQRS (Command Query Responsibility Segregation):
 * - Command = Yazma (Create, Update, Delete)
 * - Query = Oxuma (Read)
 * - Yazma və oxuma əməliyyatlarını ayırırıq.
 *
 * Niyə Command/Query ayırırıq?
 * - Oxuma və yazma fərqli optimallaşdırıla bilər.
 * - Kod daha təmiz və başa düşülən olur.
 * - Hər əməliyyatın məsuliyyəti aydın olur.
 */
final class CreateProductCommand implements Command
{
    public function __construct(
        public readonly CreateProductDTO $dto,
    ) {
    }
}
