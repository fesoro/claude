# API Versioning (Middle)

---

## 1. Niyə API Versioning Lazımdır?

API-lər production-da istifadə olunduqdan sonra onları dəyişdirmək çox çətindir. Mövcud client-lər (mobil app, third-party integrasiyalar, partner sistemlər) köhnə contract-a uyğun işləyir. Əgər siz API-ni versioning olmadan dəyişsəniz:

- Mobil app crash edər
- Partner sistemlər məlumat oxuya bilməz
- Breaking change-lər backward compatibility-ni pozar

**Məqsəd:** Köhnə client-lər işləməyə davam etsin, yeni client-lər isə yeni API-dən istifadə etsin.

### Breaking Change nədir?

```
Breaking changes (versioning tələb edir):
  - Field adını dəyişmək: "user_name" → "username"
  - Field-i silmək
  - Field tipini dəyişmək: string → integer
  - Required field əlavə etmək (request-də)
  - HTTP status code-u dəyişmək: 200 → 201
  - Response strukturunu dəyişmək (array → object)
  - Authentication tələbini dəyişmək
  - URL endpoint-i silmək və ya dəyişmək

Non-breaking changes (versioning tələb etmir):
  - Optional field əlavə etmək (response-da)
  - Yeni endpoint əlavə etmək
  - Documentation düzəltmək
  - Performance yaxşılaşdırmaq
  - Bug fix (əgər behavior dəyişmirsə)
  - Deprecation warning əlavə etmək
```

---

## 2. Versioning Strategiyaları Müqayisəsi

### 2.1 URL Path Versioning — `/api/v1/`

**Ən geniş yayılmış üsul.**

```
GET /api/v1/users
GET /api/v2/users
POST /api/v1/orders
```

**Pros:**
- Açıq və aşkar — URL-ə baxanda bilirsən hansı versiyadır
- Browser-da test etmək asandır (curl, Postman)
- Cache-lənmə asandır (CDN URL-ə görə cache edir)
- Server log-larında versiyanı görmək asandır
- Bookmark, link sharing işləyir

**Cons:**
- URL "kirləniir" — REST prinsipinə görə URL resursu ifadə etməlidir, versiya deyil
- Client hər versiya üçün ayrı base URL saxlamalıdır
- Çox versiya olduqda URL-lər çoxalır

**Nə zaman seçmək:**
- Public API (third-party developer-lər üçün)
- Uzunmüddətli backward compatibility lazım olduqda
- API documentation sadəliyi vacib olduqda

---

### 2.2 Query Parameter Versioning — `?version=1`

```
GET /api/users?version=1
GET /api/users?version=2
GET /api/users?v=2
```

**Pros:**
- Tək base URL — client yalnız parametri dəyişir
- Default versiya asanlıqla qoyula bilər
- Versiyasız istəklər default versiyaya yönləndirilə bilər

**Cons:**
- Cache problemi — CDN və proxy-lər query parametrini nəzərə almaya bilər
- Gözlənilməz: URL-ə baxanda versiya aydın deyil
- Versiya parametri digər parametrlərlə qarışa bilər

**Nə zaman seçmək:**
- Daxili API-lər (public deyil)
- Versiya dəyişikliklərinin az olduğu hallar

---

### 2.3 Header-based Versioning

*2.3 Header-based Versioning üçün kod nümunəsi:*
```http
GET /api/users HTTP/1.1
Host: api.example.com
X-API-Version: 2
```

**Pros:**
- URL təmiz qalır — REST prinsipinə uyğundur
- Client metadata-nı header-da daşıyır

**Cons:**
- Browser-da birbaşa test etmək çətin (curl/Postman lazımdır)
- Cache problemi — CDN adətən header-a görə cache etmir (Vary header lazımdır)
- Developer-lər üçün az intuitiv

---

### 2.4 Content Negotiation (Accept Header)

*2.4 Content Negotiation (Accept Header) üçün kod nümunəsi:*
```http
GET /api/users HTTP/1.1
Accept: application/vnd.myapi.v2+json
Accept: application/vnd.myapi+json;version=2
```

**Pros:**
- HTTP spesifikasiyasına ən uyğun üsul
- Media type-a görə versioning — tamamilə RESTful
- Bir URL, müxtəlif formatlar

**Cons:**
- Ən mürəkkəb implementasiya
- Çox az developer bunu düzgün anlayır
- Client tərəfdən düzgün header qurmaq çətin

---

### Strategiya Müqayisə Cədvəli

| Strategiya      | Görünürlük | Cache | REST Uyğunluq | Mürəkkəblik | Tövsiyə |
|----------------|------------|-------|---------------|-------------|---------|
| URL Path       | Yüksək     | Asan  | Orta          | Aşağı       | Public API |
| Query Param    | Orta       | Çətin | Orta          | Aşağı       | Daxili API |
| Header         | Aşağı      | Çətin | Yüksək        | Orta        | Enterprise |
| Content Negot. | Aşağı      | Çətin | Çox yüksək    | Yüksək      | Nadir    |

---

## 3. Semantic Versioning API-lər üçün

API-lər üçün SemVer (`MAJOR.MINOR.PATCH`) fərqli tətbiq edilir:

```
v1.0.0 → v2.0.0  (breaking change — major version bump)
v1.0.0 → v1.1.0  (new feature, backward compatible)
v1.0.0 → v1.0.1  (bug fix, backward compatible)
```

URL-də adətən yalnız major versiya göstərilir:
```
/api/v1/...   ← major version 1.x.x
/api/v2/...   ← major version 2.x.x
```

---

## 4. Deprecation Strategiyası

Bir versiyanı silmədən əvvəl:

1. **Announce** — developer portal-da, email list-də elan et
2. **Timeline** — minimum 6-12 ay əvvəl xəbərdarlıq et
3. **Deprecation Header** əlavə et hər response-da
4. **Sunset Date** müəyyən et
5. **Migration Guide** yaz
6. **Sunset olduqda** 410 Gone qaytarmağı düşün

---

## 5. Sunset Header (RFC 8594) — Laravel Middleware

RFC 8594 standartına görə deprecated endpoint-lər `Sunset` header-ı qaytarmalıdır.

*RFC 8594 standartına görə deprecated endpoint-lər `Sunset` header-ı qa üçün kod nümunəsi:*
```php
<?php
// app/Http/Middleware/ApiDeprecationMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiDeprecationMiddleware
{
    /**
     * Hansı versiyalar deprecated-dir və sunset tarixi nədir.
     */
    private array $deprecatedVersions = [
        'v1' => [
            'sunset'     => 'Sat, 31 Dec 2025 23:59:59 GMT',
            'link'       => 'https://api.example.com/docs/migration/v1-to-v2',
            'deprecated' => 'Mon, 01 Jan 2025 00:00:00 GMT',
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // URL-dən versiyani götür
        $version = $this->extractVersion($request);

        if ($version && isset($this->deprecatedVersions[$version])) {
            $info = $this->deprecatedVersions[$version];

            // RFC 8594 Sunset header
            $response->headers->set('Sunset', $info['sunset']);

            // Deprecation header (draft-ietf-httpapi-deprecation-header)
            $response->headers->set('Deprecation', $info['deprecated']);

            // Link header — migration guide-a
            $response->headers->set(
                'Link',
                "<{$info['link']}>; rel=\"successor-version\""
            );

            // İnsan oxuya biləcəyi warning
            $response->headers->set(
                'Warning',
                "299 - \"This API version is deprecated. Please migrate to v2. Sunset: {$info['sunset']}\""
            );
        }

        return $response;
    }

    private function extractVersion(Request $request): ?string
    {
        // URL-dən /api/v1/ → "v1"
        if (preg_match('/\/api\/(v\d+)\//', $request->getPathInfo(), $matches)) {
            return $matches[1];
        }

        return null;
    }
}
```

*həll yanaşmasını üçün kod nümunəsi:*
```php
// bootstrap/app.php (Laravel 11)
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('api', \App\Http\Middleware\ApiDeprecationMiddleware::class);
})
```

---

## 6. Laravel-də Tam Implementation

### 6.1 URL Prefix ilə Versioned Routes

*6.1 URL Prefix ilə Versioned Routes üçün kod nümunəsi:*
```php
// routes/api.php

use App\Http\Controllers\V1;
use App\Http\Controllers\V2;

// V1 Routes
Route::prefix('v1')
    ->name('api.v1.')
    ->namespace('App\Http\Controllers\V1')
    ->group(function () {
        Route::apiResource('users', V1\UserController::class);
        Route::apiResource('products', V1\ProductController::class);
        Route::post('orders', [V1\OrderController::class, 'store']);
    });

// V2 Routes
Route::prefix('v2')
    ->name('api.v2.')
    ->namespace('App\Http\Controllers\V2')
    ->group(function () {
        Route::apiResource('users', V2\UserController::class);
        Route::apiResource('products', V2\ProductController::class);
        // V2-də orders endpoint dəyişdi
        Route::apiResource('orders', V2\OrderController::class);
    });
```

Daha strukturlu hal üçün ayrı route faylları:

*Daha strukturlu hal üçün ayrı route faylları üçün kod nümunəsi:*
```php
// routes/api_v1.php
Route::prefix('v1')->name('api.v1.')->group(function () {
    require __DIR__ . '/api/v1.php';
});

// routes/api_v2.php
Route::prefix('v2')->name('api.v2.')->group(function () {
    require __DIR__ . '/api/v2.php';
});
```

---

### 6.2 Versioned Controllers

```
app/Http/Controllers/
├── V1/
│   ├── UserController.php
│   ├── ProductController.php
│   └── OrderController.php
└── V2/
    ├── UserController.php
    ├── ProductController.php
    └── OrderController.php
```

*└── OrderController.php üçün kod nümunəsi:*
```php
<?php
// app/Http/Controllers/V1/UserController.php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::paginate(15);
        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }
}
```

*return new UserResource($user); üçün kod nümunəsi:*
```php
<?php
// app/Http/Controllers/V2/UserController.php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // V2-də eager loading əlavə edildi, yeni filter imkanları
        $users = User::with(['profile', 'roles'])
            ->filter(request()->only(['status', 'role', 'search']))
            ->paginate(request()->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        $user->load(['profile', 'roles', 'permissions']);
        return new UserResource($user);
    }
}
```

---

### 6.3 API Resource Versioning

*6.3 API Resource Versioning üçün kod nümunəsi:*
```php
<?php
// app/Http/Resources/V1/UserResource.php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,       // V1: tam ad bir field-də
            'email'      => $this->email,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

*'created_at' => $this->created_at->toISOString(), üçün kod nümunəsi:*
```php
<?php
// app/Http/Resources/V2/UserResource.php

namespace App\Http\Resources\V2;

use App\Http\Resources\V2\ProfileResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            // V2: ad iki hissəyə bölündü (breaking change → yeni major version)
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            'status'     => $this->status,
            'profile'    => new ProfileResource($this->whenLoaded('profile')),
            'roles'      => $this->whenLoaded('roles', fn() =>
                $this->roles->pluck('name')
            ),
            'meta' => [
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
            ],
        ];
    }
}
```

---

### 6.4 Header-based Version Middleware

*6.4 Header-based Version Middleware üçün kod nümunəsi:*
```php
<?php
// app/Http/Middleware/ApiVersionMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionMiddleware
{
    private const DEFAULT_VERSION = 'v2';
    private const SUPPORTED_VERSIONS = ['v1', 'v2'];

    public function handle(Request $request, Closure $next): Response
    {
        // Header-dan versiya götür
        $version = $request->header('X-API-Version')
            ?? $request->header('Accept-Version')
            ?? self::DEFAULT_VERSION;

        // Normalize: "1" → "v1", "2" → "v2"
        if (is_numeric($version)) {
            $version = 'v' . $version;
        }

        $version = strtolower($version);

        if (! in_array($version, self::SUPPORTED_VERSIONS)) {
            return response()->json([
                'error'   => 'Unsupported API version',
                'message' => "Version '{$version}' is not supported. Supported versions: " 
                           . implode(', ', self::SUPPORTED_VERSIONS),
            ], 400);
        }

        // Request-ə versiya attribute-u əlavə et (controller-lər istifadə edə bilər)
        $request->attributes->set('api_version', $version);

        $response = $next($request);

        // Response-da hansı versiya işləndiğini göstər
        $response->headers->set('X-API-Version', $version);

        return $response;
    }
}
```

---

### 6.5 Version Negotiation Service

*6.5 Version Negotiation Service üçün kod nümunəsi:*
```php
<?php
// app/Services/ApiVersionNegotiator.php

namespace App\Services;

use Illuminate\Http\Request;

class ApiVersionNegotiator
{
    private const VERSION_MAP = [
        'application/vnd.myapi.v1+json' => 'v1',
        'application/vnd.myapi.v2+json' => 'v2',
        'application/json'              => 'v2', // default
    ];

    /**
     * Accept header-dan versiya müəyyən edir.
     * Accept: application/vnd.myapi.v2+json
     */
    public function negotiate(Request $request): string
    {
        $acceptHeader = $request->header('Accept', 'application/json');

        // Multiple accept types: "text/html, application/vnd.myapi.v2+json;q=0.9"
        $acceptTypes = $this->parseAcceptHeader($acceptHeader);

        foreach ($acceptTypes as $type) {
            $mediaType = $type['media_type'];
            if (isset(self::VERSION_MAP[$mediaType])) {
                return self::VERSION_MAP[$mediaType];
            }
        }

        return 'v2'; // default
    }

    /**
     * Accept header-ı parse edir, q-factor-a görə sıralayır.
     */
    private function parseAcceptHeader(string $header): array
    {
        $types = [];
        foreach (explode(',', $header) as $part) {
            $part   = trim($part);
            $pieces = explode(';', $part);
            $type   = trim($pieces[0]);
            $q      = 1.0;

            foreach (array_slice($pieces, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=')) {
                    $q = (float) substr($param, 2);
                }
            }

            $types[] = ['media_type' => $type, 'q' => $q];
        }

        usort($types, fn($a, $b) => $b['q'] <=> $a['q']);

        return $types;
    }
}
```

---

## 7. GraphQL Versioning

GraphQL-də URL versioning əvəzinə **field deprecation** istifadə edilir:

*GraphQL-də URL versioning əvəzinə **field deprecation** istifadə edili üçün kod nümunəsi:*
```graphql
type User {
    id: ID!
    
    # V1 field — deprecated
    name: String @deprecated(reason: "Use firstName and lastName instead. Will be removed 2026-01-01.")
    
    # V2 fields
    firstName: String!
    lastName: String!
    
    email: String!
    status: UserStatus!
}
```

**PHP (Lighthouse):**

```php
// GraphQL schema (schema.graphql)
type Query {
    # Deprecated query
    user(id: ID!): User
        @deprecated(reason: "Use userById instead")
    
    # New query
    userById(id: ID!): User
}
```

**GraphQL versioning strategiyası:**
1. Köhnə field-ləri `@deprecated` et
2. Yeni field-lər əlavə et
3. Client-lərə migration time ver
4. Müəyyən tarixdən sonra köhnə field-ləri sil

---

## 8. Richardson Maturity Model

REST API-lərin yetkinlik səviyyəsi:

```
Level 0: Swamp of POX (Plain Old XML)
  POST /api HTTP/1.1
  Body: { "action": "getUser", "id": 1 }
  → Bütün əməliyyatlar bir endpoint-dən
  
Level 1: Resources
  GET /api/users/1
  GET /api/products/5
  → Resurslar var, amma HTTP method-lar düzgün işlədilmir
  
Level 2: HTTP Verbs (Çox şirkətin "REST" dediyi bu səviyyədir)
  GET    /api/users     → list
  POST   /api/users     → create
  GET    /api/users/1   → get
  PUT    /api/users/1   → update
  DELETE /api/users/1   → delete
  
Level 3: Hypermedia (HATEOAS)
  GET /api/users/1 →
  {
    "id": 1,
    "name": "John",
    "_links": {
      "self": { "href": "/api/users/1" },
      "orders": { "href": "/api/users/1/orders" },
      "deactivate": { "href": "/api/users/1/deactivate", "method": "POST" }
    }
  }
```

**Laravel-də HATEOAS:**

```php
// Bu kod API response-a HATEOAS linklərinin əlavə edilməsini göstərir
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            '_links' => [
                'self' => [
                    'href'   => route('api.v2.users.show', $this->id),
                    'method' => 'GET',
                ],
                'update' => [
                    'href'   => route('api.v2.users.update', $this->id),
                    'method' => 'PUT',
                ],
                'orders' => [
                    'href'   => route('api.v2.users.orders.index', $this->id),
                    'method' => 'GET',
                ],
            ],
        ];
    }
}
```

---

## 9. OpenAPI/Swagger — Versioned Docs

*9. OpenAPI/Swagger — Versioned Docs üçün kod nümunəsi:*
```php
// app/Http/Controllers/V1/UserController.php

/**
 * @OA\Get(
 *     path="/api/v1/users/{id}",
 *     tags={"Users V1"},
 *     summary="Get user by ID (Deprecated — use V2)",
 *     deprecated=true,
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Success",
 *         @OA\JsonContent(ref="#/components/schemas/UserV1")
 *     )
 * )
 */
public function show(User $user): UserResource
{
    return new UserResource($user);
}
```

*return new UserResource($user); üçün kod nümunəsi:*
```php
// config/l5-swagger.php — birdən çox doc faylı

'documentations' => [
    'v1' => [
        'api' => [
            'title'   => 'My API V1 (Deprecated)',
            'version' => '1.0.0',
        ],
        'routes' => [
            'api' => 'api/documentation/v1',
        ],
        'paths' => [
            'annotations' => [app_path('Http/Controllers/V1')],
        ],
    ],
    'v2' => [
        'api' => [
            'title'   => 'My API V2',
            'version' => '2.0.0',
        ],
        'routes' => [
            'api' => 'api/documentation/v2',
        ],
        'paths' => [
            'annotations' => [app_path('Http/Controllers/V2')],
        ],
    ],
],
```

---

## 10. API Changelog Management

*10. API Changelog Management üçün kod nümunəsi:*
```markdown
# API Changelog

## [2.1.0] — 2025-06-01
### Added
- `GET /api/v2/users?status=active` — status filter əlavə edildi
- `POST /api/v2/users/{id}/verify` — email verification endpoint

### Fixed
- `GET /api/v2/orders` — pagination meta-data düzəldildi

## [2.0.0] — 2025-01-01 (Breaking)
### Breaking Changes
- `name` field → `first_name` + `last_name` olaraq bölündü
- `created_at` → `meta.created_at`-a köçürüldü

### Deprecated
- V1 API deprecated edildi (sunset: 2025-12-31)

## [1.0.0] — 2024-01-01
### Initial Release
```

**Laravel Artisan ilə versioning:**

```php
// app/Console/Commands/CheckApiVersionUsage.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckApiVersionUsage extends Command
{
    protected $signature   = 'api:version-usage';
    protected $description = 'Check which API versions are still being used';

    public function handle(): void
    {
        // api_request_logs cədvəlindən versiya statistikası
        $stats = DB::table('api_request_logs')
            ->selectRaw('api_version, COUNT(*) as request_count, MAX(created_at) as last_used')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('api_version')
            ->orderByDesc('request_count')
            ->get();

        $this->table(
            ['Version', 'Requests (30d)', 'Last Used'],
            $stats->map(fn($s) => [$s->api_version, $s->request_count, $s->last_used])
        );
    }
}
```

---

## 11. Version Usage Logging Middleware

*11. Version Usage Logging Middleware üçün kod nümunəsi:*
```php
<?php
// app/Http/Middleware/LogApiVersionMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LogApiVersionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Async log — response-u gecikdirmə
        dispatch(function () use ($request, $response) {
            DB::table('api_request_logs')->insert([
                'api_version' => $request->attributes->get('api_version', 'unknown'),
                'method'      => $request->method(),
                'path'        => $request->path(),
                'status_code' => $response->getStatusCode(),
                'user_agent'  => $request->userAgent(),
                'ip'          => $request->ip(),
                'created_at'  => now(),
            ]);
        })->afterResponse();

        return $response;
    }
}
```

---

## 12. Real-World Versioning Test

*12. Real-World Versioning Test üçün kod nümunəsi:*
```php
<?php
// tests/Feature/ApiVersioningTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_returns_name_as_single_field(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email']])
            ->assertJsonMissing(['first_name', 'last_name']);
    }

    public function test_v2_returns_split_name_fields(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        $this->getJson("/api/v2/users/{$user->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'first_name', 'last_name', 'email']])
            ->assertJsonMissing(['name']);
    }

    public function test_deprecated_v1_returns_sunset_header(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertHeader('Sunset')
            ->assertHeader('Deprecation');
    }

    public function test_unsupported_version_returns_400(): void
    {
        $this->withHeader('X-API-Version', 'v99')
            ->getJson('/api/users')
            ->assertStatus(400)
            ->assertJsonFragment(['error' => 'Unsupported API version']);
    }
}
```

---

## 13. İntervyu Sualları

**Sual 1:** URL versioning ilə header versioning arasındakı əsas fərq nədir?

**Cavab:** URL versioning-də versiya açıq şəkildə URL-in bir hissəsidir (`/api/v2/users`), bu da cache, debug və developer onboarding-i asanlaşdırır. Header versioning-də isə URL təmiz qalır (`/api/users`), HTTP semantikasına daha uyğundur, lakin CDN cache-ləmək üçün `Vary` header-ı lazımdır. Praktikada URL versioning daha çox seçilir.

---

**Sual 2:** Breaking change nədir, nümunə verin.

**Cavab:** Mövcud client-in işini pozan dəyişiklikdir. Məsələn: response-da `user_name` field-ini `username` ilə əvəzləmək — client `user_name`-ə görə işləyirsə, `null` alacaq. Yeni optional field əlavə etmək isə breaking deyil.

---

**Sual 3:** Sunset header RFC 8594 nədir?

**Cavab:** Deprecated API endpoint-lərinin xidmətdən çıxacağı tarixi bildirən HTTP response header-ıdır. Format: `Sunset: Sat, 31 Dec 2025 23:59:59 GMT`. Client-lər bu header-ı oxuyub migrasiya planı qura bilər.

---

**Sual 4:** Versiya nə vaxt artırılmalıdır?

**Cavab:** Yalnız breaking change zamanı major version artırılır. Non-breaking əlavələr (yeni optional field, yeni endpoint) üçün versiya artırmaq lazım deyil.

---

**Sual 5:** API-nı neçə versiya saxlamaq ağıllıdır?

**Cavab:** Adətən aktiv 2 versiya: cari (latest) və bir əvvəlki (supported). Daha qədim versiyalar üçün uzun migration period verilir, sonra sunset edilir. Üçdən çox aktiv versiya saxlamaq maintenance yükünü çox artırır.

---

**Sual 6:** Richardson Maturity Model Level 2 ilə Level 3 arasındakı fərq?

**Cavab:** Level 2 — HTTP verb-ləri düzgün istifadə edir (GET/POST/PUT/DELETE). Level 3 (HATEOAS) isə response-un içərisində növbəti mümkün əməliyyatların linklərini qaytarır. Client API-yi öyrənmədən navigasiya edə bilər, lakin praktikada çox az şirkət Level 3 tətbiq edir.

---

**Sual 7:** GraphQL-də versioning necə edilir?

**Cavab:** GraphQL-də URL versioning istifadə edilmir. Bunun əvəzinə `@deprecated` direktivi ilə köhnə field-lər işarələnir, yeni field-lər əlavə edilir. Client-lər yavaş-yavaş yeni field-lərə keçir. Bütün dəyişikliklər backward compatible olmalıdır.

---

**Sual 8:** Content negotiation versioning-in cache problemi nədir?

**Cavab:** CDN-lər default olaraq URL-ə görə cache edir. Eyni URL üçün fərqli Accept header-larla fərqli cavablar versəniz, CDN birini cache edib digərlərinə qaytara bilər. Bunu həll etmək üçün `Vary: Accept` response header-ı əlavə etmək lazımdır, bu isə cache hit rate-ni azaldır.

---

**Sual 9:** Version usage-i niyə log etmək lazımdır?

**Cavab:** Köhnə versiyanı sunset etmədən əvvəl real istifadəçilərin hələ də o versiyadan istifadə edib etmədiyini bilmək lazımdır. Log-lar sayəsində "v1-i kimsə istifadə etmir, silə bilərik" qərarı data ilə dəstəklənir.

---

**Sual 10:** Bir API endpoint-i eyni zamanda həm URL versioned, həm header versioned edə bilərikmi?

**Cavab:** Bəli, lakin bu qarışıqlıq yaradır. Standart seçin və ona sadiq qalın. Əgər hər ikisini dəstəkləsəniz, priority sırası müəyyən edin: məsələn, URL versiyası header-dan üstündür. Konsistentlik developer experience üçün kritikdir.

---

**Sual 11:** Laravel-də V1 controlleri silmədən V2-yə keçid necə edilir?

**Cavab:** V2 controller V1-i extend edib yalnız dəyişən metodları override edə bilər. Bu kod dublikasiyasını azaldır. Lakin çox fərqli davranış olduqda ayrı controller-lər daha təmiz olur.

---

**Sual 12:** API-da semantic versioning URL-də necə tətbiq edilir?

**Cavab:** Adətən yalnız major version URL-də göstərilir (`/api/v1/`, `/api/v2/`). Minor və patch versiyalar backward compatible olduğu üçün URL-i dəyişdirmir. Tam versiya response header-da (`X-API-Version: 2.1.3`) bildirilə bilər.

---

## Anti-patternlər

**1. Breaking change-ləri mövcud versiyada etmək**
`/api/v1/users` endpoint-inin response strukturunu dəyişmək — mövcud client-lər sınır, backward compatibility pozulur, production-da gözlənilməz xətalar yaranır. Breaking change-lər üçün yeni major versiya yarat (`/api/v2/`), köhnə versiyanı deprecation müddəti boyunca saxla.

**2. Versiyasız API ilə başlamaq**
İlk buraxılışda `/api/users` kimi versionless endpoint-lər yaratmaq — sonradan versiyalandırmaq çox çətin olur, mövcud integrasyonları pozmadan keçid mümkünsüzləşir. API-ni başdan versiyalı dizayn et (`/api/v1/`).

**3. Köhnə versiyaları istifadə statistikası olmadan sunset etmək**
V1-in istifadə edilib-edilmədiyini bilmədən silmək — aktiv client-lər anidən pozulur, geri dönmək çətin olur. Hər versiyaya istifadə logu əlavə et, `Sunset` response header-i ilə deprecation tarixini bildir, sıfır istifadə gördükdən sonra sil.

**4. Versiyalama strategiyasını qarışdırmaq (URL + Header eyni anda)**
Həm URL versiyası (`/v1/`), həm Accept header versiyası dəstəkləmək — priority qaydası aydın olmadıqda client-lər çaşır, server-də idarəetmə çətinləşir. Bir strategiya seç və bütün API boyunca ardıcıl istifadə et.

**5. Minor dəyişiklikləri yeni major versiya kimi yayımlamaq**
Yeni optional sahə əlavə etmək üçün V2 açmaq — versiya sayı sürətlə artır, client-lər lazımsız migration-larla məşğul olur. Yalnız geriyə uyğunsuz dəyişikliklər üçün major versiya yarat; additive dəyişikliklər (yeni optional sahələr) mövcud versiyada edilə bilər.

**6. Versiya-spesifik biznes məntiqini controller-ə yerləşdirmək**
`if ($version === 'v2') { ... }` kimi versiya yoxlamaları controller-ə yazmaq — controller-lər şişir, kod duplicasiyası artır, yeni versiya əlavəsi bütün controller-i dəyişdirir. Hər versiya üçün ayrı controller, ya da V2-nin V1-i extend etməsi pattern-ini istifadə et.
