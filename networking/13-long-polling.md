# Long Polling

## Nədir? (What is it?)

Long polling server-den real-time data almaq ucun istifade olunan texnikadir. Client request gonderir, server yeni data olana qeder cavab gondermir (baglantini "aciq saxlayir"). Data hazir olanda server cavab gonderir, client derhâl yeni request gonderir. Bu "yanlis real-time" yaradir.

```
Normal Polling (Short Polling):
  Client --GET--> Server (yeni data yox) --> 200 []     (1 san)
  Client --GET--> Server (yeni data yox) --> 200 []     (2 san)
  Client --GET--> Server (YENİ DATA!)    --> 200 [data] (3 san)
  Coxlu bosuna request, bandwidth israf

Long Polling:
  Client --GET--> Server ... gozleyir ... gozleyir ...
                  (30 saniye gozledi, data geldi!)
                  Server --> 200 [data]
  Client --GET--> Server ... gozleyir ...
  Daha az request, amma her connection bir thread tutur
```

## Necə İşləyir? (How does it work?)

### Long Polling Flow

```
Client                              Server
  |                                    |
  |--- GET /poll?since=0 ------------>|
  |                                    |  (data yoxdur, gozleyir...)
  |                                    |  ... 5 saniye ...
  |                                    |  ... 10 saniye ...
  |                                    |  (YENİ DATA GELDI!)
  |<-- 200 [{id:1, msg:"Salam"}] -----|
  |                                    |
  |--- GET /poll?since=1 ------------>|  (derhâl yeni request)
  |                                    |  (data yoxdur, gozleyir...)
  |                                    |  ... 25 saniye ...
  |                                    |  (TIMEOUT - data gelmedi)
  |<-- 204 No Content ----------------|
  |                                    |
  |--- GET /poll?since=1 ------------>|  (tekrar request)
  |                                    |
```

### Timeout Handling

```
Niye timeout lazimdir?
1. Server resource-larini serbest buraxmaq
2. Proxy/firewall connection timeout-dan qorunmaq
3. Dead client-leri detect etmek

Tipik timeout: 30-60 saniye

Client timeout:
  - Server cavab vermirsə, client yeni request gonderir
  - Exponential backoff: error zamani gozleme artir

Server timeout:
  - Max hold time asildiqda bosh cavab gonder
  - 204 No Content ve ya 200 {events: []}
```

### Long Polling vs Short Polling vs WebSocket vs SSE

```
+-------------------+----------+-----------+-----------+-------+
| Feature           | Short    | Long      | WebSocket | SSE   |
+-------------------+----------+-----------+-----------+-------+
| Latency           | Yuksek   | Asagi     | En asagi  | Asagi |
| Server load       | Yuksek   | Orta      | Asagi     | Asagi |
| Complexity        | En asagi | Asagi     | Yuksek    | Orta  |
| Bidirectional     | Beli     | Beli*     | Beli      | Xeyr  |
| Connection/request| Coxlu    | Orta      | 1         | 1     |
| Compatibility     | Hemise   | Hemise    | Demek olar| Genis |
| Real-time         | Yalanchi | Yalanchi  | Heqiqi    | Heqiqi|
+-------------------+----------+-----------+-----------+-------+
* Bidirectional amma her message ucun yeni request
```

## Əsas Konseptlər (Key Concepts)

### Etag / Since Pattern

```
# Ilk request
GET /api/poll/messages?room=5
Response: {
  "messages": [...],
  "last_id": 42,
  "etag": "abc123"
}

# Sonraki request (yalniz yeni mesajlari iste)
GET /api/poll/messages?room=5&since_id=42
If-None-Match: abc123

# Data yoxdursa
304 Not Modified (ve ya 204 No Content + timeout)

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
Problem: Her long poll bir server thread/process tutur

100 istifadeci = 100 daimi connection = 100 PHP process

Hell yollari:
1. Async PHP (Swoole, ReactPHP) - event loop ile minlerle connection
2. Database polling - sleep + query loop
3. Redis Pub/Sub - blocking subscribe
4. Ayri long-poll service (Node.js, Go)
```

## PHP/Laravel ilə İstifadə

### Basic Long Polling Controller

```php
namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongPollController extends Controller
{
    private const TIMEOUT_SECONDS = 30;
    private const POLL_INTERVAL = 1; // Her 1 saniyede DB yoxla

    /**
     * Long polling endpoint
     */
    public function poll(Request $request): JsonResponse
    {
        $roomId = $request->input('room_id');
        $sinceId = $request->input('since_id', 0);

        // PHP execution limit-i uzat
        set_time_limit(self::TIMEOUT_SECONDS + 5);

        // Session lock-u ac (basqa request-ler block olmasin)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $startTime = time();

        while (true) {
            // Yeni mesajlari yoxla
            $messages = Message::where('room_id', $roomId)
                ->where('id', '>', $sinceId)
                ->with('user:id,name,avatar')
                ->orderBy('id')
                ->limit(50)
                ->get();

            // Yeni mesaj varsa - cavab gonder
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

            // 1 saniye gozle ve tekrar yoxla
            sleep(self::POLL_INTERVAL);
        }

        return response()->json(['messages' => []], 200);
    }
}
```

### Redis-based Long Polling (Daha Effektiv)

```php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RedisLongPollController extends Controller
{
    /**
     * Redis BLPOP ile long polling (DB polling-den daha effektiv)
     */
    public function poll(Request $request): JsonResponse
    {
        $channel = "room:{$request->input('room_id')}:messages";
        $timeout = 30;

        set_time_limit($timeout + 5);

        // Redis BLPOP - data olana qeder block olur (max $timeout saniye)
        $result = Redis::blpop([$channel], $timeout);

        if ($result) {
            [$key, $data] = $result;
            $message = json_decode($data, true);

            // Queue-da daha cox mesaj var mi yoxla
            $additional = [];
            while ($extra = Redis::lpop($channel)) {
                $additional[] = json_decode($extra, true);
                if (count($additional) >= 49) break;
            }

            return response()->json([
                'messages' => [$message, ...$additional],
            ]);
        }

        // Timeout - data gelmedi
        return response()->json([
            'messages' => [],
            'timeout' => true,
        ]);
    }

    /**
     * Mesaj gondermek (basqa endpoint ve ya job-dan)
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

        // Redis-e push et (bekleyen long-poll request-ler bunu alacaq)
        Redis::rpush("room:{$data['room_id']}:messages", json_encode($message));

        // Database-e de yazilmali (persistence ucun)
        \App\Models\Message::create([
            'room_id' => $data['room_id'],
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json($message, 201);
    }
}
```

### Notification Long Polling

```php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPollController extends Controller
{
    /**
     * Notification long polling
     */
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
            usleep(500000); // 0.5 saniye
        }

        return response()->json([
            'notifications' => [],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
}
```

### JavaScript Client

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

                // Ugurlu oldu - retry delay reset
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

// Istifade
const poller = new LongPollClient('/api/poll', {
    sinceId: 0,
    onMessage: (msg) => {
        console.log('New message:', msg);
        appendToChat(msg);
    },
    onError: (err) => console.error('Poll error:', err),
});

poller.start();
// poller.stop();  // dayandirmaq ucun
```

### Routes

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/poll/messages', [LongPollController::class, 'poll']);
    Route::get('/poll/notifications', [NotificationPollController::class, 'poll']);
    Route::post('/messages', [RedisLongPollController::class, 'sendMessage']);
});
```

## Interview Sualları

### 1. Long polling nedir ve nece isleyir?
**Cavab:** Client request gonderir, server yeni data olana qeder cavab vermir (baglantini saxlayir). Data olanda cavab gonderir, client derhal yeni request gonderir. Bu prosesi tekrarlayaraq "yalanchi real-time" yaradilir.

### 2. Long polling ve short polling arasinda ferq nedir?
**Cavab:** Short polling-de client muntezer interval ile sorgu gonderir (meselen, her 1 saniye), server derhal cavab qaytarir (bosh da ola biler). Long polling-de server cavabi yeni data olana qeder saxlayir. Long polling daha az request edir amma her connection server thread tutur.

### 3. Long polling-in dezavantajlari nelerdir?
**Cavab:** Her gozleyen connection bir server thread/process tutur (resource intensive), real real-time deyil (mesaj + yeni request latency-si var), timeout management lazimdir, scaling cetindir, message ordering problemi ola biler.

### 4. Long polling ne vaxt istifade etmeliyik?
**Cavab:** WebSocket/SSE istifade etmek mumkun olmayanda (kohne browser, mehdud infrastruktur), sade implementation lazim olanda, nadir update olan data ucun (notifications). Modern app-larda WebSocket ve ya SSE tercih edilir.

### 5. PHP-de long polling-in problemi nedir?
**Cavab:** PHP her request ucun ayri process/thread isleyir. 1000 long-poll connection = 1000 PHP process = cox memory/CPU. Hell yolu: Redis BLPOP istifade edin, async PHP (Swoole), ve ya long-poll logic-i Node.js/Go kimi async runtime-a kocurun.

### 6. Timeout niye vacibdir?
**Cavab:** Server resource-larini azad etmek, proxy/firewall idle connection timeout-undan qorunmaq (NAT tipik olaraq 60 saniye), dead client-leri detect etmek ucun. Timeout adeten 25-30 saniye olur.

### 7. Long polling-de message ordering nece saxlanir?
**Cavab:** Her mesaja monotonic ID verin, client son aldiqi ID-ni `since_id` olaraq gondersin. Server yalniz bu ID-den boyuk olan mesajlari qaytarsin. Client terefinede mesajlari ID-ye gore siralyin.

## Best Practices

1. **Timeout teyin edin** - 25-30 saniye (proxy timeout-dan asagi)
2. **Exponential backoff** - Error zamani gozleme muddeti artirlmali
3. **since_id pattern** - Son aldiqi mesajin ID-sini izleyin
4. **Session lock acin** - `session_write_close()` - basqa request-ler block olmasin
5. **Connection limit** - Server basina max long-poll connection
6. **Redis BLPOP** - DB polling yerine Redis blocking operation
7. **Graceful degradation** - Server yuku artanda short polling-e kecin
8. **AbortController** - Client terefde request-i cancel etmek ucun
9. **Health endpoint** - Long-poll endpoint-den ayri health check
10. **WebSocket/SSE-ye upgrade edin** - Long polling muvqqeti hell olmalidir
