<?php

declare(strict_types=1);

namespace Database\Seeders;

use Src\Order\Infrastructure\Models\OrderModel;
use Src\Order\Infrastructure\Models\OrderItemModel;
use Src\User\Infrastructure\Models\UserModel;
use Src\Product\Infrastructure\Models\ProductModel;
use Src\Order\Domain\Enums\OrderStatusEnum;
use Illuminate\Database\Seeder;

/**
 * SİFARİŞ SEEDER
 * ===============
 * 15 sifariş yaradır — təsadüfi istifadəçilər və məhsullarla.
 *
 * Hər sifarişə 1-4 arası sifariş sətri (OrderItem) əlavə olunur.
 * Sifarişlər müxtəlif statuslarda yaradılır ki, bütün status keçidlərini
 * development mühitdə test etmək mümkün olsun.
 *
 * ƏSAS: Bu seeder UserSeeder və ProductSeeder-dən SONRA işləməlidir,
 * çünki mövcud user və product ID-lərinə ehtiyac var.
 */
class OrderSeeder extends Seeder
{
    /**
     * Sifariş datası yaradır.
     *
     * DDD QEYDI: Order və User fərqli bounded context-lərdədir (fərqli DB-lər).
     * Seeder development alətidir — burada cross-context sorğu icazəlidir.
     * Production kodda isə bu cür birbaşa sorğu QADAĞANDIR.
     */
    public function run(): void
    {
        /**
         * Mövcud istifadəçi və məhsul ID-lərini əldə et.
         * pluck('id') — yalnız id sütununu massiv şəklində qaytarır.
         * toArray() — Collection-dan PHP massivinə çevirir.
         */
        $userIds = UserModel::pluck('id')->toArray();
        $productIds = ProductModel::pluck('id')->toArray();

        /**
         * İstifadəçi və ya məhsul yoxdursa, seeder-i dayandır.
         * Bu vəziyyət əvvəlki seeder-lər işləmədikdə baş verə bilər.
         */
        if (empty($userIds) || empty($productIds)) {
            $this->command->warn('İstifadəçi və ya məhsul tapılmadı. Əvvəl UserSeeder və ProductSeeder işlədin.');
            return;
        }

        /**
         * Müxtəlif statuslarda sifarişlər yaradılır.
         * Hər status üçün müəyyən say — realistik data paylanması.
         */
        $statusDistribution = [
            OrderStatusEnum::PENDING   => 3,
            OrderStatusEnum::CONFIRMED => 2,
            OrderStatusEnum::PAID      => 4,
            OrderStatusEnum::SHIPPED   => 3,
            OrderStatusEnum::DELIVERED => 2,
            OrderStatusEnum::CANCELLED => 1,
        ];

        foreach ($statusDistribution as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                /**
                 * Sifarişi yaradır — təsadüfi istifadəçiyə bağlanır.
                 * fake()->randomElement() — massivdən təsadüfi element seçir.
                 */
                $order = OrderModel::factory()->create([
                    'user_id' => fake()->randomElement($userIds),
                    'status'  => $status->value,
                ]);

                /**
                 * Hər sifarişə 1-4 arası sifariş sətri əlavə olunur.
                 * Hər sətir təsadüfi məhsula bağlanır.
                 */
                $itemCount = fake()->numberBetween(1, 4);

                OrderItemModel::factory()
                    ->count($itemCount)
                    ->create([
                        'order_id'   => $order->id,
                        'product_id' => fn () => fake()->randomElement($productIds),
                    ]);

                /**
                 * Sifarişin ümumi məbləğini sətirlərin cəminə uyğunlaşdırır.
                 * Real sistemdə bu hesablama Domain Entity-də olur,
                 * amma seeder-də sadəlik üçün birbaşa hesablayırıq.
                 */
                $totalAmount = $order->items->sum(fn ($item) => $item->price * $item->quantity);
                $order->update(['total_amount' => $totalAmount]);
            }
        }
    }
}
