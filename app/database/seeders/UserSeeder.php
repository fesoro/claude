<?php

declare(strict_types=1);

namespace Database\Seeders;

use Src\User\Infrastructure\Models\UserModel;
use Illuminate\Database\Seeder;

/**
 * İSTİFADƏÇİ SEEDER
 * ===================
 *
 * SEEDER NƏDİR?
 * ==============
 * Seeder — verilənlər bazasına ilkin (seed) data əlavə edən class-dır.
 * Development və test mühitlərində lazım olan dataları avtomatik yaradır.
 *
 * NƏYƏ LAZIMDIR?
 * - Development: Boş DB ilə işləmək çətindir — seeder real görünüşlü data yaradır.
 * - Test: Hər test əvvəli eyni başlanğıc datanı təmin edir.
 * - Demo: Müştəriyə göstərmək üçün nümunə data lazımdır.
 *
 * SEEDER-İ NECƏ İŞLƏTMƏLİ?
 * ==========================
 *
 *   php artisan db:seed                    → DatabaseSeeder-i (bütün seeder-ləri) işlət
 *   php artisan db:seed --class=UserSeeder → Yalnız UserSeeder-i işlət
 *   php artisan migrate:fresh --seed       → DB-ni sıfırla + seeder-ləri işlət
 *
 * call() METODU:
 * ==============
 * DatabaseSeeder-dən digər seeder-ləri çağırmaq üçün istifadə olunur:
 *
 *   $this->call([
 *       UserSeeder::class,
 *       ProductSeeder::class,
 *   ]);
 *
 * call() hər seeder-i ardıcıl işlədir və konsola status mesajı yazır.
 * Sıra vacibdir — əvvəl User yaradılmalıdır ki, Order user_id istifadə edə bilsin.
 */
class UserSeeder extends Seeder
{
    /**
     * İstifadəçi datası yaradır.
     *
     * 1 admin istifadəçi + 10 adi istifadəçi yaradılır.
     * Admin — sabit email və ad ilə yaradılır (hər dəfə eyni).
     * Adi istifadəçilər — Faker ilə təsadüfi data ilə yaradılır.
     */
    public function run(): void
    {
        /**
         * Admin istifadəçi yaradılması.
         * admin() state-i factory-dən gəlir — email və adı sabitdir.
         * Bu istifadəçi ilə development mühitdə login etmək olur.
         */
        UserModel::factory()
            ->admin()
            ->create();

        /**
         * 10 adi istifadəçi yaradılması.
         * count(10) — 10 instans yaradır, hər biri fərqli Faker datası ilə.
         * Bu istifadəçilər sifariş və ödəniş seeder-lərində istifadə olunacaq.
         */
        UserModel::factory()
            ->count(10)
            ->create();
    }
}
