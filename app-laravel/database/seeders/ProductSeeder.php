<?php

declare(strict_types=1);

namespace Database\Seeders;

use Src\Product\Infrastructure\Models\ProductModel;
use Illuminate\Database\Seeder;

/**
 * MƏHSUL SEEDER
 * ==============
 * 20 məhsul yaradır — müxtəlif qiymətlər və stok səviyyələri ilə.
 *
 * Məhsullar 3 qrupa bölünür:
 * - 14 adi məhsul (normal qiymət və stok)
 * - 3 stoku az olan məhsul (LowStock alertini test etmək üçün)
 * - 3 bahalı məhsul (yüksək qiymət)
 */
class ProductSeeder extends Seeder
{
    /**
     * Məhsul datası yaradır.
     */
    public function run(): void
    {
        /**
         * 14 adi məhsul — default definition() ilə yaradılır.
         * Qiymət: 5-500 arası, Stok: 0-100 arası (təsadüfi).
         */
        ProductModel::factory()
            ->count(14)
            ->create();

        /**
         * 3 stoku az olan məhsul — lowStock() state ilə yaradılır.
         * Stok: 1-4 arası. Bu məhsullar üçün ProductModel::isLowStock() true qaytaracaq.
         * Development-də LowStock bildirişlərini test etmək üçün faydalıdır.
         */
        ProductModel::factory()
            ->count(3)
            ->lowStock()
            ->create();

        /**
         * 3 bahalı məhsul — expensive() state ilə yaradılır.
         * Qiymət: 500-2000 arası. Böyük məbləğli sifarişləri test etmək üçün.
         */
        ProductModel::factory()
            ->count(3)
            ->expensive()
            ->create();
    }
}
