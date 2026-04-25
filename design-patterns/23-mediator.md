# Mediator (Senior ⭐⭐⭐)

## İcmal
Mediator pattern, komponentlər arasındakı birbaşa əlaqəni aradan qaldırır — hər şey mərkəzi mediator vasitəsilə koordinasiya olunur. Bu, many-to-many dependency-ni one-to-many-ə çevirir: komponentlər bir-birini tanımır, yalnız mediator-ı tanıyır.

## Niyə Vacibdir
Laravel layihələrindəki mürəkkəb business process-lər çox zaman bir neçə service-i koordinasiya tələb edir: order place olduqda InventoryService, PaymentService, NotificationService, FulfillmentService işləməlidir. Bu service-lər bir-birini birbaşa çağırsa, dəyişiklik hər tərəfə yayılır. Laravel-in EventDispatcher və Command Bus sistemləri mediator pattern üzərinde qurulmuşdur.

## Əsas Anlayışlar
- **Mediator**: koordinasiya məntiqini bilir; komponentlər mediator-a mesaj göndərir, mediator kimi react edəcəyini qərar verir
- **Colleague**: mediator-la işləyən komponent; digər colleague-ları tanımır
- **Event-driven Mediator**: komponentlər event fire edir, mediator subscriber-ları koordinasiya edir
- **Command Bus**: `CommandBus.dispatch(command)` — command-ı uyğun handler-ə yönləndirir; handler mediator rolunu oynayır
- **Many-to-many → One-to-many**: N komponent bir-birini tanısa N*(N-1) əlaqə; mediator ilə N əlaqə

## Praktik Baxış
- **Real istifadə**: order processing (inventory + payment + notification koordinasiyası), chat room (user-lər otağa mesaj göndərir, otaq paylaşır), form wizard (bütün field-lər form mediator-ı vasitəsilə əlaqəli), event-driven systems, CQRS command bus
- **Trade-off-lar**: komponentlər decoupled olur, test etmək asanlaşır; lakin mediator "God Object" ola bilər — bütün coordination logic bir yerdə toplanır; kompleks iş axınlarında mediator-ın debug edilməsi çətin olur
- **İstifadə etməmək**: 2-3 sadə komponent arasında koordinasiya üçün; mediator olmadan sadə direct call kifayətdirsə
- **Common mistakes**: mediator-a business logic yerləşdirmək (o, yalnız koordinasiya etməlidir); mediator-ı bütün application üçün single point-of-failure etmək; Logger/Auth kimi cross-cutting concern-ləri mediator ilə idarə etməyə çalışmaq

## Nümunələr

### Ümumi Nümunə
Hava nəqliyyatı: təyyarələr bir-biri ilə birbaşa danışmır. Hamı dispetçer qülləsi (mediator) ilə danışır. Qüllə haradan enib-qalxacağını, hansı qaydada növbə gözlənəcəyini koordinasiya edir. Bir yeni təyyarə əlavə olduqda yalnız qüllə ilə kommunikasiya qaydası öyrənmək kifayətdir.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Mediators;

// Mediator interface
interface OrderProcessingMediator
{
    public function orderCreated(Order $order): void;
    public function paymentCompleted(Order $order, Payment $payment): void;
    public function paymentFailed(Order $order, string $reason): void;
    public function inventoryReserved(Order $order): void;
}

// Colleagues — bir-birini tanımır, yalnız mediator-ı tanıyır
class InventoryService
{
    public function __construct(private OrderProcessingMediator $mediator) {}

    public function reserve(Order $order): void
    {
        foreach ($order->items as $item) {
            // inventory azalt
            $item->product->decrement('stock', $item->quantity);
        }
        // mediator-a xəbər ver; mediator nə edəcəyini bilir
        $this->mediator->inventoryReserved($order);
    }
}

class PaymentService
{
    public function __construct(private OrderProcessingMediator $mediator) {}

    public function charge(Order $order, PaymentMethod $method): void
    {
        try {
            $payment = $this->processCharge($order->total, $method);
            $this->mediator->paymentCompleted($order, $payment);
        } catch (PaymentException $e) {
            $this->mediator->paymentFailed($order, $e->getMessage());
        }
    }

    private function processCharge(Money $amount, PaymentMethod $method): Payment
    {
        // Stripe/PayPal integration
        return new Payment(/* ... */);
    }
}

class FulfillmentService
{
    public function startFulfillment(Order $order): void
    {
        // warehouse system-ə göndər
        $order->update(['fulfillment_status' => 'pending']);
    }
}

class NotificationService
{
    public function notifyOrderConfirmed(Order $order): void
    {
        $order->customer->notify(new OrderConfirmedNotification($order));
    }

    public function notifyPaymentFailed(Order $order, string $reason): void
    {
        $order->customer->notify(new PaymentFailedNotification($order, $reason));
    }
}

// Concrete Mediator — koordinasiya məntiqini bilir
class ConcreteOrderProcessingMediator implements OrderProcessingMediator
{
    public function __construct(
        private InventoryService    $inventory,
        private PaymentService      $payment,
        private FulfillmentService  $fulfillment,
        private NotificationService $notifications,
    ) {}

    public function orderCreated(Order $order): void
    {
        // Step 1: inventory-ni reserve et
        $this->inventory->reserve($order);
        // inventoryReserved callback-i payment başladacaq
    }

    public function inventoryReserved(Order $order): void
    {
        // Step 2: ödəniş al
        $this->payment->charge($order, $order->paymentMethod);
    }

    public function paymentCompleted(Order $order, Payment $payment): void
    {
        // Step 3: order-i confirm et, fulfillment başlat, bildiriş göndər
        $order->update(['status' => 'confirmed', 'payment_id' => $payment->id]);
        $this->fulfillment->startFulfillment($order);
        $this->notifications->notifyOrderConfirmed($order);
    }

    public function paymentFailed(Order $order, string $reason): void
    {
        // Rollback: inventory-ni geri qaytar, müştəriyə xəbər ver
        $order->update(['status' => 'payment_failed']);
        // inventory release burada və ya ayrı release flow ilə
        $this->notifications->notifyPaymentFailed($order, $reason);
    }
}
```

**Laravel Event System — built-in Mediator:**

```php
<?php

// Laravel EventDispatcher mediator rolunu oynayır.
// Komponentlər bir-birini bilmir — event fire edir, listener-lar dinləyir.

// Event (colleague-lardan biri)
class OrderPlaced
{
    public function __construct(public readonly Order $order) {}
}

// Listener 1 — InventoryService bundan xəbərsizdir
class ReserveInventoryListener
{
    public function handle(OrderPlaced $event): void
    {
        // inventory reserve et
    }
}

// Listener 2
class SendOrderConfirmationListener
{
    public function handle(OrderPlaced $event): void
    {
        // email göndər
    }
}

// EventServiceProvider — mediator koordinasiya (mapping) məntiqini bilir
protected $listen = [
    OrderPlaced::class => [
        ReserveInventoryListener::class,
        SendOrderConfirmationListener::class,
        StartFulfillmentListener::class,
    ],
];

// Order service — sadəcə event fire edir, kim dinləyir bilmir
class OrderService
{
    public function placeOrder(Cart $cart, User $user): Order
    {
        $order = Order::create([/* ... */]);
        event(new OrderPlaced($order));  // mediator handle edir
        return $order;
    }
}
```

**Command Bus — mediator kimi:**

```php
<?php

// Laravel'da manual Command Bus (ya da hirethunk/laravel-cqrs)
class CommandBus
{
    private array $handlers = [];

    public function register(string $command, callable $handler): void
    {
        $this->handlers[$command] = $handler;
    }

    // Mediator: command-ı uyğun handler-ə yönləndirir
    public function dispatch(object $command): mixed
    {
        $handler = $this->handlers[$command::class]
            ?? throw new \RuntimeException("No handler for " . $command::class);

        return $handler($command);
    }
}
```

## Praktik Tapşırıqlar
1. Mövcud bir Laravel service-i götürün — başqa service-ləri birbaşa inject edib çağırır. Bunları event-lərə çevirin; `OrderPlaced`, `UserRegistered` kimi events yaradın, logic-i listener-lara köçürün
2. Sadə `CommandBus` class-ı yazın: `register(CommandClass, handler)` + `dispatch(command)` — 2-3 handler qeyd edin, test edin
3. Chat room mediator-ı modelləyin: `ChatRoom` mediator, `User` colleague-lar; user mesaj göndərəndə digər user-lər `ChatRoom` vasitəsilə alır; PHP'da test edin
4. Laravel `EventServiceProvider`-in mövcud listener-larını nəzərdən keçirin — hansı listenerlar digər event-lər fire edir? Bu event chain-i mediator coordination nümunəsidir

## Əlaqəli Mövzular
- [Observer](06-observer.md) — fərq: Observer event subscription (publisher bilmir), Mediator centralized coordinator (iş axınını bilir)
- [Command](11-command.md) — Command Bus mediator + command pattern birləşməsidir
- [Facade](02-facade.md) — facade da birdən çox subsystem-i koordinasiya edir, lakin unidirectional-dır
- [Pipeline](21-pipeline.md) — ardıcıl steps üçün mediator alternativi
