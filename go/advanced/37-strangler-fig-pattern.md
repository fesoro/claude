# Strangler Fig Pattern (Lead)

## İcmal

Strangler Fig Pattern, mövcud monolith tətbiqini mikroservislərə tədricən miqrasiya etmək üçün istifadə olunan bir arxitektura strategiyasıdır. Ad tropik Strangler Fig ağacından gəlir — bu ağac bir ev sahibi ağaca sarılır, tədricən onu örtür və nəticədə onun yerini alır. Köhnə sistem "boğulana" qədər yeni sistem onun ətrafında böyüyür.

PHP/Laravel monolithini Go microservice-lərə miqrasiya etmənin ən praktik yolu budur.

## Niyə Vacibdir

**Big-bang rewrite anti-pattern-i:**

```
❌ "6 ay monolith-i dayandırıb sıfırdan yenidən yazacağıq"
→ Risklər: 6 ay boyunca feature freeze, yeni sistemin production-da test edilməməsi,
  business logic-in itirilməsi, deadline-ların keçilməsi
```

**Strangler Fig:**

```
✓ Hər həftə bir feature yeni sistemdə işə salınır
✓ Monolith hər zaman işlək qalır
✓ Rollback ani baş verir (sadəce gateway route-u dəyiş)
✓ Yeni sistem production traffic-i ilə real test edilir
```

Real dünyada Shopify, GitHub, Airbnb bu yanaşmadan istifadə edərək legacy sistemlərini miqrasiya ediblər.

## Əsas Anlayışlar

### Miqrasiya Fazaları

**Faza 1 — İdentifikasiya**: Hansı bounded context-i ilk çıxarmaq lazımdır?
- Ən az dependency olanı seç
- Ən yüksək dəyişiklik sürəti olanı seç (tez-tez dəyişən kod miqrasiyadan sonra daha asan idarə olunur)
- Ən yüksək performans problemi olanı seç

**Faza 2 — Yeni servis qur**: Seçilmiş bounded context üçün Go service yaz

**Faza 3 — Traffic route et**: API Gateway ilə seçilmiş endpoint-ləri yeni servisə yönləndir

**Faza 4 — Data miqrasiyası**: Dual-write — hər iki DB-yə yaz. Sonra köhnəni oxuma üçün istifadə etməyi dayandır.

**Faza 5 — Decommission**: Monolithdan köhnə kodu sil

### Strangler Facade

Monolith ilə yeni servis arasında keçid dövrü üçün facade layer:

```
Client → Facade → [yeni servismi? → Go service]
                   [köhnə kodmu?   → PHP monolith]
```

### Anti-Corruption Layer (ACL)

Köhnə domain model ilə yeni domain model arasında translator. Monolith-in data strukturu ilə yeni servisin domain model-i çox vaxt fərqlidir.

## Praktik Baxış

### Hansı Servisdan Başlamaq Lazımdır

İdeal ilk seçim:
- Monolith-in digər hissəsindən nisbətən izole olan
- Aydın API sınırları olan
- Nisbətən az shared state olan
- Yüksək test coverage olan (miqrasiya düzgün olduğunu yoxlamaq üçün)

**Pis ilk seçim**: Authentication — hər yerdə istifadə olunur, əvvəlcə bu kodu çıxarmaq hər şeyi sındıra bilər.

**Yaxşı ilk seçim**: Notifications, File Upload, Email Sending — izole, aydın sınırlar.

### Dual-Write Dövrü

```
Monolith DB (MySQL)  ←──── Dual Write ────→  New Service DB (PostgreSQL)
```

Bu dövrdə hər iki DB-yə yazılır. Köhnə DB hələ də primary-dir. Yeni DB-nin datanın doğruluğu yoxlanılır. Tam inam gəldikdə köhnə DB-dən oxuma dayandırılır.

### Feature Flags

Traffic-i tədricən yeni servisə köçür:

```
1% → yeni servis (beta test)
10% → yeni servis (shadow mode)
50% → yeni servis (A/B test)
100% → yeni servis (tam miqrasiya)
```

## Nümunələr

### Ümumi Nümunə

**Ssenari**: PHP Laravel monolith-dən Go Notification Service çıxarmaq

```
Başlanğıc vəziyyəti:
PHP Monolith (Laravel):
  ├── UserController
  ├── OrderController
  ├── NotificationService  ← Bu çıxarılacaq
  └── PaymentController

Hədəf vəziyyəti:
PHP Monolith (Laravel):
  ├── UserController
  ├── OrderController
  └── PaymentController   (Notification artıq yoxdur)

Go Notification Service:  ← Yeni
  ├── EmailHandler
  ├── SMSHandler
  └── PushHandler
```

### Kod Nümunəsi

**Faza 1: Mövcud monolith — PHP Laravel notification çağırışı:**

```php
// PHP monolith — köhnə kod
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create($request->validated());

        // Köhnə kod — monolith daxilindəki notification service
        app(NotificationService::class)->sendOrderConfirmation($order);

        return response()->json($order, 201);
    }
}
```

**Faza 2: Go Notification Service yaz:**

```go
package main

import (
    "encoding/json"
    "log"
    "net/http"
)

type SendEmailRequest struct {
    To      string `json:"to"`
    Subject string `json:"subject"`
    Body    string `json:"body"`
    OrderID string `json:"order_id"`
}

type NotificationHandler struct {
    emailSender EmailSender
    smsProvider SMSProvider
}

func (h *NotificationHandler) SendOrderConfirmation(w http.ResponseWriter, r *http.Request) {
    var req SendEmailRequest
    if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
        http.Error(w, "Bad request", http.StatusBadRequest)
        return
    }

    if err := h.emailSender.Send(r.Context(), req.To, req.Subject, req.Body); err != nil {
        log.Printf("Email send failed for order %s: %v", req.OrderID, err)
        http.Error(w, "Failed to send notification", http.StatusInternalServerError)
        return
    }

    w.WriteHeader(http.StatusOK)
    json.NewEncoder(w).Encode(map[string]string{"status": "sent"})
}

func main() {
    handler := &NotificationHandler{
        emailSender: NewSMTPEmailSender(),
    }

    mux := http.NewServeMux()
    mux.HandleFunc("POST /notifications/order-confirmation", handler.SendOrderConfirmation)

    log.Println("Notification Service running on :8090")
    http.ListenAndServe(":8090", mux)
}
```

**Faza 3: API Gateway ilə routing (percentage-based rollout):**

```go
package gateway

import (
    "math/rand"
    "net/http"
    "net/http/httputil"
    "net/url"
)

// StranglerRouter köhnə monolith ilə yeni servis arasında traffic bölür
type StranglerRouter struct {
    legacy      *httputil.ReverseProxy  // PHP Laravel monolith
    newService  *httputil.ReverseProxy  // Go Notification Service
    rolloutPct  int                     // 0-100: neçə % yeni servisə getsin
    featureFlag FeatureFlagClient
}

func NewStranglerRouter(legacyURL, newServiceURL string, rolloutPct int) *StranglerRouter {
    legacy, _ := url.Parse(legacyURL)
    newSvc, _ := url.Parse(newServiceURL)

    return &StranglerRouter{
        legacy:     httputil.NewSingleHostReverseProxy(legacy),
        newService: httputil.NewSingleHostReverseProxy(newSvc),
        rolloutPct: rolloutPct,
    }
}

func (r *StranglerRouter) ServeHTTP(w http.ResponseWriter, req *http.Request) {
    // /notifications/* → yeni servisə yönləndir (rolloutPct %)
    if req.URL.Path[:len("/notifications")] == "/notifications" {
        if rand.Intn(100) < r.rolloutPct {
            r.newService.ServeHTTP(w, req)
            return
        }
    }

    // Hər şey else — köhnə monolith
    r.legacy.ServeHTTP(w, req)
}
```

**Feature flag ilə daha kontrollü rollout:**

```go
package gateway

import "net/http"

type FeatureFlagRouter struct {
    legacy     http.Handler
    newService http.Handler
    flags      FeatureFlagClient
}

func (r *FeatureFlagRouter) ServeHTTP(w http.ResponseWriter, req *http.Request) {
    userID := req.Header.Get("X-User-ID")

    // Feature flag: bu user üçün yeni servis aktiv?
    if r.flags.IsEnabled("notification-service-v2", userID) {
        r.newService.ServeHTTP(w, req)
        return
    }

    r.legacy.ServeHTTP(w, req)
}
```

**Faza 4: Dual-Write — Strangler Facade PHP-də:**

```php
// PHP Monolith — keçid dövrü: hər iki yerdə çağır
class NotificationService
{
    public function __construct(
        private readonly LegacyMailer $legacyMailer,
        private readonly GoNotificationClient $goClient,
        private readonly bool $useNewService
    ) {}

    public function sendOrderConfirmation(Order $order): void
    {
        if ($this->useNewService) {
            // Yeni Go service-ə HTTP sorğu at
            try {
                $this->goClient->sendOrderConfirmation([
                    'to'       => $order->user->email,
                    'order_id' => $order->id,
                    'subject'  => 'Order confirmed: #' . $order->id,
                    'body'     => $this->buildEmailBody($order),
                ]);
                return;
            } catch (\Exception $e) {
                // Yeni servis uğursuz olarsa köhnəyə fallback et
                logger()->warning('Go notification service failed, falling back', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Köhnə yol
        $this->legacyMailer->sendOrderConfirmation($order);
    }
}
```

**Anti-Corruption Layer — domain model çevirici:**

```go
// PHP monolith-in köhnə data strukturu ilə yeni Go domain model-i fərqlidir
// ACL bu çevrilməni edir

package acl

// LegacyOrderDTO — PHP monolith-dən gələn köhnə format
type LegacyOrderDTO struct {
    OrderNumber  string  `json:"order_number"` // "ORD-2024-001"
    CustomerID   int     `json:"customer_id"`  // integer
    TotalCents   int     `json:"total_cents"`  // santdə
    Dispatched   bool    `json:"dispatched"`
}

// Order — yeni Go domain model
type Order struct {
    ID         string
    UserID     string  // UUID formatında
    Amount     float64 // dollarla
    Status     string  // "pending", "shipped", "delivered"
}

// Translate köhnə formatı yeni domain model-ə çevirir
func TranslateLegacyOrder(dto LegacyOrderDTO) Order {
    status := "pending"
    if dto.Dispatched {
        status = "shipped"
    }

    return Order{
        ID:     dto.OrderNumber,
        UserID: fmt.Sprintf("user-%d", dto.CustomerID),
        Amount: float64(dto.TotalCents) / 100.0,
        Status: status,
    }
}
```

**Shadow Mode — yeni servis real traffic-i yoxlayır, lakin cavabı göndərmir:**

```go
// Shadow mode: real traffic yeni servisə kopyalanır — lakin yalnız test üçün
// Real cavab hələ də köhnə servisdən gəlir

func (r *StranglerRouter) shadowTest(req *http.Request) {
    // Sorğunun kopyasını yarat
    cloned := req.Clone(context.Background())

    go func() {
        // Yeni servisə göndər, nəticəni yalnız log et
        rr := httptest.NewRecorder()
        r.newService.ServeHTTP(rr, cloned)

        if rr.Code != http.StatusOK {
            log.Printf("Shadow test failed: status=%d, body=%s", rr.Code, rr.Body.String())
        } else {
            log.Printf("Shadow test passed: status=%d", rr.Code)
        }
    }()
}

func (r *StranglerRouter) ServeHTTP(w http.ResponseWriter, req *http.Request) {
    if req.URL.Path == "/api/notifications" {
        // Shadow mode aktiv — hər sorğunun kopyasını yeni servisə göndər
        r.shadowTest(req)
    }

    // Real cavab hələ köhnə servisdən
    r.legacy.ServeHTTP(w, req)
}
```

**Miqrasiya tamamlandıqda — monolith-dən köhnə kodu sil:**

```php
// ƏVVƏL: Dual-write dövrü
class NotificationService
{
    public function sendOrderConfirmation(Order $order): void
    {
        if ($this->useNewService) {
            $this->goClient->sendOrderConfirmation(...);
            return;
        }
        $this->legacyMailer->sendOrderConfirmation($order);
    }
}

// SONRA: Artıq yalnız Go service var, PHP kodu silindi
// PHP OrderController-də artıq notification çağırışı yoxdur
// Gateway /api/notifications-i 100% Go service-ə yönləndirir
```

## Praktik Tapşırıqlar

1. **Notification Service**: Go-da sadə notification service yaz. `/notifications/email` endpoint-i. PHP Laravel-dən `GoNotificationClient` HTTP wrapper-i yaz. Feature flag (`useNewService`) ilə keçid idarə et.

2. **Gateway with Rollout**: Percentage-based routing gateway yaz. 0%, 10%, 50%, 100% rollout-u test et. Her request-in hansı servises getdiyini log et.

3. **Shadow Mode**: Shadow mode implement et. Hər sorğunun kopyasını yeni servisə göndər, nəticəni köhnə servis cavabı ilə müqayisə et. Fərqlər varsa alert ver.

4. **Anti-Corruption Layer**: PHP monolith-in köhnə JSON formatını (`order_number`, `total_cents`) yeni Go domain model-ə çevirin ACL yaz. Table-driven testlər əlavə et.

5. **Rollback Plan**: Yeni servis production-da uğursuz olarsa gateway-də bir config dəyişikliyi ilə bütün traffic-i köhnə monolith-ə qaytaran mexanizm implement et. Bu rollback 30 saniyədən çox çəkməməlidir.

## Əlaqəli Mövzular

- `35-api-gateway-patterns.md` — Gateway ilə traffic routing
- `36-database-per-service.md` — Miqrasiya zamanı DB izolyasiyası
- `33-saga-pattern.md` — Miqrasiya zamanı cross-service transactions
- `26-microservices.md` — Microservice arxitekturası
- `27-clean-architecture.md` — Yeni servis üçün clean architecture
