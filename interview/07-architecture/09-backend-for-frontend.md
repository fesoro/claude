# Backend for Frontend (BFF) (Lead ⭐⭐⭐⭐)

## İcmal
Backend for Frontend (BFF) pattern-i — hər frontend (web, mobile, third-party) üçün ayrı backend layer yaradan arxitektura yanaşmasıdır. Sam Newman tərəfindən popularlaşdırılmışdır. BFF arxitekturası mürəkkəb microservices sistemlərini müxtəlif client növlərindən izole edir. Interview-da bu mövzu API Gateway vs BFF fərqini, data aggregation strategiyasını, partial failure handling-i bilməyinizi ölçür.

## Niyə Vacibdir
Müxtəlif client-lər fərqli data formatlarına, fərqli granularity-ə ehtiyac duyur. Mobile app az data istəyir (bandwidth məhdud), web daha çox, SmartTV çox sadə format istəyir. Tək ümumi API istifadə etdikdə ya over-fetching (mobil lazımsız data alır), ya under-fetching (web çox request göndərir) yaranır. BFF hər client-in ehtiyacına uyğun xüsusi cavab verir, backend microservice-ləri isə toxunulmaz qalır. Frontend team-ə autonomy verir — BFF-i özləri idarə edir.

## Əsas Anlayışlar

- **BFF**: Hər client növü üçün ayrı lightweight backend. Web BFF, Mobile BFF, Third-party Partner BFF. Hər BFF özü bir microservice-dir, amma client-specific logic daşıyır.

- **API Gateway vs BFF fərqi**: API Gateway — cross-cutting concerns üçün (auth, rate limiting, SSL termination, routing). BFF — client-specific aggregation, transformation, composition. Bunlar ayrı layer-lardır, birini digəri ilə əvəz etmirsiniz.

- **Aggregation**: Bir BFF sorğusu üçün bir neçə microservice-i çağırıb cavabları birləşdirmək. Product page üçün: Product Service + Inventory Service + Pricing Service + Review Service — dörd call, bir response.

- **Data transformation**: Microservice cavabını client-in gözlədiyi formata uyğunlaşdırmaq. Mobile üçün yalnız `first_name` + `avatar_thumb`, web üçün tam profil məlumatı.

- **Over-fetching həlli**: Mobile BFF yalnız mobil-a lazım olan field-ları qaytarır. Gereksiz data network-ü doldurmur, battery-ni boşaltmır.

- **Under-fetching həlli**: Web BFF bir sorğuda bütün lazımi məlumatı toplar. Bir HTTP round-trip əvəzinə N round-trip lazım olmur.

- **GraphQL alternatiви**: BFF əvəzinə tək GraphQL endpoint — client özü lazım olan field-ları seçir. Trade-off: GraphQL daha güclü, amma complexity artır, server-side caching çətindir. BFF daha sadədir, team ownership aydındır.

- **Team ownership**: BFF-i əsasən frontend team (ya full-stack team) idarə edir. Backend microservice team-ləri ilə koordinasiya azalır. "You build it, you run it" prinsipi.

- **Parallel requests**: BFF bir neçə microservice-i eyni anda çağırır, ən yavaş birinin cavabını gözləyir, birləşdirir. Sequential yerinə parallel — latency kəskin azalır.

- **Cache layer**: BFF-də aggregated response-u cache etmək — backend microservice-lərinə yük azalır. Invalidation strategiyası: time-based TTL ya da event-based purge.

- **Partial response (Degraded mode)**: Bir microservice xəta versə BFF partial response qaytara bilər. "Recommendation service down, amma product data var" — istifadəçi boş səhifə görməz.

- **Circuit Breaking**: Microservice cavab vermirsə BFF cached response ya da default qaytarır. Hystrix, Resilience4j, ya da PHP-də Ganesha library.

- **Token exchange**: External auth token-i (JWT) internal service token-ə çevirmək BFF-də baş verir. Internal service-lər kənara çıxmır, BFF boundary-dəki auth-u idarə edir.

- **BFF-in yüngül olması**: BFF çox az logic saxlamalıdır — aggregation, transformation, caching, error handling. Ağır biznes logic microservice-lərə aid. BFF-ə business rule kodu yazmaq anti-pattern-dir.

- **Versioning**: Mobile app-lar tez-tez yenilənmir — köhnə app version-ları BFF-in köhnə endpoint-lərini istifadə edir. BFF versioning idarəsini asanlaşdırır: `/v1/mobile/profile` vs `/v2/mobile/profile`.

- **Service mesh ilə əlaqə**: BFF → Microservice arası east-west traffic-i service mesh idarə edir. mTLS, retry policy, circuit breaker — service mesh layer-ında idarə olunur.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
BFF mövzusunda API Gateway ilə fərqi aydın izah etmək vacibdir — əksər namizəd bunları qarışdırır. Sonra konkret nümunə: "E-commerce-də product page üçün Mobile BFF 4 microservice-i parallel çağırır." Partial response, circuit breaking, caching strategiyasını bilmək artı. GraphQL alternativini və onun BFF ilə müqayisəsini də bilmək əla göstəricilər.

**Junior-dan fərqlənən senior cavabı:**
Junior: "BFF hər client üçün ayrı backend-dir."
Senior: "Mobile BFF parallel async requests ilə 4 microservice-i 200ms-də sorğulayır. Biri timeout olsa cached response qaytarırıq. Web BFF-dən fərqli olaraq mobile-da image field-lar thumbnail versiyaları qaytarır."
Lead: "BFF-in team ownership modeli vacibdir — frontend team öz BFF-ini idarə edir, backend team-ə dependency azalır. Contract testing ilə BFF-microservice inteqrasiyasını verify edirik."

**Follow-up suallar:**
- "BFF neçə versiyası olmalıdır — hər client üçün ayrı, yoxsa tək?"
- "Microservice-lərdən biri down olanda BFF necə davranır?"
- "BFF-i kim idarə etməlidir — backend team yoxsa frontend team?"
- "GraphQL BFF-in yerini ala bilərmi? Trade-off-lar nədir?"
- "BFF-in özü scale etmək lazımdırsa nə edirsən?"
- "BFF caching invalidation-ı necə idarə edirsiniz?"

**Ümumi səhvlər:**
- BFF-ə çox biznes məntiq toplamaq — BFF thin olmalıdır
- Hər microservice üçün ayrı BFF yaratmaq — bu BFF-in məqsədi deyil
- API Gateway ilə BFF-i eyni layer hesab etmək
- Parallel request-ləri sequential etmək — latency artır
- BFF-dəki cache invalidation-ı düşünməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab konkret use case ilə izah edir, parallel request-ləri göstərir, error handling strategiyasını (partial response, fallback, circuit breaker) bilir, GraphQL ilə müqayisə edir, team ownership modelini izah edir.

## Nümunələr

### Tipik Interview Sualı
"API Gateway və BFF arasındakı fərqi izah edin. Nə zaman BFF istifadə etmək lazımdır?"

### Güclü Cavab
"API Gateway bütün client-lər üçün ümumi qapıdır — auth verification, rate limiting, SSL termination, routing. BFF isə müəyyən client növü üçün xüsusi aggregation layer-dir. Hər ikisi lazımdır, biri digərini əvəz etmir. Mobile app-da istifadəçi profil səhifəsi açanda User Service, Orders Service, Notifications Service, Loyalty Service-dən data lazımdır. Mobile BFF bu dörd service-i parallel async çağırır, minimal lazım olan field-ları seçib birləşdirir, mobile-optimized format qaytarır — bir HTTP call ilə. Web BFF eyni microservice-lərdən daha çox data alır, tam user object qaytarır. Bu şəkildə mobile bandwidth qorunur, backend microservice-ləri dəyişmir. GraphQL güclü alternativdir, lakin server-side caching çətin olur, BFF-in team ownership modeli daha sadədir."

### Arxitektura Diaqramı

```
                    ┌─────────────────────────────────────┐
                    │     API Gateway (Nginx / Kong)       │
                    │   auth, rate limit, SSL, routing     │
                    └──────────────┬──────────────────────┘
                                   │
              ┌────────────────────┼────────────────────┐
              │                    │                    │
              ▼                    ▼                    ▼
    ┌──────────────┐    ┌──────────────────┐  ┌─────────────────┐
    │  Mobile BFF  │    │    Web BFF       │  │  Partner BFF    │
    │  (minimal    │    │  (full data,     │  │  (API keys,     │
    │   payload)   │    │   rich format)   │  │   rate limited) │
    └──────┬───────┘    └────────┬─────────┘  └───────┬─────────┘
           │                    │                     │
           └────────────────────┼─────────────────────┘
                                │
          ┌─────────────────────┼──────────────────────┐
          │                     │                      │
          ▼                     ▼                      ▼
  ┌──────────────┐     ┌──────────────┐      ┌──────────────────┐
  │ User Service │     │ Order Service│      │ Product Service  │
  │ (own DB)     │     │ (own DB)     │      │ (own DB)         │
  └──────────────┘     └──────────────┘      └──────────────────┘
          │                     │                      │
          ▼                     ▼                      ▼
  ┌──────────────┐     ┌──────────────┐      ┌──────────────────┐
  │  PostgreSQL  │     │  PostgreSQL  │      │   MongoDB        │
  └──────────────┘     └──────────────┘      └──────────────────┘
```

### Kod Nümunəsi

```php
// Mobile BFF — Laravel Lumen/Slim ilə
// app/Http/Controllers/Mobile/ProfileController.php

class MobileProfileController extends Controller
{
    public function __construct(
        private UserServiceClient $userService,
        private OrderServiceClient $orderService,
        private NotificationServiceClient $notificationService,
        private LoyaltyServiceClient $loyaltyService,
        private CacheManager $cache
    ) {}

    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $cacheKey = "mobile_profile:{$userId}";

        // Cache-dən yoxla
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        // Parallel async requests — Guzzle Promise ilə
        $promises = [
            'user'          => $this->userService->getUserAsync($userId),
            'recent_orders' => $this->orderService->getRecentAsync($userId, limit: 3),
            'unread_count'  => $this->notificationService->getUnreadCountAsync($userId),
            'loyalty'       => $this->loyaltyService->getPointsAsync($userId),
        ];

        // Bütün cavabları parallel gözlə
        $results = GuzzleHttp\Promise\Utils::settle(
            array_map(fn($p) => $p, $promises)
        )->wait();

        // Mobile üçün minimal response — bandwidth qorunur
        $response = [
            'user' => $this->extractUserForMobile($results['user']),
            'recent_orders' => $this->extractOrdersForMobile($results['recent_orders']),
            'notifications' => $this->extractNotificationsForMobile($results['unread_count']),
            'loyalty' => $this->extractLoyaltyForMobile($results['loyalty']),
            'meta' => [
                'degraded' => $this->hasFailures($results),
                'cached_at' => null,
            ],
        ];

        $this->cache->put($cacheKey, $response, ttl: 60); // 60 saniyə cache

        return response()->json($response);
    }

    private function extractUserForMobile(array $result): array
    {
        if ($result['state'] !== 'fulfilled') {
            return ['error' => 'unavailable'];
        }
        $user = $result['value'];
        // Mobile-da lazım olan minimal data
        return [
            'id'     => $user['id'],
            'name'   => $user['first_name'],   // tam ad yox, yalnız first name
            'avatar' => $user['avatar_thumb'],  // 64px thumbnail, 200KB deyil
        ];
    }

    private function extractOrdersForMobile(array $result): array
    {
        if ($result['state'] !== 'fulfilled') {
            return [];  // Fallback: boş array
        }
        return collect($result['value'])->map(fn($o) => [
            'id'     => $o['id'],
            'status' => $o['status'],
            'total'  => $o['total'],
            // mobile-da lazım olmayan onlarca field buraxılır
        ])->toArray();
    }

    private function hasFailures(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['state'] === 'rejected') return true;
        }
        return false;
    }
}

// Web BFF — daha çox data, richer format
class WebProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Web üçün daha çox parallel request — daha çox data lazımdır
        $promises = [
            'user'            => $this->userService->getUserAsync($userId),
            'all_orders'      => $this->orderService->getAllAsync($userId),
            'notifications'   => $this->notificationService->getAllAsync($userId),
            'addresses'       => $this->userService->getAddressesAsync($userId),
            'payment_methods' => $this->paymentService->getMethodsAsync($userId),
            'loyalty'         => $this->loyaltyService->getFullProfileAsync($userId),
        ];

        $results = GuzzleHttp\Promise\Utils::unwrap($promises);
        // Web-də daha az forgiveness — critical data failure = error

        return response()->json([
            'user'            => $results['user'],            // tam user data
            'orders'          => $results['all_orders'],       // tam siyahı + pagination
            'notifications'   => $results['notifications'],
            'addresses'       => $results['addresses'],
            'payment_methods' => $results['payment_methods'],
            'loyalty'         => $results['loyalty'],
        ]);
    }
}

// Circuit Breaker ilə BFF — Ganesha library
class ProductBff
{
    public function __construct(
        private Ackintosh\Ganesha $circuitBreaker,
        private CacheManager $cache
    ) {}

    public function getProductPage(string $productId): array
    {
        $result = [
            'product'        => null,
            'inventory'      => null,
            'reviews'        => null,
            'recommendations' => [],
        ];

        // Critical: product data olmadan page göstərilə bilməz
        if (!$this->circuitBreaker->isAvailable('product-service')) {
            throw new ServiceUnavailableException('Product service is down');
        }
        $result['product'] = $this->fetchProduct($productId);

        // Non-critical: circuit breaker açıqsa cached ya da default
        if ($this->circuitBreaker->isAvailable('inventory-service')) {
            try {
                $result['inventory'] = $this->fetchInventory($productId);
                $this->circuitBreaker->success('inventory-service');
            } catch (\Exception $e) {
                $this->circuitBreaker->failure('inventory-service');
                $result['inventory'] = $this->cache->get("inventory:{$productId}");
            }
        }

        // Non-critical: recommendations service down olsa boş array
        if ($this->circuitBreaker->isAvailable('recommendation-service')) {
            try {
                $result['recommendations'] = $this->fetchRecommendations($productId);
                $this->circuitBreaker->success('recommendation-service');
            } catch (\Exception $e) {
                $this->circuitBreaker->failure('recommendation-service');
                // Fallback: boş array — istifadəçi recommendations görmür, amma page açılır
            }
        }

        return $result;
    }
}
```

```yaml
# BFF Nginx Gateway konfiqurasiyası
upstream web-bff {
    server web-bff-1:8080 weight=3;
    server web-bff-2:8080 weight=3;
    keepalive 100;
}

upstream mobile-bff {
    server mobile-bff-1:8080 weight=5;
    server mobile-bff-2:8080 weight=5;
    keepalive 200;
}

server {
    listen 443 ssl http2;

    # Auth middleware — bütün BFF-lər üçün
    auth_request /auth/verify;

    location /api/web/ {
        proxy_pass http://web-bff/;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        add_header X-BFF-Type "web";
    }

    location /api/mobile/ {
        proxy_pass http://mobile-bff/;
        proxy_http_version 1.1;
        # Mobile-specific rate limiting
        limit_req zone=mobile_zone burst=30 nodelay;
        add_header X-BFF-Type "mobile";
    }

    location /api/partner/ {
        proxy_pass http://partner-bff/;
        # Partner API üçün daha strict rate limiting
        limit_req zone=partner_zone burst=10;
        add_header X-BFF-Type "partner";
    }
}
```

### Müqayisə Cədvəli — BFF vs API Gateway vs GraphQL

| Xüsusiyyət | API Gateway | BFF | GraphQL |
|-----------|------------|-----|---------|
| Məqsəd | Cross-cutting concerns | Client-specific aggregation | Flexible queries |
| Client flexibility | Aşağı | Orta | Yüksək |
| Server-side caching | Asan | Asan | Çətin |
| Team ownership | Platform team | Frontend/fullstack team | Backend team |
| N+1 problem | Yoxdur | Yoxdur | Var (DataLoader lazımdır) |
| Versioning | Path/header based | Multiple BFF versions | Schema versioning |
| Complexity | Aşağı | Orta | Yüksək |
| Ideal use case | Auth, routing | Multiple clients | Data exploration |

## Praktik Tapşırıqlar

1. E-commerce product page üçün Mobile BFF yazın — 4 microservice-dən parallel data toplasın, caching əlavə edin.
2. Bir microservice down olanda partial response strategiyası implement edin — hansı data kritik, hansı optional?
3. Web BFF vs Mobile BFF response boyutlarını benchmark edin — bandwidth neçə % azaldı?
4. Circuit breaking BFF-ə əlavə edin — failure threshold, cooldown period konfiqurasiya edin.
5. GraphQL ilə BFF-in trade-off analizini aparın: caching, complexity, team ownership.
6. BFF-də rate limiting implement edin — hər istifadəçi üçün fərqli limit (premium vs free).
7. Token exchange flow implement edin: JWT → internal service token.
8. Partner BFF üçün API key authentication əlavə edin, webhook notification implement edin.

## Əlaqəli Mövzular

- `08-api-first-design.md` — API design principles
- `07-service-mesh.md` — BFF + Service Mesh east-west traffic
- `01-monolith-vs-microservices.md` — BFF microservices kontekstində
- `06-cqrs-architecture.md` — BFF Query tərəfi kimi
