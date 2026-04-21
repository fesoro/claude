<?php

declare(strict_types=1);

namespace Database\Factories;

use Src\Product\Infrastructure\Models\ProductModel;
use Src\Product\Domain\Enums\CurrencyEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * MƏHSUL FACTORY
 * ===============
 * Realistik məhsul datası yaradan factory.
 *
 * Faker ilə məhsul adları, qiymətləri və stok səviyyələri yaradılır.
 * State-lər vasitəsilə müxtəlif məhsul variantları təyin edilə bilər:
 * - expensive() — bahalı məhsul
 * - lowStock() — stoku az olan məhsul
 * - outOfStock() — stoku bitmiş məhsul
 *
 * @extends Factory<ProductModel>
 */
class ProductFactory extends Factory
{
    /**
     * Bu factory ProductModel üçündür.
     * Laravel default olaraq App\Models namespace-ində model axtarır,
     * amma DDD-də model src/ altındadır — ona görə tam yol göstərilir.
     */
    protected $model = ProductModel::class;

    /**
     * Default məhsul sahələri.
     *
     * fake()->randomElement() — verilən massivdən təsadüfi element seçir.
     * fake()->randomFloat(2, 5, 500) — 5 ilə 500 arasında 2 onluq rəqəmli float.
     * fake()->numberBetween(0, 100) — 0-100 arası tam ədəd.
     *
     * Məhsul adları realistik olması üçün kateqoriya + rəng + material birləşdirilir.
     */
    public function definition(): array
    {
        /**
         * Realistik məhsul adları yaratmaq üçün komponentlər.
         * Faker-in standart metodları e-commerce üçün kifayət etmir,
         * ona görə öz siyahılarımızı təyin edirik.
         */
        $categories = [
            'Laptop', 'Telefon', 'Qulaqlıq', 'Monitor', 'Klaviatura',
            'Siçan', 'Planşet', 'Kamera', 'Saat', 'Çanta',
        ];

        $adjectives = [
            'Premium', 'Pro', 'Ultra', 'Mini', 'Smart',
            'Wireless', 'Gaming', 'Classic', 'Elite', 'Slim',
        ];

        return [
            'name'     => fake()->randomElement($adjectives) . ' ' . fake()->randomElement($categories),
            'price'    => fake()->randomFloat(2, 5.00, 500.00),
            'currency' => fake()->randomElement(CurrencyEnum::cases())->value,
            'stock'    => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Bahalı məhsul state-i (500-2000 arası qiymət).
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => fake()->randomFloat(2, 500.00, 2000.00),
        ]);
    }

    /**
     * Stoku az olan məhsul (1-4 arası).
     * ProductModel::isLowStock() bu məhsullar üçün true qaytaracaq.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => fake()->numberBetween(1, 4),
        ]);
    }

    /**
     * Stoku bitmiş məhsul.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }
}
