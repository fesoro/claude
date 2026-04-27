# TCP Server (Senior)

## İcmal

TCP — transport layer protokoludur. HTTP, WebSocket, gRPC, database driver-ları — hamısı TCP üzərindən işləyir. Go-da `net` paketi TCP server və client yazmaq üçün yüksək səviyyəli, lakin eyni zamanda güclü API təqdim edir. Xüsusi protokollar, game server, real-time chat, IoT device kommunikasiyası kimi HTTP-nin yetərli olmadığı hallarda birbaşa TCP istifadə olunur.

## Niyə Vacibdir

- HTTP-in altında TCP var — TCP-ni anlamaq HTTP debug-ını asanlaşdırır
- Xüsusi binary protokollar (database protocol, custom RPC) TCP üzərindən yazılır
- WebSocket, SSE kimi texnologiyalar TCP connection-larını yenidən istifadə edir
- Yüksək throughput lazım olan sistemlərdə HTTP overhead-ini azaltmaq üçün TCP
- Go-nun `net` paketi goroutine-friendly — hər connection ayrı goroutine-də

## Əsas Anlayışlar

### TCP vs HTTP

| Xüsusiyyət | TCP | HTTP |
|------------|-----|------|
| Protokol | Transport layer | Application layer |
| Format | Bayt axını (stream) | Request/Response |
| Overhead | Az | Header-lər, method, status |
| İstifadə | Xüsusi protokol, game, chat | REST API, web |
| Framing | Özün müəyyən edirsən | HTTP standard |

### net.Listen və net.Accept

```go
listener, err := net.Listen("tcp", ":9090")  // socket aç
conn, err := listener.Accept()               // yeni connection gözlə (blocking)
go handleConn(conn)                          // hər connection ayrı goroutine
```

`Accept()` — blocking-dir. Yeni connection gələnə qədər gözləyir.

### Connection Lifecycle

```
net.Listen() → listener yaranır
  ↓
listener.Accept() → yeni conn gəlir (blocking)
  ↓
go handleConn(conn) → goroutine-də işlə
  ↓
bufio.Scanner/conn.Read() → məlumat oxu
  ↓
conn.Write() → cavab göndər
  ↓
conn.Close() / defer conn.Close()
```

### Deadline-lar (Timeout)

TCP connection-larında idle connection-ları bağlamaq üçün:

```go
conn.SetDeadline(time.Now().Add(10 * time.Second))      // read + write
conn.SetReadDeadline(time.Now().Add(5 * time.Second))   // yalnız read
conn.SetWriteDeadline(time.Now().Add(5 * time.Second))  // yalnız write
```

Deadline keçsə: `net.Error` — `Timeout() == true` qaytarır.

### bufio.Scanner ilə Oxumaq

TCP məlumat stream-dir — `\n` ilə satır-satır oxumaq üçün `bufio.Scanner` ən rahat üsuldur:

```go
scanner := bufio.NewScanner(conn)
for scanner.Scan() {
    line := scanner.Text() // bir sətir
    // process...
}
```

Binary protokol üçün `conn.Read(buf)` birbaşa istifadə edilir.

### net.Dial — TCP Client

```go
conn, err := net.Dial("tcp", "localhost:9090")
// və ya timeout ilə:
conn, err := net.DialTimeout("tcp", "localhost:9090", 5*time.Second)
```

## Praktik Baxış

### Real Layihələrdə İstifadə

- **Database driver-ları:** PostgreSQL, MySQL — TCP üzərindən xüsusi protokol
- **Redis, Memcached:** RESP (Redis Serialization Protocol) TCP üzərindən
- **gRPC:** HTTP/2 (TCP) üzərindən
- **Custom internal services:** HTTP overhead lazım olmadığında
- **Game server:** Low-latency real-time communication
- **IoT gateway:** Cihazlardan məlumat toplama

### Connection Pool Konsepti

TCP connection yaratmaq bahalıdır (3-way handshake). Database driver-ları connection pool-lar saxlayır:

```
Client → Pool → [conn1, conn2, ..., connN] → Database
```

`net.Listen` server tərəfindədir. Client tərəfdə connection pool üçün `sync.Pool` + goroutine limit istifadə olunur.

### Concurrent Connection İdarəsi

Hər connection ayrı goroutine-də işləyir. Çox connection zamanı goroutine sayını limitləmək lazım ola bilər:

```go
semaphore := make(chan struct{}, maxConcurrent)
for {
    conn, _ := listener.Accept()
    semaphore <- struct{}{}
    go func() {
        defer func() { <-semaphore }()
        handleConn(conn)
    }()
}
```

### Trade-off-lar

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| TCP birbaşa | Minimum overhead, xüsusi protokol | Protokol özün yazmaq lazımdır |
| HTTP üzərindən | Standart, tooling çox | Header overhead, stateless |
| gRPC | Binary, bidirectional, streaming | Mürəkkəblik |
| WebSocket | Browser support, HTTP upgraade | HTTP 1.1 tələb edir |

### Anti-pattern-lər

```go
// Anti-pattern 1: defer conn.Close() olmadan
func handleConn(conn net.Conn) {
    // conn.Close() çağırılmırsa — resource leak!
}
// Düzgün:
func handleConn(conn net.Conn) {
    defer conn.Close()
    ...
}

// Anti-pattern 2: Timeout olmadan oxumaq
scanner := bufio.NewScanner(conn)
scanner.Scan() // əgər client heç nə göndərməsə — əbədi gözlər

// Düzgün:
conn.SetReadDeadline(time.Now().Add(30 * time.Second))

// Anti-pattern 3: Hər connection üçün goroutine yaratmaq LIMIT olmadan
// 100,000 concurrent connection → 100,000 goroutine — memory problem

// Anti-pattern 4: Connection xətasını Accept loop-da düzgün handle etməmək
conn, err := listener.Accept()
if err != nil {
    log.Fatal(err) // server dayanır! log.Println istifadə edin
}
```

## Nümunələr

### Nümunə 1: Sadə Echo Server

```go
package main

import (
    "bufio"
    "fmt"
    "log"
    "net"
    "strings"
    "time"
)

func handleConnection(conn net.Conn) {
    defer conn.Close()

    remoteAddr := conn.RemoteAddr().String()
    log.Printf("[+] Yeni bağlantı: %s", remoteAddr)

    // İdle timeout — 1 dəqiqə cavab olmasa bağla
    conn.SetDeadline(time.Now().Add(60 * time.Second))

    conn.Write([]byte("Xoş gəldiniz! 'exit' yazın çıxmaq üçün.\n"))

    scanner := bufio.NewScanner(conn)
    for scanner.Scan() {
        msg := strings.TrimSpace(scanner.Text())
        if msg == "" {
            continue
        }

        log.Printf("[%s] ← %s", remoteAddr, msg)

        if msg == "exit" {
            conn.Write([]byte("Sağ olun!\n"))
            return
        }

        // Echo
        response := fmt.Sprintf("Echo: %s\n", msg)
        conn.Write([]byte(response))

        // Hər mesajdan sonra deadline-ı yenilə
        conn.SetDeadline(time.Now().Add(60 * time.Second))
    }

    if err := scanner.Err(); err != nil {
        log.Printf("[%s] Oxuma xətası: %v", remoteAddr, err)
    }

    log.Printf("[-] Bağlantı bağlandı: %s", remoteAddr)
}

func main() {
    listener, err := net.Listen("tcp", ":9090")
    if err != nil {
        log.Fatal("Listen xətası:", err)
    }
    defer listener.Close()

    log.Println("TCP Server :9090-da işləyir")
    log.Println("Test: telnet localhost 9090")
    log.Println("Test: nc localhost 9090")

    for {
        conn, err := listener.Accept()
        if err != nil {
            // listener.Close() çağırıldıqda bu xəta gəlir
            log.Println("Accept xətası:", err)
            continue
        }
        go handleConnection(conn)
    }
}
```

### Nümunə 2: Command Protocol Server

```go
package main

import (
    "bufio"
    "encoding/json"
    "fmt"
    "log"
    "net"
    "strings"
    "time"
)

// Xüsusi JSON protokol: JSON request → JSON response
type Request struct {
    Command string            `json:"cmd"`
    Params  map[string]string `json:"params,omitempty"`
}

type Response struct {
    OK      bool        `json:"ok"`
    Data    interface{} `json:"data,omitempty"`
    Error   string      `json:"error,omitempty"`
}

// In-memory key-value store (demo)
var store = make(map[string]string)

func processCommand(req Request) Response {
    switch req.Command {
    case "set":
        key := req.Params["key"]
        val := req.Params["value"]
        if key == "" {
            return Response{Error: "key required"}
        }
        store[key] = val
        return Response{OK: true, Data: "OK"}

    case "get":
        key := req.Params["key"]
        val, ok := store[key]
        if !ok {
            return Response{Error: fmt.Sprintf("key '%s' tapılmadı", key)}
        }
        return Response{OK: true, Data: val}

    case "del":
        key := req.Params["key"]
        delete(store, key)
        return Response{OK: true}

    case "ping":
        return Response{OK: true, Data: "pong"}

    case "time":
        return Response{OK: true, Data: time.Now().Format(time.RFC3339)}

    default:
        return Response{Error: fmt.Sprintf("naməlum komanda: %s", req.Command)}
    }
}

func handleClient(conn net.Conn) {
    defer conn.Close()
    conn.SetDeadline(time.Now().Add(5 * time.Minute))

    log.Printf("Yeni client: %s", conn.RemoteAddr())

    scanner := bufio.NewScanner(conn)
    encoder := json.NewEncoder(conn)

    for scanner.Scan() {
        line := strings.TrimSpace(scanner.Text())
        if line == "" {
            continue
        }

        var req Request
        if err := json.Unmarshal([]byte(line), &req); err != nil {
            resp := Response{Error: "JSON parse xətası: " + err.Error()}
            encoder.Encode(resp)
            continue
        }

        resp := processCommand(req)
        if err := encoder.Encode(resp); err != nil {
            log.Printf("Write xətası: %v", err)
            return
        }

        conn.SetDeadline(time.Now().Add(5 * time.Minute))
    }
}

func main() {
    ln, err := net.Listen("tcp", ":9091")
    if err != nil {
        log.Fatal(err)
    }
    defer ln.Close()

    log.Println("JSON Protocol Server :9091")
    log.Println(`Test: echo '{"cmd":"set","params":{"key":"name","value":"Go"}}' | nc localhost 9091`)
    log.Println(`Test: echo '{"cmd":"get","params":{"key":"name"}}' | nc localhost 9091`)

    for {
        conn, err := ln.Accept()
        if err != nil {
            log.Println("Accept:", err)
            continue
        }
        go handleClient(conn)
    }
}
```

### Nümunə 3: TCP Client

```go
package main

import (
    "bufio"
    "encoding/json"
    "fmt"
    "log"
    "net"
    "time"
)

type TCPClient struct {
    conn    net.Conn
    scanner *bufio.Scanner
    encoder *json.Encoder
}

func NewTCPClient(addr string) (*TCPClient, error) {
    conn, err := net.DialTimeout("tcp", addr, 5*time.Second)
    if err != nil {
        return nil, fmt.Errorf("bağlana bilmədi %s: %w", addr, err)
    }

    return &TCPClient{
        conn:    conn,
        scanner: bufio.NewScanner(conn),
        encoder: json.NewEncoder(conn),
    }, nil
}

func (c *TCPClient) Send(req map[string]interface{}) (map[string]interface{}, error) {
    c.conn.SetDeadline(time.Now().Add(10 * time.Second))

    if err := c.encoder.Encode(req); err != nil {
        return nil, fmt.Errorf("göndərmə xətası: %w", err)
    }

    if !c.scanner.Scan() {
        return nil, fmt.Errorf("cavab oxumaq mümkün deyil")
    }

    var resp map[string]interface{}
    if err := json.Unmarshal(c.scanner.Bytes(), &resp); err != nil {
        return nil, fmt.Errorf("JSON parse xətası: %w", err)
    }

    return resp, nil
}

func (c *TCPClient) Close() error {
    return c.conn.Close()
}

func main() {
    client, err := NewTCPClient("localhost:9091")
    if err != nil {
        log.Fatal("Qoşulma xətası:", err)
    }
    defer client.Close()

    // SET
    resp, _ := client.Send(map[string]interface{}{
        "cmd": "set",
        "params": map[string]string{
            "key":   "language",
            "value": "Go",
        },
    })
    fmt.Println("SET:", resp)

    // GET
    resp, _ = client.Send(map[string]interface{}{
        "cmd":    "get",
        "params": map[string]string{"key": "language"},
    })
    fmt.Println("GET:", resp)

    // PING
    resp, _ = client.Send(map[string]interface{}{"cmd": "ping"})
    fmt.Println("PING:", resp)
}
```

### Nümunə 4: Connection Limit ilə Server

```go
package main

import (
    "log"
    "net"
    "sync/atomic"
)

const maxConnections = 1000

var activeConns int64

func handleConn(conn net.Conn, sem chan struct{}) {
    defer func() {
        conn.Close()
        <-sem // semaphore-u azalt
        atomic.AddInt64(&activeConns, -1)
        log.Printf("Bağlantı bağlandı. Aktiv: %d", atomic.LoadInt64(&activeConns))
    }()

    atomic.AddInt64(&activeConns, 1)
    log.Printf("Yeni bağlantı: %s. Aktiv: %d",
        conn.RemoteAddr(), atomic.LoadInt64(&activeConns))

    // ... connection-ı idarə et
    conn.Write([]byte("OK\n"))
}

func main() {
    semaphore := make(chan struct{}, maxConnections)

    ln, _ := net.Listen("tcp", ":9092")
    defer ln.Close()

    log.Printf("Server :9092 (max %d bağlantı)", maxConnections)

    for {
        conn, err := ln.Accept()
        if err != nil {
            log.Println("Accept:", err)
            continue
        }

        // Limit yoxla — non-blocking
        select {
        case semaphore <- struct{}{}:
            go handleConn(conn, semaphore)
        default:
            // Limit dolub — bağlantını rədd et
            conn.Write([]byte("ERROR: server busy\n"))
            conn.Close()
            log.Println("Bağlantı rədd edildi: limit dolub")
        }
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Chat Server:**
Multi-room TCP chat server yazın. `/join <room>`, `/leave`, `/list` komandalarını implement edin. Hər room-da `broadcast(msg)` funksiyası olsun.

**Tapşırıq 2 — Custom Protocol:**
Öz binary protokolunu yazın: ilk 4 byte — message uzunluğu (uint32), sonrakılar — payload. Frame-based oxuma/yazma implement edin.

**Tapşırıq 3 — TCP Proxy:**
İki port arasında transparent proxy: `client → :8080 → :9090 (real server)`. Bütün trafiki log edin.

**Tapşırıq 4 — Health Check:**
Server-ə `HEALTH` komandası göndərilsə `OK\n` qaytarsın. Load balancer health check-i simulyasiya edin.

**Tapşırıq 5 — Reconnect:**
Avtomatik reconnect edən TCP client yazın. Bağlantı kəsilsə exponential backoff ilə yenidən qoşulsun (1s, 2s, 4s, max 30s).

## Əlaqəli Mövzular

- [27-goroutines-and-channels](27-goroutines-and-channels.md) — Goroutine əsasları
- [28-context](28-context.md) — Context ilə connection idarəsi
- [33-http-server](33-http-server.md) — HTTP server (TCP üzərindən)
- [53-graceful-shutdown](53-graceful-shutdown.md) — Server-in düzgün bağlanması
- [61-websocket](61-websocket.md) — WebSocket (TCP upgrade)
- [67-grpc](67-grpc.md) — gRPC (HTTP/2 üzərindən)
