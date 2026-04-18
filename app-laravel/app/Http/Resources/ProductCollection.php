<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * PRODUCT RESOURCE COLLECTION
 * ===========================
 * Bu sinif məhsulların SİYAHISINI (collection) API formatına çevirir.
 *
 * ═══════════════════════════════════════════════════════════════════
 * ResourceCollection NƏDİR? Resource-dan FƏRQI NƏDİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * JsonResource (ProductResource) → TƏK bir məhsulu formatlaşdırır
 * ResourceCollection (ProductCollection) → SIYAHINI formatlaşdırır
 *
 * "ProductResource::collection() istifadə edə bilərəm, niyə ayrı sinif lazımdır?"
 * Yaxşı sual! Fərq budur:
 *
 * 1. ProductResource::collection($products):
 *    - Sadə siyahı üçün kifayətdir
 *    - Əlavə meta-data əlavə edə bilmirik
 *    - Amma pagination avtomatik işləyir
 *
 * 2. ProductCollection (ayrı sinif):
 *    - Siyahıya ƏLAVƏ meta-data əlavə edə bilərik (total_stock, price_range və s.)
 *    - Xüsusi formatlama edə bilərik
 *    - Daha böyük kontrol veririr
 *
 * ═══════════════════════════════════════════════════════════════════
 * PAGİNASİYA META-DATASI NECƏ ƏLAVƏ OLUNUR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * Əgər paginate() nəticəsi ötürsək:
 *   new ProductCollection(Product::paginate(15))
 *
 * Laravel avtomatik olaraq bu strukturu yaradır:
 *   {
 *     "data": [ ... məhsullar ... ],
 *     "links": {
 *       "first": "http://api.com/products?page=1",
 *       "last": "http://api.com/products?page=5",
 *       "prev": null,
 *       "next": "http://api.com/products?page=2"
 *     },
 *     "meta": {
 *       "current_page": 1,
 *       "from": 1,
 *       "last_page": 5,
 *       "per_page": 15,
 *       "to": 15,
 *       "total": 75,
 *       // ... əlavə meta-data burada görünəcək
 *     }
 *   }
 *
 * Biz yalnız data hissəsini və əlavə meta-datanı konfiqurasiya edirik,
 * links və əsas meta avtomatik gəlir!
 */
class ProductCollection extends ResourceCollection
{
    /**
     * Hər bir elementi hansı Resource ilə formatlamalı?
     *
     * Bu property olmasa, Laravel sinif adından təxmin edir:
     * ProductCollection → ProductResource (avtomatik).
     * Amma açıq yazmaq daha aydındır.
     */
    public $collects = ProductResource::class;

    /**
     * Collection-u array formatına çevir.
     *
     * $this->collection — Resource ilə wrap olunmuş elementlər siyahısıdır.
     * Yəni hər element artıq ProductResource::toArray() ilə formatlanıb.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    /**
     * Cavaba əlavə meta-data əlavə et.
     *
     * Bu metod "meta" bölməsinə əlavə sahələr əlavə etmək üçündür.
     * Pagination meta-datası (current_page, total və s.) avtomatik əlavə olunur,
     * biz isə öz xüsusi meta-datamızı əlavə edə bilərik.
     *
     * Nəticədə JSON-da belə görünəcək:
     * {
     *   "data": [...],
     *   "meta": {
     *     "current_page": 1,
     *     "total": 75,
     *     "total_stock": 1500,     // ← bizim əlavə etdiyimiz
     *     "price_range": { ... }   // ← bizim əlavə etdiyimiz
     *   }
     * }
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $default['meta']['total_stock'] = $this->collection->sum(function ($product) {
            return $product->stock;
        });

        return $default;
    }
}
