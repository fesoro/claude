# Əlavə Vacib Suallar və Patterns

## 1. Cursor-based Pagination vs Offset Pagination

**Problem:** 1M sətirli cədvəldə `OFFSET 999000, LIMIT 20` çox yavaşdır.

```php
// Offset pagination — səhifə nömr��si ilə (standart)
// Problem: OFFSET böyüdükcə yavaşlayır, DB hər dəfə OFFSET qədər sətir oxuyub atlayır
$users = User::paginate(20); // ?page=50000 → yavaş!

// Cursor pagination — son elementin ID/dəyəri ilə
$users = User::orderBy('id')->cursorPaginate(20);
// ?cursor=eyJpZCI6MTAwMH0 → həmişə sürətli

// Niyə sürətli? WHERE id > 1000 LIMIT 20 (index istifadə edir, OFFSET yoxdur)

// Manual cursor pagination
class CursorPaginator {
    public function paginate(Builder $query, int $perPage, ?string $cursor = null): array {
        if ($cursor) {
            $decoded = json_decode(base64_decode($cursor), true);
            $query->where('id', '>', $decoded['id']);
        }

        $items = $query->orderBy('id')->limit($perPage + 1)->get();

        $hasMore = $items->count() > $perPage;
        if ($hasMore) {
            $items = $items->take($perPage);
        }

        $nextCursor = $hasMore
            ? base64_encode(json_encode(['id' => $items->last()->id]))
            : null;

        return [
            'data' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }
}

// Nə vaxt hansı?
// Offset: Admin panel, kiçik dataset, random page access lazım olanda
// Cursor: API (infinite scroll), böyük dataset, real-time data (yeni sətir əlavə oluna bilər)
```

---

## 2. Specification Pattern

```php
interface Specification {
    public function isSatisfiedBy(Model $model): bool;
    public function toQuery(Builder $query): Builder;
}

class IsActiveUser implements Specification {
    public function isSatisfiedBy(Model $model): bool {
        return $model->active && $model->email_verified_at !== null;
    }

    public function toQuery(Builder $query): Builder {
        return $query->where('active', true)->whereNotNull('email_verified_at');
    }
}

class HasMinimumOrders implements Specification {
    public function __construct(private int $minimum) {}

    public function isSatisfiedBy(Model $model): bool {
        return $model->orders_count >= $this->minimum;
    }

    public function toQuery(Builder $query): Builder {
        return $query->has('orders', '>=', $this->minimum);
    }
}

class AndSpecification implements Specification {
    private array $specs;

    public function __construct(Specification ...$specs) {
        $this->specs = $specs;
    }

    public function isSatisfiedBy(Model $model): bool {
        foreach ($this->specs as $spec) {
            if (!$spec->isSatisfiedBy($model)) return false;
        }
        return true;
    }

    public function toQuery(Builder $query): Builder {
        foreach ($this->specs as $spec) {
            $query = $spec->toQuery($query);
        }
        return $query;
    }
}

// İstifadə
$loyalCustomers = new AndSpecification(
    new IsActiveUser(),
    new HasMinimumOrders(10),
);

$users = $loyalCustomers->toQuery(User::query())->get();
```

---

## 3. API Versioning — Controller level ilə tam misal

```php
// app/Http/Controllers/Api/V1/UserController.php
namespace App\Http\Controllers\Api\V1;

class UserController extends Controller {
    public function show(User $user): UserResource {
        return new UserResource($user->load('profile'));
    }
}

// V1 Resource — köhnə format
namespace App\Http\Resources\V1;

class UserResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}

// V2 — genişlədilmiş format, breaking changes
namespace App\Http\Controllers\Api\V2;

class UserController extends Controller {
    public function show(User $user): UserResource {
        return new UserResource($user->load(['profile', 'roles']));
    }
}

namespace App\Http\Resources\V2;

class UserResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,  // V1-də yox idi
            'last_name' => $this->last_name,     // V1-də yox idi
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'roles' => $this->whenLoaded('roles', fn () =>
                $this->roles->pluck('name')
            ),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

// routes/api.php
Route::prefix('v1')->name('v1.')->group(function () {
    Route::apiResource('users', V1\UserController::class);
});

Route::prefix('v2')->name('v2.')->group(function () {
    Route::apiResource('users', V2\UserController::class);
});

// Deprecation header (V1 üçün)
class DeprecatedApiMiddleware {
    public function handle(Request $request, Closure $next, string $sunset): Response {
        $response = $next($request);
        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', $sunset);
        $response->headers->set('Link', '</api/v2' . $request->getPathInfo() . '>; rel="successor-version"');
        return $response;
    }
}
```

---

## 4. Database Migration strategiyası — Feature Flag ilə yeni sütun

**Problem:** `users` cədvəlinə `phone_verified` sütunu əlavə etmək, amma bütün kodu bir anda dəyişmək istəmirsən.

```php
// Addım 1 — Sütun əlavə et (production-a deploy et)
class AddPhoneVerifiedToUsers extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('phone_verified')->default(false)->after('phone');
        });
    }
}

// Addım 2 — Feature flag ilə yeni funksionallığı aç
// config/features.php
return [
    'phone_verification' => env('FEATURE_PHONE_VERIFICATION', false),
];

// Kodda
class RegistrationService {
    public function register(RegisterDTO $dto): User {
        $user = User::create([...]);

        if (config('features.phone_verification')) {
            PhoneVerificationService::sendCode($user);
        }

        return $user;
    }
}

// Addım 3 — Feature flag-ı aç (.env dəyiş, deploy lazım deyil)
FEATURE_PHONE_VERIFICATION=true

// Addım 4 — Feature flag-ı koddan sil, birbaşa yaz
// (feature stabil olandan sonra)
```

---

## 5. Event-driven Architecture — Decoupled modules

```php
// Problem: OrderService, InventoryService, NotificationService, AnalyticsService
// bir-birini birbaşa çağırır → tight coupling

// Həll: Event bus ilə decouple et
// OrderService yalnız event atır, digərlərini bilmir

class OrderService {
    public function complete(Order $order): void {
        $order->update(['status' => OrderStatus::Completed]);

        // Tək event — bütün side effects listener-lərdə
        OrderCompleted::dispatch($order);
    }
}

// Hər modul öz listener-ini qeydiyyatdan keçirir
// InventoryModule
class DeductInventoryOnOrderComplete {
    public function handle(OrderCompleted $event): void {
        foreach ($event->order->items as $item) {
            Product::where('id', $item->product_id)
                ->decrement('stock', $item->quantity);
        }
    }
}

// NotificationModule
class SendOrderCompletionEmail implements ShouldQueue {
    public function handle(OrderCompleted $event): void {
        $event->order->user->notify(new OrderCompletedNotification($event->order));
    }
}

// AnalyticsModule
class TrackOrderCompletion implements ShouldQueue {
    public string $queue = 'analytics';

    public function handle(OrderCompleted $event): void {
        Analytics::track('order_completed', [
            'order_id' => $event->order->id,
            'total' => $event->order->total,
            'items_count' => $event->order->items->count(),
        ]);
    }
}

// LoyaltyModule
class AwardLoyaltyPoints implements ShouldQueue {
    public function handle(OrderCompleted $event): void {
        $points = (int) ($event->order->total * 10); // 10 point per AZN
        $event->order->user->loyaltyAccount->addPoints($points, "Order #{$event->order->order_number}");
    }
}

// EventServiceProvider — bütün listener-lər bir yerdə
protected $listen = [
    OrderCompleted::class => [
        DeductInventoryOnOrderComplete::class,
        SendOrderCompletionEmail::class,
        TrackOrderCompletion::class,
        AwardLoyaltyPoints::class,
        GenerateInvoicePdf::class,
    ],
];
// Yeni modul əlavə etmək üçün OrderService-ə toxunmağa ehtiyac yoxdur!
```

---

## 6. Soft Delete ilə Data Retention Policy

```php
// GDPR / Data retention: 1 il sonra user data anonim olsun, 2 il sonra silinsin

class DataRetentionService {
    // Step 1: Anonim et (1 il sonra)
    public function anonymizeInactiveUsers(): int {
        $count = 0;

        User::onlyTrashed()
            ->where('deleted_at', '<', now()->subYear())
            ->where('anonymized_at', null)
            ->chunkById(500, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $user->update([
                        'name' => 'Deleted User',
                        'email' => "deleted_{$user->id}@anonymous.local",
                        'phone' => null,
                        'address' => null,
                        'anonymized_at' => now(),
                    ]);

                    // Əlaqəli personal data
                    $user->profile()->update([
                        'bio' => null,
                        'avatar' => null,
                        'date_of_birth' => null,
                    ]);

                    $count++;
                }
            });

        return $count;
    }

    // Step 2: Həqiqi sil (2 il sonra)
    public function purgeOldUsers(): int {
        return User::onlyTrashed()
            ->where('deleted_at', '<', now()->subYears(2))
            ->each(function ($user) {
                // Cascade delete
                $user->orders()->forceDelete();
                $user->reviews()->forceDelete();
                $user->notifications()->delete();
                $user->tokens()->delete();
                $user->forceDelete();
            })
            ->count();
    }
}

// Schedule
Schedule::call(fn () => app(DataRetentionService::class)->anonymizeInactiveUsers())
    ->daily()->at('03:00');

Schedule::call(fn () => app(DataRetentionService::class)->purgeOldUsers())
    ->weekly()->sundays()->at('04:00');
```

---

## 7. Circuit Breaker Pattern — tam implementasiya

```php
enum CircuitState {
    case Closed;     // Normal — request-lər keçir
    case Open;       // Xəta çox — request-lər blok olunur
    case HalfOpen;   // Sınaq — bir neçə request buraxılır
}

class CircuitBreaker {
    public function __construct(
        private string $service,
        private int $failureThreshold = 5,
        private int $recoveryTimeout = 30,      // saniyə
        private int $halfOpenMaxAttempts = 3,
    ) {}

    public function call(Closure $action, ?Closure $fallback = null): mixed {
        $state = $this->getState();

        if ($state === CircuitState::Open) {
            Log::warning("Circuit breaker OPEN for {$this->service}");
            return $fallback ? $fallback() : throw new ServiceUnavailableException($this->service);
        }

        try {
            $result = $action();

            if ($state === CircuitState::HalfOpen) {
                $this->recordSuccess();
            } else {
                $this->resetFailures();
            }

            return $result;

        } catch (\Throwable $e) {
            $this->recordFailure();

            if ($this->getFailureCount() >= $this->failureThreshold) {
                $this->trip();
            }

            if ($fallback) return $fallback();
            throw $e;
        }
    }

    private function getState(): CircuitState {
        $trippedAt = Cache::get("circuit:{$this->service}:tripped_at");

        if (!$trippedAt) return CircuitState::Closed;

        $elapsed = now()->diffInSeconds(Carbon::parse($trippedAt));

        if ($elapsed < $this->recoveryTimeout) {
            return CircuitState::Open;
        }

        return CircuitState::HalfOpen;
    }

    private function trip(): void {
        Cache::put("circuit:{$this->service}:tripped_at", now()->toISOString(), 3600);
        Log::alert("Circuit breaker TRIPPED for {$this->service}");
    }

    private function recordFailure(): void {
        $key = "circuit:{$this->service}:failures";
        Cache::increment($key);
        Cache::put($key, Cache::get($key, 0), 120); // 2 dəqiqə window
    }

    private function recordSuccess(): void {
        $this->resetFailures();
        Cache::forget("circuit:{$this->service}:tripped_at");
        Log::info("Circuit breaker RECOVERED for {$this->service}");
    }

    private function resetFailures(): void {
        Cache::forget("circuit:{$this->service}:failures");
    }

    private function getFailureCount(): int {
        return (int) Cache::get("circuit:{$this->service}:failures", 0);
    }
}

// İstifadə
$breaker = new CircuitBreaker('payment-gateway', failureThreshold: 3, recoveryTimeout: 60);

$result = $breaker->call(
    action: fn () => Http::timeout(5)->post('https://payment.example.com/charge', $data)->throw()->json(),
    fallback: fn () => ['status' => 'queued', 'message' => 'Payment will be processed shortly'],
);
```

---

## 8. Health Check endpoint — production monitoring

```php
class HealthCheckController extends Controller {
    public function __invoke(): JsonResponse {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'external_apis' => $this->checkExternalApis(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', 'unknown'),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array {
        try {
            $start = microtime(true);
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array {
        try {
            $queueSize = Redis::llen('queues:default');
            $failedCount = DB::table('failed_jobs')->count();

            return [
                'status' => $queueSize < 10000 ? 'ok' : 'warning',
                'pending_jobs' => $queueSize,
                'failed_jobs' => $failedCount,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage(): array {
        try {
            $testFile = 'health-check-' . Str::random(8) . '.tmp';
            Storage::put($testFile, 'ok');
            Storage::delete($testFile);

            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkExternalApis(): array {
        $results = [];

        $apis = [
            'stripe' => 'https://api.stripe.com/v1',
            'mailgun' => 'https://api.mailgun.net/v3',
        ];

        foreach ($apis as $name => $url) {
            try {
                $start = microtime(true);
                Http::timeout(3)->head($url);
                $latency = round((microtime(true) - $start) * 1000, 2);
                $results[$name] = ['status' => 'ok', 'latency_ms' => $latency];
            } catch (\Throwable) {
                $results[$name] = ['status' => 'error'];
            }
        }

        return $results;
    }
}
```

---

## 9. Regex — PHP-də tez-tez soruşulan

```php
// Email validation (simplified)
preg_match('/^[\w.+-]+@[\w-]+\.[\w.]+$/', $email);

// Phone number (Azərbaycan)
preg_match('/^\+994(50|51|55|70|77|99)\d{7}$/', $phone);

// URL extract
preg_match_all('/https?:\/\/[^\s<>"]+/', $text, $matches);

// HTML tag strip (daha yaxşı: strip_tags)
$clean = preg_replace('/<[^>]*>/', '', $html);

// Slug generate
$slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($string));
$slug = trim($slug, '-');

// Named groups
preg_match('/(?P<year>\d{4})-(?P<month>\d{2})-(?P<day>\d{2})/', '2026-04-11', $m);
echo $m['year'];  // 2026
echo $m['month']; // 04

// Replace with callback
$result = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($data) {
    return $data[$matches[1]] ?? $matches[0];
}, 'Hello {name}, you have {count} messages');
```

---

## 10. PHP 8.4 yenilikləri (2024)

```php
// Property Hooks
class User {
    public string $fullName {
        get => $this->firstName . ' ' . $this->lastName;
        set (string $value) {
            [$this->firstName, $this->lastName] = explode(' ', $value, 2);
        }
    }
}

// Asymmetric Visibility
class BankAccount {
    public private(set) float $balance = 0; // Oxumaq public, yazmaq private
}

$account = new BankAccount();
echo $account->balance;      // OK
$account->balance = 100;     // Error!

// new without parentheses in expressions
$name = (new ReflectionClass(User::class))->getName();
// PHP 8.4:
$name = new ReflectionClass(User::class)->getName();

// array_find, array_find_key, array_any, array_all
$firstAdmin = array_find($users, fn ($user) => $user->role === 'admin');
$hasAdmin = array_any($users, fn ($user) => $user->role === 'admin');
$allActive = array_all($users, fn ($user) => $user->active);
```
