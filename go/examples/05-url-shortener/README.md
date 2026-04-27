# URL Shortener (⭐⭐ Middle)

In-memory URL qısaldıcı. Qısa kod yaradır, redirect edir, click statistikasını saxlayır.

## Öyrənilən Konseptlər

- `net/http` handler funksiyaları
- `sync.RWMutex` ilə concurrent map (yazma zamanı exclusive lock)
- `http.Redirect` ilə 307 redirect
- Random string generation (`math/rand`)
- JSON request/response, status codes

## Endpoints

| Method | Path | Təsvir |
|--------|------|--------|
| GET | / | Usage info |
| POST | /shorten | URL qısal |
| GET | /{code} | Redirect to original |
| GET | /stats | Bütün URL-lər + click count |

## İşə Salma

```bash
go run main.go
# → http://localhost:8080
```

## İstifadə Nümunəsi

```bash
# URL qısal
curl -X POST http://localhost:8080/shorten \
  -H "Content-Type: application/json" \
  -d '{"url": "https://go.dev/doc/effective_go"}'

# Response:
# {"code":"aB3x9K","short_url":"http://localhost:8080/aB3x9K","original":"..."}

# Redirect (browser açar)
curl -L http://localhost:8080/aB3x9K

# Statistika
curl http://localhost:8080/stats
```

## İrəli Getmək Üçün

- Custom alias: `{"url":"...","code":"mylink"}`
- Expiration (TTL) — `{"url":"...","expires_in":3600}`
- Redis ilə persistent storage
- Rate limiting per IP
- QR code generation
