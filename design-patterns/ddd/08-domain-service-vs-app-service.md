# Domain Service vs Application Service (Senior ⭐⭐⭐)

## İcmal

DDD-nin ən çox qarışdırılan ayrımlarından biridir. Hər ikisi "service" adlanır, lakin tamamilə fərqli məqsədlərə xidmət edir:

- **Domain Service** — domain layer-da yaşayır. Business logic ifadə edir. Infrastructure bilmir. Domain object-lərlə işləyir.
- **Application Service** — application layer-da yaşayır. Use case-i orkestrasiya edir. Domain service + repository + event + transaction koordinasiya edir.

```
Sual: "Bu kod domain məntiqi içərir, yoxsa use case koordinasiyasıdır?"
  Domain məntiqi → Domain Service
  Koordinasiya   → Application Service
```

## Niyə Vacibdir

Yanlış yerə yazılmış kod gec-tez problem olur:
- Business logic Application Service-ə düşərsə — test çətinləşir, domain rule-lar hər use case-ə yenidən yazılır
- Repository Domain Service-ə inject olunursa — domain artıq pure deyil, infrastructure-a asılıdır
- Entity-nin öz məntiqini service-ə çıxarmaq — anemic domain model, DDD-nin əsas anti-pattern-i

## Əsas Anlayışlar

**Domain Service:**
```
✓ Stateless (state saxlamır)
✓ Domain object-lər qəbul edir, qaytarır
✓ Infrastructure yoxdur (DB, email, HTTP yoxdur)
✓ Domain language ilə adlandırılır
✗ Repository inject edilmir
✗ Event publish etmir
✗ Transaction idarə etmir

Nümunələr:
  MoneyTransferPolicy       → Transfer qaydaları (eyni valyuta şərti)
  PricingService            → Qiymət hesablaması (endirim + vergi)
  OrderEligibilityChecker   → Sifariş icazəsi (user tier, order limit)
  TaxCalculationService     → Vergi hesabı (ölkəyə görə fərqli qayda)
```

**Application Service:**
```
✓ Use case-i orkestrasiya edir
✓ Repository-dən entity yükləyir
✓ Domain service-i çağırır
✓ Transaction idarəsi
✓ Event publish edir
✓ DTO-ları domain object-lərə çevirir
✗ Biznes məntiqi YOX (yalnız koordinasiya)

Nümunələr:
  TransferMoneyHandler      → "Transfer et" use case
  PlaceOrderHandler         → "Sifariş ver" use case
  ActivateUserHandler       → "User aktivləşdir" use case
```

## Praktik Baxış

**Real istifadə:**
- `PricingPolicy` domain service-i: `Customer` + `Order` alır, discounted total qaytarır — infrastructure yoxdur
- `PlaceOrderHandler` application service-i: repository-dən order yüklər, domain service çağırır, event publish edir, transaction saxlayır

**Trade-off-lar:**
- Ayrım düzgün olduqda domain layer test-able, infrastructure-agnostic olur
- Lakin: nəyin hara getdiyini anlamaq başlanğıcda çətindir; "çox service" hissi yarana bilər

**İstifadə etməmək:**
- Sadə CRUD-da Domain Service gerekmeyə bilər — Eloquent model + Application Service kifayətdir
- Yalnız bir entity-nin öz metodunda icra edilə bilən logic üçün Domain Service yaratmaq overkill

**Common mistakes:**
- Business logic Application Service-ə qoymaq — anemic domain
- Domain Service-ə Repository inject etmək — domain infrastructure-a asılı olur
- Entity-nin öz metodunu Domain Service-ə çıxarmaq (`order.calculateTotal()` entity-nindir, service deyil)
- Application Service-i çox detallı yazmaq — "orchestrator" sadə olmalıdır

**Anti-Pattern Nə Zaman Olur?**

- **Business logic-i App Service-ə qoymaq (Anemic Domain)** — `if ($from->currency !== $to->currency) throw ...` Application Service içindədir. Bu business rule domain-ə aiddir. Application Service yalnız koordinasiya edir: "kimləri yüklə, nəyi çağır, nəyi persist et" — "bu transferin qaydaları nədir" sorusuna cavab vermir.
- **Domain Service-i HTTP/persistence-ə bağlamaq** — `PricingService` daxilindən `ProductRepository::find()` çağırmaq, HTTP client istifadə etmək. Domain Service pure domain logic-dir: yalnız domain object-lər qəbul edir, domain object-lər qaytarır. Repository Application Service inject edir, Domain Service-ə keçir.
- **Hər şeyi service etmək (Anemic Domain)** — `Order::calculateTotal()` entity-nin öz metodudur. `OrderTotalCalculatorService` yaratmaq entity-ni məntiqsiz data bag-a çevirir. Entity öz invariant-larını, hesablamalarını, qaydalarını özü saxlayır.
- **Domain Service-i stateful etmək** — `$pricingService->setDiscount(10)` — state saxlamaq Domain Service-in əsas xüsusiyyətini pozur. Domain Service stateless-dir: eyni input → eyni output. State-i parametr kimi ötürün.

## Nümunələr

### Ümumi Nümunə

Bank transfer prosesini düşünün:
- **"Transfer yalnız eyni valyutada ola bilər"** — bu domain qaydası. `MoneyTransferPolicy` domain service-idir.
- **"User X hesabından Y hesabına 100 AZN transfer etmək istəyir"** — bu use case. `TransferMoneyHandler` application service-dir.

### PHP/Laravel Nümunəsi

**Domain Service — pure business logic:**

```php
<?php
// Domain Service — infrastructure yoxdur, repository yoxdur
namespace App\Domain\Banking\Service;

class MoneyTransferPolicy
{
    /**
     * Transfer mümkündürmü? Domain qaydaları yoxlayır.
     * Stateless: eyni input → eyni output
     */
    public function validate(Account $from, Account $to, Money $amount): void
    {
        if (!$from->getCurrency()->equals($to->getCurrency())) {
            throw new CurrencyMismatchException(
                "Transfer yalnız eyni valyutada mümkündür. " .
                "From: {$from->getCurrency()}, To: {$to->getCurrency()}"
            );
        }

        if ($from->getBalance()->lessThan($amount)) {
            throw new InsufficientFundsException(
                "Balans kifayət etmir: {$from->getBalance()} < {$amount}"
            );
        }

        if ($amount->isNegativeOrZero()) {
            throw new InvalidAmountException("Məbləğ müsbət olmalıdır");
        }

        if ($from->isFrozen() || $to->isFrozen()) {
            throw new AccountFrozenException("Hesab bloklanmışdır");
        }

        if ($from->getId()->equals($to->getId())) {
            throw new SameAccountTransferException("Eyni hesaba transfer olmaz");
        }
    }
}

// Domain Service — birdən çox aggregate, complex pricing
class OrderPricingService
{
    /**
     * Customer tier-ə görə discounted total hesabla.
     * Repository inject edilmir — domain object-lər parametr kimi gəlir.
     */
    public function calculateDiscountedTotal(Order $order, Customer $customer): Money
    {
        $total = $order->calculateTotal();

        $discount = match($customer->getTier()) {
            CustomerTier::BRONZE  => 0,
            CustomerTier::SILVER  => 5,
            CustomerTier::GOLD    => 10,
            CustomerTier::PREMIUM => 15,
        };

        if ($discount === 0) {
            return $total;
        }

        $discountAmount = $total->percentage($discount);
        return $total->subtract($discountAmount);
    }
}
```

**Application Service — use case orkestrasiyası:**

```php
<?php
// Application Service — use case koordinasiyası
namespace App\Application\Banking;

class TransferMoneyHandler
{
    public function __construct(
        private AccountRepository   $accounts,     // infrastructure
        private MoneyTransferPolicy $policy,        // domain service
        private EventBus            $eventBus,      // infrastructure
    ) {}

    public function handle(TransferMoneyCommand $cmd): void
    {
        // 1. Entity-ləri yüklə (repository — infrastructure)
        $from = $this->accounts->findById($cmd->fromAccountId);
        $to   = $this->accounts->findById($cmd->toAccountId);

        if (!$from || !$to) {
            throw new AccountNotFoundException();
        }

        $events = [];

        DB::transaction(function () use ($from, $to, $cmd, &$events) {
            // 2. Domain service ilə business qaydaları yoxla
            $this->policy->validate($from, $to, $cmd->amount);

            // 3. Domain entity metodlarını çağır (entity öz state-ini dəyişir)
            $from->debit($cmd->amount);
            $to->credit($cmd->amount);

            // 4. Persist et (repository)
            $this->accounts->save($from);
            $this->accounts->save($to);

            $events = array_merge($from->pullDomainEvents(), $to->pullDomainEvents());
        });

        // 5. TX commit-dən sonra event publish et
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}

// Application Service — Order domain ilə
class PlaceOrderHandler
{
    public function __construct(
        private OrderRepository      $orders,
        private CustomerRepository   $customers,
        private OrderPricingService  $pricingService, // domain service
        private EventBus             $eventBus,
    ) {}

    public function handle(PlaceOrderCommand $cmd): OrderId
    {
        // 1. Entity-ləri yüklə
        $customer = $this->customers->findById($cmd->customerId);

        // 2. Domain entity-ni yarat
        $order = Order::create($cmd->customerId);
        foreach ($cmd->items as $item) {
            $order->addItem($item->productId, $item->quantity, $item->unitPrice);
        }

        // 3. Domain service çağır (pricing)
        $discountedTotal = $this->pricingService->calculateDiscountedTotal($order, $customer);
        $order->applyDiscount($discountedTotal);

        // 4. Business metodu çağır (domain məntiq aggregate-dədir)
        $order->place();

        $events = [];
        DB::transaction(function () use ($order, &$events) {
            // 5. Persist
            $this->orders->save($order);
            $events = $order->pullDomainEvents();
        });

        // 6. Events dispatch
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }

        return $order->getId();
    }
}
```

**Yanlış vs Doğru:**

```php
// ❌ YANLIŞ: Business logic Application Service-ə
class TransferMoneyHandler
{
    public function handle(TransferMoneyCommand $cmd): void
    {
        $from = $this->accounts->findById($cmd->fromAccountId);
        $to   = $this->accounts->findById($cmd->toAccountId);

        // BU YANLIŞ — domain qayda Application Service-dədir
        if ($from->getCurrency() !== $to->getCurrency()) {
            throw new \DomainException("Currency mismatch");
        }
        if ($from->getBalance() < $cmd->amount) {
            throw new \DomainException("Insufficient funds");
        }

        $from->setBalance($from->getBalance() - $cmd->amount);
        $to->setBalance($to->getBalance() + $cmd->amount);

        $this->accounts->save($from);
        $this->accounts->save($to);
    }
}

// ✅ DOĞRU: Domain Service business logic saxlayır
class TransferMoneyHandler
{
    public function handle(TransferMoneyCommand $cmd): void
    {
        $from = $this->accounts->findById($cmd->fromAccountId);
        $to   = $this->accounts->findById($cmd->toAccountId);

        // Domain service business logic-i yoxlayır
        $this->policy->validate($from, $to, $cmd->amount);

        // Entity öz metodunu çağırır
        $from->debit($cmd->amount);
        $to->credit($cmd->amount);

        $this->accounts->save($from);
        $this->accounts->save($to);
    }
}

// ❌ YANLIŞ: Domain Service-ə Repository inject
class PricingService
{
    public function __construct(
        private ProductRepository $products // ← YANLIŞ! Infrastructure!
    ) {}

    public function calculate(OrderId $orderId): Money
    {
        $products = $this->products->findByOrderId($orderId); // DB sorğu!
        // ...
    }
}

// ✅ DOĞRU: Application Service repository yükləyir, Domain Service-ə ötürür
class CalculatePriceHandler
{
    public function __construct(
        private OrderRepository  $orders,
        private PricingService   $pricing,
    ) {}

    public function handle(string $orderId): Money
    {
        $order = $this->orders->findById(new OrderId($orderId));
        // Domain Service-ə hazır domain object ötürülür
        return $this->pricing->calculateTotal($order);
    }
}
```

**Domain Service unit test — infrastructure mock lazım deyil:**

```php
class MoneyTransferPolicyTest extends TestCase
{
    private MoneyTransferPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new MoneyTransferPolicy(); // dependency yoxdur!
    }

    public function test_throws_when_currencies_differ(): void
    {
        $from   = Account::create(AccountId::generate(), new Money(10000, 'AZN'));
        $to     = Account::create(AccountId::generate(), new Money(0, 'USD'));
        $amount = new Money(1000, 'AZN');

        $this->expectException(CurrencyMismatchException::class);

        $this->policy->validate($from, $to, $amount);
    }

    public function test_throws_when_insufficient_funds(): void
    {
        $from   = Account::create(AccountId::generate(), new Money(500, 'AZN'));
        $to     = Account::create(AccountId::generate(), new Money(0, 'AZN'));
        $amount = new Money(1000, 'AZN');

        $this->expectException(InsufficientFundsException::class);

        $this->policy->validate($from, $to, $amount);
    }

    // Infrastructure mock yoxdur — pure domain logic test
}
```

## Praktik Tapşırıqlar

1. **Audit** — mövcud layihənizdəki service class-larını götürün; "domain logic, yoxsa koordinasiya?" sualını verin; hansıları Application Service-də qalmalı, hansıları Domain Service-ə köçürülməlidir?
2. **Domain Service çıxarın** — `OrderService`-də business validation tapın; `OrderEligibilityPolicy` Domain Service yaradın; infrastructure yoxdur, repository yoxdur; unit test yazın.
3. **Application Service sadələşdirin** — yalnız koordinasiya saxlayın: entity yüklə → domain service/entity metod çağır → persist → event dispatch; business rule yoxdur.
4. **Test müqayisəsi** — Domain Service-i infrastructure olmadan test edin; Application Service-i mock repository ilə test edin; fərqi hiss edin.

## Əlaqəli Mövzular

- [DDD Overview](01-ddd.md) — DDD layer-ları
- [DDD Patterns](03-ddd-patterns.md) — tactical pattern-lər tam seti
- [Aggregates](04-ddd-aggregates.md) — entity öz məntiqini saxlayır
- [Domain Events](05-ddd-domain-events.md) — Application Service event dispatch edir
- [Service Layer](../laravel/02-service-layer.md) — Laravel-də service layer
- [Command/Query Bus](../laravel/08-command-query-bus.md) — Application Service = Command Handler
- [CQRS](../integration/01-cqrs.md) — Command side = Application Service; Query side = ayrı
- [Hexagonal Architecture](../architecture/05-hexagonal-architecture.md) — Application Service = Use Case (Port-da)
