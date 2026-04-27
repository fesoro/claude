# Server-Sent Events (SSE) (Senior)

## İcmal

Server-Sent Events (SSE) — server-in müştəriyə HTTP üzərindən bir-yönlü, davamlı məlumat axını göndərməsini təmin edən standart protokoldur (W3C EventSource API). Brauzer `EventSource` obyekti ilə SSE-yə qoşulur, server isə açıq saxladığı HTTP connection üzərindən istədiyi zaman event göndərir.

Go-da SSE xüsusi kitabxana tələb etmir — standart `net/http` + `text/event-stream` content type + `http.Flusher` ilə tətbiq edilir.

## Niyə Vacibdir

- Canlı bildirişlər (notification), dashboard update-ləri, progress tracking — WebSocket-in mürəkkəbliyinə ehtiyac olmadan
- SSE HTTP/1.1 üzərindən işləyir, standart proxy/CDN dəstəkləyir
- Avtomatik reconnect — brauzer `EventSource` özü yenidən qoşulur
- WebSocket-dən sadədir: handshake yox, frame yox, sadə mətn axını

## Əsas Anlayışlar

### SSE Event Formatı

```
data: məlumat\n\n
```

Bütün sahələr:

```
id: 42\n
event: order_update\n
data: {"status":"shipped"}\n
retry: 3000\n
\n
```

- `data` — məlumat (çox sətirli ola bilər: hər sətir `data:` ilə başlar)
- `id` — son alınan event ID; reconnect zamanı `Last-Event-ID` header-i göndərilir
- `event` — event tipi; JS-də `addEventListener("order_update", ...)` ilə tutulur
- `retry` — reconnect gecikmə (ms); default 3000ms
- Boş sətir `\n\n` — event-in sonu

### http.Flusher

SSE-nin düzgün işləməsi üçün serverin buferə yazmadan birbaşa göndərməsi lazımdır:

```go
func sseHandler(w http.ResponseWriter, r *http.Request) {
    flusher, ok := w.(http.Flusher)
    if !ok {
        http.Error(w, "SSE not supported", http.StatusInternalServerError)
        return
    }

    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")
    w.Header().Set("Connection", "keep-alive")
    // CORS lazımdırsa:
    w.Header().Set("Access-Control-Allow-Origin", "*")

    ticker := time.NewTicker(2 * time.Second)
    defer ticker.Stop()

    for {
        select {
        case <-r.Context().Done(): // client disconnect
            return
        case t := <-ticker.C:
            fmt.Fprintf(w, "data: %s\n\n", t.Format(time.RFC3339))
            flusher.Flush()
        }
    }
}
```

### Broadcast Hub — çox client-ə göndərmə

```go
type Hub struct {
    clients    map[chan []byte]struct{}
    register   chan chan []byte
    unregister chan chan []byte
    broadcast  chan []byte
    mu         sync.RWMutex
}

func NewHub() *Hub {
    return &Hub{
        clients:    make(map[chan []byte]struct{}),
        register:   make(chan chan []byte),
        unregister: make(chan chan []byte),
        broadcast:  make(chan []byte, 256),
    }
}

func (h *Hub) Run() {
    for {
        select {
        case client := <-h.register:
            h.mu.Lock()
            h.clients[client] = struct{}{}
            h.mu.Unlock()

        case client := <-h.unregister:
            h.mu.Lock()
            if _, ok := h.clients[client]; ok {
                delete(h.clients, client)
                close(client)
            }
            h.mu.Unlock()

        case msg := <-h.broadcast:
            h.mu.RLock()
            for client := range h.clients {
                select {
                case client <- msg:
                default:
                    // Yavaş client — buffer dolu, skip et
                }
            }
            h.mu.RUnlock()
        }
    }
}

func (h *Hub) Publish(data []byte) {
    h.broadcast <- data
}
```

### SSE Handler Hub ilə

```go
func (h *Hub) SSEHandler(w http.ResponseWriter, r *http.Request) {
    flusher, ok := w.(http.Flusher)
    if !ok {
        http.Error(w, "SSE not supported", http.StatusInternalServerError)
        return
    }

    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")
    w.Header().Set("Connection", "keep-alive")

    msgCh := make(chan []byte, 8)
    h.register <- msgCh
    defer func() { h.unregister <- msgCh }()

    // Last-Event-ID ilə missed event-ləri yolla (opsional)
    lastID := r.Header.Get("Last-Event-ID")
    _ = lastID // DB-dən missed event-ləri oxu, geri gönder

    for {
        select {
        case <-r.Context().Done():
            return
        case msg, ok := <-msgCh:
            if !ok {
                return
            }
            fmt.Fprintf(w, "data: %s\n\n", msg)
            flusher.Flush()
        }
    }
}
```

### Typed Events

```go
type SSEEvent struct {
    ID    string `json:"-"`
    Event string `json:"-"`
    Data  any    `json:"data"`
}

func writeSSEEvent(w io.Writer, flusher http.Flusher, evt SSEEvent) error {
    if evt.ID != "" {
        fmt.Fprintf(w, "id: %s\n", evt.ID)
    }
    if evt.Event != "" {
        fmt.Fprintf(w, "event: %s\n", evt.Event)
    }
    data, err := json.Marshal(evt.Data)
    if err != nil {
        return err
    }
    fmt.Fprintf(w, "data: %s\n\n", data)
    flusher.Flush()
    return nil
}
```

### JavaScript tərəf (müqayisə üçün)

```js
const evtSource = new EventSource("/events");

evtSource.onmessage = (e) => {
    console.log("default:", e.data);
};

evtSource.addEventListener("order_update", (e) => {
    const order = JSON.parse(e.data);
    updateUI(order);
});

evtSource.onerror = () => {
    // EventSource avtomatik reconnect edir
};
```

## Praktik Baxış

### SSE vs WebSocket

| | SSE | WebSocket |
|--|-----|-----------|
| Yön | Server → Client (one-way) | İki yönlü |
| Protokol | HTTP/1.1 | WS upgrade |
| Reconnect | Avtomatik (EventSource) | Əl ilə |
| Proxy/CDN | Standart dəstək | Bəzən problem |
| Binary | Yox (text) | Var |
| Overhead | Minimal | Frame header |
| Use case | Feed, bildiriş, progress | Chat, game, real-time collab |

### Nə vaxt SSE istifadə et

- Server-dən client-ə axın lazımdır, client göndərməz: log stream, notification, canlı metrika, build progress
- HTTP/2 ilə SSE daha səmərəlidir (multiplexing — bir bağlantıda çox SSE stream)

### Nə vaxt SSE istifadə etmə

- Client-dən server-ə tez-tez məlumat göndərilirsə → WebSocket
- Binary data (video, audio frame) lazımdırsa → WebSocket
- IE11 dəstəyi lazımdırsa → polyfill tələb edir

### Production məsələləri

```go
// Nginx arxasında SSE üçün buffering söndür:
// proxy_buffering off;
// proxy_cache off;

// Load balancer: sticky session lazım ola bilər
// (eyni serverdə open connection)

// Timeout: Nginx default 60s proxy timeout → artır
// proxy_read_timeout 3600s;

// HTTP/2 ilə SSE: multiplexing sayəsində connection limiti problem olmur
```

### Long polling ilə müqayisə

```
Long polling:
  Client → Server sorğu → Server gözləyir → cavab → Client yenidən sorğu
  ❌ Her event üçün yeni HTTP request
  ❌ Daha çox overhead

SSE:
  Client → Server bağlantı açır → Server event göndərir (bir bağlantı)
  ✓ Bir connection, çox event
  ✓ Overhead minimal
```

## Nümunələr

### Order Status Tracker

```go
type OrderUpdate struct {
    OrderID int    `json:"order_id"`
    Status  string `json:"status"`
    Message string `json:"message"`
}

func (h *OrderHandler) StatusStream(w http.ResponseWriter, r *http.Request) {
    orderID, _ := strconv.Atoi(r.PathValue("id"))

    flusher, ok := w.(http.Flusher)
    if !ok {
        http.Error(w, "streaming not supported", http.StatusInternalServerError)
        return
    }

    w.Header().Set("Content-Type", "text/event-stream")
    w.Header().Set("Cache-Control", "no-cache")

    // İlk dəfə cari statusu göndər
    order, _ := h.store.GetOrder(r.Context(), orderID)
    data, _ := json.Marshal(OrderUpdate{OrderID: orderID, Status: order.Status})
    fmt.Fprintf(w, "event: order_update\ndata: %s\n\n", data)
    flusher.Flush()

    // Pubsub kanalına subscribe ol
    ch := h.pubsub.Subscribe(fmt.Sprintf("order:%d", orderID))
    defer h.pubsub.Unsubscribe(fmt.Sprintf("order:%d", orderID), ch)

    for {
        select {
        case <-r.Context().Done():
            return
        case update := <-ch:
            data, _ := json.Marshal(update)
            fmt.Fprintf(w, "event: order_update\ndata: %s\n\n", data)
            flusher.Flush()
        }
    }
}
```

## Praktik Tapşırıqlar

1. **Canlı sayğac:** Hər saniyə sayğac artır, SSE ilə brauzerdə göstər
2. **Build progress:** Uzun sürən əməliyyatın (file processing) progress-ini SSE ilə yayımla
3. **Notification hub:** Çox istifadəçi qoşulur, admin panel-dən broadcast göndər
4. **Missed events:** `Last-Event-ID` ilə reconnect zamanı atlanmış event-ləri DB-dən oxu, yenidən göndər
5. **Auth ilə SSE:** JWT token query param-da gəlir (`?token=...`), handler-də verify et

## PHP ilə Müqayisə

```
PHP SSE                          Go SSE
────────────────────────────────────────────────────────────
header("Content-Type: text/event-stream")
                            →   w.Header().Set("Content-Type", "text/event-stream")
ob_flush(); flush()         →   flusher.Flush()
set_time_limit(0)           →   goroutine + context, timeout yoxdur
connection_aborted()        →   r.Context().Done()
while(true) { sleep(1); }  →   select { case <-ticker.C: }
```

**Fərqlər:**
- PHP-də SSE üçün `php-fpm` + Nginx buffering söndürülməli, uzun sürən PHP prosesi çalışmalıdır — resource-intensive
- Go-da hər SSE connection bir goroutine, yüngül (2KB stack); 10.000 bağlantı eyni anda mümkündür
- PHP-də horizontal scale etmək üçün Redis pub/sub tələb olunur; Go-da in-process channel ilə bir server daxilindəki broadcast mümkündür

## Əlaqəli Mövzular

- [27-goroutines-and-channels.md](27-goroutines-and-channels.md) — channel əsasları
- [33-http-server.md](33-http-server.md) — net/http əsasları
- [61-websocket.md](61-websocket.md) — iki yönlü real-time
- [83-event-bus.md](83-event-bus.md) — daxili pub/sub
- [53-graceful-shutdown.md](53-graceful-shutdown.md) — open connection-ları təmiz bağlamaq
