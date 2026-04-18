<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * LOW STOCK ALERT BROADCAST
 * =========================
 * Stok azalanda admin panel-ə real-time bildiriş.
 *
 * PUBLIC channel istifadə olunur — admin panel-dəki bütün admin-lər görür.
 * (Real proyektdə PrivateChannel + admin yoxlaması olmalıdır)
 *
 * Frontend nümunəsi:
 * Echo.channel('admin-alerts')
 *     .listen('LowStockAlert', (e) => {
 *         showNotification(`${e.product_name}: yalnız ${e.current_stock} ədəd qalıb!`);
 *     });
 */
class LowStockAlertBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $productId,
        public readonly string $productName,
        public readonly int $currentStock,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('admin-alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LowStockAlert';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'current_stock' => $this->currentStock,
            'severity' => $this->currentStock === 0 ? 'critical' : 'warning',
            'timestamp' => now()->toISOString(),
        ];
    }
}
