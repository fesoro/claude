<?php

declare(strict_types=1);

namespace Database\Factories;

use Src\Order\Infrastructure\Models\OrderModel;
use Src\Order\Domain\Enums\OrderStatusEnum;
use Src\Product\Domain\Enums\CurrencyEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * SİFARİŞ FACTORY
 * ================
 * Sifariş instansları yaradan factory.
 *
 * State-lər vasitəsilə müxtəlif sifariş statusları yaradıla bilər:
 *   OrderModel::factory()->pending()->create()   → gözləmədə olan sifariş
 *   OrderModel::factory()->paid()->create()       → ödənilmiş sifariş
 *   OrderModel::factory()->shipped()->create()    → göndərilmiş sifariş
 *   OrderModel::factory()->cancelled()->create()  → ləğv edilmiş sifariş
 *
 * @extends Factory<OrderModel>
 */
class OrderFactory extends Factory
{
    /**
     * Bu factory OrderModel üçündür.
     * Order bounded context-inin Infrastructure layer-indəki model.
     */
    protected $model = OrderModel::class;

    /**
     * Default sifariş sahələri.
     *
     * user_id — sifarişin sahibi. Test zamanı UUID yaradılır,
     * seeder-də isə real user ID ilə override olunacaq.
     *
     * Ünvan sahələri Faker ilə realistik olaraq doldurulur.
     */
    public function definition(): array
    {
        return [
            'user_id'         => fake()->uuid(),
            'status'          => OrderStatusEnum::PENDING->value,
            'total_amount'    => fake()->randomFloat(2, 10.00, 1000.00),
            'currency'        => fake()->randomElement(CurrencyEnum::cases())->value,
            'address_street'  => fake()->streetAddress(),
            'address_city'    => fake()->city(),
            'address_zip'     => fake()->postcode(),
            'address_country' => fake()->country(),
        ];
    }

    /**
     * Gözləmədə olan sifariş (default status).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatusEnum::PENDING->value,
        ]);
    }

    /**
     * Ödənilmiş sifariş.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatusEnum::PAID->value,
        ]);
    }

    /**
     * Göndərilmiş sifariş.
     */
    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatusEnum::SHIPPED->value,
        ]);
    }

    /**
     * Ləğv edilmiş sifariş.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatusEnum::CANCELLED->value,
        ]);
    }
}
