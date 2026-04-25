# WebSocket — real-time əlaqə, Hub pattern, gorilla/websocket (Lead)

## İcmal

WebSocket — HTTP-dən başlayan, sonra ikitərəfli (full-duplex) davamlı TCP əlaqəyə keçid edən protokoldur. Chat, real-time bildirişlər, canlı data (borsa, oyun, izləmə) üçün istifadə olunur.

Go-da WebSocket üçün ən populyar kitabxana `github.com/gorilla/websocket`-dir (arxivlənib, amma stable). Müasir alternativ `nhooyr.io/websocket`-dir. Bu mövzuda Hub pattern, hər client üçün goroutine arxitekturası, production-da diqqət etməli məqamlar öyrəniləcək.

PHP-də WebSocket üçün Ratchet/Swoole kimi ayrıca server tələb olunur. Go-da isə WebSocket handler standart HTTP serveri ilə eyni prosesdə işləyir, yüz minlərlə eyni anda əlaqəni dəstəkləyə bilər.

## Niyə Vacibdir

- REST polling-dən 10–100x az şəbəkə yükü — server push
- Laravel Echo/Pusher kimi xarici servislər əvəzinə öz WebSocket serverinizi yazın
- Goroutine-per-connection modeli — Node.js callback hell-i yoxdur
- Go runtime ilə 100K+ eyni anda əlaqə mümkündür

## Əsas Anlayışlar

### WebSocket handshake

```
Client → Server: HTTP GET + Upgrade: websocket + Sec-WebSocket-Key
Server → Client: 101 Switching Protocols + Sec-WebSocket-Accept
İndi: ikitərəfli TCP əlaqə — HTTP artıq istifadə olunmur
```

### Hub pattern arxitekturası

```
Client-1 ─┐                    ┌─ Client-1
Client-2 ─┤──→ Hub (goroutine) ├─ Client-2
Client-3 ─┘                    └─ Client-3

Hub: register/unregister/broadcast kanalları ilə
idarə olunan mərkəzi sinxronizasiya nöqtəsi
```

### Hər Client üçün 2 goroutine

```
readPump  — client-dən mesaj oxuyur (bloklanır)
writePump — client-ə mesaj yazır (kanaldan alır)
```

Bu model Go-nun goroutine-per-connection arxitekturasının klasik nümunəsidir.

### Message types

```go
websocket.TextMessage   // JSON/text
websocket.BinaryMessage // binary data
websocket.PingMessage   // keep-alive
websocket.PongMessage   // ping cavabı
websocket.CloseMessage  // bağlama
```

## Praktik Baxış

### Trade-off-lar

- Hər client 2 goroutine istifadə edir: 10K client → ~20K goroutine + ~160MB yaddaş (8KB/goroutine)
- Broadcast-da sync: RWMutex istifadəsi lazım, clients map-ə concurrent access var
- Message order guarantee: TCP-də var, amma broadcast sırasının qorunması üçün əlavə work lazım
- Reconnect: server-side reconnect məntiqi lazımdır; client disconnect edib yenidən qoşula bilər

### Production diqqət məqamları

- **Ping/Pong**: `SetPongHandler` + periodic ping → ölü əlaqəni təmizlə
- **Message size limit**: `conn.SetReadLimit(512 * 1024)` — böyük mesajlardan qorunma
- **Write deadline**: hər yazma əməliyyatı üçün deadline set et — yavaş client-i bloklamaqdan qorun
- **Graceful shutdown**: `hub.Close()` çağırışında bütün client-ləri bildirin
- **Authentication**: upgrade-dən əvvəl JWT/session yoxlaması

### Anti-pattern-lər

```go
// YANLIŞ: eyni conn-dan concurrent yazma
go func() { conn.WriteMessage(...) }()
go func() { conn.WriteMessage(...) }() // RACE CONDITION!

// DOĞRU: yalnız writePump goroutine-i yazır
// Digər goroutine-lər client.send kanalına yazır
```

```go
// YANLIŞ: broadcast zamanı clients map-i lock etmədən dəyişdirmək
for client := range hub.clients {
    delete(hub.clients, client) // iterator-da dəyişdirmə
}

// DOĞRU: Hub run() goroutine-inin içindən idarə etmək
```

## Nümunələr

### Nümunə 1: Production-grade Chat Server

```go
package main

import (
    "log"
    "net/http"
    "sync"
    "time"

    "github.com/gorilla/websocket"
)

const (
    writeWait      = 10 * time.Second
    pongWait       = 60 * time.Second
    pingPeriod     = (pongWait * 9) / 10
    maxMessageSize = 512 * 1024 // 512KB
)

var upgrader = websocket.Upgrader{
    ReadBufferSize:  4096,
    WriteBufferSize: 4096,
    CheckOrigin: func(r *http.Request) bool {
        // Production-da: origin yoxla
        // return r.Header.Get("Origin") == "https://yourapp.com"
        return true
    },
}

// Client — bir WebSocket əlaqəsini təmsil edir
type Client struct {
    hub  *Hub
    conn *websocket.Conn
    send chan []byte
    id   string
    room string
}

// readPump — client-dən mesajları oxuyur, Hub-a göndərir
func (c *Client) readPump() {
    defer func() {
        c.hub.unregister <- c
        c.conn.Close()
    }()

    c.conn.SetReadLimit(maxMessageSize)
    c.conn.SetReadDeadline(time.Now().Add(pongWait))
    c.conn.SetPongHandler(func(string) error {
        c.conn.SetReadDeadline(time.Now().Add(pongWait))
        return nil
    })

    for {
        _, message, err := c.conn.ReadMessage()
        if err != nil {
            if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
                log.Printf("Gözlənilməz bağlama (%s): %v", c.id, err)
            }
            break
        }
        c.hub.broadcast <- &Message{Room: c.room, Data: message}
    }
}

// writePump — send kanalından mesajları client-ə yazır
func (c *Client) writePump() {
    ticker := time.NewTicker(pingPeriod)
    defer func() {
        ticker.Stop()
        c.conn.Close()
    }()

    for {
        select {
        case message, ok := <-c.send:
            c.conn.SetWriteDeadline(time.Now().Add(writeWait))
            if !ok {
                c.conn.WriteMessage(websocket.CloseMessage, []byte{})
                return
            }

            w, err := c.conn.NextWriter(websocket.TextMessage)
            if err != nil {
                return
            }
            w.Write(message)

            // Kanal-da gözləyən mesajları da əlavə et (batching)
            n := len(c.send)
            for i := 0; i < n; i++ {
                w.Write([]byte{'\n'})
                w.Write(<-c.send)
            }

            if err := w.Close(); err != nil {
                return
            }

        case <-ticker.C:
            c.conn.SetWriteDeadline(time.Now().Add(writeWait))
            if err := c.conn.WriteMessage(websocket.PingMessage, nil); err != nil {
                return
            }
        }
    }
}

// Message — otaq + məlumat
type Message struct {
    Room string
    Data []byte
}

// Hub — bütün client-ləri mərkəzdən idarə edir
type Hub struct {
    mu         sync.RWMutex
    rooms      map[string]map[*Client]bool // otaq → client-lər
    register   chan *Client
    unregister chan *Client
    broadcast  chan *Message
}

func NewHub() *Hub {
    return &Hub{
        rooms:      make(map[string]map[*Client]bool),
        register:   make(chan *Client, 256),
        unregister: make(chan *Client, 256),
        broadcast:  make(chan *Message, 256),
    }
}

func (h *Hub) Run() {
    for {
        select {
        case client := <-h.register:
            h.mu.Lock()
            if _, ok := h.rooms[client.room]; !ok {
                h.rooms[client.room] = make(map[*Client]bool)
            }
            h.rooms[client.room][client] = true
            h.mu.Unlock()
            log.Printf("Qoşuldu: %s → otaq: %s", client.id, client.room)

        case client := <-h.unregister:
            h.mu.Lock()
            if room, ok := h.rooms[client.room]; ok {
                if _, ok := room[client]; ok {
                    delete(room, client)
                    close(client.send)
                    if len(room) == 0 {
                        delete(h.rooms, client.room)
                    }
                }
            }
            h.mu.Unlock()
            log.Printf("Ayrıldı: %s", client.id)

        case msg := <-h.broadcast:
            h.mu.RLock()
            clients := h.rooms[msg.Room]
            h.mu.RUnlock()

            for client := range clients {
                select {
                case client.send <- msg.Data:
                default:
                    // Kanal dolu — client yavaşdır, bağla
                    h.mu.Lock()
                    delete(h.rooms[client.room], client)
                    close(client.send)
                    h.mu.Unlock()
                    log.Printf("Yavaş client bağlandı: %s", client.id)
                }
            }
        }
    }
}

func (h *Hub) Stats() map[string]int {
    h.mu.RLock()
    defer h.mu.RUnlock()
    stats := make(map[string]int, len(h.rooms))
    for room, clients := range h.rooms {
        stats[room] = len(clients)
    }
    return stats
}

// wsHandler — HTTP → WebSocket upgrade
func wsHandler(hub *Hub, w http.ResponseWriter, r *http.Request) {
    // Authentication yoxlaması
    // token := r.URL.Query().Get("token")
    // userID, err := auth.ValidateToken(token)
    // if err != nil { http.Error(w, "unauthorized", 401); return }

    room := r.URL.Query().Get("room")
    if room == "" {
        room = "general"
    }
    clientID := r.URL.Query().Get("id") // real-da auth-dan gəlir

    conn, err := upgrader.Upgrade(w, r, nil)
    if err != nil {
        log.Println("Upgrade xətası:", err)
        return
    }

    client := &Client{
        hub:  hub,
        conn: conn,
        send: make(chan []byte, 256),
        id:   clientID,
        room: room,
    }

    hub.register <- client

    go client.writePump()
    go client.readPump()
}

func main() {
    hub := NewHub()
    go hub.Run()

    http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
        wsHandler(hub, w, r)
    })

    http.HandleFunc("/stats", func(w http.ResponseWriter, r *http.Request) {
        stats := hub.Stats()
        for room, count := range stats {
            log.Printf("Otaq %s: %d client", room, count)
        }
    })

    log.Println("WebSocket server :8080 portunda başladı")
    log.Fatal(http.ListenAndServe(":8080", nil))
}
```

### Nümunə 2: nhooyr.io/websocket ilə (müasir alternativ)

```go
package main

import (
    "context"
    "fmt"
    "net/http"

    "nhooyr.io/websocket"
    "nhooyr.io/websocket/wsjson"
)

type ChatMessage struct {
    User string `json:"user"`
    Text string `json:"text"`
}

func handler(w http.ResponseWriter, r *http.Request) {
    // nhooyr.io/websocket — gorilla-dan daha müasir API
    conn, err := websocket.Accept(w, r, &websocket.AcceptOptions{
        InsecureSkipVerify: true, // development
    })
    if err != nil {
        http.Error(w, err.Error(), http.StatusBadRequest)
        return
    }
    defer conn.CloseNow()

    ctx := r.Context()

    // JSON mesaj oxu
    var msg ChatMessage
    if err := wsjson.Read(ctx, conn, &msg); err != nil {
        fmt.Println("Oxuma xətası:", err)
        return
    }
    fmt.Printf("Aldı: %+v\n", msg)

    // JSON mesaj yaz
    reply := ChatMessage{User: "Server", Text: "Salam, " + msg.User}
    if err := wsjson.Write(ctx, conn, reply); err != nil {
        fmt.Println("Yazma xətası:", err)
        return
    }

    conn.Close(websocket.StatusNormalClosure, "")
}

func main() {
    http.HandleFunc("/ws", handler)
    fmt.Println("Server: :8080")
    // http.ListenAndServe(":8080", nil)
}
```

### Nümunə 3: WebSocket client (test üçün)

```go
package main

import (
    "context"
    "fmt"
    "log"
    "time"

    "github.com/gorilla/websocket"
)

func connectAndSend(serverURL string, messages []string) error {
    ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
    defer cancel()

    conn, _, err := websocket.DefaultDialer.DialContext(ctx, serverURL, nil)
    if err != nil {
        return fmt.Errorf("qoşulma: %w", err)
    }
    defer conn.Close()

    // Mesaj göndər
    for _, msg := range messages {
        if err := conn.WriteMessage(websocket.TextMessage, []byte(msg)); err != nil {
            return fmt.Errorf("yazma: %w", err)
        }
        log.Printf("Göndərildi: %s", msg)

        // Cavab gözlə
        _, reply, err := conn.ReadMessage()
        if err != nil {
            return fmt.Errorf("cavab oxuma: %w", err)
        }
        log.Printf("Cavab: %s", reply)
    }

    return nil
}

func main() {
    err := connectAndSend("ws://localhost:8080/ws?room=general&id=test-client",
        []string{"Salam!", "Necəsən?"},
    )
    if err != nil {
        log.Fatal(err)
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Typing indicator:**
Chat serverinə "user is typing" funksionallığı əlavə edin. Client `{"type":"typing","user":"Orkhan"}` göndərsin, bütün otaq üzvlərinə yayılsın.

**Tapşırıq 2 — Message history:**
Yeni client qoşulduqda otağın son 50 mesajını göndərin. In-memory ring buffer işlədin.

**Tapşırıq 3 — Reconnect client:**
Exponential backoff ilə avtomatik reconnect edən WebSocket client yazın. Max 5 cəhd, sonra xəta qaytar.

**Tapşırıq 4 — Load test:**
1000 eyni anda WebSocket əlaqəsi açın. Hər birindən hər saniyə bir mesaj göndərin. Server neçə mesaj/saniyə handle edə bilir?

**Tapşırıq 5 — Private messaging:**
Hub-a `DirectMessage(from, to string, data []byte) error` metodu əlavə edin. Yalnız hədəf client-ə göndərsin.

## Əlaqəli Mövzular

- [33-http-server](33-http-server.md) — HTTP server əsasları
- [35-middleware-and-routing](35-middleware-and-routing.md) — middleware
- [57-advanced-concurrency-2](57-advanced-concurrency-2.md) — worker pool
- [65-jwt-and-auth](65-jwt-and-auth.md) — WebSocket authentication
- [73-microservices](73-microservices.md) — real-time microservice arxitekturası
