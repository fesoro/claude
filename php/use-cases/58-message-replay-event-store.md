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
