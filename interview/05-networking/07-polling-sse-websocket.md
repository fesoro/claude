# Long Polling vs SSE vs WebSocket (Senior ⭐⭐⭐)

## İcmal

Real-time web aplikasiyalarında server-dən client-ə data göndərmənin üç əsas yanaşması var: Long Polling, Server-Sent Events (SSE), və WebSocket. Hər birinin fərqli complexity, scalability, browser support, və infrastructure tələbləri var. Bu üçü arasındakı seçim sadəcə texniki deyil, həm də infrastructure constraint-ləri (firewall, proxy, load balancer), use-case tələbləri, və team expertise-ə əsaslanmalıdır.

## Niyə Vacibdir

Real-time feature-lər müasir aplikasiyaların ayrılmaz hissəsidir — notifications, live feed, dashboard updates, chat. Interviewer bu sualı verərkən yoxlayır: "Hər tool-u düzgün yerə istifadə edə bilirsinizmi?" WebSocket-i hər real-time use-case üçün istifadə etmək overkill ola bilər. SSE bəzən daha sadə, daha az infrastructure tələb edən həlldir. Trade-off-ları anlamaq senior developer-in nişanəsidir.

## Əsas Anlayışlar

**Short Polling (baseline — hər ikisindən fərqli):**
- Client müntəzəm aralıqlarla server-i sorğulayır: `setInterval(() => fetch('/updates'), 3000)`
- Çox sadə, lakin inefficient: çox vaxt "yeni bir şey yoxdur" cavabı gəlir
- Latency: sorğu intervalına bərabər (3s interval → max 3s delay)
- Yük: interval nə qədər kiçik, server yükü o qədər böyük

**Long Polling:**
- Client sorğu göndərir, server yeni data olana qədər cavabı "saxlayır" (hold)
- Yeni data gəldikdə server cavab verir, client dərhal yeni sorğu göndərir
- Near real-time: data hazır olduqda dərhal çatdırılır
- Hər request üçün HTTP overhead var (headers, connection setup)
- Server-də thread/process sorğunu saxlamalıdır — concurrency modeli vacibdir
- Timeout management: 30s-60s uzun sorğu, timeout olduqda client yenidən soruşur
- Firewall/proxy friendly: normal HTTP sorğusu kimi görünür
- Use case: Legacy sistemlər, WebSocket dəstəklənmədiyi mühitlər, az tezlikli updates

**Server-Sent Events (SSE):**
- HTTP connection açıq saxlanılır, server istədiyi vaxt event göndərir
- **One-directional**: Yalnız server → client. Client yalnız HTTP ilə server-ə yazır
- `Content-Type: text/event-stream` — brauzer bunu event stream kimi tanıyır
- **Automatic reconnection**: Brauzer disconnect olduqda özü-özünə reconnect edir (built-in)
- **Event ID**: Server hər eventin ID-sini göndərə bilər. Reconnect olduqda client `Last-Event-ID` header-ını göndərir — missed events resume edilə bilər
- **HTTP/2 multiplexing**: HTTP/2 ilə bir connection üzərindən çox SSE stream mümkündür
- Proxy/firewall issue: Bəzi proxylər buffer edir, event-lər gecikmə ilə çatır. `Content-Type: text/event-stream` buffer-ı deaktiv edir
- Firewall dostu: Standard HTTP üzərindən işləyir
- Use case: Live news feed, notifications, dashboard metrics, progress tracking

**WebSocket:**
- Full-duplex persistent connection: Hər iki tərəf istənilən vaxt data göndərə bilir
- HTTP Upgrade handshake ilə qurulur, sonra WebSocket protokoluna keçir
- **Bidirectional**: Server → client + client → server eyni connection üzərindən
- **Stateful**: Connection server tərəfində state saxlayır — horizontal scaling mürəkkəbləşir
- **Low overhead**: Bir dəfə bağlandıqdan sonra hər mesajda HTTP header yoxdur (kiçik frame header)
- Browser support: Bütün müasir brauserlər (IE10+)
- Use case: Chat, collaborative editing, online gaming, live trading, bidirectional real-time

**Müqayisə cədvəli:**

| Xüsusiyyət | Long Polling | SSE | WebSocket |
|---|---|---|---|
| Yön | Bidirectional | Server → Client | Bidirectional |
| Protocol | HTTP | HTTP | WS/WSS |
| Reconnect | Manual | Automatic | Manual |
| Latency | Low | Low | Very Low |
| Scalability | Medium | Good | Complex |
| Complexity | Low | Low | Medium |
| Proxy support | Excellent | Good | Sometimes issue |

## Praktik Baxış

**Interview-da yanaşma:**
Sual gəldikdə hər üçünü izah edib sonra use-case əsasında seçim edin. "Hər şey üçün WebSocket" yanlış yanaşmadır. SSE-nin notification sistemi üçün nə qədər sadə həll olduğunu göstərin.

**Follow-up suallar:**
- "Nginx SSE ilə işlədikdə niyə problem ola bilər?" → Nginx default buffer edir, `proxy_buffering off` lazımdır
- "HTTP/2 SSE-ni necə dəyişir?" → HTTP/1.1-də SSE üçün ayrı connection lazımdır. HTTP/2-də bir connection üzərindən multiplex edilir — browser-in 6 connection limiti aradan qalxır
- "SSE max connection sayı nədir?" → HTTP/2 ilə faktiki olaraq limitsiz, HTTP/1.1-də browser per-domain 6 connection (SSE-nin hamısı istifadə edə bilər)
- "Long polling thread exhaustion problemi nədir?" → Hər request bir thread tutur. 10K concurrent long poll = 10K thread. Async I/O (Node.js, Go, PHP Swoole) bu problemi həll edir

**Ümumi səhvlər:**
- SSE-nin yalnız server→client olduğunu unutmaq (bidirectional lazımdırsa WebSocket)
- Long polling-i "köhnəlmiş" kimi tamamilə rədd etmək — hələ də bəzi hallarda ideal
- Nginx proxy_buffering-i disable etməməkdən SSE-nin işləmədiyini görəndə çaşmaq
- SSE-nin automatic reconnection-ının built-in olduğunu bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
SSE `Last-Event-ID` ilə missed event resume mexanizmini, HTTP/2-nin SSE scaling-ə təsirini, ya da async I/O ilə long polling efficiency-sini izah etmək.

## Nümunələr

### Tipik Interview Sualı

"Design a notification system for a SaaS platform. Users need to receive real-time updates when their jobs complete. What technology would you choose?"

### Güclü Cavab

Bu use-case üçün SSE ideal seçimdir. Niyə?

Notification sistemi əsasən server→client yönlüdür — job tamamlandı, email göndərildi, payment processed kimi eventlər server-dən gəlir. Client cavab göndərmir. Bu one-directional pattern SSE-nin güclü tərəfidir.

WebSocket ilə müqayisədə SSE:
- Daha sadə implement edilir
- HTTP üzərindən işləyir — proxy/firewall problemi azdır
- Automatic reconnection built-in
- Backend-də sadəcə response stream-dir

Arxitektura: Client SSE endpoint-ə qoşulur (`/api/notifications/stream`). Backend job tamamlandıqda Redis Pub/Sub-a publish edir. SSE server Redis-dən consume edib client-ə göndərir.

Long Polling da variant olardı — legacy browser dəstəyi lazım olsaydı. WebSocket isə overkill — client heç nə göndərmir ki.

### Kod Nümunəsi

```php
// Laravel SSE endpoint
// routes/api.php
Route::get('/notifications/stream', NotificationStreamController::class)
    ->middleware('auth:sanctum');

// app/Http/Controllers/NotificationStreamController.php
class NotificationStreamController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;

        return response()->stream(function () use ($userId) {
            // Initial connection
            echo "data: " . json_encode(['type' => 'connected']) . "\n\n";
            ob_flush();
            flush();

            $lastId = $request->header('Last-Event-ID', 0);

            while (true) {
                // Missed events (reconnect sonrası)
                $missed = Notification::where('user_id', $userId)
                    ->where('id', '>', $lastId)
                    ->where('sent_at', null)
                    ->get();

                foreach ($missed as $notification) {
                    echo "id: {$notification->id}\n";
                    echo "event: notification\n";
                    echo "data: " . json_encode($notification->toArray()) . "\n\n";
                    $lastId = $notification->id;
                    ob_flush();
                    flush();
                    $notification->markAsSent();
                }

                // Heartbeat (connection canlı saxlamaq üçün)
                echo ": heartbeat\n\n";
                ob_flush();
                flush();

                sleep(1);

                // Client bağlantını kəsdisə exit
                if (connection_aborted()) {
                    break;
                }
            }
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',  // Nginx buffering-i disable et
        ]);
    }
}
```

```javascript
// Client-side SSE ilə notification
const eventSource = new EventSource('/api/notifications/stream', {
    withCredentials: true  // Cookie/auth header
});

eventSource.addEventListener('notification', (event) => {
    const notification = JSON.parse(event.data);
    showNotification(notification);
});

eventSource.onerror = (error) => {
    console.error('SSE error:', error);
    // eventSource.CLOSED === 2 → reconnect edilmir
    // Automatic reconnect EventSource built-in edir
};

// Manual reconnect control
// eventSource.close() → connection bağla
```

```javascript
// Long Polling implementation
async function longPoll(lastEventId = 0) {
    try {
        const response = await fetch(
            `/api/updates?lastId=${lastEventId}`,
            { signal: AbortSignal.timeout(30000) }  // 30s timeout
        );

        if (response.ok) {
            const data = await response.json();
            if (data.events.length > 0) {
                processEvents(data.events);
                lastEventId = data.lastId;
            }
        }
    } catch (error) {
        if (error.name !== 'TimeoutError') {
            await sleep(2000);  // Error halda gözlə
        }
    }

    // Dərhal növbəti sorğu
    longPoll(lastEventId);
}
```

```
Latency müqayisəsi:

Short Polling (3s interval):
Server event  Client aware
     |              |
     0s    ....    ~3s (worst case)

Long Polling:
Server event  Client aware
     |              |
     0s           ~0ms (immediate)

SSE:
Server event  Client aware
     |              |
     0s           ~0ms (immediate)

WebSocket:
Server event  Client aware
     |              |
     0s           ~0ms (immediate, lowest overhead)
```

## Praktik Tapşırıqlar

- Laravel-də SSE notification endpoint qurun: job complete → SSE push
- Long polling-in thread exhaustion problemini simulate edin (100 parallel connection)
- SSE-nin Nginx arxasında buffer problemi yaradıb `proxy_buffering off` ilə həll edin
- SSE `Last-Event-ID` missed event recovery-ni test edin: bağlantı kəsilsin, yenidən qoşulsun
- Eyni use-case üçün SSE vs WebSocket vs Long Polling-i implement edib fərqləri qiymətləndirin

## Əlaqəli Mövzular

- [WebSockets](06-websockets.md) — WebSocket-in dərin izahı
- [TCP vs UDP](01-tcp-vs-udp.md) — Bütün bu texnologiyalar TCP üzərindən işləyir
- [HTTP Versions](02-http-versions.md) — HTTP/2 SSE üçün multiplexing
- [REST vs GraphQL vs gRPC](05-rest-graphql-grpc.md) — GraphQL Subscription SSE/WS üzərindən
- [Proxy vs Reverse Proxy](13-proxy-reverse-proxy.md) — Nginx SSE buffer problemi
