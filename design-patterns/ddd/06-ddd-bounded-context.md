# DDD: Bounded Context (Lead ⭐⭐⭐⭐)

## İcmal

Bounded Context (BC) — DDD-nin ən vacib strategic pattern-idir. Böyük, mürəkkəb domain-i idarə olunan hissələrə bölür. Hər BC öz Ubiquitous Language-ına, öz domain modelinə malikdir. Eyni söz (məs: "User") fərqli BC-lərdə fərqli məna daşıya bilər — bu normal və gözləniləndir.

Context Map — sistemdəki bütün BC-ləri və aralarındakı əlaqə pattern-lərini göstərən vizual xəritədir.

## Niyə Vacibdir

BC olmadan böyük sistemdə "User" müxtəlif yerlər fərqli anlamda istifadə olunur — Identity BC-nin User-i ilə Ordering BC-nin Customer-i eyni table-ı paylaşır, dəyişiklik hər ikisini pozur. BC sərhədləri açıq müəyyən edildikdə hər komanda öz domain-ini müstəqil inkişaf etdirə bilər, başqa BC-lərdəki dəyişikliklər sizi etkiləmir.

## Əsas Anlayışlar

**İnteqrasiya Pattern-ləri:**

| Pattern | Qısa izah |
|---------|-----------|
| **Shared Kernel** | İki BC ortaq kod paylaşır; razılaşma tələb olunur |
| **Customer/Supplier** | Upstream BC xidmət göstərir, downstream BC asılıdır |
| **Conformist** | Downstream BC upstream-in modelinə tam uyğunlaşır |
| **ACL** | Downstream BC xarici modeli öz dilinə çevirir |
| **Open Host Service** | BC çoxlu consumer üçün açıq, sənədli API dərc edir |
| **Published Language** | Paylaşılan kommunikasiya formatı (Protobuf, JSON Schema) |
| **Separate Ways** | İki BC arasında inteqrasiya yoxdur |

**Context Map — E-Commerce:**
```
┌─────────────────────────────────────────────────────────────────────┐
│                        CONTEXT MAP                                  │
│                                                                     │
│  ┌──────────────┐    OHS/PL     ┌──────────────────┐               │
│  │              │──────────────▶│                  │               │
│  │  Identity &  │               │    Ordering BC   │               │
│  │   Auth BC    │◀──────────────│                  │               │
│  │              │  Conformist   │  (Customer,Order)│               │
│  └──────────────┘               └────────┬─────────┘               │
│         │                                │                          │
│         │ SK (Shared Kernel)             │ Event-based              │
│         ▼                                ▼                          │
│  ┌──────────────┐    ACL        ┌──────────────────┐               │
│  │              │──────────────▶│                  │               │
│  │  Catalog BC  │               │  Inventory BC    │               │
│  │  (Product,   │               │ (StockItem,Ware) │               │
│  │   Category)  │               └──────────────────┘               │
│  └──────────────┘                                                   │
│                                                                     │
│  Əfsanə:                                                            │
│  ──────▶  upstream → downstream                                     │
│  SK     = Shared Kernel                                             │
│  OHS    = Open Host Service                                         │
│  ACL    = Anti-Corruption Layer                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Praktik Baxış

**Real istifadə:**
- E-commerce: Identity, Ordering, Inventory, Catalog, Payment, Shipping — hər biri ayrı BC
- Banking: Account Management, Transaction Processing, Fraud Detection, Reporting
- SaaS: Billing, Subscription, User Management, Analytics

**Trade-off-lar:**
- Hər BC müstəqil inkişaf, deploy edilə bilər; komandalar arası coupling azalır
- Lakin: BC-lər arası inteqrasiya overhead-i; eventual consistency; distributed debugging

**İstifadə etməmək:**
- Kiçik tək-komanda layihələrində — overhead-i faydasından çox olar
- Domain hələ yaxşı başa düşülməyibsə — BC-ləri tez müəyyən etmək sonradan refactor tələb edir

**Common mistakes:**
- BC-ləri DB table-larına görə çəkmək (domain biliyinə görə çəkilməlidir)
- Çox kiçik BC-lər — microservice-itis; hər entity ayrı BC-dir
- BC-lər arasında shared database — bütün BC boundary-sini pozur
- Context Map-ı sənədləşdirməmək — yeni komanda üzv anlaya bilmir

**Anti-Pattern Nə Zaman Olur?**

- **Context boundaries-i database table-larına görə çəkmək** — "`orders` cədvəli var, deməli Ordering BC var; `users` cədvəli var, deməli Identity BC var" — bu texniki arxitektura deyil, business domain-ə görə çəkilməlidir. BC-ləri müəyyən etmək üçün Event Storming aparın: domain event-lərini tapın, onların ətrafındakı natural cluster-lar BC-dir.
- **Shared database ilə BC-ləri "inteqrasiya etmək"** — Order BC və Inventory BC eyni cədvəlləri paylaşır — bir BC-nin schema dəyişikliyi digərini pozur, boundaries mövcud deyil. Hər BC-nin öz DB-si (ya da ən azı ayrı schema) olsun.
- **Çox kiçik BC-lər (microservice-itis)** — hər aggregate ayrı BC, hər BC ayrı microservice. Inteqrasiya overhead-i domain logic-dən çox yer tutur. BC-ləri natural language cluster-larına görə müəyyən edin; bir komanda bir-iki BC idarə etməlidir.
- **ACL olmadan xarici modeli birbaşa istifadə** — üçüncü tərəf ödəniş sisteminin `payment_status_code: 3` dəyərini domain code-da birbaşa istifadə etmək. Xarici model öz domain modelinizə sirayət edir. ACL tətbiq edin.

## Nümunələr

### Ümumi Nümunə

**"User" eyni şəxs, fərqli BC-lərdə fərqli kimlik:**
```
Identity BC:    User
  - id, email, passwordHash, roles, isVerified
  "Kim giriş etdi?" sualına cavab verir

Ordering BC:    Customer
  - customerId, displayName, email
  - shippingAddresses, totalOrderCount
  "Kim sifariş verir?" sualına cavab verir

Marketing BC:   Subscriber
  - subscriberId, email, segments, preferences
  "Kimə hansı kampaniya göndərək?" sualına cavab verir

Support BC:     Ticket Owner
  - contactId, name, email, ticketHistory, tier
  "Kim dəstək xidmətindən istifadə edir?" sualına cavab verir
```

Eyni fiziki şəxs — 4 fərqli BC-də 4 fərqli model. Bu DDD-nin gücüdür: hər BC öz domain-i üçün optimal modelə malikdir.

### PHP/Laravel Nümunəsi

**Anti-Corruption Layer (ACL) — xarici ödəniş sistemi:**

```php
// 1. Domain Port (BC daxilindədir)
namespace OrderingBC\Domain\Port;

interface PaymentGatewayPort
{
    public function charge(OrderId $orderId, Money $amount): Payment;
}

// 2. Xarici sistemin DTO-ları (ACL içindədir — domain-ə girmir)
namespace OrderingBC\Infrastructure\ACL\PaymentGateway\Dto;

final class GatewayResponse
{
    public function __construct(
        public readonly string  $transaction_id,
        public readonly string  $status,         // 'SUCCESS', 'FAIL', 'PENDING'
        public readonly float   $charged_amount,
        public readonly string  $currency_code,
        public readonly int     $timestamp,
        public readonly ?string $error_code,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            transaction_id: $data['transactionId'],
            status: $data['status'],
            charged_amount: $data['chargedAmount'],
            currency_code: $data['currencyCode'],
            timestamp: $data['timestamp'],
            error_code: $data['errorCode'] ?? null,
        );
    }
}

// 3. Translator — ACL-in əsas komponenti
namespace OrderingBC\Infrastructure\ACL\PaymentGateway;

final class PaymentTranslator
{
    public function toDomain(GatewayResponse $response): Payment
    {
        $amount = new Money(
            (int) round($response->charged_amount * 100),
            new Currency($response->currency_code)
        );

        $processedAt = \DateTimeImmutable::createFromFormat('U', (string) $response->timestamp);

        return $response->status === 'SUCCESS'
            ? Payment::successful(new PaymentId($response->transaction_id), $amount, $processedAt)
            : Payment::failed(new PaymentId($response->transaction_id), $amount, $processedAt);
    }
}

// 4. Facade — xarici sistemlə ünsiyyəti idarə edir
final class PaymentGatewayFacade implements PaymentGatewayPort
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly PaymentTranslator $translator,
    ) {}

    public function charge(OrderId $orderId, Money $amount): Payment
    {
        try {
            $rawResponse = $this->httpClient->post('/v2/charge', [
                'merchantOrderId' => $orderId->toString(),
                'amount'          => $amount->getAmount() / 100,
                'currency'        => $amount->getCurrency()->getCode(),
            ]);

            return $this->translator->toDomain(GatewayResponse::fromArray($rawResponse));
        } catch (HttpException $e) {
            throw new PaymentGatewayException('Ödəniş gateway əlçatmaz: ' . $e->getMessage(), previous: $e);
        }
    }
}
```

**Customer/Supplier pattern:**

```php
// Identity BC (Supplier) — dərc etdiyi kontrakt
namespace IdentityBC\Application\Port;

interface UserDataPort
{
    public function findById(UserId $id): UserData;
    public function findByEmail(Email $email): ?UserData;
}

final class UserData
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $fullName,
        public readonly bool   $isVerified,
    ) {}
}

// Ordering BC (Customer) — supplier-dən istifadə edir
final class PlaceOrderService
{
    public function __construct(
        private readonly UserDataPort    $userDataPort, // Interface vasitəsilə
        private readonly OrderRepository $orderRepository
    ) {}

    public function execute(PlaceOrderCommand $command): OrderId
    {
        $userData = $this->userDataPort->findById(new UserId($command->userId));

        if (!$userData->isVerified) {
            throw new UnverifiedUserException('Təsdiqlənməmiş istifadəçi sifariş verə bilməz');
        }

        // Supplier-in modelini öz modelimizə çeviririk
        $customer = Customer::reconstitute(
            CustomerId::fromString($userData->id),
            $userData->email,
            $userData->fullName
        );

        $order = Order::place($customer, $command->items);
        $this->orderRepository->save($order);

        return $order->getId();
    }
}
```

**Open Host Service — Catalog BC:**

```php
// Catalog BC çoxlu consumer-ə xidmət edir
// Versioning, sənədləşdirmə, sabit kontrakt

#[Route('/api/v1/catalog')]
final class CatalogApiController
{
    #[Route('/products/{id}', methods: ['GET'])]
    public function getProduct(string $id): JsonResponse
    {
        $product = $this->queryService->findById(new ProductId($id));

        if ($product === null) {
            return new JsonResponse(['error' => 'Məhsul tapılmadı'], 404);
        }

        // Published Language — standart format; versioning-ə tabedir
        return new JsonResponse([
            'id'          => $product->getId()->toString(),
            'name'        => $product->getName(),
            'sku'         => $product->getSku(),
            'price'       => [
                'amount'   => $product->getPrice()->getAmount(),
                'currency' => $product->getPrice()->getCurrency()->getCode(),
            ],
            'isAvailable' => $product->isAvailable(),
        ]);
    }
}
```

**Event-based BC inteqrasiyası:**

```php
// Ordering BC — event yayımlayır, Inventory-ni birbaşa çağırmır
final class PlaceOrderHandler
{
    public function handle(PlaceOrderCommand $cmd): void
    {
        $order = Order::place(
            CustomerId::fromString($cmd->customerId),
            $cmd->items
        );

        $this->orderRepository->save($order);

        // Yalnız event yayımla — Inventory BC asinxron reaksiya verəcək
        $this->eventBus->publish(new OrderPlacedEvent(
            orderId: $order->getId()->toString(),
            customerId: $cmd->customerId,
            items: $cmd->items,
        ));
    }
}

// Inventory BC — event-ə abunədir, müstəqil işləyir
final class ReserveStockOnOrderPlaced
{
    public function handle(OrderPlacedEvent $event): void
    {
        foreach ($event->items as $item) {
            $stockItem = $this->stockItemRepository->findByProductId(
                ProductId::fromString($item['productId'])
            );

            try {
                $stockItem->reserve($item['quantity']);
                $this->stockItemRepository->save($stockItem);
            } catch (InsufficientStockException $e) {
                $this->eventBus->publish(new StockReservationFailedEvent(
                    orderId: $event->orderId,
                    productId: $item['productId'],
                    reason: $e->getMessage()
                ));
                return;
            }
        }

        $this->eventBus->publish(new StockReservedEvent(orderId: $event->orderId));
    }
}
```

**Hər pattern nə vaxt:**

| Pattern | İstifadə et | İstifadə etmə |
|---------|------------|---------------|
| **Shared Kernel** | Eyni komanda, kiçik paylaşılan model | Müxtəlif komandalar, böyük codebase |
| **Customer/Supplier** | İki komanda əməkdaşlıq edir | Upstream xarici/idarəolunmaz |
| **Conformist** | Xarici sistem yaxşı dizaynlıdır | Xarici model domain-i zədələyəcəksə |
| **ACL** | Xarici sistem köhnə/çirkin modelə malikdir | Xarici model BC ilə uyğundursa |
| **Open Host Service** | BC çoxlu consumer-ə xidmət edir | Yalnız 1-2 consumer |
| **Separate Ways** | Koordinasiya xərci çox yüksəkdir | Gerçək ümumi ehtiyac olduqda |

## Praktik Tapşırıqlar

1. **Event Storming** — mövcud sistemin domain event-lərini (past tense) sadalayın; event-lərin natural cluster-larını tapın; hər cluster potensial BC-dir.
2. **Context Map** — sisteminizdəki BC-ləri çəkin; aralarındakı əlaqə pattern-lərini (Conformist, ACL, Customer/Supplier) müəyyən edin; sənədləşdirin.
3. **ACL implementasiya** — xarici ödəniş sistemini domain-dən izolyasiya edin; Translator + Facade + Port pattern-i tətbiq edin; xarici sistem dəyişdikdə yalnız ACL-in dəyişdiyini test edin.
4. **Ubiquitous Language glossary** — hər BC üçün ayrı glossary yaradın; eyni sözün fərqli BC-lərdə nə demək olduğunu yazın; team ilə review edin.

## Əlaqəli Mövzular

- [DDD Overview](01-ddd.md) — strategic vs tactical DDD
- [Shared Kernel](07-shared-kernel.md) — BC-lər arası paylaşılan kod
- [Domain Service vs App Service](08-domain-service-vs-app-service.md) — BC daxili service ayrımı
- [ACL Pattern](../integration/08-anti-corruption-layer.md) — xarici modeldən qorunma
- [Saga Pattern](../integration/03-saga-pattern.md) — BC-lər arası long-running transactions
- [Choreography vs Orchestration](../integration/11-choreography-vs-orchestration.md) — BC inteqrasiya üsulları
- [Event Sourcing](../integration/02-event-sourcing.md) — event-driven BC inteqrasiyası
- [Modular Monolith](../architecture/08-modular-monolith.md) — BC-ləri bir monolit içində
- [Hexagonal Architecture](../architecture/05-hexagonal-architecture.md) — BC-ni xaricindən izolyasiya
