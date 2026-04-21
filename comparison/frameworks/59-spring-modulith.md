# Spring Modulith — Dərin Müqayisə

## Giriş

**Modular monolith** mikroservislərin mürəkkəbliyindən qaçıb tək deployment unit-də qalmaq, amma eyni zamanda kodda aydın sərhədlər saxlamaq yanaşmasıdır. **Spring Modulith** (rəsmi Spring Team paketi, 1.x) bunu dəstəkləyir — paket strukturunu modul kimi qəbul edir, moduları bir-biri ilə event-driven şəkildə əlaqələndirir, ArchUnit ilə sərhəd pozuntularını avtomatik yoxlayır, hətta PlantUML/C4 diagram generate edir.

Laravel-də rəsmi ekvivalent yoxdur. Seçimlər: `nwidart/laravel-modules` (multi-package), əl ilə `app/Domain/` strukturu, `spatie/event-sourcing`, sərhəd yoxlama üçün `qossmiq/deptrac-laravel` və ya `phparkitect`. Bounded context Symfony Bundle üslubuna yaxın yazılır, hər modulun öz service provider-i olur.

---

## Spring-də istifadəsi

### 1) Paket asılılıqları

```xml
<dependencies>
    <dependency>
        <groupId>org.springframework.modulith</groupId>
        <artifactId>spring-modulith-starter-core</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.modulith</groupId>
        <artifactId>spring-modulith-starter-jpa</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.modulith</groupId>
        <artifactId>spring-modulith-events-jpa</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.modulith</groupId>
        <artifactId>spring-modulith-docs</artifactId>
        <scope>test</scope>
    </dependency>
    <dependency>
        <groupId>org.springframework.modulith</groupId>
        <artifactId>spring-modulith-starter-test</artifactId>
        <scope>test</scope>
    </dependency>
</dependencies>

<dependencyManagement>
    <dependencies>
        <dependency>
            <groupId>org.springframework.modulith</groupId>
            <artifactId>spring-modulith-bom</artifactId>
            <version>1.3.1</version>
            <type>pom</type>
            <scope>import</scope>
        </dependency>
    </dependencies>
</dependencyManagement>
```

### 2) Paket əsaslı modul strukturu

Spring Modulith paket-level modul qəbul edir. Tətbiq base paketin altında hər top-level paket ayrı moduldur.

```
com.example.shop
├── ShopApplication.java
├── orders               <-- module "orders"
│   ├── package-info.java
│   ├── Order.java                   (public API — modul xaricdən görür)
│   ├── OrderService.java            (public API)
│   ├── events
│   │   └── OrderPlaced.java         (public event)
│   └── internal                     <-- modul daxili
│       ├── OrderRepository.java
│       ├── OrderValidator.java
│       └── JpaOrderEntity.java
├── payments             <-- module "payments"
│   ├── package-info.java
│   ├── Payment.java
│   ├── PaymentService.java
│   └── internal
│       └── StripeGateway.java
├── inventory            <-- module "inventory"
│   └── ...
└── notifications        <-- module "notifications"
    └── ...
```

`package-info.java` ilə modul metadatası:

```java
@org.springframework.modulith.ApplicationModule(
    displayName = "Orders",
    allowedDependencies = {"payments", "inventory::events"}
)
package com.example.shop.orders;
```

Burada `payments` modulunun bütün public API-lərinə giriş verilib, `inventory` modulundan isə yalnız `events` subpackage-inə.

### 3) `@ApplicationModule` — expose types və internals

Default qayda: base paketdəki typelər public (modul üçün API), `internal` alt-paketdəki hər şey bağlıdır. `@Modulithic` tətbiqin root-unda yazılır:

```java
@SpringBootApplication
@org.springframework.modulith.Modulithic(
    systemName = "E-commerce Shop",
    sharedModules = {"common", "security"}
)
public class ShopApplication {
    public static void main(String[] args) {
        SpringApplication.run(ShopApplication.class, args);
    }
}
```

`sharedModules` — bütün modul-lara görünən shared utility-lər.

### 4) Event-driven inter-module communication

Moduların bir-birini birbaşa çağırması əvəzinə, hadisə publishing tövsiyə olunur.

```java
// orders module — public event
package com.example.shop.orders.events;

public record OrderPlaced(
    Long orderId,
    Long customerId,
    BigDecimal total,
    List<Item> items,
    Instant placedAt
) {}
```

Publish:

```java
// orders/OrderService.java
@Service
@RequiredArgsConstructor
public class OrderService {
    private final OrderRepository repo;
    private final ApplicationEventPublisher events;

    @Transactional
    public Order placeOrder(PlaceOrderCommand cmd) {
        Order order = new Order(cmd.customerId(), cmd.items(), cmd.total());
        repo.save(order);
        events.publishEvent(new OrderPlaced(
            order.getId(), order.getCustomerId(), order.getTotal(),
            order.getItems(), Instant.now()
        ));
        return order;
    }
}
```

Listen — başqa moduldan:

```java
// payments/PaymentOnOrderListener.java
@Component
@RequiredArgsConstructor
public class PaymentOnOrderListener {
    private final PaymentService payments;

    @ApplicationModuleListener
    void on(OrderPlaced event) {
        payments.initiate(event.orderId(), event.total());
    }
}
```

```java
// inventory/ReserveStockListener.java
@Component
public class ReserveStockListener {

    @ApplicationModuleListener
    void on(OrderPlaced event) {
        event.items().forEach(i -> reserve(i.sku(), i.quantity()));
    }
}
```

`@ApplicationModuleListener` — Spring-in `@TransactionalEventListener(phase = AFTER_COMMIT)` + `@Async` kombinasiyasıdır: publish edən modul transaction-ı commit olandan sonra ayrı thread-də işlənir. Bu, modul-lar arasında loose coupling verir.

### 5) Event publication registry — durable events

Default `@ApplicationEventPublisher` yaddaşdadır — tətbiq çökərsə event itir. **Event Publication Registry** (JDBC və ya MongoDB) event-i publish edən modul transaction-ında DB-yə yazır, uğurla işlənəndən sonra silir.

```sql
CREATE TABLE event_publication (
    id                UUID PRIMARY KEY,
    listener_id       TEXT NOT NULL,
    event_type        TEXT NOT NULL,
    serialized_event  TEXT NOT NULL,
    publication_date  TIMESTAMP NOT NULL,
    completion_date   TIMESTAMP
);
```

Restart sonrası incomplete event-ləri republishe etmək üçün:

```java
@Configuration
@EnableScheduling
class ResubmitIncompleteEventsConfig {

    @Bean
    ApplicationRunner resubmit(IncompleteEventPublications incomplete) {
        return args -> incomplete.resubmitIncompletePublications(pub -> true);
    }

    // Vaxtaşırı retry
    @Scheduled(fixedDelay = 60_000)
    void retry(IncompleteEventPublications incomplete) {
        incomplete.resubmitIncompletePublicationsOlderThan(Duration.ofMinutes(1));
    }
}
```

### 6) Module testing

Modulun təkcə özünü test etmək — digər modullar load olunmur.

```java
@ApplicationModuleTest
class OrdersModuleTests {

    @Autowired OrderService orders;
    @Autowired AssertablePublishedEvents events;

    @Test
    void publishesOrderPlacedOnSuccess() {
        Order order = orders.placeOrder(new PlaceOrderCommand(1L, List.of(), BigDecimal.TEN));

        assertThat(events)
            .contains(OrderPlaced.class)
            .matching(OrderPlaced::orderId, order.getId());
    }

    @Test
    void doesNotDependOnNonAllowedModules() {
        // @ApplicationModuleTest avtomatik check edir
    }
}
```

### 7) Architecture verification

`ApplicationModules.of(...)` avtomatik verify edir:

```java
class ModularityTests {

    ApplicationModules modules = ApplicationModules.of(ShopApplication.class);

    @Test
    void verifiesModularStructure() {
        modules.verify();    // sərhəd pozuntusu varsa fail
    }

    @Test
    void listsModules() {
        modules.forEach(m -> System.out.println(m.getName() + " -> " + m.getBasePackage()));
    }
}
```

Əgər `payments` modulu `orders.internal` paketindən class istifadə edirsə, test uğursuz olar:

```
Module 'payments' depends on non-exposed type com.example.shop.orders.internal.OrderRepository
within module 'orders'!
```

### 8) ArchUnit inteqrasiya

```java
@AnalyzeClasses(packages = "com.example.shop")
class ArchRulesTest {

    @ArchTest
    static final ArchRule controllers_dont_access_repositories_directly =
        noClasses().that().resideInAPackage("..web..")
            .should().dependOnClassesThat().resideInAPackage("..internal..");

    @ArchTest
    static final ArchRule layered_architecture = layeredArchitecture()
        .consideringAllDependencies()
        .layer("Controllers").definedBy("..web..")
        .layer("Services").definedBy("..service..")
        .layer("Persistence").definedBy("..internal..")
        .whereLayer("Controllers").mayNotBeAccessedByAnyLayer()
        .whereLayer("Services").mayOnlyBeAccessedByLayers("Controllers")
        .whereLayer("Persistence").mayOnlyBeAccessedByLayers("Services");
}
```

### 9) Documentation generation (PlantUML + C4)

```java
class DocumentationTests {

    ApplicationModules modules = ApplicationModules.of(ShopApplication.class);

    @Test
    void writesDocumentation() {
        new Documenter(modules)
            .writeModulesAsPlantUml()
            .writeIndividualModulesAsPlantUml()
            .writeModuleCanvases();
    }
}
```

`target/spring-modulith-docs/` altında hər modul üçün PlantUML file generate olunur. C4 context diagram da çıxa bilər.

Tipik çıxış (orders modulu üçün):

```
@startuml
skinparam componentStyle uml2
package "Orders" {
    [OrderService]
    [OrderController]
}
package "Payments" {
    [PaymentService] as payments
}
[OrderService] ..> payments : uses
@enduml
```

### 10) Migration path — modular monolith → mikroservis

Spring Modulith modul sərhədlərini aydın saxlayır. Gələcəkdə bir modul (məsələn `payments`) ayrı mikroservis olmalıdırsa:

1. `payments` modulundakı event-lər artıq in-process (`ApplicationEventPublisher`) olmaqdan çıxıb Kafka/RabbitMQ-ya get.
2. Spring Modulith `spring-modulith-events-kafka` starter-i bu köçürməni dəstəkləyir — event-i eyni zamanda həm local bus-a, həm Kafka-ya göndərir.

```xml
<dependency>
    <groupId>org.springframework.modulith</groupId>
    <artifactId>spring-modulith-events-kafka</artifactId>
</dependency>
```

```java
@Externalized("orders.placed")
public record OrderPlaced(...) {}
```

---

## Laravel-də istifadəsi

### 1) `nwidart/laravel-modules` paketi

```bash
composer require nwidart/laravel-modules
php artisan module:make Orders
php artisan module:make Payments
```

Generate olunan struktur:

```
Modules/
├── Orders/
│   ├── Config/config.php
│   ├── Console/
│   ├── Database/
│   │   ├── Migrations/
│   │   └── Seeders/
│   ├── Entities/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   ├── Providers/
│   │   ├── OrdersServiceProvider.php
│   │   └── RouteServiceProvider.php
│   ├── Resources/views/
│   ├── Routes/
│   │   ├── api.php
│   │   └── web.php
│   ├── Tests/
│   ├── composer.json
│   └── module.json
└── Payments/
    └── ...
```

`modules_statuses.json` ilə modulları enable/disable etmək olur:

```json
{
    "Orders": true,
    "Payments": true,
    "Inventory": true
}
```

### 2) Custom Domain-Driven struktur (package-free)

```
app/
└── Domain/
    ├── Orders/
    │   ├── Order.php
    │   ├── OrderService.php
    │   ├── OrderRepository.php
    │   ├── Events/
    │   │   └── OrderPlaced.php
    │   └── Internal/
    │       ├── OrderValidator.php
    │       └── OrderMapper.php
    ├── Payments/
    │   ├── Payment.php
    │   ├── PaymentService.php
    │   └── Listeners/
    │       └── InitiatePaymentOnOrderPlaced.php
    └── Inventory/
        └── ...
```

Hər modulun öz service provider-i:

```php
namespace App\Domain\Orders;

use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderRepository::class, OrderRepositoryEloquent::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'orders');

        \Event::listen(\App\Domain\Orders\Events\OrderPlaced::class, [
            \App\Domain\Payments\Listeners\InitiatePaymentOnOrderPlaced::class,
            \App\Domain\Inventory\Listeners\ReserveStockOnOrderPlaced::class,
        ]);
    }
}
```

`config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    \App\Domain\Orders\OrdersServiceProvider::class,
    \App\Domain\Payments\PaymentsServiceProvider::class,
    \App\Domain\Inventory\InventoryServiceProvider::class,
])->toArray(),
```

### 3) Event + Listener — modul arası əlaqə

```php
// app/Domain/Orders/Events/OrderPlaced.php
namespace App\Domain\Orders\Events;

use App\Domain\Orders\Order;

class OrderPlaced
{
    public function __construct(public readonly Order $order) {}
}

// app/Domain/Orders/OrderService.php
namespace App\Domain\Orders;

use App\Domain\Orders\Events\OrderPlaced;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly OrderRepository $repo) {}

    public function placeOrder(PlaceOrderCommand $cmd): Order
    {
        return DB::transaction(function () use ($cmd) {
            $order = new Order($cmd->customerId, $cmd->items, $cmd->total);
            $this->repo->save($order);
            OrderPlaced::dispatch($order);
            return $order;
        });
    }
}

// app/Domain/Payments/Listeners/InitiatePaymentOnOrderPlaced.php
namespace App\Domain\Payments\Listeners;

use App\Domain\Orders\Events\OrderPlaced;
use App\Domain\Payments\PaymentService;

class InitiatePaymentOnOrderPlaced implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Queue\InteractsWithQueue;

    public function __construct(private readonly PaymentService $payments) {}

    public function handle(OrderPlaced $event): void
    {
        $this->payments->initiate($event->order->id, $event->order->total);
    }
}
```

Listener `ShouldQueue` olsa async işlər — Spring Modulith-in `@ApplicationModuleListener`-inə bənzəyir.

### 4) Durable events — Spatie Event Sourcing

```bash
composer require spatie/laravel-event-sourcing
```

```php
use Spatie\EventSourcing\StoredEvents\StoredEvent;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderPlaced extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly int $customerId,
        public readonly string $total,
    ) {}
}

// dispatch
OrderPlaced::dispatch('ord_123', 1, '100.00');
```

Event DB `stored_events` cədvəlinə yazılır — durable olur. Reactor-lar event-lərə reaksiya verir. Spring Modulith-in event publication registry analoqu.

### 5) Sərhəd yoxlaması — deptrac

```bash
composer require --dev qossmig/deptrac-shim
```

`deptrac.yaml`:

```yaml
deptrac:
    paths:
        - ./app/Domain
    layers:
        -   name: Orders
            collectors:
                - { type: className, regex: ^App\\Domain\\Orders\\ }
        -   name: Payments
            collectors:
                - { type: className, regex: ^App\\Domain\\Payments\\ }
        -   name: Inventory
            collectors:
                - { type: className, regex: ^App\\Domain\\Inventory\\ }
        -   name: OrdersInternal
            collectors:
                - { type: className, regex: ^App\\Domain\\Orders\\Internal\\ }
        -   name: OrdersEvents
            collectors:
                - { type: className, regex: ^App\\Domain\\Orders\\Events\\ }

    ruleset:
        Orders:
            - OrdersInternal
            - OrdersEvents
        Payments:
            - OrdersEvents      # yalnız events paket
            - InventoryEvents
        Inventory:
            - OrdersEvents
```

```bash
vendor/bin/deptrac analyse
```

Əgər `Payments` modulu `Orders\Internal` class-a reference edirsə — fail.

### 6) Alternative: phparkitect

```bash
composer require --dev phparkitect/phparkitect
```

```php
// phparkitect.php
use PHPArkitect\Expression\ForClasses\HaveNameMatching;
use PHPArkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use PHPArkitect\RuleBuilders\Architecture\Architecture;

return [
    Architecture::withComponents()
        ->component('Orders')->definedBy('App\\Domain\\Orders\\*')
        ->component('OrdersInternal')->definedBy('App\\Domain\\Orders\\Internal\\*')
        ->component('Payments')->definedBy('App\\Domain\\Payments\\*')
        ->rule('orders internal can only be used inside orders')
        ->where('OrdersInternal')->shouldOnlyBeUsedBy('Orders', 'OrdersInternal'),
];
```

### 7) Symfony Bundle-dən ilham — self-contained module

```php
// Modules/Orders/composer.json
{
    "name": "shop/orders-module",
    "autoload": {
        "psr-4": {
            "Shop\\Orders\\": "src/"
        }
    }
}
```

Tətbiqin kök `composer.json`-unda:

```json
{
    "repositories": [
        { "type": "path", "url": "modules/*" }
    ],
    "require": {
        "shop/orders-module": "*",
        "shop/payments-module": "*"
    }
}
```

Bu, Spring Modulith-in multi-module Maven layoutuna ən yaxın olan variantdır — amma hələ də sərhəd yoxlaması avtomatik deyil.

### 8) `composer.json`

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.0",
        "nwidart/laravel-modules": "^11.1",
        "spatie/laravel-event-sourcing": "^7.9"
    },
    "require-dev": {
        "qossmig/deptrac-shim": "^2.0",
        "phparkitect/phparkitect": "^0.4"
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Modulith | Laravel (ekosistema) |
|---|---|---|
| Rəsmi dəstək | Spring Team paket | 3rd party (nwidart, Spatie, deptrac) |
| Modul tərifi | Paket strukturuna əsaslanır | Folder + ServiceProvider |
| Sərhəd yoxlama | Built-in `modules.verify()` + ArchUnit | deptrac, phparkitect |
| Internal encapsulation | `internal` alt-paket (avtomatik) | Konvensiya + deptrac qayda |
| Event publication | `ApplicationEventPublisher` + registry | Laravel Event + Spatie durable |
| Async event listener | `@ApplicationModuleListener` | `ShouldQueue` listener |
| Transactional event | `@TransactionalEventListener` | DB transaction + event-after-commit |
| Event registry (durable) | `spring-modulith-events-jpa` | Spatie Event Sourcing |
| Documentation | PlantUML, C4 auto | Manual və ya external (deptrac graph) |
| Module test | `@ApplicationModuleTest` | Pest/PHPUnit + scope fakes |
| Migration to microservice | `@Externalized` + Kafka | Manual refactor |
| `@Modulithic` tətbiq annotasiya | Var | Yoxdur |
| Integration test slicing | `@ApplicationModuleTest` yalnız 1 modul | Custom test case |

---

## Niyə belə fərqlər var?

**Java paket sistemi və ArchUnit ənənəsi.** Java-da paket strukturu həmişə modul sərhədi kimi istifadə olunub. ArchUnit artıq bir neçə ildir istifadə olunur. Spring Modulith bu iki alətin üstünə strukturlaşdırılmış bir API qoydu — `ApplicationModules.of()` bir neçə sətrdə bütün arxitekturanı analiz edir.

**Laravel-in "tətbiq tək folder" anlayışı.** Laravel default-da `app/` altında `Http`, `Models`, `Services` — texniki bölünmə istifadə edir. Modular yanaşma (domain-driven) konvensiyadan kənardadır — ona görə hər komanda öz strukturunu qurur. `nwidart/laravel-modules` bir konvensiya təklif edir, amma hamı onu qəbul etməyib.

**Event publication registry niyə Laravel-də rəsmi yoxdur?** Laravel queue sistemi (`ShouldQueue` listener + failed_jobs cədvəli) durability verir — Spring Modulith-in registry-si kimi iş görür. Lakin aradakı fərq incədir: registry publisher transaction-ı ilə eyni DB-də saxlanır (yəni outbox pattern dəqiq implementasiyasıdır), Laravel queue isə publisher commit-indən sonra yazılır — qısa "publisher OK amma event göndərilmədi" pəncərəsi var. Bunu bağlamaq üçün `hirethunk/laravel-event-sourcing` və ya manual outbox paketləri var.

**`@ApplicationModuleListener` konsepti.** Bu annotasiya üç şeyi birləşdirir: (1) event-i başqa moduldan qəbul etmək, (2) publisher transaction commit-indən sonra icra, (3) ayrı thread-də. Laravel-də `ShouldQueue` + `afterCommit()` kombinasiyası ilə eyni effekt alınır — amma konfiqurasiyada açılır, annotasiyada deyil.

**Documentation auto-generation.** Java-nın zəngin reflection-i və paket strukturu sayəsində modul arası asılılıqları tapıb PlantUML-ə çevirmək asandır. PHP-də bu deptrac `--formatter=graphviz` ilə əldə olunur, amma Laravel Modulith-in `Documenter` seviyyəsində rəsmi deyil.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- **Spring Modulith** rəsmi paket, Spring Team tərəfindən saxlanır
- `@ApplicationModule` + `package-info.java` ilə paket-level modul metadatası
- `@Modulithic` annotasiya — tətbiqin modular olduğunu bildirmək
- `ApplicationModules.of()` — sistem analizi kod içindən
- `@ApplicationModuleTest` — tək modul integration test
- `@ApplicationModuleListener` — transactional + async event listener
- `@Externalized` annotasiya ilə event-i Kafka/RabbitMQ-ya publish
- `IncompleteEventPublications` API — incomplete event resubmission
- `Documenter` — PlantUML, C4, module canvas auto-generate
- `allowedDependencies` — modulun hansı digər modullara baxa bildiyini deklarasiya
- `AssertablePublishedEvents` test utility
- ArchUnit Spring Modulith-in bir hissəsi kimi

**Yalnız Laravel-də (ekosistema):**
- `nwidart/laravel-modules` — multi-folder modul generator (artisan komandaları ilə)
- Spatie Event Sourcing — durable events + aggregate root
- deptrac + phparkitect — sərhəd yoxlaması (framework-neytral)
- Laravel Sail + `modules_statuses.json` ilə modulları runtime-da disable
- Symfony Bundle tərzində öz `composer.json` olan modul (path repositories)

---

## Best Practices

1. **Modul adları domenə uyğun olsun** — `orders`, `payments`, `inventory` — texniki adlar (`api`, `db`) istifadə etmə.
2. **Modul arası birbaşa class çağırışdan qaç** — event publishing istifadə et.
3. **Public event-lər `events` alt-paketdə saxla** — modul xaricə ilk növbədə bunları verir.
4. **`internal` alt-paket modulun daxili detalıdır** — xarici heç bir modul bunu istifadə etməməlidir.
5. **Hər CI run-da `modules.verify()` işlət** — sərhəd pozuntusu gələn kimi yaxalanır.
6. **Event publication registry prod-da açıq olsun** — event itkisini aradan qaldırır.
7. **`@ApplicationModuleListener` üstüörtülmüş davranışları bil** — transaction commit + async işləyir.
8. **Laravel-də deptrac CI-da məcburi et** — PR-lar block olunsun əgər modul sərhədi pozulur.
9. **Hər modulun öz migration folder-i** — shared `database/migrations` istifadə etmə.
10. **Modul arası DB foreign key saxlama** — runtime boundary-ni pozur.
11. **Gələcək mikroservis kandidatını ilk günü işarələ** — `@Externalized` və ya Kafka publishing-ə hazırlıq.
12. **Documentation generation-u CI artefakt kimi sax** — PR review-da arxitektura görünür.

---

## Yekun

**Spring Modulith** rəsmi, zəngin və Spring Boot-un digər infrastruktur-u ilə birlikdə işləyən modular monolith həllidir. Paket əsaslı modul strukturu, event publication registry, `@ApplicationModuleTest`, PlantUML documentation generation — hamısı daxilindədir. Mikroservis miqrasiyası üçün aydın yol verir (`@Externalized` + Kafka bridge).

**Laravel**-də rəsmi ekvivalent yoxdur — `nwidart/laravel-modules` struktur verir, Spatie Event Sourcing durable event-ləri həll edir, deptrac/phparkitect sərhəd yoxlayır. Bu alətlər birlikdə istifadə olunarsa Spring Modulith funksionallığının böyük hissəsi əldə olunur, amma `@ApplicationModule` metadatası və auto-documentation səviyyəsi hələ yoxdur.

Seçim qaydası: **Spring-də** yeni monolith tətbiqdə Modulith default seçim olmalıdır — əlavə xərci azdır, gələcək qazancı böyükdür. **Laravel-də** kiçik tətbiq `app/Domain/` strukturu ilə kifayətlənir, orta-böyük tətbiq isə `nwidart/laravel-modules` + deptrac + Spatie event sourcing kombinasiyası ilə oxşar effekt verir. Hər iki halda da **modul arası event-driven loose coupling** əsas prinsipdir — onu saxlamaq arxitekturanın sabitliyini təmin edir.
