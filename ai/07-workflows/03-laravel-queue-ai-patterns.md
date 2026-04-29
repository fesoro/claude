# Laravel Queue + AI: Background AI İşləri Patterns (Senior)

> **Oxucu:** Senior Laravel developerlər, platform mühəndisləri
> **Ön şərtlər:** Laravel Queue, Horizon, Redis, əsas Claude API
> **Tarix:** 2026-04-21
> **Modellər:** `claude-sonnet-4-6`, `claude-opus-4-7`, `claude-haiku-4-5`

---

## Mündəricat

1. Niyə AI Çağırışları Həmişə Queue-da Olmalıdır
2. Horizon Arxitekturası və Supervisor Strukturu
3. Ayrıca Növbələr — Fast-Haiku vs Slow-Opus
4. Retries: Exponential Backoff + Jitter
5. `$tries`, `$backoff`, `retryUntil` Fərqləri
6. Dead-Letter Queue — Zəhərli Mesajlar üçün
7. Idempotency Açarları — Hər İşdə
8. Progress Reporting — Broadcasting ilə
9. Chunked Batch Processing (100k sənəd embedding)
10. `Bus::batch` və `Bus::chain` Multi-step Pipeline
11. Per-Tenant Token Budget
12. Full `GenerateEmbeddingsJob` — Restart-safe
13. Monitoring, Alerting, SLO-lar
14. Production Checklist

---

## 1. Niyə AI Çağırışları Həmişə Queue-da Olmalıdır

HTTP request içində AI çağırışı senior sistemdə yasaqdır. Səbəblər:

| Problem | HTTP-də | Queue-da |
|---------|---------|----------|
| Latency (p95) | 3-30s — timeout riski | 30s+ OK, istifadəçi gözləmir |
| Rate limit | 429 → 500 istifadəçiyə | Backoff + retry şəffaf |
| API outage | Bütün UI düşür | Iş növbədə qalır |
| Cost burst | Hər request tam qiymət | Batch discount, planlama |
| Retry | Manual, çox vaxt yox | Avtomatik |
| Streaming | Client ucuna çatdırmaq çətin | Broadcasting ilə |

### İstisna: Real-Time Chat

Streaming chat-da queue dizaynı fərqlidir — chunk-lar SSE ilə göndərilir. Lakin hətta bu halda "session creation" queue-da olmalıdır.

### Qızıl Qayda

> **Əgər AI çağırışı 2 saniyədən uzundursa və ya tenant limit-ə düşə bilirsə — queue-ya.**

Laravel-da bu qayda praktik olaraq hər Claude çağırışına aiddir.

---

## 2. Horizon Arxitekturası və Supervisor Strukturu

Horizon Laravel queue-larının production monitoringi və supervisor idarəetməsi üçündür. AI iş yükü üçün Horizon qeyri-mümkün deyil — vacibdir.

### Supervisor Topologiyası

```
┌────────────────────────────────────────────────────────────┐
│                   HORIZON MASTER                           │
│                                                            │
│  ┌──────────────────┐  ┌──────────────────┐               │
│  │ ai-fast          │  │ ai-slow          │               │
│  │ (haiku, < 3s)    │  │ (opus, 10-60s)   │               │
│  │ processes: 20    │  │ processes: 4     │               │
│  │ tries: 3         │  │ tries: 2         │               │
│  │ timeout: 30      │  │ timeout: 120     │               │
│  └──────────────────┘  └──────────────────┘               │
│                                                            │
│  ┌──────────────────┐  ┌──────────────────┐               │
│  │ ai-batch         │  │ ai-dlq           │               │
│  │ (embeddings)     │  │ (dead letter)    │               │
│  │ processes: 8     │  │ processes: 1     │               │
│  │ tries: 1         │  │ manual only      │               │
│  │ timeout: 300     │  │                  │               │
│  └──────────────────┘  └──────────────────┘               │
└────────────────────────────────────────────────────────────┘
```

### `config/horizon.php`

```php
'environments' => [
    'production' => [
        'supervisor-ai-fast' => [
            'connection' => 'redis',
            'queue' => ['ai-fast'],
            'balance' => 'auto',
            'minProcesses' => 5,
            'maxProcesses' => 20,
            'tries' => 3,
            'timeout' => 30,
            'memory' => 256,
            'nice' => 0,
        ],

        'supervisor-ai-slow' => [
            'connection' => 'redis',
            'queue' => ['ai-slow'],
            'balance' => 'simple',
            'minProcesses' => 2,
            'maxProcesses' => 4,
            'tries' => 2,
            'timeout' => 120,
            'memory' => 512,
        ],

        'supervisor-ai-batch' => [
            'connection' => 'redis',
            'queue' => ['ai-batch'],
            'balance' => 'simple',
            'minProcesses' => 2,
            'maxProcesses' => 8,
            'tries' => 1,       // Batch-də custom retry
            'timeout' => 300,
            'memory' => 1024,
        ],

        'supervisor-ai-dlq' => [
            'connection' => 'redis',
            'queue' => ['ai-dlq'],
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'tries' => 1,
            'timeout' => 60,
        ],
    ],
],
```

### Prosses sayı — düzgün ölçülmə

```
processes = (target_throughput * avg_job_duration) / target_concurrency

Nümunə:
- Hədəf: 200 haiku çağırışı/dəqiqə
- Orta müddət: 2s
- Paralel işləmək istəyirik
- → processes = (200/60 * 2) = 6.67 ≈ 8
```

Çox prosses → Redis overhead. Az prosses → queue yığılır. Horizon-da `balance: auto` dinamik nizamlayır.

---

## 3. Ayrıca Növbələr — Fast-Haiku vs Slow-Opus

Ən vacib pattern: **AI iş yüklərini gecikmə profilinə görə ayır**.

### Niyə Ayrıca Növbələr

Bir növbədə həm haiku (2s) həm opus (45s) işləsə:
- Opus işi həm müddət, həm memory-ni tutur
- Haiku işləri gözləyir → p95 dramatik artır
- Timeout-ları eyni etmək olmur — 120s haiku üçün çox, opus üçün az

### Növbə Seçimi — Job-da

```php
<?php

namespace App\Jobs\AI;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;

abstract class AbstractAIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    abstract public function model(): string;

    public function __construct()
    {
        $this->onQueue($this->resolveQueue());
    }

    protected function resolveQueue(): string
    {
        return match (true) {
            str_contains($this->model(), 'haiku') => 'ai-fast',
            str_contains($this->model(), 'opus')  => 'ai-slow',
            default => 'ai-slow',
        };
    }
}
```

### Konkret Job

```php
class ClassifyTicketJob extends AbstractAIJob
{
    public int $timeout = 30;

    public function __construct(
        public int $ticketId
    ) {
        parent::__construct();
    }

    public function model(): string
    {
        return 'claude-haiku-4-5';  // Fast classification
    }

    public function handle(\App\AI\ClaudeGateway $claude): void
    {
        $ticket = \App\Models\Ticket::findOrFail($this->ticketId);

        $result = $claude->chat([
            'model' => $this->model(),
            'max_tokens' => 100,
            'messages' => [[
                'role' => 'user',
                'content' => "Təsnif et: {$ticket->subject}",
            ]],
        ]);

        $ticket->update(['category' => $result['category']]);
    }
}
```

### Dərin Analiz üçün Slow-Opus

```php
class GenerateCaseAnalysisJob extends AbstractAIJob
{
    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public int $caseId) { parent::__construct(); }

    public function model(): string { return 'claude-opus-4-7'; }

    public function handle(\App\AI\ClaudeGateway $claude): void { /* ... */ }
}
```

---

## 4. Retries: Exponential Backoff + Jitter

AI API-lər rate-limit və transient failure-lara meyillidir. Sadə retry (10 sandan sonra yenidən cəhd) "retry storm" yaradır — bütün job-lar eyni vaxtda cəhd edir və rate-limit daha da uzadır.

### Exponential Backoff Formulu

```
backoff(attempt) = base * 2^(attempt - 1) + jitter
                 = 4 * 2^(n-1) + random(0, 2)

attempt 1 (first retry): 4-6 saniyə
attempt 2:               8-10 saniyə
attempt 3:               16-18 saniyə
attempt 4:               32-34 saniyə
```

Jitter kritikdir — o olmazsa, bütün retry-lar eyni saniyədə olur.

### Laravel-də `$backoff` Array kimi

```php
class ChatCompletionJob extends AbstractAIJob
{
    public int $tries = 4;

    public function backoff(): array
    {
        return [
            4 + random_int(0, 2000) / 1000,
            8 + random_int(0, 2000) / 1000,
            16 + random_int(0, 2000) / 1000,
        ];
    }

    public function model(): string { return 'claude-sonnet-4-6'; }

    public function handle(\App\AI\ClaudeGateway $claude): void
    {
        // iş
    }
}
```

### Specifik Exception-lar üçün Fərqli Davranış

```php
public function failed(\Throwable $e): void
{
    // Heç retry olunmayacaq vəziyyətlər
    if ($e instanceof \App\AI\Exceptions\InvalidPromptException) {
        \Log::error('Invalid prompt, DLQ-a göndərilmir', [
            'job' => static::class,
            'ticket' => $this->ticketId,
        ]);
        return;
    }

    // DLQ-a göndər
    dispatch(new \App\Jobs\AI\DLQJob(
        jobClass: static::class,
        payload: ['ticket_id' => $this->ticketId],
        error: $e->getMessage(),
    ))->onQueue('ai-dlq');
}
```

### Rate Limit üçün xüsusi cəhət

```php
public function handle(\App\AI\ClaudeGateway $claude): void
{
    try {
        $claude->chat([...]);
    } catch (\App\AI\Exceptions\RateLimitException $e) {
        // Anthropic retry-after header göndərir
        $this->release($e->retryAfter ?? 30);
        return;
    }
}
```

`release()` job-u retry sayını artırmadan eyni delay ilə yenidən növbəyə qoyur.

---

## 5. `$tries`, `$backoff`, `retryUntil` Fərqləri

Laravel üç fərqli retry strategiyası təklif edir — hər biri fərqli problemləri həll edir.

### `$tries`

Maksimal cəhd sayı. Çatdıqda `failed()` metodu çağırılır.

```php
public int $tries = 3;
```

### `$backoff`

Retry-lər arasındakı saniyələr.

```php
public int $backoff = 10;  // Sabit
// və ya
public function backoff(): array
{
    return [5, 15, 45];  // Hər retry üçün fərqli
}
```

### `retryUntil` — Tam Limit

Bəzi AI iş yüklərində "nə qədər tez olsa çatsın, sonra atılsın" qaydası lazımdır. `retryUntil` mütləq vaxt hədləyir:

```php
use Illuminate\Support\Carbon;

public function retryUntil(): \DateTime
{
    return now()->addMinutes(15);
}
```

Bu, `$tries`-dən üstündür. Məsələn summary job 15 dəqiqədən çox gecikdirsə istifadəçi üçün məzmunsuzdur.

### Praktik Kombinasiya

```php
class GenerateSummaryJob extends AbstractAIJob
{
    public int $tries = 5;
    public function backoff(): array { return [2, 5, 15, 45]; }
    public function retryUntil(): \DateTime { return now()->addMinutes(10); }
    public function model(): string { return 'claude-sonnet-4-6'; }
}
```

Bu dizayn deyir: "5 cəhd et, exponential backoff ilə, amma ümumi 10 dəqiqədən çox gecikdirmə."

---

## 6. Dead-Letter Queue — Zəhərli Mesajlar üçün

"Poison message" — heç vaxt uğurla emal olunmayacaq iş. Normal queue-da qalsa sonsuz retry edir və resursları yeyir. Həll: DLQ.

### DLQ Job Strukturu

```php
namespace App\Jobs\AI;

class DLQJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Bus\Queueable, \Illuminate\Queue\InteractsWithQueue,
        \Illuminate\Queue\SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $jobClass,
        public array $payload,
        public string $error,
    ) {
        $this->onQueue('ai-dlq');
    }

    public function handle(): void
    {
        \App\Models\DeadLetter::create([
            'job_class' => $this->jobClass,
            'payload' => $this->payload,
            'error' => $this->error,
            'received_at' => now(),
            'status' => 'pending_review',
        ]);

        \App\Events\DeadLetterReceived::dispatch($this->jobClass);
    }
}
```

### DLQ-dan Bərpa

Admin dashboard-da manual restart:

```php
Route::post('/admin/dlq/{id}/retry', function (int $id) {
    $dl = DeadLetter::findOrFail($id);
    $jobClass = $dl->job_class;
    dispatch(new $jobClass(...$dl->payload));
    $dl->update(['status' => 'retried', 'retried_at' => now()]);
    return back();
});
```

### DLQ Alarmları

DLQ-ya 10+ mesaj düşsə kritik alert:

```php
// app/Console/Commands/CheckDLQ.php
protected function handle(): int
{
    $pending = DeadLetter::where('status', 'pending_review')
        ->where('received_at', '>', now()->subHour())
        ->count();

    if ($pending >= 10) {
        \App\Notifications\DLQAlert::dispatch($pending);
    }

    return 0;
}
```

---

## 7. Idempotency Açarları — Hər İşdə

Queue-da retry qaçınılmazdır. Hər job idempotent olmalıdır — yəni eyni giriş ilə N dəfə icra olunsa nəticə dəyişməməlidir.

### Açar Stratejiyası

```
idempotency_key = hash(tenant_id + entity_id + action + version)

Nümunə:
hash("tenant:42|ticket:1001|classify|v2") = "a4f7..."
```

### İmplementasiya

```php
class ClassifyTicketJob extends AbstractAIJob
{
    public function __construct(
        public int $ticketId,
        public string $version = 'v2',
    ) {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        return "classify:{$this->ticketId}:{$this->version}";
    }

    public function handle(\App\AI\ClaudeGateway $claude): void
    {
        $key = "job_result:" . $this->uniqueId();

        if (\Cache::has($key)) {
            \Log::info('Job skipped — idempotency cache hit', ['key' => $key]);
            return;
        }

        // iş
        $result = $claude->chat([...]);

        Ticket::find($this->ticketId)->update(['category' => $result['category']]);

        \Cache::put($key, true, 86400);  // 24 saat
    }
}
```

### Laravel-in `ShouldBeUnique` Interface-i

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ClassifyTicketJob extends AbstractAIJob implements ShouldBeUnique
{
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return "classify:{$this->ticketId}";
    }
}
```

Fərq: `ShouldBeUnique` **dispatch zamanı** dublikatı bloklayır. Cache-based idempotency **icra zamanı**. Hər ikisi birlikdə istifadə etmək olar.

---

## 8. Progress Reporting — Broadcasting ilə

Uzun AI işlərində (məsələn 100 sənəd summarize) istifadəçi gözləmir. Progress real-time göstərilməlidir.

### Broadcast Event

```php
namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AIJobProgress implements ShouldBroadcast
{
    public function __construct(
        public string $jobId,
        public int $userId,
        public int $processed,
        public int $total,
        public string $message,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'ai.progress';
    }
}
```

### Job-da İstifadə

```php
class BulkSummarizeJob extends AbstractAIJob
{
    public int $timeout = 600;

    public function __construct(
        public string $jobId,
        public int $userId,
        public array $documentIds,
    ) { parent::__construct(); }

    public function model(): string { return 'claude-sonnet-4-6'; }

    public function handle(\App\AI\ClaudeGateway $claude): void
    {
        $total = count($this->documentIds);

        foreach ($this->documentIds as $i => $docId) {
            $doc = \App\Models\Document::find($docId);
            $summary = $claude->chat([
                'model' => $this->model(),
                'max_tokens' => 300,
                'messages' => [[
                    'role' => 'user',
                    'content' => "Xülasələ:\n\n" . $doc->content,
                ]],
            ]);

            $doc->update(['summary' => $summary['text']]);

            \App\Events\AIJobProgress::dispatch(
                jobId: $this->jobId,
                userId: $this->userId,
                processed: $i + 1,
                total: $total,
                message: "Sənəd #{$doc->id} emal olundu",
            );
        }
    }
}
```

### Frontend (Vue/Livewire)

```javascript
Echo.private(`user.${userId}`)
    .listen('.ai.progress', (e) => {
        this.progress = (e.processed / e.total) * 100;
        this.currentMessage = e.message;
    });
```

---

## 9. Chunked Batch Processing (100k sənəd embedding)

100k sənəd embedding etmək istəyirsiniz. Bir job-da olmaz:
- Timeout
- Memory
- Tek fail bütün işi öldürür

Həll: **chunk + batch**.

### Arxitektura

```
┌────────────────────────────────────────────────────────┐
│  DocumentCorpus (100k sənəd)                           │
└──────────────────────────┬─────────────────────────────┘
                           │ chunk(500)
         ┌─────────────────┼─────────────────┐
         │                 │                 │
    ┌────▼────┐      ┌────▼────┐       ┌────▼────┐
    │ Chunk 1 │      │ Chunk 2 │  ...  │ Chunk N │
    │ 500 doc │      │ 500 doc │       │ 500 doc │
    └────┬────┘      └────┬────┘       └────┬────┘
         │                │                 │
    ┌────▼────┐      ┌────▼────┐       ┌────▼────┐
    │Embedder │      │Embedder │       │Embedder │
    │  Job    │      │  Job    │       │  Job    │
    └─────────┘      └─────────┘       └─────────┘
         │                │                 │
         └────────────────┴─────────────────┘
                    Bus::batch()
```

### Chunk Yaradıcı

```php
namespace App\Services;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class EmbeddingOrchestrator
{
    public function dispatch(int $corpusId): string
    {
        $chunks = \App\Models\Document::where('corpus_id', $corpusId)
            ->where('embedding_status', 'pending')
            ->select('id')
            ->chunkById(500, function ($docs) use (&$jobs) {
                $jobs[] = new \App\Jobs\AI\EmbedChunkJob(
                    documentIds: $docs->pluck('id')->toArray()
                );
            });

        $batch = Bus::batch($jobs)
            ->name("corpus_embed_{$corpusId}")
            ->onQueue('ai-batch')
            ->allowFailures()
            ->then(function (Batch $batch) use ($corpusId) {
                \App\Models\Corpus::find($corpusId)->update(['status' => 'ready']);
            })
            ->catch(function (Batch $batch, \Throwable $e) {
                \Log::error('Batch failed', ['batch' => $batch->id]);
            })
            ->finally(function (Batch $batch) use ($corpusId) {
                \App\Notifications\CorpusEmbedded::dispatch($corpusId);
            })
            ->dispatch();

        return $batch->id;
    }
}
```

### `EmbedChunkJob`

```php
namespace App\Jobs\AI;

use Illuminate\Bus\Batchable;

class EmbedChunkJob extends AbstractAIJob
{
    use Batchable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public array $documentIds) { parent::__construct(); }

    public function model(): string { return 'claude-haiku-4-5'; }

    public function handle(\App\AI\EmbeddingService $embedder): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $docs = \App\Models\Document::whereIn('id', $this->documentIds)
            ->where('embedding_status', 'pending')
            ->get();

        if ($docs->isEmpty()) return;

        // Anthropic batch embedding API (hypothetical) və ya parallel calls
        $embeddings = $embedder->embedBatch(
            $docs->pluck('content')->toArray()
        );

        \DB::transaction(function () use ($docs, $embeddings) {
            foreach ($docs as $i => $doc) {
                $doc->update([
                    'embedding' => $embeddings[$i],
                    'embedding_status' => 'done',
                    'embedded_at' => now(),
                ]);
            }
        });
    }
}
```

### Batch Progress Tracking

```php
Route::get('/batch/{id}/status', function (string $id) {
    $batch = Bus::findBatch($id);
    return [
        'total' => $batch->totalJobs,
        'processed' => $batch->processedJobs(),
        'failed' => $batch->failedJobs,
        'progress' => $batch->progress(),
        'cancelled' => $batch->cancelled(),
    ];
});
```

---

## 10. `Bus::batch` və `Bus::chain` Multi-step Pipeline

### `Bus::chain` — Sıralı Addımlar

Bir işin çıxışı digərinə daxil olur:

```php
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new \App\Jobs\AI\ExtractTextJob($documentId),
    new \App\Jobs\AI\ClassifyTextJob($documentId),
    new \App\Jobs\AI\GenerateTagsJob($documentId),
    new \App\Jobs\AI\IndexDocumentJob($documentId),
])->onQueue('ai-slow')->dispatch();
```

Bir iş uğursuz olarsa, zəncir dayanır. Bu, mürəkkəb AI pipeline üçün mükəmməldir.

### `Bus::batch` — Paralel

Addımlar müstəqildirsə paralel işləyir:

```php
Bus::batch([
    new ExtractEntitiesJob($docId),
    new GenerateSummaryJob($docId),
    new ClassifyCategoryJob($docId),
])->then(fn() => IndexDocumentJob::dispatch($docId))
  ->dispatch();
```

### Mixed Pattern

```php
Bus::chain([
    // Addım 1: parallel analiz
    fn() => Bus::batch([
        new ExtractEntitiesJob($docId),
        new ClassifyCategoryJob($docId),
    ])->dispatch(),

    // Addım 2: nəticələri birləşdir
    new AggregateAnalysisJob($docId),

    // Addım 3: index
    new IndexDocumentJob($docId),
])->dispatch();
```

---

## 11. Per-Tenant Token Budget

SaaS-da hər tenant-ın AI xərc limiti olmalıdır. Bu limit **job başladılmadan əvvəl** yoxlanmalıdır.

### Budget Service

```php
namespace App\AI;

use Illuminate\Support\Facades\Redis;

class TokenBudget
{
    public function __construct(
        private int $tenantId,
    ) {}

    public function hasCapacity(int $expectedTokens): bool
    {
        $used = $this->currentUsage();
        $limit = $this->limit();
        return ($used + $expectedTokens) <= $limit;
    }

    public function record(int $tokens, float $costCents): void
    {
        $key = "ai:usage:{$this->tenantId}:" . now()->format('Y-m');
        Redis::hincrby($key, 'tokens', $tokens);
        Redis::hincrbyfloat($key, 'cost_cents', $costCents);
        Redis::expire($key, 86400 * 35);
    }

    public function currentUsage(): int
    {
        $key = "ai:usage:{$this->tenantId}:" . now()->format('Y-m');
        return (int) (Redis::hget($key, 'tokens') ?? 0);
    }

    public function limit(): int
    {
        return cache()->remember(
            "tenant:{$this->tenantId}:ai_limit",
            3600,
            fn() => \App\Models\Tenant::find($this->tenantId)->ai_monthly_token_limit
        );
    }
}
```

### Job-da İstifadə

```php
class ExpensiveAIJob extends AbstractAIJob
{
    public function __construct(
        public int $tenantId,
        public string $prompt,
    ) { parent::__construct(); }

    public function model(): string { return 'claude-opus-4-7'; }

    public function handle(\App\AI\ClaudeGateway $claude, TokenBudget $budget): void
    {
        $budget = new TokenBudget($this->tenantId);
        $estimated = (int) (strlen($this->prompt) / 4) + 1000;  // input + max_tokens

        if (!$budget->hasCapacity($estimated)) {
            $this->fail(new \App\AI\Exceptions\BudgetExhausted(
                "Tenant {$this->tenantId} aylıq limiti doldu"
            ));
            return;
        }

        $result = $claude->chat([
            'model' => $this->model(),
            'max_tokens' => 1000,
            'messages' => [['role' => 'user', 'content' => $this->prompt]],
        ]);

        $budget->record(
            tokens: $result['usage']['input_tokens'] + $result['usage']['output_tokens'],
            costCents: $this->calculateCost($result['usage']),
        );
    }

    private function calculateCost(array $usage): float
    {
        // Nümunə qiymətləndirmə (faktiki rəqəmlər fərqli olacaq)
        $inputCostPer1k = 15.0;   // cents
        $outputCostPer1k = 75.0;  // cents
        return ($usage['input_tokens'] / 1000) * $inputCostPer1k
             + ($usage['output_tokens'] / 1000) * $outputCostPer1k;
    }
}
```

---

## 12. Full `GenerateEmbeddingsJob` — Restart-safe

Restart-safe — proses ortasında öldürülsə, yenidən başladıldıqda qaldığı yerdən davam edir.

```php
<?php

namespace App\Jobs\AI;

use App\AI\EmbeddingService;
use App\AI\TokenBudget;
use App\Models\Document;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingsJob extends AbstractAIJob
{
    use Batchable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public int $corpusId,
        public int $tenantId,
        public array $documentIds,
        public string $runId,           // restart bütövlüyü üçün
    ) {
        parent::__construct();
        $this->onQueue('ai-batch');
    }

    public function model(): string { return 'claude-haiku-4-5'; }

    public function backoff(): array { return [10, 30, 90]; }

    public function uniqueId(): string
    {
        return "embed:{$this->runId}:" . md5(implode(',', $this->documentIds));
    }

    public function handle(EmbeddingService $embedder): void
    {
        if ($this->batch()?->cancelled()) {
            Log::info('Batch cancelled, skipping', ['run' => $this->runId]);
            return;
        }

        $budget = new TokenBudget($this->tenantId);

        // YALNIZ hələ emal olunmamış sənədləri götür (restart üçün)
        $pending = Document::whereIn('id', $this->documentIds)
            ->where('corpus_id', $this->corpusId)
            ->whereIn('embedding_status', ['pending', 'failed'])
            ->get();

        if ($pending->isEmpty()) {
            Log::info('Bütün sənədlər artıq emal olunub', [
                'run' => $this->runId,
                'ids' => $this->documentIds,
            ]);
            return;
        }

        Log::info('Embedding başladı', [
            'run' => $this->runId,
            'pending' => $pending->count(),
            'attempt' => $this->attempts(),
        ]);

        // Təxmini token sayı
        $totalChars = $pending->sum(fn($d) => strlen($d->content));
        $estimatedTokens = (int) ($totalChars / 4);

        if (!$budget->hasCapacity($estimatedTokens)) {
            $this->fail(new \App\AI\Exceptions\BudgetExhausted(
                "Budget aşıldı, tenant: {$this->tenantId}"
            ));
            return;
        }

        // Sənədləri `processing` vəziyyətinə keçir — concurrency qorunması
        $lockedIds = DB::transaction(function () use ($pending) {
            return Document::whereIn('id', $pending->pluck('id'))
                ->where('embedding_status', '!=', 'processing')
                ->lockForUpdate()
                ->get()
                ->each(fn($d) => $d->update([
                    'embedding_status' => 'processing',
                    'embedding_run_id' => $this->runId,
                    'embedding_started_at' => now(),
                ]))
                ->pluck('id');
        });

        $toEmbed = $pending->whereIn('id', $lockedIds);

        if ($toEmbed->isEmpty()) {
            Log::info('Başqa worker bu sənədləri götürüb', ['run' => $this->runId]);
            return;
        }

        $contents = $toEmbed->pluck('content')->values()->toArray();

        try {
            $embeddings = $embedder->embedBatch($contents, $this->model());
        } catch (\App\AI\Exceptions\RateLimitException $e) {
            // Sənədləri `pending` vəziyyətinə qaytar
            Document::whereIn('id', $toEmbed->pluck('id'))
                ->update(['embedding_status' => 'pending']);
            $this->release($e->retryAfter ?? 30);
            return;
        } catch (\Throwable $e) {
            Document::whereIn('id', $toEmbed->pluck('id'))
                ->update(['embedding_status' => 'failed']);
            throw $e;
        }

        // Sənədləri yenilə
        DB::transaction(function () use ($toEmbed, $embeddings, $budget) {
            foreach ($toEmbed as $i => $doc) {
                $doc->update([
                    'embedding' => $embeddings[$i]['vector'],
                    'embedding_model' => $this->model(),
                    'embedding_tokens' => $embeddings[$i]['tokens'],
                    'embedding_status' => 'done',
                    'embedded_at' => now(),
                ]);

                $budget->record(
                    tokens: $embeddings[$i]['tokens'],
                    costCents: $embeddings[$i]['cost_cents'] ?? 0.0,
                );
            }
        });

        Log::info('Embedding uğurla tamamlandı', [
            'run' => $this->runId,
            'count' => $toEmbed->count(),
        ]);

        event(new \App\Events\AIJobProgress(
            jobId: $this->runId,
            userId: \App\Models\Tenant::find($this->tenantId)->owner_id,
            processed: $toEmbed->count(),
            total: count($this->documentIds),
            message: "{$toEmbed->count()} sənəd embed edildi",
        ));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateEmbeddingsJob fail', [
            'run' => $this->runId,
            'error' => $e->getMessage(),
        ]);

        // Sənədləri təmiz vəziyyətə qaytar
        Document::whereIn('id', $this->documentIds)
            ->where('embedding_run_id', $this->runId)
            ->where('embedding_status', 'processing')
            ->update(['embedding_status' => 'failed']);

        // DLQ
        dispatch(new DLQJob(
            jobClass: static::class,
            payload: [
                'corpus' => $this->corpusId,
                'tenant' => $this->tenantId,
                'docs' => $this->documentIds,
                'run' => $this->runId,
            ],
            error: $e->getMessage(),
        ));
    }
}
```

### Restart-Safety Xüsusiyyətləri

1. **`embedding_status` state machine**: `pending → processing → done` (və ya `failed`).
2. **`embedding_run_id`**: hansı job hansı sənədi tutub.
3. **`lockForUpdate()`**: iki worker eyni sənədi götürməsin.
4. **`failed()`-də cleanup**: `processing`-də qalan sənədləri `failed`-ə keçir.
5. **`handle()` başlanğıcında yalnız `pending/failed` seç**: artıq `done` olanları buraxma.

---

## 13. Monitoring, Alerting, SLO-lar

### Qızıl Siqnallar

```
AI Queue SLO-lar:
├── ai-fast queue depth < 500 (p95)
├── ai-fast job duration < 5s (p99)
├── ai-slow job duration < 120s (p99)
├── failure rate < 2% (rolling 1h)
├── DLQ growth rate < 1/hour
├── Token budget exhaustion alerts per tenant
└── Rate limit event frequency
```

### Horizon Metrics → Prometheus

```php
// app/Console/Commands/ExportQueueMetrics.php
protected function handle(): int
{
    $metrics = \Laravel\Horizon\Contracts\MetricsRepository::class;
    $repo = app($metrics);

    $queues = ['ai-fast', 'ai-slow', 'ai-batch', 'ai-dlq'];

    foreach ($queues as $queue) {
        \Prometheus\Gauge::set(
            'laravel_queue_pending_jobs',
            \Queue::size($queue),
            ['queue' => $queue]
        );

        $throughput = $repo->throughputForQueue($queue);
        \Prometheus\Gauge::set(
            'laravel_queue_throughput_per_minute',
            $throughput,
            ['queue' => $queue]
        );
    }

    return 0;
}
```

### PagerDuty Alert Şablonu

```yaml
- alert: AIQueueBacklog
  expr: laravel_queue_pending_jobs{queue="ai-fast"} > 500
  for: 5m
  labels: { severity: warning }
  annotations:
    summary: "AI fast queue backlog aşıldı"

- alert: AIDLQGrowing
  expr: rate(ai_dlq_messages_total[1h]) > 10
  for: 10m
  labels: { severity: critical }
  annotations:
    summary: "DLQ-ya 10+ mesaj/saat düşür — araşdır"

- alert: TokenBudgetExhausted
  expr: increase(ai_tenant_budget_exhausted_total[5m]) > 0
  labels: { severity: info }
```

---

## 14. Production Checklist

```
QUEUE KONFIQURASİYASI
─────────────────────
[x] Horizon supervisor-ları model klasslarına ayrılıb
[x] Haiku və Opus üçün ayrıca queue (ai-fast, ai-slow)
[x] Batch iş üçün ai-batch queue
[x] DLQ queue (ai-dlq) var
[x] Per-queue timeout və tries set edilib

RETRY STRATEGIYASI
─────────────────────
[x] Exponential backoff + jitter
[x] retryUntil mütləq hədd üçün
[x] RateLimitException üçün release() ilə xüsusi handling
[x] Qeyri-retry-able exception-lar (InvalidPrompt) identifikasiya olunub

IDEMPOTENCY
─────────────────────
[x] Hər job-un uniqueId() metodu var
[x] ShouldBeUnique dispatch-də dublikatı bloklayır
[x] Cache-based idempotency icrada yoxlanır
[x] DB-level unique constraint son müdafiə xətti

RESTART-SAFETY
─────────────────────
[x] State machine (pending → processing → done)
[x] run_id ilə row-level tracking
[x] lockForUpdate concurrency üçün
[x] failed() cleanup edir

BUDGET & COST
─────────────────────
[x] Per-tenant monthly token limit
[x] Pre-check hər job əvvəlində
[x] Usage Redis-də track olunur
[x] Budget aşıldıqda DLQ və ya graceful fail

OBSERVABILITY
─────────────────────
[x] Queue depth metrics
[x] Job duration histogram
[x] Failure rate per job type
[x] DLQ growth rate alert
[x] Broadcasting progress user-ə

DEAD-LETTER QUEUE
─────────────────────
[x] DLQJob var
[x] Admin UI-də DLQ siyahısı və retry
[x] DLQ threshold alert (10+ mesaj/saat)
[x] Manual review flow
```

---

## Yekun

Laravel queue + AI vahid sistem kimi dizayn edilməlidir. Əsas prinsiplər:

1. **Heç bir AI çağırışı HTTP request daxilində olmasın** — hər şey queue-da.
2. **Model sürətinə görə ayrıca queue** — haiku fast, opus slow, batch batch.
3. **Exponential backoff + jitter** retry storm-un qarşısını alır.
4. **Idempotency** retry-ları təhlükəsiz edir.
5. **Restart-safe state machine** uzun işləri məhv olmaqdan qoruyur.
6. **DLQ** zəhərli mesajları izolyasiya edir.
7. **Per-tenant budget** xərc hücumlarının qarşısını alır.
8. **Progress broadcasting** istifadəçi təcrübəsini saxlayır.

`GenerateEmbeddingsJob` göstərdi ki, bütün bu prinsiplər bir job-da necə birləşir. Bu, sadəcə pattern yox, production-qrafiya-prelimineri Laravel + AI arxitekturasıdır.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: AI Job with Rate Limiting

`SummarizeDocumentJob` implement et. `ThrottlesExceptions` + `RateLimited` middleware əlavə et: Anthropic API üçün dəqiqədə 60 request limiti. Job uğursuz olduqda 3 cəhd, exponential backoff. `failed_jobs`-da uğursuz job-ların monitoring-i qur.

### Tapşırıq 2: Batch Embedding Job

1000 sənəd üçün `GenerateEmbeddingsJob`-u `Bus::batch()` ilə çalışdır. Progress tracking: `batch->processedJobs()` / `batch->totalJobs()` SSE ilə frontend-ə axtar. Batch tamamlandıqda webhook göndər. Batch-ın yarıda uğursuz olduqda `catch()` callback-ı test et.

### Tapşırıq 3: Per-Tenant Budget

`ai_usage` cədvəlindən hər tenant üçün aylıq token xərclərini izlə. Tenant-ın `monthly_budget_usd` həddinə çatdıqda `TenantBudgetExceededException` at, job-u queue-dan çıxar. Admin-ə email göndər. Budget reset (ayın 1-i) üçün scheduled command yaz.

---

## Əlaqəli Mövzular

- `01-ai-pipeline-laravel.md` — Queue job-lardan ibarət pipeline qurmaq
- `04-ai-idempotency-circuit-breaker.md` — Job idempotency və circuit breaker
- `../02-claude-api/11-rate-limits-retry-php.md` — API rate limit idarəetməsi
- `../08-production/04-cost-optimization.md` — Budget enforcement strategiyası
