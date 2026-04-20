# WebSockets Deep Dive (Reverb, Ratchet, Soketi, Pusher)

## Mündəricat
1. [WebSocket nədir?](#websocket-nədir)
2. [HTTP polling vs long-polling vs SSE vs WebSocket](#http-polling-vs-long-polling-vs-sse-vs-websocket)
3. [Protocol deep](#protocol-deep)
4. [Laravel Broadcasting](#laravel-broadcasting)
5. [Laravel Reverb (native, 2024+)](#laravel-reverb)
6. [Soketi (Pusher-compatible)](#soketi)
7. [Ratchet (low-level)](#ratchet)
8. [Private və Presence channels](#private-və-presence-channels)
9. [Scaling WebSocket](#scaling-websocket)
10. [Production problems](#production-problems)
11. [İntervyu Sualları](#intervyu-sualları)

---

## WebSocket nədir?

```
WebSocket — bidirectional, full-duplex əlaqə.
Klient və server BİR TCP connection üzərindən istədikləri vaxt mesaj göndərə bilər.

HTTP:
  Client → Server (request)
  Server → Client (response)
  Connection bağlandı.
  Yeni mesaj üçün yeni request.

WebSocket:
  Client → Server (upgrade handshake)
  Connection açıq qalır (həftələrlə ola bilər).
  Client → Server və Server → Client istənilən vaxt, istənilən istiqamətdə.

Protokol:
  1. Client HTTP GET göndərir:
     GET /ws HTTP/1.1
     Upgrade: websocket
     Connection: Upgrade
     Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
     Sec-WebSocket-Version: 13
  
  2. Server "upgrade" cavab verir:
     HTTP/1.1 101 Switching Protocols
     Upgrade: websocket
     Connection: Upgrade
     Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=
  
  3. İndi raw TCP frame-ləri göndərilir — HTTP daha yox.
  4. wss:// = WebSocket over TLS (443 port)

Use cases:
  - Real-time chat
  - Live notifications
  - Stock tickers, crypto prices
  - Multiplayer games
  - Collaborative editing (Figma, Google Docs)
  - IoT telemetry
```

---

## HTTP polling vs long-polling vs SSE vs WebSocket

```
1. SHORT POLLING
   Client hər 5 saniyədən bir GET /messages
   ✗ Çox bandwidth (hətta yeni mesaj yoxsa da)
   ✗ Latency 5s-ə qədər
   ✓ Sadə, proxy-firewall dostu

2. LONG POLLING
   Client GET /messages → server cavab verməyib gözləyir
   Yeni mesaj gəlir → server cavab göndərir → client yenə GET
   ✓ Aşağı bandwidth
   ✗ Connection-lar uzun açıq qalır (server resource)
   ✗ One-way (client → server request-response)

3. SERVER-SENT EVENTS (SSE)
   Content-Type: text/event-stream
   Server → Client stream (one-way)
   ✓ HTTP üzərində, proxy dostu
   ✓ Auto-reconnect browser tərəfdən built-in
   ✗ YALNIZ server → client (client → server yox)
   ✗ 6 connection per domain limit (browser)
   
   Use case: Notifications, stock tickers, log streams

4. WEBSOCKET
   Full-duplex
   ✓ Bidirectional
   ✓ Aşağı latency (TCP frame)
   ✓ Binary dəstəyi
   ✗ Proxy/firewall bəzən bloklayır (sticky sessions lazım)
   ✗ Scaling çətin (stateful connection)

Qərar matrisi:
  One-way server→client, simple:  SSE
  Two-way, chat/game:              WebSocket
  Mobile, battery-sensitive:       Push notification + polling
```

---

## Protocol deep

```
WebSocket frame format:

 0                   1                   2                   3
 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
+-+-+-+-+-------+-+-------------+-------------------------------+
|F|R|R|R| opcode|M| Payload len |    Extended payload length    |
|I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
|N|V|V|V|       |S|             |   (if payload len==126/127)   |
| |1|2|3|       |K|             |                               |
+-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
|     Extended payload length continued, if payload len == 127  |
+ - - - - - - - - - - - - - - - +-------------------------------+
|                               |Masking-key, if MASK set to 1  |
+-------------------------------+-------------------------------+
| Masking-key (continued)       |          Payload Data         |
+-------------------------------- - - - - - - - - - - - - - - - +

opcode:
  0x0 = continuation
  0x1 = text (UTF-8)
  0x2 = binary
  0x8 = close
  0x9 = ping
  0xA = pong

Ping/Pong — connection keep-alive.
Close frame — qalib bağlanma.

Control frames < 125 byte, fragmentation yox.
```

---

## Laravel Broadcasting

```php
<?php
// Laravel Broadcasting — WebSocket abstraksiyası
// Driver: pusher, ably, reverb, log, null

// config/broadcasting.php
return [
    'default' => env('BROADCAST_DRIVER', 'reverb'),
    
    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key'    => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host'   => env('REVERB_HOST'),
                'port'   => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
            ],
        ],
    ],
];
```

```php
<?php
// Event broadcast
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageSent implements ShouldBroadcast
{
    public function __construct(
        public readonly int $chatId,
        public readonly string $user,
        public readonly string $text,
    ) {}
    
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->chatId}"),
        ];
    }
    
    // Custom event name (default: class name)
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
    
    // Hansı data frontend-ə göndərilir
    public function broadcastWith(): array
    {
        return [
            'user' => $this->user,
            'text' => $this->text,
            'at'   => now()->toIso8601String(),
        ];
    }
    
    // Hazırkı user üçün öz eventini göndərməmək
    public function broadcastToEveryoneExcept(): ?string
    {
        return request()->header('X-Socket-ID');
    }
}

// Event trigger
event(new MessageSent(chatId: 42, user: 'Ali', text: 'Salam'));
```

```js
// Frontend (Echo + Pusher/Reverb JS)
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],
});

Echo.private(`chat.${chatId}`)
    .listen('.message.sent', (e) => {
        console.log(e.user, e.text);
    });
```

---

## Laravel Reverb

```bash
# Quraşdırma
composer require laravel/reverb
php artisan reverb:install

# Start server
php artisan reverb:start --host=0.0.0.0 --port=8080

# Production — Supervisor
# /etc/supervisor/conf.d/reverb.conf
# [program:reverb]
# command=php /var/www/app/artisan reverb:start --host=0.0.0.0 --port=8080
# autostart=true
# autorestart=true
# user=www-data
# stdout_logfile=/var/log/reverb.log
```

```
Laravel Reverb nədir?
  - 2024-də Laravel 11 ilə gəldi
  - Native Laravel WebSocket server
  - ReactPHP əsaslı (long-running)
  - Pusher-compatible protokol → Echo yenilik etmədən işləyir
  - Horizontal scaling: Redis pub/sub ilə

Niyə Pusher əvəzinə Reverb?
  ✓ Self-hosted — data sizin infrastruktura qalır
  ✓ Sonsuz connection — Pusher məhdud plan
  ✓ Pulsuz (open-source)
  ✗ Operational load — özünüz scale etməlisiniz
```

---

## Soketi

```yaml
# docker-compose.yml
services:
  soketi:
    image: quay.io/soketi/soketi:latest-16-alpine
    ports:
      - "6001:6001"   # WebSocket
      - "9601:9601"   # Metrics
    environment:
      SOKETI_DEBUG: "0"
      SOKETI_DEFAULT_APP_ID: app-id
      SOKETI_DEFAULT_APP_KEY: app-key
      SOKETI_DEFAULT_APP_SECRET: app-secret
      SOKETI_METRICS_ENABLED: "1"
    restart: unless-stopped

  # Horizontal scaling — Redis adapter
  redis:
    image: redis:alpine
```

```
Soketi:
  - Node.js əsaslı Pusher-compatible WebSocket server
  - uWebSockets.js istifadə edir (C++) → çox performant
  - Laravel Pusher driver ilə avtomatik işləyir
  - Prometheus metrics built-in
  - Horizontal scaling: Redis adapter (Redis pub/sub)
  
Müqayisə:
  Reverb:  PHP (ReactPHP), Laravel ecosystem
  Soketi:  Node.js + C++, daha sürətli (~2-3× əlavə throughput)
  Pusher:  SaaS, managed, zero-ops
```

---

## Ratchet

```php
<?php
// Ratchet — low-level WebSocket server (PHP, ReactPHP)
// Laravel olmayan ssenarilər üçün

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class Chat implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }
    
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        echo "Connection {$conn->resourceId} opened\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Bütün client-lərə broadcast (author-dan başqa)
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send($msg);
            }
        }
    }
    
    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $conn->close();
    }
}

$server = IoServer::factory(
    new HttpServer(new WsServer(new Chat())),
    8080
);
$server->run();
```

---

## Private və Presence channels

```php
<?php
// routes/channels.php — authorization callback
Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    // return true → user kanala abunə ola bilər
    return ChatMember::where('chat_id', $chatId)
        ->where('user_id', $user->id)
        ->exists();
});

// Presence channel — "kim online" bilir
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    if (Room::find($roomId)->hasMember($user)) {
        return [
            'id'     => $user->id,
            'name'   => $user->name,
            'avatar' => $user->avatar_url,
        ];
    }
    return null;
});
```

```js
// Frontend — presence
Echo.join(`room.${roomId}`)
    .here((users) => {
        console.log('Currently online:', users);
    })
    .joining((user) => {
        console.log(user.name, 'joined');
    })
    .leaving((user) => {
        console.log(user.name, 'left');
    })
    .listen('.new-message', (e) => {
        // ...
    });
```

---

## Scaling WebSocket

```
Problem: WebSocket stateful — load balancer-də sticky session lazım.
Bir user 1 server-də, dostu başqa server-də → message paylanmır.

Həll: Pub/Sub backend
  ┌─ Server A (WS) ─┐
  ├─ Server B (WS) ─┤ ── Redis pub/sub ── hamısı "message.sent" subscribe
  └─ Server C (WS) ─┘
  
  User-1 Server A-da, User-2 Server C-də.
  Server A mesajı Redis-ə publish → Server C öz client-inə ötürür.

Reverb ilə:
  # .env
  # REVERB_SCALING_ENABLED=true
  # REVERB_SCALING_CHANNEL=reverb
  # REDIS_HOST=redis
```

```
Load balancing:
  - L7 (nginx) proxy_pass + proxy_http_version 1.1 + Upgrade header
  - Sticky session (ip_hash və ya consistent hash)
  - CLOSE_WAIT problem — timeout yüksək olmalı (600s+)

Nginx config:
  location /ws {
      proxy_pass http://reverb_backend;
      proxy_http_version 1.1;
      proxy_set_header Upgrade $http_upgrade;
      proxy_set_header Connection "upgrade";
      proxy_read_timeout 3600s;      # 1 saat
      proxy_send_timeout 3600s;
  }
```

---

## Production problems

```
❌ Problem 1: File descriptor limit
   Hər WebSocket = 1 open socket = 1 FD
   Default limit 1024 — 10k concurrent üçün çox az
   
   Həll:
   ulimit -n 100000
   # /etc/security/limits.conf
   # reverb soft nofile 100000
   # reverb hard nofile 100000

❌ Problem 2: Memory leak
   Hər connection memory saxlayır. 10k connection × 10 KB = 100 MB
   Stale connection-lar detect olunmur → ping/pong timeout qurulmalıdır.

❌ Problem 3: CPU bottleneck on broadcast
   10k user-ə broadcast → 10k socket.write() çağırışı
   Həll: uWebSockets (Soketi), BSD kqueue / epoll istifadə et

❌ Problem 4: Client reconnect storm
   Server restart → 10k client eyni anda reconnect → thundering herd
   Həll: Exponential backoff client tərəfdə
   
   new Echo({
       // ...
       reconnectAfterMs: (attempts) => Math.min(1000 * 2 ** attempts, 30000)
   })

❌ Problem 5: Auth expire
   Long-lived connection — JWT bitir, socket bağlanmır
   Həll: server-side JWT check hər N dəqiqə + auto-disconnect

❌ Problem 6: Mobile battery
   WS connection + keep-alive ping → battery drain
   Həll: Push notification (FCM/APNS) fallback
```

---

## İntervyu Sualları

- WebSocket ilə HTTP long-polling arasındakı fərq nədir?
- Server-Sent Events (SSE) nə vaxt WebSocket-dən üstündür?
- WebSocket upgrade handshake-də hansı başlıqlar mübadilə olunur?
- Sticky session WebSocket-də niyə lazımdır?
- Reverb və Soketi arasında seçim edərkən nələri nəzərə alırsınız?
- Laravel-də Private və Presence channel fərqi nədir?
- WebSocket horizontal scaling-də Redis-in rolu nədir?
- Ping/Pong frame nəyə xidmət edir? Timeout nə qədər olmalıdır?
- 50k concurrent WebSocket connection üçün hansı resource-lar lazımdır?
- Reconnect storm nə deməkdir? Necə qarşısını alırsınız?
- Mobile app-də WebSocket istifadəsi niyə problemlidir?
- Broadcasting event-lərini niyə queue üzərindən göndərmək lazımdır?
