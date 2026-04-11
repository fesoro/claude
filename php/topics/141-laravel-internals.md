# Laravel Internals

## Mündəricat
1. [Service Container](#service-container)
2. [Service Providers](#service-providers)
3. [Middleware Pipeline](#middleware-pipeline)
4. [Eloquent ORM İnternals](#eloquent-orm-internals)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Service Container

```
Laravel IoC Container — xidmət idarəetməsi.

Binding növləri:
  bind:       Hər dəfə yeni instance
  singleton:  Bir instance (lifecycle boyu)
  instance:   Artıq yaranan instance qeydiyyat
  scoped:     Request scope-da singleton

Resolution:
  app(OrderService::class) → Container resolve edir
  Type-hint → avtomatik resolve (autowiring)
  
Contextual Binding:
  Eyni Interface-in fərqli implementasiyası
  OrderController → MySQLOrderRepository
  ReportController → ReadOnlyOrderRepository
```

---

## Service Providers

```
Service Provider — Laravel bootstrap prosesi.

İki metod:
  register(): Binding-ləri qeydiyyat et (container-a əlavə et)
  boot():     Digər service-lərə bağlı işlər (route, event, view)

Yükləmə ardıcıllığı:
  1. config/app.php-dəki bütün providers yüklənir
  2. Hamısının register() çağırılır
  3. Hamısının boot() çağırılır

Deferred Provider:
  Lazım olana qədər yüklənmir (performance)
  $defer = true;
  provides(): hansı binding-ləri təmin edir

Package Development:
  Composer "extra.laravel.providers" → avtomatik qeydiyyat
```

---

## Middleware Pipeline

```
Laravel Middleware — onion (soğan) arxitekturası:

Request → [Middleware1 before] → [Middleware2 before]
  → Controller Action
  → [Middleware2 after] → [Middleware1 after] → Response

Pipeline implementasiyası:
  İlham: array_reduce + closure chain
  
  pipe(request, [Auth, Throttle, Cors]):
    fn() → Cors(fn() → Throttle(fn() → Auth(fn() → Controller)))

Növlər:
  Global middleware: hər sorğuya
  Route middleware: müəyyən route-lara
  Middleware groups: web, api qrupları
  
Terminable Middleware:
  Response göndərildikdən SONRA işləyir
  Session flush, response logging üçün ideal
  terminate(Request, Response)
```

---

## Eloquent ORM İnternals

```
Active Record pattern:
  Model = həm data həm davranış
  User::find(1) → DB-dən yükləyir
  $user->save() → DB-ə yazır
  
  DDD ilə ziddiyyət:
  Domain logic model-ə keçir (Anemic Model riski)

Query Builder:
  Eloquent → QueryBuilder → PDO
  Lazy vs Eager Loading

N+1 Problem:
  $orders = Order::all(); // 1 sorğu
  foreach ($orders as $order) {
    $order->customer->name; // N sorğu!
  }
  
  Həll: with('customer') — Eager Loading
  $orders = Order::with('customer')->get();

Events & Observers:
  creating, created, updating, updated, deleting, deleted
  Observer sinfi → Model event-lərini dinlər
  
  Diqqət: Observer-lər Domain Event deyil!
  Observer → Infrastructure concern
  Domain Event → Domain logic üçün

Scopes:
  Local scope: query filter-ləri reusable
  Global scope: hər sorğuya avtomatik tətbiq (soft delete)
```

---

## PHP İmplementasiyası

```php
<?php
// 1. Service Provider
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    // Deferred loading — yalnız lazım olduqda
    public bool $defer = true;

    public function register(): void
    {
        // Binding — yeni instance hər dəfə
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return new StripeGateway(
                apiKey: config('services.stripe.key'),
                mode:   config('services.stripe.mode'),
            );
        });

        // Singleton — bir dəfə yaradılır
        $this->app->singleton(PaymentLogger::class, function ($app) {
            return new PaymentLogger($app->make(LoggerInterface::class));
        });
    }

    public function boot(): void
    {
        // Digər service-lər artıq mövcuddur
        // Route, view, event qeydiyyat buraya
        $this->loadRoutesFrom(__DIR__.'/../routes/payment.php');
    }

    public function provides(): array
    {
        return [PaymentGatewayInterface::class, PaymentLogger::class];
    }
}
```

```php
<?php
// 2. Contextual Binding
// AppServiceProvider::register()-da:

$this->app->when(OrderController::class)
    ->needs(OrderRepositoryInterface::class)
    ->give(MySQLOrderRepository::class);

$this->app->when(ReportController::class)
    ->needs(OrderRepositoryInterface::class)
    ->give(ReadReplicaOrderRepository::class);
```

```php
<?php
// 3. Custom Pipeline (Middleware kimi)
use Illuminate\Pipeline\Pipeline;

class OrderProcessor
{
    public function __construct(private Pipeline $pipeline) {}

    public function process(Order $order): Order
    {
        return $this->pipeline
            ->send($order)
            ->through([
                ValidateOrderPipe::class,
                ApplyDiscountPipe::class,
                CalculateTaxPipe::class,
                ReserveInventoryPipe::class,
            ])
            ->thenReturn();
    }
}

class ApplyDiscountPipe
{
    public function handle(Order $order, \Closure $next): Order
    {
        if ($order->getTotal()->greaterThan(Money::of(100, 'USD'))) {
            $order->applyDiscount(0.1); // 10% endirim
        }
        return $next($order);
    }
}
```

```php
<?php
// 4. Eloquent ilə DDD — Repository pattern
// Eloquent-i Domain-dən gizlət

interface OrderRepository
{
    public function findById(string $id): ?Order;
    public function save(Order $order): void;
}

class EloquentOrderRepository implements OrderRepository
{
    public function findById(string $id): ?Order
    {
        $model = OrderModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function save(Order $order): void
    {
        OrderModel::updateOrCreate(
            ['id' => $order->getId()],
            $this->toModel($order),
        );
    }

    private function toDomain(OrderModel $model): Order
    {
        return Order::reconstitute(
            id:         $model->id,
            customerId: $model->customer_id,
            status:     OrderStatus::from($model->status),
            items:      $model->items->map(fn($i) => new OrderItem(...))->toArray(),
        );
    }

    private function toModel(Order $order): array
    {
        return [
            'customer_id' => $order->getCustomerId(),
            'status'      => $order->getStatus()->value,
            'total'       => $order->getTotal()->getAmount(),
        ];
    }
}
```

---

## İntervyu Sualları

- Laravel Service Container Symfony Container-dan nə ilə fərqlənir?
- Service Provider-ın `register` və `boot` metodu nə zaman çağırılır?
- Middleware "onion" arxitekturası necə işləyir?
- Eloquent Observer Laravel Event-indən nə ilə fərqlənir?
- N+1 problemi nədir? Eloquent-də həll yolu?
- Deferred Service Provider nəyə lazımdır?
