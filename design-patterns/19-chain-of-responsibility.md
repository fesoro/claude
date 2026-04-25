# Chain of Responsibility (Senior ⭐⭐⭐)

## İcmal
Chain of Responsibility pattern request handler-larını zəncirə bağlayır. Hər handler request-i ya özü işlədib zənciri dayandırır, ya da növbəti handler-ə ötürür. Request göndərici konkret hansı handler-in işlədəcəyini bilmir. "Kimin edəcəyi"ni dinamik qurmağa imkan verir.

## Niyə Vacibdir
Laravel Middleware bu pattern-in birbaşa implementation-ıdır: `$next($request)` — request növbəti handler-ə ötürülür. Laravel Pipeline da eyni mexanizmi istifadə edir. Validation pipeline, order processing workflow, approval chain — real layihələrdə bu pattern-ə tez-tez ehtiyac yaranır.

## Əsas Anlayışlar
- **Handler interface**: `handle(Request $request)` + `setNext(Handler $next)` metodları
- **AbstractHandler**: `setNext()` və default `handle()` logic-i (növbəti-yə ötür) — boilerplate azaldır
- **ConcreteHandler**: konkret handler — ya request-i işlədib dayanır, ya `$next->handle()` çağırır
- **Chain building**: handler-lar ardıcıl əlaqələndirilir: `$a->setNext($b)->setNext($c)`
- **Early stopping**: handler request-i işlədisə növbəti handler-ə ötürməyə bilər
- **Fallback**: zəncirin sonuna qədər heç kim işlətmədisə default davranış

## Praktik Baxış
- **Real istifadə**: HTTP middleware (auth → rate limit → cors → validation → controller), order validation (inventory → credit → fraud → fulfillment), file upload processing (virus scan → size check → format check → save), multi-level approval workflow
- **Trade-off-lar**: request hansı handler-in işlədiyini izləmək çətin — debug mürəkkəbdir; chain düzgün qurulmasa request handle olunmadan keçib gedər; performans — hər handler yoxlama edir
- **İstifadə etməmək**: handler sayı azsa (1-2) və dinamik deyilsə — sadə if/else kifayətdir; request mütləq bir konkret handler tərəfindən işlənməlidirsə (ambiguity yoxdur)
- **Common mistakes**:
  1. Handler-da zənciri başlatmaq (circular reference)
  2. `setNext()` çağırmağı unutmaq — zəncir kəsilir, sonrakı handler-lər işləmir
  3. Handler-da həm işlədib həm də növbəti-yə ötürmək (logic conflict)

## Nümunələr

### Ümumi Nümunə
Şirkətin kredit təsdiq sistemi düşünün. $1000-a qədər menencer təsdiqləyir, $10000-a qədər direktor, daha çoxu CFO. Hər kəs öz limitindən artıq olduqda növbəti-yə ötürür. Yeni limit qatını əlavə etmək üçün mövcud koda toxunmursunuz — sadə yeni handler əlavə edirsiniz.

### PHP/Laravel Nümunəsi

**Handler interface + Abstract base:**

```php
<?php

// Handler interface
interface OrderHandler
{
    public function setNext(OrderHandler $handler): OrderHandler;
    public function handle(Order $order): OrderResult;
}

// Abstract base — boilerplate azaldır
abstract class AbstractOrderHandler implements OrderHandler
{
    private ?OrderHandler $next = null;

    public function setNext(OrderHandler $handler): OrderHandler
    {
        $this->next = $handler;
        return $handler; // fluent — chain qurmaq üçün
    }

    public function handle(Order $order): OrderResult
    {
        if ($this->next !== null) {
            return $this->next->handle($order);
        }
        // Zəncirin sonu — default: uğurlu
        return OrderResult::success($order);
    }
}
```

**ConcreteHandlers — order validation pipeline:**

```php
// Handler 1: inventory yoxla
class InventoryCheckHandler extends AbstractOrderHandler
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Order $order): OrderResult
    {
        foreach ($order->items as $item) {
            $available = $this->inventory->getStock($item->product_id);
            if ($available < $item->quantity) {
                // İşlə, zənciri dayandır
                return OrderResult::failure(
                    "Insufficient stock for product #{$item->product_id}. " .
                    "Requested: {$item->quantity}, Available: {$available}"
                );
            }
        }
        // Keç, növbəti handler-ə ötür
        return parent::handle($order);
    }
}

// Handler 2: kredit limiti yoxla
class CreditCheckHandler extends AbstractOrderHandler
{
    public function __construct(private readonly CreditService $credit) {}

    public function handle(Order $order): OrderResult
    {
        $limit     = $this->credit->getLimit($order->user_id);
        $totalDebt = $this->credit->getTotalDebt($order->user_id);

        if ($totalDebt + $order->total > $limit) {
            return OrderResult::failure(
                "Credit limit exceeded. Limit: {$limit}, Current debt: {$totalDebt}, Order: {$order->total}"
            );
        }

        return parent::handle($order);
    }
}

// Handler 3: fraud detection
class FraudDetectionHandler extends AbstractOrderHandler
{
    public function __construct(private readonly FraudService $fraud) {}

    public function handle(Order $order): OrderResult
    {
        $riskScore = $this->fraud->calculateRiskScore($order);

        if ($riskScore > 0.8) {
            // Yüksək risk: manual review-a yönləndir
            $order->update(['status' => 'pending_review', 'fraud_score' => $riskScore]);
            return OrderResult::requiresReview("High fraud risk score: {$riskScore}");
        }

        if ($riskScore > 0.5) {
            // Orta risk: extra verifikasiya tələb et amma davam et
            $order->update(['requires_verification' => true, 'fraud_score' => $riskScore]);
        }

        return parent::handle($order); // Davam et
    }
}

// Handler 4: fulfillment (son handler — rezerv et)
class FulfillmentHandler extends AbstractOrderHandler
{
    public function __construct(private readonly WarehouseService $warehouse) {}

    public function handle(Order $order): OrderResult
    {
        // Bu son handler — artıq real iş görür
        $this->warehouse->reserveItems($order);
        $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        return OrderResult::success($order);
    }
}
```

**Chain qurmaq və istifadəsi:**

```php
class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CreditService    $credit,
        private readonly FraudService     $fraud,
        private readonly WarehouseService $warehouse,
    ) {}

    private function buildValidationChain(): OrderHandler
    {
        $inventory   = new InventoryCheckHandler($this->inventory);
        $credit      = new CreditCheckHandler($this->credit);
        $fraud       = new FraudDetectionHandler($this->fraud);
        $fulfillment = new FulfillmentHandler($this->warehouse);

        // Zənciri qur: inventory → credit → fraud → fulfillment
        $inventory
            ->setNext($credit)
            ->setNext($fraud)
            ->setNext($fulfillment);

        return $inventory; // zəncirin başı qaytarılır
    }

    public function placeOrder(Order $order): OrderResult
    {
        $chain = $this->buildValidationChain();
        return $chain->handle($order);
    }
}

// OrderResult value object
class OrderResult
{
    private function __construct(
        public readonly bool    $success,
        public readonly bool    $requiresReview,
        public readonly ?string $message,
        public readonly ?Order  $order,
    ) {}

    public static function success(Order $order): self
    {
        return new self(true, false, null, $order);
    }

    public static function failure(string $message): self
    {
        return new self(false, false, $message, null);
    }

    public static function requiresReview(string $message): self
    {
        return new self(false, true, $message, null);
    }
}
```

**Laravel Middleware = Chain of Responsibility:**

```php
// Laravel Middleware eyni pattern-dir
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()->hasVerifiedEmail()) {
            return response()->json(['error' => 'Email not verified'], 403);
            // $next çağırılmır — zəncir dayandı
        }

        return $next($request); // $next = növbəti middleware/controller
    }
}

class ThrottleRequests
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60): Response
    {
        if ($this->tooManyAttempts($request, $maxAttempts)) {
            return $this->buildResponse($request, $maxAttempts);
            // Dayandı
        }

        $this->hit($request, $maxAttempts);
        return $next($request); // Davam edir
    }
}

// routes/api.php
Route::middleware([
    'auth:sanctum',        // 1. Auth yoxla
    'verified',            // 2. Email verification yoxla
    'throttle:60,1',       // 3. Rate limit yoxla
    EnsureSubscription::class, // 4. Subscription yoxla
])->group(function () {
    // Bütün handler-lardan keçən request buraya çatır
    Route::apiResource('orders', OrderController::class);
});
```

**Laravel Pipeline — generic chain:**

```php
// Laravel Pipeline-ı istifadə etmək
use Illuminate\Pipeline\Pipeline;

class OrderController
{
    public function store(Request $request, Pipeline $pipeline): JsonResponse
    {
        $order = Order::create($request->validated());

        $result = $pipeline
            ->send($order)
            ->through([
                CheckInventory::class,
                CheckCreditLimit::class,
                DetectFraud::class,
                ReserveItems::class,
            ])
            ->thenReturn(); // son nəticəni qaytarır

        return response()->json(new OrderResource($result));
    }
}

// Pipeline pipe-ları (handle yerinə __invoke)
class CheckInventory
{
    public function handle(Order $order, Closure $next): Order
    {
        foreach ($order->items as $item) {
            if ($item->product->stock < $item->quantity) {
                throw new InsufficientStockException($item->product);
            }
        }
        return $next($order);
    }
}

class DetectFraud
{
    public function handle(Order $order, Closure $next): Order
    {
        $score = app(FraudService::class)->score($order);
        $order->fraud_score = $score;

        if ($score > 0.9) {
            throw new HighFraudRiskException($score);
        }

        return $next($order);
    }
}
```

**Chain of Responsibility vs Decorator fərqi:**

```php
// CoR: handler-lardan BIRI işlədə bilər ya da heç biri
// Məqsəd: hansı handler cavabdehdir, onu tap
// Zəncir işləyən handler-dən SONRA dayanır (adətən)

// Decorator: HAMISI işlədilir, hər biri bir şey əlavə edir
// Məqsəd: behaviour stack qurmaq
// Bütün wrapper-lar həmişə çalışır

// Middleware istisnası: Laravel middleware-lər
// həm CoR (auth block edə bilər = stop), həm də
// Decorator kimi (logging middleware — hamısını pass edir, sadəcə log edir)
```

## Praktik Tapşırıqlar
1. File upload üçün chain qurun: `VirusScanHandler` → `FileSizeHandler` → `FileTypeHandler` → `SaveToStorageHandler`; hər biri fail olarsa müvafiq exception atır
2. Multi-level expense approval: $500-a qədər manager, $5000-a qədər director, daha çox CFO; hər handler-in approve etdiyi log-lanır
3. Laravel Pipeline ilə HTTP request validation pipeline qurun: `SanitizeInput` → `ValidateSchema` → `CheckRateLimit` → `ProcessRequest`

## Əlaqəli Mövzular
- [06-decorator.md](06-decorator.md) — Decorator da zəncir qurur amma hamısı işlədilir
- [15-service-layer.md](15-service-layer.md) — Service-lərdə pipeline logic
- [16-event-listener.md](16-event-listener.md) — Event listener-lər paralel CoR kimi
- [20-state.md](20-state.md) — State dəyişikliyi CoR ilə birgə işlədilir
