# Server-Sent Events (Middle)

## İcmal

Server-Sent Events (SSE) serverin client-ə HTTP bağlantısı üzərindən real-time data push etməsinə imkan verən texnologiyadır. WebSocket-dən fərqli olaraq, yalnız server-dən client-ə tək yönlü (unidirectional) data axını var. HTTP üzərində işləyir, buna görə proxy/firewall problemi yoxdur.

```
HTTP Polling:
  Client --request--> Server  (1 saniyə)
  Client --request--> Server  (2 saniyə)
  Client --request--> Server  (3 saniyə)
  Hər dəfə yeni connection, çoxlu overhead

SSE:
  Client --request--> Server
  Client <--- event 1 --- Server
  Client <--- event 2 --- Server
  Client <--- event 3 --- Server
  Tək connection, davamedici stream
```

## Niyə Vacibdir

Notification sistemləri, live dashboard-lar, AI chat streaming (ChatGPT üslubu), progress bar-lar kimi server-dən client-ə tək yönlü data axını olan hallarda SSE WebSocket-dən daha sadə və effektivdir. HTTP üzərində işlədiyindən mövcud infrastrukturla tam uyğundur, browser-dən başqa heç bir konfiqurasiya tələb etmir. Auto-reconnect və event ID tracking kimi daxili mexanizmlər əlavə reliability verir.

## Əsas Anlayışlar

### SSE Connection Flow

```
1. Client EventSource API ilə connect olur:
   const source = new EventSource('/events');

2. Server cavab qaytarır:
   HTTP/1.1 200 OK
   Content-Type: text/event-stream
   Cache-Control: no-cache
   Connection: keep-alive

3. Server event-ləri stream edir:
   data: {"message": "Hello"}

   event: notification
   data: {"text": "Yeni mesaj var"}

   data: Bu sadəcə text data-dır

4. Bağlantı qırılsa browser avtomatik reconnect edir
```

### Event Stream Format

```
# Sadəcə data
data: Hello World

# Multi-line data
data: line 1
data: line 2

# JSON data
data: {"user": "Orkhan", "message": "Salam"}

# Named event
event: notification
data: {"text": "Yeni sifariş!"}

# Event ID (reconnect zamanı Last-Event-ID göndərir)
id: 42
data: {"order": "shipped"}

# Retry interval (milliseconds)
retry: 5000

# Comment (keep-alive üçün istifadə olunur)
: this is a comment

# Tam nümunə (hər event boş sətirlə ayrılır)
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

### Auto-Reconnect və Last-Event-ID

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
  |    Last-Event-ID: 3               |  (son aldığı ID-ni göndərir)
  |<-- id: 4, data: "event 4" -------|  (server 3-dən sonrakıları göndərir)
  |<-- id: 5, data: "event 5" -------|
```

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
| Max connections    | 6/domain  | Unlimited | 6/domain    |
| Proxy friendly     | Yes ✓     | Sometimes | Yes ✓       |
| Complexity         | Aşağı     | Orta      | Aşağı       |
| Browser support    | Geniş     | Geniş     | Geniş       |
+--------------------+-----------+-----------+-------------+

SSE nə vaxt istifadə edin:
- Server push (notifications, live feed, stock prices)
- Client-dən server-ə data göndərmək lazım olmayanda
- Simple implementation lazım olanda

WebSocket nə vaxt istifadə edin:
- Bidirectional lazım olanda (chat, gaming)
- Binary data lazım olanda
- Çox sürətli hər iki yönlü kommunikasiya
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

// Auth header ilə (EventSource native dəstəkləmir, polyfill lazımdır)
const source = new EventSourcePolyfill('/api/events', {
    headers: {
        'Authorization': 'Bearer ' + token,
    },
});
```

## Praktik Baxış

**Nə vaxt SSE istifadə etmək lazımdır:**
- Real-time notifications (sifariş statusu, sistem xəbərləri)
- Live dashboard-lar (server metrics, analytics)
- AI chat streaming (ChatGPT üslubu token-by-token cavab)
- Progress bar-lar (fayl upload, batch job tamamlanması)
- Stock/kurs canlı qiymətləri

**Nə vaxt WebSocket seçmək lazımdır:**
- İstifadəçi server-ə mesaj da göndərməlidir (chat)
- Binary data axını lazımdır
- Çox yüksək sürətli bidirectional kommunikasiya

**Trade-off-lar:**
- PHP-də hər SSE connection bir PHP process tutur — 100 connection = 100 process
- HTTP/1.1-də domain başına 6 connection limiti var (HTTP/2 ilə aradan qalxır)
- Binary data dəstəklənmir (yalnız UTF-8 text)

**Anti-pattern-lər:**
- `X-Accel-Buffering: no` header-ini unutmaq (Nginx buffer-i SSE-ni bloklayır)
- Session lock-u bağlamamaq — `session_write_close()` çağırmadan digər request-lər bloklanır
- Event ID-siz istifadə etmək — disconnect zamanı itirilen event-lər bərpa olunmur
- PHP-də çox sayda SSE connection üçün async runtime istifadə etməmək

## Nümunələr

### Ümumi Nümunə

SSE axını vizual olaraq:

```
[Client browser] --- GET /sse/notifications ---> [Laravel server]
                                                       |
                              <--- id:1, event:notif --|
                              <--- : heartbeat --------|  (hər 30 san)
                              <--- id:2, event:order---|
```

### Kod Nümunəsi

**Basic SSE in PHP:**

```php
// Sadə PHP SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');  // Nginx buffering-i söndür

// Output buffering-i söndür
if (ob_get_level()) {
    ob_end_clean();
}

$lastEventId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? 0;

while (true) {
    // Yeni event-ləri yoxla (DB, Redis, etc.)
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

**Laravel SSE with StreamedResponse:**

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
            // Buffering söndür
            if (ob_get_level()) {
                ob_end_clean();
            }

            $lastId = 0;

            while (true) {
                // Database-dən yeni notifications
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

**Redis Pub/Sub ilə SSE:**

```php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveFeedController extends Controller
{
    /**
     * Redis Pub/Sub ilə real-time feed
     */
    public function stream(string $channel): StreamedResponse
    {
        return new StreamedResponse(function () use ($channel) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Retry interval (5 saniyə)
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
     * Event publish etmək (başqa endpoint və ya job-dan)
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

**SSE Route və Middleware:**

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sse/notifications', [SseController::class, 'notifications']);
    Route::get('/sse/feed/{channel}', [LiveFeedController::class, 'stream']);
});

// SSE üçün timeout middleware (uzun connection)
// app/Http/Middleware/SseMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // PHP execution time limitini artır
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        // Session lock-u aç (başqa request-lər block olmasın)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return $next($request);
    }
}
```

**AI Chat Streaming (ChatGPT style):**

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    /**
     * AI cavabını stream etmək (ChatGPT kimi)
     */
    public function stream(Request $request): StreamedResponse
    {
        $message = $request->input('message');

        return new StreamedResponse(function () use ($message) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            // OpenAI API ilə streaming
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

## Praktik Tapşırıqlar

1. **Sadə SSE endpoint:** `StreamedResponse` ilə `/api/sse/time` endpoint-i yazın — hər saniyə server vaxtını göndərsin. Browser-də `EventSource` ilə test edin.

2. **Notification stream:** Login olmuş istifadəçi üçün unread notification-ları SSE ilə göndərin. `Last-Event-ID`-ni düzgün handle edin ki, disconnect zamanı itirilen notification-lar bərpa olunsun.

3. **Redis Pub/Sub:** `LiveFeedController`-i implement edin. Ayrı bir endpoint-dən `Redis::publish()` ilə mesaj göndərin, SSE vasitəsilə browser-ə çatdırılmasını izləyin.

4. **AI streaming:** OpenAI (və ya başqa LLM) streaming API-sından gələn token-ləri SSE ilə real-time browser-ə göndərin. `[DONE]` siqnalını qəbul edəndə UI-da "tamamlandı" göstərin.

5. **Heartbeat middleware:** `SseMiddleware` yazın. Hər 15 saniyə comment göndərsin (`": ping\n\n"`). Nginx-in `proxy_read_timeout` ilə konflikt olmadığını yoxlayın.

6. **Load test:** 50 paralel SSE connection aç. PHP-FPM worker sayını izləyin. Sonra Swoole/Octane ilə eyni testi edin — fərqi müqayisə edin.

## Əlaqəli Mövzular

- [WebSocket](11-websocket.md)
- [Long Polling](13-long-polling.md)
- [HTTP Protocol](05-http-protocol.md)
- [Webhooks](23-webhooks.md)
- [API Gateway](21-api-gateway.md)
