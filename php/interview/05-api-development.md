# API Development

## 1. RESTful API dizayn prinsipləri hansılardır?

**URL konvensiyaları:**
```
GET    /api/v1/users          — bütün users (list)
GET    /api/v1/users/123      — tək user (show)
POST   /api/v1/users          — yeni user yarat (store)
PUT    /api/v1/users/123      — tam yenilə (update)
PATCH  /api/v1/users/123      — qismən yenilə (partial update)
DELETE /api/v1/users/123      — sil (destroy)

GET    /api/v1/users/123/posts     — user-in postları (nested resource)
POST   /api/v1/users/123/posts     — user üçün post yarat
```

**HTTP Status Codes:**
```
200 OK               — uğurlu GET/PUT/PATCH
201 Created           — uğurlu POST (Location header ilə)
204 No Content        — uğurlu DELETE
400 Bad Request       — validation error
401 Unauthorized      — autentifikasiya lazım
403 Forbidden         — icazə yoxdur
404 Not Found         — resurs tapılmadı
409 Conflict          — resurs konflikti (duplicate email)
422 Unprocessable     — validation error (Laravel default)
429 Too Many Requests — rate limit aşıldı
500 Internal Error    — server xətası
```

**Pagination:**
```json
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 150,
        "last_page": 10
    },
    "links": {
        "first": "/api/users?page=1",
        "last": "/api/users?page=10",
        "next": "/api/users?page=2",
        "prev": null
    }
}
```

---

## 2. API Authentication — Sanctum vs Passport

### Laravel Sanctum (SPA + Mobile + Simple token)
```php
// Install
// composer require laravel/sanctum

// Token yaratma
$token = $user->createToken('mobile-app', ['orders:read'])->plainTextToken;

// Middleware ilə qoruma
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('orders', OrderController::class);
});

// Ability check
if ($user->tokenCan('orders:read')) { /* ... */ }

// SPA autentifikasiya (cookie-based, CSRF qoruması)
// Sanctum avtomatik session cookie istifadə edir
```

### Laravel Passport (Full OAuth2)
```php
// OAuth2 grant types:
// 1. Authorization Code — third-party apps
// 2. Client Credentials — machine-to-machine
// 3. Password Grant — first-party apps (deprecated)
// 4. Implicit — SPA (deprecated, Sanctum istifadə et)

// Scope-lar
Passport::tokensCan([
    'read-orders' => 'Read orders',
    'create-orders' => 'Create orders',
]);

Route::middleware(['auth:api', 'scope:read-orders'])->get('/orders', ...);
```

**Nə vaxt hansı?**
- **Sanctum:** SPA, mobile app, simple token API — əksər hallarda bu kifayətdir
- **Passport:** Third-party OAuth2 integration lazım olanda

---

## 3. API Rate Limiting

```php
// RouteServiceProvider və ya bootstrap/app.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Müxtəlif limitlər
RateLimiter::for('api', function (Request $request) {
    return $request->user()?->isPremium()
        ? Limit::perMinute(300)->by($request->user()->id)
        : Limit::perMinute(60)->by($request->ip());
});

// Route-da
Route::middleware('throttle:api')->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Custom rate limiter
Route::middleware('throttle:10,1')->post('/login', ...); // 10 request / dəqiqə
```

---

## 4. API Versioning strategiyaları

```php
// 1. URL Versioning (ən çox istifadə olunan)
Route::prefix('api/v1')->group(function () {
    Route::apiResource('users', V1\UserController::class);
});
Route::prefix('api/v2')->group(function () {
    Route::apiResource('users', V2\UserController::class);
});

// 2. Header Versioning
class ApiVersionMiddleware {
    public function handle(Request $request, Closure $next): Response {
        $version = $request->header('Api-Version', 'v1');
        config(['app.api_version' => $version]);
        return $next($request);
    }
}

// 3. Resource-da versiya dəstəyi
class UserResource extends JsonResource {
    public function toArray(Request $request): array {
        $base = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($request->header('Api-Version') === 'v2') {
            $base['full_name'] = $this->first_name . ' ' . $this->last_name;
        }

        return $base;
    }
}
```

---

## 5. API Error Handling

```php
// app/Exceptions/Handler.php və ya bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->expectsJson()) {
            return match(true) {
                $e instanceof ModelNotFoundException => response()->json([
                    'error' => 'Resource not found',
                    'type' => 'not_found',
                ], 404),

                $e instanceof ValidationException => response()->json([
                    'error' => 'Validation failed',
                    'type' => 'validation_error',
                    'details' => $e->errors(),
                ], 422),

                $e instanceof AuthenticationException => response()->json([
                    'error' => 'Unauthenticated',
                    'type' => 'unauthenticated',
                ], 401),

                $e instanceof AuthorizationException => response()->json([
                    'error' => 'Forbidden',
                    'type' => 'forbidden',
                ], 403),

                default => response()->json([
                    'error' => app()->isProduction() ? 'Server error' : $e->getMessage(),
                    'type' => 'server_error',
                ], 500),
            };
        }
    });
})

// Custom API Exception
class ApiException extends HttpException {
    public function __construct(
        string $message,
        private string $errorType,
        int $statusCode = 400,
        private array $details = [],
    ) {
        parent::__construct($statusCode, $message);
    }

    public function render(): JsonResponse {
        return response()->json([
            'error' => $this->getMessage(),
            'type' => $this->errorType,
            'details' => $this->details,
        ], $this->getStatusCode());
    }
}
```

---

## 6. API Testing

```php
class OrderApiTest extends TestCase {
    use RefreshDatabase;

    public function test_can_list_orders(): void {
        $user = User::factory()->create();
        Order::factory()->count(3)->for($user)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'total', 'status', 'created_at']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_can_create_order(): void {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
                'shipping_address_id' => Address::factory()->for($user)->create()->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_unauthenticated_returns_401(): void {
        $this->getJson('/api/v1/orders')
            ->assertUnauthorized();
    }

    public function test_validation_errors_return_422(): void {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/orders', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }
}
```
