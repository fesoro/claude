<?php

declare(strict_types=1);

namespace Src\Product\Application\Queries\ListProducts;

use Illuminate\Database\Eloquent\Builder;

/**
 * PRODUCT FILTER (Query Builder Pattern)
 * =======================================
 * Məhsul siyahısını dinamik şəkildə filtrlə, axtar və sırala.
 *
 * QUERY BUILDER PATTERN NƏDİR?
 * Sorğunu addım-addım qurmaq üçün istifadə olunan pattern.
 * Hər filter metodu query-yə şərt əlavə edir və $this qaytarır (method chaining).
 *
 * NÜMUNƏ:
 * ProductFilter::apply($query, [
 *     'search' => 'laptop',
 *     'min_price' => 100,
 *     'max_price' => 500,
 *     'currency' => 'USD',
 *     'in_stock' => true,
 *     'sort_by' => 'price',
 *     'sort_dir' => 'desc',
 * ]);
 *
 * NƏTİCƏ SQL:
 * SELECT * FROM products
 * WHERE name LIKE '%laptop%'
 *   AND price >= 100
 *   AND price <= 500
 *   AND currency = 'USD'
 *   AND stock > 0
 * ORDER BY price DESC
 *
 * NƏYƏ AYRI CLASS?
 * - Controller-də uzun query yazmamaq üçün (Single Responsibility)
 * - Filterləri yenidən istifadə etmək üçün (Reusability)
 * - Test etmək asan olsun deyə (Testability)
 * - Yeni filter əlavə etmək sadə olsun (Open-Closed Principle)
 */
class ProductFilter
{
    /**
     * Bütün filterləri query-yə tətbiq et.
     *
     * @param Builder $query Eloquent query builder
     * @param array $filters İstifadəçidən gələn filter parametrləri
     * @return Builder Filtrlənmiş query
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        // Axtarış — məhsul adında axtarış (LIKE)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Minimum qiymət
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        // Maksimum qiymət
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        // Valyuta filteri
        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        // Stokda olan məhsullar
        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $query->where('stock', '>', 0);
        }

        // Sıralama
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        // Yalnız icazə verilən sütunlar üzrə sıralama (SQL injection qoruması)
        $allowedSortColumns = ['name', 'price', 'stock', 'created_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        return $query;
    }
}
