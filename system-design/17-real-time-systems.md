# Real-Time Systems

## Nədir? (What is it?)

Real-time systems data-nı dərhal (milliseconds-seconds) istifadəçiyə çatdıran
sistemlərdir. Traditional HTTP request-response model-indən fərqli olaraq, server
aktiv şəkildə client-ə data push edə bilir. Chat, live notifications, stock prices,
live scores kimi tətbiqlər real-time communication tələb edir.

Sadə dillə: telefon zəngi (real-time) vs email (request-response). Telefonda
hər iki tərəf eyni anda danışıb eşidə bilir.

```
Traditional HTTP:           Real-Time:
Client → Server             Client ⇄ Server
Client ← Server             (bidirectional, persistent)
(request-response)          (server can push anytime)
```

## Əsas Konseptlər (Key Concepts)

### Communication Methods

**1. Short Polling:**
```
Client: "Yeni mesaj var?"     → Server: "Yox"
(2 saniyə gözlə)
Client: "Yeni mesaj var?"     → Server: "Yox"
(2 saniyə gözlə)
Client: "Yeni mesaj var?"     → Server: "Bəli! 1 mesaj"

Problem: Çox request, bandwidth israf, latency (poll interval qədər)
```

**2. Long Polling:**
```
Client: "Yeni mesaj var?"     → Server: (gözləyir... 30 saniyəyə qədər)
                              → Server: "Bəli! Mesaj var" (cavab göndərir)
Client: "Yeni mesaj var?"     → Server: (yenidən gözləyir...)

Better: Daha az request, amma hər cavabdan sonra yeni connection
```

**3. Server-Sent Events (SSE):**
```
Client: GET /events (Accept: text/event-stream)
Server: (connection açıq qalır)
Server → Client: data: {"type": "message", "text": "Hello"}
Server → Client: data: {"type": "notification", "count": 5}
Server → Client: data: {"type": "update", "status": "shipped"}

One-way: Server → Client only
Good for: Notifications, live feeds, stock prices
```

**4. WebSocket:**
```
Client: GET /ws (Upgrade: websocket)
Server: 101 Switching Protocols

Client ⇄ Server: Full-duplex, bidirectional
Client → Server: {"type": "message", "text": "Hi!"}
Server → Client: {"type": "message", "text": "Hello!"}
Server → Client: {"type": "typing", "user": "John"}

Best for: Chat, gaming, collaboration tools
```

### Comparison

```
| Feature        | Polling | Long Poll | SSE        | WebSocket  |
|----------------|---------|-----------|------------|------------|
| Direction      | Client→S| Client→S  | Server→C   | Both ways  |
| Latency        | High    | Medium    | Low        | Very Low   |
| Overhead       | High    | Medium    | Low        | Very Low   |
| Complexity     | Low     | Medium    | Low        | High       |
| Browser Support| All     | All       | Most       | All modern |
| Connection     | New each| New each  | Persistent | Persistent |
| Binary data    | No      | No        | No         | Yes        |
```

### Channel Types

```
Public Channel:     Everyone can subscribe (live scores)
Private Channel:    Authenticated users only (user notifications)
Presence Channel:   Know who is online (chat room members)
Personal Channel:   Only for specific user (private messages)
```

### Connection Management

```
┌─────────────────────────────────────────────────┐
│              WebSocket Server                    │
│                                                  │
│  Connections Pool:                               │
│  ┌────────────────────────────────────────┐     │
│  │ user:1 → [conn_a, conn_b]  (2 devices)│     │
│  │ user:2 → [conn_c]          (1 device) │     │
│  │ user:3 → [conn_d, conn_e, conn_f]     │     │
│  └────────────────────────────────────────┘     │
│                                                  │
│  Channel Subscriptions:                          │
│  ┌────────────────────────────────────────┐     │
│  │ chat:room-1 → [user:1, user:2]        │     │
│  │ chat:room-2 → [user:2, user:3]        │     │
│  │ presence:lobby → [user:1, user:2, u:3]│     │
│  └────────────────────────────────────────┘     │
│                                                  │
│  Heartbeat: ping/pong every 30 seconds          │
│  Reconnection: auto-reconnect with backoff      │
└─────────────────────────────────────────────────┘
```

### Scaling WebSocket Servers

```
                    ┌──────────────┐
                    │ Load Balancer│
                    │ (sticky      │
                    │  sessions)   │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
       ┌──────┴──┐  ┌─────┴───┐  ┌────┴─────┐
       │  WS     │  │  WS     │  │  WS      │
       │ Server 1│  │ Server 2│  │ Server 3 │
       │ (1000   │  │ (1000   │  │ (1000    │
       │  conns) │  │  conns) │  │  conns)  │
       └────┬────┘  └────┬────┘  └────┬─────┘
            │            │            │
            └────────────┼────────────┘
                         │
                  ┌──────┴──────┐
                  │    Redis    │
                  │  Pub/Sub    │
                  │             │
                  │ (message    │
                  │  broadcast  │
                  │  across     │
                  │  servers)   │
                  └─────────────┘

User A on Server 1 sends message to User B on Server 3:
1. Server 1 receives message from User A
2. Server 1 publishes to Redis channel
3. All servers receive from Redis
4. Server 3 forwards to User B
```

## Arxitektura (Architecture)

### Complete Real-Time Architecture

```
┌─────────┐  ┌─────────┐  ┌─────────┐
│ Web App │  │ Mobile  │  │ Desktop │
│ (Echo)  │  │ (SDK)   │  │ (SDK)   │
└────┬────┘  └────┬────┘  └────┬────┘
     │            │            │
     └────────────┼────────────┘
                  │ WebSocket
           ┌──────┴──────┐
           │  WS Server  │
           │  (Reverb/   │
           │   Pusher)   │
           └──────┬──────┘
                  │
           ┌──────┴──────┐
           │    Redis     │
           │   Pub/Sub    │
           └──────┬──────┘
                  │
           ┌──────┴──────┐
           │   Laravel    │
           │   Backend    │
           │              │
           │ broadcast(   │
           │  new Event)  │
           └─────────────┘
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel Reverb (Native WebSocket Server)

```php
// Installation
// composer require laravel/reverb
// php artisan reverb:install

// config/reverb.php
return [
    'default' => env('REVERB_SERVER', 'reverb'),
    'servers' => [
        'reverb' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'hostname' => env('REVERB_HOST', null),
            'options' => [
                'tls' => [],
            ],
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
            ],
            'pulse_ingest_interval' => 15,
        ],
    ],
];

// Start Reverb server
// php artisan reverb:start
```

### Broadcasting Events

```php
// app/Events/NewChatMessage.php
class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ChatMessage $message,
        public readonly User $sender
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("chat.{$this->message->room_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'text' => $this->message->text,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar_url,
            ],
            'sent_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}

// app/Events/UserTyping.php
class UserTyping implements ShouldBroadcastNow  // No queue, instant
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $roomId,
        public readonly int $userId,
        public readonly string $userName
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("chat.{$this->roomId}");
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }
}

// Controller trigger
class ChatController extends Controller
{
    public function sendMessage(SendMessageRequest $request, int $roomId): JsonResponse
    {
        $message = ChatMessage::create([
            'room_id' => $roomId,
            'user_id' => auth()->id(),
            'text' => $request->validated('text'),
        ]);

        broadcast(new NewChatMessage($message, auth()->user()))->toOthers();

        return response()->json(new ChatMessageResource($message), 201);
    }

    public function typing(int $roomId): JsonResponse
    {
        broadcast(new UserTyping($roomId, auth()->id(), auth()->user()->name));

        return response()->json(['status' => 'ok']);
    }
}
```

### Channel Authorization

```php
// routes/channels.php
Broadcast::channel('chat.{roomId}', function (User $user, int $roomId) {
    $room = ChatRoom::find($roomId);

    if ($room && $room->members()->where('user_id', $user->id)->exists()) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar_url,
        ];
    }

    return false;
});

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return Order::where('id', $orderId)->where('user_id', $user->id)->exists();
});
```

### Laravel Echo (Frontend)

```javascript
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Listen for messages in a chat room
Echo.join(`chat.${roomId}`)
    // Presence channel - know who is online
    .here((users) => {
        this.onlineUsers = users;
    })
    .joining((user) => {
        this.onlineUsers.push(user);
    })
    .leaving((user) => {
        this.onlineUsers = this.onlineUsers.filter(u => u.id !== user.id);
    })
    // Listen for events
    .listen('.message.sent', (e) => {
        this.messages.push(e);
    })
    .listen('.user.typing', (e) => {
        this.showTypingIndicator(e.userName);
    });

// Private channel for personal notifications
Echo.private(`user.${userId}`)
    .notification((notification) => {
        this.notifications.push(notification);
    });
```

### Server-Sent Events (SSE) with Laravel

```php
// SSE for simpler use cases (one-way server → client)
class SSEController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {
            $userId = auth()->id();
            $lastEventId = $request->header('Last-Event-ID', 0);

            while (true) {
                // Check for new notifications
                $notifications = Notification::where('user_id', $userId)
                    ->where('id', '>', $lastEventId)
                    ->orderBy('id')
                    ->take(10)
                    ->get();

                foreach ($notifications as $notification) {
                    echo "id: {$notification->id}\n";
                    echo "event: notification\n";
                    echo "data: " . json_encode($notification->toArray()) . "\n\n";
                    $lastEventId = $notification->id;
                }

                // Heartbeat
                if ($notifications->isEmpty()) {
                    echo ": heartbeat\n\n";
                }

                ob_flush();
                flush();

                if (connection_aborted()) break;
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

// Frontend SSE
// const source = new EventSource('/api/stream');
// source.addEventListener('notification', (e) => {
//     const data = JSON.parse(e.data);
//     console.log('New notification:', data);
// });
```

### Presence Tracking

```php
class PresenceService
{
    public function __construct(private \Redis $redis) {}

    public function setOnline(int $userId, string $connectionId): void
    {
        $this->redis->hset("presence:users", $userId, json_encode([
            'connection_id' => $connectionId,
            'last_seen' => now()->timestamp,
        ]));
        $this->redis->expire("presence:users:{$userId}", 120);
    }

    public function setOffline(int $userId): void
    {
        $this->redis->hdel("presence:users", $userId);
    }

    public function isOnline(int $userId): bool
    {
        return $this->redis->hexists("presence:users", $userId);
    }

    public function getOnlineUsers(array $userIds): array
    {
        $online = [];
        foreach ($userIds as $id) {
            if ($this->isOnline($id)) {
                $online[] = $id;
            }
        }
        return $online;
    }
}
```

## Real-World Nümunələr

1. **Slack** - WebSocket for messages, presence, typing indicators
2. **WhatsApp Web** - WebSocket for real-time messaging
3. **Figma** - WebSocket for real-time collaborative design
4. **Binance** - WebSocket for real-time price tickers
5. **Google Docs** - Operational Transformation over WebSocket

## Interview Sualları

**S1: WebSocket vs SSE - nə vaxt hansını istifadə etmək lazımdır?**
C: WebSocket - bidirectional communication lazım olanda (chat, gaming). SSE -
yalnız server→client push lazım olanda (notifications, live feed, stock prices).
SSE daha sadədir, auto-reconnect var, HTTP/2 ilə yaxşı işləyir.

**S2: WebSocket connection-ları necə scale edilir?**
C: Horizontal scaling: sticky sessions ilə load balancing, Redis Pub/Sub ilə
server-lər arası mesaj broadcast. Hər server öz connection-larını idarə edir,
Redis vasitəsilə digər server-lərdəki client-lərə mesaj çatdırılır.

**S3: Connection drop olduqda nə baş verir?**
C: Auto-reconnect with exponential backoff. Client reconnect edəndə son alınan
event ID-ni göndərir, server missed events-i göndərir. Offline queue ilə
mesajlar saxlanır, online olanda deliver edilir.

**S4: Presence system necə işləyir?**
C: Heartbeat mechanism - client hər 30 saniyə ping göndərir. Server cavab almasa
user offline sayılır. Redis-də online users saxlanır. Presence channel ilə
room-da kim olduğunu real-time görürsən.

**S5: 1 milyon concurrent WebSocket connection necə idarə olunur?**
C: Horizontal scaling (10+ server, hər biri 100K connection), Redis Pub/Sub
və ya Kafka ilə cross-server messaging, connection pooling, efficient
memory management, kernel tuning (file descriptor limits).

**S6: Real-time data ordering necə təmin olunur?**
C: Timestamp + sequence number ilə hər mesaja sıra nömrəsi verin. Client
tərəfində reordering buffer saxlayın. Out-of-order mesajları düzgün
pozisiyaya qoyun. Server-side ordering üçün single-threaded processing.

## Best Practices

1. **Heartbeat** - Connection canlılığını yoxlayın (ping/pong)
2. **Auto-Reconnect** - Exponential backoff ilə avtomatik reconnect
3. **Message Queuing** - Offline müddətdə mesajları saxlayın
4. **Authentication** - WebSocket connection auth token ilə qoruyun
5. **Rate Limiting** - Message flood-dan qorunun
6. **Compression** - WebSocket permessage-deflate ilə bandwidth azaldın
7. **Graceful Degradation** - WebSocket işləmirsə long polling-ə fallback
8. **Binary Protocol** - Yüksək throughput üçün JSON əvəzinə binary
9. **Connection Limits** - Per-user connection limit qoyun
10. **Monitoring** - Connection count, message rate, latency track edin
