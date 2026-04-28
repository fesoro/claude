# Data Mapper vs Active Record (Senior)

## İcmal

Data Mapper və Active Record — object-ləri database-ə necə map etmək (ORM pattern) haqqında iki fərqli yanaşmadır. **Active Record**: model özü DB-ni bilir — `$user->save()`, `User::find(1)`. **Data Mapper**: model DB-ni bilmir; ayrıca Mapper/Repository bu işi görür. Laravel Eloquent Active Record, Doctrine ORM isə Data Mapper nümunəsidir.

## Niyə Vacibdir

5+ illik Laravel developer kimi "Eloquent ilə hər şeyi edirəm" yanaşması mürəkkəb domain-lərdə çatışmazlıqlarını ortaya çıxarır. `User::where('status', 'active')->get()` query-ləri hər yerdə tekrarlanır (query scope unutulur); domain entity-lər DB sxemasına bağlıdır; test üçün real DB lazım olur. Data Mapper bu problemləri həll edir, lakin boilerplate artırır. Seçim doğru kontekstdə etmək lazımdır.

## Əsas Anlayışlar

- **Active Record**: Martin Fowler-in "Patterns of Enterprise Application Architecture" kitabında təqdim edilib; model həm domain logic, həm persistence daşıyır; `find()`, `save()`, `delete()` modelin özündə
- **Data Mapper**: model yalnız domain logic-i bilir; ayrıca Mapper class domain object ↔ DB row çevrilməsini edir; model DB-nin mövcudluğundan xəbərsizdir
- **Eloquent**: Laravel-in Active Record implementasiyası; `extends Model` — hər model DB cədvəlinə birbaşa bağlıdır
- **Doctrine ORM**: PHP-nin ən məşhur Data Mapper implementasiyası; `EntityManager`, annotation/attribute mapping, `@Entity`, `@Column`
- **Repository Pattern**: Data Mapper ilə birlikdə istifadə olunur; domain layer-da interface, infrastructure-da implementasiya
- **Anemic Domain**: Data Mapper yanlış istifadə edildikdə yaranan problem — domain object yalnız getter/setter, bütün logic service-lərdə
- **Hydration**: DB row-dan domain object yaratma prosesi (Data Mapper-in əsas əməliyyatı)

## Praktik Baxış

- **Active Record nə vaxt**: sadə CRUD Laravel app-ları, admin panellər, prototipler, az sayda domain rule, sürətli development
- **Data Mapper nə vaxt**: mürəkkəb domain logic, DDD tətbiqi, framework müstəqil domain istəyi, uzun ömürlü enterprise layihə
- **Trade-off**: AR = developer experience (az kod, sürət), DM = domain purity (test edilə bilər, framework müstəqil)
- **Hansı hallarda AR istifadə etməmək**: domain entity-lər həm Eloquent, həm başqa sistem (CLI, queue, API) tərəfindən fərqli şəkildə istifadə olunursa; domain logic DB sxemasına bağlı olmamalıdırsa
- **Common mistakes**: Eloquent model-ə `extends Model` əlavə edib onu domain entity kimi treat etmək; `User::where(...)` query-ni hər yerdə tekrarlamaq (scope istifadə etməmək)

### Anti-Pattern Nə Zaman Olur?

**Eloquent-in hər yerə sızdırılması**: `User::where('status', 'active')->where('role', 'admin')->get()` eyni query 10 fərqli controller və service-də tekrarlanır. Query scope-lar (`scopeActive()`, `scopeAdmin()`) unudulur. Daha pis versiyası: bu query-lər blade template-lərdə yazılır. Həll: Repository Pattern + Query Scope.

**Active Record-u domain entity kimi istifadə etmək**: `$order = new Order(); $order->save(); $order->items()->attach(...)` — controller-də business rule yoxdur, validation-lar dağınıqdır. Eloquent model-i domain layer-a qarışdırmaq `extends Model`-in layihənin hər yerinə sızmasına gətirir. DDD tətbiq edərkən Eloquent yalnız Infrastructure-da Mapper rolunu oynamalıdır.

---

## Nümunələr

### Ümumi Nümunə

Bir `Order` entity-sini hər iki yanaşma ilə düşünün:

**Active Record yanaşması**: `Order` model özü DB-ni bilir. `$order->save()` birbaşa INSERT edir. `Order::where('status', 'pending')->get()` birbaşa SELECT edir. Sadə, az kod — kiçik layihə üçün ideal.

**Data Mapper yanaşması**: `Order` class — plain PHP, DB-dən xəbərsiz. `OrderRepository::save($order)` — Mapper ayrıca `order` ↔ `OrderModel` çevrilməsini edir. Test üçün `InMemoryOrderRepository` yazmaq mümkündür.

### Kod Nümunəsi

**Active Record — Laravel Eloquent:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    protected $fillable = ['user_id', 'status', 'total', 'currency'];

    // ✅ Query Scope — sorğunu model içinə kapsülləyir
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // Relationship
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Domain behavior — Active Record-da model içinə yazılır
    public function confirm(): void
    {
        if ($this->status !== 'draft') {
            throw new \DomainException('Yalnız draft sifariş təsdiqlənə bilər');
        }
        $this->status = 'confirmed';
    }

    public function cancel(): void
    {
        if (in_array($this->status, ['shipped', 'delivered'])) {
            throw new \DomainException('Göndərilmiş sifariş ləğv edilə bilməz');
        }
        $this->status = 'cancelled';
    }
}

// İstifadə:
$order = Order::pending()->forUser($userId)->with('items')->first();
$order->confirm();
$order->save(); // DB-ə yazır

// ❌ Anti-pattern — scope istifadə etmədən
$order = Order::where('status', 'pending')
              ->where('user_id', $userId)
              ->first(); // Bu query hər yerdə tekrarlanır
```

**Data Mapper — Doctrine ilə PHP:**

```php
<?php

// Domain Entity — DB-dən tamamilə müstəqil
namespace App\Domain\Order;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'integer')]  // Sentdə saxlanır
    private int $totalAmount;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'])]
    private array $items = [];

    private function __construct(
        private readonly int $userId,
        string $currency = 'AZN'
    ) {
        $this->status = 'draft';
        $this->totalAmount = 0;
        $this->currency = $currency;
    }

    public static function create(int $userId, string $currency = 'AZN'): self
    {
        return new self($userId, $currency);
    }

    public function addItem(int $productId, int $quantity, int $priceInCents): void
    {
        if ($this->status !== 'draft') {
            throw new \DomainException('Yalnız draft sifarişə məhsul əlavə edilə bilər');
        }
        $this->items[] = new OrderItem($this, $productId, $quantity, $priceInCents);
        $this->totalAmount += $priceInCents * $quantity;
    }

    public function confirm(): void
    {
        if (empty($this->items)) {
            throw new \DomainException('Boş sifariş təsdiqlənə bilməz');
        }
        if ($this->status !== 'draft') {
            throw new \DomainException('Yalnız draft sifariş təsdiqlənə bilər');
        }
        $this->status = 'confirmed';
    }

    public function getId(): ?int { return $this->id; }
    public function getStatus(): string { return $this->status; }
    public function getTotal(): int { return $this->totalAmount; }
}

// Repository Interface — Domain layer-da
namespace App\Domain\Order;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;
    public function findPendingForUser(int $userId): array;
    public function save(Order $order): void;
}

// Doctrine Repository — Infrastructure layer-da
namespace App\Infrastructure\Persistence;

use App\Domain\Order\Order;
use App\Domain\Order\OrderRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findById(int $id): ?Order
    {
        return $this->em->find(Order::class, $id);
    }

    public function findPendingForUser(int $userId): array
    {
        return $this->em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->where('o.userId = :userId')
            ->andWhere('o.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', 'draft')
            ->getQuery()
            ->getResult();
    }

    public function save(Order $order): void
    {
        $this->em->persist($order);
        $this->em->flush();
    }
}

// İstifadə:
$order = Order::create($userId);
$order->addItem($productId, 2, 5000);
$order->confirm();
$repository->save($order); // EntityManager persist + flush
```

**Hybrid yanaşma — Eloquent + Repository Pattern:**

```php
<?php

// Eloquent model — yalnız Infrastructure-da
namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class OrderModel extends Model
{
    protected $table = 'orders';
    protected $fillable = ['user_id', 'status', 'total_amount', 'currency'];

    public function items()
    {
        return $this->hasMany(OrderItemModel::class, 'order_id');
    }
}

// Domain Entity — plain PHP, DB bilmir
namespace App\Domain\Order;

final class Order
{
    private array $items = [];
    private array $domainEvents = [];

    private function __construct(
        private ?int $id,
        private readonly int $userId,
        private string $status,
        private int $totalAmount,
        private readonly string $currency
    ) {}

    public static function create(int $userId, string $currency = 'AZN'): self
    {
        $order = new self(null, $userId, 'draft', 0, $currency);
        $order->domainEvents[] = new OrderCreatedEvent($userId);
        return $order;
    }

    public static function reconstitute(
        int $id, int $userId, string $status,
        int $totalAmount, string $currency, array $items
    ): self {
        $order = new self($id, $userId, $status, $totalAmount, $currency);
        $order->items = $items;
        return $order;
    }

    public function confirm(): void
    {
        if (empty($this->items)) {
            throw new \DomainException('Boş sifariş təsdiqlənə bilməz');
        }
        $this->status = 'confirmed';
        $this->domainEvents[] = new OrderConfirmedEvent($this->id);
    }

    public function pullEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    public function getId(): ?int { return $this->id; }
    public function getStatus(): string { return $this->status; }
    public function getTotal(): int { return $this->totalAmount; }
    public function getUserId(): int { return $this->userId; }
}

// Repository — Eloquent istifadə edərək Domain Entity qaytarır
namespace App\Infrastructure\Repositories;

use App\Domain\Order\Order;
use App\Domain\Order\OrderRepositoryInterface;
use App\Infrastructure\Models\OrderModel;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        $model = OrderModel::with('items')->find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function findPendingForUser(int $userId): array
    {
        return OrderModel::where('user_id', $userId)
            ->where('status', 'draft')
            ->with('items')
            ->get()
            ->map(fn($m) => $this->toDomain($m))
            ->all();
    }

    public function save(Order $order): void
    {
        $model = OrderModel::updateOrCreate(
            ['id' => $order->getId()],
            [
                'user_id'      => $order->getUserId(),
                'status'       => $order->getStatus(),
                'total_amount' => $order->getTotal(),
            ]
        );

        // Domain event-ləri dispatch et
        foreach ($order->pullEvents() as $event) {
            event($event);
        }
    }

    private function toDomain(OrderModel $model): Order
    {
        return Order::reconstitute(
            id:          $model->id,
            userId:      $model->user_id,
            status:      $model->status,
            totalAmount: $model->total_amount,
            currency:    $model->currency ?? 'AZN',
            items:       $model->items->toArray(),
        );
    }
}
```

**Eyni Order feature — AR vs DM fərqi:**

```php
<?php

// === Active Record — OrderController ===
class OrderController extends Controller
{
    public function confirm(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->confirm();
        $order->save();

        return response()->json(['status' => $order->status]);
    }
}

// === Data Mapper — OrderController ===
class OrderController extends Controller
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ConfirmOrderService $service
    ) {}

    public function confirm(int $id): JsonResponse
    {
        $order = $this->orders->findById($id);
        if (!$order) abort(404);

        $this->service->execute($order);  // Domain logic service-də

        return response()->json(['status' => $order->getStatus()]);
    }
}
```

## Praktik Tapşırıqlar

1. Mövcud bir Eloquent model-i götürün; `User::where(...)` query-lərini tapın; hər birinə uyğun `scopeActive()`, `scopeForRole()` kimi scope-lar yazın; controller-ləri yenidən yaradın
2. Hybrid yanaşma: `Order` domain entity-sini plain PHP class kimi yazın (`extends Model` yoxdur); `EloquentOrderRepository`-ni `toDomain()` mapper ilə implement edin; `InMemoryOrderRepository` yazın; unit test-lər `InMemoryOrderRepository` ilə çalışsın
3. Doctrine sınaqlaması: sadə bir Laravel layihəsindəki modulu Doctrine ORM ilə yenidən yaradın; `EntityManager` + `@Entity` annotation; Eloquent ilə müqayisə edin; hansı hallarda Doctrine daha rahatdır?
4. Decision guide: öz layihənizə baxın — neçə domain rule var? DB sxeması dəyişdikdə neçə class dəyişir? Test üçün real DB lazımdırmı? Bu sualların cavabına görə hansı pattern daha uyğundur qərar verin

## Ətraflı Qeydlər

**Doctrine əsasları PHP-də:**

```php
// composer require doctrine/orm

// config/doctrine.php (laravel-doctrine paketindən)
// entities:
//   App\Domain\: src/Domain/

// Entity Manager — Data Mapper-in əsas aləti
$em = app(EntityManagerInterface::class);

// Persist (INSERT ya da UPDATE — ID-dən asılı)
$order = Order::create($userId);
$em->persist($order);
$em->flush(); // Real SQL buraya qədər çalışmır

// Find
$order = $em->find(Order::class, $id);

// DQL (Doctrine Query Language)
$orders = $em->createQuery(
    'SELECT o FROM App\Domain\Order\Order o WHERE o.status = :status'
)->setParameter('status', 'draft')->getResult();

// Repository vasitəsilə
$orderRepo = $em->getRepository(Order::class);
$order = $orderRepo->find($id);
```

**Active Record-da Trait ilə domain behavior əlavə etmək:**

```php
// Eloquent model-ə domain behavior əlavə etmək üçün Trait
trait HasOrderBehavior
{
    public function confirm(): void
    {
        if ($this->status !== 'draft') {
            throw new \DomainException('...');
        }
        $this->status = 'confirmed';
        $this->save(); // AR-da save buradadır
    }
}

class Order extends Model
{
    use HasOrderBehavior;
}
```

## Əlaqəli Mövzular

- [Repository Pattern](../laravel/01-repository-pattern.md) — Data Mapper yanaşmasının əsas tamamlayıcısı
- [Service Layer](../laravel/02-service-layer.md) — Data Mapper ilə birlikdə Application Service
- [DDD](../ddd/01-ddd.md) — Data Mapper DDD-nin domain purity tələbini ödəyir
- [Aggregates](../ddd/04-ddd-aggregates.md) — Aggregate root-lar Data Mapper ilə daha təmiz idarə olunur
- [Value Objects](../ddd/02-value-objects.md) — Domain entity-lərdə istifadə olunan Value Object-lər
- [Hexagonal Architecture](05-hexagonal-architecture.md) — Domain layer-da Data Mapper, Infrastructure-da Adapter
- [Onion Architecture](06-onion-architecture.md) — Onion-da domain entity plain PHP, Infrastructure-da Mapper
- [Unit of Work](../laravel/14-unit-of-work.md) — Doctrine-in Unit of Work pattern-i EntityManager vasitəsilə
