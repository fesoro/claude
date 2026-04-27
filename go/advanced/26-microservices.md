# Microservices (Architect)

## İcmal

Microservices — böyük sistemi kiçik, müstəqil xidmətlərə bölmək arxitektura yanaşmasıdır. Hər xidmetin öz database-i, öz deploy prosesi, öz komandası ola bilər. Go bu arxitektura üçün ideal dildir: kiçik binary, sürətli startup, az yaddaş, güclü concurrency.

Architect səviyyəsində yalnız microservice yazmaq deyil — service decomposition qərarları, communication pattern seçimi, service discovery, circuit breaker, saga pattern, data consistency strategiyası bilinməlidir.

## Niyə Vacibdir

- Böyük komandalar monoliti paylaşa bilmir — hər komanda öz xidmətini idarə edir
- Müstəqil scale: ödəniş xidməti çox yüklənib → yalnız onu scale et
- Fault isolation: bir xidmet düşsə hamısı düşmür
- Technology heterogeneity: ML xidməti Python, API Go, frontend Node ola bilər
- Deploy frequency artır: 50 komanda ayda bir deyil, hər komanda günlük deploy edir

## Əsas Anlayışlar

**Service decomposition prinsipləri:**
- Single Responsibility: hər xidmet bir biznes domenini əhatə edir
- Domain-Driven Design (DDD): Bounded Context-ə görə böl
- Team ownership: Conway's Law — arxitektura komanda strukturunu əks etdirir

**Communication növləri:**
- Sinxron: REST/HTTP (sadə, debug rahat), gRPC (sürətli, type-safe, streaming)
- Asinxron: Message Queue (Kafka, RabbitMQ) — loose coupling, retry

**Service Discovery:**
- Client-side: client özü qeydiyyat sistemini soruşur (Consul client)
- Server-side: load balancer qeydiyyat sistemini soruşur (Kubernetes Service)
- DNS-based: Kubernetes-də `service-adı.namespace.svc.cluster.local`

**Circuit Breaker vəziyyətləri:**
- CLOSED (normal): bütün request-lər keçir
- OPEN (bloklanmış): çox xəta — request rədd edilir, fallback işləyir
- HALF-OPEN (test): müəyyən müddətdən sonra test request keçir

**Saga pattern:**
- Microservice-lərdə distributed transaction yoxdur
- Saga: local tranzaksiyalar + compensating (geri qaytarma) əməliyyatlar
- Orchestration: mərkəzi koordinator idarə edir
- Choreography: hər xidmet hadisə yayır, növbəti xidmet dinləyir

## Praktik Baxış

**Nə vaxt microservices:**
- Komanda 10+ developer (5-7 komandaya bölünəcək)
- Müxtəlif scale tələbləri (payments vs notifications)
- Müxtəlif texnologiya tələbləri
- Release frequency artdıqca monolit bottleneck olur

**Nə vaxt microservices-ə KEÇMƏ:**
- 2-3 developer komandası — overhead-ə dəyməz
- Domenler arası sıx coupling — ayrılmadan əvvəl monolit-i refaktor et
- "Microservices daha yaxşıdır" düşüncəsi ilə — problem olmadan həll axtarma

**Trade-off-lar:**
- Monolit: sadə debug, transaction asandır, network yoxdur, amma scale çətindir
- Microservice: müstəqil scale, fault isolation, amma network latency, distributed transaction, observability mürəkkəbliyi
- "Modular monolith" aralıq seçim — modular, amma tək deploy

**Common mistakes:**
- Xidmətləri çox incə bölmək ("nano-service antipattern")
- Xidmətlər arası sinxron HTTP zənciri — A→B→C→D — hər xəta hamısını çökdürür
- Hər xidmət eyni database-i paylaşır — bu microservice deyil
- API Gateway olmadan direct service-ə bağlanmaq

## Nümunələr

### Nümunə 1: Service decomposition — e-ticarət nümunəsi

```
Monolit (bütün kod bir yerdə):
┌──────────────────────────────────────┐
│  User + Order + Payment + Product   │
│  + Notification + Inventory         │
│         TEK DATABASE                │
└──────────────────────────────────────┘

Microservices (hər xidmet müstəqil):
┌──────────┐  ┌──────────┐  ┌──────────┐
│   User   │  │  Order   │  │ Payment  │
│ Service  │  │ Service  │  │ Service  │
│ [UserDB] │  │[OrderDB] │  │ [PayDB]  │
└──────────┘  └──────────┘  └──────────┘
┌──────────┐  ┌──────────┐  ┌──────────┐
│ Product  │  │Inventory │  │ Notif.   │
│ Service  │  │ Service  │  │ Service  │
│[ProdDB]  │  │ [InvDB]  │  │ [Redis]  │
└──────────┘  └──────────┘  └──────────┘
       ↕            ↕            ↕
   ┌─────────────────────────────────┐
   │         API Gateway             │
   │  (auth, routing, rate limiting) │
   └─────────────────────────────────┘
              ↕
         Clients (mobile, web)
```

### Nümunə 2: Sadə API Gateway — reverse proxy

```go
package main

import (
    "log/slog"
    "net/http"
    "net/http/httputil"
    "net/url"
    "strings"
    "time"
)

type ServiceConfig struct {
    Name   string
    URL    string
    Prefix string
}

type APIGateway struct {
    services map[string]*httputil.ReverseProxy
    logger   *slog.Logger
}

func NewAPIGateway(services []ServiceConfig) *APIGateway {
    gw := &APIGateway{
        services: make(map[string]*httputil.ReverseProxy),
        logger:   slog.Default(),
    }

    for _, svc := range services {
        target, err := url.Parse(svc.URL)
        if err != nil {
            gw.logger.Error("URL parse xətası", slog.String("service", svc.Name))
            continue
        }

        proxy := httputil.NewSingleHostReverseProxy(target)

        // Xəta handling
        proxy.ErrorHandler = func(w http.ResponseWriter, r *http.Request, err error) {
            gw.logger.Error("Proxy xətası",
                slog.String("service", svc.Name),
                slog.String("error", err.Error()),
            )
            http.Error(w, "Service unavailable", http.StatusServiceUnavailable)
        }

        gw.services[svc.Prefix] = proxy
    }

    return gw
}

func (gw *APIGateway) ServeHTTP(w http.ResponseWriter, r *http.Request) {
    // Auth middleware
    if !gw.authenticate(r) {
        http.Error(w, "Unauthorized", http.StatusUnauthorized)
        return
    }

    // Rate limiting
    if !gw.rateLimit(r) {
        http.Error(w, "Too Many Requests", http.StatusTooManyRequests)
        return
    }

    // Routing
    for prefix, proxy := range gw.services {
        if strings.HasPrefix(r.URL.Path, prefix) {
            // Request ID əlavə et — distributed tracing üçün
            r.Header.Set("X-Request-ID", generateID())
            r.Header.Set("X-Forwarded-For", r.RemoteAddr)

            gw.logger.Info("Request routing",
                slog.String("prefix", prefix),
                slog.String("path", r.URL.Path),
            )
            proxy.ServeHTTP(w, r)
            return
        }
    }

    http.Error(w, "Not Found", http.StatusNotFound)
}

func (gw *APIGateway) authenticate(r *http.Request) bool {
    // JWT token yoxla
    token := r.Header.Get("Authorization")
    return strings.HasPrefix(token, "Bearer ")
}

func (gw *APIGateway) rateLimit(r *http.Request) bool {
    // Real implementasiya: Redis ilə sliding window
    return true
}

func generateID() string {
    return "req-" + time.Now().Format("20060102150405.000000")
}

func main() {
    gw := NewAPIGateway([]ServiceConfig{
        {Name: "user-service", URL: "http://user-service:8081", Prefix: "/api/users"},
        {Name: "order-service", URL: "http://order-service:8082", Prefix: "/api/orders"},
        {Name: "payment-service", URL: "http://payment-service:8083", Prefix: "/api/payments"},
    })

    slog.Info("API Gateway :8080 portunda")
    http.ListenAndServe(":8080", gw)
}
```

### Nümunə 3: Circuit Breaker — gobreaker ilə

```go
package main

import (
    "context"
    "errors"
    "fmt"
    "io"
    "log/slog"
    "net/http"
    "time"

    "github.com/sony/gobreaker/v2"
)

// go get github.com/sony/gobreaker/v2

// User service client — circuit breaker ilə
type UserServiceClient struct {
    cb         *gobreaker.CircuitBreaker[[]byte]
    httpClient *http.Client
    baseURL    string
    logger     *slog.Logger
    cache      map[int64][]byte // fallback cache
}

func NewUserServiceClient(baseURL string) *UserServiceClient {
    client := &UserServiceClient{
        baseURL:    baseURL,
        httpClient: &http.Client{Timeout: 5 * time.Second},
        logger:     slog.Default(),
        cache:      make(map[int64][]byte),
    }

    client.cb = gobreaker.NewCircuitBreaker[[]byte](gobreaker.Settings{
        Name:        "user-service",
        MaxRequests: 3,                // HALF-OPEN-də max test request sayı
        Interval:    10 * time.Second, // CLOSED-də sayac sıfırlanma müddəti
        Timeout:     30 * time.Second, // OPEN-dən HALF-OPEN-ə keçmə müddəti
        ReadyToTrip: func(counts gobreaker.Counts) bool {
            // 5 ardıcıl uğursuzluqda OPEN-ə keç
            // VƏ uğursuzluq faizi %60-dan çox olsun
            failureRatio := float64(counts.TotalFailures) / float64(counts.Requests)
            return counts.ConsecutiveFailures >= 5 || (counts.Requests >= 10 && failureRatio >= 0.6)
        },
        OnStateChange: func(name string, from, to gobreaker.State) {
            client.logger.Warn("Circuit breaker vəziyyəti dəyişdi",
                slog.String("name", name),
                slog.String("from", from.String()),
                slog.String("to", to.String()),
            )
        },
    })

    return client
}

func (c *UserServiceClient) GetUser(ctx context.Context, id int64) ([]byte, error) {
    // Circuit breaker vasitəsilə çalışdır
    result, err := c.cb.Execute(func() ([]byte, error) {
        req, err := http.NewRequestWithContext(ctx, "GET",
            fmt.Sprintf("%s/users/%d", c.baseURL, id), nil)
        if err != nil {
            return nil, err
        }

        resp, err := c.httpClient.Do(req)
        if err != nil {
            return nil, err
        }
        defer resp.Body.Close()

        if resp.StatusCode >= 500 {
            return nil, fmt.Errorf("server xətası: %d", resp.StatusCode)
        }

        body, err := io.ReadAll(resp.Body)
        if err != nil {
            return nil, err
        }

        // Cache-ə yaz — fallback üçün
        c.cache[id] = body
        return body, nil
    })

    if err != nil {
        // Circuit breaker OPEN vəziyyətindədir
        if errors.Is(err, gobreaker.ErrOpenState) {
            c.logger.Warn("Circuit breaker OPEN — cache-dən oxunur",
                slog.Int64("user_id", id),
            )
            // Fallback: keş məlumat qaytar
            if cached, ok := c.cache[id]; ok {
                return cached, nil
            }
            return nil, fmt.Errorf("xidmət əlçatmazdır, keş yoxdur: user_id=%d", id)
        }
        return nil, err
    }

    return result, nil
}
```

### Nümunə 4: Saga pattern — sifariş yaratma

```go
package main

import (
    "context"
    "fmt"
    "log/slog"
)

// Saga mərhələsi
type SagaStep struct {
    Name        string
    Execute     func(ctx context.Context, data *OrderData) error
    Compensate  func(ctx context.Context, data *OrderData) error
}

type OrderData struct {
    UserID    int64
    ProductID int64
    Amount    float64
    OrderID   string
    PaymentID string
}

// Saga Orchestrator — mərkəzləşdirilmiş koordinator
type OrderSaga struct {
    steps  []SagaStep
    logger *slog.Logger
}

func NewOrderSaga() *OrderSaga {
    saga := &OrderSaga{logger: slog.Default()}

    saga.steps = []SagaStep{
        {
            Name: "order-create",
            Execute: func(ctx context.Context, data *OrderData) error {
                // OrderService: sifariş yarat
                data.OrderID = "order-123"
                saga.logger.Info("Sifariş yaradıldı", slog.String("order_id", data.OrderID))
                return nil
            },
            Compensate: func(ctx context.Context, data *OrderData) error {
                // Sifarişi ləğv et
                saga.logger.Info("Sifariş ləğv edildi", slog.String("order_id", data.OrderID))
                return nil
            },
        },
        {
            Name: "inventory-reserve",
            Execute: func(ctx context.Context, data *OrderData) error {
                // InventoryService: anbarda əşyanı rezerv et
                saga.logger.Info("Anbar rezerv edildi",
                    slog.Int64("product_id", data.ProductID),
                )
                return nil
            },
            Compensate: func(ctx context.Context, data *OrderData) error {
                // Rezervi ləğv et
                saga.logger.Info("Anbar rezervi ləğv edildi")
                return nil
            },
        },
        {
            Name: "payment-process",
            Execute: func(ctx context.Context, data *OrderData) error {
                // PaymentService: ödəniş al
                data.PaymentID = "pay-456"
                saga.logger.Info("Ödəniş alındı", slog.String("payment_id", data.PaymentID))
                // Uğursuz olarsa: return fmt.Errorf("ödəniş rədd edildi")
                return nil
            },
            Compensate: func(ctx context.Context, data *OrderData) error {
                // Ödənişi geri qaytar
                saga.logger.Info("Ödəniş geri qaytarıldı", slog.String("payment_id", data.PaymentID))
                return nil
            },
        },
        {
            Name: "notification-send",
            Execute: func(ctx context.Context, data *OrderData) error {
                // NotificationService: müştəriyə bildiriş göndər
                saga.logger.Info("Bildiriş göndərildi")
                return nil
            },
            Compensate: func(ctx context.Context, data *OrderData) error {
                // Bildirişi ləğv etmək adətən mümkün deyil — no-op
                return nil
            },
        },
    }

    return saga
}

func (s *OrderSaga) Execute(ctx context.Context, data *OrderData) error {
    executed := []SagaStep{}

    for _, step := range s.steps {
        s.logger.Info("Saga mərhələsi başladı", slog.String("step", step.Name))

        if err := step.Execute(ctx, data); err != nil {
            s.logger.Error("Saga mərhələsi uğursuz",
                slog.String("step", step.Name),
                slog.String("error", err.Error()),
            )

            // Geriyə gedərək kompensasiya et
            s.logger.Info("Kompensasiya başlayır...")
            for i := len(executed) - 1; i >= 0; i-- {
                prev := executed[i]
                s.logger.Info("Kompensasiya", slog.String("step", prev.Name))

                if compErr := prev.Compensate(ctx, data); compErr != nil {
                    s.logger.Error("Kompensasiya xətası — manual müdaxilə lazımdır",
                        slog.String("step", prev.Name),
                        slog.String("error", compErr.Error()),
                    )
                    // Bu ciddi problemdir — alert, manual intervention
                }
            }

            return fmt.Errorf("saga '%s' mərhələsində uğursuz: %w", step.Name, err)
        }

        executed = append(executed, step)
    }

    s.logger.Info("Saga uğurla tamamlandı",
        slog.String("order_id", data.OrderID),
        slog.String("payment_id", data.PaymentID),
    )
    return nil
}
```

### Nümunə 5: gRPC ilə servislərarası kommunikasiya

```go
// user.proto
// syntax = "proto3";
// package user;
// option go_package = "myapp/pb/user";
//
// service UserService {
//     rpc GetUser(GetUserRequest) returns (User);
//     rpc ListUsers(ListUsersRequest) returns (stream User);
//     rpc CreateUser(CreateUserRequest) returns (User);
// }
//
// message GetUserRequest { int64 id = 1; }
// message ListUsersRequest { int32 page = 1; int32 limit = 2; }
// message User { int64 id = 1; string name = 2; string email = 3; }
// message CreateUserRequest { string name = 1; string email = 2; }

// Protobuf generate:
// protoc --go_out=. --go-grpc_out=. user.proto

package main

import (
    "context"
    "fmt"
    "io"
    "log/slog"
    "net"
    "time"

    "google.golang.org/grpc"
    "google.golang.org/grpc/credentials/insecure"
    "google.golang.org/grpc/keepalive"
)

// go get google.golang.org/grpc
// go get google.golang.org/protobuf

// gRPC server implementation
// type UserServer struct {
//     pb.UnimplementedUserServiceServer
//     repo domain.UserRepository
// }
//
// func (s *UserServer) GetUser(ctx context.Context, req *pb.GetUserRequest) (*pb.User, error) {
//     user, err := s.repo.FindByID(ctx, req.Id)
//     if err != nil {
//         return nil, status.Errorf(codes.NotFound, "user tapılmadı: %d", req.Id)
//     }
//     return &pb.User{Id: user.ID, Name: user.Name, Email: user.Email}, nil
// }
//
// func (s *UserServer) ListUsers(req *pb.ListUsersRequest, stream pb.UserService_ListUsersServer) error {
//     users, err := s.repo.FindAll(stream.Context(), int(req.Limit), int(req.Page))
//     if err != nil {
//         return status.Errorf(codes.Internal, "siyahı xətası: %v", err)
//     }
//
//     for _, user := range users {
//         if err := stream.Send(&pb.User{Id: user.ID, Name: user.Name}); err != nil {
//             return err // client bağlandı
//         }
//     }
//     return nil
// }

// gRPC server başlatmaq
func startGRPCServer() {
    lis, err := net.Listen("tcp", ":50051")
    if err != nil {
        slog.Error("Port dinlənə bilmir", slog.String("error", err.Error()))
        return
    }

    server := grpc.NewServer(
        grpc.KeepaliveParams(keepalive.ServerParameters{
            MaxConnectionIdle: 15 * time.Minute,
            Timeout:           20 * time.Second,
        }),
    )

    // pb.RegisterUserServiceServer(server, &UserServer{})

    slog.Info("gRPC server :50051 portunda")
    server.Serve(lis)
}

// gRPC client
type UserGRPCClient struct {
    // client pb.UserServiceClient
    conn   *grpc.ClientConn
    logger *slog.Logger
}

func NewUserGRPCClient(addr string) (*UserGRPCClient, error) {
    conn, err := grpc.NewClient(addr,
        grpc.WithTransportCredentials(insecure.NewCredentials()),
        grpc.WithKeepaliveParams(keepalive.ClientParameters{
            Time:                10 * time.Second,
            Timeout:             5 * time.Second,
            PermitWithoutStream: true,
        }),
    )
    if err != nil {
        return nil, fmt.Errorf("gRPC bağlantı xətası: %w", err)
    }

    return &UserGRPCClient{
        // client: pb.NewUserServiceClient(conn),
        conn:   conn,
        logger: slog.Default(),
    }, nil
}

func (c *UserGRPCClient) GetUser(ctx context.Context, id int64) error {
    // ctx-ə timeout əlavə et
    ctx, cancel := context.WithTimeout(ctx, 5*time.Second)
    defer cancel()

    // user, err := c.client.GetUser(ctx, &pb.GetUserRequest{Id: id})
    // if err != nil {
    //     st, ok := status.FromError(err)
    //     if ok && st.Code() == codes.NotFound {
    //         return nil, domain.ErrUserNotFound
    //     }
    //     return nil, fmt.Errorf("gRPC xətası: %w", err)
    // }

    _ = io.EOF // import saxlamaq üçün
    return nil
}

func (c *UserGRPCClient) Close() error {
    return c.conn.Close()
}
```

### Nümunə 6: Health check — bütün asılılıqları yoxla

```go
package main

import (
    "context"
    "database/sql"
    "encoding/json"
    "net/http"
    "time"
)

type ServiceHealth struct {
    db     *sql.DB
    // redis  *redis.Client
    // kafka  *kafka.Conn
}

type HealthResponse struct {
    Status    string            `json:"status"`
    Checks    map[string]string `json:"checks"`
    Timestamp string            `json:"timestamp"`
    Service   string            `json:"service"`
}

func (h *ServiceHealth) ReadyHandler(w http.ResponseWriter, r *http.Request) {
    ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
    defer cancel()

    checks := make(map[string]string)
    allOK := true

    // Database yoxla
    if err := h.db.PingContext(ctx); err != nil {
        checks["postgresql"] = "FAIL: " + err.Error()
        allOK = false
    } else {
        checks["postgresql"] = "OK"
    }

    // Redis yoxla
    // if err := h.redis.Ping(ctx).Err(); err != nil {
    //     checks["redis"] = "FAIL: " + err.Error()
    //     allOK = false
    // } else {
    //     checks["redis"] = "OK"
    // }

    resp := HealthResponse{
        Status:    "ready",
        Checks:    checks,
        Timestamp: time.Now().UTC().Format(time.RFC3339),
        Service:   "order-service",
    }

    w.Header().Set("Content-Type", "application/json")
    if !allOK {
        resp.Status = "not_ready"
        w.WriteHeader(http.StatusServiceUnavailable)
    }

    json.NewEncoder(w).Encode(resp)
}

// Kubernetes konfiqurasiyası:
// livenessProbe:
//   httpGet:
//     path: /health
//     port: 8080
//   initialDelaySeconds: 10
//   periodSeconds: 30
//
// readinessProbe:
//   httpGet:
//     path: /ready
//     port: 8080
//   initialDelaySeconds: 5
//   periodSeconds: 10
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Minimal microservice:**
1. İki xidmət yazın: User Service (port 8081) + Order Service (port 8082)
2. Order Service, user-i User Service-dən HTTP ilə alır
3. Docker Compose ilə hər ikisini çalışdırın
4. User Service-i dayandırın — Order Service nə qaytarır?

**Tapşırıq 2 — Circuit Breaker:**
1. User Service-ə 30% xəta faizi əlavə edin: `rand.Intn(10) < 3 → error`
2. Order Service-ə gobreaker əlavə edin
3. 100 request göndərin
4. Circuit breaker-in OPEN-ə keçdiyini görün
5. Fallback cache ilə xidməti ayaqda saxlayın

**Tapşırıq 3 — Saga pattern:**
1. 4 xidmət simulyasiya edin (goroutine funksiyaları kimi)
2. Ödəniş mərhələsini 50% xəta ehtimalı ilə yaradın
3. Xəta olduqda kompensasiya işlədiyini izləyin

**Tapşırıq 4 — gRPC:**
1. `protoc` ilə user.proto yaradın
2. Server tərəfi implement edin
3. Unary + Server streaming RPC əlavə edin
4. `grpcurl` ilə test edin

**Tapşırıq 5 — Service discovery:**
1. Konsul Docker ilə qurun
2. User Service start-da Consul-a qeyd olunsun
3. Order Service Consul-dan User Service-in URL-ni tapsın
4. User Service-i yenidən başladın — URL dəyişdi, Order Service-ə təsiri?

## Ətraflı Qeydlər

**Modular Monolith — aralıq həll:**
- Microservice-in faydaları, monolit-in sadəliyi
- Modul-lar arası əlaqə: interface ilə (HTTP yox)
- Tək deploy, amma daxilən ayrılmış
- Go-da: `internal/` paketi ilə modul əlçatanlığı məhdudlaşdırılır

**Service mesh (Istio/Linkerd):**
- mTLS: servislərarası şifrəli kommunikasiya
- Observability: trace, metric — koda müdaxilə olmadan
- Traffic management: A/B test, canary deploy
- Circuit breaker — aplikasiya kodundan kənarda

**Deployment strategiyaları:**
- Blue-Green: köhnə + yeni eyni anda, switch edilir
- Canary: yeni versiyaya trafixin 5% → 10% → 100% yönləndirilməsi
- Rolling update: pod-lar ardıcıl yenilənir (Kubernetes default)

## PHP ilə Müqayisə

PHP/Laravel-dən gələnlər üçün microservices ən böyük düşüncə dəyişikliyi (mental shift) tələb edən mövzudur. Laravel-in monolit arxitekturasında — bir codebase, bir database, bir deploy. Microservice-lərdə isə 5-50 ayrı servis, hər biri öz database-i, öz deploy pipeline-ı ilə idarə olunur. PHP-nin hər-request-yeni-proses modeli microservice-lərarası state paylaşımını avtomatik həll edir; Go-da uzun yaşayan proses kimi diqqətli state idarəsi lazımdır. Saga pattern PHP-nin distributed transaction problemini Go-dakından daha tez-tez qarşılaşdığı bir problemdir — çünki Laravel ekosistemi uzun müddət monolitik qalmışdır. Go microservice-lərin startup vaxtı (millisaniyələr) PHP-FPM-dən (yüz millisaniyələr) əhəmiyyətli dərəcədə azdır — bu container orchestration-da kritik üstünlükdür.

## Əlaqəli Mövzular

- [72-message-queues.md](72-message-queues.md) — Asinxron servislərarası kommunikasiya
- [74-clean-architecture.md](74-clean-architecture.md) — Hər microservice-in daxili strukturu
- [70-docker-and-deploy.md](70-docker-and-deploy.md) — Docker + Kubernetes deployment
- [71-monitoring-and-observability.md](71-monitoring-and-observability.md) — Distributed tracing
- [67-grpc.md](67-grpc.md) — gRPC ətraflı
