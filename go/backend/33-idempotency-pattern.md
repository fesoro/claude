# Idempotency Pattern (Senior)

## İcmal

Idempotency — eyni əməliyyatı bir neçə dəfə çağırmağın nəticəsi ilk dəfə çağırmaqla eyni olan xüsusiyyətdir. Go HTTP API-lərində idempotency `Idempotency-Key` header vasitəsilə POST endpoint-lərinə tətbiq edilir. Client timeout olub sorğunu təkrar göndərdikdə, server əməliyyatı bir daha icra etmir — əvvəlki cavabı qaytarır.

## Niyə Vacibdir

Şəbəkə etibarsızdır:
- Client sorğu göndərir, cavab gəlmir (timeout)
- Client bilmir: server qəbul etdi, amma cavab itdi? Yoxsa server heç almadı?
- Client sorğunu təkrar göndərir
- **Problem**: server ikinci dəfə işləndirsə — iki ödəniş, iki sifariş, iki email

**Real nümunə**: İstifadəçi "Ödə" düyməsinə basır. Network 30 saniyə donur. Browser timeout verir. İstifadəçi yenidən basır. Kartından iki dəfə pul çəkilir.

HTTP metodların natural idempotency-si:
- `GET`, `HEAD`, `OPTIONS` — natural olaraq idempotent
- `PUT`, `DELETE` — natural olaraq idempotent (eyni nəticə)
- `POST` — **DEYİL** — hər dəfə yeni resurs yarada bilər

## Əsas Anlayışlar

- **Idempotency-Key**: client tərəfindən yaradılan unikal UUID — sorğunun "imzası"
- **Key storage**: server bu key → response cütünü saxlayır (Redis)
- **TTL**: key 24-48 saat saxlanır, sonra silinir
- **Replay header**: təkrarlanan sorğuya `X-Idempotent-Replayed: true` qaytarılır
- **Atomic check**: eyni key ilə eyni anda iki sorğu gəlsə — yalnız biri işlənməlidir (Redis SET NX)
- **Scope**: idempotency-key client-specific olmalıdır — başqa client eyni key göndərə bilər

## Praktik Baxış

### Redis schema

```
Key:   idempotency:{idempotency_key}
Value: JSON { "status": 201, "body": "...", "created_at": "..." }
TTL:   24 saat (86400 saniyə)
```

### Flow

```
Client → POST /payments  (Idempotency-Key: uuid-1)
  ↓
Middleware:
  1. Redis-də `idempotency:uuid-1` varmı?
     Var → cached cavabı qaytar + X-Idempotent-Replayed: true
     Yox → davam et

  2. Redis SET NX `idempotency:uuid-1` = "processing" (10s TTL)
     Fail (başqası artıq işləyir) → 409 Conflict qaytar

  3. Handler-ı çağır → cavab al
  4. Redis-ə tam cavabı yaz (24h TTL)
  5. Cavabı client-ə göndər
```

### Trade-offs

**Müsbət:**
- Client asanlıqla retry edə bilər — data safety
- Server tərəfindən duplicate protection

**Mənfi:**
- Redis dependency — əgər Redis düşsə, idempotency check keçilir (soft-fail etmək olar)
- Response body-ni Redis-də saxlamaq — böyük response-lar üçün memory məsələsi
- Latency: hər sorğuda Redis-ə əlavə 1-2ms

## Nümunələr

### Ümumi Nümunə

Laravel-də `php artisan queue:work` — job fail olsa retry edir. Ödəniş job-u idempotent olmasa, retry zamanı iki dəfə ödəniş alınar. `idempotency_key` column-u unique index ilə DB-də saxlamaq — klassik həll.

### Kod Nümunəsi

**Redis ilə tam middleware implementasiyası:**

```go
// middleware/idempotency.go
package middleware

import (
    "bytes"
    "context"
    "encoding/json"
    "fmt"
    "io"
    "log"
    "net/http"
    "strings"
    "time"

    "github.com/redis/go-redis/v9"
)

const (
    idempotencyKeyHeader  = "Idempotency-Key"
    replayedHeader        = "X-Idempotent-Replayed"
    processingTTL         = 10 * time.Second  // "işlənir" flag-ı
    storageTTL            = 24 * time.Hour    // tam cavab
)

type cachedResponse struct {
    StatusCode int               `json:"status_code"`
    Headers    map[string]string `json:"headers"`
    Body       []byte            `json:"body"`
    CreatedAt  time.Time         `json:"created_at"`
}

// responseRecorder — handler-ın cavabını tutmaq üçün
type responseRecorder struct {
    http.ResponseWriter
    statusCode int
    body       bytes.Buffer
    headers    http.Header
}

func newResponseRecorder(w http.ResponseWriter) *responseRecorder {
    return &responseRecorder{
        ResponseWriter: w,
        statusCode:     http.StatusOK,
        headers:        make(http.Header),
    }
}

func (r *responseRecorder) WriteHeader(code int) {
    r.statusCode = code
    r.ResponseWriter.WriteHeader(code)
}

func (r *responseRecorder) Write(b []byte) (int, error) {
    r.body.Write(b)
    return r.ResponseWriter.Write(b)
}

// IdempotencyMiddleware — POST sorğularına idempotency tətbiq edir
func IdempotencyMiddleware(rdb *redis.Client) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            // Yalnız POST-a tətbiq et
            if r.Method != http.MethodPost {
                next.ServeHTTP(w, r)
                return
            }

            idempotencyKey := r.Header.Get(idempotencyKeyHeader)
            if idempotencyKey == "" {
                // Stripe kimi strict davranış: key olmadan rədd et
                http.Error(w, "Idempotency-Key header required", http.StatusBadRequest)
                return
            }

            // Key sanitize et
            idempotencyKey = strings.TrimSpace(idempotencyKey)
            if len(idempotencyKey) > 255 {
                http.Error(w, "Idempotency-Key too long", http.StatusBadRequest)
                return
            }

            redisKey := fmt.Sprintf("idempotency:%s", idempotencyKey)
            processingKey := fmt.Sprintf("idempotency:processing:%s", idempotencyKey)
            ctx := r.Context()

            // 1. Mövcud cavabı yoxla
            if cached, err := getCachedResponse(ctx, rdb, redisKey); err == nil {
                // Cavab tapıldı — replay et
                for k, v := range cached.Headers {
                    w.Header().Set(k, v)
                }
                w.Header().Set(replayedHeader, "true")
                w.Header().Set("Content-Type", "application/json")
                w.WriteHeader(cached.StatusCode)
                w.Write(cached.Body)
                return
            }

            // 2. Atomic SET NX — eyni anda iki sorğu olmasın
            set, err := rdb.SetNX(ctx, processingKey, "1", processingTTL).Result()
            if err != nil {
                // Redis xətası — idempotency skip et, request-i keç
                log.Printf("idempotency redis error: %v", err)
                next.ServeHTTP(w, r)
                return
            }
            if !set {
                // Başqası işləyir — conflict
                http.Error(w,
                    `{"error":"concurrent request with same Idempotency-Key"}`,
                    http.StatusConflict,
                )
                return
            }
            defer rdb.Del(ctx, processingKey)

            // 3. Handler-ı çağır, cavabı tut
            rec := newResponseRecorder(w)
            next.ServeHTTP(rec, r)

            // 4. Uğurlu sorğuları saxla (2xx və 4xx — amma 5xx deyil)
            if rec.statusCode < 500 {
                response := cachedResponse{
                    StatusCode: rec.statusCode,
                    Body:       rec.body.Bytes(),
                    CreatedAt:  time.Now(),
                    Headers:    extractHeaders(rec.Header()),
                }
                if err := storeCachedResponse(ctx, rdb, redisKey, response, storageTTL); err != nil {
                    log.Printf("idempotency store error: %v", err)
                }
            }
        })
    }
}

func getCachedResponse(ctx context.Context, rdb *redis.Client, key string) (*cachedResponse, error) {
    data, err := rdb.Get(ctx, key).Bytes()
    if err != nil {
        return nil, err
    }

    var cached cachedResponse
    if err := json.Unmarshal(data, &cached); err != nil {
        return nil, err
    }
    return &cached, nil
}

func storeCachedResponse(ctx context.Context, rdb *redis.Client, key string, resp cachedResponse, ttl time.Duration) error {
    data, err := json.Marshal(resp)
    if err != nil {
        return err
    }
    return rdb.Set(ctx, key, data, ttl).Err()
}

func extractHeaders(h http.Header) map[string]string {
    result := make(map[string]string)
    for _, key := range []string{"Content-Type", "X-Request-ID"} {
        if v := h.Get(key); v != "" {
            result[key] = v
        }
    }
    return result
}
```

**Handler:**

```go
// handler/payment.go
package handler

import (
    "encoding/json"
    "net/http"
)

type PaymentRequest struct {
    Amount   float64 `json:"amount"`
    Currency string  `json:"currency"`
    CardID   string  `json:"card_id"`
}

type PaymentResponse struct {
    PaymentID string  `json:"payment_id"`
    Amount    float64 `json:"amount"`
    Status    string  `json:"status"`
}

func (h *Handler) CreatePayment(w http.ResponseWriter, r *http.Request) {
    var req PaymentRequest
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        http.Error(w, "invalid request body", http.StatusBadRequest)
        return
    }

    // Bu handler yalnız bir dəfə çağırılacaq — idempotency middleware təmin edir
    paymentID, err := h.paymentService.Charge(r.Context(), req.CardID, req.Amount, req.Currency)
    if err != nil {
        http.Error(w, `{"error":"payment failed"}`, http.StatusUnprocessableEntity)
        return
    }

    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(http.StatusCreated)
    json.NewEncoder(w).Encode(PaymentResponse{
        PaymentID: paymentID,
        Amount:    req.Amount,
        Status:    "success",
    })
}
```

**Router quraşdırması:**

```go
// main.go
func main() {
    rdb := redis.NewClient(&redis.Options{
        Addr: os.Getenv("REDIS_ADDR"),
    })

    h := handler.New(paymentService)

    mux := http.NewServeMux()
    mux.HandleFunc("POST /api/v1/payments", h.CreatePayment)
    mux.HandleFunc("POST /api/v1/orders", h.CreateOrder)

    // Idempotency middleware tətbiq et
    idempotency := middleware.IdempotencyMiddleware(rdb)
    handler := idempotency(mux)

    http.ListenAndServe(":8080", handler)
}
```

**Database-level idempotency (əlavə qoruma):**

```sql
-- Payments cədvəlində idempotency_key unique constraint
CREATE TABLE payments (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    idempotency_key  TEXT UNIQUE NOT NULL,  -- duplikat INSERT fail olur
    tenant_id        TEXT NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    currency         TEXT NOT NULL,
    status           TEXT NOT NULL DEFAULT 'pending',
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- İndeks — tez axtarmaq üçün
CREATE UNIQUE INDEX idx_payments_idempotency_key ON payments(idempotency_key);
```

```go
// Db-level idempotency: ON CONFLICT DO NOTHING
func (s *PaymentService) Charge(ctx context.Context, cardID string, amount float64, currency, idempotencyKey string) (string, error) {
    var paymentID string
    err := s.db.QueryRowContext(ctx, `
        INSERT INTO payments (idempotency_key, card_id, amount, currency, status)
        VALUES ($1, $2, $3, $4, 'pending')
        ON CONFLICT (idempotency_key) DO UPDATE
            SET idempotency_key = EXCLUDED.idempotency_key  -- no-op update
        RETURNING id
    `, idempotencyKey, cardID, amount, currency).Scan(&paymentID)

    return paymentID, err
}
```

**Client tərəfindən istifadə:**

```go
// client/payment_client.go
package client

import (
    "bytes"
    "encoding/json"
    "fmt"
    "net/http"

    "github.com/google/uuid"
)

type PaymentClient struct {
    baseURL    string
    httpClient *http.Client
}

func (c *PaymentClient) CreatePayment(amount float64, currency, cardID string) (*PaymentResponse, error) {
    // Client unikal key yaradır — retry-larda EYNİ key istifadə edir
    idempotencyKey := uuid.New().String()

    body, _ := json.Marshal(map[string]interface{}{
        "amount":   amount,
        "currency": currency,
        "card_id":  cardID,
    })

    // Retry loop
    for attempt := 0; attempt < 3; attempt++ {
        req, _ := http.NewRequest("POST", c.baseURL+"/api/v1/payments", bytes.NewReader(body))
        req.Header.Set("Content-Type", "application/json")
        req.Header.Set("Idempotency-Key", idempotencyKey) // HƏMIŞƏ eyni key

        resp, err := c.httpClient.Do(req)
        if err != nil {
            if attempt < 2 {
                continue // retry
            }
            return nil, fmt.Errorf("payment request failed: %w", err)
        }

        if resp.Header.Get("X-Idempotent-Replayed") == "true" {
            fmt.Println("cached response received — payment already processed")
        }

        var result PaymentResponse
        json.NewDecoder(resp.Body).Decode(&result)
        resp.Body.Close()
        return &result, nil
    }

    return nil, fmt.Errorf("max retries exceeded")
}
```

## Praktik Tapşırıqlar

**1. Test yaz — eyni key ilə iki sorğu:**

```go
func TestIdempotencyMiddleware(t *testing.T) {
    rdb := redis.NewClient(&redis.Options{Addr: "localhost:6379"})
    defer rdb.FlushDB(context.Background())

    callCount := 0
    handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        callCount++
        w.WriteHeader(http.StatusCreated)
        json.NewEncoder(w).Encode(map[string]string{"id": "payment-123"})
    })

    middleware := IdempotencyMiddleware(rdb)(handler)
    server := httptest.NewServer(middleware)
    defer server.Close()

    // İlk sorğu
    req1, _ := http.NewRequest("POST", server.URL+"/payments", strings.NewReader(`{}`))
    req1.Header.Set("Idempotency-Key", "test-key-001")
    resp1, _ := http.DefaultClient.Do(req1)

    // İkinci sorğu — eyni key
    req2, _ := http.NewRequest("POST", server.URL+"/payments", strings.NewReader(`{}`))
    req2.Header.Set("Idempotency-Key", "test-key-001")
    resp2, _ := http.DefaultClient.Do(req2)

    // Handler yalnız bir dəfə çağırılmalıdır
    if callCount != 1 {
        t.Errorf("handler called %d times, expected 1", callCount)
    }

    // İkinci sorğu replay header-ı saxlamalıdır
    if resp2.Header.Get("X-Idempotent-Replayed") != "true" {
        t.Error("expected X-Idempotent-Replayed: true")
    }

    // Hər iki sorğu 201 qaytarmalıdır
    if resp1.StatusCode != http.StatusCreated || resp2.StatusCode != http.StatusCreated {
        t.Errorf("expected 201, got %d and %d", resp1.StatusCode, resp2.StatusCode)
    }
}
```

**2. Webhook handler-ında idempotency:**

Üçüncü tərəf webhook göndərir, network problemi olduğunda retry edir. Webhook handler-ı idempotent olmalıdır:

```go
mux.HandleFunc("POST /webhooks/stripe", stripeWebhookHandler)
// Stripe öz `Stripe-Idempotency-Key` header-ı göndərir
// Onu `Idempotency-Key` ilə map et
```

**3. 5xx-i cache etmə:**

Server xətası baş verəndə (500, 503) — cavabı cache etmə. Client retry etsin, server bir daha cəhd etsin. Yalnız 2xx və 4xx cache olunur.

**Common mistakes:**

- `Idempotency-Key` header-ını server tərəfindən yaratmaq — client yaratmalıdır, hər retry-da eyni key istifadə etsin
- 5xx cavablarını cache etmək — server xətasını perpetuate etmiş olursun
- TTL çox qısa qoymaq — client 48 saatdan sonra retry edib fərqli nəticə alır
- Redis down olduqda request-i block etmək — soft-fail et (log yaz, davam et)
- Response body-ni limitləməmək — 100MB response Redis-də saxlanmamalıdır

## Əlaqəli Mövzular

- `15-rate-limiting.md` — duplicate request-lərdən qorunma
- `08-caching.md` — Redis istifadəsi
- `18-circuit-breaker-and-retry.md` — retry pattern
- `17-graceful-shutdown.md` — request lifecycle
- `29-background-jobs.md` — job idempotency
