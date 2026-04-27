# CQRS Architecture Deep Dive (Lead ⭐⭐⭐⭐)

## İcmal
CQRS (Command Query Responsibility Segregation) Greg Young tərəfindən populyarlaşdırılmış, oxu (query) və yazı (command) əməliyyatlarını ayrı model, ayrı stack ilə idarə edən arxitektura yanaşmasıdır. Bertrand Meyer-in CQS prinsipinin arxitektura səviyyəsinə qaldırılmış versiyasıdır. Interview-da Lead səviyyəli mövqelər üçün çıxır — yüklü sistemlərdə scalability strategiyasını ölçür.

## Niyə Vacibdir
Böyük sistemlərdə oxu yükü yazı yükündən 10-100x çox ola bilər. CQRS hər birini müstəqil scale etmək imkanı verir. Write side mürəkkəb domain logic-i dəstəkləyir, Read side oxu üçün optimize edilmiş sadə query-lər istifadə edir. Event Sourcing ilə birlikdə ən güclü formada fəaliyyət göstərir. Bu pattern-i başa düşmək böyük sistemlər üçün arxitektura qərarlarını izah edə biləcəyinizi göstərir.

## Əsas Anlayışlar

- **Command**: Sistemin vəziyyətini dəyişdirən əmr — `PlaceOrderCommand`, `CancelOrderCommand`. Cavab qaytarmır (və ya yalnız ID qaytarır)
- **Query**: Sistemin vəziyyətini oxuyan sorğu — `GetOrderQuery`. Heç nə dəyişdirmir
- **Command Handler**: Command-ı alan, domain logic-i çağıran, event publish edən komponent
- **Query Handler**: Query-ni alan, read model-dən data döndərən komponent
- **Write Model**: Domain logic-i qoruyan, invariant-ları saxlayan model — Aggregate, Entity
- **Read Model / Projection**: Query-lər üçün optimize edilmiş, denormalized data strukturu
- **CQS vs CQRS**: CQS metod səviyyəsindədir (method ya dəyişdirir ya oxuyur), CQRS arxitektura səviyyəsindədir
- **Eventual consistency**: Write model yenilənəndən sonra read model gecikmə ilə yenilənə bilər
- **Separate databases**: Extreme CQRS — write PostgreSQL, read Elasticsearch/Redis/MongoDB
- **Command Bus**: Command-ları Handler-lara yönləndirən dispatcher — middleware əlavə etmək asandır
- **Query Bus**: Query-ləri Handler-lara yönləndirən dispatcher
- **Synchronous CQRS**: Read model write zamanı eyni transaksiyada yenilənir — consistency qorunur, amma ayrılıq azalır
- **Asynchronous CQRS**: Event-lər vasitəsilə read model yenilənir — eventual consistency, lakin tam ayrılıq
- **Idempotency**: Command-lar idempotent olmalıdır — eyni command iki dəfə göndərilsə eyni nəticə

## Praktik Baxış

**Interview-da necə yanaşmaq:**
CQRS mövzusunda ən yaxşı yanaşma: "tam CQRS" (ayrı DB-lər) vs "CQRS-lite" (eyni DB, ayrı model) fərqini izah etmək. Hər layihəyə tam CQRS tətbiq etmək overkill-dir — bunu bilmək vacibdir.

**Follow-up suallar:**
- "Read model nə vaxt stale ola bilər? Bunu necə idarə edirsiniz?"
- "Command idempotency-ni necə həll edirsiniz?"
- "CQRS olmadan bu problemi həll etmək mümkün olardımı?"
- "Event Sourcing CQRS-siz işləyə bilərmi?"

**Ümumi səhvlər:**
- CQRS = Event Sourcing deməkdir — bunlar müstəqil pattern-lərdir, birlikdə uyğun amma ayrı-ayrı da işləyir
- Hər layihəyə CQRS tətbiq etmək — simple CRUD üçün overkill
- Eventual consistency-ni ignorar etmək — "məlumat köhnədir" istifadəçiyə göstərilə bilər
- Command cavab qaytarmamalıdır — lakin `commandId` və ya `aggregateId` qaytarmaq məntiqlidir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab konkret trade-off-ları bilir: eventual consistency-nin UI-da necə idarə olunduğunu, projection yenilənməsinə qədər istifadəçiyə nə göstəriləcəyini izah edə bilir.

## Nümunələr

### Tipik Interview Sualı
"CQRS nədir, nə vaxt istifadə etmək lazımdır? Event Sourcing ilə fərqi nədir?"

### Güclü Cavab
"CQRS yazı və oxu modellərini ayırır. Yazı tərəfini Command, oxu tərəfini Query idarə edir. Bu ayrılıq hər birini müstəqil scale etməyə imkan verir. E-commerce-də product catalog-u Elasticsearch-də saxlaya bilərsiniz — oxu sürətli olur. Yazı tərəfi domain logic-i qoruyur, event publish edir. Event Sourcing CQRS-in write tərəfini event-lərlə implement edir — amma bunlar müstəqil pattern-lərdir. CQRS-i yüksək oxu yükü olan, domain-i mürəkkəb olan sistemlər üçün tövsiyə edərdim. Simple blog layihəsi üçün overkill-dir."

### Kod / Konfiqurasiya Nümunəsi

```php
// ============================================================
// COMMAND SIDE (Write Model)
// ============================================================

// Command — değişiklik niyyəti
final class PlaceOrderCommand
{
    public function __construct(
        public readonly string $commandId,   // idempotency üçün
        public readonly string $customerId,
        public readonly array $items
    ) {}
}

// Command Handler
class PlaceOrderCommandHandler
{
    public function __construct(
        private OrderRepository $orders,
        private EventBus $eventBus,
        private IdempotencyStore $idempotency
    ) {}

    public function handle(PlaceOrderCommand $command): string // orderId
    {
        // Idempotency check — eyni command ikinci dəfə gəlsə
        if ($existing = $this->idempotency->find($command->commandId)) {
            return $existing['order_id'];
        }

        $order = Order::place(
            CustomerId::from($command->customerId),
            $this->buildItems($command->items)
        );

        $this->orders->save($order);

        foreach ($order->pullDomainEvents() as $event) {
            $this->eventBus->publish($event);
        }

        $orderId = (string) $order->id();
        $this->idempotency->store($command->commandId, ['order_id' => $orderId]);

        return $orderId;
    }
}

// Command Bus
class CommandBus
{
    private array $handlers = [];
    private array $middleware = [];

    public function register(string $command, callable $handler): void
    {
        $this->handlers[$command] = $handler;
    }

    public function dispatch(object $command): mixed
    {
        $handler = $this->handlers[get_class($command)]
            ?? throw new \RuntimeException('No handler for ' . get_class($command));

        // Middleware chain (logging, validation, transaction)
        return $this->runThroughMiddleware($command, $handler);
    }
}

// ============================================================
// QUERY SIDE (Read Model)
// ============================================================

// Query — sadə oxu sorğusu
final class GetOrderDetailsQuery
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $requestingUserId
    ) {}
}

// Read Model — denormalized, query üçün optimize
final class OrderDetailsReadModel
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $status,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly array $items,    // product adı ilə birlikdə
        public readonly int $totalCents,
        public readonly ?string $trackingNumber,
        public readonly \DateTimeImmutable $placedAt
    ) {}
}

// Query Handler — birbaşa DB sorğusu, domain logic yoxdur
class GetOrderDetailsQueryHandler
{
    public function handle(GetOrderDetailsQuery $query): OrderDetailsReadModel
    {
        // Denormalized read model-dən birbaşa oxumaq
        $row = DB::table('order_read_models')
            ->join('users', 'order_read_models.customer_id', '=', 'users.id')
            ->where('order_read_models.order_id', $query->orderId)
            ->where('order_read_models.customer_id', $query->requestingUserId)
            ->select([
                'order_read_models.*',
                'users.name as customer_name',
                'users.email as customer_email'
            ])
            ->first();

        if (!$row) {
            throw new \DomainException('Order not found');
        }

        return new OrderDetailsReadModel(
            orderId: $row->order_id,
            status: $row->status,
            customerName: $row->customer_name,
            customerEmail: $row->customer_email,
            items: json_decode($row->items_json, true),
            totalCents: $row->total_cents,
            trackingNumber: $row->tracking_number,
            placedAt: new \DateTimeImmutable($row->placed_at)
        );
    }
}

// ============================================================
// PROJECTION — Event-lərdən Read Model qurmaq
// ============================================================

class OrderReadModelProjection
{
    // Domain Event gəldikdə read model-i yenilə
    public function onOrderPlaced(OrderPlaced $event): void
    {
        DB::table('order_read_models')->insert([
            'order_id'    => $event->aggregateId,
            'customer_id' => $event->customerId,
            'status'      => 'placed',
            'items_json'  => json_encode($event->items),
            'total_cents' => $event->totalCents,
            'placed_at'   => $event->occurredAt,
        ]);
    }

    public function onOrderShipped(OrderShipped $event): void
    {
        DB::table('order_read_models')
            ->where('order_id', $event->aggregateId)
            ->update([
                'status'          => 'shipped',
                'tracking_number' => $event->trackingNumber,
            ]);
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        DB::table('order_read_models')
            ->where('order_id', $event->aggregateId)
            ->update(['status' => 'cancelled']);
    }
}

// ============================================================
// CONTROLLER — Command/Query Bus istifadəsi
// ============================================================

class OrderController extends Controller
{
    public function __construct(
        private CommandBus $commands,
        private QueryBus $queries
    ) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $orderId = $this->commands->dispatch(new PlaceOrderCommand(
            commandId: $request->header('Idempotency-Key', (string) Str::uuid()),
            customerId: $request->user()->id,
            items: $request->validated('items')
        ));

        return response()->json(['order_id' => $orderId], 201);
    }

    public function show(string $orderId, Request $request): JsonResponse
    {
        $order = $this->queries->dispatch(new GetOrderDetailsQuery(
            orderId: $orderId,
            requestingUserId: $request->user()->id
        ));

        return response()->json($order);
    }
}
```

**Separate Read/Write Databases (Extreme CQRS):**
```yaml
# docker-compose.yml
services:
  postgres-write:
    image: postgres:16
    # Write model: Orders, domain aggregates

  elasticsearch-read:
    image: elasticsearch:8.12.0
    # Read model: Full-text search, complex aggregations

  redis-read:
    image: redis:7-alpine
    # Read model: Hot data cache, dashboard counts

  kafka:
    image: confluentinc/cp-kafka:latest
    # Event bus: Write → Read model synchronization
```

## Praktik Tapşırıqlar

- Command Bus ilə Query Bus implementasiyasını yazın
- Idempotency-Key header-i ilə duplicate command-ları detect edin
- Projection-u async event handler kimi implement edin
- "Stale read model" problemi: istifadəçiyə göstərilən datanın köhnə olduğunu necə bildirərdiniz?
- Simple CRUD-u CQRS-ə refactor edin — nə qazandınız, nə itirdiniz?

## Əlaqəli Mövzular

- `05-event-sourcing.md` — CQRS + Event Sourcing kombinasiyası
- `11-saga-pattern.md` — CQRS ilə distributed transactions
- `02-domain-driven-design.md` — Command = Use Case
- `03-clean-architecture.md` — Application Service = Command Handler
