# Anti-Corruption Layer, Sidecar, Ambassador Patterns (Senior)

## Mündəricat
1. [Anti-Corruption Layer (ACL)](#anti-corruption-layer-acl)
2. [Sidecar Pattern](#sidecar-pattern)
3. [Ambassador Pattern](#ambassador-pattern)
4. [API Composition Pattern](#api-composition-pattern)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Anti-Corruption Layer (ACL)

```
Problem:
  Köhnə/xarici sistem öz domain modelini "zorla" qəbul etdirir
  Sizin domain model zədələnir (corrupted)

  Legacy System:           New System:
  "tbl_cust_ord_hdr"  →   Order, Customer domain
  "CUST_NO"           →   customerId
  "ORD_DT"            →   createdAt
  "AMT_TOT_NET"       →   totalAmount

Həll: ACL — Translator layer
  New System ←→ ACL ←→ Legacy System
  
  ACL xarici modeli daxili modelə çevirir
  New domain xarici sistemdən xəbərsizdir

┌────────────────────┐    ┌──────────┐    ┌──────────────────┐
│   Your Domain      │    │   ACL    │    │ External/Legacy  │
│                    │◄──►│(Translator)◄──►│    System        │
│  Order, Customer   │    │          │    │ tbl_cust_ord_hdr │
└────────────────────┘    └──────────┘    └──────────────────┘

Nümunə: Legacy ERP → Microservice inteqrasiyası
        Third-party payment gateway → Domain model
        Köhnə SOAP API → REST microservice
```

---

## Sidecar Pattern

```
Servis ilə eyni pod-da çalışan köməkçi container

┌─────────────────────────────────────┐
│              K8s Pod                │
│                                     │
│  ┌───────────────┐  ┌─────────────┐ │
│  │  Main App     │  │   Sidecar   │ │
│  │  (PHP-FPM)    │  │             │ │
│  │               │  │  Logging    │ │
│  │               │  │  Tracing    │ │
│  │               │  │  mTLS proxy │ │
│  │               │  │  Config     │ │
│  └───────────────┘  └─────────────┘ │
│         localhost network           │
└─────────────────────────────────────┘

Sidecar nə edir:
  Logging: Log collector (Fluentd, Promtail)
  Tracing: OpenTelemetry agent
  mTLS:    Envoy proxy (Istio service mesh)
  Config:  Vault agent (secret injection)
  Metrics: Prometheus exporter

✅ App kodu dəyişmir (cross-cutting concerns sidecar-da)
✅ Polyglot (hər dil ilə işləyir)
✅ Independent update (app restart olmadan sidecar update)
```

---

## Ambassador Pattern

```
Client ilə xarici servis arasında proxy

┌──────────┐    ┌─────────────────┐    ┌──────────────┐
│  Client  │───►│   Ambassador    │───►│   Service    │
│  (App)   │    │                 │    │  (External)  │
│          │◄───│  Retry logic    │◄───│              │
└──────────┘    │  Circuit Break  │    └──────────────┘
                │  Rate limiting  │
                │  Auth injection │
                │  Logging        │
                └─────────────────┘

Client ambassador-a danışır, ambassador xarici servislə.

Fərq Sidecar-dan:
  Sidecar:    eyni pod, inbound traffic
  Ambassador: eyni pod, outbound traffic (client proxy)

Nümunə:
  PHP app → Ambassador (localhost:8001) → Payment Gateway
  Ambassador: retry, timeout, circuit break, auth header inject
```

---

## API Composition Pattern

```
Bir neçə mikroservisin cavabını birləşdir

Client: GET /dashboard
  ↓
API Composer (Gateway/BFF):
  → GET /users/123          (parallel)
  → GET /orders?user=123    (parallel)
  → GET /recommendations/123 (parallel)
  ↓
Merge → { user: {...}, orders: [...], recommendations: [...] }
  ↓
Client: 1 cavab

✅ Client-ə az sorğu
✅ Paralel execution (latency azaldır)
✅ Client sadədir

Partial failure:
  Recommendations servis down → null qaytar, digərləri normal
  → Graceful degradation
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Anti-Corruption Layer
class LegacyOrderACL
{
    // Legacy → Domain
    public function toDomain(array $legacyOrder): Order
    {
        return new Order(
            id:         $legacyOrder['ORD_ID'],
            customerId: $legacyOrder['CUST_NO'],
            total:      (int) ($legacyOrder['AMT_TOT_NET'] * 100), // cents
            status:     $this->mapStatus($legacyOrder['ORD_STS_CD']),
            createdAt:  Carbon::createFromFormat('Ymd', $legacyOrder['ORD_DT']),
        );
    }

    // Domain → Legacy
    public function fromDomain(Order $order): array
    {
        return [
            'ORD_ID'      => $order->id,
            'CUST_NO'     => $order->customerId,
            'AMT_TOT_NET' => $order->total / 100,
            'ORD_STS_CD'  => $this->reverseMapStatus($order->status),
            'ORD_DT'      => $order->createdAt->format('Ymd'),
        ];
    }

    private function mapStatus(string $legacyCode): string
    {
        return match($legacyCode) {
            'N' => 'pending',
            'P' => 'paid',
            'S' => 'shipped',
            'C' => 'cancelled',
            default => throw new \DomainException("Unknown status: $legacyCode"),
        };
    }
}

// ACL Repository wrapper
class LegacyOrderRepository implements OrderRepository
{
    public function __construct(
        private LegacyOrderClient $legacyClient,
        private LegacyOrderACL    $acl,
    ) {}

    public function findById(string $id): ?Order
    {
        $raw = $this->legacyClient->getOrder($id);
        return $raw ? $this->acl->toDomain($raw) : null;
    }

    public function save(Order $order): void
    {
        $this->legacyClient->saveOrder($this->acl->fromDomain($order));
    }
}

// Ambassador pattern — outbound proxy
class PaymentGatewayAmbassador
{
    private CircuitBreaker $cb;
    private RetryPolicy    $retry;

    public function charge(array $data): array
    {
        return $this->retry->execute(function () use ($data) {
            return $this->cb->call(function () use ($data) {
                return Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'X-Idempotency-Key' => $data['idempotency_key'],
                ])
                ->timeout(10)
                ->post(config('payment.gateway_url') . '/charges', $data)
                ->throw()
                ->json();
            });
        });
    }

    private function getAccessToken(): string
    {
        return Cache::remember('payment:token', 3500, fn() =>
            Http::post(config('payment.token_url'), [
                'client_id'     => config('payment.client_id'),
                'client_secret' => config('payment.client_secret'),
            ])->json('access_token')
        );
    }
}

// API Composition
class DashboardComposer
{
    public function compose(int $userId): array
    {
        $results = Http::pool(fn($pool) => [
            $pool->as('user')->get("/internal/users/$userId"),
            $pool->as('orders')->get("/internal/orders?user_id=$userId&limit=5"),
            $pool->as('recommendations')->get("/internal/recommendations/$userId"),
        ]);

        return [
            'user'            => $results['user']->ok() ? $results['user']->json() : null,
            'recent_orders'   => $results['orders']->ok() ? $results['orders']->json() : [],
            'recommendations' => $results['recommendations']->ok()
                ? $results['recommendations']->json() : [],
        ];
    }
}
```

---

## İntervyu Sualları

**1. Anti-Corruption Layer nədir, nə zaman lazımdır?**
Xarici/legacy sistemin domain modelinin öz sisteminizə sirayət etməsini önləyən translation layer. Legacy sistem öz field adları, statusları, strukturu ilə gəlir — ACL bunları domain modelinizə çevirir. Köhnə ERP, third-party API inteqrasiyalarında vacibdir.

**2. Sidecar vs Ambassador fərqi nədir?**
Sidecar: eyni pod-da inbound traffic üçün köməkçi (logging, mTLS, metrics). Ambassador: eyni pod-da outbound traffic proxy-si (retry, circuit break, auth injection). İkisi də cross-cutting concerns-i app kodundan ayırır.

**3. API Composition partial failure necə idarə edilir?**
Paralel sorğularda bir servis fail olarsa digərlərini bloklamamalıdır. Her servis üçün ayrı error handling: fail → null/empty array qaytar. Client partial data ilə işləməyi dəstəkləməlidir. Timeout-lar müstəqil olmalıdır.

**4. Sidecar pattern-in üstünlüyü nədir?**
App kodu dəyişmir (cross-cutting concerns sidecar-da). Polyglot: PHP, Java, Go — eyni sidecar işləyir. Independent lifecycle: sidecar app restart olmadan update edilir. Service mesh (Istio/Linkerd) sidecar pattern üzərindədir.

**5. ACL nə zaman Gateway əvəzinə kullanılır?**
API Gateway: giriş nöqtəsindəki xarici API-lər üçün. ACL: bounded context-lərin daxilindəki inteqrasiya nöqtəsindədir — legacy ERP-dən data oxuyanda. ACL domain-in özündə, Gateway xaricdədir. İkisi birlikdə də mövcud ola bilər.

**6. BFF (Backend For Frontend) pattern nədir?**
API Composition-un xüsusi forması. Hər frontend tipi üçün ayrı backend: Mobile BFF (az data, mobil-ə uyğun), Web BFF (tam data), Smart TV BFF. Hər BFF öz downstream service-lərini compose edir. GraphQL BFF kimi istifadə edilə bilər.

**7. Istio service mesh-i sidecar ilə necə işləyir?**
Istio hər pod-a avtomatik Envoy proxy sidecar inject edir (mutating webhook). Bütün servis-to-servis traffic bu proxy-dən keçir: mTLS, circuit break, retry, traffic shifting, observability — app kodu heç nə bilmir. Control plane (istiod) proxy-ləri konfigurasiya edir.

---

## Anti-patternlər

**1. ACL olmadan xarici sistemin modelini birbaşa domain-ə daxil etmək**
Ödəniş provayderinin `txn_status: 3` kimi dəyərlərini bütün kod bazasında istifadə etmək — provayder API dəyişəndə hər yeri düzəltmək lazım olur, xarici model domain-i çirkləndirir. ACL ilə xarici modeli öz ubiquitous language-ınıza çevirin: `PaymentStatus::CONFIRMED` domain modelinizin dili ilə.

**2. ACL-i çox "thin" yazmaq**
ACL yalnız field adlarını çevirir, business mapping yoxdur — xarici `status: "PENDING_REVIEW"` kodun hər yerinə sızır. ACL həm data çevirməli, həm xarici konseptləri domain konseptlərinə mapping etməlidir; translation tam olsun, yarım çevirmə korlanmış model yaradır.

**3. Sidecar-ı production-da test etmədən deploy etmək**
Sidecar proxy-si əlavə edilir, lakin latency, timeout əlavəsi test edilmir — sidecar özü gecikməyə səbəb olur, başqa servislərin timeout-larını keçir. Sidecar ilə tam integration testi aparın: latency overhead-i ölçün, timeout-ları sidecar overhead-ini nəzərə alaraq tənzimləyin.

**4. Ambassador pattern-i olmayan servislərlə "outbound proxy" qarışdırmaq**
Hər servis öz retry, circuit breaker, auth logic-ini özündə yazır — kod dublikatı, müxtəlif davranış, mərkəzi qorunma yoxdur. Ambassador pattern-i ilə outbound cross-cutting concern-ləri mərkəzləşdirin; hər servis sadəcə ambassador-a müraciət etsin.

**5. API Composition-da partial failure-ı işləməmək**
Bir upstream servis xəta verəndə bütün composition uğursuz sayılır — istifadəçi tam cavab ala bilmir, digər sağlam servislər boş gedir. Hər upstream sorğusu müstəqil timeout və fallback alsın; partial failure-da mövcud data ilə cavab formalaşdırılsın.

**6. Sidecar-ı hər servisə default əlavə etmək**
Sadə, low-traffic servislərə də sidecar qoşmaq — resource istifadəsi artır, deployment complexity genişlənir, service mesh overhead hər servisə yük olur. Sidecar-ı yalnız real ehtiyac olduqda tətbiq edin: cross-cutting concerns kritik olduqda, polyglot mühitdə, ya da service mesh artıq mövcud olduqda.
