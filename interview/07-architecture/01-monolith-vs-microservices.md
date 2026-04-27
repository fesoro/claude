# Monolith vs Microservices Trade-offs (Senior ⭐⭐⭐)

## İcmal
Monolith vs Microservices mövzusu hər senior backend müsahibəsinin əsas suallarından biridir. Bu sual arxitektura düşüncəsini, trade-off analizi bacarığını və real layihə təcrübəsini yoxlayır. Cavab verərkən yalnız nəzəriyyə deyil, real kontekst əsasında danışmaq vacibdir. Interviewer sizi "hər şeyi microservices ilə həll edək" tələsinə salıb-salmadığını yoxlayır.

## Niyə Vacibdir
Yanlış arxitektura seçimi milyon dollar itkiyə səbəb ola bilər. Bu sual həmçinin sizin sistemin scalability, deployment, team structure haqqındakı anlayışınızı ölçür. Contextual thinking — yəni "bu layihə üçün hansı yanaşma uyğundur?" sualını düzgün cavablandıra bilmək senior-ı middle-dan ayırır. Amazon, Netflix, Uber kimi şirkətlər microservices-ə keçəndə çox böyük pain yaşadılar — bu təcrübələri bilmək real dəyər göstərir. Martin Fowler: "MonolithFirst" — yeni layihə üçün monolith ilə başla.

## Əsas Anlayışlar

### Monolith:
- Bütün komponentlər tək bir deployable unit-də birləşir. Shared codebase, shared database, single process.
- Single JAR (Java), single PHP application, single deployment artifact.
- Daxili modul çağırışları — in-process, microsecond latency.
- Bir yerdən deploy olunur — deployment sadədir.

### Microservices:
- Hər service öz məsuliyyəti olan, müstəqil deploy edilə bilən, ayrı prosesdə işləyən unit.
- Hər service öz database-inə sahib olmalıdır — shared DB anti-pattern.
- Service-lər arası HTTP/gRPC/message queue ilə kommunikasiya — network overhead var.
- Hər service-in öz deployment pipeline-ı, monitoring-i, logging-i var.

### Modular Monolith:
- Ən sağlam başlanğıc yanaşması.
- Daxili modul ayrımı var (domain-lər aydın) — amma hələ tək unit kimi deploy olunur.
- Kod strukturu mikroservislərə bölünməyə hazır — boundary-lər aydındır.
- Martin Fowler, Sam Newman bu yanaşmanı tövsiyə edir.
- Laravel: `app/Modules/Order/`, `app/Modules/Payment/`, `app/Modules/Notification/`.

### Distributed Monolith (Anti-pattern):
- Ən pis hal — microservices kimi görünür, amma tight coupling var.
- Bir service dəyişdikdə digərlərini deploy etmək lazımdır — hər deploy koordinasiya tələb edir.
- Network overhead var, ACID transactions yoxdur, debugging cəhənnəmdir.
- Shared database: microservices kimi deploy, amma DB coupling qalır.
- Symptom: "Biz microservices qururuq amma hər deployment hamını affect edir."

### Conway's Law:
- "Sistemin arxitekturası komandanın kommunikasiya strukturunu əks etdirir."
- Üç komanda → üç service. İstəsəniz də istəməsəniz də.
- Team boundaries = service boundaries. Əvvəlcə team structure-u düzəlt, sonra service boundary-ləri.
- Reverse Conway Maneuver: İstədiyiniz arxitektura üçün team-i elə qurun.

### Monolith Üstünlükləri:
- Sadə deployment — tək binary, tək pipeline.
- Asan debugging — single process stack trace, local breakpoint.
- In-process communication — microsecond latency (HTTP yerinə function call).
- Transactional consistency asandır — ACID, single DB connection, `DB::transaction()`.
- Lokal development sürətlidir — bir `php artisan serve` bəs edir.
- Junior team üçün uyğundur — komplekslik azdır.

### Monolith Çatışmazlıqları:
- Scaling hissə-hissə mümkün deyil — bütün app scale olur (CPU-intensive modul yüzlərçe web server tələb etmir).
- Bir hissənin xətası ya da memory leak hamını təsir edir — single point of failure.
- Tech stack dəyişdirmək çox çətin — bütün app bir dildə.
- Böyük teams üçün merge conflict cəhənnəm — 50+ developer eyni repoda.
- Deploy frequency azalır — bir nəfərin kodu hamının deployunu bloklamaq riski.

### Microservices Üstünlükləri:
- Independent scaling — yalnız yüklü service scale olur. Payment service 50x, catalog service 2x.
- Independent deployment — bir service dəyişdikdə digərləri toxunulmaz. Daily deploy mümkündür.
- Fault isolation — bir service çökəndə circuit breaker ilə digərləri yaşayır.
- Team autonomy — hər team öz service-i üzərində tamamilə müstəqildir.
- Polyglot persistence — hər service optimal DB seçir: PostgreSQL, MongoDB, Redis, Elasticsearch.
- Technology diversity — hər service fərqli language istifadə edə bilər.

### Microservices Çatışmazlıqları:
- Network latency — in-process yerinə HTTP/gRPC call: milliseconds fərq.
- Distributed transactions çətin — Saga pattern, 2PC tələb edir. ACID yoxdur.
- Operational complexity — service discovery, load balancing, distributed tracing, Kubernetes — 10+ service üçün idarəetmə sıxlığı.
- Eventual consistency ilə yaşamaq lazımdır — bir service yenilədi, digəri hələ bilmir.
- Integration testing çətin — 10 service-i test mühitdə ayağa qaldırmaq.
- Debugging: Distributed tracing (Jaeger, Zipkin) olmadan stack trace izləmək mümkün deyil.
- Deployment complexity: 1 monolith → 1 pipeline; 10 microservice → 10 ayrı pipeline, 10 Docker image, 10 K8s deployment.

### Strangler Fig Pattern:
- Monolith-dən microservices-ə tədricən keçid üsulu. Martin Fowler-in tövsiyəsi.
- Yeni funksionallıq yeni service-də. Köhnə funksionallıq tədricən yeni service-ə köçürülür.
- Big bang rewrite edilmir — risk böyük, uğursuzluq ehtimalı yüksək (Amazon'un "green field" prinsipi).
- API Gateway köhnə və yeni service-i qarışdırır — client eyni URL-ə müraciət edir.

### Service Size Qaydaları:
- "2-pizza team" (Jeff Bezos): Bir service üçün maksimum 2 pizza ilə doydurulan team (6-10 nəfər).
- Single Responsibility: Service bir domain-i idarə edir — Order, Payment, Notification.
- Çox kiçik service = nano-service anti-pattern: Network overhead, no real benefit. "Function call-u service-ə çevirmə."
- Doğru ölçü: Service-i bir team tam anlaya bilir, bir team tam deploy edə bilir.

### Data Isolation Prinsipi:
- Hər microservice öz database-inə sahib olmalıdır — bu mikroservislerin fundamental qanunudur.
- Shared database: Microservices-in ən böyük anti-pattern-i. Shared DB varsa tight coupling qalır.
- Schema dəyişiklikləri koordinasiya tələb edir — başqa service-in tablosunu dəyişmək olmaz.
- Cross-service query: JOIN yoxdur — API call ya event-driven denormalization.

### Inter-Service Communication:
- **Synchronous (REST, gRPC)**: Real-time cavab lazımdırsa. Request-response. Caller bloklanır. Latency visible.
- **Asynchronous (Kafka, RabbitMQ, SQS)**: Decoupling lazımdırsa, high throughput, eventual consistency qəbul edilmişsə. Caller bloklanmır — fire and forget.
- **Hybrid**: Core flow sync (order create → payment), notification async (order placed email via Kafka).

### Service Mesh:
- 10+ microservice-dən sonra service-to-service communication mürəkkəbləşir.
- Istio, Linkerd, Consul Connect — mTLS, observability, traffic management, canary deployment.
- Sidecar proxy (Envoy): Hər service yanında, network traffic-i intercept edir.
- Senior/Lead level bilgi — interviewer-ı impress edir.

### Health Check və Circuit Breaker:
- Hər service `/health` endpoint-inə sahib olmalıdır — Kubernetes liveness/readiness probe.
- Circuit breaker (Hystrix, Resilience4j, PHP-də Ganesha): Xətalı service-ə çağırışları dayandırır, cascade failure-u əngəlləyir.
- States: Closed (normal), Open (xətalı service-ə call etmə), Half-Open (test call — recover etdimi?).
- Timeout, retry, backoff — hər external call-un ətrafında olmalıdır.

### Database per Service Strategiyası:
- Order Service → PostgreSQL (ACID, complex queries).
- Product Service → MongoDB (flexible schema, faceted search).
- Session Service → Redis (ultra-low latency, TTL-based expiry).
- Analytics Service → ClickHouse (OLAP, time-series).
- Hər service öz data store-unu optimal seçir.
- Trade-off: Cross-service JOIN mümkün deyil, eventual consistency lazımdır.

### Real Şirkət Təcrübəsi:
- **Amazon**: Monolith-dən microservices-ə keçdi — indi 500+ service. SOA (Service-Oriented Architecture) prinsipi.
- **Netflix**: 700+ microservice. Chaos Engineering (Chaos Monkey) — production-da random service kill.
- **Shopify**: Modular monolith — Rails monolith-i "component-based" arxitekturaya keçirdi. Tam microservices-ə geçmədilər.
- **Basecamp (DHH)**: Microservices-dən monolith-ə geri döndü — "Majestic Monolith" manifesto.
- **StackOverflow**: Milyonlarca traffic-i sadə monolith ilə handle edir — microservices sihirli həll deyil.

## Praktik Baxış

### Interview-da Necə Yanaşmaq:
Sual qoyulduqda dərhal "microservices daha yaxşıdır" deməyin. Əvvəlcə soruşun:
- "Mövcud layihədir, yoxsa yeni?"
- "Team size nə qədərdir?"
- "Traffic load nədir? DAU?"
- "Deployment frequency nədir?"
Bu suallar sizin arxitektura düşüncənizi göstərir. Cavabı daima kontekstlə başladın.

### Junior-dan Fərqlənən Senior Cavabı:
- **Junior**: "Microservices daha modern, monolith köhnəlmiş." — Texniki derinlik yoxdur.
- **Middle**: "Microservices scalability üstünlüyü verir." — Amma ne zaman lazım olduğunu bilmir.
- **Senior**: "Depends on context. Yeni startupda 6-12 ay monolith ilə başlaram — domain aydın deyil, team kiçik. Sonra ən çox yüklənən hissəni microservice-ə çıxararam." — Kontekst var.
- **Lead**: "Conway's Law nəzərə alıb team structure-u microservice boundary-lərinə uyğun quraram. Error budget müəyyən edərəm." — Operational aspekt var.

### Follow-up Suallar:
- "Monolith-dən microservices-ə keçmək üçün nə vaxt hazır olduğunuzu necə bilərsiniz?" → Deploylar biri-birini bloklayır, fərqli scaling tələbi, team-lər konflikt yaşayır.
- "Distributed transactions problemini necə həll edərdiniz?" → Saga pattern — Choreography vs Orchestration. Compensating transaction.
- "Service discovery necə işləyir?" → Consul, Kubernetes DNS, AWS Service Discovery. Client-side vs server-side.
- "Microservices-də data consistency-ni necə qoruyursunuz?" → Eventual consistency qəbul etmək, Outbox pattern, idempotency key.
- "Bir service down olanda digər service-lər necə davranır?" → Circuit breaker, timeout, graceful degradation, fallback response.
- "Distributed tracing nədir?" → Jaeger, Zipkin, OpenTelemetry. Trace ID hər service-dən keçir.

### Ümumi Səhvlər:
- "Microservices = modern, monolith = köhnəlmiş" kimi düşünmək — Netflix, Amazon monolith ilə başladı.
- Microservices-ə keçid xərclərini (DevOps infrastructure, monitoring, distributed tracing) kiçik hesab etmək.
- Team size-ı nəzərə almamaq — 3 nəfərlik team 20 microservices idarə edə bilməz.
- Shared database saxlamaq — microservices-in ən böyük anti-pattern-i.
- Domain boundary-ləri aydın olmadan bölmək — distributed monolith yaranır.
- Strangler Fig əvəzinə big bang rewrite etmək — "biz hər şeyi yenidən yazacağıq" 2 ildə bitmir.

### Yaxşı Cavabı Əla Cavabdan Fərqləndirən:
Əla cavab konkret nümunə verir: "Biz X layihəsini microservices-ə keçirərkən Y problemi ilə üzləşdik, Z şəkildə həll etdik." Real trade-off-ları özündən keçirmiş kimi danışır. Error budget, SLO, circuit breaker kimi operational aspektlərə toxunur.

## Nümunələr

### Tipik Interview Sualı
"Monolith vs Microservices — hansını seçərdiniz və niyə?"

### Güclü Cavab
"Bu sualın cavabı kontekstdən asılıdır. Yeni bir startupda ilk 6-12 ay monolith ilə başlamağı tövsiyə edərəm — biznes domain hələ tam aydın deyil, team kiçikdir, sürətli iteration lazımdır. Modular monolith qurardım: Laravel-də `app/Modules/` strukturu ilə domain-lər aydın ayrılmış, amma hələ tək binary. Domain boundary-ləri aydınlaşdıqdan sonra ən çox yüklənən hissəni — məsələn notification service-i — ayrı microservice-ə çıxarardım. Microservices-ə keçid siqnalları: deploylar biri-birini bloklayır, fərqli scaling tələbi, fərqli team-lər eyni koda toxunur. Bu siqnallar olmadan microservices — premature optimization-dur."

### Arxitektura / Kod Nümunəsi

**Modular Monolith — Laravel:**
```php
// app/Modules/Order/Services/OrderService.php
namespace App\Modules\Order\Services;

class OrderService
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentService $payment,     // eyni process — in-memory call, microsecond
        private InventoryService $inventory, // eyni process
        private NotificationService $notify  // eyni process
    ) {}

    public function place(PlaceOrderDTO $dto): Order
    {
        // Sadə ACID transaction — eyni DB connection
        return DB::transaction(function () use ($dto) {
            $order = $this->orders->create($dto);
            $this->inventory->reserve($dto->items);        // O(1) — function call
            $this->payment->charge($dto->paymentMethod, $order->total);
            $this->notify->orderPlaced($order);            // sync, in-process
            return $order;
        });
        // Rollback avtomatik — exception olduqda hamı geri döndürülür
    }
}
```

**Microservices — Saga Pattern ilə:**
```php
// Order Service → Inventory Service HTTP call
// Distributed transaction — ACID yoxdur, Saga lazımdır
class OrderService
{
    public function __construct(
        private InventoryClient $inventoryClient,  // HTTP client — network call
        private PaymentClient $paymentClient,      // HTTP client — network call
        private OrderRepository $orders,
        private EventBus $eventBus                 // Kafka/RabbitMQ
    ) {}

    public function place(PlaceOrderDTO $dto): Order
    {
        // Step 1: Reserve inventory — network call, fail ola bilər
        $reservationId = $this->inventoryClient->reserve($dto->items);
        // BU NÖQTƏDƏ CRASH OLSA? reservationId var, amma order yoxdur!

        try {
            // Step 2: Create order record — local DB
            $order = $this->orders->create([...$dto->toArray(), 'reservation_id' => $reservationId]);

            // Step 3: Process payment — network call
            $this->paymentClient->charge($dto->paymentMethod, $order->total);

            // Step 4: Publish event — async fan-out
            $this->eventBus->publish(new OrderPlaced($order));

            return $order;
        } catch (\Exception $e) {
            // Compensating transaction — saga rollback
            $this->inventoryClient->cancelReservation($reservationId);
            // Amma paymentClient çağrısından sonra crash olsaydı?
            // Outbox Pattern + idempotency key lazımdır!
            throw $e;
        }
    }
}

// Outbox Pattern: Distributed transaction probleminin həlli
class OrderService
{
    public function place(PlaceOrderDTO $dto): Order
    {
        // Atomic: Order CREATE + event RECORD eyni DB transaction-da
        return DB::transaction(function () use ($dto) {
            $order = Order::create($dto->toArray());

            // Outbox table-a yaz — eyni transaction-da, atomik
            OutboxEvent::create([
                'aggregate_type' => 'Order',
                'aggregate_id'   => $order->id,
                'event_type'     => 'OrderPlaced',
                'payload'        => json_encode($order->toArray()),
            ]);

            return $order;
        });
        // Kafka producer ayrı process: outbox-dan oxuyur, publish edir.
        // Transaction commit olubsa event mütləq publish olunacaq.
    }
}
```

**Docker Compose — Microservices local development:**
```yaml
version: '3.8'
services:
  order-service:
    build: ./order-service
    ports: ["8001:8000"]
    environment:
      INVENTORY_SERVICE_URL: http://inventory-service:8000
      PAYMENT_SERVICE_URL: http://payment-service:8000
      KAFKA_BROKERS: kafka:9092
    depends_on: [order-db, kafka]

  inventory-service:
    build: ./inventory-service
    ports: ["8002:8000"]
    depends_on: [inventory-db]

  payment-service:
    build: ./payment-service
    ports: ["8003:8000"]
    depends_on: [payment-db]

  # Hər service-in öz DB-si — shared DB anti-pattern-dən qaçmaq
  order-db:
    image: postgres:16
    environment:
      POSTGRES_DB: orders
    volumes: [order-data:/var/lib/postgresql/data]

  inventory-db:
    image: mysql:8
    volumes: [inventory-data:/var/lib/mysql]

  payment-db:
    image: postgres:16
    environment:
      POSTGRES_DB: payments

  # Async communication backbone
  kafka:
    image: confluentinc/cp-kafka:7.5.0
    environment:
      KAFKA_BROKER_ID: 1
      KAFKA_ZOOKEEPER_CONNECT: zookeeper:2181

  # Service discovery — local dev üçün sadə
  consul:
    image: consul:1.15
    ports: ["8500:8500"]
```

### Müqayisə Cədvəli

| Xüsusiyyət | Monolith | Modular Monolith | Microservices |
|-----------|---------|-----------------|---------------|
| Deployment complexity | Aşağı ✓ | Aşağı ✓ | Yüksək ✗ |
| Development sürəti (başlanğıc) | Yüksək ✓ | Yüksək ✓ | Aşağı ✗ |
| Independent scaling | Yoxdur ✗ | Yoxdur ✗ | Var ✓ |
| Database transactions | ACID ✓ | ACID ✓ | Saga/2PC ✗ |
| Team autonomy | Aşağı ✗ | Orta | Yüksək ✓ |
| Debugging | Asan ✓ | Asan ✓ | Çətin (tracing) ✗ |
| Optimal team size | 1-10 nəfər | 5-20 nəfər | 20+ nəfər |
| Operational cost | Aşağı ✓ | Aşağı ✓ | Yüksək ✗ |
| Technology flexibility | Yoxdur | Yoxdur | Var ✓ |
| Fault isolation | Yoxdur ✗ | Qismən | Var ✓ |
| Domain boundary clarity | Zəif | Güclü ✓ | Güclü ✓ |
| Local development | Asan ✓ | Asan ✓ | Çətin (10 service) ✗ |

## Praktik Tapşırıqlar

1. Cari layihənizdə ən çox yüklənən module hansıdır? Onu microservice-ə çıxarmağın faydası/xərci nə olardı? ROI hesablayın.
2. Distributed transaction problemini Saga pattern ilə həll edin — Choreography (event-based) vs Orchestration (central coordinator) müqayisəsi.
3. Monolith-də bir bug-ı debug etmək microservices ilə müqayisədə nə qədər çəkir? Distributed tracing olmadan nə baş verər?
4. "Distributed Monolith" yaratmamaq üçün hansı prinsiplərə əməl etmək lazımdır? Shared DB-nin niyə anti-pattern olduğunu iki konkret nümunə ilə izah edin.
5. Conway's Law-u öz komandanıza tətbiq edin — team structure arxitekturanıza uyğundurmu?
6. Microservices üçün CI/CD pipeline dizayn edin — 10 service-in independent deployment-ı necə idarə olunur? Canary deployment?
7. Bir service down olanda digər service-lərin circuit breaker ilə necə davranacağını simulate edin. PHP-də `Ganesha` library.
8. Strangler Fig pattern tətbiq edin: Laravel monolith-inizdə bir route-u microservice-ə köçürün — API Gateway olaraq Nginx-i konfiqurasiya edin.

## Əlaqəli Mövzular

- `10-strangler-fig.md` — Monolith-dən microservices-ə keçid strategiyası
- `11-saga-pattern.md` — Distributed transactions: Choreography vs Orchestration
- `02-domain-driven-design.md` — Service boundary-ləri necə müəyyən etmək (Bounded Context)
- `07-service-mesh.md` — Microservices infrastructure: Istio, Linkerd
- `06-cqrs-architecture.md` — Microservices ilə birlikdə CQRS pattern
