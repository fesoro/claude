# Temporal Workflow Orchestration (Lead)

## İcmal
Temporal — uzunmüddətli, fault-tolerant iş axınlarını (workflow) kod kimi idarə etməyə imkan verən open-source orkestrasiya platformasıdır. Laravel queue jobs-dan fərqli olaraq, Temporal workflow-lar state-ful-dur, retry-ı daxili idarə edir, istənilən anda durdurub davam etdirmək mümkündür.

## Niyə Vacibdir
E-commerce checkout, KYC verification, multi-step onboarding, loan approval — bunlar saatlar/günlər çəkə bilən, external API-lar çağıran, kompensasiya tələb edən proseslərdir. Laravel job chain-ləri bu ssenarilərdə state itirir, retry məntiqi dağılır, partial failure-da recovery çətindir. Temporal bu problemi fundamental həll edir.

## Əsas Anlayışlar

### Workflow vs Activity
```
Workflow = Orchestrator (sadəcə kontrol axını, deterministik, idempotent)
Activity = Real iş görən kod (external API, DB, email, payment)
```

```
Temporal Server
    │
    ├── Workflow Worker (PHP) — iş axınını idarə edir
    └── Activity Worker (PHP) — real əməliyyatları yerinə yetirir
```

### PHP SDK Qurulumu
```bash
# RoadRunner ilə Temporal PHP SDK
composer require temporal/sdk

# RoadRunner binary
./rr get-binary
```

```yaml
# .rr.yaml
server:
  command: "php app/temporal-worker.php"

temporal:
  address: localhost:7233
  activities:
    num_workers: 10
  workflows:
    num_workers: 5
```

## Workflow Yazımı

### Sadə Nümunə — Order Processing
```php
// app/Temporal/Workflows/OrderWorkflow.php

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface OrderWorkflowInterface
{
    #[WorkflowMethod(name: 'OrderWorkflow')]
    public function process(int $orderId): string;
}

// Implementation
class OrderWorkflow implements OrderWorkflowInterface
{
    private ActivityStubInterface $activities;

    public function __construct()
    {
        // Activity stub — timeout və retry siyasəti
        $this->activities = Workflow::newActivityStub(
            OrderActivitiesInterface::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::minutes(30))
                ->withStartToCloseTimeout(CarbonInterval::minutes(5))
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withMaximumAttempts(3)
                        ->withNonRetryableExceptions([PaymentDeclinedException::class])
                )
        );
    }

    public function process(int $orderId): string
    {
        // Workflow kodu deterministik olmalıdır — random, time, I/O olmamalıdır
        
        // 1. Stok yoxla
        yield $this->activities->reserveInventory($orderId);
        
        // 2. Ödəniş al
        try {
            $transactionId = yield $this->activities->chargePayment($orderId);
        } catch (PaymentDeclinedException $e) {
            // Kompensasiya: stoku azad et
            yield $this->activities->releaseInventory($orderId);
            throw $e;
        }
        
        // 3. Email göndər
        yield $this->activities->sendConfirmationEmail($orderId, $transactionId);
        
        // 4. Gəlinciyi yenilə
        yield $this->activities->updateOrderStatus($orderId, 'completed');
        
        return $transactionId;
    }
}
```

### Activity Yazımı
```php
// app/Temporal/Activities/OrderActivities.php

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'OrderActivities.')]
interface OrderActivitiesInterface
{
    #[ActivityMethod]
    public function reserveInventory(int $orderId): void;
    
    #[ActivityMethod]
    public function chargePayment(int $orderId): string;
    
    #[ActivityMethod]
    public function sendConfirmationEmail(int $orderId, string $transactionId): void;
    
    #[ActivityMethod]
    public function releaseInventory(int $orderId): void;
}

class OrderActivities implements OrderActivitiesInterface
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PaymentGateway $payment,
        private readonly Mailer $mailer,
        private readonly OrderRepository $orders,
    ) {}

    public function reserveInventory(int $orderId): void
    {
        $order = $this->orders->findOrFail($orderId);
        $this->inventory->reserve($order->items);
        // Xəta atılsa Temporal retry edir
    }

    public function chargePayment(int $orderId): string
    {
        $order  = $this->orders->findOrFail($orderId);
        $result = $this->payment->charge($order->total, $order->payment_method);
        
        if (!$result->success) {
            throw new PaymentDeclinedException($result->errorCode);
        }
        
        return $result->transactionId;
    }

    public function sendConfirmationEmail(int $orderId, string $transactionId): void
    {
        $order = $this->orders->findOrFail($orderId);
        $this->mailer->to($order->user)->send(new OrderConfirmed($order, $transactionId));
    }
}
```

### Workflow Başlatmaq
```php
// Laravel controller ya da job-dan başlat
use Temporal\Client\WorkflowClient;

class OrderController extends Controller
{
    public function __construct(private WorkflowClient $temporal) {}

    public function checkout(Request $request): JsonResponse
    {
        $order = Order::create($request->validated());
        
        // Workflow başlat — async
        $workflow = $this->temporal->newWorkflowStub(
            OrderWorkflowInterface::class,
            WorkflowOptions::new()
                ->withWorkflowId('order-' . $order->id)  // idempotency key
                ->withWorkflowExecutionTimeout(CarbonInterval::hours(24))
        );
        
        WorkflowClient::start($workflow, $order->id);
        
        return response()->json(['order_id' => $order->id, 'status' => 'processing'], 202);
    }
    
    // Nəticəni gözlə (sync)
    public function checkStatus(int $orderId): JsonResponse
    {
        $handle = $this->temporal->getHandle(
            'order-' . $orderId,
            OrderWorkflowInterface::class
        );
        
        $result = $handle->getResult(); // tamamlanana qədər gözləyir
        return response()->json(['transaction_id' => $result]);
    }
}
```

## Signal və Query

### Signal — Çalışan Workflow-a Mesaj Göndər
```php
// KYC approval manual step
#[WorkflowInterface]
interface KycWorkflowInterface
{
    #[WorkflowMethod]
    public function run(int $userId): string;
    
    #[SignalMethod]
    public function approve(string $reviewerId): void;
    
    #[SignalMethod]
    public function reject(string $reason): void;
    
    #[QueryMethod]
    public function getStatus(): string;
}

class KycWorkflow implements KycWorkflowInterface
{
    private bool $approved = false;
    private bool $rejected = false;
    private string $status = 'pending_review';
    
    public function run(int $userId): string
    {
        yield $this->activities->runAutomatedChecks($userId);
        
        // Admin signal gözlə (ən çox 7 gün)
        yield Workflow::await(
            CarbonInterval::days(7),
            fn() => $this->approved || $this->rejected
        );
        
        if ($this->rejected) {
            yield $this->activities->notifyRejection($userId);
            return 'rejected';
        }
        
        if (!$this->approved) {
            // Timeout — eskalasiya
            yield $this->activities->escalateToManager($userId);
            return 'escalated';
        }
        
        yield $this->activities->activateAccount($userId);
        return 'approved';
    }
    
    public function approve(string $reviewerId): void
    {
        $this->approved = true;
        $this->status   = 'approved_by_' . $reviewerId;
    }
    
    public function reject(string $reason): void
    {
        $this->rejected = true;
        $this->status   = 'rejected';
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
}

// Admin panel-dən signal göndər
$handle = $client->getHandle('kyc-' . $userId, KycWorkflowInterface::class);
$handle->signal('approve', ['reviewer_id' => auth()->id()]);

// Status yoxla (workflow durdurmadan)
$status = $handle->query('getStatus');
```

## Temporal vs Digər Seçimlər

| | Laravel Jobs | Saga Pattern | Temporal |
|--|-------------|-------------|---------|
| State | Stateless | Manual state | Built-in |
| Retry | Config-based | El ilə | Automatic, per-activity |
| Compensation | El ilə | El ilə | Workflow kodunda |
| Long-running | Çətin | Mümkün | Native |
| Query/Signal | Yox | Yox | Built-in |
| Debugging | Log | Log | Web UI (Temporal UI) |
| PHP Support | Native | Native | SDK var, immat |

## Worker Prosesi

```php
// app/temporal-worker.php
use Temporal\WorkerFactory;

$factory = WorkerFactory::create();

$worker = $factory->newWorker('default');

// Workflow-ları qeydiyyatdan keçir
$worker->registerWorkflowTypes(OrderWorkflow::class, KycWorkflow::class);

// Activity-ları qeydiyyatdan keçir (DI container ilə)
$worker->registerActivity(
    OrderActivities::class,
    fn() => app(OrderActivities::class)  // Laravel IoC
);

$factory->run();
```

## Praktik Baxış

### Trade-off-lar
- **Temporal vs AWS Step Functions**: Temporal vendor-agnostic, self-hosted; Step Functions AWS-ə bağlıdır amma managed.
- **Temporal vs Laravel Horizon**: Horizon sadə job queue üçün yetərli. Temporal uzunmüddətli, state-ful, signal/query tələb edən workflow-lar üçün.
- **PHP SDK immat**: PHP SDK Java/Go/TypeScript-dən geridədir. Production-da test yükü lazımdır.
- **Operational complexity**: Temporal server (Temporal + Cassandra/PostgreSQL) ayrı infrastruktur tələb edir.

### Nə Vaxt İstifadə Etməmək
- Sadə job queue tələbatı (email göndər, resim process et) — Laravel jobs kifayətdir
- <5 addımlı, <1 dəqiqəlik proseslər
- Komanda Temporal-ı idarə edə bilmirsə

### Common Mistakes
- Workflow kodunda DB sorğusu, HTTP sorğusu yazmaq → workflow kodu deterministik olmalıdır, bunlar Activity-ə aparılmalıdır
- Eyni `workflowId` ilə yeni workflow başlatmaq → idempotency key, artıq başlatılmış workflow-a qoşular
- Activity timeout-larını çox qısa tutmaq → üçüncü tərəf API gecikmə olarsa fail

## Praktik Tapşırıqlar

1. `OrderWorkflow`: inventory reserve → payment → email → status update; payment fail olduqda inventory release
2. `KycWorkflow`: automated check → admin signal gözlə (7 gün timeout) → approve/reject/escalate
3. Temporal UI-da workflow history-ə bax, event log-u incələ
4. Retry siyasəti: `PaymentDeclinedException` retry olunmasın, `PaymentTimeoutException` 3 dəfə retry
5. Laravel controller-dan workflow başlat, `workflowId` ilə status query et

## Əlaqəli Mövzular
- [Saga Pattern](119-saga-pattern.md)
- [Choreography vs Orchestration](095-choreography-vs-orchestration.md)
- [Queues & Jobs](057-queues.md)
- [Idempotency Patterns](129-idempotency-patterns.md)
- [Distributed Transactions Alternatives](134-distributed-transactions-alternatives.md)
