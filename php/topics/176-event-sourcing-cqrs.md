# Event Sourcing + CQRS (Lead)

## Konseptlər

**Normal DB:** Cari state saxlanır. `UPDATE orders SET status='shipped'` əvvəlki state-i məhv edir — tarix yoxdur.

**Event Sourcing:** Yalnız events saxlanır (append-only). State heç vaxt birbaşa yazılmır — events-dən rebuild edilir.

```
// Bu kod event sourcing-in state-i event-lərdən rebuild etmə prinsipini göstərir
[OrderCreated, ItemAdded, PaymentTaken, OrderShipped]
State = events.reduce(initialState)
```

**CQRS (Command Query Responsibility Segregation):** Write (Command) və Read (Query) tərəfləri ayrılır. Write tərəfi Event Store-a event yazır; Read tərəfi denormalized, sorğuya optimized projection-lardan oxuyur.

---

## Event Sourcing — Necə işləyir?

```
// Bu kod aggregate event log-unun temporal query ilə müxtəlif versiyalarda state-i necə rebuild etdiyini göstərir
Aggregate event log:
  v1: OrderCreated   {customerId: 1, items: [...]}
  v2: ItemAdded      {productId: 5, qty: 2}
  v3: PaymentTaken   {amount: 150}
  v4: OrderShipped   {tracking: "TRK-001"}

Current state = replay(v1..v4)
State at v2   = replay(v1..v2)  ← temporal query
```

**Optimistic concurrency:** İki user eyni aggregate-i eyni anda dəyişməyə çalışırsa version conflict exception. Biri geri qayıdıb yenidən cəhd etməlidir.

**Snapshot pattern:** 10,000 event olan aggregate hər dəfə replay edirsə yavaşdır. Həll: müəyyən versionda snapshot saxla, sonrakı events-i yalnız snapshot-dan etibarən replay et.

```
// Bu kod snapshot pattern-inin böyük event log-unu necə optimallaşdırdığını göstərir
Snapshot at v5000: Order{status: 'paid', total: 500, ...}
Replay: snapshot + events[v5001..v5200]
→ 5000 event əvəzinə 200 event replay
```

---

## CQRS ilə inteqrasiya

```
// Bu kod CQRS-in write və read tərəflərinin event store vasitəsilə ayrılmasını göstərir
Write Side:
  Command → Command Handler → Aggregate → Events → Event Store

Read Side (async):
  Event Store → Projectors → Read Models (denormalized)
  Query Handler → Read Model → Fast response

Nəticə:
  Write: domain logic, consistency, correctness
  Read:  sürət, JOIN yoxdur, use-case-ə uyğun shape
```

Write və read ayrı DB-də ola bilər: write PostgreSQL, read MySQL və ya Redis — ayrı scale edilir.

---

## Niyə bu problemlər yaranır?

**Eventual consistency:** Event yazılır, projection async işlənir. 100ms lag — user yazır, dərhal oxuyur, köhnə data görür. Bu normal behavior-dur, lakin kritik yerlərdə (payment confirmation) write DB-dən oxumaq lazımdır.

**Event versioning:** 6 ay sonra `OrderCreated` event-inin strukturu dəyişir. Köhnə events hələ də disk-dədir. Həll: upcasting — köhnə event yeni formata çevrilir; versioned events — `OrderCreatedV2`.

**Large aggregate replay:** Snapshot olmadan minlərlə event hər dəfə replay — performance problemi.

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod event store, event-sourced aggregate, order, projector və snapshot siniflərini PHP-də göstərir
// Event Store — append-only, optimistic concurrency ilə
class MySQLEventStore implements EventStore
{
    public function append(string $aggregateId, array $events, int $expectedVersion): void
    {
        DB::transaction(function () use ($aggregateId, $events, $expectedVersion) {
            $currentVersion = DB::table('event_store')
                ->where('aggregate_id', $aggregateId)
                ->max('version') ?? 0;

            // Version uyğun gəlmirsə — başqa process dəyişib
            if ($currentVersion !== $expectedVersion) {
                throw new ConcurrencyException(
                    "Expected version $expectedVersion, got $currentVersion"
                );
            }

            foreach ($events as $i => $event) {
                DB::table('event_store')->insert([
                    'id'           => Str::uuid(),
                    'aggregate_id' => $aggregateId,
                    'type'         => get_class($event),
                    'payload'      => json_encode($event),
                    'version'      => $expectedVersion + $i + 1,
                    'occurred_at'  => now(),
                ]);
            }
        });
    }

    public function load(string $aggregateId, int $fromVersion = 0): array
    {
        return DB::table('event_store')
            ->where('aggregate_id', $aggregateId)
            ->where('version', '>', $fromVersion)
            ->orderBy('version')
            ->get()
            ->map(fn($row) => $this->deserialize($row))
            ->all();
    }
}

// Event-sourced aggregate — state birbaşa yazılmır, events apply edilir
abstract class EventSourcedAggregate
{
    protected array $uncommittedEvents = [];
    private int $version = 0;

    protected function recordEvent(object $event): void
    {
        $this->applyEvent($event);          // State-i dəyişdir
        $this->uncommittedEvents[] = $event; // Store-a yazılmaq üçün saxla
    }

    abstract protected function applyEvent(object $event): void;

    public static function reconstitute(array $events): static
    {
        $aggregate = new static();
        foreach ($events as $event) {
            $aggregate->applyEvent($event);
            $aggregate->version++;
        }
        return $aggregate;
    }

    public function pullUncommittedEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];
        return $events;
    }

    public function version(): int { return $this->version; }
}

// Order aggregate
class Order extends EventSourcedAggregate
{
    private string $status;
    private int $total = 0;

    public static function create(string $customerId, array $items): self
    {
        $order = new self();
        $order->recordEvent(new OrderCreated(
            Str::uuid(), $customerId, $items,
            array_sum(array_column($items, 'price'))
        ));
        return $order;
    }

    public function ship(string $trackingNumber): void
    {
        if ($this->status !== 'paid') {
            throw new \DomainException('Yalnız ödənilmiş order göndərilə bilər');
        }
        $this->recordEvent(new OrderShipped($trackingNumber));
    }

    protected function applyEvent(object $event): void
    {
        match(true) {
            $event instanceof OrderCreated => $this->status = 'pending',
            $event instanceof PaymentTaken => $this->status = 'paid',
            $event instanceof OrderShipped => $this->status = 'shipped',
            default => null,
        };
    }
}

// Projector — event-lərdən read model yaradır
class OrderListProjector
{
    public function onOrderCreated(OrderCreated $event): void
    {
        DB::table('order_list_view')->insert([
            'order_id'    => $event->orderId,
            'customer_id' => $event->customerId,
            'total'       => $event->total,
            'status'      => 'pending',
            'created_at'  => $event->occurredAt,
        ]);
    }

    public function onOrderShipped(OrderShipped $event): void
    {
        DB::table('order_list_view')
            ->where('order_id', $event->orderId)
            ->update(['status' => 'shipped', 'tracking' => $event->trackingNumber]);
    }
}

// Snapshot — böyük aggregate üçün performance optimizasiyası
class SnapshotStore
{
    public function save(string $aggregateId, object $aggregate): void
    {
        DB::table('snapshots')->upsert([
            'aggregate_id' => $aggregateId,
            'version'      => $aggregate->version(),
            'state'        => serialize($aggregate),
            'created_at'   => now(),
        ], ['aggregate_id'], ['version', 'state', 'created_at']);
    }

    public function load(string $aggregateId): ?array
    {
        $row = DB::table('snapshots')
            ->where('aggregate_id', $aggregateId)
            ->first();

        return $row ? ['aggregate' => unserialize($row->state), 'version' => $row->version] : null;
    }
}
```

---

## Nə vaxt istifadə etmək?

**Uyğundur:**
- Audit trail kritikdir (bank, tibb, hüquq)
- Temporal queries lazımdır ("2 həftə əvvəl nə idi?")
- Bug fix sonrası event-ləri yenidən replay etmək lazım ola bilər
- Kompleks domain, çoxlu business event

**Uyğun deyil:**
- Sadə CRUD — unnecessary complexity
- Team ES bilmirsə — learning curve yüksəkdir
- Eventual consistency tolerate edilmirsə
- Kiçik layihə

---

## Anti-patterns

- **Hər şeyə ES tətbiq etmək:** Simple user profile, settings — ES overkill. Yalnız kompleks, audit-critical domain-lər üçün.
- **Snapshot etməmək:** 50,000 eventlik aggregate hər dəfə replay — unacceptable latency.
- **Event-ləri mutation etmək:** Event append-only-dir. Köhnə event-i dəyişmək event sourcing-i pozur. Upcasting istifadə et.
- **Read üçün event store-u sorğulamaq:** Event store write-optimized-dır. Read üçün mütləq projection/read model.

---

## İntervyu Sualları

**1. Event Sourcing nədir, niyə istifadə edilir?**
State əvəzinə events saxlanır. Bütün tarix qorunur, audit trail tam-dır. Temporal query mümkündür. Tradeoff: complexity artır, eventual consistency, event versioning problemi. Sadə CRUD üçün overkill.

**2. CQRS niyə Event Sourcing ilə çox birlikdə istifadə edilir?**
Event Store write-optimal, read üçün çətin. CQRS: events-dən denormalized read model-lər (projection) yaradılır — sürətli sorğu. Birlikdə: write tərəfi domain logic-i saxlayır, read tərəfi use-case-ə uyğun şəkildə göstərir.

**3. Snapshot nədir, nə zaman lazımdır?**
Aggregate-in müəyyən versiondakı state-ini serialized saxlamaq. 1000+ event olan aggregate-lər üçün replay cost yüksəkdir. Snapshot + sonrakı events = sürətli reconstitution. Hər N event-dən sonra snapshot yarat.

**4. Optimistic concurrency Event Sourcing-də necə işləyir?**
Aggregate yüklənərkən son version oxunur. Yeni events append zamanı expected version göndərilir. Başqa process arada event yazmışsa version fərqlənir → ConcurrencyException. Client yenidən aggregate-i yükləyib retry edir.

**5. Eventual consistency problemi necə idarə edilir?**
Write commit olur, projection qısa lag ilə yenilənir. Kritik oxumalar (payment status, balance) write DB-dən oxusun. Non-critical (dashboard, listing) read model-dən. UI-da "data bir neçə saniyə gecikmə ilə yenilənə bilər" bildirişi.

**6. Event upcasting nədir?**
Köhnə event schemalarını yeni formata çevirmək. `OrderCreatedV1` → `OrderCreatedV2` dönüşümü event store-dan yükləmə zamanı tətbiq edilir. Köhnə event-lər dəyişdirilmir (immutable), yalnız deserialization zamanı transform edilir. Upcaster chain-i ilə V1 → V2 → V3 zənciri qurula bilər.

**7. Event Sourcing-də "correlation ID" vs "causation ID" nədir?**
Correlation ID: bir iş axınındakı bütün event-lər eyni correlation ID-ni daşıyır — bir sorğunun yaratdığı bütün event-ləri izləmək üçün. Causation ID: bu event-i hansı event yaratdı. İkisi birlikdə audit trail-i tam izlənilə bilir edir.

---

## Anti-patternlər

**1. Sadə CRUD tətbiqə Event Sourcing tətbiq etmək**
Hər entity üçün event store, projection, snapshot — komplekslik artır, inkişaf yavaşlayır, komanda öyrənmə yükü böyüyür, halbuki tətbiq audit trail-ə ehtiyac duymur. Event Sourcing-i yalnız real tələb olduqda seçin: audit trail kritikdirsə, temporal query lazımdırsa, event replay dəyər yaradırsansa.

**2. Event schema-nı versiyalanmadan dəyişmək**
`OrderConfirmed` event-inin field-ləri dəyişdirilir — event store-da köhnə strukturda milyonlarla event var, yeni projection onları parse edə bilmir. Event-ləri immutable saxlayın; dəyişiklik lazımdırsa yeni event versiyası yaradın (`OrderConfirmedV2`); upcasting ilə köhnə event-ləri yeni formata çevirin.

**3. Event store-dan birbaşa read sorğusu etmək**
`SELECT * FROM event_store WHERE aggregate_id=? ORDER BY version` hər read-də — event store write-optimized-dır, mürəkkəb sorğular üçün deyil. Read model-ləri (projection) yaradın: event-lərdən denormalized, sorğuya uyğun cədvəllər qurun; event store yalnız write üçün.

**4. Snapshot almadan yüzlərlə event olan aggregate-i yükləmək**
`Order` aggregate-inin 500 event-i var, hər yükləmədə hamısı replay edilir — latency artır, resurS israfı olur. Müəyyən event sayından sonra snapshot alın (məs: hər 50 event-dən sonra); yükləmədə ən yaxın snapshot + sonrakı event-lər replay edilsin.

**5. CQRS-i Event Sourcing olmadan "sadəcə read/write ayrımı" kimi tətbiq etmək**
Write model oxuyur, read model ayrı cədvəldir, amma event-siz sync edilir — projection rebuild mümkün deyil, data uyuşmazlığı olduqda mənbə-yə qayıtmaq olmur. CQRS-i Event Sourcing ilə birlikdə istifadə edin; event-lər həm write-ın audit trail-i, həm projection-ların mənbəyidir.

**6. Optimistic concurrency check olmadan event store-a yazmaq**
İki proses eyni aggregate-i eyni anda yükləyir, hər ikisi yeni event append edir — ikinci write birincinin üstünə yazır, version conflict gizli qalır. Event append-də expected version yoxlayın: `INSERT ... WHERE version = ?`; conflict-də `ConcurrencyException` atın, caller retry etsin.
