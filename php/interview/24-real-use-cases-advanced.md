# Real Use Cases — Advanced Scenarios

## 1. Rate Limiter ilə API Throttling (Sliding Window)

**Problem:** API endpoint-ə saniyədə 100-dən çox request gəlir, servisi qorumaq lazımdır.

```php
// Sliding window rate limiter — daha dəqiq nəticə verir
class SlidingWindowRateLimiter {
    public function attempt(string $key, int $maxRequests, int $windowSeconds): RateLimitResult {
        $now = microtime(true);
        $windowStart = $now - $windowSeconds;
        $redisKey = "ratelimit:{$key}";

        // Lua script — atomic əməliyyat
        $result = Redis::eval(<<<'LUA'
            local key = KEYS[1]
            local now = tonumber(ARGV[1])
            local window_start = tonumber(ARGV[2])
            local max_requests = tonumber(ARGV[3])
            local window_seconds = tonumber(ARGV[4])

            -- Köhnə entry-ləri sil
            redis.call('zremrangebyscore', key, '-inf', window_start)

            -- Cari sayı al
            local current = redis.call('zcard', key)

            if current < max_requests then
                -- İcazə ver, əlavə et
                redis.call('zadd', key, now, now .. ':' .. math.random(1000000))
                redis.call('expire', key, window_seconds)
                return {1, max_requests - current - 1}
            else
                -- Limit aşılıb
                local oldest = redis.call('zrange', key, 0, 0, 'WITHSCORES')
                local retry_after = oldest[2] and (tonumber(oldest[2]) + window_seconds - now) or window_seconds
                return {0, 0, math.ceil(retry_after)}
            end
        LUA, 1, $redisKey, $now, $windowStart, $maxRequests, $windowSeconds);

        return new RateLimitResult(
            allowed: (bool) $result[0],
            remaining: (int) $result[1],
            retryAfter: $result[2] ?? null,
        );
    }
}

// Middleware
class ApiThrottleMiddleware {
    public function handle(Request $request, Closure $next): Response {
        $key = $this->resolveKey($request);
        $limits = $this->resolveLimits($request);

        $limiter = app(SlidingWindowRateLimiter::class);

        foreach ($limits as $limit) {
            $result = $limiter->attempt($key . ':' . $limit['window'], $limit['max'], $limit['window']);

            if (!$result->allowed) {
                return response()->json([
                    'error' => 'Rate limit exceeded',
                    'retry_after' => $result->retryAfter,
                ], 429)->withHeaders([
                    'Retry-After' => $result->retryAfter,
                    'X-RateLimit-Limit' => $limit['max'],
                    'X-RateLimit-Remaining' => 0,
                ]);
            }
        }

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $limits[0]['max'],
            'X-RateLimit-Remaining' => $result->remaining,
        ]);
    }

    private function resolveLimits(Request $request): array {
        $user = $request->user();

        if ($user?->isPremium()) {
            return [
                ['max' => 1000, 'window' => 60],    // 1000/dəqiqə
                ['max' => 20000, 'window' => 3600],  // 20000/saat
            ];
        }

        return [
            ['max' => 100, 'window' => 60],      // 100/dəqiqə
            ['max' => 2000, 'window' => 3600],    // 2000/saat
        ];
    }

    private function resolveKey(Request $request): string {
        return $request->user()?->id
            ? 'user:' . $request->user()->id
            : 'ip:' . $request->ip();
    }
}
```

---

## 2. Distributed Locking — Eyni Job-un paralel icrasının qarşısını almaq

**Problem:** 3 server eyni queue-dan oxuyur, eyni order 2 dəfə emal olunur.

```php
class ProcessOrderJob implements ShouldQueue {
    public int $tries = 3;

    public function __construct(private int $orderId) {}

    public function handle(OrderService $service): void {
        // Redis distributed lock
        $lock = Cache::lock("process_order:{$this->orderId}", 120); // 2 dəqiqə

        if (!$lock->get()) {
            // Başqa worker artıq emal edir — retry etmə, sil
            Log::info("Order {$this->orderId} already being processed, skipping.");
            return;
        }

        try {
            $order = Order::findOrFail($this->orderId);

            // Idempotency check
            if ($order->status !== OrderStatus::Pending) {
                Log::info("Order {$this->orderId} already in status {$order->status->value}");
                return;
            }

            $service->process($order);
        } finally {
            $lock->release();
        }
    }

    // Unique job — eyni order üçün queue-da 1-dən çox job olmasın
    public function uniqueId(): string {
        return (string) $this->orderId;
    }

    public int $uniqueFor = 300; // 5 dəqiqə unique
}
```

---

## 3. Event-driven Inventory Management

**Problem:** 1000 nəfər eyni məhsulu eyni anda almaq istəyir (flash sale).

```php
class InventoryManager {
    // Redis-based atomic stock management
    public function initializeStock(int $productId, int $quantity): void {
        Redis::set("stock:{$productId}", $quantity);
    }

    public function reserve(int $productId, int $quantity, string $reservationId): bool {
        // Lua script — atomic decrement with check
        $result = Redis::eval(<<<'LUA'
            local stock_key = KEYS[1]
            local reservation_key = KEYS[2]
            local quantity = tonumber(ARGV[1])
            local reservation_id = ARGV[2]
            local ttl = tonumber(ARGV[3])

            local current = tonumber(redis.call('get', stock_key) or 0)

            if current >= quantity then
                redis.call('decrby', stock_key, quantity)
                redis.call('setex', reservation_key, ttl, quantity)
                return 1
            end

            return 0
        LUA, 2,
            "stock:{$productId}",
            "reservation:{$reservationId}",
            $quantity,
            $reservationId,
            900 // 15 dəqiqə reservation TTL
        );

        if ($result) {
            // DB-yə async yaz
            SyncStockToDatabase::dispatch($productId);
            return true;
        }

        return false;
    }

    public function confirmReservation(string $reservationId, int $productId): void {
        $quantity = Redis::get("reservation:{$reservationId}");
        if ($quantity) {
            Redis::del("reservation:{$reservationId}");

            // DB-də stock azalt
            Product::where('id', $productId)->decrement('stock', (int) $quantity);
        }
    }

    public function releaseReservation(string $reservationId, int $productId): void {
        $quantity = Redis::get("reservation:{$reservationId}");
        if ($quantity) {
            Redis::incrby("stock:{$productId}", (int) $quantity);
            Redis::del("reservation:{$reservationId}");
        }
    }

    // Expired reservation-ları geri qaytar (scheduled task)
    public function releaseExpiredReservations(): void {
        // Redis key expiry event istifadə et
        // Və ya scheduled command ilə DB-dəki pending reservation-ları yoxla
        Booking::where('status', 'reserved')
            ->where('reserved_at', '<', now()->subMinutes(15))
            ->each(function ($booking) {
                $this->releaseReservation($booking->reservation_id, $booking->product_id);
                $booking->update(['status' => 'expired']);
            });
    }
}

// Flash sale controller
class FlashSaleController extends Controller {
    public function purchase(Request $request, Product $product): JsonResponse {
        $quantity = $request->validated('quantity', 1);
        $reservationId = Str::uuid()->toString();

        $inventory = app(InventoryManager::class);

        if (!$inventory->reserve($product->id, $quantity, $reservationId)) {
            return response()->json([
                'error' => 'Stok bitib!',
                'available' => (int) Redis::get("stock:{$product->id}"),
            ], 409);
        }

        try {
            // Payment process
            $order = app(OrderService::class)->createFromFlashSale(
                $product, $quantity, $reservationId, $request->user()
            );

            $inventory->confirmReservation($reservationId, $product->id);

            return response()->json(new OrderResource($order), 201);

        } catch (\Throwable $e) {
            // Ödəniş uğursuz olsa stoku geri qaytar
            $inventory->releaseReservation($reservationId, $product->id);
            throw $e;
        }
    }
}
```

---

## 4. Webhook Delivery sistemi — göndərən tərəf

**Problem:** SaaS platformanız var, müştərilərə event-lər göndərirsiniz.

```php
// webhook_endpoints table: id, user_id, url, secret, events, is_active, created_at
// webhook_deliveries table: id, endpoint_id, event, payload, status, attempts, response_status, response_body, next_retry_at

class WebhookDispatcher {
    public function dispatch(string $event, array $payload, ?int $userId = null): void {
        $endpoints = WebhookEndpoint::where('is_active', true)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->filter(fn ($endpoint) => in_array($event, $endpoint->events) || in_array('*', $endpoint->events));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
            ]);

            SendWebhook::dispatch($delivery);
        }
    }
}

class SendWebhook implements ShouldQueue {
    public int $tries = 1; // Retry-ı özümüz idarə edirik

    public function __construct(private WebhookDelivery $delivery) {}

    public function handle(): void {
        $endpoint = $this->delivery->endpoint;
        $payload = $this->delivery->payload;

        // İmza yarat
        $timestamp = time();
        $signature = hash_hmac('sha256',
            $timestamp . '.' . json_encode($payload),
            $endpoint->secret
        );

        $this->delivery->increment('attempts');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $this->delivery->event,
                    'X-Webhook-Signature' => "t={$timestamp},v1={$signature}",
                    'X-Webhook-Delivery-Id' => $this->delivery->id,
                ])
                ->post($endpoint->url, $payload);

            $this->delivery->update([
                'status' => $response->successful() ? 'delivered' : 'failed',
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 1000),
            ]);

            if (!$response->successful()) {
                $this->scheduleRetry();
            }

        } catch (\Throwable $e) {
            $this->delivery->update([
                'status' => 'failed',
                'response_body' => $e->getMessage(),
            ]);

            $this->scheduleRetry();
        }
    }

    private function scheduleRetry(): void {
        $attempt = $this->delivery->attempts;

        // Exponential backoff: 1m, 5m, 30m, 2h, 8h (max 5 retry)
        $delays = [60, 300, 1800, 7200, 28800];

        if ($attempt > count($delays)) {
            $this->delivery->update(['status' => 'exhausted']);

            // Endpoint-i deaktiv et (çox uğursuz)
            $failedCount = WebhookDelivery::where('endpoint_id', $this->delivery->endpoint_id)
                ->where('status', 'exhausted')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            if ($failedCount >= 10) {
                $this->delivery->endpoint->update(['is_active' => false]);
                // Sahibinə bildiriş
            }

            return;
        }

        $delay = $delays[$attempt - 1];
        $this->delivery->update([
            'status' => 'pending_retry',
            'next_retry_at' => now()->addSeconds($delay),
        ]);

        SendWebhook::dispatch($this->delivery)->delay(now()->addSeconds($delay));
    }
}
```

---

## 5. Multi-step Form / Wizard (state management)

```php
// Misal: Şirkət qeydiyyatı — 4 addım
// Step 1: Şirkət məlumatları
// Step 2: Ünvan
// Step 3: Sənədlər (fayl upload)
// Step 4: Təsdiqləmə

class RegistrationWizardService {
    public function startOrResume(User $user): WizardState {
        return WizardState::firstOrCreate(
            ['user_id' => $user->id, 'type' => 'company_registration'],
            ['current_step' => 1, 'data' => [], 'status' => 'in_progress'],
        );
    }

    public function submitStep(WizardState $wizard, int $step, array $data): WizardState {
        if ($step !== $wizard->current_step) {
            throw new InvalidStepException("Gözlənilən addım: {$wizard->current_step}");
        }

        // Step-specific validation
        $validated = $this->validateStep($step, $data);

        // Data-nı saxla (merge)
        $wizard->update([
            'data' => array_merge($wizard->data, ["step_{$step}" => $validated]),
            'current_step' => min($step + 1, 4),
        ]);

        // Son addımdırsa — finalize
        if ($step === 4) {
            return $this->finalize($wizard);
        }

        return $wizard->fresh();
    }

    public function goBack(WizardState $wizard): WizardState {
        if ($wizard->current_step <= 1) {
            throw new InvalidStepException('Geri gedə bilməzsiniz.');
        }

        $wizard->update(['current_step' => $wizard->current_step - 1]);
        return $wizard->fresh();
    }

    private function validateStep(int $step, array $data): array {
        $rules = match($step) {
            1 => [
                'company_name' => 'required|string|max:255',
                'tax_id' => 'required|string|unique:companies,tax_id',
                'industry' => 'required|string',
                'employee_count' => 'required|integer|min:1',
            ],
            2 => [
                'address_line_1' => 'required|string',
                'city' => 'required|string',
                'country' => 'required|string|size:2',
                'postal_code' => 'required|string',
            ],
            3 => [
                'registration_doc' => 'required|file|mimes:pdf|max:5120',
                'tax_certificate' => 'required|file|mimes:pdf|max:5120',
            ],
            4 => [
                'terms_accepted' => 'required|accepted',
                'privacy_accepted' => 'required|accepted',
            ],
        };

        return validator($data, $rules)->validate();
    }

    private function finalize(WizardState $wizard): WizardState {
        $allData = $wizard->data;

        DB::transaction(function () use ($wizard, $allData) {
            $company = Company::create([
                'user_id' => $wizard->user_id,
                ...$allData['step_1'],
                ...$allData['step_2'],
                'status' => 'pending_review',
            ]);

            // Sənədləri company-yə bağla
            // ...

            $wizard->update(['status' => 'completed']);
        });

        CompanyRegistrationSubmitted::dispatch($wizard->user);

        return $wizard->fresh();
    }
}

// API endpoints
Route::prefix('registration')->group(function () {
    Route::get('/', [WizardController::class, 'show']);        // Cari vəziyyət
    Route::post('/step/{step}', [WizardController::class, 'submit']);  // Addım təsdiqlə
    Route::post('/back', [WizardController::class, 'back']);           // Geri
});
```

---

## 6. Scheduled Price Change (E-commerce)

```php
// price_schedules table:
// id, product_id, price, starts_at, ends_at, priority, created_at

class PriceService {
    public function getCurrentPrice(Product $product): float {
        $scheduledPrice = PriceSchedule::where('product_id', $product->id)
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->value('price');

        return $scheduledPrice ?? $product->base_price;
    }

    public function schedulePrice(
        Product $product,
        float $price,
        Carbon $startsAt,
        ?Carbon $endsAt = null,
        int $priority = 0,
    ): PriceSchedule {
        $schedule = PriceSchedule::create([
            'product_id' => $product->id,
            'price' => $price,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'priority' => $priority,
        ]);

        // Cache invalidation job (starts_at zamanında)
        InvalidateProductPriceCache::dispatch($product->id)
            ->delay($startsAt);

        if ($endsAt) {
            InvalidateProductPriceCache::dispatch($product->id)
                ->delay($endsAt);
        }

        return $schedule;
    }
}

// Product model-də
class Product extends Model {
    public function getCurrentPrice(?int $variantId = null): float {
        return Cache::remember(
            "product_price:{$this->id}:" . ($variantId ?? 'base'),
            300, // 5 dəqiqə cache
            fn () => app(PriceService::class)->getCurrentPrice($this)
        );
    }
}
```
