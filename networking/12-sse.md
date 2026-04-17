# Server-Sent Events (SSE)

## Nədir? (What is it?)

Server-Sent Events (SSE) serverin client-e HTTP baglantisi uzerinden real-time data push etmesine imkan veren texnologiyadir. WebSocket-den ferqli olaraq, yalniz server-den client-e tek yonlu (unidirectional) data axini var. HTTP uzerinde isleyir, buna gore proxy/firewall problemi yoxdur.

```
HTTP Polling:
  Client --request--> Server  (1 saniye)
  Client --request--> Server  (2 saniye)
  Client --request--> Server  (3 saniye)
  Her defe yeni connection, coxlu overhead

SSE:
  Client --request--> Server
  Client <--- event 1 --- Server
  Client <--- event 2 --- Server
  Client <--- event 3 --- Server
  Tek connection, davamedici stream
```

## Necə İşləyir? (How does it work?)

### SSE Connection Flow

```
1. Client EventSource API ile connect olur:
   const source = new EventSource('/events');

2. Server cavab qaytarir:
   HTTP/1.1 200 OK
   Content-Type: text/event-stream
   Cache-Control: no-cache
   Connection: keep-alive

3. Server event-leri stream edir:
   data: {"message": "Hello"}

   event: notification
   data: {"text": "Yeni mesaj var"}

   data: Bu sadece text data-dir

4. Baglanti qirilsa browser avtomatik reconnect edir
```

### Event Stream Format

```
# Sadece data
data: Hello World

# Multi-line data
data: line 1
data: line 2

# JSON data
data: {"user": "Orkhan", "message": "Salam"}

# Named event
event: notification
data: {"text": "Yeni sifaris!"}

# Event ID (reconnect zamani Last-Event-ID gonderiir)
id: 42
data: {"order": "shipped"}

# Retry interval (milliseconds)
retry: 5000

# Comment (keep-alive ucun istifade olunur)
: this is a comment

# Tam numune (her event bosh setirle ayrilir)
id: 1
event: message
data: {"text": "Salam!"}

id: 2
event: notification
data: {"count": 5}

id: 3
event: heartbeat
data: ping

```

### Auto-Reconnect ve Last-Event-ID

```
Client                              Server
  |                                    |
  |--- GET /events ------------------>|
  |<-- id: 1, data: "event 1" -------|
  |<-- id: 2, data: "event 2" -------|
  |<-- id: 3, data: "event 3" -------|
  |                                    |
  |    ~~~ CONNECTION LOST ~~~         |
  |                                    |
  |--- GET /events ------------------>|  (avtomatik reconnect)
  |    Last-Event-ID: 3               |  (son aldiqi ID-ni gonderir)
  |<-- id: 4, data: "event 4" -------|  (server 3-den sonrakileri gonderir)
  |<-- id: 5, data: "event 5" -------|
```

## Əsas Konseptlər (Key Concepts)

### SSE vs WebSocket vs Long Polling

```
+--------------------+-----------+-----------+-------------+
| Feature            | SSE       | WebSocket | Long Polling|
+--------------------+-----------+-----------+-------------+
| Direction          | Server→C  | Both ↔    | Both (hack) |
| Protocol           | HTTP      | WS        | HTTP        |
| Auto reconnect     | Yes ✓     | No ✗      | Manual      |
| Event ID tracking  | Yes ✓     | No ✗      | No ✗        |
| Binary data        | No ✗      | Yes ✓     | Yes ✓       |
| Max connections     | 6/domain  | Unlimited | 6/domain    |
| Proxy friendly     | Yes ✓     | Sometimes | Yes ✓       |
| Complexity         | Asagi     | Orta      | Asagi       |
| Browser support    | Genis     | Genis     | Genis       |
+--------------------+-----------+-----------+-------------+

SSE ne vaxt istifade edin:
- Server push (notifications, live feed, stock prices)
- Client-den server-e data gondermek lazim olmayanda
- Simple implementation lazim olanda

WebSocket ne vaxt istifade edin:
- Bidirectional lazim olanda (chat, gaming)
- Binary data lazim olanda
- Cox suretli her iki yonlu kommunikasiya
```

### EventSource API (Browser)

```javascript
// Basic usage
const source = new EventSource('/api/events');

// Default 'message' event
source.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Data:', data);
    console.log('Event ID:', event.lastEventId);
};

// Named events
source.addEventListener('notification', (event) => {
    const data = JSON.parse(event.data);
    showNotification(data.text);
});

source.addEventListener('order-update', (event) => {
    const data = JSON.parse(event.data);
    updateOrderStatus(data.orderId, data.status);
});

// Connection events
source.onopen = () => {
    console.log('Connected to SSE');
};

source.onerror = (event) => {
    if (source.readyState === EventSource.CLOSED) {
        console.log('Connection closed');
    } else {
        console.log('Reconnecting...');
    }
};

// Manual close
source.close();

// Auth header ile (EventSource native desteklemir, polyfill lazimdir)
// eventsource-polyfill ve ya fetch-based custom implementation
const source = new EventSourcePolyfill('/api/events', {
    headers: {
        'Authorization': 'Bearer ' + token,
    },
});
```

## PHP/Laravel ilə İstifadə

### Basic SSE in PHP

```php
// Sade PHP SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');  // Nginx buffering-i sondur

// Output buffering-i sondur
if (ob_get_level()) {
    ob_end_clean();
}

$lastEventId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? 0;

while (true) {
    // Yeni event-leri yoxla (DB, Redis, etc.)
    $events = getNewEvents($lastEventId);

    foreach ($events as $event) {
        echo "id: {$event['id']}\n";
        echo "event: {$event['type']}\n";
        echo "data: " . json_encode($event['data']) . "\n\n";

        $lastEventId = $event['id'];
    }

    // Keep-alive comment
    if (empty($events)) {
        echo ": heartbeat\n\n";
    }

    ob_flush();
    flush();

    // Connection yoxla
    if (connection_aborted()) {
        break;
    }

    sleep(1);
}
```

### Laravel SSE with StreamedResponse

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseController extends Controller
{
    /**
     * SSE endpoint - real-time notifications
     */
    public function notifications(Request $request): StreamedResponse
    {
        $user = $request->user();

        return new StreamedResponse(function () use ($user) {
            // Buffering sondur
            if (ob_get_level()) {
                ob_end_clean();
            }

            $lastId = 0;

            while (true) {
                // Database-den yeni notifications
                $notifications = $user->unreadNotifications()
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(10)
                    ->get();

                foreach ($notifications as $notification) {
                    $this->sendEvent(
                        'notification',
                        [
                            'id' => $notification->id,
                            'type' => $notification->type,
                            'data' => $notification->data,
                            'created_at' => $notification->created_at->toISOString(),
                        ],
                        $notification->id
                    );
                    $lastId = $notification->id;
                }

                // Heartbeat
                if ($notifications->isEmpty()) {
                    echo ": heartbeat\n\n";
                }

                ob_flush();
                flush();

                if (connection_aborted()) {
                    break;
                }

                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * SSE event format gondermek
     */
    private function sendEvent(string $event, array $data, string|int $id = null): void
    {
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
    }
}
```

### Redis Pub/Sub ile SSE

```php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveFeedController extends Controller
{
    /**
     * Redis Pub/Sub ile real-time feed
     */
    public function stream(string $channel): StreamedResponse
    {
        return new StreamedResponse(function () use ($channel) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Retry interval (5 saniye)
            echo "retry: 5000\n\n";

            Redis::subscribe([$channel], function ($message, $channel) {
                $data = json_decode($message, true);

                echo "id: " . ($data['id'] ?? uniqid()) . "\n";
                echo "event: " . ($data['event'] ?? 'message') . "\n";
                echo "data: {$message}\n\n";

                ob_flush();
                flush();

                if (connection_aborted()) {
                    return;
                }
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Event publish etmek (basqa endpoint ve ya job-dan)
     */
    public function publish(string $channel, array $data): void
    {
        Redis::publish($channel, json_encode([
            'id' => uniqid(),
            'event' => $data['event'] ?? 'message',
            ...$data,
        ]));
    }
}
```

### SSE Route ve Middleware

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sse/notifications', [SseController::class, 'notifications']);
    Route::get('/sse/feed/{channel}', [LiveFeedController::class, 'stream']);
});

// SSE ucun timeout middleware (uzun connection)
// app/Http/Middleware/SseMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // PHP execution time limitini ardir
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        // Session lock-u acirag (basqa request-ler block olmasin)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return $next($request);
    }
}
```

### AI Chat Streaming (ChatGPT style)

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    /**
     * AI cavabini stream etmek (ChatGPT kimi)
     */
    public function stream(Request $request): StreamedResponse
    {
        $message = $request->input('message');

        return new StreamedResponse(function () use ($message) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            // OpenAI API ile streaming
            $stream = \OpenAI::chat()->createStreamed([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'user', 'content' => $message],
                ],
            ]);

            foreach ($stream as $chunk) {
                $content = $chunk->choices[0]->delta->content ?? '';
                if ($content) {
                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

## Interview Sualları

### 1. SSE nedir ve nece isleyir?
**Cavab:** Server-Sent Events serverin HTTP uzerinden client-e real-time data push etme texnologiyasidir. Client EventSource API ile baglanir, server `text/event-stream` content type ile cavab qaytarir ve event-leri stream edir. Unidirectional-dir (yalniz server -> client).

### 2. SSE ve WebSocket arasinda ferq nedir?
**Cavab:** SSE tek yonlu (server->client), HTTP uzerinde isleyir, avtomatik reconnect var, text-only. WebSocket iki yonlu, ayri protokol, manual reconnect, binary destekliyir. Yalniz server push lazim olanda SSE daha sade ve uygundir.

### 3. SSE-de auto-reconnect nece isleyir?
**Cavab:** Browser baglantiini itirende avtomatik yeniden baglanir. Son aldiqi event ID-ni `Last-Event-ID` header ile gonderir. Server bu ID-den sonraki event-leri gonderir. `retry:` field ile reconnect intervali teyin oluna biler.

### 4. SSE-nin limitleri nelerdir?
**Cavab:** Yalniz text data (binary yox), tek yonlu (server->client), HTTP/1.1-de browser basina 6 connection limiti var (HTTP/2-de bu problem yoxdur), IE/Edge kohne versiyalari desteklemirydi (indi destekleyir).

### 5. SSE-de keep-alive nece edilir?
**Cavab:** Comment setiri gonderilir: `: heartbeat\n\n`. Bu proxy ve firewall-larin connection-u timeout etmesinin qarsisini alir. Her 15-30 saniye comment gonderilmesi tovsiye olunur.

### 6. SSE ne zaman istifade etmeliyik?
**Cavab:** Real-time notifications, live dashboards, stock price updates, news feeds, progress bars, AI chat streaming (ChatGPT uslubu). Client-den servere data gondermek lazim olmayan butun hallarda SSE uygun secimdir.

### 7. PHP-de SSE-nin problemi nedir?
**Cavab:** PHP traditional request-response model ile isleyir. Her SSE connection bir PHP process tutur. Cox connection = cox process = cox memory. Hell yolu: Nginx + Redis Pub/Sub, Swoole/ReactPHP kimi async PHP frameworks, ve ya SSE-ni Node.js/Go ile ayri service olaraq qurmaq.

## Best Practices

1. **Heartbeat gonderin** - Her 15-30 saniye comment setiri ile keep-alive
2. **Event ID istifade edin** - Reconnect zamani itirilen event-leri almaq ucun
3. **Retry interval teyin edin** - `retry: 5000` ile reconnect muddeti
4. **Nginx buffering sondurulmelidir** - `X-Accel-Buffering: no` header
5. **Session lock** - `session_write_close()` cagirib session-u azad edin
6. **PHP timeout** - `set_time_limit(0)` ile execution limit-i ardin
7. **Connection limit** - Server basina max SSE connection limiti qoyun
8. **HTTP/2 istifade edin** - 6 connection limiti aradan qalxir
9. **Graceful degradation** - SSE desteklenmirsə polling-e kecin
10. **Redis Pub/Sub** - Multi-process/multi-server ucun Redis istifade edin
