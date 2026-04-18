<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * ƏSAS DATABASE SEEDER
 * =====================
 * Bütün seeder-ləri düzgün ardıcıllıqla çağıran mərkəzi seeder.
 *
 * ARDICILLIQ VACİBDİR:
 * 1. UserSeeder — əvvəlcə istifadəçilər yaradılır (sifarişlər user_id tələb edir)
 * 2. ProductSeeder — sonra məhsullar (sifariş sətirləri product_id tələb edir)
 * 3. OrderSeeder — sifarişlər user və product ID-ləri ilə yaradılır
 * 4. PaymentSeeder — ödənişlər ödənilmiş sifarişlər üçün yaradılır
 *
 * İŞLƏTMƏ:
 *   php artisan db:seed                  → bu seeder-i (hamısını) işlət
 *   php artisan migrate:fresh --seed     → DB sıfırla + seed et
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Verilənlər bazasını seed edir.
     *
     * call() metodu seeder-ləri ardıcıl çağırır.
     * Hər seeder işlədikdə konsola "Seeding: ClassName" mesajı yazılır.
     * Seeder uğurla bitdikdə "Seeded: ClassName" mesajı göstərilir.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
            PaymentSeeder::class,
        ]);
    }
}
