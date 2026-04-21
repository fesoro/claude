<?php

declare(strict_types=1);

namespace Src\Product\Domain\Repositories;

use Src\Product\Domain\Entities\Product;
use Src\Product\Domain\ValueObjects\ProductId;

/**
 * ProductRepositoryInterface - Məhsul anbarı (repository) interfeysi.
 *
 * Repository Pattern nədir?
 * - Verilənlər bazası əməliyyatlarını gizlədən bir "vasitəçi" təbəqədir.
 * - Domain (biznes məntiqi) verilənlər bazası barədə heç nə bilmir.
 * - Domain yalnız bu interfeysi tanıyır, konkret implementasiyanı yox.
 *
 * Niyə interfeys istifadə edirik?
 * - Dependency Inversion Principle (DIP): Yuxarı səviyyə aşağı səviyyədən asılı olmamalıdır.
 * - Domain (yuxarı) -> Interface <- Infrastructure (aşağı)
 * - Bu, verilənlər bazasını dəyişməyi asanlaşdırır (MySQL -> PostgreSQL və ya API).
 * - Test yazarkən saxta (mock/fake) repository istifadə edə bilərik.
 *
 * Bu interfeys Domain qatında yerləşir, amma implementasiya Infrastructure qatındadır.
 * Bu, DDD-nin əsas prinsiplərindən biridir.
 */
interface ProductRepositoryInterface
{
    /**
     * ID-yə görə məhsul tapır.
     *
     * @param ProductId $id Axtarılan məhsulun ID-si
     * @return Product|null Tapılmadıqda null qaytarır
     */
    public function findById(ProductId $id): ?Product;

    /**
     * Məhsulu saxlayır (yaradır və ya yeniləyir).
     * "Upsert" məntiqi - əgər varsa yeniləyir, yoxdursa yaradır.
     *
     * @param Product $product Saxlanacaq məhsul
     */
    public function save(Product $product): void;

    /**
     * Bütün məhsulları qaytarır.
     *
     * @return Product[] Məhsullar massivi
     */
    public function findAll(): array;
}
