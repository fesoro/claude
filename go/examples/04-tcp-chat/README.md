# TCP Chat Server (⭐⭐⭐ Senior)

Real-time çox istifadəçili chat server. Raw TCP üzərindən işləyir. **Hub pattern** ilə bütün client-lərə broadcast.

## Öyrənilən Konseptlər

- `net.Listen` / `net.Accept` ilə TCP server
- Hər client üçün ayrı goroutine (`go handleConn`)
- **Hub pattern**: mərkəzi goroutine event-ləri idarə edir
- `chan *client` ilə join/leave events
- Buffered send channel — yavaş client-ləri bloklamır
- `sync.RWMutex` ilə clients map-i

## İşə Salma

```bash
# Server-i başlat
go run main.go

# Ayrı terminal-lərdə client qoş:
nc localhost 8888
# və ya
telnet localhost 8888
```

## Demo

```
Terminal 1 (server):
  2024/01/15 TCP Chat Server running on :8888

Terminal 2:
  Enter your name: Alice
  Welcome, Alice! Type a message or /quit to exit.
  *** Bob joined the chat ***
  [Bob]: salam!
  Salam Bob!

Terminal 3:
  Enter your name: Bob
  Welcome, Bob!
  [Alice]: Salam Bob!
```

## Hub Arxitekturası

```
                    ┌─────────────────────┐
conn1 → handleConn ─┤                     │
conn2 → handleConn ─┤   hub.run()         ├─ broadcast → all clients
conn3 → handleConn ─┤  (single goroutine) │
                    └─────────────────────┘
         join/leave/message via channels
```

## İrəli Getmək Üçün

- `/nick newname` — ad dəyişdirmə
- `/whisper user msg` — özəl mesaj
- Chat room-lar (map[string]*hub)
- WebSocket-ə upgrade (`gorilla/websocket`)
