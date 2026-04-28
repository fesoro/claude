# Anti-Corruption Layer (Senior)

Bounded Context-lər arasında **translation layer** — öz domain model-ini başqa sistemin zəif
və ya uyğunsuz modelindən qoruyur. Xarici sistem öz domain-ini tətbiq etməsin deyə adapter + facade yaradılır.

**Əsas anlayışlar:**
- **ACL (Anti-Corruption Layer)** — Xarici modeli öz domain model-inə çevirən qat
- **Adapter** — Xarici interfeysi daxili interfeyslə uyğunlaşdırır
- **Facade** — Xarici sistemin mürəkkəbliyini gizlədir
- **Translator** — Xarici DTO ↔ Domain Object çevirməsi
- **Gateway** — Xarici sistemlə ünsiyyət nöqtəsi

**Nə vaxt lazımdır:**
- Legacy sistem ilə inteqrasiya (köhnə DB, köhnə API)
- Üçüncü tərəf servis ilə inteqrasiya (ERP, CRM, payment provider)
- Fərqli domain language-li başqa Bounded Context ilə ünsiyyət
- "Big ball of mud" sistemdən köçüb gəlirsən

---

## Laravel

```
app/
├── Domain/
│   ├── Ordering/
│   │   ├── Aggregate/
│   │   │   └── Order/
│   │   │       ├── Order.php                  # Clean domain model
│   │   │       └── OrderLine.php
│   │   ├── ValueObject/
│   │   │   ├── OrderId.php
│   │   │   ├── Money.php                      # Domain-specific money concept
│   │   │   └── ProductId.php
│   │   └── Port/
│   │       ├── InventoryPort.php              # What ordering context needs
│   │       └── LegacyErpPort.php              # Port for legacy ERP
│   │
│   └── Inventory/                             # External/Legacy context
│       └── (legacy code, different model)
│
├── Infrastructure/
│   └── AntiCorruption/                        # ACL lives here
│       │
│       ├── LegacyErp/                         # Legacy ERP integration
│       │   ├── LegacyErpGateway.php           # Communicates with legacy ERP
│       │   ├── LegacyErpAdapter.php           # Implements LegacyErpPort interface
│       │   ├── Translator/
│       │   │   ├── LegacyOrderTranslator.php  # Legacy order format → domain Order
│       │   │   ├── LegacyProductTranslator.php
│       │   │   └── LegacyCustomerTranslator.php
│       │   ├── DTO/                           # External system's data structures
│       │   │   ├── LegacyOrderDto.php
│       │   │   ├── LegacyProductDto.php
│       │   │   └── LegacyCustomerDto.php
│       │   └── Client/
│       │       └── ErpHttpClient.php          # Low-level HTTP calls
│       │
│       ├── PaymentProvider/                   # Stripe/PayPal ACL
│       │   ├── StripeGateway.php
│       │   ├── StripeAdapter.php              # Implements PaymentPort
│       │   ├── Translator/
│       │   │   ├── StripeChargeTranslator.php # Stripe Charge → domain Payment
│       │   │   └── StripeErrorTranslator.php
│       │   └── DTO/
│       │       └── StripeChargeDto.php
│       │
│       └── CrmSystem/                        # External CRM ACL
│           ├── CrmGateway.php
│           ├── CrmAdapter.php                 # Implements CustomerPort
│           └── Translator/
│               └── CrmCustomerTranslator.php
│
└── Tests/
    └── Unit/
        └── Infrastructure/
            └── AntiCorruption/
                ├── LegacyOrderTranslatorTest.php
                └── StripeAdapterTest.php
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── domain/
│   ├── ordering/
│   │   ├── aggregate/
│   │   │   └── Order.java
│   │   ├── valueobject/
│   │   │   ├── OrderId.java
│   │   │   └── Money.java
│   │   └── port/
│   │       ├── InventoryPort.java             # Interface for inventory access
│   │       ├── PaymentPort.java               # Interface for payment
│   │       └── LegacyErpPort.java             # Interface for legacy ERP
│   │
│   └── shared/
│       └── Money.java
│
├── infrastructure/
│   └── acl/                                   # Anti-Corruption Layer
│       │
│       ├── legacyerp/
│       │   ├── LegacyErpGateway.java
│       │   ├── LegacyErpAdapter.java          # implements LegacyErpPort
│       │   ├── translator/
│       │   │   ├── LegacyOrderTranslator.java
│       │   │   ├── LegacyProductTranslator.java
│       │   │   └── LegacyCustomerTranslator.java
│       │   ├── dto/
│       │   │   ├── LegacyOrderDto.java
│       │   │   └── LegacyProductDto.java
│       │   └── client/
│       │       └── ErpRestClient.java         # Feign or RestTemplate
│       │
│       ├── payment/
│       │   ├── stripe/
│       │   │   ├── StripeGateway.java
│       │   │   ├── StripePaymentAdapter.java  # implements PaymentPort
│       │   │   ├── translator/
│       │   │   │   └── StripeChargeTranslator.java
│       │   │   └── dto/
│       │   │       └── StripeChargeDto.java
│       │   └── paypal/
│       │       ├── PayPalGateway.java
│       │       ├── PayPalPaymentAdapter.java
│       │       └── translator/
│       │           └── PayPalTransactionTranslator.java
│       │
│       └── crm/
│           ├── CrmGateway.java
│           ├── CrmCustomerAdapter.java        # implements CustomerPort
│           └── translator/
│               └── CrmCustomerTranslator.java
│
└── config/
    └── AclConfig.java                         # Wires adapters to ports
```

---

## Golang

```
project/
├── internal/
│   ├── domain/
│   │   ├── ordering/
│   │   │   ├── order.go
│   │   │   ├── money.go
│   │   │   └── port/
│   │   │       ├── inventory.go               # Interface: what ordering needs
│   │   │       ├── payment.go                 # Interface: payment operations
│   │   │       └── legacy_erp.go              # Interface: legacy ERP access
│   │   └── shared/
│   │       └── money.go
│   │
│   └── infrastructure/
│       └── acl/                               # Anti-Corruption Layer
│           │
│           ├── legacyerp/
│           │   ├── gateway.go                 # HTTP calls to legacy ERP
│           │   ├── adapter.go                 # Implements legacy_erp.Port
│           │   ├── translator/
│           │   │   ├── order_translator.go    # legacy DTO → domain Order
│           │   │   └── product_translator.go
│           │   └── dto/
│           │       ├── legacy_order.go
│           │       └── legacy_product.go
│           │
│           ├── stripe/
│           │   ├── gateway.go
│           │   ├── adapter.go                 # Implements payment.Port
│           │   ├── translator/
│           │   │   └── charge_translator.go
│           │   └── dto/
│           │       └── stripe_charge.go
│           │
│           └── crm/
│               ├── gateway.go
│               ├── adapter.go                 # Implements customer port
│               └── translator/
│                   └── customer_translator.go
│
├── pkg/
└── go.mod
```

---

## Translator Nümunəsi (Laravel)

```php
// Xarici ERP modeli → Domain modeli

class LegacyOrderTranslator
{
    public function toDomain(LegacyOrderDto $dto): Order
    {
        return new Order(
            id: OrderId::fromString($dto->order_ref),        // "ORD-001" → OrderId
            total: Money::fromCents($dto->total_amount_cents), // cents → Money VO
            status: $this->mapStatus($dto->status_code),      // "S" → OrderStatus::Shipped
            lines: array_map(
                fn($line) => $this->translateLine($line),
                $dto->line_items
            )
        );
    }

    private function mapStatus(string $legacyCode): OrderStatus
    {
        return match($legacyCode) {
            'P'  => OrderStatus::Pending,
            'C'  => OrderStatus::Confirmed,
            'S'  => OrderStatus::Shipped,
            'X'  => OrderStatus::Cancelled,
            default => throw new UnknownLegacyStatusException($legacyCode)
        };
    }
}
```
