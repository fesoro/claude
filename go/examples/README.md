# Go Examples — Praktiki Mini Layihələr

Müxtəlif Go konseptlərini real ssenari üzərindən göstərən **8 müstəqil, işlək mini layihə**. Hər layihə ayrı folder-dədir, external dependency yoxdur.

## Layihələr

| # | Layihə | Növ | Səviyyə | Əsas Konseptlər |
|---|--------|-----|---------|-----------------|
| 01 | [CLI Task Manager](./01-cli-task-manager/) | Console App | ⭐ Junior | `os.Args`, JSON, file I/O |
| 02 | [REST API](./02-rest-api/) | HTTP Server | ⭐⭐ Middle | `net/http`, middleware, `sync.RWMutex` |
| 03 | [Word Counter](./03-word-counter/) | Concurrency | ⭐⭐ Middle | worker pool, channels, `WaitGroup` |
| 04 | [TCP Chat](./04-tcp-chat/) | TCP Server | ⭐⭐⭐ Senior | `net.Listener`, hub pattern, broadcast |
| 05 | [URL Shortener](./05-url-shortener/) | HTTP Server | ⭐⭐ Middle | HTTP handlers, redirects, mutex |
| 06 | [File Organizer](./06-file-organizer/) | CLI Tool | ⭐ Junior | `filepath`, `os`, `flag` |
| 07 | [Web Scraper](./07-web-scraper/) | HTTP Client | ⭐⭐⭐ Senior | `http.Client`, regex, semaphore |
| 08 | [Mini Cache](./08-mini-cache/) | TCP Server | ⭐⭐⭐⭐ Lead | custom protokol, TTL, goroutines |

## Tələblər

- Go 1.21+
- External dependency **yoxdur** — yalnız standart kitabxana (`stdlib`)

## Sürətli Başlanğıc

```bash
cd go/examples/01-cli-task-manager
go run main.go add "First task"
go run main.go list
```

## Tövsiyə Olunan Oxuma Sırası

**Yeni başlayanlar:** `01` → `06` → `03` → `02` → `05`

**Concurrency fokus:** `03` → `04` → `07` → `08`

**Backend API developer:** `02` → `05` → `07` → `08`
