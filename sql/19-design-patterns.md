# Database Design Patterns

## 1. Repository Pattern

Database erisimini abstract edir. Business logic database texnologiyasindan asili olmur.

```php
// Interface
interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;
    public function findByStatus(string $status): Collection;
    public function save(Order $order): void;
    public function delete(int $id): void;
}

// Eloquent Implementation
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }
    
    public function findByStatus(string $status): Collection
    {
        return Order::where('status', $status)->get();
    }
    
    public function save(Order $order): void
    {
        $order->save();
    }
    
    public function delete(int $id): void
    {
        Order::destroy($id);
    }
}

// Service Provider-da bind et
$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);

// Istifade
class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orders
    ) {}
    
    public function getActiveOrders(): Collection
    {
        return $this->orders->findByStatus('active');
    }
}
```

**Ne vaxt istifade et:** Boyuk layihelerde, testability muhum olduqda, DB deyise bilecekse.
**Ne vaxt lazim deyil:** Kicik layihe, sadece Eloquent-den istifade olunur.

---

## 2. Active Record vs Data Mapper

### Active Record (Laravel Eloquent)

Model = Table row. Model ozu CRUD emeliyyatlarini edir.

```php
$user = new User();
$user->name = 'John';
$user->email = 'john@mail.com';
$user->save(); // Model ozu INSERT edir

$user = User::find(1);
$user->name = 'Jane';
$user->save(); // Model ozu UPDATE edir
```

**Ustunlukleri:** Sade, suretli development
**Menfi:** Business logic ve persistence qarisir

### Data Mapper (Doctrine ORM)

Entity ve database ayridir. Mapper arada kecid edir.

```php
// Entity (sade PHP class, DB-den xeberi yoxdur)
class User
{
    private int $id;
    private string $name;
    
    public function getName(): string { return $this->name; }
    public function changeName(string $name): void { $this->name = $name; }
}

// EntityManager CRUD edir
$user = $entityManager->find(User::class, 1);
$user->changeName('Jane');
$entityManager->flush(); // Deyisiklikleri DB-ye yazir
```

---

## 3. Soft Deletes

Row-u silmek yerine, silinmis kimi isarele.

```php
// Laravel Migration
$table->softDeletes(); // deleted_at column elave edir

// Model
class Order extends Model
{
    use SoftDeletes;
}

// Istifade
$order->delete();                    // deleted_at = NOW()
Order::all();                        // Yalniz silinmemisleri qaytarir
Order::withTrashed()->get();         // Hamisi (silinmisler de)
Order::onlyTrashed()->get();         // Yalniz silinmisler
$order->restore();                   // Geri qaytar
$order->forceDelete();               // Haqiqi silme

// Unique constraint problemi:
// email unique-dir, user silinir, sonra eyni email ile yenisi qeydiyyat kecir?
// Hell: Unique constraint-e deleted_at elave et
$table->unique(['email', 'deleted_at']);
```

---

## 4. Polymorphic Relations

Bir table bir nece ferqli table-a relation saxlayir.

```php
// Laravel Morphable
// comments table: commentable_type, commentable_id

class Comment extends Model
{
    public function commentable()
    {
        return $this->morphTo();
    }
}

class Post extends Model
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Video extends Model
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

// Istifade
$post->comments; // Post-un comment-leri
$video->comments; // Video-nun comment-leri
$comment->commentable; // Post ve ya Video qaytarir
```

**Database:**

```sql
CREATE TABLE comments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    body TEXT,
    commentable_type VARCHAR(255), -- 'App\Models\Post' ve ya 'App\Models\Video'
    commentable_id BIGINT UNSIGNED,
    INDEX idx_commentable (commentable_type, commentable_id)
);
```

**Problem:** FK constraint qoymaq mumkun deyil (commentable_id bir nece table-a isare edir).

---

## 5. EAV (Entity-Attribute-Value)

Dynamic/flexible attributes ucun. Her attribute ayri row-da saxlanilir.

```sql
CREATE TABLE product_attributes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED,
    attribute_name VARCHAR(100),  -- 'color', 'size', 'weight'
    attribute_value TEXT,
    INDEX idx_product (product_id),
    INDEX idx_name_value (attribute_name, attribute_value(100))
);

-- Product 1: color=red, size=XL
-- Product 2: color=blue, weight=500g (size yoxdur!)
```

```php
// Query (yavas ve murakkab!)
$redProducts = DB::table('products')
    ->join('product_attributes as pa', 'products.id', '=', 'pa.product_id')
    ->where('pa.attribute_name', 'color')
    ->where('pa.attribute_value', 'red')
    ->get();
```

**Ustunlukleri:** Flexible schema, ixtiyari attribute elave etmek olar
**Menfi:** Query murakkabligi, performance, data type yoxdur (her sey TEXT)

**Alternativ:** JSON column istifade et (xususen PostgreSQL JSONB)

```php
// JSON column (daha yaxsi alternativ)
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->json('attributes')->nullable();
});

// {"color": "red", "size": "XL", "weight": "500g"}
```

---

## 6. Event Sourcing

State saxlamaq yerine, butun **deyisiklikleri** (event-leri) saxlayirsan. State event-lerden hesablanir.

```sql
-- Events table
CREATE TABLE account_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT NOT NULL,
    event_type VARCHAR(50) NOT NULL,  -- 'deposited', 'withdrawn', 'transferred'
    payload JSON NOT NULL,             -- {"amount": 500, "to_account": 2}
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_id, created_at)
);

-- State yoxdur, event-lerden hesablanir:
SELECT 
    account_id,
    SUM(CASE 
        WHEN event_type = 'deposited' THEN JSON_EXTRACT(payload, '$.amount')
        WHEN event_type = 'withdrawn' THEN -JSON_EXTRACT(payload, '$.amount')
    END) AS balance
FROM account_events
WHERE account_id = 1
GROUP BY account_id;
```

```php
// PHP implementation
class BankAccount
{
    private array $events = [];
    private float $balance = 0;
    
    public function deposit(float $amount): void
    {
        $this->recordEvent('deposited', ['amount' => $amount]);
        $this->balance += $amount;
    }
    
    public function withdraw(float $amount): void
    {
        if ($amount > $this->balance) {
            throw new InsufficientFundsException();
        }
        $this->recordEvent('withdrawn', ['amount' => $amount]);
        $this->balance -= $amount;
    }
    
    private function recordEvent(string $type, array $payload): void
    {
        $this->events[] = [
            'event_type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ];
    }
    
    // Event-lerden state-i yeniden qur
    public static function reconstruct(array $events): self
    {
        $account = new self();
        foreach ($events as $event) {
            match ($event['event_type']) {
                'deposited' => $account->balance += $event['payload']['amount'],
                'withdrawn' => $account->balance -= $event['payload']['amount'],
            };
        }
        return $account;
    }
}
```

**Ne vaxt istifade et:** Audit trail lazim olduqda, financial systems, undo/redo lazim olduqda.
**Ne vaxt lazim deyil:** Sade CRUD app-lar (over-engineering).

---

## 7. CQRS (Command Query Responsibility Segregation)

Read ve Write ucun ferqli model-ler istifade et.

```php
// Write Model (Command) - normalized, consistent
class CreateOrderCommand
{
    public function handle(array $data): void
    {
        DB::transaction(function () use ($data) {
            $order = Order::create($data);
            foreach ($data['items'] as $item) {
                OrderItem::create([...]);
                Product::where('id', $item['product_id'])->decrement('stock', $item['quantity']);
            }
        });
        // Read model-i yenile (async)
        dispatch(new UpdateOrderReadModel($order->id));
    }
}

// Read Model (Query) - denormalized, suretli
// orders_read_model table: order_id, customer_name, items_json, total_amount, ...
class OrderQueryService
{
    public function getOrderList(array $filters): Collection
    {
        return DB::table('orders_read_model')
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        // JOIN yoxdur, tek table, suretli!
    }
}
```

---

## 8. Multi-Tenancy

Bir application-da bir nece musteri (tenant) var.

### Single Database, Shared Tables

```php
// Her table-da tenant_id var
class Order extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        });
        
        static::creating(function ($model) {
            $model->tenant_id = auth()->user()->tenant_id;
        });
    }
}
```

### Single Database, Separate Schemas (PostgreSQL)

```php
// Her tenant ucun ayri schema
DB::statement("SET search_path TO tenant_{$tenantId}");
```

### Separate Databases

```php
// Her tenant ucun ayri database
config(['database.connections.tenant.database' => "tenant_{$tenantId}"]);
DB::purge('tenant');
```

---

## Interview suallari

**Q: Repository pattern ne vaxt lazimdir?**
A: Boyuk layihelerde, business logic-i database-den ayirmaq lazim olduqda, unit test-de database-i mock etmek lazim olduqda. Kicik layihelerde Eloquent birbaşa istifade etmek daha sade ve pragmatikdir.

**Q: Event Sourcing vs traditional CRUD?**
A: CRUD: state saxlanir, kohne deyer itirilir, sade. Event Sourcing: butun tarixce saxlanir, ixtiyari zamana geri donmek olar, audit trail var, amma complex ve cox data tutur. Financial, healthcare ve ya audit-critical system-lerde istifade olunur.

**Q: Multi-tenancy ucun hansi yaanasma daha yaxsidir?**
A: Shared table: asan implement, resource efficient, amma tenant isolation zeifdir. Separate schema: orta isolation, orta complexity. Separate database: en guclu isolation, amma en cox resurs ve operation overhead. Regulyasiya tebleri (GDPR, HIPAA) ayrı DB teleb ede biler.
