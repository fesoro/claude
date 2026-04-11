# DDD: Bounded Context İnteqrasiyası

## 1. Context Map — Bounded Context-lər Arasındakı Əlaqələr

Context Map, sistemdəki bütün bounded context-ləri və onların bir-biri ilə necə əlaqəli olduğunu göstərən vizual xəritədir. Bu xəritə həm texniki, həm də komanda strukturunu əks etdirir.

### ASCII Diagram — E-Commerce Sistemi

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
│         │ SK (Shared Kernel)             │ Customer/Supplier        │
│         │                                │ (Ordering=Supplier)      │
│         ▼                                ▼                          │
│  ┌──────────────┐    ACL        ┌──────────────────┐               │
│  │              │──────────────▶│                  │               │
│  │  Catalog BC  │               │  Inventory BC    │               │
│  │  (Product,   │◀──────────────│                  │               │
│  │   Category)  │  OHS/PL       │ (StockItem,Ware) │               │
│  └──────────────┘               └────────┬─────────┘               │
│         │                                │                          │
│         │ Separate Ways                  │ ACL                      │
│         │                                ▼                          │
│  ┌──────────────┐               ┌──────────────────┐               │
│  │  Search BC   │               │  Payment BC      │               │
│  │  (ElasticSrch│               │  (Transaction,   │               │
│  │   index)     │               │   Invoice)       │               │
│  └──────────────┘               └──────────────────┘               │
│                                                                     │
│  Əfsanə:                                                            │
│  ──────▶  upstream → downstream                                     │
│  SK     = Shared Kernel                                             │
│  OHS    = Open Host Service                                         │
│  PL     = Published Language                                        │
│  ACL    = Anti-Corruption Layer                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Context Map-in Əhəmiyyəti

- Komandalar arasındakı asılılıqları aydınlaşdırır
- Texniki borcları və riskləri üzə çıxarır
- Refaktorinq prioritetlərini müəyyən edir
- Yeni komanda üzvləri üçün sistem haqqında ümumi mənzərə verir

---

## 2. İnteqrasiya Pattern-ləri

### 2.1 Shared Kernel (Paylaşılan Nüvə)

İki və ya daha çox BC-nin ortaq bir domain modelini paylaşmasıdır. Bu kod hər iki komanda tərəfindən birgə idarə edilir.

**Xüsusiyyətlər:**
- Paylaşılan kod açıq şəkildə müəyyən edilir
- Hər iki komanda dəyişikliklərə razılıq verməlidir
- Sıx birləşmə (tight coupling) yaradır

**Risklər:**
- Bir komandanın dəyişikliyi digərini sındıra bilər
- Koordinasiya xərci yüksəkdir
- Zamanla "shared kernel" böyüyür və idarəolunmaz hala gəlir
- Mikroservis mühitində deploy koordinasiyası çətinləşir

**Nə vaxt istifadə etməli:**
- İki BC çox sıx əlaqəlidirsə və eyni komanda tərəfindən idarə olunursa
- Paylaşılan kod həqiqətən kiçikdirsə (value objects, enums)
- Qısamüddətli həll kimi (uzunmüddətli məqsəd ayrışmaq olmalıdır)

*- Qısamüddətli həll kimi (uzunmüddətli məqsəd ayrışmaq olmalıdır) üçün kod nümunəsi:*
```php
<?php
// Shared Kernel — hər iki BC tərəfindən istifadə edilir
// packages/shared-kernel/src/Money.php

namespace SharedKernel\Domain;

final class Money
{
    public function __construct(
        private readonly int $amount,      // qəpik cinsindən
        private readonly Currency $currency
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Məbləğ mənfi ola bilməz');
        }
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    private function assertSameCurrency(Money $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new \DomainException('Valyutalar eyni deyil');
        }
    }

    public function getAmount(): int { return $this->amount; }
    public function getCurrency(): Currency { return $this->currency; }
}
```

---

### 2.2 Customer/Supplier (Müştəri/Təchizatçı)

İki BC arasında upstream (təchizatçı/supplier) və downstream (müştəri/customer) münasibəti mövcuddur. Upstream BC xidmət göstərir, downstream BC isə bu xidmətdən asılıdır.

**Xüsusiyyətlər:**
- Supplier, Customer-ın ehtiyaclarını nəzərə almalıdır
- Customer tələblərini supplier-ə bildirə bilər
- Supplier dəyişiklik edərkən customer-ı xəbərdar etməlidir
- Acceptance test-lər hər iki tərəf tərəfindən yazılır

**Münasibət diaqramı:**
```
  [Ordering BC] ──────────────────▶ [Identity BC]
   (Customer/downstream)              (Supplier/upstream)

  - Ordering BC, Identity BC-dən user məlumatı alır
  - Identity BC, Ordering BC-nin ehtiyaclarını dinləyir
  - Identity BC yeni endpoint əlavə edə bilər
  - Köhnə endpoint-ləri dərhal silmir (versioning)
```

**PHP nümunəsi:**

```php
<?php
// Identity BC (Supplier) — dərc etdiyi kontrakt
// identity-bc/src/Application/Port/UserDataPort.php

namespace IdentityBC\Application\Port;

interface UserDataPort
{
    public function findById(UserId $id): UserData;
    public function findByEmail(Email $email): ?UserData;
}

// UserData — supplier-in dərc etdiyi DTO
final class UserData
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $fullName,
        public readonly bool $isVerified,
        public readonly \DateTimeImmutable $registeredAt
    ) {}
}

// Ordering BC (Customer) — supplier-dən istifadə edir
// ordering-bc/src/Application/Service/PlaceOrderService.php

namespace OrderingBC\Application\Service;

use IdentityBC\Application\Port\UserDataPort;

final class PlaceOrderService
{
    public function __construct(
        private readonly UserDataPort $userDataPort,
        private readonly OrderRepository $orderRepository
    ) {}

    public function execute(PlaceOrderCommand $command): OrderId
    {
        $userData = $this->userDataPort->findById(
            new UserId($command->userId)
        );

        if (!$userData->isVerified) {
            throw new UnverifiedUserException('Təsdiqlənməmiş istifadəçi sifariş verə bilməz');
        }

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

---

### 2.3 Conformist (Uyğunlaşan)

Downstream BC, upstream BC-nin modelinə tam uyğunlaşır. Upstream BC downstream-in tələblərini nəzərə almır.

**Nə vaxt baş verir:**
- Upstream BC xarici sistem və ya üçüncü tərəf xidmətidir
- Upstream komanda downstream-in tələblərinə əhəmiyyət vermir
- Upstream modeli kifayət qədər yaxşıdır və adaptasiya lazım deyil

**Risklər:**
- Downstream BC xarici sistemin "pis" dizaynını miras alır
- Upstream-dəki dəyişikliklər birbaşa downstream-ə yayılır
- Domain modeliniz çirklənə bilər

*- Domain modeliniz çirklənə bilər üçün kod nümunəsi:*
```php
<?php
// Xarici CRM sisteminin modelinə uyğunlaşma (Conformist)
// crm-integration/src/Domain/CrmContact.php

namespace CrmIntegration\Domain;

// CRM sisteminin strukturunu olduğu kimi istifadə edirik
// öz modelimizi yaratmırıq
final class CrmContact
{
    public string $contact_id;       // CRM-in adlandırma konvensiyası
    public string $first_name;
    public string $last_name;
    public string $email_address;
    public string $phone_number;
    public array $custom_fields;     // CRM-in çevik sahəsi

    // CRM-in API cavabından hydrate
    public static function fromApiResponse(array $data): self
    {
        $contact = new self();
        $contact->contact_id = $data['contactId'];
        $contact->first_name = $data['firstName'];
        $contact->last_name = $data['lastName'];
        $contact->email_address = $data['email'];
        $contact->phone_number = $data['phone'] ?? '';
        $contact->custom_fields = $data['customFields'] ?? [];
        return $contact;
    }
}
```

---

### 2.4 Anti-Corruption Layer (ACL) — Korrupsiyadan Qoruma Qatı

ACL, downstream BC-nin öz domain modelini qorumaq üçün upstream BC-nin modelini öz modelinə çevirən bir tərcümə qatıdır. Bu pattern Conformist-in əksidir.

**Məqsəd:**
- Xarici sistemin "çirkin" modelinin öz BC-nə sızmasının qarşısını alır
- Öz domain dilini qoruyur
- Xarici sistemin dəyişiklikləri yalnız ACL-ə təsir edir

**Komponentlər:**
- **Translator/Mapper** — model çevrilməsini həyata keçirir
- **Facade** — xarici sistemlə ünsiyyəti sadələşdirir
- **Adapter** — texniki inteqrasiya detallarını gizlədir

*- **Adapter** — texniki inteqrasiya detallarını gizlədir üçün kod nümunəsi:*
```php
<?php
// ============================================================
// ACL İmplementasiyası — Xarici Ödəniş Sistemi Nümunəsi
// ============================================================

// 1. Öz Domain Modelimiz (Ordering BC)
// ordering-bc/src/Domain/Model/Payment.php

namespace OrderingBC\Domain\Model;

final class Payment
{
    private function __construct(
        private readonly PaymentId $id,
        private readonly Money $amount,
        private readonly PaymentStatus $status,
        private readonly \DateTimeImmutable $processedAt
    ) {}

    public static function successful(
        PaymentId $id,
        Money $amount,
        \DateTimeImmutable $processedAt
    ): self {
        return new self($id, $amount, PaymentStatus::COMPLETED, $processedAt);
    }

    public static function failed(
        PaymentId $id,
        Money $amount,
        \DateTimeImmutable $processedAt
    ): self {
        return new self($id, $amount, PaymentStatus::FAILED, $processedAt);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }
}

// 2. Xarici Ödəniş Sisteminin DTO-ları (ACL içindədir)
// ordering-bc/src/Infrastructure/ACL/PaymentGateway/Dto/GatewayResponse.php

namespace OrderingBC\Infrastructure\ACL\PaymentGateway\Dto;

final class GatewayResponse
{
    public function __construct(
        public readonly string $transaction_id,
        public readonly string $status,       // 'SUCCESS', 'FAIL', 'PENDING'
        public readonly float $charged_amount,
        public readonly string $currency_code,
        public readonly int $timestamp,       // Unix timestamp
        public readonly ?string $error_code,
        public readonly ?string $error_message
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
            error_message: $data['errorMessage'] ?? null
        );
    }
}

// 3. Translator — ACL-in əsas komponenti
// ordering-bc/src/Infrastructure/ACL/PaymentGateway/PaymentTranslator.php

namespace OrderingBC\Infrastructure\ACL\PaymentGateway;

use OrderingBC\Domain\Model\Payment;
use OrderingBC\Domain\Model\PaymentId;
use OrderingBC\Domain\Model\Money;
use OrderingBC\Domain\Model\Currency;
use OrderingBC\Infrastructure\ACL\PaymentGateway\Dto\GatewayResponse;

final class PaymentTranslator
{
    public function toDomain(GatewayResponse $response): Payment
    {
        $amount = new Money(
            (int) round($response->charged_amount * 100),
            new Currency($response->currency_code)
        );

        $processedAt = \DateTimeImmutable::createFromFormat(
            'U',
            (string) $response->timestamp
        );

        if ($response->status === 'SUCCESS') {
            return Payment::successful(
                new PaymentId($response->transaction_id),
                $amount,
                $processedAt
            );
        }

        return Payment::failed(
            new PaymentId($response->transaction_id),
            $amount,
            $processedAt
        );
    }

    public function toGatewayRequest(OrderId $orderId, Money $amount): array
    {
        return [
            'merchantOrderId' => $orderId->toString(),
            'amount'          => $amount->getAmount() / 100,
            'currency'        => $amount->getCurrency()->getCode(),
            'callbackUrl'     => config('payment.callback_url'),
        ];
    }
}

// 4. Facade — xarici sistemlə ünsiyyəti idarə edir
// ordering-bc/src/Infrastructure/ACL/PaymentGateway/PaymentGatewayFacade.php

namespace OrderingBC\Infrastructure\ACL\PaymentGateway;

use OrderingBC\Domain\Port\PaymentGatewayPort;
use OrderingBC\Domain\Model\Payment;
use OrderingBC\Domain\Model\Money;

final class PaymentGatewayFacade implements PaymentGatewayPort
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly PaymentTranslator $translator,
        private readonly string $gatewayBaseUrl
    ) {}

    public function charge(OrderId $orderId, Money $amount): Payment
    {
        $requestData = $this->translator->toGatewayRequest($orderId, $amount);

        try {
            $rawResponse = $this->httpClient->post(
                $this->gatewayBaseUrl . '/v2/charge',
                $requestData
            );

            $gatewayResponse = GatewayResponse::fromArray($rawResponse);
            return $this->translator->toDomain($gatewayResponse);

        } catch (HttpException $e) {
            throw new PaymentGatewayException(
                'Ödəniş gateway-i əlçatmaz: ' . $e->getMessage(),
                previous: $e
            );
        }
    }
}

// 5. Domain Port (interface) — BC-nin daxilindədir
// ordering-bc/src/Domain/Port/PaymentGatewayPort.php

namespace OrderingBC\Domain\Port;

interface PaymentGatewayPort
{
    public function charge(OrderId $orderId, Money $amount): Payment;
}
```

---

### 2.5 Open Host Service (OHS) — Açıq Host Xidməti

BC öz xidmətlərini açıq, yaxşı sənədləşdirilmiş protokol vasitəsilə dərc edir. Hər yeni inteqrasiya üçün ayrı adapter yazmaq əvəzinə, ümumi bir API/protokol təyin edilir.

**Xüsusiyyətlər:**
- Bir BC çoxlu digər BC-lər tərəfindən istifadə edilirsə münasibdir
- API versioning tətbiq olunur
- Contract testing aparılır

*- Contract testing aparılır üçün kod nümunəsi:*
```php
<?php
// Catalog BC — Open Host Service kimi çıxış edir
// catalog-bc/src/Application/Api/CatalogApiController.php

namespace CatalogBC\Application\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/catalog')]
final class CatalogApiController
{
    public function __construct(
        private readonly ProductQueryService $queryService
    ) {}

    #[Route('/products/{id}', methods: ['GET'])]
    public function getProduct(string $id): JsonResponse
    {
        $product = $this->queryService->findById(new ProductId($id));

        if ($product === null) {
            return new JsonResponse(['error' => 'Məhsul tapılmadı'], 404);
        }

        // Published Language — standart format
        return new JsonResponse([
            'id'          => $product->getId()->toString(),
            'name'        => $product->getName(),
            'sku'         => $product->getSku(),
            'price'       => [
                'amount'   => $product->getPrice()->getAmount(),
                'currency' => $product->getPrice()->getCurrency()->getCode(),
            ],
            'isAvailable' => $product->isAvailable(),
            'category'    => [
                'id'   => $product->getCategory()->getId()->toString(),
                'name' => $product->getCategory()->getName(),
            ],
        ]);
    }

    #[Route('/products', methods: ['GET'])]
    public function listProducts(Request $request): JsonResponse
    {
        // Filtrləmə, səhifələmə dəstəyi
        $products = $this->queryService->findByFilters(
            new ProductFilters(
                categoryId: $request->query->get('categoryId'),
                minPrice: $request->query->get('minPrice'),
                maxPrice: $request->query->get('maxPrice'),
                page: (int) $request->query->get('page', 1),
                perPage: (int) $request->query->get('perPage', 20),
            )
        );

        return new JsonResponse($this->serializeCollection($products));
    }
}
```

---

### 2.6 Published Language (PL) — Dərc Edilmiş Dil

Bütün BC-lər arasında paylaşılan, yaxşı sənədləşdirilmiş kommunikasiya dilidir. Adətən OHS ilə birlikdə istifadə edilir.

**Formaları:**
- JSON Schema
- Protocol Buffers (Protobuf)
- XML Schema (XSD)
- AsyncAPI (event-lər üçün)
- OpenAPI/Swagger (REST üçün)

*- OpenAPI/Swagger (REST üçün) üçün kod nümunəsi:*
```php
<?php
// Published Language — Domain Event strukturu
// Bütün BC-lər bu event formatını bilir

// shared/src/Events/OrderPlacedEvent.php
namespace Shared\Events;

/**
 * Published Language: Sifariş verildiyi zaman yayımlanan event
 *
 * Versiya: 1.2
 * Schema: https://schema.company.com/events/order-placed/v1.2
 */
final class OrderPlacedEvent implements DomainEventInterface
{
    public const EVENT_NAME = 'ordering.order.placed';
    public const EVENT_VERSION = '1.2';

    public function __construct(
        public readonly string $eventId,
        public readonly string $occurredAt,      // ISO 8601
        public readonly string $orderId,
        public readonly string $customerId,
        public readonly string $customerEmail,
        public readonly array  $items,            // OrderItemDto[]
        public readonly array  $totalAmount,      // {amount: int, currency: string}
        public readonly string $shippingAddress
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventId: $data['eventId'],
            occurredAt: $data['occurredAt'],
            orderId: $data['orderId'],
            customerId: $data['customerId'],
            customerEmail: $data['customerEmail'],
            items: $data['items'],
            totalAmount: $data['totalAmount'],
            shippingAddress: $data['shippingAddress']
        );
    }

    public function toArray(): array
    {
        return [
            'eventName'       => self::EVENT_NAME,
            'eventVersion'    => self::EVENT_VERSION,
            'eventId'         => $this->eventId,
            'occurredAt'      => $this->occurredAt,
            'orderId'         => $this->orderId,
            'customerId'      => $this->customerId,
            'customerEmail'   => $this->customerEmail,
            'items'           => $this->items,
            'totalAmount'     => $this->totalAmount,
            'shippingAddress' => $this->shippingAddress,
        ];
    }
}
```

---

### 2.7 Separate Ways (Ayrı Yollar)

İki BC arasında heç bir inteqrasiya yoxdur. Hər BC öz ehtiyaclarını özü həll edir, hətta bəzi funksionallıq dublikat olsa belə.

**Nə vaxt seçilir:**
- İnteqrasiyanın xərci potensial faydadan çoxdur
- İki BC-nin ehtiyacları o qədər fərqlidir ki, ümumi kod mənasızdır
- Komandalar arasında koordinasiya çox baha başa gəlir
- Sistemin bir hissəsinin digərindən izolasiyası vacibdir

**Nümunə:**
```
Catalog BC — öz axtarış indeksi var (Elasticsearch)
Search BC  — öz axtarış məntiqi var (tam-mətn, facet)

İnteqrasiya əvəzinə: Search BC məhsul dəyişikliklərini event ilə alır
və öz indexini özü saxlayır. İki BC bir-birini "görmür".
```

---

## 3. PHP-də ACL İmplementasiyası — Translator Sinifləri

Tam bir ACL arxitekturası aşağıdakı komponentlərdən ibarətdir:

*Tam bir ACL arxitekturası aşağıdakı komponentlərdən ibarətdir üçün kod nümunəsi:*
```php
<?php
// ============================================================
// Tam ACL Nümunəsi: Inventory BC ↔ Xarici Anbar Sistemi
// ============================================================

// --- Domain tərəfi (Inventory BC daxili) ---

// inventory-bc/src/Domain/Model/StockItem.php
namespace InventoryBC\Domain\Model;

final class StockItem
{
    private function __construct(
        private readonly StockItemId $id,
        private readonly ProductId $productId,
        private int $quantityOnHand,
        private int $quantityReserved
    ) {}

    public static function create(
        StockItemId $id,
        ProductId $productId,
        int $initialQuantity
    ): self {
        if ($initialQuantity < 0) {
            throw new \DomainException('İlkin miqdar mənfi ola bilməz');
        }
        return new self($id, $productId, $initialQuantity, 0);
    }

    public function reserve(int $quantity): void
    {
        if ($this->getAvailable() < $quantity) {
            throw new InsufficientStockException(
                "Stokda kifayət qədər məhsul yoxdur. " .
                "Mövcud: {$this->getAvailable()}, Tələb: {$quantity}"
            );
        }
        $this->quantityReserved += $quantity;
    }

    public function getAvailable(): int
    {
        return $this->quantityOnHand - $this->quantityReserved;
    }
}

// inventory-bc/src/Domain/Port/WarehouseSystemPort.php
namespace InventoryBC\Domain\Port;

interface WarehouseSystemPort
{
    public function getStockLevel(ProductId $productId): StockLevel;
    public function reserveStock(ProductId $productId, int $quantity): ReservationId;
    public function releaseReservation(ReservationId $reservationId): void;
}

// --- ACL qatı ---

// inventory-bc/src/Infrastructure/ACL/WarehouseSystem/Dto/WarehouseStockDto.php
namespace InventoryBC\Infrastructure\ACL\WarehouseSystem\Dto;

final class WarehouseStockDto
{
    public function __construct(
        public readonly string $item_code,
        public readonly string $warehouse_id,
        public readonly int    $qty_available,
        public readonly int    $qty_allocated,
        public readonly int    $qty_on_order,
        public readonly string $last_updated    // 'd/m/Y H:i:s' format
    ) {}
}

// inventory-bc/src/Infrastructure/ACL/WarehouseSystem/StockLevelTranslator.php
namespace InventoryBC\Infrastructure\ACL\WarehouseSystem;

use InventoryBC\Domain\Model\StockLevel;
use InventoryBC\Infrastructure\ACL\WarehouseSystem\Dto\WarehouseStockDto;

final class StockLevelTranslator
{
    public function toDomain(WarehouseStockDto $dto): StockLevel
    {
        // Xarici sistemin 'd/m/Y H:i:s' formatını öz formatımıza çeviririk
        $lastUpdated = \DateTimeImmutable::createFromFormat(
            'd/m/Y H:i:s',
            $dto->last_updated
        );

        return new StockLevel(
            available: $dto->qty_available,
            reserved: $dto->qty_allocated,
            incoming: $dto->qty_on_order,
            asOf: $lastUpdated
        );
    }

    public function toReservationRequest(ProductId $productId, int $quantity): array
    {
        return [
            'itemCode'  => $productId->toString(),
            'quantity'  => $quantity,
            'source'    => 'ECOMMERCE',
            'timestamp' => time(),
        ];
    }
}

// inventory-bc/src/Infrastructure/ACL/WarehouseSystem/WarehouseSystemAdapter.php
namespace InventoryBC\Infrastructure\ACL\WarehouseSystem;

use InventoryBC\Domain\Port\WarehouseSystemPort;

final class WarehouseSystemAdapter implements WarehouseSystemPort
{
    public function __construct(
        private readonly WarehouseApiClient $apiClient,
        private readonly StockLevelTranslator $translator,
        private readonly ReservationTranslator $reservationTranslator
    ) {}

    public function getStockLevel(ProductId $productId): StockLevel
    {
        try {
            $rawData = $this->apiClient->fetchStockInfo($productId->toString());
            $dto = new WarehouseStockDto(
                item_code: $rawData['itemCode'],
                warehouse_id: $rawData['warehouseId'],
                qty_available: $rawData['qtyAvailable'],
                qty_allocated: $rawData['qtyAllocated'],
                qty_on_order: $rawData['qtyOnOrder'],
                last_updated: $rawData['lastUpdated']
            );
            return $this->translator->toDomain($dto);
        } catch (ApiException $e) {
            throw new WarehouseUnavailableException(
                'Anbar sistemi əlçatmaz: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function reserveStock(ProductId $productId, int $quantity): ReservationId
    {
        $request = $this->translator->toReservationRequest($productId, $quantity);
        $response = $this->apiClient->createReservation($request);
        return new ReservationId($response['reservationId']);
    }

    public function releaseReservation(ReservationId $reservationId): void
    {
        $this->apiClient->cancelReservation($reservationId->toString());
    }
}
```

---

## 4. Hər Pattern-i Nə Vaxt İstifadə Etməli

| Pattern | Nə vaxt istifadə et | Nə vaxt istifadə etmə |
|---------|--------------------|-----------------------|
| **Shared Kernel** | Eyni komanda, kiçik paylaşılan model, qısamüddətli | Müxtəlif komandalar, böyük codebase, mikroservislər |
| **Customer/Supplier** | İki komanda əməkdaşlıq edir, upstream downstream-ə qulaq asır | Upstream xarici/idarəolunmaz olduqda |
| **Conformist** | Xarici sistem yaxşı dizaynlıdır, dəyişdirə bilmirsiniz | Xarici modeli mənimsəmək domain-i zədələyəcəksə |
| **ACL** | Xarici sistem çirkin/köhnə modelə malikdir | Xarici model BC-nin modeli ilə uyğundursa (ACL əlavə mürəkkəblik yaradır) |
| **Open Host Service** | BC çoxlu konsumentlərə xidmət edir | Yalnız bir-iki konsument varsa |
| **Published Language** | Geniş ekosistem, çoxlu inteqrasiyalar | Kiçik, yaxın əlaqəli BC-lər |
| **Separate Ways** | Koordinasiya xərci çox yüksəkdir | Gerçək ümumi ehtiyac olduqda |

### Qərar ağacı:

```
Xarici sistem idarəolunandırmı?
├── Bəli → Customer/Supplier
│    └── Downstream modeli zədələyirmi?
│         ├── Bəli  → Customer/Supplier + ACL
│         └── Xeyr → Customer/Supplier (Conformist ola bilər)
└── Xeyr (üçüncü tərəf/legacy)
     └── Model yaxşıdırmı?
          ├── Bəli  → Conformist
          └── Xeyr → ACL mütləqdir

Bir BC çox BC-yə xidmət edirmi?
└── Bəli → Open Host Service + Published Language

Koordinasiya xərci çox yüksəkdirmi?
└── Bəli → Separate Ways (dublikat qəbul edilə bilər)

İki BC həddindən artıq sıx əlaqəlidirmi?
└── Bəli → Shared Kernel (lakin uzunmüddətdə ayrışmaq lazımdır)
```

---

## 5. Context-lər Arasında Ubiquitous Language Fərqləri

Eyni "şey" fərqli BC-lərdə fərqli adlar və mənalar daşıyır. Bu DDD-nin ən vacib anlayışlarından biridir.

### "User" vs "Customer" vs "Buyer" nümunəsi

```
┌─────────────────────────────────────────────────────────────────┐
│  Eyni fiziki şəxs, fərqli BC-lərdə fərqli kimlikdir            │
│                                                                 │
│  Identity BC:    User                                           │
│    - id, email, passwordHash                                    │
│    - roles: [ADMIN, CUSTOMER, MODERATOR]                        │
│    - isVerified, lastLoginAt                                    │
│    - MFA settings                                               │
│    "Kim giriş etdi?" sualına cavab verir                        │
│                                                                 │
│  Ordering BC:    Customer                                       │
│    - customerId, displayName, email                             │
│    - shippingAddresses, billingAddress                          │
│    - orderHistory, totalSpent                                   │
│    - preferredPaymentMethod                                     │
│    "Kim sifariş verir?" sualına cavab verir                     │
│                                                                 │
│  Marketing BC:   Subscriber                                     │
│    - subscriberId, email                                        │
│    - segments: [LOYAL, HIGH_VALUE, CHURNED]                     │
│    - preferences, consentGivenAt                                │
│    - openRate, clickRate                                        │
│    "Kimə hansı kampaniya göndərək?" sualına cavab verir         │
│                                                                 │
│  Support BC:     Ticket Owner                                   │
│    - contactId, name, email, phone                              │
│    - ticketHistory, satisfactionScore                           │
│    - tier: [STANDARD, PREMIUM, VIP]                             │
│    "Kim dəstək xidmətindən istifadə edir?" sualına cavab verir  │
└─────────────────────────────────────────────────────────────────┘
```

### PHP-də fərqli modellər:

*PHP-də fərqli modellər: üçün kod nümunəsi:*
```php
<?php
// Identity BC-nin User-i
namespace IdentityBC\Domain\Model;

final class User
{
    private function __construct(
        private readonly UserId $id,
        private Email $email,
        private HashedPassword $password,
        private array $roles,
        private bool $isEmailVerified,
        private ?MfaConfig $mfaConfig
    ) {}

    public function canLogin(): bool
    {
        return $this->isEmailVerified;
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}

// Ordering BC-nin Customer-i — eyni şəxs, fərqli kontekst
namespace OrderingBC\Domain\Model;

final class Customer
{
    private function __construct(
        private readonly CustomerId $id,
        private readonly string $displayName,
        private readonly string $email,
        private CustomerTier $tier,
        private array $shippingAddresses,
        private ?BillingAddress $defaultBillingAddress,
        private int $totalOrderCount
    ) {}

    public function isEligibleForFreeShipping(): bool
    {
        return $this->tier === CustomerTier::PREMIUM
            || $this->totalOrderCount > 10;
    }

    public function getApplicableDiscount(): Percentage
    {
        return match($this->tier) {
            CustomerTier::BRONZE  => Percentage::of(0),
            CustomerTier::SILVER  => Percentage::of(5),
            CustomerTier::GOLD    => Percentage::of(10),
            CustomerTier::PREMIUM => Percentage::of(15),
        };
    }
}
```

### "Order" anlayışının fərqli BC-lərdə mənası:

| BC | Anlayış | Vacib atributlar | Əsas davranış |
|----|---------|-----------------|---------------|
| Ordering | Order | items, total, status | place, cancel, confirm |
| Inventory | Demand | productId, quantity, reservationId | reserve, release |
| Shipping | Shipment | destination, weight, dimensions | dispatch, track, deliver |
| Billing | Invoice | lineItems, tax, dueDate | issue, pay, void |
| Reporting | SalesRecord | revenue, margin, channel | aggregate, analyze |

---

## 6. Event-based İnteqrasiya vs Sinxron API Çağırışları

### Sinxron API Çağırışları (REST/gRPC)

**Üstünlüklər:**
- Sadə, anlaşılan model
- Dərhal cavab
- Asan debug
- Güclü type safety (gRPC ilə)

**Çatışmazlıqlar:**
- Temporal coupling — caller, callee mövcud olmalıdır
- Cascade failures — bir servis çökərsə digərlərini də çökürür
- Latency artımı — zincirlənmiş çağırışlarda yığılır
- BC-lər arasında sıx əlaqə

*- BC-lər arasında sıx əlaqə üçün kod nümunəsi:*
```php
<?php
// Sinxron inteqrasiya — problemlər açıqdır
final class PlaceOrderService
{
    public function execute(PlaceOrderCommand $command): void
    {
        // 1. User yoxla — Identity BC-yə sinxron çağırış
        $user = $this->identityClient->getUser($command->userId);

        // 2. Stok yoxla — Inventory BC-yə sinxron çağırış
        $stock = $this->inventoryClient->checkStock($command->items);

        // 3. Ödəniş al — Payment BC-yə sinxron çağırış
        $payment = $this->paymentClient->charge($command->paymentDetails);

        // 4. Sifarişi qeyd et
        $order = Order::place($user, $command->items, $payment);
        $this->orderRepository->save($order);

        // Problem: Identity, Inventory və ya Payment mövcud deyilsə — UĞURSUZLUQ
        // Problem: Biri yavaşsa — hamı yavaşlayır
        // Problem: Partial failure — ödəniş alındı amma sifariş saxlanmadı
    }
}
```

### Event-based İnteqrasiya (Asinxron)

**Üstünlüklər:**
- Temporal decoupling — BC-lər eyni anda mövcud olmaya bilər
- Resilience — bir BC çökərsə event queue-da gözləyir
- Scalability — event consumer-lər müstəqil şəkildə genişlənə bilər
- Audit trail — event log tarix kimi saxlanır
- Eventual consistency — güclü consistency əvəzinə

**Çatışmazlıqlar:**
- Mürəkkəb debugging — distributed tracing tələb edir
- Eventual consistency — anında tutarsızlıq ola bilər
- Duplicate event handling — idempotency vacibdir
- Ordering garantiyası yoxdur (əksər sistemlərdə)

*- Ordering garantiyası yoxdur (əksər sistemlərdə) üçün kod nümunəsi:*
```php
<?php
// Event-based inteqrasiya

// Ordering BC — event yayımlayır
final class PlaceOrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EventBus $eventBus
    ) {}

    public function execute(PlaceOrderCommand $command): OrderId
    {
        // Yalnız öz kontekstimizə aid validasiya
        $order = Order::place(
            CustomerId::fromString($command->customerId),
            $command->items
        );

        $this->orderRepository->save($order);

        // Event yayımla — digər BC-lər lazım olduqda reaksiya verəcək
        $this->eventBus->publish(new OrderPlacedEvent(
            eventId: Uuid::generate(),
            occurredAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            orderId: $order->getId()->toString(),
            customerId: $command->customerId,
            customerEmail: $command->customerEmail,
            items: $this->serializeItems($order->getItems()),
            totalAmount: [
                'amount'   => $order->getTotal()->getAmount(),
                'currency' => $order->getTotal()->getCurrency()->getCode(),
            ],
            shippingAddress: $command->shippingAddress
        ));

        return $order->getId();
    }
}

// Inventory BC — event-ə abunədir
final class ReserveStockOnOrderPlaced
{
    public function __construct(
        private readonly StockReservationService $reservationService
    ) {}

    public function handle(OrderPlacedEvent $event): void
    {
        foreach ($event->items as $item) {
            try {
                $this->reservationService->reserve(
                    ProductId::fromString($item['productId']),
                    $item['quantity'],
                    OrderId::fromString($event->orderId)
                );
            } catch (InsufficientStockException $e) {
                // Stok yoxdursa — StockReservationFailedEvent yayımla
                // Ordering BC bu event-ə abunədir və sifarişi ləğv edir
                $this->eventBus->publish(new StockReservationFailedEvent(
                    orderId: $event->orderId,
                    productId: $item['productId'],
                    reason: $e->getMessage()
                ));
                return;
            }
        }

        $this->eventBus->publish(new StockReservedEvent(
            orderId: $event->orderId
        ));
    }
}

// Idempotency — eyni event-in iki dəfə işlənməsinin qarşısı
final class IdempotentEventHandler
{
    public function __construct(
        private readonly ProcessedEventRepository $processedEvents
    ) {}

    public function handle(DomainEventInterface $event, callable $handler): void
    {
        if ($this->processedEvents->hasBeenProcessed($event->eventId)) {
            // Event artıq işlənib, ötür
            return;
        }

        $handler($event);

        $this->processedEvents->markAsProcessed($event->eventId);
    }
}
```

### Müqayisə cədvəli:

| Meyar | Sinxron API | Event-based |
|-------|-------------|-------------|
| Mürəkkəblik | Aşağı | Yüksək |
| Coupling | Temporal (sıx) | Loose |
| Consistency | Güclü (strong) | Eventual |
| Hata tolerantlığı | Zəif | Güclü |
| Debug asanlığı | Asan | Çətin |
| Latency | Yüksək (chain) | Aşağı (non-blocking) |
| Use case | Real-time cavab lazımdır | Fire-and-forget, side effects |

**Hibrid yanaşma:** Kritik iş məntiqi üçün sinxron (stok yoxlamaq), yan effektlər üçün asinxron (e-mail göndərmək, audit log).

---

## 7. Real Nümunə: 4 Bounded Context olan E-Commerce

### Sistemin Ümumi Mənzərəsi

```
┌────────────────────────────────────────────────────────────────────────┐
│                    E-COMMERCE PLATFORM                                 │
│                                                                        │
│  ┌─────────────┐         ┌──────────────┐         ┌─────────────┐     │
│  │  Identity   │ OHS/PL  │   Ordering   │  ACL    │  Payment    │     │
│  │     BC      │◀────────│      BC      │────────▶│     BC      │     │
│  │             │         │              │         │             │     │
│  │ - User      │         │ - Order      │         │ - Txn       │     │
│  │ - Session   │         │ - Customer   │         │ - Invoice   │     │
│  │ - Role      │         │ - OrderItem  │         │ - Refund    │     │
│  └─────────────┘         └──────┬───────┘         └─────────────┘     │
│                                 │                                      │
│                    OrderPlaced  │  (Domain Event — Message Broker)     │
│                                 │                                      │
│              ┌──────────────────┴──────────────────┐                  │
│              │                                     │                  │
│              ▼                                     ▼                  │
│  ┌─────────────────────┐               ┌─────────────────────┐        │
│  │    Inventory BC     │               │    Catalog BC       │        │
│  │                     │               │                     │        │
│  │ - StockItem         │               │ - Product           │        │
│  │ - Reservation       │ OHS/PL        │ - Category          │        │
│  │ - Warehouse         │◀──────────────│ - PriceList         │        │
│  │                     │               │                     │        │
│  │ [Reserves stock on  │               │ [Open Host Service  │        │
│  │  OrderPlaced event] │               │  for all consumers] │        │
│  └─────────────────────┘               └─────────────────────┘        │
│                                                                        │
│  İnteqrasiya pattern-ləri:                                             │
│  Identity ↔ Ordering   : Customer/Supplier + ACL                      │
│  Ordering → Payment    : Customer/Supplier + ACL                      │
│  Catalog → Inventory   : Open Host Service / Published Language       │
│  Ordering → Inventory  : Event-based (OrderPlaced → StockReserved)    │
└────────────────────────────────────────────────────────────────────────┘
```

### BC-lərin Əlaqə Cədvəli

| From BC | To BC | Pattern | Mexanizm | Məqsəd |
|---------|-------|---------|----------|--------|
| Ordering | Identity | Customer/Supplier + ACL | REST API | User doğrulaması |
| Ordering | Payment | Customer/Supplier + ACL | REST API | Ödəniş emalı |
| Ordering | Inventory | Event-based | Message Broker | Stok rezervasiyası |
| Inventory | Catalog | Conformist | REST API (OHS) | Məhsul məlumatı |
| Catalog | — | Open Host Service | REST + Events | Məhsul kataloqu |

### Tam Sifariş Axını:

*Tam Sifariş Axını: üçün kod nümunəsi:*
```php
<?php
// Sifariş vermə axını — bütün BC-ləri əhatə edir

// ADDIM 1: Ordering BC — sinxron, ACL vasitəsilə Identity BC-yə müraciət
final class PlaceOrderHandler
{
    public function handle(PlaceOrderCommand $cmd): void
    {
        // ACL — Identity BC-nin modelini öz modelimizə çeviririk
        $customerData = $this->identityAcl->getCustomerData(
            UserId::fromString($cmd->userId)
        );

        // ADDIM 2: Kataloqdan qiymətləri yoxlayırıq (Catalog BC — OHS)
        $pricedItems = [];
        foreach ($cmd->items as $item) {
            $product = $this->catalogClient->getProduct(
                ProductId::fromString($item['productId'])
            );
            $pricedItems[] = OrderItem::create(
                ProductId::fromString($item['productId']),
                $product->getName(),
                $product->getPrice(),
                $item['quantity']
            );
        }

        // ADDIM 3: Sifarişi yaradırıq (Ordering BC daxili məntiqi)
        $order = Order::place($customerData->toCustomer(), $pricedItems);
        $this->orderRepository->save($order);

        // ADDIM 4: Ödəniş alırıq — sinxron (ACL vasitəsilə Payment BC)
        try {
            $payment = $this->paymentAcl->chargeForOrder(
                $order->getId(),
                $order->getTotal(),
                $cmd->paymentToken
            );
            $order->confirmPayment($payment->getId());
        } catch (PaymentFailedException $e) {
            $order->markPaymentFailed();
            $this->orderRepository->save($order);
            throw $e;
        }

        $this->orderRepository->save($order);

        // ADDIM 5: Event yayımla — Inventory BC asinxron reaksiya verəcək
        $this->eventBus->publish(
            OrderPlacedEvent::fromOrder($order, $customerData->email)
        );

        // ADDIM 6: Notification göndər (asinxron, event vasitəsilə)
        // Notification BC OrderPlacedEvent-ə abunədir
    }
}

// Inventory BC — OrderPlacedEvent-i emal edir
final class OrderPlacedEventHandler
{
    public function __invoke(OrderPlacedEvent $event): void
    {
        $orderId = OrderId::fromString($event->orderId);

        foreach ($event->items as $item) {
            $stockItem = $this->stockItemRepository->findByProductId(
                ProductId::fromString($item['productId'])
            );

            if ($stockItem === null) {
                $this->publishReservationFailed($orderId, $item['productId'], 'Stok tapılmadı');
                return;
            }

            try {
                $stockItem->reserve($item['quantity']);
                $this->stockItemRepository->save($stockItem);
            } catch (InsufficientStockException $e) {
                $this->publishReservationFailed($orderId, $item['productId'], $e->getMessage());
                return;
            }
        }

        // Bütün rezervasiyalar uğurlu
        $this->eventBus->publish(new StockReservedEvent(
            orderId: $event->orderId,
            reservedAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ));
    }

    private function publishReservationFailed(
        OrderId $orderId,
        string $productId,
        string $reason
    ): void {
        $this->eventBus->publish(new StockReservationFailedEvent(
            orderId: $orderId->toString(),
            productId: $productId,
            reason: $reason
        ));
    }
}

// Ordering BC — StockReservationFailedEvent-i emal edir
final class StockReservationFailedHandler
{
    public function __invoke(StockReservationFailedEvent $event): void
    {
        $order = $this->orderRepository->findById(
            OrderId::fromString($event->orderId)
        );

        // Sifarişi ləğv et və ödənişi iade et
        $order->cancelDueToStockIssue($event->reason);
        $this->orderRepository->save($order);

        // Ödənişi iade et (Payment BC-yə sinxron çağırış)
        $this->paymentAcl->refundForOrder(OrderId::fromString($event->orderId));

        // İstifadəçiyə məlumat ver (asinxron)
        $this->eventBus->publish(new OrderCancelledEvent(
            orderId: $event->orderId,
            reason: $event->reason
        ));
    }
}
```

---

## 8. İntervyu Sualları

### Sual 1: Context Map nədir və nə üçün lazımdır?

**Cavab:** Context Map, bir proqram təminatı sistemindəki bütün bounded context-ləri və onların bir-biri ilə əlaqəliliyini göstərən vizual diaqramdır. Bu xəritə sadəcə texniki arxitektura deyil, həm də komanda strukturunu, siyasi münasibətləri və texniki əlaqə pattern-lərini əks etdirir. Context Map olmadan:
- Komandalar bir-birinin sistemini düzgün başa düşmür
- İnteqrasiya pattern-ləri qeyri-şüuri şəkildə seçilir
- BC-lər arasındakı sıxlıq (coupling) görünməz qalır

### Sual 2: ACL ilə Conformist arasındakı fərq nədir?

**Cavab:**
- **Conformist**: Downstream BC, upstream BC-nin modelini olduğu kimi qəbul edir. Öz domain modeli yoxdur — xarici modeli bilavasitə istifadə edir.
- **ACL**: Downstream BC, xarici modeli öz domain modelinə çevirmək üçün bir tərcümə qatı yaradır. Öz domain modelini qoruyur.

ACL daha çox kod tələb edir, lakin uzunmüddətdə daha yaxşıdır çünki xarici sistemdəki dəyişiklikləri yalnız ACL qatında idarə etmək kifayətdir.

### Sual 3: Eyni şirkət daxilindəki iki BC arasında hansı pattern seçərdiniz?

**Cavab:** Bir neçə amilə baxardım:
1. **Komandalar əməkdaşlıq edə bilirmi?** → Customer/Supplier
2. **Bir BC çox consumer-ə xidmət edirmi?** → Open Host Service + Published Language
3. **İki BC həddindən artıq sıx əlaqəlidirsə** → Shared Kernel (lakin bu bir antipattern ola bilər)
4. **Upstream modeli yaxşı dizaynlıdırsa** → Conformist
5. **Upstream modeli çirklidir** → ACL

### Sual 4: "User" eyni şeydir, niyə fərqli BC-lərdə fərqli model lazımdır?

**Cavab:** Çünki fərqli BC-lər eyni fiziki şəxs haqqında fərqli suallar soruşur:
- **Identity BC**: "Bu şəxs kim, kim kimi giriş etmişdir?"
- **Ordering BC**: "Bu müştəri necə alış-veriş edir, ünvanları nədir?"
- **Marketing BC**: "Bu abonentə hansı kampaniya göndərək?"
- **Support BC**: "Bu şəxsin dəstək tarixi nədir?"

Hər BC-nin öz modeli var çünki hər BC öz domain logic-ini öz kontekstindən baxaraq modelləşdirir. Bir modeli paylaşmaq BC-ləri sıx birləşdirər (tight coupling) və hər dəyişiklik digərini pozar.

### Sual 5: Event-based inteqrasiyada idempotency niyə vacibdir?

**Cavab:** Distributed sistemlərdə eyni event bir dəfədən çox çatdırıla bilər (at-least-once delivery). Əgər handler idempotent deyilsə:
- Eyni sifariş iki dəfə işlənər
- Stok iki dəfə azalar
- Ödəniş iki dəfə alınar

Həll: Hər event-in unikal ID-si olur. Handler event-i emal etməzdən əvvəl bazada yoxlayır: "Bu ID-li event artıq işlənibmi?" Əgər işlənibsə — ötürür.

### Sual 6: Shared Kernel-in nə vaxt problem olduğunu izah edin.

**Cavab:** Shared Kernel aşağıdakı hallarda problem yaradır:
1. **Komandalar böyüdükcə**: Koordinasiya xərci eksponensial artır
2. **Fərqli deploy ritmi**: Bir komanda dəyişiklik etmək istəyir, digərinin testi bitməmiş
3. **Diverging requirements**: İki BC-nin ehtiyacları zamanla fərqlənir, lakin ortaq kod hər ikisini məmnun etməyə çalışır
4. **Mikroservislər**: Paylaşılan kod library kimi dağıdılmalıdır — versioning problemi yaranır

### Sual 7: Open Host Service ilə normal REST API arasındakı fərq nədir?

**Cavab:** Hər REST API OHS deyil. OHS bir pattern-dir:
- **Açıq sənədləşmə**: API contract dərc edilir (OpenAPI, AsyncAPI)
- **Versioning strategiyası**: Geriyə uyğunluq qorunur
- **Çoxlu consumer-lər**: Bir BC-nin API-ni istifadə etmək üçün izin/özel əlaqə lazım deyil
- **Sabit kontrakt**: API dəyişdikdə müştərilər xəbərdar edilir, deprecated period verilir
- **Published Language**: Paylaşılan terminologiya istifadə edilir

### Sual 8: Saga pattern-i BCİnteqrasiyası ilə necə əlaqəlidir?

**Cavab:** Saga, bir neçə BC-yə span edən uzunmüddətli business transaction-ı koordinasiya edir. Hər BC öz lokal transaction-ını həyata keçirir, event yayımlayır, digər BC reaksiya verir. Əgər bir addım uğursuz olarsa — compensating transaction-lar icra edilir.

Nümunə: Sifariş verərkən:
1. Ordering BC → Order yaradır → OrderPlaced event
2. Inventory BC → Stok rezerv edir → StockReserved event
3. Payment BC → Ödəniş alır → PaymentProcessed event
4. Ordering BC → Sifarişi təsdiqləyir

Hər hansı addım uğursuz olarsa — əvvəlki addımlar ləğv edilir (compensating transactions).

### Sual 9: Hansı hallarda sinxron API çağırışını asinxron event-ə üstün tutarsınız?

**Cavab:**
**Sinxron seçin:**
- İstifadəçi dərhal cavab gözləyirsə (ödəniş nəticəsi, autentifikasiya)
- Məlumat olmadan davam etmək mümkün deyilsə
- Real-time validasiya lazımdırsa

**Asinxron seçin:**
- Yan effektlər: e-mail göndərmək, audit log, analytics
- Uzunmüddətli proseslər: hesabat generasiyası, video emalı
- Digər BC-lər "nəyin baş verdiyini" bilməlidir, lakin dərhal cavab lazım deyil
- Resiliency vacibdirsə (digər BC-nin downtime-ı işi bloklamamalıdır)

### Sual 10: DDD kontekstində "ubiquitous language" BC sərhədlərini necə müəyyən edir?

**Cavab:** Ubiquitous language (hər yerdə istifadə edilən dil), bir BC-nin daxilindəki ekspertlər, developerlar və domain model arasında paylaşılan dilin vahidliyini tələb edir. BC sərhədi məhz bu dilin dəyişdiyi yerdən keçir:

Əgər "order" sözü iki fərqli kontekstdə fərqli məna daşıyırsa (Ordering-də "müştərinin sifarişi", Inventory-də "anbar sifarişi"), bu iki fərqli BC-dir. Dil fərqliliyi BC ayrılığının işarəsidir.

### Sual 11: Legacy sistemlə inteqrasiyada hansı pattern-i seçərdiniz?

**Cavab:** Demək olar ki, həmişə **ACL (Anti-Corruption Layer)**. Çünki:
- Legacy sistemlər adətən köhnə, pis dizaynlı modellərə malikdir
- Bu modellər öz domain-inizə sızmamalıdır
- Legacy sistemdəki dəyişikliklər yalnız ACL-ə təsir etməlidir
- Gələcəkdə legacy sistemi əvəz etsəniz — yalnız ACL implementasiyasını dəyişirsiniz, domain modeliniz toxunulmaz qalır

### Sual 12: Context Map-i necə müəyyən edərdiniz yeni bir proyektdə?

**Cavab:**
1. **Event Storming**: Domain ekspertləri ilə domain event-lərini müəyyən edirik
2. **Aggregate identification**: Hər aggregate natural BC mərkəzi ola bilər
3. **Ubiquitous Language analizi**: Dilin dəyişdiyi yerlər BC sərhədlərini göstərir
4. **Komanda strukturu**: Conway's Law — komanda strukturu arxitekturanı şəkilləndirir
5. **İstifadəçi journey-ləri**: End-to-end axınlar BC-lərin necə əlaqələndiyini göstərir
6. **Mövcud sistem analizi**: Mövcud modul/servis əlaqələri BC-lərin başlanğıc nöqtəsidir

---

## Xülasə

| Anlayış | Qısa izah |
|---------|-----------|
| Context Map | BC-lər arasındakı əlaqələrin xəritəsi |
| Shared Kernel | Ortaq kod — sıx əlaqə, risk var |
| Customer/Supplier | Upstream/downstream — əməkdaşlıq var |
| Conformist | Downstream upstream modelinə uyğunlaşır |
| ACL | Xarici modeli öz modelinə çevirən qat |
| Open Host Service | Hamı üçün açıq, sənədləşdirilmiş API |
| Published Language | Paylaşılan kommunikasiya formatı |
| Separate Ways | İnteqrasiya yoxdur |
| Ubiquitous Language | BC-nin öz dili — BC sərhədi dilin dəyişdiyi yerdir |
| Event-based | Loose coupling, eventual consistency, resilient |
| Sinxron API | Tight coupling, strong consistency, sadə |

---

## Anti-patternlər

**1. Shared database ilə bounded context-ləri "inteqrasiya etmək"**
Order BC və Inventory BC eyni cədvəlləri paylaşır — bir BC-nin schema dəyişikliyi digərini pozur, boundaries mövcud deyil. Hər BC-nin öz DB-si olsun; BC-lər arası kommunikasiya yalnız API ya da event-lər vasitəsilə olsun.

**2. Anti-Corruption Layer olmadan xarici modeli birbaşa istifadə**
Üçüncü tərəf ödəniş sisteminin `payment_status_code: 3` kimi dəyərlərini domain code-da birbaşa istifadə etmək — xarici model öz daxili modlinizə sirayət edir. ACL tətbiq edin: xarici formatı öz ubiquitous language-ınıza çevirin.

**3. Bütün BC-lər arasında Shared Kernel genişlətmək**
Ortaq kodu rahatlıq üçün tədricən böyütmək — Shared Kernel dəyişəndə bütün BC-lər yenidən test edilib deploy edilməlidir. Shared Kernel yalnız həqiqətən ortaq, nadir dəyişən kontraktlarla məhdudlaşdırılsın; şübhəlisini ayrı saxlayın.

**4. Synchronous API ilə sıx əlaqəli BC-lər qurmaq**
Order BC hər əməliyyatda Inventory BC-yə sinxron HTTP sorğusu edir — Inventory down olduqda Order da işləmir. Mümkün olan yerdə event-based async kommunikasiya istifadə edin; temporal decoupling sistemi daha resilient edir.

**5. Ubiquitous Language-ı BC-lər arasında paylaşmaq**
`Order` sözünün Order BC-də və Shipping BC-də eyni mənada istifadəsi — hər BC-nin öz dili var, eyni söz fərqli məna daşıya bilər. Hər BC-nin öz ubiquitous language glossary-si olsun; BC-lər arası keçiddə Translation (ACL) aparılsın.

**6. Context Map-ı sənədləşdirməmək**
BC-lər arası əlaqələr (Conformist, Customer/Supplier, ACL) başda bəlli olsa da yazılmır — yeni komanda üzv anlaya bilmir, əlaqə növü zamanla unudulur. Context Map-ı canlı sənəd olaraq saxlayın: hər BC-nin upstream/downstream əlaqələri açıq şəkildə qeyd edilsin.
