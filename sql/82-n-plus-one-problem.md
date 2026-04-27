# N+1 Problem (Middle)

## Mündəricat
1. [N+1 Problem nədir?](#n1-problem-nədir)
2. [Aşkarlanma](#aşkarlanma)
3. [Eager Loading](#eager-loading)
4. [Polymorphic N+1](#polymorphic-n1)
5. [Nested N+1](#nested-n1)
6. [DataLoader Pattern](#dataloader-pattern)
7. [Query Optimization](#query-optimization)
8. [İntervyu Sualları](#intervyu-sualları)

---

## N+1 Problem nədir?

```
// Bu kod N+1 probleminin necə yarandığını loop daxilində lazy loading ilə göstərir
N+1: 1 sorğu + hər nəticə üçün əlavə 1 sorğu = N+1 sorğu

Nümunə:
  $orders = Order::all();  // 1 sorğu: 100 order
  
  foreach ($orders as $order) {
      echo $order->customer->name;  // 100 sorğu: hər order üçün customer!
  }
  
  Cəmi: 101 sorğu (1 + 100)

SQL-də:
  SELECT * FROM orders;
  SELECT * FROM customers WHERE id = 1;
  SELECT * FROM customers WHERE id = 2;
  SELECT * FROM customers WHERE id = 3;
  ... (100 dəfə!)
```

---

## Aşkarlanma

*Aşkarlanma üçün kod nümunəsi:*
```php
// Bu kod N+1 problemini aşkarlamaq üçün query log və detector üsullarını göstərir
// Laravel Telescope (development)
// Telescope N+1 warning-ları avtomatik göstərir

// Laravel Debugbar
composer require barryvdh/laravel-debugbar --dev

// Manual: Query log
DB::enableQueryLog();

$orders = Order::all();
foreach ($orders as $order) {
    $order->customer->name;
}

$queries = DB::getQueryLog();
echo count($queries); // 101!

// Clockwork, Xdebug, Blackfire
```

**Preventive: N+1 detector:**

```php
// Testing-də N+1 yoxla
use Illuminate\Database\Events\QueryExecuted;

class N1Detector
{
    private array $queries = [];
    private int $threshold;
    
    public function __construct(int $threshold = 10)
    {
        $this->threshold = $threshold;
        
        DB::listen(function (QueryExecuted $query) {
            $this->queries[] = $query->sql;
            
            if (count($this->queries) > $this->threshold) {
                $duplicates = array_count_values(
                    array_map(fn($q) => preg_replace('/\d+/', '?', $q), $this->queries)
                );
                
                foreach ($duplicates as $sql => $count) {
                    if ($count > 5) {
                        Log::warning("Possible N+1 detected", [
                            'sql'   => $sql,
                            'count' => $count,
                        ]);
                    }
                }
            }
        });
    }
}

// Test-də:
it('does not have N+1 queries', function () {
    $queryCount = 0;
    DB::listen(fn() => $queryCount++);
    
    $response = $this->get('/api/orders');
    
    expect($queryCount)->toBeLessThan(10);  // N+1 yoxdur
});
```

---

## Eager Loading

*Eager Loading üçün kod nümunəsi:*
```php
// Bu kod eager loading ilə N+1 probleminin həllini və nested əlaqələrin yüklənməsini göstərir
// Problem:
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name;    // N+1!
    echo $order->items->count();    // N+1!
}

// Həll: Eager loading
$orders = Order::with(['customer', 'items'])->get();
// SQL:
//   SELECT * FROM orders;
//   SELECT * FROM customers WHERE id IN (1,2,3,...);
//   SELECT * FROM order_items WHERE order_id IN (1,2,3,...);
// 3 sorğu, N+1 yox!

// Nested eager loading
$orders = Order::with([
    'customer',
    'customer.address',          // customer-in address-i
    'items',
    'items.product',             // item-lərin product-ı
    'items.product.category',    // product-ın category-si
])->get();

// Conditional eager loading
$orders = Order::with([
    'customer:id,name,email',    // Yalnız bu sütunlar
    'items' => function ($query) {
        $query->where('status', 'active')
              ->orderBy('created_at');
    },
])->get();

// Lazy eager loading (artıq yüklənmiş collection üçün)
$orders = Order::all();

// Sonradan qərar verilərsə:
$orders->load('customer', 'items');

// loadMissing: artıq yüklənmişləri yenidən yükləmə
$orders->loadMissing('customer');
```

---

## Polymorphic N+1

*Polymorphic N+1 üçün kod nümunəsi:*
```php
// Bu kod polymorphic əlaqələrdə N+1 problemini və morphWith həllini göstərir
// Polymorphic relation — xüsusi N+1 case
class Comment extends Model
{
    public function commentable(): MorphTo
    {
        return $this->morphTo();  // Post, Video, Product ola bilər
    }
}

// Problem:
$comments = Comment::all();
foreach ($comments as $comment) {
    echo $comment->commentable->title;  // N+1! Hər tip üçün ayrı sorğu
}
// SELECT * FROM posts WHERE id IN (...)
// SELECT * FROM videos WHERE id IN (...)
// SELECT * FROM products WHERE id IN (...)
// ...

// Həll: morphWith
$comments = Comment::with('commentable')->get();
// Laravel avtomatik olaraq hər morph type üçün ayrı IN sorğusu edir

// Specific morph type constraint:
$comments = Comment::with([
    'commentable' => function (MorphTo $morphTo) {
        $morphTo->morphWith([
            Post::class    => ['author'],
            Video::class   => ['channel'],
        ]);
    }
])->get();
```

---

## Nested N+1

*Nested N+1 üçün kod nümunəsi:*
```php
// Bu kod çox səviyyəli nested əlaqələrdə N+1 problemini və eager loading həllini göstərir
// Deep nesting problemi:
$users = User::all();
foreach ($users as $user) {
    foreach ($user->orders as $order) {          // N+1
        foreach ($order->items as $item) {       // N*M+1
            echo $item->product->name;           // N*M*K+1!
        }
    }
}

// Həll: Nested eager loading
$users = User::with([
    'orders.items.product',
])->get();

// Daha effektiv — limit nested data
$users = User::with([
    'orders' => function ($q) {
        $q->latest()->limit(10);  // Son 10 order
    },
    'orders.items:id,order_id,product_id,quantity',
    'orders.items.product:id,name,price',
])->get();
```

---

## DataLoader Pattern

GraphQL və ya custom API-lərdə batching:

*GraphQL və ya custom API-lərdə batching üçün kod nümunəsi:*
```php
// Bu kod GraphQL resolver-lərindəki N+1 problemini DataLoader batching ilə həll edir
// Problem: GraphQL resolvers N+1 yaradır
// Hər resolver öz DB sorğusunu edir

class OrderType extends Type
{
    public function customer(): Customer
    {
        // Bu hər order üçün ayrı sorğu edir!
        return Customer::find($this->customer_id);
    }
}

// Həll: DataLoader (batching + caching)
class CustomerDataLoader
{
    private array $batch = [];
    private array $cache = [];
    
    public function load(int $customerId): \Closure
    {
        $this->batch[] = $customerId;
        
        // Deferred — həmin frame-in sonunda icra olunur
        return function () use ($customerId) {
            if (isset($this->cache[$customerId])) {
                return $this->cache[$customerId];
            }
            
            $this->flush();
            return $this->cache[$customerId] ?? null;
        };
    }
    
    private function flush(): void
    {
        if (empty($this->batch)) return;
        
        $ids = array_unique($this->batch);
        $this->batch = [];
        
        // 1 sorğu: Bütün customer-ları al
        $customers = Customer::whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        
        foreach ($customers as $id => $customer) {
            $this->cache[$id] = $customer;
        }
    }
}

// Laravel context-ə qeydiyyat
app()->singleton(CustomerDataLoader::class);

// Resolver-də:
class OrderType
{
    public function customer(Order $order): ?Customer
    {
        return app(CustomerDataLoader::class)->load($order->customer_id)();
    }
}
```

---

## Query Optimization

*Query Optimization üçün kod nümunəsi:*
```php
// Bu kod withCount, withSum, JOIN və chunk kimi sorğu optimizasiya üsullarını göstərir
// 1. Select yalnız lazım olan sütunları
$orders = Order::select(['id', 'status', 'total', 'customer_id'])
    ->with('customer:id,name,email')
    ->get();

// 2. withCount — say üçün ayrı collection yükləmə
$customers = Customer::withCount('orders')->get();
// SQL: SELECT customers.*, COUNT(orders.id) as orders_count FROM ...
foreach ($customers as $customer) {
    echo $customer->orders_count;  // N+1 yoxdur!
}

// 3. withSum, withAvg, withMin, withMax
$customers = Customer::withSum('orders', 'total')
    ->withCount('orders')
    ->get();
// $customer->orders_sum_total
// $customer->orders_count

// 4. Has vs whereHas
// whereHas — filter üçün, N+1 yoxdur (subquery)
$customersWithOrders = Customer::whereHas('orders', function ($q) {
    $q->where('status', 'completed');
})->get();

// 5. Join vs Eager Loading
// Bəzən JOIN daha effektivdir (reporting):
$result = DB::table('orders')
    ->join('customers', 'orders.customer_id', '=', 'customers.id')
    ->join('order_items', 'orders.id', '=', 'order_items.order_id')
    ->select([
        'orders.id',
        'customers.name as customer_name',
        DB::raw('COUNT(order_items.id) as item_count'),
        DB::raw('SUM(order_items.price) as total'),
    ])
    ->groupBy('orders.id', 'customers.name')
    ->get();

// 6. Chunk for large datasets
Order::with('customer')
    ->where('status', 'pending')
    ->chunk(100, function ($orders) {
        foreach ($orders as $order) {
            $this->processOrder($order);
        }
    });
```

---

## İntervyu Sualları

**1. N+1 problem nədir?**
1 sorğu N record qaytarır, sonra hər record üçün əlavə 1 sorğu atılır = N+1 sorğu. 100 order üçün customers sorulursa: 1 (orders) + 100 (customers) = 101 sorğu. ORM-lərdə lazy loading ilə baş verir.

**2. Eager loading nədir, necə həll edir?**
`with()` ilə əlaqəli modellər əvvəlcədən yüklənir. 1 order sorğusu + 1 customer sorğusu (IN clause) = 2 sorğu. Laravel: `Order::with('customer')->get()`. SQL: `SELECT * FROM customers WHERE id IN (1,2,3,...)`.

**3. withCount vs counting in loop fərqi?**
Counting in loop: N+1 (hər model üçün COUNT sorğusu). withCount: 1 aggregate subquery — `COUNT(orders.id) as orders_count`. `$customer->orders_count` lazım olduqda withCount daha effektivdir.

**4. Polymorphic N+1 necə həll edilir?**
`morphWith()` ilə. Laravel polymorphic relation-da hər morph type üçün ayrı IN sorğusu edir. `Comment::with('commentable')` → posts, videos, products hər biri 1 sorğu ilə yüklənir.

**5. Production-da N+1-i necə aşkar etmək olar?**
Laravel Telescope (N+1 alerts), Debugbar, Clockwork. Query log analizi: oxşar sorğular N dəfə təkrarlanır. Blackfire profiler. Test-lərdə: DB::listen ilə sorğu sayını say, threshold-u keç oldu mu yoxla.

**6. `preventLazyLoading()` nədir, nə vaxt istifadə edilir?**
Laravel 8.x+ ilə gəldi. `Model::preventLazyLoading(!app()->isProduction())` — development-də lazy loading-ə cəhd edildikdə exception atır. Bu N+1-i development-də məcburi olaraq aşkar edir, production-a keçməzdən öncə düzəldilir. Production-da söndürülməlidir (həssas olmayan yerlərdə performance hit riski).

**7. Cursor vs Chunk N+1 ilə necə əlaqəlidir?**
`chunk()` partiyalar halında yüklər — hər chunk üçün eager loading işləyir. `cursor()` generator ilə lazeri birer-birer qaytarır (memory effektiv), amma eager loading dəstəkləmir — N+1 riski var. Böyük datasetdə N+1 riski olmayan əlaqə yoxdursa `chunk()` + `with()` birlikdə istifadə edilməlidir.

---

## Anti-patternlər

**1. Lazy loading-i default olaraq bütün yerdə istifadə etmək**
Eloquent modellərindəki bütün əlaqələri lazy yükləmək — loop-da hər modelin əlaqəsinə daxil olunanda N+1 sorğu yaranır, performance aşağı düşür. Eager loading-i (`with()`) default strategiya kimi qəbul edin; Laravel-də `Model::preventLazyLoading()` ilə development-də lazy loading-i deaktiv edin.

**2. `with()` ilə bütün əlaqəni yükləmək, yalnız bir sahə lazım olanda**
`Order::with('customer')->get()` — bütün customer sütunları yüklənir, yalnız `name` lazımdır. `with('customer:id,name')` ilə yalnız lazımlı sütunları seçin; həddən artıq data ötürülməsin.

**3. N+1-i index əlavə etməklə "həll etmək"**
Hər iterasiyada atılan sorğuya index qoymaq — sorğu sayı azalmır, yalnız hər biri bir az sürətlənir. Əvvəlcə sorğu sayını azaldın (eager loading, `withCount`); index optimizasiyası sonrakı addımdır.

**4. Nested relation-larda N+1-i göz ardı etmək**
`Order → OrderItems → Product` üçün `with('items')` yetər düşünmək, `with('items.product')` yazmamaq — items yüklənir, sonra hər item üçün product ayrıca sorğu atılır. Bütün nested əlaqə chain-ini eager loading ilə bildirin: `with('items.product')`.

**5. Production-da N+1 aşkarlanma mexanizmi olmamaq**
Development-də Debugbar istifadə edilir, production-da heç nə — real yükdə N+1 yaranır, yalnız yavaşlama artdıqca aşkar edilir. Laravel Telescope ya da Pulse-u production-da aktiv edin; sorğu sayı threshold-u keçdikdə alert qurulsun.

**6. `withCount` əvəzinə loop-da `count()` çağırmaq**
`foreach ($customers as $c) { $c->orders()->count() }` — hər müştəri üçün ayrı COUNT sorğusu atılır. `Customer::withCount('orders')->get()` istifadə edin: 1 aggregate subquery ilə bütün saylar yüklənir.
