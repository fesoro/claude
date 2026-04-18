<?php

declare(strict_types=1);

namespace Database\Factories;

use Src\Order\Infrastructure\Models\OrderItemModel;
use Src\Product\Domain\Enums\CurrencyEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * SİFARİŞ SƏTRİ FACTORY
 * =======================
 * Sifariş sətri (OrderItem) instansları yaradan factory.
 *
 * OrderItem — sifarişin daxilindəki bir məhsul sətridir.
 * Hər sifarişin 1 və ya daha çox item-i olur.
 *
 * İSTİFADƏ NÜMUNƏLƏRİ:
 *   // Tək item yaratmaq
 *   OrderItemModel::factory()->create(['order_id' => $order->id]);
 *
 *   // Bir sifariş üçün 3 item yaratmaq
 *   OrderItemModel::factory()->count(3)->create(['order_id' => $order->id]);
 *
 * @extends Factory<OrderItemModel>
 */
class OrderItemFactory extends Factory
{
    /**
     * Bu factory OrderItemModel üçündür.
     * OrderItem Order Aggregate-inin daxili Entity-sidir.
     */
    protected $model = OrderItemModel::class;

    /**
     * Default sifariş sətri sahələri.
     *
     * order_id — bu item hansı sifarişə aiddir.
     * product_id — bu item hansı məhsuldur (cross-context referans).
     * quantity — neçə ədəd sifariş edilib.
     * price — sifariş anındakı qiymət (snapshot).
     *
     * QEYD: price burada sifariş anındakı qiymətdir, hazırkı məhsul qiyməti deyil.
     * Məhsulun qiyməti dəyişsə belə, sifariş sətri orijinal qiyməti saxlayır.
     */
    public function definition(): array
    {
        return [
            'order_id'   => fake()->uuid(),
            'product_id' => fake()->uuid(),
            'quantity'    => fake()->numberBetween(1, 5),
            'price'      => fake()->randomFloat(2, 5.00, 300.00),
            'currency'   => fake()->randomElement(CurrencyEnum::cases())->value,
        ];
    }
}
