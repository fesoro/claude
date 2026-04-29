# Webhook (Senior)

## İcmal

Webhook — bir sistemin hadisə baş verdikdə digər sistemə HTTP POST ilə bildiriş göndərməsidir. Go-da iki tərəf var: **webhook qəbul etmək** (Stripe, GitHub, Mailgun sizi çağırır) və **webhook göndərmək** (siz client-ləri çağırırsınız).

## Niyə Vacibdir

- Ödəniş sistemləri (Stripe, PayPal) hər ödəniş hadisəsini webhook ilə bildirir
- CI/CD: GitHub push → deploy pipeline başlayır
- Öz platformanı qursanız — client-lərə webhook göndərməli olacaqsınız
- Polling əvəzinə push model — daha səmərəli

## Əsas Anlayışlar

**Webhook qəbul etmək:**
- Endpoint yazırsınız → provider URL-i kaydınıza alır
- Hadisə olduqda POST göndərir → siz 200 qaytarırsınız
- Ağır işi background-da et — 200 tez qaytarılmalıdır

**İmza yoxlama (signature verification):**
- Provider body-ni HMAC-SHA256 ilə imzalayır
- Siz eyni sirri bilirsiniz → imzanı yoxlayırsınız
- Bu olmadan hər kəs fake webhook göndərə bilər

**Idempotency:**
- Provider uğursuzluqda webhook-u yenidən göndərir
- Eyni webhook iki dəfə işlənməməlidir → `webhook_id` saxla

**Webhook göndərmək:**
- Retry məntiqi — alıcı offline ola bilər
- Exponential backoff
- DLQ (dead-letter queue) — çox uğursuz cəhddən sonra

## Praktik Baxış

**Nə vaxt webhook:**
- Real-time bildiriş lazımdır (polling çox tez-tez sorğu edir)
- Üçüncü tərəf sistemlərlə inteqrasiya
- Event-driven arxitektura

**Common mistakes:**
- İmzanı yoxlamamaq — güvənlik açığı
- Senkron ağır iş — provider timeout edir, yenidən göndərir, duplikat problem
- Retry olmadan göndərmək — alıcı offline olduqda məlumat itirilir
- Webhook ID-ni saxlamamaq — duplikat emal

## Nümunələr

### Nümunə 1: Stripe webhook qəbul etmək

```go
package main

import (
    "encoding/json"
    "io"
    "log/slog"
    "net/http"

    "github.com/stripe/stripe-go/v76/webhook"
)

// go get github.com/stripe/stripe-go/v76

const stripeWebhookSecret = "whsec_..."

type PaymentEventHandler struct {
    orderService OrderService
    logger       *slog.Logger
}

func (h *PaymentEventHandler) Handle(w http.ResponseWriter, r *http.Request) {
    // Body-ni oxu — maksimum 65KB
    body, err := io.ReadAll(io.LimitReader(r.Body, 65536))
    if err != nil {
        http.Error(w, "Body oxunmadı", http.StatusBadRequest)
        return
    }

    // Stripe imzasını yoxla — bu olmadan fake webhook mümkündür
    sig := r.Header.Get("Stripe-Signature")
    event, err := webhook.ConstructEvent(body, sig, stripeWebhookSecret)
    if err != nil {
        h.logger.Warn("Yanlış Stripe imzası", slog.String("error", err.Error()))
        http.Error(w, "Yanlış imza", http.StatusUnauthorized)
        return
    }

    // 200 qaytarılır — ağır iş background-da
    w.WriteHeader(http.StatusOK)

    // Asinxron emal
    go h.processEvent(event)
}

func (h *PaymentEventHandler) processEvent(event stripe.Event) {
    switch event.Type {
    case "payment_intent.succeeded":
        var pi stripe.PaymentIntent
        if err := json.Unmarshal(event.Data.Raw, &pi); err != nil {
            h.logger.Error("PaymentIntent parse xətası", slog.String("error", err.Error()))
            return
        }
        h.logger.Info("Ödəniş uğurlu",
            slog.String("id", pi.ID),
            slog.Int64("amount", pi.Amount),
        )
        // Sifarişi tamamla
        orderID := pi.Metadata["order_id"]
        h.orderService.MarkPaid(context.Background(), orderID)

    case "payment_intent.payment_failed":
        h.logger.Warn("Ödəniş uğursuz", slog.String("event_id", event.ID))

    case "customer.subscription.deleted":
        h.logger.Info("Abunəlik ləğv edildi", slog.String("event_id", event.ID))
    }
}
```

### Nümunə 2: GitHub webhook — push eventini qəbul et

```go
package main

import (
    "crypto/hmac"
    "crypto/sha256"
    "encoding/hex"
    "encoding/json"
    "io"
    "log/slog"
    "net/http"
    "strings"
)

const githubSecret = "your-webhook-secret"

type GitHubPushEvent struct {
    Ref        string `json:"ref"`
    Repository struct {
        FullName string `json:"full_name"`
    } `json:"repository"`
    HeadCommit struct {
        ID      string `json:"id"`
        Message string `json:"message"`
    } `json:"head_commit"`
}

func verifyGitHubSignature(body []byte, signature string) bool {
    mac := hmac.New(sha256.New, []byte(githubSecret))
    mac.Write(body)
    expected := "sha256=" + hex.EncodeToString(mac.Sum(nil))
    return hmac.Equal([]byte(expected), []byte(signature))
}

func GitHubWebhookHandler(w http.ResponseWriter, r *http.Request) {
    body, err := io.ReadAll(io.LimitReader(r.Body, 1<<20)) // 1MB limit
    if err != nil {
        http.Error(w, "Body oxunmadı", http.StatusBadRequest)
        return
    }

    // İmza yoxla
    sig := r.Header.Get("X-Hub-Signature-256")
    if !verifyGitHubSignature(body, sig) {
        http.Error(w, "Yanlış imza", http.StatusUnauthorized)
        return
    }

    eventType := r.Header.Get("X-GitHub-Event")
    deliveryID := r.Header.Get("X-GitHub-Delivery")

    slog.Info("GitHub webhook alındı",
        slog.String("event", eventType),
        slog.String("delivery", deliveryID),
    )

    // Dərhal 200 qaytar
    w.WriteHeader(http.StatusOK)

    if eventType == "push" {
        var event GitHubPushEvent
        if err := json.Unmarshal(body, &event); err != nil {
            slog.Error("Parse xətası", slog.String("error", err.Error()))
            return
        }

        // main branch-ə push → deploy
        if event.Ref == "refs/heads/main" {
            go triggerDeploy(event.Repository.FullName, event.HeadCommit.ID)
        }
    }
}

func triggerDeploy(repo, commit string) {
    slog.Info("Deploy başladı",
        slog.String("repo", repo),
        slog.String("commit", commit),
    )
    // CI/CD pipeline başlat
}
```

### Nümunə 3: Webhook göndərmək — retry ilə

```go
package main

import (
    "bytes"
    "context"
    "crypto/hmac"
    "crypto/sha256"
    "encoding/hex"
    "encoding/json"
    "fmt"
    "log/slog"
    "net/http"
    "time"
)

type WebhookSender struct {
    client *http.Client
    secret string
    logger *slog.Logger
}

func NewWebhookSender(secret string) *WebhookSender {
    return &WebhookSender{
        client: &http.Client{Timeout: 10 * time.Second},
        secret: secret,
        logger: slog.Default(),
    }
}

type WebhookPayload struct {
    ID        string          `json:"id"`
    Event     string          `json:"event"`
    CreatedAt time.Time       `json:"created_at"`
    Data      json.RawMessage `json:"data"`
}

func (s *WebhookSender) sign(body []byte) string {
    mac := hmac.New(sha256.New, []byte(s.secret))
    mac.Write(body)
    return "sha256=" + hex.EncodeToString(mac.Sum(nil))
}

// Exponential backoff ilə retry
func (s *WebhookSender) Send(ctx context.Context, url string, payload WebhookPayload) error {
    body, err := json.Marshal(payload)
    if err != nil {
        return fmt.Errorf("marshal: %w", err)
    }

    maxRetries := 5
    backoff := time.Second

    for attempt := 1; attempt <= maxRetries; attempt++ {
        err := s.sendOnce(ctx, url, body)
        if err == nil {
            s.logger.Info("Webhook göndərildi",
                slog.String("url", url),
                slog.String("event", payload.Event),
                slog.Int("attempt", attempt),
            )
            return nil
        }

        if attempt == maxRetries {
            return fmt.Errorf("webhook %d cəhddən sonra uğursuz: %w", maxRetries, err)
        }

        s.logger.Warn("Webhook uğursuz, yenidən cəhd",
            slog.String("url", url),
            slog.Int("attempt", attempt),
            slog.Duration("next_retry", backoff),
            slog.String("error", err.Error()),
        )

        select {
        case <-ctx.Done():
            return ctx.Err()
        case <-time.After(backoff):
        }

        backoff *= 2 // 1s → 2s → 4s → 8s → 16s
        if backoff > time.Minute {
            backoff = time.Minute
        }
    }

    return nil
}

func (s *WebhookSender) sendOnce(ctx context.Context, url string, body []byte) error {
    req, err := http.NewRequestWithContext(ctx, http.MethodPost, url, bytes.NewReader(body))
    if err != nil {
        return err
    }

    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("X-Webhook-Signature", s.sign(body))
    req.Header.Set("X-Webhook-Timestamp", fmt.Sprintf("%d", time.Now().Unix()))

    resp, err := s.client.Do(req)
    if err != nil {
        return err
    }
    defer resp.Body.Close()

    if resp.StatusCode < 200 || resp.StatusCode >= 300 {
        return fmt.Errorf("HTTP %d", resp.StatusCode)
    }

    return nil
}
```

### Nümunə 4: Idempotency — eyni webhook iki dəfə işlənməsin

```go
package main

import (
    "context"
    "sync"
)

// In-memory (production-da Redis istifadə et)
type ProcessedWebhooks struct {
    mu   sync.RWMutex
    seen map[string]bool
}

func NewProcessedWebhooks() *ProcessedWebhooks {
    return &ProcessedWebhooks{seen: make(map[string]bool)}
}

func (p *ProcessedWebhooks) MarkSeen(id string) bool {
    p.mu.Lock()
    defer p.mu.Unlock()
    if p.seen[id] {
        return false // artıq işlənib
    }
    p.seen[id] = true
    return true // yeni
}

// Handler-də istifadə
type IdempotentWebhookHandler struct {
    processed *ProcessedWebhooks
    inner     http.Handler
}

func (h *IdempotentWebhookHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
    deliveryID := r.Header.Get("X-GitHub-Delivery") // və ya Stripe event ID

    if deliveryID != "" && !h.processed.MarkSeen(deliveryID) {
        // Artıq işlənib — 200 qaytar (provider yenidən göndərməsin)
        slog.Info("Webhook artıq işlənib, keçilir", slog.String("id", deliveryID))
        w.WriteHeader(http.StatusOK)
        return
    }

    h.inner.ServeHTTP(w, r)
}
```

### Nümunə 5: Webhook endpoint-lərini test etmək

```go
package main

import (
    "bytes"
    "crypto/hmac"
    "crypto/sha256"
    "encoding/hex"
    "encoding/json"
    "net/http"
    "net/http/httptest"
    "testing"
)

func TestGitHubWebhook_ValidSignature(t *testing.T) {
    payload := map[string]string{"ref": "refs/heads/main"}
    body, _ := json.Marshal(payload)

    // Düzgün imza yarat
    mac := hmac.New(sha256.New, []byte(githubSecret))
    mac.Write(body)
    sig := "sha256=" + hex.EncodeToString(mac.Sum(nil))

    req := httptest.NewRequest(http.MethodPost, "/webhook/github", bytes.NewReader(body))
    req.Header.Set("X-Hub-Signature-256", sig)
    req.Header.Set("X-GitHub-Event", "push")

    w := httptest.NewRecorder()
    GitHubWebhookHandler(w, req)

    if w.Code != http.StatusOK {
        t.Errorf("gözlənilən 200, alınan: %d", w.Code)
    }
}

func TestGitHubWebhook_InvalidSignature(t *testing.T) {
    body := []byte(`{"ref":"refs/heads/main"}`)

    req := httptest.NewRequest(http.MethodPost, "/webhook/github", bytes.NewReader(body))
    req.Header.Set("X-Hub-Signature-256", "sha256=yanlış")

    w := httptest.NewRecorder()
    GitHubWebhookHandler(w, req)

    if w.Code != http.StatusUnauthorized {
        t.Errorf("gözlənilən 401, alınan: %d", w.Code)
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
GitHub webhook qəbul edən endpoint yaz. İmzanı yoxla. Push event-ə `main` branch-ə olduqda log yaz. `httptest` ilə test et.

**Tapşırıq 2:**
Webhook göndərən servis yaz: exponential backoff (1s→2s→4s→max 60s), 5 cəhd. Uğursuz webhook-ları DB-ə yaz (dead-letter queue).

**Tapşırıq 3:**
Idempotency: eyni `X-GitHub-Delivery` ID ilə iki dəfə gələn webhook-u yalnız bir dəfə işlə. Redis `SET NX` ilə implement et.

## PHP ilə Müqayisə

Laravel-də webhook qəbul etmək üçün route yazılır, imza yoxlama manual edilir. Go-da eyni axın, amma goroutine ilə asinxron emal daha sadədir.

```php
// Laravel — Stripe webhook qəbul etmək
Route::post('/webhook/stripe', function (Request $request) {
    $payload = $request->getContent();
    $sig = $request->header('Stripe-Signature');

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig, config('stripe.webhook_secret'));
    } catch (\Exception $e) {
        return response('Yanlış imza', 401);
    }

    // Sync emal (queue-ya göndərmək üçün → dispatch(new HandleStripeEvent($event)))
    match ($event->type) {
        'payment_intent.succeeded' => handlePayment($event->data->object),
        default => null,
    };

    return response('OK', 200);
});
```

```go
// Go — goroutine ilə asinxron emal
func (h *PaymentEventHandler) Handle(w http.ResponseWriter, r *http.Request) {
    body, _ := io.ReadAll(r.Body)

    // İmza yoxla
    event, err := webhook.ConstructEvent(body, r.Header.Get("Stripe-Signature"), secret)
    if err != nil {
        http.Error(w, "Yanlış imza", http.StatusUnauthorized)
        return
    }

    w.WriteHeader(http.StatusOK) // Dərhal 200 qaytar

    go h.processEvent(event) // Asinxron emal
}
```

**Əsas fərqlər:**
- Laravel: queue driver (Redis/database) ilə persistent async; Go: goroutine — in-process, process restart-da itirilir
- Laravel `Webhook::constructEvent()`: Stripe SDK daxilindədir; Go-da eyni
- İmza yoxlama: hər ikisində manual HMAC-SHA256 (Stripe SDK yardımı ilə)
- Laravel `php artisan stripe:listen` (Stripe CLI): webhook-ları lokal test edir; Go-da eyni CLI istifadə olunur

## Əlaqəli Mövzular

- [01-http-server.md](01-http-server.md) — HTTP server
- [62-security.md](../advanced/07-security.md) — HMAC, imza yoxlama
- [15-event-bus.md](../advanced/15-event-bus.md) — Event-driven pattern
- [02-http-client.md](02-http-client.md) — HTTP client (webhook göndərmək üçün)
