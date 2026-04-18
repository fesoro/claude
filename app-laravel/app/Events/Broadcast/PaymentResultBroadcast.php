<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PAYMENT RESULT BROADCAST
 * ========================
 * Ödəniş nəticəsi real-time bildirişi.
 *
 * İstifadəçi ödəniş edir → backend emal edir (async) → nəticə hazırdır
 * → Bu event fire olunur → Frontend dərhal nəticəni göstərir
 *
 * Frontend nümunəsi:
 * Echo.private(`payments.${userId}`)
 *     .listen('PaymentResult', (e) => {
 *         if (e.success) {
 *             showToast('Ödəniş uğurlu!', 'success');
 *         } else {
 *             showToast('Ödəniş uğursuz: ' + e.failure_reason, 'error');
 *         }
 *     });
 */
class PaymentResultBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly string $orderId,
        public readonly string $paymentId,
        public readonly bool $success,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $failureReason = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("payments.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PaymentResult';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'payment_id' => $this->paymentId,
            'success' => $this->success,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'failure_reason' => $this->failureReason,
            'timestamp' => now()->toISOString(),
        ];
    }
}
