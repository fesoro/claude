# WebSockets (Senior ⭐⭐⭐)

## İcmal
WebSocket — client ilə server arasında full-duplex, persistent communication channel açan protokoldur. HTTP-nin request-response modelindən fərqli olaraq, WebSocket açıq qaldıqdan sonra hər iki tərəf istənilən vaxt data göndərə bilir. Chat, real-time notifications, live dashboards, collaborative editing, online gaming — bu use-case-lərin hamısı WebSocket əsasında qurulur.

## Niyə Vacibdir
Real-time feature-lər artıq demək olar ki, hər modern aplikasiyada var. Interviewer WebSocket mövzusunu verəndə əslində soruşur: "Stateful connection-ları horizontal scaling-də necə idarə edərsiniz? Load balancer arxasında sticky session lazımdırmı? 100,000 concurrent connection-ı necə handle edərsiniz? Server restart olduqda client-lər nə edir?"

## Əsas Anlayışlar

- **WebSocket Handshake:** HTTP `Upgrade` request ilə başlayır. Client `Connection: Upgrade` + `Upgrade: websocket` + `Sec-WebSocket-Key` göndərir. Server 101 Switching Protocols ilə cavab verir. Artıq HTTP deyil, WS protokolu
- **Full-duplex:** Hər iki tərəf eyni anda data göndərə bilər. HTTP-də yalnız client sorğu göndərir, server cavablaşır
- **Persistent Connection:** TCP connection açıq qalır, hər mesaj üçün yeni handshake yoxdur. HTTP polling-in overhead-i yoxdur
- **ws:// vs wss://** `wss` (WebSocket Secure) TLS üzərindən işləyir. Production-da mütləq `wss` istifadə edilməlidir — plain `ws` MITM attack-a açıqdır
- **WebSocket Frame:** Data binary frame-lər şəklindədir. Növlər: text (UTF-8), binary, ping, pong, close. Minimum frame overhead: 2 byte (HTTP header-lardan çox kiçik)
- **Ping/Pong Heartbeat:** Bağlantının canlı olduğunu yoxlamaq üçün. Server `ping` göndərir, client `pong` ilə cavablaşır. Timeout olsa connection drop sayılır
- **Stateful Connection:** HTTP stateless-dir — istənilən server sorğunu işləyə bilər. WebSocket stateful-dur — client-in bağlı olduğu server-ə sonrakı mesajlar da getməlidir. Bu horizontal scaling-i çətinləşdirir
- **Sticky Sessions (Session Affinity):** Load balancer client-i həmişə eyni server-ə yönləndirir (IP hash, cookie). WebSocket scaling üçün ən sadə həll amma server-lər arasında mesaj paylaşımı lazımdır
- **Redis Pub/Sub Broadcasting:** Hər server bütün channel-lara subscribe olur. Server 1-dəki client mesaj göndərir → Server 1 Redis-ə publish edir → Bütün serverlər alır → Server 2 öz client-inə çatdırır
- **Connection Drop + Auto-reconnect:** Network geçici kəsildikdə, server restart olduqda WS connection düşür. Client-in exponential backoff ilə auto-reconnect logic-i mütləq olmalıdır
- **Broadcasting:** N client-ə eyni mesaj göndərmək. Naive loop O(N) — server-ə yük. Redis Pub/Sub ilə distributed broadcasting. "Fan-out problem"
- **Rooms / Channels / Namespaces:** Müəyyən group-a mesaj göndərmək. Socket.IO rooms, Laravel Echo channels. Bir user bir neçə room-da ola bilər
- **Authentication:** WS handshake zamanı custom HTTP header göndərilə bilməz (browser WebSocket API məhdudiyyəti). Həll: URL query param (`?token=...`) ya da WS-dən sonra ilk mesajda token göndər
- **Backpressure:** Client mesajları göndərdiyi sürətlə emal edilə bilmirsə, queue dolur. Server slow consumer-ı disconnect etməlidir ya da throttle tətbiq etməlidir
- **File Descriptor Limit:** Bir Linux server-də max open file descriptor ≈ 65535 (default). Hər WebSocket bir fd istifadə edir. `ulimit -n` artırmaq lazımdır. Praktik: bir server prosesi 10K-50K concurrent WS saxlaya bilər
- **Socket.IO vs Raw WebSocket:** Socket.IO: auto-reconnect, rooms, namespace, polling fallback. Raw WS: daha az overhead, daha çevikdir. Laravel Reverb raw WS-dir

## Praktik Baxış

**Interview-da yanaşma:**
WebSocket-i izah edərkən sadəcə texniki mexanizm yox, scaling challenge-larına fokuslanın: "WebSocket stateful-dur — bu horizontal scaling-i çətinləşdirir. Redis Pub/Sub ilə həll edərdim."

**Follow-up suallar:**
- "10 million concurrent WebSocket user-i necə scale edərdiniz?" — Horizontal scaling + Redis Pub/Sub + regional deployment + connection multiplexing
- "WebSocket vs SSE — nə zaman hansını seçərdiniz?" — SSE: server→client only, simpler, HTTP/2 ilə native multiplexing. WebSocket: bidirectional lazım olduqda
- "WebSocket authentication necə işləyir?" — HTTP cookie (wss ilə göndərilir), ya da ilk mesajda JWT token
- "Load balancer WebSocket-i necə handle etməlidir?" — Layer 4 pass-through ya da Layer 7 WebSocket-aware (sticky session ilə)
- "Laravel Reverb nədir?" — PHP-da yazılmış WebSocket server, Laravel Echo ilə inteqrasiya

**Ümumi səhvlər:**
- WebSocket-i hər real-time use-case üçün tövsiyə etmək — SSE server→client yönlü bir çox halda yetərlidir, sadədir
- Scaling challenge-ını qeyd etməmək
- Client-side reconnect logic-ini unutmaq
- Production-da `ws://` istifadə etmək (şifrəsiz)
- Authentication-ı unutmaq — handshake-dən sonra kim olduğunu bilmək lazımdır

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Redis Pub/Sub broadcasting arxitekturasını izah etmək
- Backpressure management-i bilmək
- "SSE 1-way communication üçün daha sadədir, WebSocket yalnız bidirectional lazım olduqda" demək
- File descriptor limit-ini bilmək

## Nümunələr

### Tipik Interview Sualı
"Design a real-time chat system that supports 1 million concurrent users. How would WebSockets fit into this architecture? What are the scaling challenges?"

### Güclü Cavab
1 million concurrent user üçün WebSocket sistemi dizayn edərkən horizontal scaling məcburidir.

**Arxitektura:**
```
Clients → CDN/Load Balancer (sticky session) → WS Server Fleet (20-50 node)
                                                         ↕ Redis Pub/Sub
                                                     PostgreSQL (persistence)
```

**Sticky session:** Load balancer cookie-based ya da IP hash ilə hər client-i həmişə eyni WS server-ə yönləndirir. Çünki WebSocket stateful-dur.

**Mesaj akışı:**
1. User A (server-1-dədir) User B-yə (server-2-dədir) mesaj göndərir
2. Server-1 mesajı Redis `channel:chat-room-5`-ə publish edir
3. Server-2 həmin channel-a subscribe olduğu üçün mesajı alır
4. Server-2 User B-yə WebSocket ilə çatdırır

**Persistence:** Redis yalnız delivery üçün (volatile). Daimi saxlama PostgreSQL-ə/Cassandra-ya ayrı yazılır.

**Scale hesablaması:**
- Bir server: 30K-50K concurrent WS connection (fd limit + memory)
- 1M user ÷ 40K/server = 25 server
- Redis Pub/Sub: 1M subscriber, millions msg/sec

### Kod Nümunəsi
```php
// Laravel Reverb + Echo — WebSocket server
// config/broadcasting.php → 'reverb' driver

// Event definition — broadcast olunan event
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly int     $roomId
    ) {}

    // Hansı channel-lara broadcast olunur
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat-room.{$this->roomId}"),
        ];
    }

    // Client-ə göndərilən data
    public function broadcastWith(): array
    {
        return [
            'id'        => $this->message->id,
            'content'   => $this->message->content,
            'user'      => [
                'id'   => $this->message->user->id,
                'name' => $this->message->user->name,
            ],
            'sent_at'   => $this->message->created_at->toISOString(),
        ];
    }

    // Sender-ə göndərməyi atla
    public function broadcastExcept(): array
    {
        return [request()->socket_id];
    }
}

// Controller — mesaj göndər
class MessageController extends Controller
{
    public function store(Request $request, int $roomId): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:5000']);

        $message = Message::create([
            'room_id'    => $roomId,
            'user_id'    => auth()->id(),
            'content'    => $request->content,
        ]);

        // Bu event bütün WS server-lərə Redis Pub/Sub ilə çatır
        broadcast(new MessageSent($message, $roomId))->toOthers();

        return response()->json($message, 201);
    }
}

// Authorization — Private channel üçün
// routes/channels.php
Broadcast::channel('chat-room.{roomId}', function (User $user, int $roomId) {
    return ChatRoom::where('id', $roomId)
        ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
        ->exists();
});
```

```javascript
// Client-side — Robust auto-reconnect ilə WebSocket
class ReliableWebSocket extends EventEmitter {
    constructor(url, options = {}) {
        super();
        this.url           = url;
        this.options       = options;
        this.reconnectDelay = options.initialDelay ?? 1000;
        this.maxDelay      = options.maxDelay      ?? 30000;
        this.maxRetries    = options.maxRetries     ?? Infinity;
        this.retryCount    = 0;
        this.messageQueue  = [];  // Reconnection zamanı mesajları queue-ya al
        this.authenticated = false;
        this.connect();
    }

    connect() {
        this.ws = new WebSocket(this.url);

        this.ws.onopen = () => {
            console.log('WebSocket connected');
            this.reconnectDelay = 1000;  // Delay-i reset et
            this.retryCount = 0;
            this.authenticate();         // İlk mesaj: auth token
        };

        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);

            if (data.type === 'auth_success') {
                this.authenticated = true;
                // Queue-dakı mesajları göndər
                this.messageQueue.forEach(msg => this.sendRaw(msg));
                this.messageQueue = [];
            } else {
                this.emit('message', data);
            }
        };

        this.ws.onclose = (event) => {
            this.authenticated = false;
            if (event.wasClean) {
                console.log('WebSocket closed cleanly');
                return;
            }
            this.scheduleReconnect();
        };

        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
    }

    authenticate() {
        // Browser WebSocket API custom header dəstəkləmir
        // Token-i ilk mesajda göndər
        this.sendRaw({
            type:  'auth',
            token: localStorage.getItem('auth_token')
        });
    }

    scheduleReconnect() {
        if (this.retryCount >= this.maxRetries) {
            this.emit('max_retries_exceeded');
            return;
        }
        const delay = Math.min(this.reconnectDelay, this.maxDelay);
        console.log(`Reconnecting in ${delay}ms... (attempt ${this.retryCount + 1})`);

        setTimeout(() => {
            this.retryCount++;
            this.connect();
        }, delay);

        // Exponential backoff: 1s, 2s, 4s, 8s, ... max 30s
        this.reconnectDelay = Math.min(this.reconnectDelay * 2, this.maxDelay);
    }

    send(data) {
        if (this.ws?.readyState === WebSocket.OPEN && this.authenticated) {
            this.sendRaw(data);
        } else {
            // Reconnect zamanı queue-ya əlavə et
            this.messageQueue.push(data);
        }
    }

    sendRaw(data) {
        this.ws.send(JSON.stringify(data));
    }

    disconnect() {
        this.maxRetries = 0;  // Reconnect-i dayandır
        this.ws?.close(1000, 'Client disconnect');
    }
}

// Laravel Echo ilə WebSocket (Reverb backend)
import Echo from 'laravel-echo';

window.Echo = new Echo({
    broadcaster:  'reverb',
    key:          import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:       import.meta.env.VITE_REVERB_HOST,
    wsPort:       import.meta.env.VITE_REVERB_PORT,
    wssPort:      import.meta.env.VITE_REVERB_PORT,
    forceTLS:     true,
    enabledTransports: ['ws', 'wss'],
});

// Private channel-a subscribe ol
Echo.private(`chat-room.${roomId}`)
    .listen('MessageSent', (e) => {
        addMessageToUI(e.message);
    })
    .listenForWhisper('typing', (e) => {
        showTypingIndicator(e.user);
    });

// Typing indicator — serverə GET etmədən P2P kimi
Echo.private(`chat-room.${roomId}`)
    .whisper('typing', { user: currentUser });
```

```
WebSocket Scaling Architecture:

                    ┌───────────────────────────┐
Clients ──── LB ───→│  WS Server 1              │
         (sticky)   │  conn: Ali, Fidan          │──→ Redis
             │      │  channel: room.1, room.2   │    Pub/Sub
             │      └───────────────────────────┘    (fan-out)
             │                                        ↕
             │      ┌───────────────────────────┐    ↕
             └─────→│  WS Server 2              │←──→│
                    │  conn: Murad, Leyla        │
                    │  channel: room.1, room.3   │
                    └───────────────────────────┘
                                │
                           PostgreSQL
                        (message persistence)

Mesaj axışı: Ali (Server-1) → room.1 → Murad (Server-2)
  1. Ali mesaj göndərir → Server-1 alır
  2. Server-1 Redis: PUBLISH channel:room.1 {message}
  3. Server-2 (room.1-a subscribe) mesajı alır
  4. Server-2 Murad-a WebSocket frame göndərir
```

```yaml
# Docker Compose — Laravel Reverb + Redis
services:
  reverb:
    image: php:8.3-cli
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    environment:
      REVERB_APP_ID:     myapp
      REVERB_APP_KEY:    secret-key
      REVERB_APP_SECRET: secret-secret
      BROADCAST_DRIVER:  reverb
      REDIS_HOST:        redis
    depends_on:
      - redis
    ports:
      - "8080:8080"

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes

  nginx:
    image: nginx:alpine
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    ports:
      - "443:443"
```

```nginx
# Nginx WebSocket proxy konfiqurasiyası
# WebSocket upgrade + proxy pass
upstream reverb {
    ip_hash;  # Sticky session — eyni client eyni WS server-ə
    server reverb1:8080;
    server reverb2:8080;
    server reverb3:8080;
    keepalive 100;  # Upstream connection pool
}

server {
    listen 443 ssl http2;
    server_name ws.example.com;

    ssl_certificate     /etc/ssl/certs/ws.crt;
    ssl_certificate_key /etc/ssl/private/ws.key;

    location /app/ {
        proxy_pass http://reverb;
        proxy_http_version 1.1;

        # WebSocket upgrade headers — vacibdir!
        proxy_set_header Upgrade    $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host       $host;
        proxy_set_header X-Real-IP  $remote_addr;

        # Long-lived connection üçün timeout-ları artır
        proxy_read_timeout    86400s;  # 24 saat
        proxy_send_timeout    86400s;
        proxy_connect_timeout 60s;
    }
}
```

```php
// WebSocket connection monitoring
class WebSocketMonitor
{
    public function getMetrics(): array
    {
        return [
            'active_connections' => Redis::zcard('ws:active-connections'),
            'connections_per_server' => Redis::hgetall('ws:server-connections'),
            'messages_per_minute'    => Redis::get('ws:metrics:messages-rate'),
            'rooms'                  => [
                'active' => Redis::scard('ws:active-rooms'),
                'largest' => $this->getLargestRoom(),
            ],
        ];
    }

    // Server restart-dan əvvəl graceful shutdown
    public function gracefulShutdown(): void
    {
        $connections = $this->getActiveConnections();

        foreach ($connections as $conn) {
            // Client-ə "server restarting" mesajı göndər
            $conn->send(json_encode([
                'type'   => 'server_restart',
                'delay'  => 5,  // 5 saniyə ərzində reconnect et
                'reason' => 'Scheduled maintenance'
            ]));
        }

        sleep(5);  // Client-lərin reconnect etməsi üçün gözlə
        // Yeni connection qəbul etməyi dayandır
    }
}
```

### İkinci Nümunə — SSE vs WebSocket seçimi

```
Nə vaxt SSE, nə vaxt WebSocket?

SSE (Server-Sent Events) seçin əgər:
  - Server → Client yalnız bir yönlü lazımdırsa
  - Notifications, live updates, progress bar
  - HTTP/2 ilə native multiplexing istifadə etmək istəyirsinizsə
  - Simpler implementation lazımdırsa
  - CDN/reverse proxy dəstəyi vacibdirsə (SSE HTTP-dir)

SSE nümunəsi (Laravel):
  Route::get('/notifications/stream', function () {
      return response()->stream(function () {
          while (true) {
              $notifications = auth()->user()
                  ->unreadNotifications()
                  ->get();

              foreach ($notifications as $n) {
                  echo "data: " . json_encode($n) . "\n\n";
                  ob_flush(); flush();
              }
              sleep(2);
          }
      }, 200, [
          'Content-Type'  => 'text/event-stream',
          'Cache-Control' => 'no-cache',
          'X-Accel-Buffering' => 'no', // Nginx buffering disable
      ]);
  })->middleware('auth');

WebSocket seçin əgər:
  - Bidirectional communication lazımdırsa
  - Chat, collaborative editing, real-time gaming
  - Client-dən server-ə yüksək frekvensli mesajlar
  - Custom protocol, binary data transfer

Qiymət müqayisəsi (100K concurrent user):
  SSE:       Load balancer friendly, HTTP-based, CDN arkasında işləyir
  WebSocket: Sticky session + Redis Pub/Sub lazım, daha kompleks ops
```

## Praktik Tapşırıqlar

- Laravel Reverb ilə sadə chat app qurun: public room, private messages, typing indicator
- Redis Pub/Sub ilə multi-server WebSocket broadcasting simulate edin: 2 ayrı Reverb instance başladın, aralarında mesaj keçdiyini görün
- Client-side auto-reconnect + message queue implement edin: server 5 saniyə kapalı qalsın, mesajlar queue-da saxlanılsın, reconnect-dən sonra göndərilsin
- `ab` ya da `wrk` ilə WebSocket connection load test: 10K concurrent connection serverin memory + CPU istifadəsini ölçün
- WebSocket authentication: JWT token ilk mesajda göndərilən mexanizmi implement edin, token expire olduqda connection-ı bağla
- SSE vs WebSocket: notifications üçün hər ikisini implement edin, complexity və resource usage-ı müqayisə edin

## Əlaqəli Mövzular
- [Long Polling vs SSE vs WebSocket](07-polling-sse-websocket.md) — Real-time pattern müqayisəsi
- [TCP vs UDP](01-tcp-vs-udp.md) — WebSocket TCP üzərindən işləyir
- [TLS/SSL Handshake](03-tls-ssl-handshake.md) — wss:// TLS üzərindən WebSocket
- [REST vs GraphQL vs gRPC](05-rest-graphql-grpc.md) — GraphQL Subscription vs WebSocket
