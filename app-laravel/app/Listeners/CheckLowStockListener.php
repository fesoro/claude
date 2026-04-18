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
         * Anbar menecerinə stok xəbərdarlığı email-i göndəririk.
         *
         * WAREHOUSE_EMAIL .env-dən oxunur. Default: warehouse@example.com
         * Real layihədə bu bir neçə yerə göndərilə bilər:
         * - Email (burada)
         * - Slack notification
         * - Dashboard alert
         *
         * Stok 0-dırsa təcili (urgent) olaraq qeyd edirik —
         * mail subject-ində fərq olacaq ki, diqqət çəksin.
         */
        $recipientEmail = config('mail.warehouse_email', 'warehouse@example.com');

        // LowStockAlertMail productName (ad) gözləyir, productId deyil.
        // Product-u DB-dən tapıb adını alırıq.
        $product = \Src\Product\Infrastructure\Models\ProductModel::find($event->productId);
        $productName = $product?->name ?? "Məhsul #{$event->productId}";

        \Illuminate\Support\Facades\Mail::to($recipientEmail)->queue(
            new \App\Mail\LowStockAlertMail(
                productName: $productName,
                currentStock: $event->newStock,
            ),
        );

        Log::info('LowStockAlertMail queue-yə əlavə olundu', [
            'product_id' => $event->productId,
            'new_stock' => $event->newStock,
            'recipient' => $recipientEmail,
        ]);
    }
}
