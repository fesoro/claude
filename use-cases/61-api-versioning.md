# API Versioning Strategy (Middle)

## Problem Təsviri

Production-da olan bir API-niz var. Mobile app v1.0 istifadəçiləri hələ köhnə endpoint-ləri çağırır. Partner şirkətlərin SLA müqavilələri var — xəbərdarlıq olmadan response strukturunu dəyişə bilməzsiniz. Yeni tələblər üçün field-ları dəyişmək, endpoint-ləri silmək və ya response formatını yeniləmək lazımdır.

```
v1 API:           GET /api/orders → { "customer_name": "Ali Həsənov" }
v2 tələb:         GET /api/orders → { "customer": { "full_name": "Ali Həsənov", "id": 42 } }
Problem:          Köhnə client-lər `customer_name` field-ını gözləyir.
                  Dəyişsəniz → crash, 500 error, partner SLA pozulur.
```

### Problem niyə yaranır?

API-ni versiyalamadan yazanda hər dəyişiklik bütün client-ləri potensial olaraq pozur. Aşağıdakı dəyişikliklər xüsusilə təhlükəlidir:

- **Field adını dəyişmək** — `customer_name` → `full_name`: köhnə client null alır, crash edir
- **Field-ı silmək** — client həmin field-a etibar edirsə, NullPointerException
- **Endpoint-i silmək** — 404, partner integration pozulur
- **Response strukturunu dəyişmək** — `[]` array → `{ data: [] }` wrapping əlavə etmək

Mobile app-lar xüsusilə problemlidir: user update etməsə, 2019-cu ildən qalan app hələ sizin API-nizi çağırır. Versiyalama olmadan bu müştəriləri ya məcburi update-ə sürürsünüz (churn risk) ya da API-ni dondurursunuz (feature freeze).

---

## Əsas Strategiyalar

### 1. URL Versioning (ən çox istifadə olunan)

Versiya URL path-ında göstərilir: `/api/v1/orders`, `/api/v2/orders`.

**Niyə populyardır:** Açıq, aydın, log-da görünür, browser-da test etmək asandır, cache-friendly.

```php
// routes/api.php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V2;
use App\Http\Middleware\DeprecationMiddleware;

// V1 — aktiv, köhnə
Route::prefix('v1')
    ->middleware(['api', 'throttle:api', DeprecationMiddleware::class . ':v1'])
    ->name('api.v1.')
    ->group(function () {
        Route::apiResource('orders', V1\OrderController::class);
        Route::apiResource('users', V1\UserController::class);
    });

// V2 — cari aktiv versiya
Route::prefix('v2')
    ->middleware(['api', 'throttle:api'])
    ->name('api.v2.')
    ->group(function () {
        Route::apiResource('orders', V2\OrderController::class);
        Route::apiResource('users', V2\UserController::class);
    });
```

**Shared service, fərqli controller:** Hər versiyanın ayrı controller-i olur, amma business logic ortaq `Service` layer-ında qalır:

```
app/Http/Controllers/Api/
├── V1/
│   ├── OrderController.php   ← V1-ə məxsus format
│   └── UserController.php
├── V2/
│   ├── OrderController.php   ← V2-ə məxsus format
│   └── UserController.php
app/Services/
└── OrderService.php          ← Hər iki versiya istifadə edir
app/Http/Resources/
├── V1/
│   └── OrderResource.php     ← Köhnə format
└── V2/
    └── OrderResource.php     ← Yeni format
```

---

### 2. Accept Header Versioning

Versiya HTTP `Accept` header-ında göndərilir. REST-in "düzgün" yolu sayılır — URL resource-u, header isə representation-ı müəyyən edir.

```
Accept: application/vnd.myapp.v2+json
```

```php
// app/Http/Middleware/ApiVersionMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionMiddleware
{
    private const SUPPORTED_VERSIONS = ['v1', 'v2'];
    private const DEFAULT_VERSION = 'v1';

    public function handle(Request $request, Closure $next): Response
    {
        $version = $this->extractVersion($request);

        if (!in_array($version, self::SUPPORTED_VERSIONS)) {
            return response()->json([
                'error' => 'Dəstəklənməyən API versiyası.',
                'supported_versions' => self::SUPPORTED_VERSIONS,
            ], 400);
        }

        // Route/controller qərarda istifadə etmək üçün request-ə əlavə edirik
        $request->attributes->set('api_version', $version);

        return $next($request);
    }

    private function extractVersion(Request $request): string
    {
        // Accept: application/vnd.myapp.v2+json
        $accept = $request->header('Accept', '');

        if (preg_match('/application\/vnd\.myapp\.(v\d+)\+json/', $accept, $matches)) {
            return $matches[1];
        }

        return self::DEFAULT_VERSION;
    }
}
```

```php
// routes/api.php — Accept header ilə tək endpoint qrupu
Route::middleware(['api', 'throttle:api', ApiVersionMiddleware::class])
    ->group(function () {
        Route::apiResource('orders', OrderController::class);
    });
```

```php
// app/Http/Controllers/OrderController.php
namespace App\Http\Controllers;

use App\Http\Resources\V1\OrderResource as V1OrderResource;
use App\Http\Resources\V2\OrderResource as V2OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request)
    {
        $version = $request->attributes->get('api_version', 'v1');
        $orders = $this->orderService->getOrders();

        return match ($version) {
            'v2'    => V2OrderResource::collection($orders),
            default => V1OrderResource::collection($orders),
        };
    }
}
```

---

### 3. Custom Header Versioning

Ayrıca `X-API-Version` header-i ilə versiya göndərilir. Accept header yanaşmasından sadədir, lakin standart deyil.

```
X-API-Version: 2
```

```php
// app/Http/Middleware/CustomHeaderVersionMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomHeaderVersionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = (int) $request->header('X-API-Version', 1);

        $request->attributes->set('api_version', "v{$version}");

        return $next($request);
    }
}
```

---

### 4. Query Parameter (anti-pattern)

`GET /api/orders?version=2` — sadədir, lakin tövsiyə edilmir:

- Cache poisoning riski (`?version=1` vs `?version=2` fərqli cache entry-ləri)
- Proxy və CDN-lər query string-i ignore edə bilər
- REST semantikasını pozur — query param filter üçündür, versiya üçün deyil
- Swagger/OpenAPI documentation-ı çətinləşir

---

## Breaking vs Non-Breaking Changes

### Non-Breaking (Backward Compatible)

Köhnə client-lər bu dəyişikliklərdən zərər görmür:

| Dəyişiklik | Nümunə |
|------------|--------|
| Yeni optional field əlavə etmək | `{ "status": "active", "created_at": "..." }` → yeni `updated_at` əlavə |
| Yeni optional query param | `?include=tags` əlavə etmək |
| Yeni endpoint əlavə etmək | `GET /api/orders/{id}/timeline` |
| Mövcud field-a default dəyər əlavə etmək | `"currency": "AZN"` əlavə |
| Error message-ı dəyişmək | Human-readable mətn dəyişikliyi |
| Performance təkmilləşdirmə | Eyni cavab, daha sürətli |

### Breaking (Versiya tələb edir)

| Dəyişiklik | Nəticə |
|------------|--------|
| Field adını dəyişmək | `customer_name` → `full_name`: köhnə client null alır |
| Field-ı silmək | Client `undefined`/null alır, crash |
| Response strukturunu dəyişmək | `[]` → `{ data: [], meta: {} }` |
| Field tipini dəyişmək | `"id": 42` → `"id": "42"` (int → string) |
| Endpoint-i silmək | 404 error |
| Authentication metodunu dəyişmək | API key → JWT: bütün clientlər pozulur |
| HTTP method dəyişikliyi | `POST /pay` → `PUT /pay` |
| Required field əlavə etmək | Köhnə client bu field-ı göndərmir → 422 |

---

## Deprecation Strategy

Köhnə versiyaları silməzdən əvvəl müştərilərə kifayət qədər xəbərdarlıq verilməlidir. Standart yanaşma HTTP response header-ları ilə başlayır.

### Sunset və Deprecation Header-ları

- **`Sunset`** (RFC 8594) — endpoint-in rəsmi olaraq bağlanacağı tarix
- **`Deprecation`** — endpoint-in köhnəldiyini bildirən tarix

```
HTTP/1.1 200 OK
Deprecation: Mon, 01 Jan 2025 00:00:00 GMT
Sunset: Wed, 31 Dec 2025 23:59:59 GMT
Link: <https://api.myapp.com/docs/migration/v1-to-v2>; rel="deprecation"
```

### DeprecationMiddleware

```php
// app/Http/Middleware/DeprecationMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DeprecationMiddleware
{
    /**
     * Versiya konfiqurasiyası.
     * 'sunset' — endpoint-in bağlanacağı tarix (RFC 7231 formatı).
     * 'deprecated_at' — deprecated elan edildiyi tarix.
     * 'migration_url' — migration guide linki.
     */
    private const VERSION_CONFIG = [
        'v1' => [
            'deprecated_at' => 'Mon, 01 Jan 2025 00:00:00 GMT',
            'sunset'        => 'Wed, 31 Dec 2025 23:59:59 GMT',
            'migration_url' => 'https://api.myapp.com/docs/migration/v1-to-v2',
        ],
    ];

    public function handle(Request $request, Closure $next, string $version): Response
    {
        $response = $next($request);

        if (!isset(self::VERSION_CONFIG[$version])) {
            return $response;
        }

        $config = self::VERSION_CONFIG[$version];

        // Deprecation header-larını əlavə et
        $response->headers->set('Deprecation', $config['deprecated_at']);
        $response->headers->set('Sunset', $config['sunset']);
        $response->headers->set(
            'Link',
            "<{$config['migration_url']}>; rel=\"deprecation\""
        );

        // Hər deprecated çağırışı log et
        Log::warning('Deprecated API version called', [
            'version'    => $version,
            'path'       => $request->path(),
            'method'     => $request->method(),
            'ip'         => $request->ip(),
            'user_id'    => $request->user()?->id,
            'user_agent' => $request->userAgent(),
            'sunset'     => $config['sunset'],
        ]);

        return $response;
    }
}
```

### Middleware Qeydiyyatı (Laravel 11)

```php
// bootstrap/app.php
use App\Http\Middleware\DeprecationMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'api.deprecation' => DeprecationMiddleware::class,
        ]);
    })
    ->create();
```

### API Consumer-lərinə Email Bildirişi

Deprecated versiyaları kim istifadə edir? Bunu izləyib onlara məktub göndərmək vacibdir:

```php
// app/Jobs/NotifyDeprecatedApiUsersJob.php
namespace App\Jobs;

use App\Mail\ApiDeprecationNoticeMail;
use App\Models\ApiKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class NotifyDeprecatedApiUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $version,
        private string $sunsetDate
    ) {}

    public function handle(): void
    {
        // Son 30 gündə deprecated versiyadan istifadə edən unikal API key-lər
        $affectedKeys = ApiKey::whereHas('requestLogs', function ($query) {
            $query->where('api_version', $this->version)
                  ->where('created_at', '>=', now()->subDays(30));
        })->with('user')->get();

        foreach ($affectedKeys as $apiKey) {
            Mail::to($apiKey->user->email)
                ->queue(new ApiDeprecationNoticeMail(
                    version: $this->version,
                    sunsetDate: $this->sunsetDate,
                    migrationGuideUrl: 'https://api.myapp.com/docs/migration/v1-to-v2'
                ));
        }
    }
}
```

```php
// Scheduler ilə hər həftə xatırlatma
// bootstrap/app.php və ya routes/console.php
use App\Jobs\NotifyDeprecatedApiUsersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new NotifyDeprecatedApiUsersJob('v1', 'Dec 31, 2025'))
    ->weeklyOn(1, '09:00') // Hər bazar ertəsi
    ->onOneServer();
```

---

## Tam Laravel Implementation

### V1 Order Resource

```php
// app/Http/Resources/V1/OrderResource.php
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'customer_name' => $this->customer->full_name,   // V1: flat field
            'customer_email'=> $this->customer->email,
            'total'         => $this->total_amount,
            'status'        => $this->status,
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}
```

### V2 Order Resource

```php
// app/Http/Resources/V2/OrderResource.php
namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'customer' => [                                   // V2: nested object
                'id'        => $this->customer->id,
                'full_name' => $this->customer->full_name,
                'email'     => $this->customer->email,
            ],
            'amount'   => [
                'total'    => $this->total_amount,
                'currency' => $this->currency,
            ],
            'status'     => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

### V1 Order Controller

```php
// app/Http/Controllers/Api/V1/OrderController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $this->orderService->getPaginatedOrders(
            userId: $request->user()->id,
            perPage: $request->integer('per_page', 15)
        );

        return OrderResource::collection($orders);
    }

    public function show(int $id, Request $request): OrderResource
    {
        $order = $this->orderService->findForUser($id, $request->user()->id);

        return new OrderResource($order);
    }
}
```

### V2 Order Controller

```php
// app/Http/Controllers/Api/V2/OrderController.php
namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $this->orderService->getPaginatedOrders(
            userId: $request->user()->id,
            perPage: $request->integer('per_page', 15),
            // V2-yə məxsus yeni filter-lər
            status: $request->string('status')->toString() ?: null,
            dateFrom: $request->date('date_from'),
        );

        return OrderResource::collection($orders);
    }

    public function show(int $id, Request $request): OrderResource
    {
        $order = $this->orderService->findForUser($id, $request->user()->id);

        return new OrderResource($order);
    }
}
```

### Ortaq OrderService

```php
// app/Services/OrderService.php
namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderService
{
    /**
     * Hər iki versiya bu metodu istifadə edir.
     * Business logic bir yerdə saxlanır — controller-lər yalnız format-a baxır.
     */
    public function getPaginatedOrders(
        int $userId,
        int $perPage = 15,
        ?string $status = null,
        ?Carbon $dateFrom = null,
    ): LengthAwarePaginator {
        $query = Order::with(['customer', 'items'])
            ->where('user_id', $userId)
            ->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($dateFrom !== null) {
            $query->where('created_at', '>=', $dateFrom);
        }

        return $query->paginate($perPage);
    }

    public function findForUser(int $orderId, int $userId): Order
    {
        $order = Order::with(['customer', 'items'])
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->first();

        if (!$order) {
            throw new ModelNotFoundException("Order #{$orderId} tapılmadı.");
        }

        return $order;
    }
}
```

### Routes — Tam Nümunə

```php
// routes/api.php
use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V2;
use App\Http\Middleware\DeprecationMiddleware;

/*
|--------------------------------------------------------------------------
| V1 — Deprecated (Sunset: Dec 31, 2025)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')
    ->middleware(['api', 'auth:sanctum', 'throttle:api', 'api.deprecation:v1'])
    ->name('api.v1.')
    ->group(function () {
        Route::apiResource('orders', V1\OrderController::class)->only(['index', 'show']);
        Route::apiResource('users',  V1\UserController::class)->only(['index', 'show']);
    });

/*
|--------------------------------------------------------------------------
| V2 — Current stable version
|--------------------------------------------------------------------------
*/
Route::prefix('v2')
    ->middleware(['api', 'auth:sanctum', 'throttle:api'])
    ->name('api.v2.')
    ->group(function () {
        Route::apiResource('orders', V2\OrderController::class);
        Route::apiResource('users',  V2\UserController::class);
        Route::apiResource('products', V2\ProductController::class); // V2-yə məxsus
    });
```

---

## Trade-offs Müqayisəsi

| Yanaşma | Üstünlüklər | Çatışmazlıqlar | Nə zaman |
|---------|-------------|-----------------|----------|
| **URL Versioning** | Açıq-aydın, log-da görünür, browser-da test asandır, cache-friendly, Swagger dəstəyi tam | URL-i "kirlədir", REST semantikasını zəif pozur | Public API, mobile client, partner integration — əksər hallar üçün |
| **Accept Header** | REST-ə uyğundur, URL təmiz qalır, content negotiation standartına tabedir | Client-lərdən həmişə header göndərilmir, debug çətin, browser-da test olmur, cache daha mürəkkəb | Internal service-lər arası, strict REST amalları olduqda |
| **Custom Header** | Sadə implement, açıq-aydın, client-lər asanlıqla göndərir | Standart deyil, HTTP spec-ə uyğun gəlmir, CDN/proxy bəzən header-ları silir | Internal tooling, developer-friendly API-lər |
| **Query Parameter** | Ən sadə implement, debug çox asandır | Cache poisoning, REST semantikasını pozur, CDN problemləri, professional görünmür | Demək olar ki, heç vaxt |

**Tövsiyə:** Public API üçün **URL versioning** seçin. Sadədir, sınaqdan keçmişdir, documentation yazmaq asandır. Perfect olmaya bilər, amma praktikada ən az problem yaradır.

---

## Anti-patternlər

**1. Hər endpoint-i ayrıca versiyalamaq**
`/api/orders/v2` vs `/api/v2/orders` — sistemsiz versiyalama. Bütün API üçün vahid versiya nömrəsi istifadə edin. Bir endpoint v3-dədirsə, hamısı v3-ə keçməlidir.

**2. Breaking change-i minor versiya kimi göstərmək**
Semantic versioning-i yanlış tətbiq etmək: `v1.1`-də field-ı silmək. Client-lər `v1.x` içindəki bütün versiyaların backward compatible olduğunu gözləyir. Breaking change → major versiya, mütləq.

**3. Köhnə versiyaları heç vaxt silməmək**
"Hər ehtimala qarşı" saxlamaq — versiya sayı artır, test yükü artır, infrastructure xərci artır. Sunset date müəyyənləşdirin, trafik azalana qədər gözləyin, sonra silin.

**4. Versiya dəyişikliyi barədə müştəriləri xəbərdar etməmək**
Changelog yayımlamadan, email göndərmədən köhnə versiyaya deprecation header əlavə etmək. Müştərilər Sunset header-ını oxumayıbsa, service kəsilir. Proaktiv communication: email, developer portal, in-app notification.

**5. Testləri yalnız son versiya üçün yazmaq**
V2 yayımlananda V1 testləri silinir. Amma V1 hələ istifadədədir — buglar aşkarlanmır. Bütün aktiv versiyalar üçün ayrıca test suite saxlayın.

**6. Versiya nömrəsini response body-yə qoymamaq**
Client debug edərkən hansı versiyadan cavab aldığını bilmir. Response-a `"api_version": "v2"` əlavə edin — xüsusilə Accept header versiyalamada vacibdir.

**7. V2-ni V1-in üzərinə "hər şeyi miras almaq" şəklində qurmaq**
V2 controller-də `parent::index()` çağırıb üzərinə əlavə etmək. Bir gün V1 silinəndə V2 sınır. Hər versiya müstəqil olmalıdır — ortaq logic yalnız `Service` layer-da olmalıdır.

---

## Interview Sualları və Cavablar

**S: API versiyalamağın ən yaxşı yolu hansıdır?**

C: "Ən yaxşı" kontekstdən asılıdır. Public API, mobile client-lər, partner integration üçün **URL versioning** (`/api/v1/`, `/api/v2/`) tövsiyə edilir — açıq-aydındır, debug asandır, cache-friendly-dir. Internal microservice-lər üçün Accept header daha semantik düzgündür. Query parameter demək olar ki, heç vaxt istifadə edilməməlidir — cache poisoning riski var və REST semantikasını pozur.

---

**S: Breaking change ilə non-breaking change-i necə fərqləndirirsiniz? Nümunə verin.**

C: Non-breaking change — yeni optional field, yeni endpoint, yeni optional query param əlavə etmək. Köhnə client-lər bunları ignore edir, sınmır. Breaking change — mövcud field-ın adını dəyişmək, field-ı silmək, response strukturunu yenidən qurmaq (flat → nested), required field əlavə etmək, auth metodunu dəyişmək. Qaydası sadədir: əgər köhnə client-lər kodu dəyişmədən çağırmağa davam edə bilirsə — non-breaking. Əgər kodu uyğunlaşdırmalıdırlarsa — breaking, yeni major versiya tələb edir.

---

**S: Köhnə versiyaları nə vaxt və necə silmək lazımdır?**

C: Addımlar: (1) Versiyaya `Deprecation` və `Sunset` header-larını əlavə edin. (2) Aktiv istifadəçilərə email göndərin. (3) Developer portal-da migration guide paylaşın. (4) Traffic monitoring qeyd edin — köhnə versiyanın trafiki sıfıra yaxınlaşana qədər gözləyin. (5) Sunset tarixindən sonra `410 Gone` qaytarın, bir müddət gözləyin, sonra tamamilə silin. Ən az 6 ay, kritik integration-lar varsa 12 ay Sunset müddəti qoyun.

---

**S: URL versiyalamasında Laravel-də versiya-specific controller-lər yazmaq lazımdırmı, yoxsa tək controller-də version parametri?**

C: Versiya-specific controller-lər (V1\OrderController, V2\OrderController) daha düzgündür. Tək controller-də `if ($version === 'v2')` yığışdırmaq controller-i böyüdür, testləri çətinləşdirir, yeni versiya əlavə edəndə mövcud kodu dəyişmək lazım olur (Open/Closed Principle pozulur). Ortaq business logic-i `OrderService`-də saxlayın — hər controller yalnız öz formatına məsul olsun. Resource class-lar da (V1\OrderResource, V2\OrderResource) ayrı olmalıdır.

---

**S: API istifadəçiləri Sunset header-ını oxumursa, kim çağırır bilmək üçün nə edə bilərik?**

C: Deprecated versiyalara gələn hər sorğunu log edin: `user_id`, `api_key`, `ip`, `user_agent`, `endpoint`. Bu log-lardan aktiv istifadəçiləri çıxarıb email göndərin. Daha qabaqcıl: `api_keys` cədvəlində hər key üçün istifadə etdiyi versiyaları izləyin, həftəlik `NotifyDeprecatedApiUsersJob` run edin. Sunset tarixinə 30 gün qalmış trafik hələ var isə, iri müştərilərlə birbaşa əlaqə qurun.
