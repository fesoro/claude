<?php

declare(strict_types=1);

namespace App\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ORDER STATUS CHANGED BROADCAST EVENT
 * ======================================
 * Sifariş statusu dəyişəndə real-time bildiriş göndərir.
 *
 * ═══════════════════════════════════════════════════════════════════
 * LARAVEL BROADCASTING NƏDİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * Broadcasting — server-dən client-ə (brauzer) real-time məlumat göndərmə mexanizmidir.
 * Normal API-da CLIENT sorğu göndərir və cavab alır (pull model).
 * Broadcasting-də SERVER dəyişiklik baş verəndə CLIENT-ə bildiriş PUSH edir.
 *
 * NÜMUNƏ:
 * İstifadəçi sifariş verir → ödəniş emal olunur → status "paid" olur
 * → Server avtomatik brauzerə bildirir → Frontend status-u yeniləyir
 * İstifadəçi heç bir düyməyə basmır, səhifəni yeniləmir!
 *
 * ═══════════════════════════════════════════════════════════════════
 * WEBSOCKET vs POLLING vs SSE:
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. POLLING (köhnə üsul):
 *    Client hər 5 saniyə sorğu göndərir: "Dəyişiklik var?"
 *    Problemlər: Traffic, latency, server yükü.
 *    setInterval(() => fetch('/api/orders/123'), 5000);
 *
 * 2. LONG POLLING:
 *    Client sorğu göndərir, server dəyişiklik olana qədər GÖZLƏYİR.
 *    Daha az traffic, amma hər bağlantı server resursunu tutur.
 *
 * 3. SERVER-SENT EVENTS (SSE):
 *    Server → Client bir istiqamətli axın.
 *    Sadə, amma yalnız bir istiqamətli (client server-ə göndərə bilmir).
 *
 * 4. WEBSOCKET (ən yaxşı):
 *    İki istiqamətli daimi bağlantı.
 *    Server istənilən vaxt client-ə mesaj göndərə bilər.
 *    Client də server-ə göndərə bilər.
 *    Ən az latency, ən az traffic.
 *
 * ═══════════════════════════════════════════════════════════════════
 * LARAVEL BROADCASTING DRİVER-LƏR:
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. REVERB (Laravel Reverb):
 *    - Laravel-in öz WebSocket server-i (yeni, tövsiyə olunan).
 *    - Açıq mənbə, pulsuz, self-hosted.
 *    - php artisan reverb:start ilə başladılır.
 *
 * 2. PUSHER:
 *    - SaaS WebSocket xidməti (pusher.com).
 *    - Quraşdırma çox sadə, amma pullu (pulsuz plan var).
 *    - Production-da ən populyar seçim.
 *
 * 3. ABLY:
 *    - Pusher-ə alternativ SaaS.
 *
 * 4. REDIS (Laravel Echo Server):
 *    - Redis pub/sub + Socket.IO ilə custom server.
 *
 * ═══════════════════════════════════════════════════════════════════
 * CHANNEL TİPLƏRİ:
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. PUBLIC CHANNEL — Channel('orders'):
 *    Hər kəs dinləyə bilər. Auth lazım deyil.
 *    Məsələn: Ümumi bildirişlər, saytda online user sayı.
 *
 * 2. PRIVATE CHANNEL — PrivateChannel('orders.{userId}'):
 *    Yalnız auth olunmuş, icazəli istifadəçi dinləyə bilər.
 *    Məsələn: Öz sifarişlərinin statusu, öz bildirişləri.
 *
 * 3. PRESENCE CHANNEL — PresenceChannel('chat.{roomId}'):
 *    Private + kim online olduğunu göstərir.
 *    Məsələn: Chat otağı, live collaboration.
 *
 * ═══════════════════════════════════════════════════════════════════
 * ShouldBroadcast INTERFACE:
 * ═══════════════════════════════════════════════════════════════════
 *
 * Bu interface implement edildikdə, event fire olunanda
 * Laravel avtomatik broadcasting driver-ə göndərir.
 *
 * ShouldBroadcast → Queue vasitəsilə async göndərir (tövsiyə)
 * ShouldBroadcastNow → Sinxron göndərir (dərhal, amma yavaşladır)
 */
class OrderStatusChangedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $orderId,
        public readonly string $userId,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly ?string $totalAmount = null,
    ) {}

    /**
     * Event hansı kanallarda yayımlanacaq.
     *
     * PrivateChannel istifadə edirik — yalnız sifariş sahibi görə bilər.
     * 'orders.{userId}' → hər istifadəçinin öz kanalı var.
     *
     * routes/channels.php-də auth yoxlaması təyin olunur:
     * Broadcast::channel('orders.{userId}', fn($user, $userId) => $user->id === $userId);
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orders.{$this->userId}"),
        ];
    }

    /**
     * Event adı — frontend bu adla dinləyir.
     *
     * Frontend (Laravel Echo):
     * Echo.private(`orders.${userId}`)
     *     .listen('OrderStatusChanged', (e) => {
     *         console.log('Status dəyişdi:', e.new_status);
     *         updateOrderUI(e.order_id, e.new_status);
     *     });
     */
    public function broadcastAs(): string
    {
        return 'OrderStatusChanged';
    }

    /**
     * Yayımlanan data — frontend bu datanı alır.
     * Yalnız lazım olan sahələri göndəririk (təhlükəsizlik).
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'total_amount' => $this->totalAmount,
            'timestamp' => now()->toISOString(),
        ];
    }
}
