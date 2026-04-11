# Real-time Notifications

## Ssenari

İstifadəçilərə order statusu, payment, sistem xəbərdarlıqları kimi real-time bildirişlər göndərmək lazımdır. Müxtəlif transport seçimləri: SSE, WebSocket, Long Polling.

---

## Transport Müqayisəsi

```
┌─────────────────┬────────────────┬───────────────┬─────────────────┐
│                 │   Long Polling │      SSE      │    WebSocket    │
├─────────────────┼────────────────┼───────────────┼─────────────────┤
│ Protocol        │ HTTP           │ HTTP          │ WS (TCP)        │
│ Direction       │ Client→Server  │ Server→Client │ Bidirectional   │
│ Connection      │ Yeni hər req   │ Persistent    │ Persistent      │
│ Real-time       │ ~delay         │ ✅            │ ✅              │
│ Server load     │ Orta           │ Az            │ Az              │
│ Complexity      │ Sadə           │ Orta          │ Yüksək          │
│ Proxy/Firewall  │ ✅             │ ✅            │ Bəzən problem   │
│ Use case        │ Simple poll    │ Notifications │ Chat, games     │
└─────────────────┴────────────────┴───────────────┴─────────────────┘

Recommendation:
  Unidirectional notifications → SSE (sadə, HTTP)
  Bidirectional (chat, collab) → WebSocket
  Fallback support lazım → Long Polling
```

---

## Arxitektura

```
User (Browser)                   App Server              Backend
      │                               │                      │
      │── SSE connect ───────────────►│                      │
      │   GET /notifications/stream   │                      │
      │                               │                      │
      │                          Order paid (event)          │
      │                               │◄─────────────────────│
      │                         ┌─────▼──────┐               │
      │                         │   Redis    │               │
      │                         │  Pub/Sub   │               │
      │                         └─────┬──────┘               │
      │◄── SSE event ─────────────────│                      │
      │  data: {"type":"order.paid"}  │                      │
```

---

## SSE İmplementasiyası

*Bu kod SSE axını quran, missed event-ləri göndərən və Redis Pub/Sub-dan real-time bildirişlər yayan controller-ı göstərir:*

```php
// SSE Controller
class NotificationStreamController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        
        return response()->stream(function () use ($user) {
            // SSE formatı
            $this->sendEvent('connected', [
                'message' => 'Bağlantı quruldu',
                'user_id' => $user->id,
            ]);
            
            $channel = "user.{$user->id}.notifications";
            $lastEventId = request()->header('Last-Event-ID', 0);
            
            // Missed events-i göndər (reconnect case)
            $this->sendMissedEvents($user->id, $lastEventId);
            
            // Redis Pub/Sub-dan dinlə
            $redis = app(\Redis::class);
            $redis->subscribe([$channel], function ($message, $channel) use ($redis) {
                $data = json_decode($message, true);
                
                $this->sendEvent($data['type'], $data, $data['id'] ?? null);
                
                // Flush — browser-ə çatdır
                ob_flush();
                flush();
                
                // Connection closed?
                if (connection_aborted()) {
                    $redis->unsubscribe();
                }
            });
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',  // Nginx buffering-i söndür
            'Connection'        => 'keep-alive',
        ]);
    }
    
    private function sendEvent(string $event, array $data, ?int $id = null): void
    {
        if ($id !== null) {
            echo "id: $id\n";
        }
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
    }
    
    private function sendMissedEvents(int $userId, int $lastEventId): void
    {
        if ($lastEventId <= 0) return;
        
        $missed = Notification::where('user_id', $userId)
            ->where('id', '>', $lastEventId)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->orderBy('id')
            ->get();
        
        foreach ($missed as $notification) {
            $this->sendEvent($notification->type, $notification->data, $notification->id);
        }
    }
}
```

**Redis Pub/Sub dispatcher:**

*Bu kod bildirişi DB-yə persist edib Redis Pub/Sub vasitəsilə real-time göndərən notification servisini göstərir:*

```php
class RealTimeNotificationService
{
    public function __construct(
        private \Redis $redis
    ) {}
    
    public function notify(int $userId, string $type, array $data): void
    {
        // DB-yə persist et (history + missed events)
        $notification = Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'data'    => $data,
            'read_at' => null,
        ]);
        
        // Redis pub/sub ilə real-time göndər
        $message = json_encode([
            'id'   => $notification->id,
            'type' => $type,
            ...$data,
        ]);
        
        $this->redis->publish("user.$userId.notifications", $message);
    }
    
    public function notifyMany(array $userIds, string $type, array $data): void
    {
        foreach ($userIds as $userId) {
            $this->notify($userId, $type, $data);
        }
    }
    
    public function broadcast(string $channel, string $type, array $data): void
    {
        $message = json_encode(['type' => $type, ...$data]);
        $this->redis->publish($channel, $message);
    }
}
```

---

## Laravel Broadcasting

*Bu kod Laravel Broadcasting konfiqurasiyasını, `ShouldBroadcast` event-ini və private channel authorization-ı göstərir:*

```php
// config/broadcasting.php
'default' => env('BROADCAST_DRIVER', 'redis'),

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
    ],
    'pusher' => [...],
    'ably'   => [...],
],

// Event
class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public readonly Order $order,
        public readonly string $newStatus,
    ) {}
    
    // Hansı channel-da broadcast olsun
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->order->customer_id}"),
            new Channel("order.{$this->order->id}"),
        ];
    }
    
    // Hansı event adı ilə
    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }
    
    // Hansı data göndərilsin
    public function broadcastWith(): array
    {
        return [
            'order_id'   => $this->order->id,
            'new_status' => $this->newStatus,
            'updated_at' => $this->order->updated_at->toISOString(),
        ];
    }
    
    // Condition: bəzən broadcast etmə
    public function broadcastWhen(): bool
    {
        return $this->order->customer_id !== null;
    }
}

// Event dispatch et
broadcast(new OrderStatusChanged($order, 'shipped'))->toOthers();

// Channel authorization (PrivateChannel üçün)
// routes/channels.php
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user->id === (int) $userId;
});
```

---

## Database-backed Notification

*Bu kod bildirişləri sıralayan, oxunmuş kimi işarələyən controller-ı və unread count-u cache-ə alan servis sinfini göstərir:*

```php
// Notifications cədvəli + unread count

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);
        
        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $request->user()->unreadNotifications()->count(),
        ]);
    }
    
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);
        
        $notification->update(['read_at' => now()]);
        
        return response()->json(['status' => 'marked as read']);
    }
    
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        
        return response()->json(['status' => 'all marked as read']);
    }
}

// Unread count-u cache-lər
class CachedUnreadCountService
{
    public function getCount(int $userId): int
    {
        return Cache::remember(
            "user.$userId.unread_notifications",
            60,
            fn() => Notification::where('user_id', $userId)
                ->whereNull('read_at')
                ->count()
        );
    }
    
    public function invalidate(int $userId): void
    {
        Cache::forget("user.$userId.unread_notifications");
    }
}
```

---

## Horizontal Scaling

```
Problem: Multi-server deployment-də SSE:
  User1 → Server A-ya connected
  User1-ə notification göndər → Server B-yə gəldi
  Server B-nin User1 ilə SSE connection-u yoxdur!

Həll: Redis Pub/Sub (hər server subscribe olur)

  ┌──────────────────────────────────────────────────┐
  │                    Redis                         │
  │                   Pub/Sub                        │
  └──────────┬──────────────────┬────────────────────┘
             │                  │
  ┌──────────▼──────┐  ┌────────▼────────┐
  │   Server A      │  │   Server B      │
  │  [User1 SSE]    │  │  [User2 SSE]    │
  │  [User3 SSE]    │  │  [User4 SSE]    │
  └─────────────────┘  └─────────────────┘
  
  Notification User1-ə → Redis publish
  Hər iki server alır
  Server A: User1 subscription var → SSE göndər ✅
  Server B: User1 subscription yoxdur → skip ✅
```

---

## İntervyu Sualları

**S: SSE vs WebSocket nə vaxt hansını seç?**
C: SSE: Server→Client unidirectional, order updates, news feed, live prices kimi use-case-lər üçün. HTTP/2 üzərindən, proxy-friendly, auto-reconnect built-in, simpledr. WebSocket: Bidirectional, chat, multiplayer games, collaborative editing, real-time collaboration. Daha çox complexity amma tam duplex. Əgər client-dən server-ə sadəcə acknowledgment göndərmək lazımdırsa — SSE + REST endpoint kombinasiyası WebSocket-dən sadədir.

**S: Multi-server SSE deployment-də Redis Pub/Sub-un rolu nədir?**
C: Problem niyə yaranır: User Server A-ya SSE connection qurub, amma notification Server B-ə gəldi. Server B-nin bu user-in SSE connection-u yoxdur. Həll: hər server Redis channel-ına (`user.{id}.notifications`) subscribe olur. Notification publish ediləndə bütün serverlər alır. Yalnız həmin user-in SSE connection-unu tutan server göndərir, digərləri skip edir. Bu sayədə horizontal scaling mümkün olur.

**S: Missed notification-ları necə handle etmək olar?**
C: SSE-nin `Last-Event-ID` header-i reconnect-də gəlir. Server bu ID-dən sonrakı missed notification-ları DB-dən oxuyub göndərir. Bu sayədə qısa disconnection-larda data itirilmir. Kritik: notification-ları DB-yə persist etmək vacibdir, yalnız Redis Pub/Sub-a güvənmə — Pub/Sub "fire-and-forget"dir, subscriber offline-dırsa mesajı almir.

**S: SSE-ni Nginx/PHP-FPM ilə konfiqurasiya etmək üçün nə lazımdır?**
C: Nginx-də `proxy_buffering off` və `X-Accel-Buffering: no` header-i əlavə et (buffering SSE real-time-lığını məhv edir). PHP-FPM-də `fastcgi_read_timeout`-u artır. Connection timeout uzatmaq lazımdır. Laravel-də `response()->stream()` istifadə et, `ob_flush(); flush()` ilə hər event-i dərhal göndər. Redis Pub/Sub blocking call-dır — bu əlaqəni ayrı process/worker-da idarə etmək lazımdır.

**S: Real-time bildirişlərdə performance üçün nə nəzərə alınmalıdır?**
C: Unread count-u Redis-də cache-lə (hər request-də DB COUNT etmə). Notification-ları DB-yə async yaz (queue ilə). SSE connection-larını limit et (per user: 1-2, per IP). Nginx/load balancer-in SSE buffering-ini söndür. Per-user SSE channel-ı Redis SUBSCRIBE bloklanacağı üçün uzun yaşayan process açır — connection sayı çox olarsa server resursları tükənir. Buna görə WebSocket server (Soketi, Reverb) ya da Pusher/Ably kimi managed servis daha uyğundur.

**S: Laravel Reverb nədir, nə vaxt istifadə edilir?**
C: Laravel 11-də təqdim edilmiş rəsmi WebSocket server. Pusher protokolunu implement edir — Laravel Broadcasting ilə tam uyğundur. Öz serverindən işlədilir (self-hosted). Pusher/Ably-yə alternativdir. Horizontally scale etmək üçün Redis adapter lazımdır. Use case: managed WebSocket servisi üçün ödəniş etmək istəmirsən, öz infrastrukturunu idarə edirsən.

**S: Push notification (mobile) ilə real-time SSE fərqi nədir?**
C: SSE/WebSocket: browser-da açıq connection lazımdır — app bağlıdırsa mesaj çatmır. Push notification (FCM/APNs): OS-level delivery — app bağlı olsa belə notification göstərilir. Strategiya: istifadəçi aktiv+online ise SSE/WebSocket, offline ise push notification. Laravel-in `ShouldBroadcastNow` + `notifiable` sistemi hər iki kanala bir API ilə göndərmək imkanı verir.

---

## Anti-patternlər

**1. Hər notification-ı birbaşa DB-yə sinxron yazmaq**
Real-time bildiriş göndərərkən eyni anda `INSERT INTO notifications` etmək — yüksək yükdə DB bottleneck olur, SSE/WebSocket cavabı gecikir. Notification-ı queue-ya at, DB-yə yazmağı async et.

**2. Multi-server mühitdə Redis Pub/Sub olmadan SSE qurmaq**
Load balancer arxasında bir neçə server işlədərkən Redis olmadan SSE connection saxlamaq — istifadəçi server A-ya qoşulub, notification server B-yə göndərilir, user heç nə almır. Bütün serverlərin paylaşdığı Redis Pub/Sub kanalı istifadə et.

**3. Unread count-u hər request-də COUNT sorğusu ilə hesablamaq**
`SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL` sorğusunu hər API çağırışında etmək — milyonlarla istifadəçidə DB-yə ağır yük. Unread count-u Redis-də cache-lə, yalnız dəyişiklikdə yenilə.

**4. Nginx/proxy-nin SSE buffering-ini söndürməmək**
Default olaraq Nginx SSE cavablarını bufer edir — istifadəçi bildirişi saniyələrlə gecikmiş alır, SSE-nin real-time üstünlüyü itir. `X-Accel-Buffering: no` header-i əlavə et, proxy buffering-i deaktiv et.

**5. `Last-Event-ID` ilə missed notification-ları handle etməmək**
SSE reconnect-də `Last-Event-ID` header-ini nəzərə almamaq — qısa disconnection zamanı göndərilmiş bildirişlər itir, istifadəçi məlumatı qaçırır. Reconnect-də bu ID-dən sonrakı bütün missed notification-ları DB-dən oxuyub göndər.

**6. Hər istifadəçiyə limit olmadan SSE connection icazəsi vermək**
Per-user SSE connection limitini tətbiq etməmək — eyni hesabdan minlərlə tab açılır, hər biri bir connection tutur, server resursları tükənir. Hər istifadəçi üçün maksimum connection sayını məhdudlaşdır, köhnə connection-ları bağla.
