<?php

declare(strict_types=1);

namespace Src\Product\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * PRODUCT ELOQUENT MODEL
 * =======================
 * Məhsul cədvəlinin Eloquent təmsili.
 *
 * ACCESSOR / MUTATOR:
 * Bu model-də price sahəsi üçün accessor istifadə olunur.
 * Accessor — DB-dən gələn datanı avtomatik çevirən metoddur.
 * Mutator — DB-yə yazılan datanı avtomatik çevirən metoddur.
 *
 * Məsələn:
 * DB-də price = 2999 (cent), amma $product->price → 29.99 (dollar) qaytarır.
 * $product->price = 29.99 yazanda → DB-yə 2999 yazılır.
 */
class ProductModel extends Model
{
    use HasUuids;

    /**
     * Bu model Product bounded context-inin ayrı verilənlər bazasına qoşulur.
     * Məhsul datası yalnız Product context-ə məxsusdur — digər context-lər
     * (Order, Payment) məhsul məlumatlarına yalnız Integration Event və ya
     * API vasitəsilə çatmalıdır, birbaşa DB sorğusu ilə deyil.
     */
    protected $connection = 'product_db';

    protected $table = 'products';

    protected $fillable = [
        'id',
        'name',
        'price',
        'currency',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    /**
     * SCOPE — yenidən istifadə edilə bilən query filter-ləri.
     *
     * NƏYƏ LAZIMDIR?
     * ProductModel::inStock()->get() yazmaq,
     * ProductModel::where('stock', '>', 0)->get() yazmaqdan daha oxunaqlıdır.
     *
     * Scope-lar query builder-ə zəncir şəklində əlavə olunur:
     * ProductModel::inStock()->where('currency', 'USD')->get()
     */
    /**
     * hasMany — Məhsulun şəkilləri (eyni bounded context, eyni DB).
     */
    public function images()
    {
        return $this->hasMany(ProductImageModel::class, 'product_id')->orderBy('sort_order');
    }

    /**
     * Əsas şəkli qaytar.
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImageModel::class, 'product_id')->where('is_primary', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Stokda azmı? (5-dən az)
     * Observer-də istifadə olunacaq — LowStockEvent fire etmək üçün.
     */
    public function isLowStock(): bool
    {
        return $this->stock < 5;
    }
}
