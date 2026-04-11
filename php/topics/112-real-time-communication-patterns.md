# Real-Time Communication Patterns

## Mündəricat
1. [Müqayisə Cədvəli](#müqayisə-cədvəli)
2. [Short Polling](#short-polling)
3. [Long Polling](#long-polling)
4. [Server-Sent Events (SSE)](#server-sent-events-sse)
5. [WebSocket](#websocket)
6. [PHP Məhdudiyyətləri](#php-məhdudiyyətləri)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Müqayisə Cədvəli

```
┌──────────────┬────────────┬────────────┬────────────┬────────────┐
│              │Short Polling│Long Polling│    SSE     │ WebSocket  │
├──────────────┼────────────┼────────────┼────────────┼────────────┤
│ Protokol     │ HTTP/1.1   │ HTTP/1.1   │ HTTP/1.1   │ WS (upgrade│
│ Yön          │ Client→Srv │ Client→Srv │ Srv→Client │ Bidirective│
│ Latency      │ Yüksək     │ Aşağı      │ Aşağı      │ Ən aşağı   │
│ Server yükü  │ Yüksək     │ Orta       │ Aşağı      │ Aşağı      │
│ HTTP cache   │ ✅         │ ❌         │ ✅         │ ❌         │
│ Firewall     │ ✅         │ ✅         │ ✅         │ ⚠️ Bəzən   │
│ Auto reconnect│✅ client  │ ✅ client  │ ✅ native  │ ❌ manual  │
│ PHP-FPM      │ ✅         │ ⚠️         │ ❌         │ ❌         │
│ Swoole/Amp   │ ✅         │ ✅         │ ✅         │ ✅         │
└──────────────┴────────────┴────────────┴────────────┴────────────┘
```

---

## Short Polling

```
Client hər N saniyədə sorğu göndərir.

┌────────┐  GET /updates  ┌────────┐
│ Client │───────────────►│ Server │ → 200 [] (boş)
│        │  (2s sonra)    │        │
│        │───────────────►│        │ → 200 [] (boş)
│        │  (2s sonra)    │        │
│        │───────────────►│        │ → 200 [{event}]
└────────┘                └────────┘

Faydalar: Sadə, PHP-FPM ilə işləyir, stateless
Çatışmazlıqlar: 
  - Boş sorğular bandwidth israf edir
  - N saniyəyə qədər gecikmə
  - Server yükü yüksəkdir

Use case:
  Dashboard refresh (hər 30s)
  Status check (tez-tez dəyişmir)
```

---

## Long Polling

```
Client sorğu göndərir, server yeni məlumat olana qədər GÖZLƏYIR.

┌────────┐  GET /updates  ┌────────┐
│ Client │───────────────►│ Server │
│        │    (gözlə...)  │        │ ← event baş verənə qədər
│        │                │        │   connection açıq qalır
│        │◄── 200 {event}─│        │ ← event baş verdi!
│        │                │        │
│        │  GET /updates  │        │ ← dərhal yenidən sorğu
│        │───────────────►│        │
└────────┘                └────────┘

Server-da implementasiya:
  while (true) {
      $events = getNewEvents($lastEventId);
      if (!empty($events)) return $events; // cavab ver
      sleep(1);  // gözlə
      if (time() - $start > 30) return []; // timeout, yenidən sorğu
  }

Faydalar:
  + Short polling-dən az yük
  + Real-time görünüş
  
Çatışmazlıqlar:
  - PHP-FPM worker-ı 30s bloklanır → az worker qalır
  - Hər client 1 worker tutur
  - Scale etmək çətin
```

---

## Server-Sent Events (SSE)

```
Server → Client unidirectional stream.
HTTP connection açıq qalır, server push edir.

Client:
  const evtSource = new EventSource('/events');
  evtSource.onmessage = (e) => console.log(e.data);

Server cavabı:
  Content-Type: text/event-stream
  Cache-Control: no-cache
  
  data: {"type":"order_update","id":42}\n\n
  data: {"type":"notification","msg":"Hello"}\n\n

Format:
  event: custom_event_name\n   (optional)
  id: 123\n                    (optional, reconnect üçün)
  data: your data here\n\n    (required, double newline ilə bitir)

SSE xüsusiyyətləri:
  ✓ HTTP/1.1 — Proxy, firewall friendly
  ✓ Auto-reconnect (EventSource özü edir)
  ✓ Last-Event-ID header (yenidən bağlandıqda hansı event-dən davam?)
  ✓ Simple protocol
  ✗ Server → Client yalnız (Client → Server deyil)
  ✗ PHP-FPM ilə uzun connection problemi

Use case: Live dashboard, notification stream, progress bar
```

---

## WebSocket

```
HTTP → WebSocket upgrade.
Tam bidirectional, tam duplex.

Əl sıxışma:
  Client: GET /chat HTTP/1.1
          Upgrade: websocket
          Connection: Upgrade
          Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
          
  Server: HTTP/1.1 101 Switching Protocols
          Upgrade: websocket
          Connection: Upgrade
          Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=

Sonra:
  Client → Server: {type: "message", text: "salam"}
  Server → Client: {type: "message", text: "salam!", from: "Ali"}
  Server → Client: {type: "typing", user: "Vüsal"}
  Client → Server: {type: "ping"}
  Server → Client: {type: "pong"}

Faydalar:
  ✓ Full duplex
  ✓ Ən aşağı latency
  ✓ Custom protocol (JSON, binary)
  
Çatışmazlıqlar:
  ✗ PHP-FPM ilə deyil (uzun connection!)
  ✗ Auto-reconnect manual (library lazımdır)
  ✗ Stateful → scale çətin (sticky session ya shared state)
  ✗ Bəzi proxy/firewall problemlər

Use case: Chat, real-time game, collaborative editing, live auction
```

---

## PHP Məhdudiyyətləri

```
PHP-FPM shared-nothing modeli:
  Hər request → worker alır → işlə → serbest burax
  Long-lived connection → worker uzun müddət tutulur

SSE / Long Polling ilə PHP-FPM:
  N client = N worker bloklanır
  pm.max_children = 50 → max 50 SSE connection!
  Normal HTTP request üçün worker qalmır → sistem çöküşü

Həllər:

1. Swoole (PHP async extension):
   Event loop əsaslı.
   Bir proses → minlərlə WebSocket connection!
   
   $server = new Swoole\WebSocket\Server("0.0.0.0", 9501);
   $server->on('message', function($server, $frame) {
       $server->push($frame->fd, "pong: " . $frame->data);
   });

2. ReactPHP:
   Pure PHP event loop.
   Non-blocking I/O.
   Framework: Ratchet (WebSocket), ReactPHP HTTP

3. Nginx + Redis pubsub:
   PHP-FPM normal HTTP endpoint-i.
   Nginx-push-stream-module SSE üçün.
   PHP Redis-ə yazar → Nginx subscriber-lara push edir.

4. Dedicated WebSocket server (Node.js, Go):
   PHP backend → Redis pubsub.
   Node.js WebSocket server → Redis subscribe → Client push.
   Hər servis öz güclü tərəfini edir.
```

---

## PHP İmplementasiyası

```php
<?php
// SSE endpoint — PHP-FPM ilə (məhdud connection sayı!)
class SseController
{
    public function stream(Request $request): void
    {
        // Buffer-ı devre dışı bırak
        if (ob_get_level()) ob_end_clean();

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no'); // Nginx buffering-i söndür

        $lastEventId = $request->header('Last-Event-ID', 0);

        $start = time();

        while (true) {
            // Connection kəsildi?
            if (connection_aborted()) break;

            // 30 saniyə timeout
            if (time() - $start > 30) {
                echo "event: timeout\ndata: reconnect\n\n";
                break;
            }

            // Yeni event-lər var?
            $events = $this->eventStore->getSince($lastEventId);

            foreach ($events as $event) {
                echo "id: {$event->id}\n";
                echo "event: {$event->type}\n";
                echo "data: " . json_encode($event->data) . "\n\n";
                $lastEventId = $event->id;
            }

            // Keep-alive ping (15s)
            if (time() - $start % 15 === 0) {
                echo ": keep-alive\n\n";
            }

            flush();
            sleep(1);
        }
    }
}
```

```php
<?php
// Swoole WebSocket Server
use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;

$server = new Server('0.0.0.0', 9501);
$connections = new SplObjectStorage();

$server->on('open', function(Server $server, Request $request) use ($connections) {
    $connections->attach($request, $request->fd);
    echo "Connection opened: fd={$request->fd}\n";
});

$server->on('message', function(Server $server, Frame $frame) use ($connections) {
    $data = json_decode($frame->data, true);

    // Broadcast to all connections
    foreach ($connections as $req) {
        $fd = $connections[$req];
        if ($server->isEstablished($fd)) {
            $server->push($fd, json_encode([
                'from' => $frame->fd,
                'data' => $data,
            ]));
        }
    }
});

$server->on('close', function(Server $server, int $fd) {
    echo "Connection closed: fd={$fd}\n";
});

$server->start();
```

---

## İntervyu Sualları

- SSE WebSocket-dən nəylə fərqlənir? Hər birini nə vaxt seçərdiniz?
- PHP-FPM ilə SSE niyə problematikdir?
- Long polling server-da worker-ı niyə bloklanır?
- WebSocket-i scale etmək üçün nə lazımdır? (Sticky session nədir?)
- Swoole PHP-FPM modelindən necə fərqlənir?
- Chat app üçün hansı pattern seçərdiniz? Niyə?
- `Last-Event-ID` SSE-də nə işə yarayır?
