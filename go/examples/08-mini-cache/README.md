# Mini Cache (⭐⭐⭐⭐ Lead)

Redis-ə bənzər TCP-based in-memory key-value cache. TTL dəstəyi, background GC, custom text protokol, goroutine-per-connection.

## Öyrənilən Konseptlər

- TCP server — `net.Listen`, `net.Accept`
- **Goroutine-per-connection** model
- Custom text protokol parsing (`strings.Fields`)
- TTL ilə expired key-lərin **background GC** (`time.Ticker`)
- `sync.RWMutex` — concurrent read / exclusive write
- Buffered `bufio.Scanner` ilə line-based protokol

## Dəstəklənən Commands

```
SET key value [EX seconds]   — dəyər saxla (optional TTL)
GET key                      — dəyər gətir
DEL key [key2 ...]           — sil (neçə silindi qaytarır)
KEYS                         — bütün aktiv key-lər
TTL key                      — neçə saniyə qalıb
                               -1 = TTL yoxdur, -2 = key yoxdur/expiry keçib
FLUSHALL                     — hər şeyi sil
INFO                         — key sayı
QUIT / EXIT                  — bağlan
```

## İşə Salma

```bash
# Server-i başlat
go run main.go

# Client olaraq qoş
nc localhost 6399
# və ya
telnet localhost 6399
```

## Demo Session

```
mini-cache 1.0  |  SET GET DEL KEYS TTL FLUSHALL INFO QUIT
SET name orkhan
OK
SET token abc123 EX 30
OK
GET name
"orkhan"
TTL token
27
KEYS
1) "name"
2) "token"
DEL name
(integer) 1
GET name
(nil)
INFO
keys: 1
QUIT
BYE
```

## TTL Arxitekturası

```
set() → entry{value, expiresAt, hasTTL}
get() → check time.Now() > expiresAt → (nil) if expired
gc()  → background ticker, 5s interval → delete expired keys
```

## İrəli Getmək Üçün

- RESP protokolu (real Redis client uyğunluğu)
- Persistence — AOF (append-only file)
- Pub/Sub — `SUBSCRIBE channel`, `PUBLISH channel msg`
- Hash tipi — `HSET`, `HGET`, `HGETALL`
- LRU eviction (max memory limit)
