# Background Job Patterns (Senior ⭐⭐⭐)

## İcmal

Background job-lar — HTTP request cycle-ından kənar, asinxron şəkildə icra olunan işlərdir. E-mail göndərmək, hesabat generasiya etmək, ödəniş emal etmək — bunların hamısı birbaşa request-ə cavab vermədən arxa planda baş verməlidir.

Senior developer olaraq bilməlisiniz: nə vaxt job istifadə edəcəksiniz, necə etibarlı (reliable) edəcəksiniz, failure-lari necə idarə edəcəksiniz, və scale etməyiniz lazım gəldikdə hansı qərarları verəcəksiniz.

---

## Niyə Vacibdir

- HTTP request timeout-ları var (30s, 60s) — uzun işlər request-də görünə bilməz
- İstifadəçi cavabı gözləməməlidir — ilk cavab ver, iş arxa planda bitsin
- Sistem yükünü düzgün idarə etmək: peak saatlarda gözləyən işlər sonra işlənir
- Failure isolation — bir job çöksə, digər job-ları və main request-i etkiləmir
- Retry mexanizmi — müvəqqəti xətalar (network, external API) avtomatik aradan qalxır

---

## Əsas Anlayışlar

### 1. Ne Zaman Background Job İstifadə Et

**Mütləq:**
- İşin müddəti 2 saniyədən çoxdur
- External API çağırışı (email, SMS, push notification)
- File processing (PDF yaratmaq, şəkil resize etmək)
- Database-də bulk əməliyyat
- Third-party service (ödəniş gateway, CRM sync)

**İstəyə görə:**
- İstifadəçi real-time nəticəyə ehtiyac duymursa
- Sistem yükü saatlara görə dalğalanırsa (rate smoothing)
- Əməliyyat retry tələb edirsə

**İstifadə etmə:**
- İstifadəçi anında nəticə gözləyirsə (məs: şifrə yoxlama)
- Əməliyyat atomik olmalıdırsa (job + main transaction)
- Sadə hesablama 100ms-dən azdırsa

### 2. Queue Arxitekturaları

```
Dedicated Queues — Mövzuya görə ayırma:
┌─────────────┐     ┌──────────────────┐
│   emails    │────▶│  email-workers   │
│   sms       │────▶│  sms-workers     │
│   reports   │────▶│  report-workers  │
│   payments  │────▶│  payment-workers │
└─────────────┘     └──────────────────┘

Priority Queue — Vacib işlər əvvəl:
HIGH   [payment_confirmed, otp_send]  ← əvvəl işlənir
MEDIUM [welcome_email, report_gen]
LOW    [newsletter, analytics_sync]

Delayed Queue — Zamanlanmış icra:
[+5min: remind_user] [+1h: follow_up] [+24h: survey_send]
```

**FIFO vs LIFO:**
- **FIFO** (First In, First Out) — əksər hallarda düzgün seçim; ədalətli, gözlənilən sıra
- **LIFO** (Last In, First Out) — nadir istifadə; cache warmup kimi yeni data-ya prioritet verirsən

### 3. Worker Patterns

```
Single Worker — sadə, kiçik yük:
Queue ──▶ [Worker] ──▶ Process

Worker Pool — paralel emal:
        ┌──▶ [Worker 1] ──▶ Process
Queue ──┼──▶ [Worker 2] ──▶ Process
        └──▶ [Worker 3] ──▶ Process

Horizontal Scaling — server-ləri artır:
Server A: [Worker 1] [Worker 2]
Server B: [Worker 3] [Worker 4]
Server C: [Worker 5] [Worker 6]
```

**Worker sayını necə müəyyən et:**
- I/O-bound jobs (email, HTTP) → CPU core sayından çox worker ola bilər (2-4x)
- CPU-bound jobs (video encode, PDF) → core sayına bərabər worker
- Monitoring: queue depth artırsa → worker əlavə et

### 4. Retry Strategiyaları

#### Immediate Retry — Müvəqqəti şəbəkə xətası üçün
```
Attempt 1 → Fail → Attempt 2 (dərhal) → Fail → Attempt 3 (dərhal) → Dead Letter
```

#### Exponential Backoff — Standard yanaşma
```
Attempt 1 → Fail
Wait: 2^1 = 2s  → Attempt 2 → Fail
Wait: 2^2 = 4s  → Attempt 3 → Fail
Wait: 2^3 = 8s  → Attempt 4 → Fail
Wait: 2^4 = 16s → Attempt 5 → Fail → Dead Letter Queue
```

**Jitter əlavə et** (thundering herd probleminin qarşısını almaq üçün):
```
wait = (2^attempt) + random(0, 1000ms)
```

**Retry nə vaxt etmə:**
- 4xx xəta (400, 404, 422) — client data xətalıdır, retry nəticə verməz
- Validation xətaları
- Business rule pozulması

**Retry et:**
- 5xx xətalar (502, 503, 504) — server/network müvəqqəti problem
- Connection timeout
- Rate limit (429) — `Retry-After` header-ə bax

### 5. Dead Letter Queue (DLQ)

Max retry-dan sonra job buraya gedir:

```
Normal Flow:
Queue → Worker → Success ✓

Failure Flow:
Queue → Worker → Fail → Retry (3x) → DLQ

DLQ monitoring:
- Alert: DLQ-ya yeni job düşdükdə bildiriş
- Dashboard: DLQ dərinliyini izlə
- Manual review: hər DLQ job-unu analiz et
- Re-process: xəta düzəldikdən sonra DLQ-nu yenidən işlə
```

**DLQ-suz nə baş verir:** Job əbədi loop-da döndü ya da susqun şəkildə itdi — ikisi də pisdir.

### 6. Idempotency in Jobs

**Problem:** Network xətasından sonra job iki dəfə işlənə bilər. Ödənişi iki dəfə göndərmək fəlakətdir.

**Həll:** Hər job öz ID-si ilə idempotent olmalıdır.

```
Job A (id: abc-123) → Process → İşarələ (abc-123 tamamlandı)
Job A (id: abc-123) → Yenidən gəldi → Artıq tamamlandı → Skip

Deduplication yolları:
1. Job ID-ni database-də saxla (processed_jobs cədvəli)
2. Redis-də SET NX ilə işarələ: SET job:abc-123 1 EX 86400
3. İstifadəçi ID + əməliyyat tipi + tarix üzrə unique constraint
```

### 7. Job Status Tracking

```
Statuslar:
PENDING   → Növbəyə alındı, gözləyir
RUNNING   → Bir worker tərəfindən götürüldü
COMPLETED → Uğurla tamamlandı
FAILED    → Xəta, retry olacaq
DEAD      → Max retry keçdi, DLQ-da

İstifadəçiyə görünürlük:
POST /reports/generate
→ 202 Accepted
→ { "job_id": "job_xyz", "status_url": "/jobs/job_xyz" }

GET /jobs/job_xyz
→ { "status": "running", "progress": 45, "estimated_completion": "..." }

GET /jobs/job_xyz
→ { "status": "completed", "result_url": "/reports/rpt_abc" }
```

### 8. Long-running Job Patterns

#### Progress Tracking
```
Job başlayanda: progress = 0%
Job işlədikdə: progress = işlənmiş / ümumi * 100
Job bitəndə: progress = 100%, result_url qaytarılır

Client polling: GET /jobs/{id} hər 2-5 saniyədə
Alternativ: WebSocket/SSE ilə real-time progress göndər
```

#### Cancellation
```
User "ləğv et" basır → POST /jobs/{id}/cancel
Worker hər iterasiyada yoxlayır: "bu job ləğv edilibmi?"
Ləğv edilibsə: iş dayandır, resursları azad et, status = CANCELLED
```

#### Chunking Large Jobs
```
Problematik: 1M sətiri bir job-da emal et → timeout, memory exhaustion

Həll: Chunk-lara böl
  Job 1: sətir 1-10.000
  Job 2: sətir 10.001-20.000
  ...
  Job 100: sətir 990.001-1.000.000

Faydaları:
  - Hər chunk ayrıca retry oluna bilər
  - Paralel emal mümkündür
  - Memory problemi yoxdur
  - Progress tracking asandır
```

### 9. Scheduled/Cron Jobs

```
Problem: Çoxlu server varsa, cron hər serverdə işləyir → duplikat

Həll 1: Distributed Lock
  - Redis SETNX ilə lock al
  - Lock var → başqa server çalışdırıb, skip et
  - Lock yoxdur → al, işlə, bitdikdə azad et

Həll 2: Single Scheduler Node
  - Yalnız bir server cron-u idarə edir (database flag ilə seçilir)
  - Failover mexanizmi lazımdır

Həll 3: Dedicated Scheduler Service
  - Ayrı bir servis job-ları queue-ya göndərir
  - Worker-lar yalnız queue-dan oxuyur

Missed Job Handling:
  - Servis down oldu, cron vaxtı keçdi — nə etmək lazımdır?
  - "At least once" → sonra başlayanda işlə
  - "Skip if missed" → 5 dəqiqəlik report-u sonra işləmə mənası yoxdur
  - "Backfill" → hər keçmiş slot üçün ayrıca job yarat
```

### 10. Priority Queue — Starvation Prevention

```
Problem: HIGH priority-da daim yeni job gəlir → LOW priority job-lar heç işlənmir

Həll: Aging — job-un priority-si gözlədikdə artır
  Job LOW priority ilə gəldi
  10 dəqiqə gözlədisə → MEDIUM-ə yüksəl
  30 dəqiqə gözlədisə → HIGH-a yüksəl

Alternativ: İşçilərin bir hissəsini (məs: 20%) aşağı priority-yə ayır
  - 4 worker-dan 3-ü HIGH/MEDIUM
  - 1 worker həmişə LOW işləyir
```

### 11. Fan-out Pattern

```
Bir job → çox uşaq job yaradır

Nümunə: Bülten göndərmək
  Parent Job: send_newsletter(campaign_id)
    ↓
    1M subscriber-i 1000-lik qruplara böl
    ↓
  Child Job 1: send_batch(user_ids: [1..1000])
  Child Job 2: send_batch(user_ids: [1001..2000])
  ...
  Child Job 1000: send_batch(user_ids: [999001..1000000])

Koordinasiya:
  - Parent job-un tamamlanması üçün bütün child-ların bitməsini gözlə
  - Counter ilə izlə: tamamlanan child / ümumi child
  - Callback: hamısı bitəndə "campaign completed" bildirişi göndər
```

### 12. Saga Orchestration via Jobs

```
Distributed transaction — hər addım ayrı job:
  Job 1: reserve_inventory(order_id)  → uğurluysa →
  Job 2: charge_payment(order_id)     → uğurluysa →
  Job 3: send_confirmation(order_id)  → uğurluysa →
  Job 4: update_analytics(order_id)

Hər addım uğursuz olsa → compensating job:
  Job 2 uğursuz → Job 2a: release_inventory(order_id)
  Job 3 uğursuz → Job 3a: refund_payment(order_id)
                   Job 3b: release_inventory(order_id)
```

### 13. Failure Modes

| Problem | Səbəb | Həll |
|---------|-------|------|
| Job loss | Worker restart, queue persist yoxdur | Persistent queue (Redis AOF, RabbitMQ durable) |
| Duplicate processing | Worker restart after ack, at-least-once delivery | Idempotent job design |
| Poison message | Job həmişə crash edir, sonsuz retry | Max retry + DLQ |
| Queue backup | Producer → consumer-dan sürətli | Worker sayını artır, rate limiting əlavə et |
| Stale job | Saatlarca gözləyən job artıq relevant deyil | Job expiry/TTL |

### 14. Monitoring

**Vacib metrikalar:**
- **Queue depth** — gözləyən job sayı (artırsa → worker əlavə et)
- **Processing time (p50, p95, p99)** — job-un orta və pik müddəti
- **Failure rate** — hər queue üzrə xəta faizi
- **DLQ depth** — diqqət tələb edən problem sayı
- **Throughput** — saniyədə işlənən job sayı
- **Worker utilization** — worker-ların nə qədər məşğul olduğu

**Alert şərtləri:**
- Queue depth > threshold (məs: 1000 job > 5 dəqiqə)
- DLQ-ya yeni job düşdükdə
- Failure rate > 5% son 5 dəqiqədə
- Worker sayı 0-a düşdükdə

### 15. Texnologiya Müqayisəsi

| Texnologiya | Güclü Cəhəti | Zəif Cəhəti | İstifadə Et |
|------------|-------------|------------|------------|
| Redis Queue (Horizon) | Sürətli, sadə, Laravel-ə daxil | Persistent risk, RAM limiti | 99% PHP proyekti üçün |
| RabbitMQ | Routing, exchange, reliable | Mürəkkəb, əlavə servis | Mürəkkəb routing lazımdırsa |
| Amazon SQS | Managed, FIFO dəstəyi, ucuz | Latency (ms), AWS vendor lock | Cloud-native, low-maintenance |
| Kafka | Yüksək throughput, log compaction, replay | Overhead, mürəkkəb | Event stream, 100k+ msg/s |
| Database queue | 0 əlavə servis | Scale problemi, polling | Çox az yük, sadəlik lazımdırsa |

---

## Praktik Baxış

### Trade-off-lar

- **Reliability vs Complexity** — "at-least-once" delivery + idempotency = etibarlı amma mürəkkəb. "At-most-once" = sadə amma itkilər ola bilər.
- **Throughput vs Ordering** — sıralama lazımdırsa (FIFO), paralel işləmək çətinləşir.
- **Visibility vs Privacy** — job statusunu client-a açmaq debugging-i asanlaşdırır, amma həssas data sızmaya bilər.

### Ən Çox Edilən Səhvlər

- Job-u idempotent etməmək (ödənişi iki dəfə göndərmək)
- Retry-ı 4xx xətalara da tətbiq etmək (məntiqsiz)
- DLQ-nu ignore etmək — "baxaram" deyib heç baxmamaq
- Job-da çox böyük payload saxlamaq (job ID saxla, data-nı DB-dən yüklə)
- Scheduled job-larda distributed lock unutmaq

---

## Nümunələr

### Tipik Interview Sualı

> "İstifadəçi 'hesabat yarat' düyməsini basır. Hesabat 2-3 dəqiqə çəkir. Necə dizayn edərdiniz?"

---

### Güclü Cavab

"Bu klassik async pattern-dir. İstifadəçi düyməni basdıqda:

1. Endpoint dərhal `202 Accepted` qaytarır, `job_id` verir
2. Job queue-ya yazılır (Redis Horizon və ya SQS)
3. Worker götürür, hesabatı yaradır, fayl storage-a yazır
4. Job tamamlandıqda status `COMPLETED` olur, `result_url` saxlanır
5. Client polling edir: `GET /jobs/{id}` hər 3 saniyədə

Güvenilirlik üçün: job idempotent olmalıdır (məs: hesabat ID-yə görə unique). Xəta halında exponential backoff ilə 3-5 dəfə retry. DLQ-ya düşsə alert.

Scale üçün: çoxlu worker, report queue ayrıca, CPU-intensive task-lar üçün ayrı worker pool."

---

### Arxitektura Diaqramı

```
User Request
     │
     ▼
┌─────────────┐
│   API       │ ──── 202 Accepted {job_id}
│   Server    │
└──────┬──────┘
       │ enqueue
       ▼
┌─────────────┐
│  Job Queue  │ (Redis/SQS/RabbitMQ)
│  [job_xyz]  │
└──────┬──────┘
       │ dequeue
       ▼
┌─────────────┐
│   Worker    │
│   Process   │ ──── 3x retry (exp. backoff)
└──────┬──────┘              │
       │ success          failure
       ▼                     ▼
┌─────────────┐      ┌──────────────┐
│   Storage   │      │     DLQ      │ ──── Alert
│  (S3, DB)   │      │  (manual fix)│
└─────────────┘      └──────────────┘
       │
       ▼
GET /jobs/job_xyz → {status: "completed", result_url: "..."}
```

---

### Kod Nümunəsi

#### Retry + DLQ Məntiqi (PHP/Laravel)

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    // Maksimum retry sayı
    public int $tries = 5;

    // Retry-lar arasında gözləmə (saniyə) — exponential backoff
    public int $backoff = 60;

    // Job timeout (saniyə)
    public int $timeout = 300;

    public function __construct(
        private readonly string $reportId,
        private readonly int $userId,
    ) {}

    public function middleware(): array
    {
        return [
            // 10 saniyədə 3 xətadan çox olsa 5 dəqiqə gözlə
            new ThrottlesExceptions(3, 5),
        ];
    }

    public function handle(ReportService $service): void
    {
        // Idempotency check — artıq tamamlanıbsa skip et
        if (Report::where('id', $this->reportId)->where('status', 'completed')->exists()) {
            return;
        }

        Report::find($this->reportId)->update(['status' => 'running']);

        $result = $service->generate($this->reportId);

        Report::find($this->reportId)->update([
            'status'     => 'completed',
            'result_url' => $result->url,
            'completed_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        // Max retry keçdi — DLQ-ya düşdü
        Report::find($this->reportId)->update(['status' => 'failed']);

        // Alert göndər
        Notification::route('slack', config('alerts.channel'))
            ->notify(new JobFailedNotification($this->reportId, $e->getMessage()));
    }

    // Retry attempt-ə görə gözləmə müddəti
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600]; // 30s, 1min, 2min, 5min, 10min
    }
}
```

#### Idempotent Job Implementation

```php
<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Redis;

class SendPaymentConfirmationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $paymentId,
        private readonly string $idempotencyKey, // UUID — job yaradılarkən təyin edilir
    ) {}

    public function handle(PaymentNotifier $notifier): void
    {
        $lockKey = "job:payment_confirm:{$this->idempotencyKey}";

        // Redis-də atomic SET NX — yalnız bir worker icra edə bilər
        $acquired = Redis::set($lockKey, '1', 'EX', 86400, 'NX');

        if (! $acquired) {
            // Artıq başqa worker bu job-u işləyib
            return;
        }

        $notifier->sendConfirmation($this->paymentId);
    }
}
```

#### Progress Tracking Job

```php
<?php

namespace App\Jobs;

class BulkImportJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    public function __construct(
        private readonly string $importId,
        private readonly array $rows,
    ) {}

    public function handle(ImportService $service): void
    {
        $total = count($this->rows);
        $processed = 0;

        Import::find($this->importId)->update([
            'status'   => 'running',
            'total'    => $total,
            'progress' => 0,
        ]);

        // Chunk-larla emal — memory efficient
        foreach (array_chunk($this->rows, 500) as $chunk) {
            // İptal sorğusu yoxlanır
            if (Import::find($this->importId)->status === 'cancelled') {
                return;
            }

            $service->processChunk($chunk);

            $processed += count($chunk);

            // Progress yenilə
            Import::find($this->importId)->update([
                'progress' => round($processed / $total * 100, 1),
            ]);
        }

        Import::find($this->importId)->update([
            'status'       => 'completed',
            'progress'     => 100,
            'completed_at' => now(),
        ]);
    }
}
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1 — Queue Dizaynı

E-commerce sistemi üçün queue arxitekturasını dizayn et:
- Ödəniş bildirişləri (kritik, dərhal)
- Bülten göndərmək (1M istifadəçi)
- Anbarda sayım yeniləmə (yüksək tezlik)
- Aylıq hesabat (ağır, az tezlik)

Neçə queue? Neçə worker? Priority necə?

### Tapşırıq 2 — Idempotency

Aşağıdakı job-u idempotent et: `SendEmailJob(userId, templateId)`.
- Email iki dəfə getməsin
- Redis vs Database — hansını seçərsən, niyə?
- TTL nə qədər olmalıdır?

### Tapşırıq 3 — Cron Locking

Laravel-də scheduled command yazacaqsın: hər gecə 02:00-da bütün istifadəçilərin aylıq statistikasını hesabla. Çoxlu server var. Duplikat işləməsin.
- Redis lock necə implement edilir?
- Lock timeout nə qədər olmalıdır?
- Server lock aldı, sonra crash etdi — deadlock olmaması üçün nə etməli?

### Tapşırıq 4 — DLQ Monitoring

Mövcud proyektinizin DLQ-sunu audit et:
- Hal-hazırda DLQ-da nə qədər job var?
- Xətaların növü nədir?
- Alert mexanizmi varmı?

### Tapşırıq 5 — Fan-out Sistemi

"Bütün premium istifadəçilərə yeni funksiya haqqında push notification göndər" ssenarisini dizayn et:
- 500.000 premium istifadəçi var
- Hər notification 50ms çəkir
- Hamısı 10 dəqiqə ərzində çatmalıdır

Worker sayını, chunk böyüklüyünü, monitoring strategiyasını müəyyən et.

---

## Əlaqəli Mövzular

- [08-api-first-design.md](08-api-first-design.md) — 202 Accepted pattern
- [11-saga-pattern.md](11-saga-pattern.md) — Distributed transaction via jobs
- [09-backend-for-frontend.md](09-backend-for-frontend.md) — Async API design
- [06-cqrs-architecture.md](06-cqrs-architecture.md) — Command handler → job pattern
- [05-event-sourcing.md](05-event-sourcing.md) — Event-driven job triggering
