<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProductStockChangedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * CHECK LOW STOCK LISTENER
 * =========================
 *
 * ProductStockChangedEvent baş verdikdə stok miqdarını yoxlayır.
 * Əgər yeni stok 5-dən azdırsa, anbar menecerinə xəbərdarlıq göndərir.
 *
 * ShouldQueue — çünki email göndərmək yavaş əməliyyatdır.
 *
 * ŞƏRT YOXLAMASI:
 * Hər stok dəyişikliyində deyil, YALNIZ aşağı stok olduqda reaksiya göstərir.
 * Listener daxilində if/else ilə qərar vermək normaldır.
 */
class CheckLowStockListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    /**
     * Aşağı stok həddi — bu rəqəmdən az olsa xəbərdarlıq göndərilir.
     */
    private const LOW_STOCK_THRESHOLD = 5;

    public function handle(ProductStockChangedEvent $event): void
    {
        /**
         * Yalnız stok azaldıqda VƏ həddən aşağı olduqda reaksiya göstər.
         * Stok artıbsa (məsələn yeni partiya gəlibsə) — xəbərdarlığa ehtiyac yoxdur.
         */
        if ($event->newStock >= self::LOW_STOCK_THRESHOLD) {
            return;
        }

        Log::warning('Məhsul stoku azdır!', [
            'product_id' => $event->productId,
            'old_stock' => $event->oldStock,
            'new_stock' => $event->newStock,
        ]);

        /**
         * TODO: LowStockAlertMail göndər.
         *
         * Nümunə:
         * $product = ProductModel::findOrFail($event->productId);
         * Mail::to('warehouse@example.com')->queue(
         *     new LowStockAlertMail($product, $event->newStock)
         * );
         *
         * Əgər stok 0-dırsa, daha təcili bildiriş göndərmək olar:
         * if ($event->newStock === 0) {
         *     // Urgent notification
         * }
         */
        Log::info('LowStockAlertMail göndərilməlidir (TODO)', [
            'product_id' => $event->productId,
            'new_stock' => $event->newStock,
        ]);
    }
}
