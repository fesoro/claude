# Background Job Orchestration (Lead)

## Problem Statement

Bir e-commerce platformunda sifariş verildir. Ardınca:
1. Ödəniş yoxlanılır
2. İnventar rezerv edilir
3. Warehouse-a bildiriş göndərilir (2 paralel warehouse)
4. Hər warehouse-dan paketləmə confirmation gəlir (fan-in)
5. Shipping label yaradılır
6. User-ə bildiriş göndərilir

Bu job-lar bir-birindən asılıdır, bəziləri paralel işləyə bilər. Uğursuz olarsa nə olur? Timeout varsa? Bu mürəkkəb orchestration-u necə qururuq?

---

## 1. Job Chaining — Sadə Asılılıq

*Bu kod `withChain()` ilə ardıcıl job zənciri quran və uğursuzluqda compensation başladan statik dispatch metodunu göstərir:*

```php
// app/Jobs/ProcessOrder.php
// Sadə ardıcıl zəncir: A → B → C

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrderChain
{
    public static function dispatch(Order $order): void
    {
        // then() ilə zəncir qur
        ValidatePaymentJob::withChain([
            new ReserveInventoryJob($order),
            new NotifyWarehouseJob($order),
            new CreateShippingLabelJob($order),
            new NotifyUserJob($order),
        ])
        ->dispatch($order)
        ->catch(function (Throwable $e) use ($order) {
            // Zəncirlənmiş job-lardan biri uğursuz olarsa
            Log::error("Order processing failed", [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            $order->update(['status' => 'processing_failed']);
            dispatch(new CompensateOrderJob($order));
        });
    }
}
```

*Bu kod `WithoutOverlapping` və `ThrottlesExceptions` middleware ilə qorunan ödəniş doğrulama job-unu göstərir:*

```php
// app/Jobs/ValidatePaymentJob.php
<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

class ValidatePaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries    = 3;
    public int $timeout  = 30;       // 30 saniyə
    public int $backoff  = 10;       // Retry-lar arasında 10s gözlə

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $payment = app(PaymentService::class)->validate($this->order->payment_id);

        if (!$payment->isValid()) {
            // Retry-u dayandır, exception throw et
            $this->fail(new \RuntimeException("Payment validation failed: {$payment->getReason()}"));
            return;
        }

        $this->order->update(['payment_status' => 'validated']);
    }

    public function middleware(): array
    {
        return [
            // Eyni order üçün paralel execution önlə
            new WithoutOverlapping("order_{$this->order->id}"),
            // Exception-da throttle: 5 dəq içində 3 exception → 10 dəq gözlə
            (new ThrottlesExceptions(3, 5))->backoff(10),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ValidatePaymentJob failed permanently", [
            'order_id' => $this->order->id,
            'error'    => $exception->getMessage(),
        ]);
        // Dead letter — manual review
        DeadLetterJob::dispatch($this->order, 'validate_payment', $exception->getMessage());
    }
}
```

---

## 2. Bus::batch() — Paralel İşlər + Fan-Out/Fan-In

*Bu kod `Bus::batch()` ilə çoxlu warehouse-a paralel bildiriş göndərən, fan-in callback ilə növbəti adımı başladan job-u göstərir:*

```php
// app/Jobs/NotifyWarehousesJob.php
// Fan-out: 2 warehouse-a paralel bildiriş
// Fan-in: ikisi tamamlananda növbəti step

<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class NotifyWarehousesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $warehouses = Warehouse::where('region', $this->order->delivery_region)
            ->take(2)
            ->get();

        $batch = Bus::batch(
            $warehouses->map(fn($wh) => new NotifyWarehouseJob($this->order, $wh))->all()
        )
        ->name("order_{$this->order->id}_warehouse_notifications")
        ->allowFailures() // Bir warehouse uğursuz olsa da davam et
        ->progress(function (Batch $batch) {
            // Hər job tamamlandıqda çağırılır
            Log::info("Warehouse notification progress: {$batch->processedJobs()}/{$batch->totalJobs}");
        })
        ->then(function (Batch $batch) {
            // Fan-in: hamısı tamamlandı
            $order = Order::find($this->order->id);
            if ($batch->failedJobs > 0 && $batch->processedJobs() === 0) {
                // Heç biri uğurlu olmadı
                $order->update(['warehouse_status' => 'notification_failed']);
                dispatch(new CompensateOrderJob($order));
            } else {
                // Ən az biri uğurlu oldu — davam et
                dispatch(new CreateShippingLabelJob($order));
            }
        })
        ->catch(function (Batch $batch, \Throwable $e) {
            Log::error("Warehouse batch failed completely", ['error' => $e->getMessage()]);
        })
        ->finally(function (Batch $batch) {
            Log::info("Warehouse batch finished", ['batch_id' => $batch->id]);
        })
        ->dispatch();

        // Batch ID-ni order-a yaz (monitoring üçün)
        $this->order->update(['warehouse_batch_id' => $batch->id]);
    }
}
```

*Bu kod tək anbar üçün idempotency key ilə bildiriş göndərən individual job-u göstərir:*

```php
// app/Jobs/NotifyWarehouseJob.php
<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Warehouse;

class NotifyWarehouseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 15;

    public function __construct(
        public Order     $order,
        public Warehouse $warehouse
    ) {}

    public function handle(): void
    {
        app(WarehouseNotificationService::class)->notify(
            $this->warehouse,
            $this->order,
            idempotencyKey: "order_{$this->order->id}_warehouse_{$this->warehouse->id}"
        );
    }
}
```

---

## 3. Custom WorkflowEngine — DAG-based Orchestration

*Bu kod DAG-based workflow üçün abstract step sinifini asılılıqlar, timeout, retry və compensation metodları ilə göstərir:*

```php
// app/Workflow/WorkflowStep.php
<?php

namespace App\Workflow;

abstract class WorkflowStep
{
    abstract public function getName(): string;

    /** Bu step hansı step-lərdən sonra icra ediləcək */
    abstract public function getDependencies(): array;

    abstract public function execute(WorkflowContext $context): void;

    /** Timeout (saniyə), null = yoxdur */
    public function getTimeout(): ?int
    {
        return 60;
    }

    public function getRetries(): int
    {
        return 3;
    }

    /** Uğursuzluqda compensate et */
    public function compensate(WorkflowContext $context): void
    {
        // Default: heç nə etmə
    }
}
```

*Bu kod workflow addımları arasında məlumat ötürən və tamamlanmış step-ləri izləyən context obyektini göstərir:*

```php
// app/Workflow/WorkflowContext.php
<?php

namespace App\Workflow;

class WorkflowContext
{
    private array $data     = [];
    private array $results  = [];

    public function __construct(
        public readonly string $workflowId,
        public readonly string $entityId,
        array $initialData = []
    ) {
        $this->data = $initialData;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function markStepCompleted(string $stepName, mixed $result = null): void
    {
        $this->results[$stepName] = [
            'completed_at' => now()->toIso8601String(),
            'result'       => $result,
        ];
    }

    public function isStepCompleted(string $stepName): bool
    {
        return isset($this->results[$stepName]);
    }

    public function toArray(): array
    {
        return ['data' => $this->data, 'results' => $this->results];
    }
}
```

*Bu kod Kahn's algoritmi ilə topological sort edən, resume dəstəyi olan və uğursuzluqda compensation işlədən workflow engine-i göstərir:*

```php
// app/Workflow/WorkflowEngine.php
<?php

namespace App\Workflow;

use App\Models\WorkflowRun;
use Illuminate\Support\Facades\Log;

class WorkflowEngine
{
    /** @var WorkflowStep[] */
    private array $steps = [];

    public function addStep(WorkflowStep $step): self
    {
        $this->steps[$step->getName()] = $step;
        return $this;
    }

    public function run(WorkflowContext $context): void
    {
        // DAG-ı topological sort et
        $sortedSteps = $this->topologicalSort();

        $completedSteps = [];

        foreach ($sortedSteps as $stepName) {
            $step = $this->steps[$stepName];

            // Artıq tamamlanmışsa skip (resume support)
            if ($context->isStepCompleted($stepName)) {
                Log::info("Workflow step already done, skipping: {$stepName}");
                $completedSteps[] = $step;
                continue;
            }

            // Dependencies tamamlanıbmı?
            foreach ($step->getDependencies() as $dep) {
                if (!$context->isStepCompleted($dep)) {
                    throw new \RuntimeException("Dependency not met: {$dep} for step {$stepName}");
                }
            }

            try {
                Log::info("Executing workflow step: {$stepName}");
                $step->execute($context);
                $context->markStepCompleted($stepName);
                $completedSteps[] = $step;
                $this->persistContext($context);
            } catch (\Throwable $e) {
                Log::error("Workflow step failed: {$stepName}", ['error' => $e->getMessage()]);
                $this->compensate(array_reverse($completedSteps), $context);
                throw $e;
            }
        }
    }

    /**
     * Kahn's Algorithm ilə topological sort
     */
    private function topologicalSort(): array
    {
        $inDegree  = array_fill_keys(array_keys($this->steps), 0);
        $adjList   = array_fill_keys(array_keys($this->steps), []);

        foreach ($this->steps as $stepName => $step) {
            foreach ($step->getDependencies() as $dep) {
                $adjList[$dep][]  = $stepName;
                $inDegree[$stepName]++;
            }
        }

        $queue  = [];
        foreach ($inDegree as $step => $degree) {
            if ($degree === 0) {
                $queue[] = $step;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($adjList[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($this->steps)) {
            throw new \RuntimeException("Workflow has circular dependencies!");
        }

        return $sorted;
    }

    private function compensate(array $completedSteps, WorkflowContext $context): void
    {
        foreach ($completedSteps as $step) {
            try {
                $step->compensate($context);
            } catch (\Throwable $e) {
                Log::critical("Compensation failed for step {$step->getName()}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function persistContext(WorkflowContext $context): void
    {
        \Cache::put("workflow:{$context->workflowId}:context", $context->toArray(), now()->addHours(24));
    }
}
```

---

## 4. Workflow Definition — Order Processing

*4. Workflow Definition — Order Processing üçün kod nümunəsi:*
```php
// app/Workflow/Steps/ValidatePaymentStep.php
<?php

namespace App\Workflow\Steps;

use App\Workflow\WorkflowContext;
use App\Workflow\WorkflowStep;

class ValidatePaymentStep extends WorkflowStep
{
    public function getName(): string { return 'validate_payment'; }
    public function getDependencies(): array { return []; } // İlk step

    public function execute(WorkflowContext $context): void
    {
        $order   = Order::findOrFail($context->entityId);
        $payment = app(PaymentService::class)->validate($order->payment_id);

        $context->set('payment_validated', true);
        $context->set('payment_amount', $payment->amount);
    }

    public function compensate(WorkflowContext $context): void
    {
        // Payment void et
        $order = Order::findOrFail($context->entityId);
        app(PaymentService::class)->void($order->payment_id);
    }
}
```

*app(PaymentService::class)->void($order->payment_id); üçün kod nümunəsi:*
```php
// app/Workflow/Steps/ReserveInventoryStep.php
<?php

namespace App\Workflow\Steps;

use App\Workflow\WorkflowContext;
use App\Workflow\WorkflowStep;

class ReserveInventoryStep extends WorkflowStep
{
    public function getName(): string { return 'reserve_inventory'; }
    public function getDependencies(): array { return ['validate_payment']; }
    public function getTimeout(): ?int { return 20; }

    public function execute(WorkflowContext $context): void
    {
        $order         = Order::with('items')->findOrFail($context->entityId);
        $reservationId = app(InventoryService::class)->reserve(
            $order->items,
            idempotencyKey: "workflow_{$context->workflowId}_inventory"
        );

        $context->set('inventory_reservation_id', $reservationId);
    }

    public function compensate(WorkflowContext $context): void
    {
        $reservationId = $context->get('inventory_reservation_id');
        if ($reservationId) {
            app(InventoryService::class)->release($reservationId);
        }
    }
}
```

*app(InventoryService::class)->release($reservationId); üçün kod nümunəsi:*
```php
// app/Workflow/Steps/NotifyWarehousesStep.php
// Paralel warehouse notification üçün batch dispatch edir
<?php

namespace App\Workflow\Steps;

use App\Workflow\WorkflowContext;
use App\Workflow\WorkflowStep;
use Illuminate\Support\Facades\Bus;

class NotifyWarehousesStep extends WorkflowStep
{
    public function getName(): string { return 'notify_warehouses'; }
    public function getDependencies(): array { return ['reserve_inventory']; }
    public function getTimeout(): ?int { return 120; } // Warehouse response gözlə

    public function execute(WorkflowContext $context): void
    {
        $order      = Order::findOrFail($context->entityId);
        $warehouses = Warehouse::availableFor($order)->get();

        $batch = Bus::batch(
            $warehouses->map(fn($wh) => new \App\Jobs\NotifyWarehouseJob($order, $wh))->all()
        )
        ->allowFailures()
        ->then(function ($batch) use ($context) {
            // Fan-in — hamısı tamamlandı, workflow davam edir
            \Cache::put(
                "workflow:{$context->workflowId}:warehouse_done",
                true,
                now()->addHours(1)
            );
        })
        ->dispatch();

        $context->set('warehouse_batch_id', $batch->id);

        // Batch tamamlanana qədər gözlə (Octane task ya da polling)
        $this->waitForBatch($batch->id, timeout: $this->getTimeout());
    }

    private function waitForBatch(string $batchId, int $timeout): void
    {
        $start = time();
        while (time() - $start < $timeout) {
            $batch = Bus::findBatch($batchId);
            if ($batch?->finished()) {
                return;
            }
            sleep(2);
        }
        throw new \RuntimeException("Warehouse notification timed out after {$timeout}s");
    }
}
```

---

## 5. Workflow-u İşlədən Job

*5. Workflow-u İşlədən Job üçün kod nümunəsi:*
```php
// app/Jobs/RunOrderWorkflowJob.php
<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\WorkflowRun;
use App\Workflow\WorkflowContext;
use App\Workflow\WorkflowEngine;
use App\Workflow\Steps\{ValidatePaymentStep, ReserveInventoryStep, NotifyWarehousesStep, CreateShippingLabelStep, NotifyUserStep};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RunOrderWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1; // Workflow özü retry edir
    public int $timeout = 300; // 5 dəqiqə ümumi timeout

    public function __construct(
        public Order $order,
        public ?string $workflowId = null
    ) {
        $this->workflowId = $workflowId ?? Str::uuid()->toString();
    }

    public function handle(): void
    {
        // Mövcud context-i yüklə (resume support)
        $savedContext = \Cache::get("workflow:{$this->workflowId}:context");

        $context = new WorkflowContext(
            workflowId: $this->workflowId,
            entityId: (string) $this->order->id,
            initialData: $savedContext['data'] ?? []
        );

        // Completed steps-i context-ə yüklə
        if ($savedContext) {
            foreach ($savedContext['results'] as $step => $result) {
                $context->markStepCompleted($step, $result['result']);
            }
        }

        $engine = app(WorkflowEngine::class);
        $engine
            ->addStep(new ValidatePaymentStep())
            ->addStep(new ReserveInventoryStep())
            ->addStep(new NotifyWarehousesStep())
            ->addStep(new CreateShippingLabelStep())
            ->addStep(new NotifyUserStep());

        $workflowRun = WorkflowRun::updateOrCreate(
            ['workflow_id' => $this->workflowId],
            ['order_id' => $this->order->id, 'status' => 'running', 'started_at' => now()]
        );

        try {
            $engine->run($context);
            $workflowRun->update(['status' => 'completed', 'completed_at' => now()]);
            $this->order->update(['status' => 'processed']);
        } catch (\Throwable $e) {
            $workflowRun->update(['status' => 'failed', 'error' => $e->getMessage()]);
            $this->order->update(['status' => 'processing_failed']);

            // Alert göndər
            dispatch(new AlertDevTeamJob(
                "Workflow failed for order {$this->order->id}: {$e->getMessage()}"
            ));
        }
    }
}
```

---

## 6. Dead Letter Handling

*6. Dead Letter Handling üçün kod nümunəsi:*
```php
// app/Jobs/DeadLetterJob.php
<?php

namespace App\Jobs;

use App\Models\DeadLetterEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeadLetterJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public string $queue = 'dead-letter';
    public int    $tries = 1;

    public function __construct(
        public mixed  $entity,
        public string $jobName,
        public string $errorMessage,
        public array  $context = []
    ) {}

    public function handle(): void
    {
        DeadLetterEntry::create([
            'job_name'      => $this->jobName,
            'entity_type'   => get_class($this->entity),
            'entity_id'     => $this->entity->id ?? null,
            'error_message' => $this->errorMessage,
            'context'       => $this->context,
            'status'        => 'pending_review',
        ]);

        // Slack/PagerDuty-ə göndər
        app(AlertService::class)->critical(
            "Dead letter entry created for job: {$this->jobName}",
            ['entity_id' => $this->entity->id ?? null, 'error' => $this->errorMessage]
        );
    }
}
```

*['entity_id' => $this->entity->id ?? null, 'error' => $this->errorMess üçün kod nümunəsi:*
```php
// Dead letter entry-ləri yenidən işlət
// app/Console/Commands/ReplayDeadLetterCommand.php
<?php

namespace App\Console\Commands;

use App\Models\DeadLetterEntry;
use Illuminate\Console\Command;

class ReplayDeadLetterCommand extends Command
{
    protected $signature   = 'dead-letter:replay {id} {--dry-run}';
    protected $description = 'Dead letter entry-ni yenidən işlət';

    public function handle(): void
    {
        $entry = DeadLetterEntry::findOrFail($this->argument('id'));

        if ($this->option('dry-run')) {
            $this->info("Would replay: {$entry->job_name} for {$entry->entity_type}:{$entry->entity_id}");
            return;
        }

        $jobClass = $this->resolveJobClass($entry->job_name);
        $entity   = $entry->entity_type::find($entry->entity_id);

        dispatch(new $jobClass($entity));

        $entry->update(['status' => 'replayed', 'replayed_at' => now()]);
        $this->info("Successfully replayed dead letter entry #{$entry->id}");
    }
}
```

---

## 7. Progress Reporting — WebSocket/Polling

*7. Progress Reporting — WebSocket/Polling üçün kod nümunəsi:*
```php
// app/Jobs/Steps ilə progress update

// app/Events/WorkflowProgressUpdated.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class WorkflowProgressUpdated implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public string $workflowId,
        public string $step,
        public int    $progress, // 0-100
        public string $message
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("workflow.{$this->workflowId}")];
    }

    public function broadcastAs(): string
    {
        return 'progress.updated';
    }
}
```

*return 'progress.updated'; üçün kod nümunəsi:*
```php
// WorkflowStep base class-ına progress broadcasting əlavə et
abstract class WorkflowStep
{
    protected function reportProgress(WorkflowContext $context, int $progress, string $message): void
    {
        event(new WorkflowProgressUpdated(
            $context->workflowId,
            $this->getName(),
            $progress,
            $message
        ));

        // DB-ə də yaz (polling üçün)
        WorkflowRun::where('workflow_id', $context->workflowId)
            ->update([
                'current_step' => $this->getName(),
                'progress'     => $progress,
                'progress_message' => $message,
            ]);
    }
}
```

*'progress_message' => $message, üçün kod nümunəsi:*
```php
// Polling endpoint (WebSocket yoxdursa)
// app/Http/Controllers/WorkflowController.php

public function progress(string $workflowId)
{
    $run = WorkflowRun::where('workflow_id', $workflowId)->firstOrFail();

    return response()->json([
        'status'   => $run->status,
        'step'     => $run->current_step,
        'progress' => $run->progress,
        'message'  => $run->progress_message,
    ]);
}
```

---

## 8. Retry Per Step + Timeout

*8. Retry Per Step + Timeout üçün kod nümunəsi:*
```php
// app/Workflow/WorkflowStepRunner.php
<?php

namespace App\Workflow;

class WorkflowStepRunner
{
    public function runWithRetry(WorkflowStep $step, WorkflowContext $context): void
    {
        $maxRetries  = $step->getRetries();
        $timeout     = $step->getTimeout();
        $attempt     = 0;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            try {
                if ($timeout) {
                    $this->executeWithTimeout($step, $context, $timeout);
                } else {
                    $step->execute($context);
                }
                return; // Uğurlu
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt <= $maxRetries) {
                    // Exponential backoff
                    $delay = min(pow(2, $attempt), 30);
                    Log::warning("Step failed, retrying in {$delay}s: {$step->getName()}", [
                        'attempt' => $attempt,
                        'error'   => $e->getMessage(),
                    ]);
                    sleep($delay);
                }
            }
        }

        throw new WorkflowStepFailedException(
            "Step {$step->getName()} failed after {$maxRetries} retries: {$lastException->getMessage()}",
            previous: $lastException
        );
    }

    private function executeWithTimeout(WorkflowStep $step, WorkflowContext $context, int $timeout): void
    {
        // PHP-də real async timeout üçün pcntl_alarm (CLI-da) ya da Swoole coroutine lazımdır
        // Queue-da işlədilən job-lar üçün $this->timeout istifadə edilir
        // Burada sadə yanaşma:
        $signal = pcntl_alarm($timeout);
        pcntl_signal(SIGALRM, function () use ($timeout, $step) {
            throw new \RuntimeException("Step {$step->getName()} timed out after {$timeout}s");
        });

        try {
            $step->execute($context);
        } finally {
            pcntl_alarm(0); // Timer-i sıfırla
        }
    }
}
```

---

## 9. Temporal.io Konsepti (Qısa)

Temporal.io — Workflow orchestration üçün dedicated platform. Laravel queue-dan fərqi:

| | Laravel Queue | Temporal.io |
|---|---|---|
| Workflow state | Redis/DB-ə manual yaz | Automatically persisted |
| Long-running | Timeout riski var | Years boyunca çalışa bilər |
| Versioning | Mürəkkəb | Built-in workflow versioning |
| Visibility | Manual logging | Built-in workflow history UI |
| Language | PHP | Go, Java, Python, TypeScript |

PHP üçün alternative: **Conductor** (Netflix), **Durable Functions** (Azure)

---

## Migration

*Migration üçün kod nümunəsi:*
```php
Schema::create('workflow_runs', function (Blueprint $table) {
    $table->id();
    $table->string('workflow_id')->unique();
    $table->morphs('entity');  // entity_id + entity_type
    $table->string('status')->default('pending');
    $table->string('current_step')->nullable();
    $table->integer('progress')->default(0);
    $table->string('progress_message')->nullable();
    $table->text('error')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index('workflow_id');
    $table->index(['entity_type', 'entity_id']);
});

Schema::create('dead_letter_entries', function (Blueprint $table) {
    $table->id();
    $table->string('job_name');
    $table->string('entity_type')->nullable();
    $table->unsignedBigInteger('entity_id')->nullable();
    $table->text('error_message');
    $table->json('context')->nullable();
    $table->string('status')->default('pending_review');
    $table->timestamp('replayed_at')->nullable();
    $table->timestamps();
});
```

---

## İntervyu Sualları

**S: Job chaining vs Job batching fərqi nədir?**
C: Chaining ardıcıl — A tamamlananda B başlayır. Batching paralel — A, B, C eyni anda işləyir, hamısı tamamlananda `then()` callback çağırılır. Batch `allowFailures()` ilə partial failure-ı handle edə bilər.

**S: Fan-out/Fan-in pattern nədir?**
C: Fan-out — bir işi çox paralel işçiyə paylama (1 → N). Fan-in — paralel işlər tamamlandıqda nəticələri bir yerə toplamaq (N → 1). Bus::batch() bu pattern-i realize edir.

**S: DAG-based workflow niyə topological sort tələb edir?**
C: DAG (Directed Acyclic Graph) asılılıqları olan step-lər qrafıdır. Topological sort hər step-in öz dependency-lərindən sonra icra edilməsini təmin edir. Dairəvi asılılıq varsa (A → B → A), sort mümkün deyil və xəta atılır.

**S: Workflow-u resume etmək necə işləyir?**
C: Hər step tamamlandıqda state-i persistent store-a (Redis/DB) yazırıq. Job yenidən başlalanda, artıq tamamlanmış step-ləri skip edirik. Bu idempotency + checkpoint pattern-dir.

**S: Dead letter queue nədir?**
C: Bütün retry-ları tükənmiş, uğursuz job-ların saxlandığı ayrıca queue. Bunlar avtomatik işlənmir — developer müdaxiləsi tələb olunur. Manual review sonrası replay edilə bilər.

**S: Long-running workflow-ların timeout problemi necə həll olunur?**
C: Job timeout-unu artırmaq (queue worker config), ya da workflow-u kiçik step-lərə bölmək (hər step ayrı job). Hər step qısa yaşayır, state-i DB-ə yazır, növbəti step ayrı job kimi dispatch olunur.

**S: `WithoutOverlapping` middleware nə edir, nə vaxt lazımdır?**
C: Eyni job-un eyni parametrlərlə paralel işləməsinin qarşısını alır. Məsələn, eyni `order_id` üçün `ValidatePaymentJob` iki dəfə dispatch olunarsa biri lock ala bilmir, release timeout-a qədər gözləyir (ya da skip edilir). Nə vaxt lazımdır: idempotent olmayan, resursa exclusive daxil olan job-lar — payment processing, inventory reservation.

**S: `ThrottlesExceptions` middleware nə edir?**
C: Bir job müəyyən müddətdə müəyyən sayda exception atarsa, növbəti cəhdləri avtomatik olaraq delay edir. Məsələn: 5 dəqiqə içində 3 exception → 10 dəqiqə gözlə. Xarici API-nin keçici çökməsindən qorunmaq üçün idealdir. Circuit breaker pattern-in sadəlşdirilmiş formasıdır.

**S: Saga pattern ilə 2PC (Two-Phase Commit) fərqi nədir?**
C: 2PC bütün katılımcıları eyni anda lock edir — distributed deadlock riski, latency artımı, availablility problemi. Microservice-lər üçün praktikada mümkün deyil. Saga: hər addım müstəqil yerli tranzaksiyadır, uğursuzluqda compensating action-lar çağırılır. 2PC: ACID strong consistency. Saga: eventual consistency. Maliyyə sistemlərinin çoxu Saga istifadə edir — "rezervlə, sonra tamamla" (reserve-then-confirm) pattern.

**S: Job priority necə idarə edilir?**
C: Laravel-də hər job fərqli queue-ya göndərilə bilər. Worker `--queue=critical,high,default,low` parametri ilə priority sıralamasına görə işləyir. Kritik: ödəniş job-ları `critical` queue-da, email bildirişləri `low` queue-da. Alternativ: Redis Sorted Set ilə priority score saxla, worker-lar yüksək score-luları əvvəlcə götürsün.

---

## Anti-patterns

**1. Bütün workflow-u bir job-da icra etmək**
Saatlıq proses bir job-da — timeout, memory exhaustion, retry bütündən başlayır. Hər step ayrı job olmalı, state checkpoint-lərlə saxlanmalıdır.

**2. Job idempotentliyini unutmaq**
Queue at-least-once delivery edir. Eyni job iki dəfə işləyə bilər. Hər addımda "artıq tamamlanıb?" yoxlaması lazımdır.

**3. Failure-da compensation olmamaq**
Step 3 fail olur, step 1 və 2 artıq tamamlanıb. Rollback/compensation məntiqi yazılmayıbsa inconsistent state qalır. Saga pattern — hər step-in compensating action-ı olmalıdır.

**4. Workflow state-ini yalnız memory-də saxlamaq**
Worker restart olduqda bütün state itirilir. State mütləq Redis/DB-ə persist edilməlidir.

**5. Çox böyük batch dispatch**
Milyonluq job-ları dərhal dispatch etmək — queue dolur, digər iş növləri gözləyir. Rate-limited dispatch (chunk + delay) ilə yavaş-yavaş göndər.
