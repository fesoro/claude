# Strangler Fig Pattern — Legacy Sistem Miqrasiyası

## Mündəricat
1. [Problem: Legacy Sistem](#problem-legacy-sistem)
2. [Strangler Fig nədir?](#strangler-fig-nədir)
3. [Miqrasiya Mərhələləri](#miqrasiya-mərhələləri)
4. [Branch by Abstraction](#branch-by-abstraction)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [Real Nümunə: Monolitdən Mikroservislərə](#real-nümunə)
7. [Risklərin İdarəsi](#risklərin-idarəsi)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Legacy Sistem

```
Legacy sistemlər ölmür — böyüyür və ağırlaşır:

┌─────────────────────────────────────┐
│         Monolithic App              │
│                                     │
│  Orders ──────────────────────────┐ │
│  Payments ─────────────────────┐  │ │
│  Inventory ────────────────┐   │  │ │
│  Users ──────────────────┐ │   │  │ │
│  Reports ──────────────┐ │ │   │  │ │
│                        └─┴─┴───┴──┘ │
│         (tightly coupled)           │
└─────────────────────────────────────┘

Problemlər:
❌ Deploy riski yüksəkdir
❌ Texnologiya borcu artır
❌ Yeni feature əlavə etmək çətindir
❌ Tam rebuild riski: "Big Bang Rewrite" adətən uğursuz olur
```

**Big Bang Rewrite niyə uğursuz olur:**
- Aylar/illər sürür
- Business tələblər dəyişir
- Test coverage çatmır
- Paralel sistemlər diver­gent olur

---

## Strangler Fig nədir?

Strangler Fig — tropik ağac növü. Başqa ağacın ətrafında böyüyür, zamanla onu tamamilə sarıyır.

Martin Fowler 2004-cü ildə bu pattern-i metodoloji kimi təsvir etdi.

```
Mərhələ 1: Yeni sistem paralel işləyir
┌────────────────────────────────────────┐
│              Proxy/Gateway             │
└───────────┬────────────────────────────┘
            │ Bütün traffic
            ▼
┌───────────────────────────────────────┐
│           Legacy System               │
└───────────────────────────────────────┘
   ┌────────────────────┐
   │   New System       │ ← hazırlanır
   └────────────────────┘

Mərhələ 2: Bəzi feature-lər köçürülüb
┌────────────────────────────────────────┐
│              Proxy/Gateway             │
└───────────┬──────────────┬─────────────┘
            │              │
            ▼              ▼
┌───────────────┐  ┌────────────────────┐
│ Legacy System │  │   New System       │
│ (orders,      │  │   (users, auth)    │
│  payments)    │  │                    │
└───────────────┘  └────────────────────┘

Mərhələ 3: Köçürmə tamamdır
┌────────────────────────────────────────┐
│              Proxy/Gateway             │
└────────────────────────────────────────┘
                     │
                     ▼
          ┌────────────────────┐
          │   New System       │
          │   (hər şey)        │
          └────────────────────┘
   ┌───────────────┐
   │ Legacy System │ ← söndürülür
   └───────────────┘
```

---

## Miqrasiya Mərhələləri

```
1. INTERCEPT (Kəsişdirmə)
   → Proxy/facade əlavə et
   → Bütün sorğular proxy-dən keçir
   → Hələ ki, hər şey legacy-ə gedir

2. ROUTE (Yönləndirmə)
   → Yeni komponent üçün yeni sistemi yaz
   → Proxy, yeni komponent üçün yeni sistemə yönləndir
   → Legacy, köhnə komponentlər üçün işləməyə davam edir

3. STRANGLE (Boğma)
   → Bütün komponentlər köçürüldükdən sonra
   → Legacy sistemi söndür
   → Proxy sadəcə yeni sistemə yönləndirir

4. RETIRE (Kənara çək)
   → Legacy kodu arxivlə/sil
   → Proxy-ni sadələşdir və ya sil
```

---

## Branch by Abstraction

Legacy kodu tədricən dəyişdirmək üçün:

```
Addım 1: Abstraction layer yarat
         ┌──────────────────┐
         │  Client Code     │
         └────────┬─────────┘
                  │
         ┌────────▼─────────┐
         │   Interface      │ ← yeni
         └────────┬─────────┘
                  │
         ┌────────▼─────────┐
         │  Legacy Impl     │
         └──────────────────┘

Addım 2: Yeni implementasiya yaz
         ┌──────────────────┐
         │  Client Code     │
         └────────┬─────────┘
                  │
         ┌────────▼─────────┐
         │   Interface      │
         └──────┬─────┬──────┘
                │     │
    ┌───────────▼─┐ ┌──▼────────────┐
    │ Legacy Impl │ │  New Impl     │
    └─────────────┘ └───────────────┘

Addım 3: Traffic-i yeni implə yönləndir (feature flag ilə)
Addım 4: Legacy impl-i sil
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Addım 1: Interface yarat
interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;
    public function save(Order $order): void;
    public function findByCustomerId(int $customerId): array;
}

// Addım 2: Legacy implementasiya (köhnə spaghetti kod)
class LegacyOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        // Köhnə, ugly kod buradadır
        $row = mysql_query("SELECT * FROM tbl_orders WHERE id=$id");
        if (!$row) return null;
        return $this->mapRow(mysql_fetch_assoc($row));
    }
    
    // ...
}

// Addım 3: Yeni implementasiya yaz
class NewOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private Connection $db) {}
    
    public function findById(int $id): ?Order
    {
        $row = $this->db->table('orders')->find($id);
        return $row ? Order::fromArray((array) $row) : null;
    }
    
    public function save(Order $order): void
    {
        $this->db->table('orders')->upsert(
            $order->toArray(),
            ['id'],
            ['status', 'total', 'updated_at']
        );
    }
    
    public function findByCustomerId(int $customerId): array
    {
        return $this->db->table('orders')
            ->where('customer_id', $customerId)
            ->get()
            ->map(fn($row) => Order::fromArray((array) $row))
            ->all();
    }
}

// Addım 4: Feature flag ilə keçid
class OrderRepositoryFactory
{
    public static function create(): OrderRepositoryInterface
    {
        if (config('features.use_new_order_repo')) {
            return app(NewOrderRepository::class);
        }
        return app(LegacyOrderRepository::class);
    }
}
```

**Proxy/Gateway implementasiyası:**

```php
// HTTP Proxy — sorğuları intercept edib yönləndirir
class StranglerProxy
{
    private array $routes = [
        '/api/users'    => 'http://new-service:8001',
        '/api/auth'     => 'http://new-service:8001',
        // Qalanlar legacy-ə gedir
    ];
    
    public function handle(Request $request): Response
    {
        $path = $request->getPathInfo();
        
        foreach ($this->routes as $prefix => $newServiceUrl) {
            if (str_starts_with($path, $prefix)) {
                return $this->forwardToNew($request, $newServiceUrl);
            }
        }
        
        return $this->forwardToLegacy($request);
    }
    
    private function forwardToNew(Request $request, string $baseUrl): Response
    {
        $url = $baseUrl . $request->getRequestUri();
        
        $response = Http::withHeaders($request->headers->all())
            ->send($request->method(), $url, [
                'body' => $request->getContent(),
            ]);
        
        return response($response->body(), $response->status());
    }
    
    private function forwardToLegacy(Request $request): Response
    {
        // Legacy PHP app-a yönləndir
        return $this->forwardToNew($request, config('legacy.base_url'));
    }
}
```

**Feature flag ilə tədricən köçürmə:**

```php
class FeatureFlag
{
    public static function isEnabled(string $feature, ?int $userId = null): bool
    {
        // 1. Tam açıq/qapalı
        $config = config("features.$feature");
        if (is_bool($config)) return $config;
        
        // 2. Faiz əsaslı rollout
        if (isset($config['rollout_percentage'])) {
            $hash = crc32("$feature:$userId") % 100;
            return $hash < $config['rollout_percentage'];
        }
        
        // 3. Müəyyən user-lər üçün
        if (isset($config['user_ids']) && $userId) {
            return in_array($userId, $config['user_ids']);
        }
        
        return false;
    }
}

// İstifadə
class OrderService
{
    public function placeOrder(PlaceOrderData $data): Order
    {
        if (FeatureFlag::isEnabled('new_order_flow', $data->userId)) {
            return $this->newOrderService->place($data);
        }
        return $this->legacyOrderService->place($data);
    }
}
```

---

## Real Nümunə

**E-commerce monolit → mikroservis miqrasiyası:**

```
Monolith:
  /app
  ├── controllers/
  │   ├── OrderController.php
  │   ├── UserController.php
  │   ├── PaymentController.php
  │   └── CatalogController.php
  └── models/
      ├── Order.php
      ├── User.php
      └── Product.php

Miqrasiya planı (ardıcıllıq önəmlidir!):
  Sprint 1: Auth/User service ← az dependency
  Sprint 2: Catalog service ← read-heavy, izolə
  Sprint 3: Order service ← core business
  Sprint 4: Payment service ← ən kritik, son
  
İzolasiya asanlığına görə sıralama:
  User > Catalog > Inventory > Order > Payment
```

**Data miqrasiyası:**

```
Problem: Legacy DB-si yeni DB-dən fərqli schema

Həll: Sync layer
  Legacy DB ──write──► Sync Service ──write──► New DB
                                    ──read───► New DB
  
  Keçid dönəmi:
  - Hər iki DB-yə yazılır (dual write)
  - Oxumaq üçün tədricən yeni DB-yə keçilir
  - Legacy write söndürüldükdən sonra legacy DB arxivlənir
```

---

## Risklərin İdarəsi

```
Risk 1: Data inconsistency (köhnə və yeni DB-nin desinxronizasiyası)
  Həll: Event-driven sync, CDC (Debezium), reconciliation jobs

Risk 2: Paralel sistemlərin maintenance xərci
  Həll: Miqrasiya cədvəlini saxla, hər sprint bir modul bitirsin

Risk 3: Traffic routing xətaları
  Həll: Shadow mode — yeni sistemə paralel göndər, nəticəni müqayisə et

Risk 4: Performance regressions
  Həll: Load testing hər mərhələdə

Risk 5: Rollback çətinliyi
  Həll: Feature flags, blue-green deployment
```

**Shadow Mode (Canary testing üçün):**

```php
class ShadowOrderService
{
    public function place(PlaceOrderData $data): Order
    {
        // Legacy-dən nəticəni al
        $legacyResult = $this->legacyService->place($data);
        
        // Paralel olaraq yeni servisi çağır (async, cavab ignore edilir)
        dispatch(function () use ($data, $legacyResult) {
            try {
                $newResult = $this->newService->place($data);
                
                // Nəticələri müqayisə et (log)
                if ($this->differs($legacyResult, $newResult)) {
                    Log::warning('Shadow mode divergence', [
                        'legacy' => $legacyResult->toArray(),
                        'new'    => $newResult->toArray(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Shadow mode error', ['error' => $e->getMessage()]);
            }
        })->afterResponse();
        
        return $legacyResult;  // Legacy nəticəsini qaytar
    }
}
```

---

## İntervyu Sualları

**1. Strangler Fig pattern nədir, niyə Big Bang Rewrite-a alternativdir?**
Legacy sistemi tədricən yeni sistemlə əvəz edir. Proxy bütün sorğuları intercept edir; köçürülmüş hissələr yeni sistemə yönləndirilir, qalanlar legacy-ə gedir. Big Bang Rewrite riski yüksəkdir (aylar sürür, tələblər dəyişir, test yoxdur). Strangler Fig inkremental, geri döndürülə bilən, hər addımda test edilə bilən.

**2. Branch by Abstraction nədir?**
Legacy kodu birbaşa dəyişdirmək əvəzinə, əvvəlcə interface yaradılır. Legacy implementasiya bu interface-i implement edir. Sonra yeni implementasiya yazılır. Feature flag ilə trafik tədricən yeni implementasiyaya keçirilir. Bu, kodu kompilasiya etmədən deploy etməyə imkan verir.

**3. Data miqrasiyası zamanı dual write necə idarə edilir?**
Keçid dönəmündə hər iki DB-yə yazılır (outbox pattern ilə). Oxuma trafiki tədricən yeni DB-yə keçirilir. Reconciliation job-lar iki DB-ni müqayisə edir. Legacy write söndürüldükdən sonra legacy DB arxivlənir.

**4. Shadow mode nədir?**
Sorğu legacy sistemə göndərilir, nəticə client-ə qaytarılır. Paralel olaraq eyni sorğu yeni sistemə də göndərilir (cavab ignore edilir). Nəticələr müqayisə edilir, divergence log-lanır. Bu sayədə yeni sistem production-da test edilir, amma risk yoxdur.

**5. Miqrasiya ardıcıllığı necə seçilməlidir?**
Az dependency olan modullardan başlanır (auth, catalog). Core business logic (order, payment) sonraya saxlanır. Hər modul izolə olunmuş, ayrı DB-yə sahib olmalıdır. Ən kritik modul (payment) ən sona saxlanır ki, təcrübə qazanılsın.

**6. Strangler Fig-də "Proxy" hansı texniki üsullarla həyata keçirilir?**
1. Nginx `proxy_pass` — path-based routing legacy vs yeni servisə. 2. AWS ALB/API Gateway — header/path routing qaydaları. 3. Laravel middleware — tətbiq daxilindəki routing qatı. 4. Feature Flag servisləri (LaunchDarkly, Unleash) — kod içindən yönləndirmə. Ən etibarlı: infrastructure-level proxy (Nginx/ALB), çünki dil/framework müstəqildir.

**7. "Parallel Run" nədir, Shadow Mode-dan fərqi nədir?**
Parallel Run: hər iki sistem həqiqətən işləyir, nəticələr müqayisə edilir, istifadəçilər Legacy nəticəsini alır. Shadow Mode: yeni sistem paralel çağrılır, nəticəsi LOG-lanır amma istifadəçiyə verilmir. Parallel Run daha güvənlidir — əgər divergence varsa Legacy cavabı qorunur.

---

## Anti-patternlər

**1. Big Bang Rewrite cəhdi**
Legacy sistemi tam dayandırıb sıfırdan yazmaq — aylar ərzində yeni tələblər dəyişir, test yoxdur, risk maksimaldır. Strangler Fig ilə inkremental köçürün: hər modul ayrıca miqrasiya edilsin, hər addım deploy edilib test edilsin.

**2. Proxy olmadan birbaşa keçid**
Trafiki idarə edən proxy qatı olmadan iki sistemin paralel işləməsi mümkün deyil — istifadəçilər ya köhnə, ya yeni sistemə düşür, nəzarətsiz. Facade/proxy qatı qurun ki, routing mərkəzləşsin və trafik tədricən yönləndirilsin.

**3. Data miqrasiyasını sonraya saxlamaq**
Əvvəlcə kodu köçürüb, DB-ni en sona saxlamaq — iki sistem eyni cədvəlləri paylaşır, schema dəyişiklikləri hər ikisini pozur. Hər modulun öz DB-si olsun; dual-write + reconciliation job ilə sinxronizasiya aparılsın.

**4. Shadow mode-u atlamaq**
Yeni sistemi birbaşa production trafikinə vermək — gizli edge case-lər real istifadəçiləri təsir edir. Əvvəlcə shadow mode işlədin: cavablar müqayisə edilsin, divergence log-lansın, yalnız sonra real trafik keçirilsin.

**5. Miqrasiyanı ən kritik moduldan başlamaq**
Payment, auth kimi core modulları ilk köçürmək — təcrübəsiz komanda ən riskli yerdə səhv edir. Az dependency olan periferik modullardan (katalog, bildiriş) başlayın, metodologiyanı öyrənin, kritiki sona saxlayın.

**6. Feature flag olmadan deployment**
Yeni modulun bütün istifadəçilərə birdən açılması — problem çıxanda rollback bütün sistemi dayandırır. Feature flag ilə faiz-faiz aç: 1% → 10% → 50% → 100%, problem olsa dərhal kapat.
