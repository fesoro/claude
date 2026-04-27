# Event Sourcing (Architect ⭐⭐⭐⭐⭐)

## İcmal
Event Sourcing — sistemin cari vəziyyətini saxlamaq əvəzinə, o vəziyyətə gətirib çıxaran bütün hadisələri saxlayan arxitektura yanaşmasıdır. Bank hesabı nümunəsi: balansı bir sütunda saxlamaq əvəzinə hər deposit/withdrawal hadisəsini saxlayırıq, balansı hesablamaq lazım olanda bütün hadisələri replay edirik. Interview-da bu mövzu Architect səviyyəli mövqelər üçün çıxır.

## Niyə Vacibdir
Event Sourcing audit trail, temporal query (keçmişdəki vəziyyəti öyrənmək), event replay və debug imkanları yaradır. Fintech, banking, legal compliance tələb edən sistemlər üçün ideal seçimdir. CQRS ilə birlikdə oxu/yazı yükünü ayrı-ayrı scale etmək mümkün olur. Bu mövzunu dərindən bilmək sizin distributed systems mürəkkəbliyini idarə edə biləcəyinizi göstərir.

## Əsas Anlayışlar

- **Event Store**: Hadisələrin append-only şəkildə saxlandığı veritabanı — EventStoreDB, PostgreSQL, Kafka
- **Append-only**: Hadisələr heç vaxt dəyişdirilmir, yalnız əlavə edilir — immutability
- **Event Replay**: Bütün hadisələri sıra ilə tətbiq edərək cari vəziyyəti yenidən qurmaq
- **Snapshot**: Müəyyən nöqtədə Aggregate vəziyyətinin kopyası — hər dəfə bütün hadisələri replay etməmək üçün
- **Projection**: Event stream-dən başqa bir oxu modeli qurmaq — məsələn, OrderSummary view-u
- **Command**: Niyyət — `PlaceOrder`, `CancelOrder`. Event: baş vermiş fakt — `OrderPlaced`, `OrderCancelled`
- **Event versioning**: Hadisə strukturu zamanla dəyişir — versioning strategiyası lazımdır (upcasting)
- **Eventual consistency**: Projection-lar real-time yenilənməyə bilər — eventual consistency qəbul etmək lazımdır
- **Optimistic concurrency**: Eyni Aggregate-ə eyni anda iki event gəlsə version mismatch detect edilir
- **Read model / View model**: Event-lərdən query-lər üçün ayrı data struktur qurulur (CQRS ilə birlikdə)
- **CQRS birlikdə**: Event Sourcing write tərəfini, CQRS read tərəfini idarə edir — mükəmməl cüt
- **Temporal query**: "3 ay əvvəl hesab nə qədər idi?" — event store-dan cavab alınır
- **Compensating event**: Bir hadisəni ləğv etmək üçün əks hadisə — delete yoxdur, yalnız compensation
- **At-least-once delivery**: Event-lər birdən çox dəfə işlənə bilər — idempotency vacibdir

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Event Sourcing mövzusunda əvvəlcə klassik CRUD ilə fərqini izah edin, sonra nə zaman uyğun olduğunu deyin. "Hər layihə üçün Event Sourcing" anti-pattern-dir — bunu bilmək vacibdir.

**Follow-up suallar:**
- "Snapshot nə vaxt lazımdır?"
- "Event versioning problemini necə həll edirsiniz?"
- "Event Sourcing olmayan sistemlə inteqrasiya necə?"
- "Eventual consistency-ni necə idarə edirsiniz?"
- "Event store olaraq hansı texnologiya istifadə edərdiniz?"

**Ümumi səhvlər:**
- Event-i Command ilə qarışdırmaq — "PlaceOrder" command, "OrderPlaced" event
- Hər sistem üçün Event Sourcing tövsiyə etmək — overkill ola bilər
- Snapshot olmadan çox böyük event stream-ləri replay etmək — performance problemi
- Event schema dəyişikliyini planlaşdırmamaq — migration çətin olacaq
- Idempotency-ni unutmaq — at-least-once delivery zamanı duplicate processing

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab event versioning strategiyasını, snapshot-ın nə vaxt lazım olduğunu, konkret texnologiya seçimini (EventStoreDB vs Kafka vs PostgreSQL) əsaslandıra bilir.

## Nümunələr

### Tipik Interview Sualı
"Event Sourcing nədir? CRUD sistemdən nə ilə fərqlənir? Nə zaman istifadə etmək lazımdır?"

### Güclü Cavab
"CRUD sistemdə son vəziyyəti saxlayırıq — order.status = 'shipped'. Event Sourcing-də bütün hadisələri saxlayırıq: OrderPlaced, PaymentReceived, ItemShipped. Cari vəziyyəti hesablamaq üçün bu hadisələri sıra ilə tətbiq edirik. Üstünlükləri: tam audit trail, istənilən anda keçmişdəki vəziyyəti bilmək, projection-lar vasitəsilə fərqli oxu modelləri qurmaq. Çatışmazlıqları: complexity, eventual consistency, event versioning problemi. Fintech, banking, compliance tələb edən sistemlər üçün uyğundur. Sadə CRUD tətbiqləri üçün overkill-dir."

### Kod / Konfiqurasiya Nümunəsi

```php
// Domain Events — immutable facts
abstract class DomainEvent
{
    public readonly \DateTimeImmutable $occurredAt;
    public readonly string $eventId;

    public function __construct(
        public readonly string $aggregateId
    ) {
        $this->eventId   = (string) \Ramsey\Uuid\Uuid::uuid4();
        $this->occurredAt = new \DateTimeImmutable();
    }
}

class OrderPlaced extends DomainEvent
{
    public function __construct(
        string $orderId,
        public readonly string $customerId,
        public readonly array $items,
        public readonly int $totalCents
    ) {
        parent::__construct($orderId);
    }
}

class OrderShipped extends DomainEvent
{
    public function __construct(
        string $orderId,
        public readonly string $trackingNumber
    ) {
        parent::__construct($orderId);
    }
}

class OrderCancelled extends DomainEvent
{
    public function __construct(
        string $orderId,
        public readonly string $reason
    ) {
        parent::__construct($orderId);
    }
}

// Aggregate — event-lərdən özünü quran
class Order
{
    private string $id;
    private string $customerId;
    private string $status = 'draft';
    private array $items   = [];
    private int $version   = 0;
    private array $pendingEvents = [];

    // Factory: yeni Order yaratmaq
    public static function place(string $customerId, array $items): self
    {
        $order = new self();
        $order->apply(new OrderPlaced(
            orderId: (string) \Ramsey\Uuid\Uuid::uuid4(),
            customerId: $customerId,
            items: $items,
            totalCents: array_sum(array_column($items, 'price_cents'))
        ));
        return $order;
    }

    // Factory: event store-dan yenidən qurmaq
    public static function reconstitute(array $events): self
    {
        $order = new self();
        foreach ($events as $event) {
            $order->apply($event, recording: false);
        }
        return $order;
    }

    public function ship(string $trackingNumber): void
    {
        if ($this->status !== 'paid') {
            throw new \DomainException("Cannot ship order in status: {$this->status}");
        }
        $this->apply(new OrderShipped($this->id, $trackingNumber));
    }

    public function cancel(string $reason): void
    {
        if (in_array($this->status, ['shipped', 'cancelled'])) {
            throw new \DomainException("Cannot cancel order in status: {$this->status}");
        }
        $this->apply(new OrderCancelled($this->id, $reason));
    }

    // Event tətbiq etmək — state mutation yalnız burada
    private function apply(DomainEvent $event, bool $recording = true): void
    {
        $this->when($event);
        $this->version++;

        if ($recording) {
            $this->pendingEvents[] = $event;
        }
    }

    // Event-ə görə state dəyişikliyi
    private function when(DomainEvent $event): void
    {
        match (true) {
            $event instanceof OrderPlaced    => $this->whenOrderPlaced($event),
            $event instanceof OrderShipped   => $this->whenOrderShipped($event),
            $event instanceof OrderCancelled => $this->whenOrderCancelled($event),
            default => throw new \InvalidArgumentException('Unknown event: ' . get_class($event))
        };
    }

    private function whenOrderPlaced(OrderPlaced $event): void
    {
        $this->id         = $event->aggregateId;
        $this->customerId = $event->customerId;
        $this->items      = $event->items;
        $this->status     = 'placed';
    }

    private function whenOrderShipped(OrderShipped $event): void
    {
        $this->status = 'shipped';
    }

    private function whenOrderCancelled(OrderCancelled $event): void
    {
        $this->status = 'cancelled';
    }

    public function pullPendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];
        return $events;
    }

    public function version(): int { return $this->version; }
    public function status(): string { return $this->status; }
}

// Event Store — append-only
interface EventStore
{
    public function append(string $aggregateId, array $events, int $expectedVersion): void;
    public function load(string $aggregateId, int $fromVersion = 0): array;
}

class PostgresEventStore implements EventStore
{
    public function append(string $aggregateId, array $events, int $expectedVersion): void
    {
        DB::transaction(function () use ($aggregateId, $events, $expectedVersion) {
            // Optimistic concurrency check
            $currentVersion = DB::table('event_store')
                ->where('aggregate_id', $aggregateId)
                ->max('version') ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw new \RuntimeException('Concurrency conflict');
            }

            foreach ($events as $i => $event) {
                DB::table('event_store')->insert([
                    'event_id'      => $event->eventId,
                    'aggregate_id'  => $aggregateId,
                    'event_type'    => get_class($event),
                    'payload'       => json_encode($event),
                    'version'       => $expectedVersion + $i + 1,
                    'occurred_at'   => $event->occurredAt->format('Y-m-d H:i:s.u'),
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
            ->toArray();
    }
}

// Projection — event-lərdən read model qurmaq
class OrderSummaryProjection
{
    public function handle(DomainEvent $event): void
    {
        match (true) {
            $event instanceof OrderPlaced    => $this->onOrderPlaced($event),
            $event instanceof OrderShipped   => $this->onOrderShipped($event),
            $event instanceof OrderCancelled => $this->onOrderCancelled($event),
            default => null // bu projection üçün vacib deyil
        };
    }

    private function onOrderPlaced(OrderPlaced $event): void
    {
        DB::table('order_summaries')->insert([
            'order_id'    => $event->aggregateId,
            'customer_id' => $event->customerId,
            'status'      => 'placed',
            'total_cents' => $event->totalCents,
            'placed_at'   => $event->occurredAt,
        ]);
    }

    private function onOrderShipped(OrderShipped $event): void
    {
        DB::table('order_summaries')
            ->where('order_id', $event->aggregateId)
            ->update(['status' => 'shipped', 'tracking_number' => $event->trackingNumber]);
    }
}
```

## Praktik Tapşırıqlar

- Bank hesabı modelini Event Sourcing ilə implement edin
- Snapshot mexanizmini əlavə edin — neçə event-dən sonra snapshot lazımdır?
- Event versioning: `OrderPlaced`-ə yeni sahə əlavə olunsa köhnə event-lər necə oxunar?
- Projection-u yenidən qurun — bütün event-ləri replay edin
- Idempotent event handler yazın

## Əlaqəli Mövzular

- `06-cqrs-architecture.md` — Event Sourcing + CQRS klassik cüt
- `11-saga-pattern.md` — Event-driven distributed transactions
- `02-domain-driven-design.md` — Domain Event-lərin mənbəyi
- `01-monolith-vs-microservices.md` — Event Sourcing microservices ilə
