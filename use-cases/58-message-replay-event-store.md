# Message Replay & Event Store Recovery (Lead)

## Problem
- Microservice consumer-də bug → 2 saat ərzində bütün event-lər səhv işləndi
- Event-lər silinməyib (Kafka retention 7 gün)
- Replay edib data-nı düzəltmək lazımdır
- Production traffic-i pozmadan

---

## Həll: Replay strategy + idempotency + offset management

```
Strategy seçimi:
  
  1. CONSUMER OFFSET RESET (Kafka)
     Bütün group offset-ini geri at, baştan oxu
     ✓ Sadə
     ✗ Live consumer pozulur
  
  2. PARALLEL REPLAY GROUP
     Yeni consumer group ilə replay
     ✓ Live trafik toxunulmur
     ✓ İdeal
  
  3. EVENT STORE DIRECT QUERY
     DB-də saxlanan event-ləri filter ilə re-process
     ✓ Audit trail
     ✗ Storage cost
  
  4. SELECTIVE REPLAY
     Müəyyən aggregate (user_id, order_id) üçün replay
     ✓ Kiçik dataset, sürətli
```

---

## 1. Kafka offset reset

```bash
# Bütün consumer group offset-ini sıfırla
kafka-consumer-groups.sh \
  --bootstrap-server kafka:9092 \
  --group order-processors \
  --reset-offsets \
  --to-datetime 2026-04-19T08:00:00.000 \
  --topic shop.orders \
  --execute

# YA DA specific offset
kafka-consumer-groups.sh \
  --reset-offsets \
  --to-offset 12345 \
  --topic shop.orders \
  --group order-processors \
  --execute

# Offset earliest (full replay)
kafka-consumer-groups.sh --reset-offsets --to-earliest ...
```

```
PROBLEM:
  Live consumer-i restart etmək lazımdır.
  Bu müddətdə yeni event-lər process olunmur.
  
  Daha yaxşı: parallel consumer group istifadə et.
```

---

## 2. Parallel replay group

```php
<?php
// Yeni consumer group ilə paralel oxu
class ReplayConsumer
{
    private $kafkaContext;
    
    public function __construct(string $groupSuffix)
    {
        $factory = new RdKafkaConnectionFactory([
            'global' => [
                'group.id'             => "order-processors-replay-$groupSuffix",
                'metadata.broker.list' => env('KAFKA_BROKER'),
                'auto.offset.reset'    => 'earliest',
            ],
        ]);
        $this->kafkaContext = $factory->createContext();
    }
    
    public function replay(\DateTime $from, \DateTime $to): void
    {
        $topic = $this->kafkaContext->createTopic('shop.orders');
        $consumer = $this->kafkaContext->createConsumer($topic);
        
        $count = 0;
        
        while (true) {
            $msg = $consumer->receive(5000);
            if (!$msg) break;
            
            $event = json_decode($msg->getBody(), true);
            $ts = strtotime($event['ts']);
            
            // Range filter
            if ($ts < $from->getTimestamp()) {
                $consumer->acknowledge($msg);
                continue;
            }
            if ($ts > $to->getTimestamp()) {
                break;   // replay end
            }
            
            // Process (idempotent!)
            $this->processWithDedup($event);
            $consumer->acknowledge($msg);
            $count++;
        }
        
        echo "Replayed $count events\n";
    }
    
    private function processWithDedup(array $event): void
    {
        $eventId = $event['id'];
        
        // Idempotency check (Redis SET if not exists)
        $isNew = Redis::set("replay:processed:$eventId", '1', 'NX', 'EX', 86400);
        if (!$isNew) {
            return;   // artıq replay olunub bu run-da
        }
        
        // Real processing
        match ($event['type']) {
            'OrderCreated' => $this->handleOrderCreated($event),
            'OrderUpdated' => $this->handleOrderUpdated($event),
            'OrderCancelled' => $this->handleOrderCancelled($event),
        };
    }
}
```

---

## 3. Event Store DB schema

```sql
CREATE TABLE event_store (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    aggregate_id VARCHAR(255) NOT NULL,
    aggregate_type VARCHAR(255) NOT NULL,
    event_type VARCHAR(255) NOT NULL,
    event_version INT DEFAULT 1,
    payload JSON NOT NULL,
    metadata JSON,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sequence_no BIGINT NOT NULL,    -- per-aggregate sequence
    INDEX (aggregate_id, sequence_no),
    INDEX (event_type, occurred_at),
    INDEX (occurred_at)
);
```

```php
<?php
// Append event
class EventStore
{
    public function append(string $aggregateId, string $type, string $eventType, array $payload): void
    {
        DB::transaction(function () use ($aggregateId, $type, $eventType, $payload) {
            // Last sequence
            $lastSeq = DB::table('event_store')
                ->where('aggregate_id', $aggregateId)
                ->lockForUpdate()
                ->max('sequence_no') ?? 0;
            
            DB::table('event_store')->insert([
                'aggregate_id'   => $aggregateId,
                'aggregate_type' => $type,
                'event_type'     => $eventType,
                'payload'        => json_encode($payload),
                'sequence_no'    => $lastSeq + 1,
                'occurred_at'    => now(),
            ]);
        });
    }
    
    public function load(string $aggregateId, ?int $fromSequence = null): array
    {
        return DB::table('event_store')
            ->where('aggregate_id', $aggregateId)
            ->when($fromSequence, fn($q) => $q->where('sequence_no', '>=', $fromSequence))
            ->orderBy('sequence_no')
            ->get()
            ->map(fn($r) => array_merge((array) $r, ['payload' => json_decode($r->payload, true)]))
            ->toArray();
    }
}
```

---

## 4. Selective replay (per aggregate)

```php
<?php
class AggregateReplayer
{
    public function replayAggregate(string $aggregateId): void
    {
        $events = app(EventStore::class)->load($aggregateId);
        
        // Reset projections (read model)
        OrderProjection::where('order_id', $aggregateId)->delete();
        OrderItemProjection::where('order_id', $aggregateId)->delete();
        
        // Re-apply
        foreach ($events as $event) {
            $this->dispatcher->dispatch(
                $this->reconstructEvent($event)
            );
        }
    }
    
    public function replayRange(\DateTime $from, \DateTime $to, ?string $aggregateType = null): int
    {
        $count = 0;
        
        DB::table('event_store')
            ->whereBetween('occurred_at', [$from, $to])
            ->when($aggregateType, fn($q) => $q->where('aggregate_type', $aggregateType))
            ->orderBy('id')
            ->chunk(1000, function ($events) use (&$count) {
                foreach ($events as $event) {
                    $this->reapply($event);
                    $count++;
                }
            });
        
        return $count;
    }
    
    private function reapply(object $event): void
    {
        try {
            $eventClass = $this->resolveClass($event->event_type);
            $domainEvent = $eventClass::fromArray(json_decode($event->payload, true));
            
            $this->dispatcher->dispatch($domainEvent);
        } catch (\Throwable $e) {
            ReplayFailureLog::create([
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}

// Artisan command
class ReplayEventsCommand extends Command
{
    protected $signature = 'events:replay
                            {--from= : Start datetime}
                            {--to= : End datetime}
                            {--aggregate= : Filter by aggregate type}
                            {--id= : Specific aggregate ID}';
    
    public function handle(AggregateReplayer $replayer): int
    {
        if ($id = $this->option('id')) {
            $replayer->replayAggregate($id);
            $this->info("Replayed aggregate $id");
        } else {
            $from = new \DateTime($this->option('from'));
            $to = new \DateTime($this->option('to'));
            
            if (! $this->confirm("Replay events from $from to $to. Continue?")) {
                return self::FAILURE;
            }
            
            $count = $replayer->replayRange($from, $to, $this->option('aggregate'));
            $this->info("Replayed $count events");
        }
        
        return self::SUCCESS;
    }
}
```

---

## 5. Projection rebuild

```php
<?php
// Bug: OrderProjection.total wrong (calculation səhv)
// Fix: code düzəlt → bütün projection-ları rebuild

class RebuildProjection extends Command
{
    protected $signature = 'projection:rebuild {projection}';
    
    public function handle(): int
    {
        $projection = $this->argument('projection');   // e.g., "order"
        
        // 1. Projection table-i sıfırla
        match ($projection) {
            'order' => OrderProjection::truncate(),
            'user'  => UserProjection::truncate(),
        };
        
        // 2. Bütün event-ləri replay
        $bar = $this->output->createProgressBar();
        DB::table('event_store')
            ->orderBy('id')
            ->chunk(1000, function ($events) use ($bar) {
                foreach ($events as $event) {
                    app(EventDispatcher::class)
                        ->dispatch($this->reconstructEvent($event));
                    $bar->advance();
                }
            });
        $bar->finish();
        
        $this->newLine();
        $this->info('Rebuild complete');
        return self::SUCCESS;
    }
}
```

---

## 6. Production replay safety

```
Pre-replay checklist:
  ☐ Bug fix deployed first
  ☐ Idempotent handler verified (replay 2 dəfə → eyni nəticə)
  ☐ Live consumer və replay consumer ayrı group
  ☐ Replay consumer separate worker pool
  ☐ Rate limit (production load-u boğmasın)
  ☐ Monitoring (live + replay metric ayrı)
  ☐ Manual approval (audit log)
  ☐ Rollback plan (replay-i dayandır necə)
```

```php
<?php
// Rate limited replay
class ThrottledReplayer
{
    public function replay(int $maxPerSec = 100): void
    {
        $rateLimiter = RateLimiter::for('replay');
        
        DB::table('event_store')
            ->orderBy('id')
            ->chunk(100, function ($events) use ($rateLimiter, $maxPerSec) {
                foreach ($events as $event) {
                    $rateLimiter->attempt('replay-key', $maxPerSec, function () use ($event) {
                        $this->reapply($event);
                    }, 1);   // 1s decay
                }
            });
    }
}
```

---

## 7. Pitfalls

```
❌ Non-idempotent handler — replay duplicate side effect
   ✓ Idempotency key (event_id), check-and-set

❌ Side effect (email, payment) replay-də — DUPLICATE PAYMENT!
   ✓ Side effect-ləri "external action log" cədvəlində işarələ
   ✓ Replay zamanı external action SKIP

❌ Schema evolution — köhnə event payload yeni handler-ə uyğun deyil
   ✓ Event versioning, upgrade migrator

❌ Aggregate ordering pozulur (parallel replay)
   ✓ Per-aggregate sequence number, single-threaded per aggregate

❌ Live data ilə conflict — replay sonra DB inconsistent
   ✓ Replay window-ında write block, snapshot, restore pattern

❌ Replay slowdown live system
   ✓ Separate replica, separate worker pool, rate limit
```

---

## 8. Real-world recovery scenario

```
İncident timeline:
  10:00 — Bug deploy (OrderCreated handler total səhv hesablayır)
  12:00 — Bug aşkarlandı (1000 order səhv toplam)
  12:05 — Bug fix deploy
  12:10 — Replay plan approve
  12:15 — Replay command:
          php artisan events:replay --aggregate=Order \
              --from="2026-04-19 10:00" --to="2026-04-19 12:05"
  12:20 — 1000 OrderCreated event re-dispatched
  12:21 — OrderProjection rebuilt for affected orders
  12:25 — Verification (10 sample order check)
  12:30 — Status: RESOLVED
```

---

## Problem niyə yaranır?

Microservice arxitekturasında hər servis öz vəziyyətini (state) event-lər əsasında qurur. Consumer bir event alır, onu işləyir, local DB-yə yazar. Bu model çox güclüdür — amma hər şeyin düzgün işlənməsindən asılıdır. Bug olan bir consumer xətalı məlumat yazır, amma bu xəta dərhal görünmür: sistem işləməyə davam edir, yeni event-lər gəlir, hər biri yenə səhv işlənir. İki saat ərzində yüzlərlə və ya minlərlə event yanlış proyeksiya yaradır — `order_totals` cədvəlindəki məbləğlər yanlış hesablanır, `inventory` counter-ləri artıq düzgün deyil, `user_stats` aggregate-ləri reallıqla uyğun gəlmir.

Texniki baxımdan, bug zamanı işlənən event-lərin hər biri bir və ya bir neçə DB row-a yazılmışdır. Bu row-lar artıq corrupted state-i saxlayır — nə sadəcə silinə bilər, nə də "undo" edilə bilər, çünki hər row öncəki row-ların üzərindəki incremental dəyişiklikdir. Məsələn, `OrderUpdated` handler-inin `total` sütununu yanlış hesabladığını düşünək: 2 saat ərzində həmin `total` sütunu həm yaranmış, həm yenilənmiş, həm də bəzən digər servislərin hesablamalarına input olmuşdur. Sadəcə "son dəyəri düzəlt" demək yetərli deyil — bütün törəmə məlumatlar da yanlışdır.

"Bug-ı düzəlt və davam et" yanaşması işə yaramır, çünki mövcud corrupted state bir reference point kimi qalır. Yeni düzgün gələn event-lər yanlış baseline üzərinə yazılacaq. Məsələn, əgər `order_total` bug zamanı 150 AZN əvəzinə 100 AZN yazılıbsa, sonra gələn `OrderDiscounted` event-i 100 AZN üzərindən endirim tətbiq edəcək — doğru 150 AZN üzərindən yox. Məhz buna görə corrupted dövr üçün bütün event-lər yenidən işlənməlidir: state tamamilə sıfırlanır, sonra event-lər başdan doğru tətbiq edilir.

---

## Event Store PHP Implementation

Aşağıdakı implementation event-ləri DB-də persist edir, aggregate üzrə yükləyir və Artisan command vasitəsilə replay edir. Idempotency üçün `processed_events` cədvəlindən istifadə olunur.

### Migration

```php
// database/migrations/2024_01_10_create_event_store_records_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_store_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();              // Hər event üçün unikal ID
            $table->string('aggregate_type', 100);           // 'Order', 'User', 'Payment'
            $table->string('aggregate_id', 255);             // order-uuid, user-123
            $table->string('event_type', 100);               // 'OrderCreated', 'OrderUpdated'
            $table->unsignedSmallInteger('event_version')->default(1); // Schema version
            $table->json('payload');                         // Event data-sı
            $table->json('metadata')->nullable();            // correlation_id, user_agent, ip
            $table->unsignedBigInteger('sequence_no');       // Per-aggregate sıra nömrəsi
            $table->timestamp('occurred_at');                // Event baş verdiyi an
            $table->timestamps();

            // Aggregate-ə görə sürətli yüklənmə
            $table->index(['aggregate_type', 'aggregate_id', 'sequence_no'], 'idx_aggregate_seq');
            // Zaman aralığı üzrə replay
            $table->index(['aggregate_type', 'occurred_at'], 'idx_type_time');
            // Event tip üzrə filtr
            $table->index(['event_type', 'occurred_at'], 'idx_event_type_time');
        });

        // Replay idempotency — eyni event ikinci dəfə işlənməsin
        Schema::create('replay_processed_events', function (Blueprint $table) {
            $table->id();
            $table->string('replay_run_id', 100);            // Bir replay session-ın ID-si
            $table->uuid('event_id');
            $table->timestamp('processed_at');

            $table->unique(['replay_run_id', 'event_id'], 'idx_replay_event_unique');
            $table->index('replay_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replay_processed_events');
        Schema::dropIfExists('event_store_records');
    }
};
```

### EventStoreRecord Model

```php
// app/Models/EventStoreRecord.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventStoreRecord extends Model
{
    protected $fillable = [
        'event_id',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'event_version',
        'payload',
        'metadata',
        'sequence_no',
        'occurred_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
    ];
}
```

### EventStore Service

```php
// app/Services/EventStore.php
namespace App\Services;

use App\Models\EventStoreRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventStore
{
    /**
     * Event-i event store-a əlavə edir.
     * sequence_no per-aggregate olaraq avtomatik artırılır.
     * DB transaction ilə sequence race condition qarşısı alınır.
     */
    public function append(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload,
        array $metadata = [],
        int $eventVersion = 1,
    ): EventStoreRecord {
        return DB::transaction(function () use (
            $aggregateType, $aggregateId, $eventType, $payload, $metadata, $eventVersion
        ) {
            // Aggregate-in son sequence_no-sunu pessimistic lock ilə al
            $lastSeq = EventStoreRecord::where('aggregate_type', $aggregateType)
                ->where('aggregate_id', $aggregateId)
                ->lockForUpdate()
                ->max('sequence_no') ?? 0;

            return EventStoreRecord::create([
                'event_id'       => (string) Str::uuid(),
                'aggregate_type' => $aggregateType,
                'aggregate_id'   => $aggregateId,
                'event_type'     => $eventType,
                'event_version'  => $eventVersion,
                'payload'        => $payload,
                'metadata'       => $metadata,
                'sequence_no'    => $lastSeq + 1,
                'occurred_at'    => now(),
            ]);
        });
    }

    /**
     * Bir aggregate-in bütün event-lərini sıra ilə yükləyir.
     * fromSequence ilə partial yükləmə mümkündür (incremental replay).
     */
    public function getEventsFor(
        string $aggregateType,
        string $aggregateId,
        ?int $fromSequence = null,
    ): Collection {
        return EventStoreRecord::where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->when($fromSequence, fn($q) => $q->where('sequence_no', '>=', $fromSequence))
            ->orderBy('sequence_no')
            ->get();
    }

    /**
     * Zaman aralığına və aggregate type-a görə event-ləri chunk ilə qaytarır.
     * Böyük datasetlər üçün memory-safe.
     *
     * @param callable $callback fn(Collection $chunk): void
     */
    public function getEventsInRange(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?string $aggregateType = null,
        int $chunkSize = 500,
        callable $callback = null,
    ): int {
        $query = EventStoreRecord::whereBetween('occurred_at', [$from, $to])
            ->when($aggregateType, fn($q) => $q->where('aggregate_type', $aggregateType))
            ->orderBy('id');

        $total = 0;

        $query->chunk($chunkSize, function (Collection $chunk) use (&$total, $callback) {
            $total += $chunk->count();
            if ($callback) {
                $callback($chunk);
            }
        });

        return $total;
    }

    /**
     * Bir event-in artıq bu replay session-da işlənib-işlənmədiyini yoxlayır.
     * Replay-i dayandırıb yenidən başlatdıqda dublikat işlənməni önləyir.
     */
    public function markProcessed(string $replayRunId, string $eventId): bool
    {
        try {
            DB::table('replay_processed_events')->insert([
                'replay_run_id' => $replayRunId,
                'event_id'      => $eventId,
                'processed_at'  => now(),
            ]);
            return true;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false; // Artıq işlənib
        }
    }

    public function isProcessed(string $replayRunId, string $eventId): bool
    {
        return DB::table('replay_processed_events')
            ->where('replay_run_id', $replayRunId)
            ->where('event_id', $eventId)
            ->exists();
    }
}
```

### Replay Command (tam idempotency ilə)

```php
// app/Console/Commands/ReplayEventsFromStore.php
namespace App\Console\Commands;

use App\Models\EventStoreRecord;
use App\Services\EventStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReplayEventsFromStore extends Command
{
    protected $signature = 'events:replay-store
                            {--from=          : Başlangıc vaxtı (Y-m-d H:i:s)}
                            {--to=            : Bitmə vaxtı (Y-m-d H:i:s)}
                            {--aggregate=     : Aggregate type filtri (məs: Order)}
                            {--id=            : Tək aggregate ID (selective replay)}
                            {--run-id=        : Mövcud replay session-ı davam etdir}
                            {--dry-run        : Nə işləyəcəyini göstər, əməliyyat etmə}
                            {--rate=100       : Saniyədə maksimum event sayı}';

    protected $description = 'Event Store-dan event-ləri idempotent şəkildə replay edir';

    public function handle(EventStore $store): int
    {
        $replayRunId = $this->option('run-id') ?? (string) Str::uuid();
        $isDryRun    = (bool) $this->option('dry-run');
        $rateLimit   = (int) ($this->option('rate') ?? 100);

        $this->info("Replay Run ID: $replayRunId");
        if ($isDryRun) {
            $this->warn('DRY RUN — heç bir dəyişiklik yazılmayacaq');
        }

        // Selective replay: tək aggregate
        if ($id = $this->option('id')) {
            return $this->replayAggregate($store, $replayRunId, $id, $isDryRun);
        }

        // Range replay: zaman aralığı
        $from = new \DateTime($this->option('from'));
        $to   = new \DateTime($this->option('to'));
        $type = $this->option('aggregate');

        $this->info("Replay: $from → $to" . ($type ? ", type: $type" : ''));

        if (!$isDryRun && !$this->confirm('Davam etmək istəyirsiniz?')) {
            return self::FAILURE;
        }

        $processed = 0;
        $skipped   = 0;
        $failed    = 0;
        $startTime = microtime(true);

        $bar = $this->output->createProgressBar();

        $store->getEventsInRange($from, $to, $type, 500, function ($chunk) use (
            $store, $replayRunId, $isDryRun, $rateLimit,
            &$processed, &$skipped, &$failed, $bar
        ) {
            foreach ($chunk as $record) {
                // Idempotency: bu event artıq işlənibsə keç
                if ($store->isProcessed($replayRunId, $record->event_id)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if (!$isDryRun) {
                    try {
                        $this->dispatchEvent($record);
                        $store->markProcessed($replayRunId, $record->event_id);
                        $processed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('Replay failed for event', [
                            'event_id'   => $record->event_id,
                            'event_type' => $record->event_type,
                            'error'      => $e->getMessage(),
                        ]);
                    }

                    // Rate limiting: saniyədə $rateLimit event
                    if ($processed % $rateLimit === 0) {
                        usleep(1_000_000); // 1 saniyə
                    }
                } else {
                    $processed++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Tamamlandı: processed=$processed, skipped=$skipped, failed=$failed, time={$elapsed}s");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function replayAggregate(
        EventStore $store,
        string $replayRunId,
        string $aggregateId,
        bool $isDryRun,
    ): int {
        $aggregateType = $this->option('aggregate') ?? 'Order';
        $events = $store->getEventsFor($aggregateType, $aggregateId);

        $this->info("Aggregate $aggregateId üçün {$events->count()} event tapıldı");

        foreach ($events as $record) {
            if (!$isDryRun) {
                $this->dispatchEvent($record);
                $store->markProcessed($replayRunId, $record->event_id);
            }
            $this->line("  [{$record->sequence_no}] {$record->event_type} @ {$record->occurred_at}");
        }

        return self::SUCCESS;
    }

    private function dispatchEvent(EventStoreRecord $record): void
    {
        // Event class-ını event_type-a görə resolve et
        $eventClass = "App\\Events\\{$record->event_type}";

        if (!class_exists($eventClass)) {
            throw new \RuntimeException("Event class tapılmadı: $eventClass");
        }

        $domainEvent = $eventClass::fromArray($record->payload);
        event($domainEvent);
    }
}
```

---

## Trade-offs

| Strategiya | Sürət | Risk | Idempotency tələbi | Nə zaman |
|---|---|---|---|---|
| **Offset Reset (Kafka)** | Sürətli — Kafka-nın öz mexanizmi | Yüksək — live consumer dayandırılmalıdır, yeni event-lər process olunmur | Mütləq — eyni event ikinci dəfə işlənəcək | Sadə sistemlər, live traffic az olan vaxtlarda, texniki borc qəbul edilirki |
| **Parallel Replay Group** | Orta — ayrı consumer group qurulur | Aşağı — live group toxunulmur, paralel işləyir | Mütləq — eyni proyeksiyanı iki consumer yazacaq | Production-da ən təhlükəsiz seçim; live trafik kəsilməməlidir |
| **Event Store Direct Query** | Yavaş — DB I/O, chunk, rate limit | Orta — DB load artır, projection sıfırlanmalıdır | Orta — `replay_processed_events` cədvəli ilə idarə olunur | Seçici replay lazımdırsa; audit trail vacibdirsə; Kafka retention bitibsə |
| **Selective Replay** | Ən sürətli — yalnız bir aggregate | Aşağı — digər aggregate-lərə toxunulmur | Orta — per-aggregate idempotency yetər | Bir neçə konkret `user_id` / `order_id` üçün düzəliş; tam sistemdən deyil, hissəvi data corruption |

---

## Anti-patternlər

**1. Consumer-i idempotent etməmək**
Ən çox yayılmış səhvdir. Replay zamanı eyni event ikinci dəfə işlənir — əgər handler `INSERT` edir əvəzinə `INSERT OR UPDATE` (upsert) etmirsə, dublikat row yaranır. Hər handler `event_id` yoxlamalı, ya DB unique constraint-dən, ya da explicit check-from istifadə etməlidir.

**2. Event-ləri qısa retention ilə saxlamaq**
Kafka-da 3 günlük retention qoymaq kifayət görünür — bug-ı 4 günlük istifadəçi data-sında aşkar etdikdə replay mümkün olmur. Event Store yoxdursa, Kafka retention minimum 30 gün olmalıdır; kritik sistemlər üçün Event Store tətbiq edilməlidir ki, event-lər sınırsız saxlansın.

**3. Production-da live consumer-i durdurmadan replay etmək**
Həm live consumer, həm replay consumer eyni proyeksiyaya yazırsa — race condition, qarışıq sıralama, data corruption daha da artır. Ya ayrı consumer group ilə paralel replay istifadə et (projection-a eyni idempotent yazılışla), ya da replay-dən əvvəl live consumer-i dayandır.

**4. Replay-i monitoring olmadan etmək**
"Replay command işə salındı" dedikdən sonra unudulmaq real incident yaradır. Replay progress-i metrics-ə yazılmalı, failed event-lər alert göndərməli, tamamlananda confirmation notification göndərilməlidir. `replay_run_id` vasitəsilə hər replay session-ı izləmək mümkündür.

**5. Event schema-sını versiyalamadan dəyişmək**
Event Store-da saxlanmış köhnə event-lərin payload-ı dəyişdirilibsə — replay-də `fromArray()` call-u exception atır. Hər event-in `event_version` sütunu olmalıdır; handler köhnə versiyaları migrate edən `upcaster` tətbiq etməlidir.

**6. Replay zamanı throttle etməmək**
Event Store-da 1 milyon event-i heç bir rate limit olmadan replay etmək DB-ni, downstream servisləri, email/notification provider-ləri sıradan çıxarır. `--rate` parametri ilə saniyəlik işlənən event sayını məhdudlaşdırın; ayrı replica üzərindən oxuyun; replica-ya oxu, primary-ə yazı yönləndirin.

**7. Test environment-da replay test etməmək**
Replay mexanizmi production-da incident zamanı ilk dəfə sınaqdan keçirilir — bu ən pis vaxtdır. Hər sprint-də staging-də replay test ssenarisi işlədilməlidir: bilərəkdən bug deploy et → consumer-i düzəlt → replay et → nəticəni yoxla. Bu drill olmadan replay-in özü gözlənilməz bug yarada bilər.

---

## Interview Sualları və Cavablar

**S: Event replay zamanı idempotency niyə kritikdir?**

Replay-in məqsədi corrupted data-nı düzəltməkdir. Amma handler idempotent deyilsə, eyni event ikinci dəfə işləndiyi zaman yan effektlər ikiqat baş verir: email göndərilir, `total` iki dəfə artırılır, `inventory` iki dəfə azaldılır. Nəticədə replay data-nı düzəltmir — daha da korlandı. Idempotency `event_id` əsaslı check-and-set ilə həll olunur: əvvəl `INSERT IGNORE INTO processed_events`, sonra əsl iş. Uğurlu insert isə event-in işlənmədiyini, müvəffəqiyyətsiz insert isə artıq işləndiyini göstərir.

**S: Consumer bug-ı production-da düzəltmədən replay etmək olarmı?**

Olmaz — bu cür replay bir problem həll etmək yerinə ikinci problem yaradır. Bug düzəlməmiş handler eyni yanlış məntiqlə event-ləri ikinci dəfə işləyir. Düzgün ardıcıllıq belədir: (1) corrupted data-nı read-only mode-a al və ya projection-ları pause et, (2) bug fix-i hazırlayıb test et, (3) bug fix-i deploy et, (4) replay et, (5) verify et. Bəzən bug fix-i deploy etmədən əvvəl corrupted projection-ları `truncate` etmək lazım gəlir ki, live users daha çox yanlış data görməsin — lakin replay tamamlanana qədər service degraded vəziyyətdə qalacaq.

**S: Kafka-nın retention müddəti qurtarıbsa nə edərdiniz?**

Bu vəziyyətdə 3 seçim var. Birincisi, Event Store-un mövcudluğu: əgər event-lər DB-də saxlanıbsa, birbaşa oradan replay mümkündür. İkincisi, snapshot + partial replay: əgər aggregate snapshot-ları tutulursa (məs: hər 100 event-dən sonra), son snapshot-dan sonrakı event-ləri Kafka-da tapsaq bəs edər. Üçüncüsü, manual reconstruction: backup-lardan, audit log-lardan, üçüncü tərəf sistemlərindən (payment provider, ERP) data çəkib projection-ları əl ilə rebuild etmək. Bu incident-dən çıxarılacaq əsas dərs: həmişə Event Store saxla və ya Kafka retention-ı kifayət qədər uzun tut. "Retention 7 gün yetər" düşüncəsi — aylıq audit ancaq fark edilən bug-lar üçün heç nəyi həll etmir.

**S: Event Store vs Kafka — fərqləri nədir?**

Kafka bir **message broker**-dir: event-lər müvəqqəti saxlanır (retention ilə məhdud), consumer group-lar offset ilə oxuyur, event-lər aggregate üzrə deyil, topic partition üzrə saxlanır. Event Store bir **persistent append-only log**-dur: event-lər sınırsız saxlanır, aggregate üzrə sıra nömrəsi var, istənilən vaxt istənilən aggregate-in tarixini yükləmək mümkündür. Praktik fərq: Kafka replay üçün offset-i sıfırlamaq lazımdır — bu başqa consumer-ləri təsir edə bilər. Event Store isə `SELECT WHERE aggregate_id = ?` ilə istənilən aggregate-i izolasiyalı rehydrate edir. Kafka messaging üçün, Event Store auditing + replay üçün optimallaşdırılmışdır; yaxşı arxitektura ikisini birlikdə istifadə edir.

**S: Selective replay (yalnız bir user-in event-lərini) necə implement edərdiniz?**

Selective replay üç addımlıdır. Birinci addım: yalnız həmin `user_id` üçün mövcud projection-ları sıfırlamaq — `DELETE FROM order_projections WHERE user_id = ?`, `DELETE FROM user_stats WHERE user_id = ?`. İkinci addım: Event Store-dan həmin aggregate-in bütün event-lərini sequence ilə yükləmək — `getEventsFor('User', $userId)`. Üçüncü addım: event-ləri sıraya görə yenidən dispatch etmək, hər biri idempotent işlənməlidir. Kafka-da selective replay daha çətindir — topic partition üzrə bölündüyündən user-in event-ləri fərqli partition-larda ola bilər. Buna görə selective replay üçün Event Store daha uyğundur; Kafka isə fan-out, real-time messaging üçün optimal qalır.

---

## Əlaqəli Mövzular

- `57-cqrs-read-write-separation.md` — Event Store CQRS ilə birlikdə tez-tez istifadə olunur
- `59-batch-reports.md` — Replay-in projection rebuild-ə tətbiqi
- `02-double-charge-prevention.md` — Idempotency pattern-lərinin payment kontekstindəki tətbiqi
