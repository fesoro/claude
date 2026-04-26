# Microservices, Messaging və API Patterns (Lead)

## 1. Microservices arası kommunikasiya

### Synchronous — HTTP / gRPC
```php
// HTTP (REST) — ən sadə
$response = Http::timeout(5)
    ->retry(3, 100)
    ->withToken($serviceToken)
    ->get('http://user-service/api/users/123');

$user = $response->json();

// Circuit Breaker pattern — xarici servis down olanda
class CircuitBreaker {
    private int $failures = 0;
    private int $threshold = 5;
    private ?Carbon $openUntil = null;

    public function call(Closure $action): mixed {
        if ($this->isOpen()) {
            throw new ServiceUnavailableException('Circuit is open');
        }

        try {
            $result = $action();
            $this->failures = 0;
            return $result;
        } catch (Throwable $e) {
            $this->failures++;
            if ($this->failures >= $this->threshold) {
                $this->openUntil = now()->addSeconds(30);
            }
            throw $e;
        }
    }

    private function isOpen(): bool {
        return $this->openUntil && now()->lt($this->openUntil);
    }
}

// İstifadə
$breaker = app(CircuitBreaker::class);
$user = $breaker->call(fn () => Http::get('http://user-service/api/users/123')->json());
```

### Asynchronous — Message Queues
```
Service A ──publish──► Message Broker ──consume──► Service B
                      (RabbitMQ/Kafka)         ──► Service C
```

---

## 2. RabbitMQ ilə işləmək

```php
// composer require php-amqplib/php-amqplib

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Publisher
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('order_events', false, true, false, false);

$message = new AMQPMessage(json_encode([
    'event' => 'order.placed',
    'order_id' => 123,
    'timestamp' => now()->toISOString(),
]), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

$channel->basic_publish($message, '', 'order_events');

// Consumer
$channel->basic_consume('order_events', '', false, false, false, false,
    function (AMQPMessage $msg) {
        $data = json_decode($msg->getBody(), true);
        // Process event...
        $msg->ack(); // Acknowledge
    }
);

while ($channel->is_consuming()) {
    $channel->wait();
}

// Laravel-də: laravel-queue-rabbitmq package
// config/queue.php
'rabbitmq' => [
    'driver' => 'rabbitmq',
    'host' => env('RABBITMQ_HOST'),
    'port' => env('RABBITMQ_PORT', 5672),
    'queue' => 'default',
],
```

**Exchange types:**
- **Direct** — routing key ilə exact match
- **Fanout** — bütün queue-lara broadcast
- **Topic** — pattern matching (order.*, *.created)
- **Headers** — header-lara görə route

---

## 3. Event Sourcing və CQRS

### Event Sourcing
State saxlamaq əvəzinə event-lər saxlanır. Cari state event-lərdən hesablanır.

```php
// Events
class MoneyDeposited {
    public function __construct(
        public string $accountId,
        public float $amount,
        public Carbon $occurredAt,
    ) {}
}

class MoneyWithdrawn {
    public function __construct(
        public string $accountId,
        public float $amount,
        public Carbon $occurredAt,
    ) {}
}

// Event Store
// events table: id, aggregate_id, event_type, payload, created_at
class EventStore {
    public function append(string $aggregateId, object $event): void {
        DB::table('events')->insert([
            'aggregate_id' => $aggregateId,
            'event_type' => get_class($event),
            'payload' => json_encode($event),
            'created_at' => now(),
        ]);
    }

    public function getEvents(string $aggregateId): Collection {
        return DB::table('events')
            ->where('aggregate_id', $aggregateId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->deserialize($row));
    }
}

// Aggregate — event-lərdən state hesabla
class BankAccount {
    private float $balance = 0;

    public static function fromEvents(Collection $events): self {
        $account = new self();
        foreach ($events as $event) {
            $account->apply($event);
        }
        return $account;
    }

    private function apply(object $event): void {
        match(true) {
            $event instanceof MoneyDeposited => $this->balance += $event->amount,
            $event instanceof MoneyWithdrawn => $this->balance -= $event->amount,
        };
    }
}
```

### CQRS (Command Query Responsibility Segregation)
Write və Read modellərini ayırmaq.

```php
// Command (Write) side
class PlaceOrderCommand {
    public function __construct(
        public int $userId,
        public array $items,
        public string $paymentMethod,
    ) {}
}

class PlaceOrderHandler {
    public function handle(PlaceOrderCommand $command): void {
        // Validate, create order, dispatch events
        // Yalnız write DB-yə yazır
    }
}

// Query (Read) side
class GetOrdersQuery {
    public function __construct(
        public int $userId,
        public int $page = 1,
    ) {}
}

class GetOrdersHandler {
    public function handle(GetOrdersQuery $query): LengthAwarePaginator {
        // Read DB-dən oxu (denormalized, optimized for reads)
        return DB::connection('read')
            ->table('order_read_model')
            ->where('user_id', $query->userId)
            ->paginate(20, ['*'], 'page', $query->page);
    }
}
```

---

## 4. GraphQL vs REST

```php
// REST problemi — over-fetching / under-fetching
GET /api/users/1           → bütün user data (over-fetch)
GET /api/users/1/posts     → ayrı request (under-fetch)
GET /api/users/1/followers → daha bir request

// GraphQL — client lazım olanı seçir
// query {
//   user(id: 1) {
//     name
//     email
//     posts(limit: 5) { title }
//     followersCount
//   }
// }

// Laravel + Lighthouse (GraphQL)
// composer require nuwave/lighthouse

// schema.graphql
type Query {
    user(id: ID! @eq): User @find
    users(name: String @where(operator: "like")): [User!]! @paginate
}

type User {
    id: ID!
    name: String!
    email: String!
    posts: [Post!]! @hasMany
}

type Mutation {
    createUser(input: CreateUserInput! @spread): User! @create
    updateUser(id: ID!, input: UpdateUserInput! @spread): User! @update
}

// N+1 problem GraphQL-də — Dataloader ilə həll
// Lighthouse avtomatik batch edir @hasMany, @belongsTo

// Nə vaxt GraphQL?
// - Müxtəlif client-lər (mobile, web, third-party)
// - Complex, nested data
// - Client-ə data seçimi vermək istəyirsənsə

// Nə vaxt REST?
// - Sadə CRUD API
// - Caching (HTTP cache REST-də daha asandır)
// - File upload
// - Webhook-lar
```

---

## 5. Saga Pattern — distributed transactions

```php
// Microservices-də transaction yoxdur, Saga əvəzinə istifadə olunur
// Hər addımın compensating action-u var

class PlaceOrderSaga {
    private array $completedSteps = [];

    public function execute(OrderDTO $dto): void {
        try {
            // Step 1: Reserve inventory
            $this->inventoryService->reserve($dto->items);
            $this->completedSteps[] = 'inventory';

            // Step 2: Process payment
            $this->paymentService->charge($dto->total);
            $this->completedSteps[] = 'payment';

            // Step 3: Create shipment
            $this->shippingService->create($dto);
            $this->completedSteps[] = 'shipping';

        } catch (Throwable $e) {
            $this->compensate($dto);
            throw $e;
        }
    }

    private function compensate(OrderDTO $dto): void {
        // Əks sırada geri qaytar
        foreach (array_reverse($this->completedSteps) as $step) {
            match($step) {
                'shipping' => $this->shippingService->cancel($dto),
                'payment' => $this->paymentService->refund($dto->total),
                'inventory' => $this->inventoryService->release($dto->items),
            };
        }
    }
}
```

---

## 6. Idempotency — təkrar request-lərdə eyni nəticə

```php
// Problem: Payment 2 dəfə çəkilir (network retry, user double-click)

// Həll: Idempotency key
class ProcessPaymentController {
    public function __invoke(Request $request): JsonResponse {
        $idempotencyKey = $request->header('Idempotency-Key');

        // Əvvəlcə yoxla
        $existing = Cache::get("payment:idempotent:{$idempotencyKey}");
        if ($existing) {
            return response()->json($existing); // Eyni nəticəni qaytar
        }

        // İlk dəfə icra et
        $result = $this->paymentService->process($request->validated());

        // Nəticəni cache-lə
        Cache::put("payment:idempotent:{$idempotencyKey}", $result, 86400);

        return response()->json($result, 201);
    }
}

// Client:
// POST /api/payments
// Idempotency-Key: unique-uuid-here
```

---

## 7. Webhook-lar necə düzgün implement olunur?

```php
// Webhook qəbul edən (receiver)
class StripeWebhookController {
    public function handle(Request $request): Response {
        // 1. İmzanı yoxla
        $signature = $request->header('Stripe-Signature');
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            return response('Invalid signature', 400);
        }

        // 2. Idempotent — eyni event-i təkrar emal etmə
        if (WebhookLog::where('event_id', $event->id)->exists()) {
            return response('Already processed', 200);
        }

        // 3. Async emal et (queue-ya göndər)
        ProcessStripeWebhook::dispatch($event);

        // 4. Dərhal 200 qaytar (timeout-dan qaçmaq üçün)
        WebhookLog::create(['event_id' => $event->id, 'type' => $event->type]);
        return response('OK', 200);
    }
}

// Webhook göndərən (sender)
class WebhookDispatcher {
    public function send(string $url, array $payload): void {
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        $response = Http::timeout(5)
            ->retry(3, 1000, throw: false)  // 3 retry, 1 san interval
            ->withHeaders(['X-Signature' => $signature])
            ->post($url, $payload);

        if ($response->failed()) {
            // Exponential backoff ilə retry queue-ya göndər
            RetryWebhook::dispatch($url, $payload)
                ->delay(now()->addMinutes(5));
        }
    }
}
```
