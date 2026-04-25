# Long Polling (Middle)

## İcmal

Long polling server-dən real-time data almaq üçün istifadə olunan texnikadır. Client request göndərir, server yeni data olana qədər cavab göndərmir (bağlantını "açıq saxlayır"). Data hazır olanda server cavab göndərir, client dərhal yeni request göndərir. Bu "yalançı real-time" yaradır.

```
Normal Polling (Short Polling):
  Client --GET--> Server (yeni data yox) --> 200 []     (1 san)
  Client --GET--> Server (yeni data yox) --> 200 []     (2 san)
  Client --GET--> Server (YENİ DATA!)    --> 200 [data] (3 san)
  Çoxlu boşuna request, bandwidth israf

Long Polling:
  Client --GET--> Server ... gözləyir ... gözləyir ...
                  (30 saniyə gözlədi, data gəldi!)
                  Server --> 200 [data]
  Client --GET--> Server ... gözləyir ...
  Daha az request, amma hər connection bir thread tutur
```

## Niyə Vacibdir

WebSocket və SSE mövcud olmayan mühitlərdə (köhnə browserlar, məhdud infrastruktur, proxy məhdudiyyətləri) long polling real-time-a yaxın davranış əldə etməyin praktiki yoludur. Əlavə protokol tələb etmədən standart HTTP ilə işlədiyindən universaldır. Bugün bu texnika əsasən legacy sistemlərə dəstək üçün və ya WebSocket/SSE-yə keçid hazırlığı kimi istifadə olunur.

## Əsas Anlayışlar

### Long Polling Flow

```
Client                              Server
  |                                    |
  |--- GET /poll?since=0 ------------>|
  |                                    |  (data yoxdur, gözləyir...)
  |                                    |  ... 5 saniyə ...
  |                                    |  ... 10 saniyə ...
  |                                    |  (YENİ DATA GƏLDİ!)
  |<-- 200 [{id:1, msg:"Salam"}] -----|
  |                                    |
  |--- GET /poll?since=1 ------------>|  (dərhal yeni request)
  |                                    |  (data yoxdur, gözləyir...)
  |                                    |  ... 25 saniyə ...
  |                                    |  (TIMEOUT - data gəlmədi)
  |<-- 204 No Content ----------------|
  |                                    |
  |--- GET /poll?since=1 ------------>|  (təkrar request)
  |                                    |
```

### Timeout Handling

```
Niyə timeout lazımdır?
1. Server resource-larını sərbəst buraxmaq
2. Proxy/firewall connection timeout-dan qorunmaq
3. Dead client-ləri detect etmək

Tipik timeout: 30-60 saniyə

Client timeout:
  - Server cavab vermirsə, client yeni request göndərir
  - Exponential backoff: error zamanı gözləmə artır

Server timeout:
  - Max hold time aşıldıqda boş cavab göndər
  - 204 No Content və ya 200 {events: []}
```

### Long Polling vs Short Polling vs WebSocket vs SSE

```
+-------------------+----------+-----------+-----------+-------+
| Feature           | Short    | Long      | WebSocket | SSE   |
+-------------------+----------+-----------+-----------+-------+
| Latency           | Yüksək   | Aşağı     | Ən aşağı  | Aşağı |
| Server load       | Yüksək   | Orta      | Aşağı     | Aşağı |
| Complexity        | Ən aşağı | Aşağı     | Yüksək    | Orta  |
| Bidirectional     | Bəli     | Bəli*     | Bəli      | Xeyr  |
| Connection/request| Çoxlu    | Orta      | 1         | 1     |
| Compatibility     | Həmişə   | Həmişə    | Demək olar| Geniş |
| Real-time         | Yalançı  | Yalançı   | Həqiqi    | Həqiqi|
+-------------------+----------+-----------+-----------+-------+
* Bidirectional amma hər message üçün yeni request
```

### Etag / Since Pattern

```
# İlk request
GET /api/poll/messages?room=5
Response: {
  "messages": [...],
  "last_id": 42,
  "etag": "abc123"
}

# Sonrakı request (yalnız yeni mesajları istə)
GET /api/poll/messages?room=5&since_id=42
If-None-Match: abc123

# Data yoxdursa
304 Not Modified (və ya 204 No Content + timeout)

# Yeni data varsa
200 OK
{
  "messages": [new items...],
  "last_id": 48,
  "etag": "def456"
}
```

### Connection Management

```
Problem: Hər long poll bir server thread/process tutur

100 istifadəçi = 100 daimi connection = 100 PHP process

Həll yolları:
1. Async PHP (Swoole, ReactPHP) - event loop ilə minlərlə connection
2. Database polling - sleep + query loop
3. Redis Pub/Sub - blocking subscribe
4. Ayrı long-poll service (Node.js, Go)
```

## Praktik Baxış

**Nə vaxt long polling istifadə etmək lazımdır:**
- WebSocket/SSE istifadə etmək mümkün olmayanda
- Legacy browser dəstəyi tələb olunanda
- Nadir update olan data üçün (az tezlikli notifications)
- Mövcud HTTP infrastrukturundan çıxmaq istəmədikdə

**Nə vaxt WebSocket/SSE seçmək lazımdır:**
- Tezlikli real-time update lazımdır
- Çoxlu eyni vaxtlı istifadəçi var
- Server resource optimallaşdırması vacibdir

**Trade-off-lar:**
- Hər gözləyən connection bir server thread tutur
- "Əsl" real-time deyil — mesaj + yeni request latency-si var
- PHP-də 1000 long-poll = 1000 PHP process — kritik resource problemi

**Anti-pattern-lər:**
- Long polling-i əsas real-time mexanizm kimi istifadə etmək (müvəqqəti həll olmalıdır)
- `session_write_close()` çağırmamaq — digər request-lər bloklanır
- Timeout olmadan işlətmək — zombie connection-lar server-i tıxayır
- DB polling-i sleep(1) ilə etmək — Redis BLPOP daha effektivdir

## Nümunələr

### Ümumi Nümunə

Long polling axını:

```
Client            Server
  |                  |
  |--- GET /poll --> | (data yoxdur)
  |                  | ... 20 saniyə gözləyir ...
  |                  | (Redis/DB-dən yeni data gəldi)
  |<-- 200 [data] -- |
  |                  |
  |--- GET /poll --> | (dərhal yeni request — növbəti data üçün)
  |                  | ...
```

### Kod Nümunəsi

**Basic Long Polling Controller:**

```php
namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongPollController extends Controller
{
    private const TIMEOUT_SECONDS = 30;
    private const POLL_INTERVAL = 1; // Hər 1 saniyədə DB yoxla

    /**
     * Long polling endpoint
     */
    public function poll(Request $request): JsonResponse
    {
        $roomId = $request->input('room_id');
        $sinceId = $request->input('since_id', 0);

        // PHP execution limit-i uzat
        set_time_limit(self::TIMEOUT_SECONDS + 5);

        // Session lock-u aç (başqa request-lər block olmasın)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $startTime = time();

        while (true) {
            // Yeni mesajları yoxla
            $messages = Message::where('room_id', $roomId)
                ->where('id', '>', $sinceId)
                ->with('user:id,name,avatar')
                ->orderBy('id')
                ->limit(50)
                ->get();

            // Yeni mesaj varsa - cavab göndər
            if ($messages->isNotEmpty()) {
                return response()->json([
                    'messages' => $messages,
                    'last_id' => $messages->last()->id,
                    'has_more' => $messages->count() === 50,
                ]);
            }

            // Timeout yoxla
            if ((time() - $startTime) >= self::TIMEOUT_SECONDS) {
                return response()->json([
                    'messages' => [],
                    'last_id' => $sinceId,
                    'timeout' => true,
                ], 200);
            }

            // Connection yoxla
            if (connection_aborted()) {
                break;
            }

            // 1 saniyə gözlə və təkrar yoxla
            sleep(self::POLL_INTERVAL);
        }

        return response()->json(['messages' => []], 200);
    }
}
```

**Redis-based Long Polling (Daha Effektiv):**

```php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RedisLongPollController extends Controller
{
    /**
     * Redis BLPOP ilə long polling (DB polling-dən daha effektiv)
     */
    public function poll(Request $request): JsonResponse
    {
        $channel = "room:{$request->input('room_id')}:messages";
        $timeout = 30;

        set_time_limit($timeout + 5);

        // Redis BLPOP - data olana qədər block olur (max $timeout saniyə)
        $result = Redis::blpop([$channel], $timeout);

        if ($result) {
            [$key, $data] = $result;
            $message = json_decode($data, true);

            // Queue-da daha çox mesaj var mı yoxla
            $additional = [];
            while ($extra = Redis::lpop($channel)) {
                $additional[] = json_decode($extra, true);
                if (count($additional) >= 49) break;
            }

            return response()->json([
                'messages' => [$message, ...$additional],
            ]);
        }

        // Timeout - data gəlmədi
        return response()->json([
            'messages' => [],
            'timeout' => true,
        ]);
    }

    /**
     * Mesaj göndərmək (başqa endpoint və ya job-dan)
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => 'required|integer',
            'body' => 'required|string|max:1000',
        ]);

        $message = [
            'id' => uniqid(),
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'body' => $data['body'],
            'created_at' => now()->toISOString(),
        ];

        // Redis-ə push et (gözləyən long-poll request-lər bunu alacaq)
        Redis::rpush("room:{$data['room_id']}:messages", json_encode($message));

        // Database-ə də yazılmalı (persistence üçün)
        \App\Models\Message::create([
            'room_id' => $data['room_id'],
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json($message, 201);
    }
}
```

**Notification Long Polling:**

```php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPollController extends Controller
{
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $sinceId = $request->input('since_id', 0);
        $timeout = 25;

        set_time_limit($timeout + 5);
        $start = time();

        while ((time() - $start) < $timeout) {
            $notifications = $user->unreadNotifications()
                ->when($sinceId, fn($q) => $q->where('id', '>', $sinceId))
                ->orderBy('id')
                ->limit(20)
                ->get();

            if ($notifications->isNotEmpty()) {
                return response()->json([
                    'notifications' => $notifications->map(fn($n) => [
                        'id' => $n->id,
                        'type' => class_basename($n->type),
                        'data' => $n->data,
                        'created_at' => $n->created_at->toISOString(),
                    ]),
                    'last_id' => $notifications->last()->id,
                    'unread_count' => $user->unreadNotifications()->count(),
                ]);
            }

            if (connection_aborted()) break;
            usleep(500000); // 0.5 saniyə
        }

        return response()->json([
            'notifications' => [],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
}
```

**JavaScript Client:**

```javascript
class LongPollClient {
    constructor(url, options = {}) {
        this.url = url;
        this.lastId = options.sinceId || 0;
        this.running = false;
        this.retryDelay = 1000;
        this.maxRetryDelay = 30000;
        this.onMessage = options.onMessage || (() => {});
        this.onError = options.onError || (() => {});
    }

    start() {
        this.running = true;
        this._poll();
    }

    stop() {
        this.running = false;
        if (this.controller) {
            this.controller.abort();
        }
    }

    async _poll() {
        while (this.running) {
            try {
                this.controller = new AbortController();

                const response = await fetch(
                    `${this.url}?since_id=${this.lastId}`,
                    {
                        signal: this.controller.signal,
                        headers: {
                            'Authorization': `Bearer ${getToken()}`,
                            'Accept': 'application/json',
                        },
                    }
                );

                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const data = await response.json();

                if (data.messages?.length > 0) {
                    this.lastId = data.last_id;
                    data.messages.forEach(msg => this.onMessage(msg));
                }

                // Uğurlu oldu - retry delay reset
                this.retryDelay = 1000;

            } catch (error) {
                if (error.name === 'AbortError') continue;

                this.onError(error);

                // Exponential backoff
                await new Promise(r => setTimeout(r, this.retryDelay));
                this.retryDelay = Math.min(
                    this.retryDelay * 2,
                    this.maxRetryDelay
                );
            }
        }
    }
}

// İstifadə
const poller = new LongPollClient('/api/poll', {
    sinceId: 0,
    onMessage: (msg) => {
        console.log('New message:', msg);
        appendToChat(msg);
    },
    onError: (err) => console.error('Poll error:', err),
});

poller.start();
// poller.stop();  // dayandırmaq üçün
```

**Routes:**

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/poll/messages', [LongPollController::class, 'poll']);
    Route::get('/poll/notifications', [NotificationPollController::class, 'poll']);
    Route::post('/messages', [RedisLongPollController::class, 'sendMessage']);
});
```

## Praktik Tapşırıqlar

1. **DB-based long poll:** `LongPollController`-i implement edin. `since_id` parametrini qəbul etsin, 30 saniyə timeout ilə yeni mesaj gözləsin. Postman ilə test edin.

2. **Redis BLPOP versiyası:** `RedisLongPollController`-i implement edin. `sendMessage` endpoint-i ilə mesaj göndərin, `poll` endpoint-inin onu necə aldığını izləyin.

3. **JavaScript client:** `LongPollClient` class-ını yazın. Exponential backoff implement edin. Network kesildikdə avtomatik reconnect olmasını test edin.

4. **Session lock:** `session_write_close()` olmadan və olduqda paralel request-lərin davranışını müqayisə edin. Bloklanma effektini izləyin.

5. **Migration plan:** Mövcud DB polling-li long poll endpoint-ini SSE-yə çevirin. Hər iki versiyaı paralel işlədin — client-in SSE-ni dəstəklədiyini yoxlayın, dəstəkləmirsə long poll-a fall back edin.

6. **Load test:** 100 paralel long poll connection aç. PHP-FPM worker sayını izləyin. Server yükünü ölçün.

## Əlaqəli Mövzular

- [WebSocket](11-websocket.md)
- [SSE - Server-Sent Events](12-sse.md)
- [HTTP Protocol](05-http-protocol.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [Network Timeouts](42-network-timeouts.md)
