<?php

declare(strict_types=1);

namespace Database\Seeders;

use Src\Order\Infrastructure\Models\OrderModel;
use Src\Order\Domain\Enums\OrderStatusEnum;
use Src\Payment\Infrastructure\Models\PaymentModel;
use Illuminate\Database\Seeder;

/**
 * ÖDƏNİŞ SEEDER
 * ===============
 * Ödənilmiş (PAID) statusda olan sifarişlər üçün ödəniş qeydləri yaradır.
 *
 * MƏNTIQ: Yalnız PAID, SHIPPED və DELIVERED statuslu sifarişlərin ödənişi olmalıdır,
 * çünki bu statuslara yalnız ödəniş tamamlandıqdan sonra keçid mümkündür.
 * PENDING və CONFIRMED sifarişlər hələ ödənilməyib.
 * CANCELLED sifarişlər ləğv edilib — ödəniş olmaya bilər.
 *
 * ƏSAS: Bu seeder OrderSeeder-dən SONRA işləməlidir.
 */
class PaymentSeeder extends Seeder
{
    /**
     * Ödəniş datası yaradır.
     */
    public function run(): void
    {
        /**
         * Ödənilmiş statusda olan sifarişləri tap.
         * whereIn() — bir neçə dəyərdən birinə uyğun gələn qeydləri seçir.
         * Bu statuslar ödənişin tamamlandığını bildirir.
         */
        $paidOrders = OrderModel::whereIn('status', [
            OrderStatusEnum::PAID->value,
            OrderStatusEnum::SHIPPED->value,
            OrderStatusEnum::DELIVERED->value,
        ])->get();

        if ($paidOrders->isEmpty()) {
            $this->command->warn('Ödənilmiş sifariş tapılmadı. Əvvəl OrderSeeder işlədin.');
            return;
        }

        /**
         * Hər ödənilmiş sifariş üçün uğurlu ödəniş yaradılır.
         * completed() state — status=completed, transaction_id mövcud.
         * Sifariş məbləği və valyutası ödənişə kopyalanır.
         */
        foreach ($paidOrders as $order) {
            PaymentModel::factory()
                ->completed()
                ->create([
                    'order_id' => $order->id,
                    'amount'   => $order->total_amount,
                    'currency' => $order->currency,
                ]);
        }

        /**
         * Əlavə olaraq 2 uğursuz ödəniş yaradılır.
         * Bu test datası uğursuz ödəniş axınını yoxlamaq üçün faydalıdır.
         * PENDING statuslu sifarişlər üçün yaradılır — ödəniş cəhdi uğursuz olub.
         */
        $pendingOrders = OrderModel::where('status', OrderStatusEnum::PENDING->value)
            ->take(2)
            ->get();

        foreach ($pendingOrders as $order) {
            PaymentModel::factory()
                ->failed()
                ->create([
                    'order_id' => $order->id,
                    'amount'   => $order->total_amount,
                    'currency' => $order->currency,
                ]);
        }
    }
}
