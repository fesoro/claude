# Clean Architecture (Senior ⭐⭐⭐)

## İcmal
Clean Architecture Robert C. Martin (Uncle Bob) tərəfindən təklif edilmiş, biznes məntiqini framework, database və UI-dan izole edən arxitektura yanaşmasıdır. Interview-da bu mövzu dependency inversion, testability və maintainability haqqında düşüncəni ölçür. Laravel, Spring kimi framework-lərin üzərindəki mühəndislik qatını başa düşmək üçün vacibdir.

## Niyə Vacibdir
Framework-dən asılı kod test etmək çətindir, dəyişdirmək bahalıdır. Clean Architecture-ı başa düşən mühəndis Laravel-i Symfony ilə, MySQL-i PostgreSQL ilə əvəz edərkən biznes məntiqinə toxunmamalı olduğunu bilir. Bu, uzunmüddətli maintainability-nin fundamentidir. Böyük komandada hər developer öz layihəsini müstəqil inkişaf etdirə bilir — çünki layer-lər arası contract-lar aydındır.

## Əsas Anlayışlar

- **Dependency Rule**: Daxili layer-lər xarici layer-lərdən xəbərsiz olmalıdır — dependency yalnız içəriyə doğru axır
- **Entities layer**: Ən daxili layer — enterprise-wide biznes qaydaları, framework-dən tamamilə asılı deyil
- **Use Cases layer**: Application-specific biznes qaydaları — bir use case = bir iş axışı
- **Interface Adapters layer**: Controller, Presenter, Gateway — use case-ləri xarici dünya üçün uyğunlaşdırır
- **Frameworks & Drivers layer**: Ən xarici layer — Laravel, database, web, UI
- **Dependency Inversion Principle (DIP)**: Yüksək səviyyəli modul aşağı səviyyəli modula deyil, abstraksiyaya bağlıdır
- **Interactor**: Use case implementation-ı — input port-dan alır, output port-a verir
- **Port**: Interface — use case-in xarici aləmlə danışdığı müqavilə
- **Adapter**: Port-un konkret implementasiyası — məsələn, `EloquentOrderRepository` → `OrderRepository` port-u
- **Screaming Architecture**: Folder struktur bakanda nə görürsünüz? `Controllers/`? Yoxsa `Orders/`, `Payments/`? İkincisi daha yaxşıdır
- **Test kolaylığı**: Bütün xarici asılılıqlar interface arxasındadır — mock etmək asandır
- **SOLID**: Clean Architecture SOLID prinsipləri üzərində qurulub — xüsusilə SRP, OCP, DIP
- **Hexagonal Architecture fərqi**: Eyni fikir, fərqli metafora — Ports & Adapters (bax: `04-hexagonal-architecture.md`)
- **Over-engineering riski**: Kiçik layihə üçün Clean Architecture lazımsız complexity əlavə edə bilər

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Clean Architecture mövzusunda ən çox yayılmış sual "bunu Laravel-də necə tətbiq edərdiniz?" kimidir. Əsas nöqtə: Laravel-i daxili layer-lərə sızdırmamaq. `Request` obyekti Use Case-ə girməməlidir — DTO istifadə edin.

**Follow-up suallar:**
- "Use Case layer Application Service-dən nə ilə fərqlənir?"
- "Framework-ə dependency olmadan test necə yazılır?"
- "Clean Architecture-ı nə vaxt istifadə etməmək lazımdır?"
- "Circular dependency problemi yarana bilərmi?"

**Ümumi səhvlər:**
- Hər yerdə interface yaratmaq — lazımsız abstraksiya complexity artırır
- Use Case içindən `Request`, `Response` Laravel class-larını çağırmaq
- Layer-ləri fiziki folder ilə limit etmək — layer = sərhəd, folder = sadəcə organizasiya
- Entity-ni Eloquent model ilə eyniləşdirmək — bunlar fərqli qatlardadır

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab konkret nümunə ilə layer violation-ı göstərir və düzgün həllini izah edir. "Framework-dən çıxmaq lazım olsa kodun neçə %-ni dəyişmək lazım gələrdi?" sualına cavab verə bilmək.

## Nümunələr

### Tipik Interview Sualı
"Clean Architecture-ı Laravel layihəsinə necə tətbiq edərdiniz? Konkret nümunə verin."

### Güclü Cavab
"Əsas ideya: Use Case layer Laravel-i bilmir. Controller Request-dən DTO yaradır, Use Case-ə ötürür. Use Case yalnız Repository interface-ə baxır, Eloquent-dən xəbərsizdir. Eloquent Repository interface-i implement edir — bu dependency inversion. Beləliklə Use Case-i test edərkən real DB lazım deyil, sadə in-memory repository kifayətdir."

### Kod / Konfiqurasiya Nümunəsi

```php
// 1. ENTITIES LAYER — Framework yoxdur
class Order
{
    private array $items = [];
    private OrderStatus $status;

    public function __construct(
        public readonly OrderId $id,
        public readonly CustomerId $customerId
    ) {
        $this->status = OrderStatus::Draft;
    }

    public function addItem(Product $product, int $qty): void
    {
        $this->items[] = new OrderItem($product, $qty);
    }

    public function place(): void
    {
        if (empty($this->items)) {
            throw new \DomainException('Order cannot be empty');
        }
        $this->status = OrderStatus::Placed;
    }
}

// 2. USE CASES LAYER — Port-lar (interfaces) vasitəsilə danışır
interface OrderRepositoryPort   // Output port
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
}

interface StockCheckerPort      // Output port
{
    public function isAvailable(ProductId $id, int $qty): bool;
}

// Input DTO — Laravel Request yoxdur
final class PlaceOrderInput
{
    public function __construct(
        public readonly string $customerId,
        public readonly array $items   // [['product_id' => ..., 'qty' => ...]]
    ) {}
}

final class PlaceOrderOutput
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $status
    ) {}
}

// Interactor — Use Case implementasiyası
class PlaceOrderInteractor
{
    public function __construct(
        private OrderRepositoryPort $orders,
        private StockCheckerPort $stock
    ) {}

    public function execute(PlaceOrderInput $input): PlaceOrderOutput
    {
        $order = new Order(
            OrderId::generate(),
            CustomerId::from($input->customerId)
        );

        foreach ($input->items as $item) {
            $productId = ProductId::from($item['product_id']);

            if (!$this->stock->isAvailable($productId, $item['qty'])) {
                throw new \DomainException("Product {$item['product_id']} out of stock");
            }

            $order->addItem(new Product($productId), $item['qty']);
        }

        $order->place();
        $this->orders->save($order);

        return new PlaceOrderOutput((string) $order->id, 'placed');
    }
}

// 3. INTERFACE ADAPTERS LAYER — Laravel Controller
class OrderController extends Controller
{
    public function __construct(
        private PlaceOrderInteractor $placeOrder
    ) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        // Request → Input DTO (Laravel burada qalır)
        $input = new PlaceOrderInput(
            customerId: $request->user()->id,
            items: $request->validated('items')
        );

        $output = $this->placeOrder->execute($input);

        // Output DTO → JSON Response
        return response()->json([
            'order_id' => $output->orderId,
            'status'   => $output->status,
        ], 201);
    }
}

// 4. FRAMEWORKS LAYER — Eloquent Repository (adapter)
class EloquentOrderRepository implements OrderRepositoryPort
{
    public function save(Order $order): void
    {
        // Domain Order → Eloquent model mapping
        OrderModel::updateOrCreate(
            ['id' => (string) $order->id],
            [
                'customer_id' => (string) $order->customerId,
                'status'      => $order->status()->value,
            ]
        );
    }

    public function findById(OrderId $id): ?Order
    {
        $model = OrderModel::find((string) $id);
        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(OrderModel $model): Order
    {
        // Eloquent model → Domain Order mapping
        $order = new Order(
            OrderId::from($model->id),
            CustomerId::from($model->customer_id)
        );
        // ... items yükləmək
        return $order;
    }
}

// Test — Laravel olmadan
class PlaceOrderTest extends TestCase
{
    public function test_places_order_successfully(): void
    {
        $orders  = new InMemoryOrderRepository();  // fake adapter
        $stock   = new AlwaysAvailableStockChecker();

        $interactor = new PlaceOrderInteractor($orders, $stock);

        $output = $interactor->execute(new PlaceOrderInput(
            customerId: 'user-123',
            items: [['product_id' => 'prod-1', 'qty' => 2]]
        ));

        $this->assertSame('placed', $output->status);
        $this->assertNotEmpty($orders->findById(OrderId::from($output->orderId)));
    }
}
```

```
Folder struktur (Screaming Architecture):
app/
  Orders/
    Domain/
      Order.php           ← Entity
      OrderItem.php
      OrderId.php
    Application/
      PlaceOrder/
        PlaceOrderInteractor.php
        PlaceOrderInput.php
        PlaceOrderOutput.php
      Ports/
        OrderRepositoryPort.php
        StockCheckerPort.php
    Infrastructure/
      EloquentOrderRepository.php
      HttpStockChecker.php
    Presentation/
      OrderController.php
      PlaceOrderRequest.php
```

## Praktik Tapşırıqlar

- Mövcud Controller-dən biznes məntiqini çıxarıb Use Case-ə köçürün
- Use Case-i framework olmadan test edin
- "Layer violation" nümunəsi tapın — Use Case-dən `Illuminate\Http\Request` çağrılırmı?
- InMemory Repository yazın, Use Case-i onunla test edin
- Screaming Architecture: folder strukturunuzu baxanda nə görürsünüz?

## Əlaqəli Mövzular

- `04-hexagonal-architecture.md` — Eyni konseptin Ports & Adapters versiyası
- `02-domain-driven-design.md` — Domain layer daha dərin
- `06-cqrs-architecture.md` — Clean Architecture + CQRS
- `01-monolith-vs-microservices.md` — Arxitektura seçimi
