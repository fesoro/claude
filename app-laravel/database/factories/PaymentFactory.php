<?php

declare(strict_types=1);

namespace Database\Factories;

use Src\Payment\Infrastructure\Models\PaymentModel;
use Src\Payment\Domain\Enums\PaymentStatusEnum;
use Src\Payment\Domain\Enums\PaymentMethodEnum;
use Src\Product\Domain\Enums\CurrencyEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * ÖDƏNİŞ FACTORY
 * ================
 * Ödəniş instansları yaradan factory.
 *
 * State-lər vasitəsilə müxtəlif ödəniş nəticələri yaradıla bilər:
 *   PaymentModel::factory()->completed()->create()  → uğurlu ödəniş
 *   PaymentModel::factory()->failed()->create()     → uğursuz ödəniş
 *
 * @extends Factory<PaymentModel>
 */
class PaymentFactory extends Factory
{
    /**
     * Bu factory PaymentModel üçündür.
     * Payment bounded context-inin Infrastructure layer-indəki model.
     */
    protected $model = PaymentModel::class;

    /**
     * Default ödəniş sahələri.
     *
     * transaction_id — gateway-in qaytardığı unikal əməliyyat nömrəsi.
     * Test zamanı Faker UUID istifadə olunur, real mühitdə gateway özü yaradır.
     *
     * failure_reason — yalnız FAILED statusda doldurulur, default-da null-dur.
     */
    public function definition(): array
    {
        return [
            'order_id'       => fake()->uuid(),
            'amount'         => fake()->randomFloat(2, 10.00, 1000.00),
            'currency'       => fake()->randomElement(CurrencyEnum::cases())->value,
            'method'         => fake()->randomElement(PaymentMethodEnum::cases())->value,
            'status'         => PaymentStatusEnum::PENDING->value,
            'transaction_id' => Str::uuid()->toString(),
            'failure_reason' => null,
        ];
    }

    /**
     * Uğurlu ödəniş state-i.
     * Status COMPLETED, transaction_id mövcud, failure_reason null.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => PaymentStatusEnum::COMPLETED->value,
            'transaction_id' => Str::uuid()->toString(),
            'failure_reason' => null,
        ]);
    }

    /**
     * Uğursuz ödəniş state-i.
     * Status FAILED, failure_reason doldurulur.
     *
     * Realistik uğursuzluq səbəbləri seçilir — test zamanı
     * xəta mesajlarının düzgün göstərildiyini yoxlamaq üçün faydalıdır.
     */
    public function failed(): static
    {
        $reasons = [
            'Balans kifayət deyil',
            'Kartın müddəti bitib',
            'Gateway cavab vermədi',
            'Əməliyyat bank tərəfindən rədd edildi',
            'Təhlükəsizlik yoxlaması uğursuz oldu',
        ];

        return $this->state(fn (array $attributes) => [
            'status'         => PaymentStatusEnum::FAILED->value,
            'failure_reason' => fake()->randomElement($reasons),
        ]);
    }
}
