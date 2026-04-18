<?php

declare(strict_types=1);

namespace Src\Product\Application\DTOs;

/**
 * CreateProductDTO - Yeni məhsul yaratmaq üçün giriş (input) DTO-su.
 *
 * Bu DTO controller-dən (və ya API-dən) gələn məlumatları daşıyır.
 * readonly - bir dəfə təyin olunur, sonra dəyişdirilə bilməz.
 *
 * Input DTO vs Output DTO:
 * - Input DTO (bu): Xaricdən gələn məlumatlar (request). Sadə tiplər istifadə edir.
 * - Output DTO (ProductDTO): Xaricə göndərilən məlumatlar (response).
 */
final readonly class CreateProductDTO
{
    /**
     * @param string $name         Məhsulun adı
     * @param int    $priceAmount  Qiymət (qəpiklərlə). Məsələn: 1050 = 10.50 AZN
     * @param string $currency     Valyuta kodu. Məsələn: 'AZN'
     * @param int    $stock        Başlanğıc stok miqdarı
     */
    public function __construct(
        public string $name,
        public int $priceAmount,
        public string $currency,
        public int $stock,
    ) {
    }
}
