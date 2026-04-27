# Go (Golang) Proqramlaşdırma Dili

PHP/Laravel developer üçün Go dilini sıfırdan professional arxitektura səviyyəsinə qədər öyrənmə yolu. Hər mövzu real layihə təcrübəsindən gələn praktik biliklər, trade-off analizi və kod nümunələri ilə çatdırılır.

**Hədəf auditoriya:** 5+ il PHP/Laravel təcrübəsi olan developer, Go-ya keçid etmək istəyir  
**Ümumi mövzu sayı:** 110 mövzu + 8 praktiki layihə  
**Proqramlaşdırma dili:** Go (Golang), kod nümunələri `go` bloklarında

---

## Folder Strukturu

| Folder | Mövzu sayı | Səviyyə | Qısa Məzmun |
|--------|-----------|---------|-------------|
| [`core/`](./core/) | 36 | ⭐ Junior → ⭐⭐⭐ Senior | Dil əsasları, interface, error, concurrency, dil dərinliyi |
| [`backend/`](./backend/) | 33 | ⭐⭐ Middle → ⭐⭐⭐ Senior | HTTP, database, API, production patterns |
| [`advanced/`](./advanced/) | 41 | ⭐⭐⭐ Senior → ⭐⭐⭐⭐⭐ Architect | Patterns, security, DDD, CQRS, Saga, K8s, CI/CD, architecture |
| [`examples/`](./examples/) | 8 layihə | ⭐ → ⭐⭐⭐⭐ | İşlək mini layihələr (stdlib only) |

---

## Öyrənmə Yolları

### 🟢 PHP Developer → Go Başlanğıc (4 həftə)
```
core/01-15   → Go sintaksisi və PHP müqayisəsi
core/16-26   → Go idiomları (interface, error, test)
backend/01-06 → HTTP server + database
examples/02  → REST API mini layihəsi
```

### 🔵 Tam Öyrənmə Yolu (3-4 ay)
```
core/01-32 → backend/01-36 → advanced/01-27
```

### 🟡 Backend API Developer (6-8 həftə)
```
core/01-28    → Əsaslar + goroutine giriş
backend/01-23 → HTTP, database, project structure
backend/24-36 → Production patterns
examples/02, 05, 07 → REST API, URL shortener, scraper
```

### 🔴 Senior/Lead Fokus
```
core/27-32        → Advanced concurrency basics
advanced/01-12    → Mutex, patterns, gRPC, security
advanced/13-20    → Resilience, testing, GraphQL
advanced/21-27    → Architecture, observability, microservices
advanced/28-37    → DDD, CQRS, Event Sourcing, Saga, Outbox, API Gateway
advanced/38-41    → Kubernetes, CI/CD, Multi-tenancy, ADR
```

---

## Səviyyə Legendi

| Nişan | Səviyyə | Hədəf |
|-------|---------|-------|
| ⭐ | Junior | Go ilk dəfə öyrənən |
| ⭐⭐ | Middle | İstehsalata hazır kod yaza bilir |
| ⭐⭐⭐ | Senior | Mövzunu dərindən başa düşür |
| ⭐⭐⭐⭐ | Lead | Arxitektura qərarları verir |
| ⭐⭐⭐⭐⭐ | Architect | Sistem dizaynı, infra |

---

## PHP/Laravel → Go Əsas Fərqlər

| PHP/Laravel | Go |
|-------------|-----|
| `throw` / `try/catch` | `error` return dəyəri, `errors.Is/As` |
| Abstract class, Interface (explicit) | Yalnız interface (implicit — duck typing) |
| Composer + `composer.json` | `go mod` (built-in) |
| PHP-FPM (hər request yeni proses) | Tək proses, goroutine ilə built-in concurrency |
| Eloquent ORM | GORM / sqlx / raw `database/sql` |
| `.env` + Laravel Config | `os.Getenv`, Viper |
| Laravel Queue + Horizon | asynq + goroutine pool |
| Artisan CLI | `cobra` / `flag` |
| Namespace | Package |
| `null` | nil pointer, zero value |
| Laravel DI Container | Manual DI, Wire, Fx |
| Guzzle retry middleware | backoff + gobreaker |
| rebing/graphql-laravel | gqlgen (schema-first) |
| SSE (`ob_flush`) | `net/http` + `http.Flusher` |

---

## Praktiki Layihələr — examples/

[`examples/`](./examples/) qovluğunda **8 işlək mini layihə** var — hər biri müstəqildir, `go run main.go` ilə işə salınır, external dependency yoxdur:

| # | Layihə | Növ | Əsas Konsept |
|---|--------|-----|-------------|
| [01](./examples/01-cli-task-manager/) | CLI Task Manager | Console | `os.Args`, JSON, file I/O |
| [02](./examples/02-rest-api/) | REST API | HTTP Server | `net/http`, middleware, mutex |
| [03](./examples/03-word-counter/) | Word Counter | Concurrency | Worker pool, channels |
| [04](./examples/04-tcp-chat/) | TCP Chat | TCP Server | `net.Listener`, hub broadcast |
| [05](./examples/05-url-shortener/) | URL Shortener | HTTP Server | Handlers, redirects |
| [06](./examples/06-file-organizer/) | File Organizer | CLI Tool | `filepath`, `os`, flags |
| [07](./examples/07-web-scraper/) | Web Scraper | HTTP Client | Semaphore, regex |
| [08](./examples/08-mini-cache/) | Mini Cache | TCP Server | Custom protokol, TTL |

---

## Əlavə Resurslar

- **Rəsmi sənəd:** https://go.dev/doc/
- **Go Tour:** https://go.dev/tour/
- **Effective Go:** https://go.dev/doc/effective_go
- **Go Playground:** https://go.dev/play/
- **pkg.go.dev:** https://pkg.go.dev/
- **Awesome Go:** https://awesome-go.com/
