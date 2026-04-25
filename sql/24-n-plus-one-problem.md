# N+1 Problem (Junior)

## Problem nedir?

1 query ile N neticeni alirsan, sonra her netice ucun ayri-ayri 1 query daha cagirirsan. Cemisi: **1 + N query**.

```php
// 1 query: Butun order-lari al
$orders = Order::all(); // SELECT * FROM orders;  (1 query)

// N query: Her order ucun user-i al
foreach ($orders as $order) {
    echo $order->user->name;
    // SELECT * FROM users WHERE id = 1;  (query 2)
    // SELECT * FROM users WHERE id = 2;  (query 3)
    // SELECT * FROM users WHERE id = 3;  (query 4)
    // ... N defe
}
// 100 order varsa = 101 query!
```

---

## Hell yollari

### 1. Eager Loading (with)

```php
// 2 query: Butun data-ni 2 query ile al
$orders = Order::with('user')->get();
// Query 1: SELECT * FROM orders;
// Query 2: SELECT * FROM users WHERE id IN (1, 2, 3, ...);

foreach ($orders as $order) {
    echo $order->user->name; // Elave query yoxdur!
}
```

### Nested Eager Loading

```php
// Order -> User -> Profile, Order -> Items -> Product
$orders = Order::with([
    'user.profile',
    'items.product',
])->get();
// Query-ler:
// 1. SELECT * FROM orders
// 2. SELECT * FROM users WHERE id IN (...)
// 3. SELECT * FROM profiles WHERE user_id IN (...)
// 4. SELECT * FROM order_items WHERE order_id IN (...)
// 5. SELECT * FROM products WHERE id IN (...)
// 5 query (100 order ucun de 5 query!)
```

### Conditional Eager Loading

```php
// Yalniz lazim olan sutunlari yukle
$orders = Order::with(['user:id,name,email'])->get();

// Sertli eager loading
$orders = Order::with(['items' => function ($query) {
    $query->where('quantity', '>', 1)->orderBy('price', 'desc');
}])->get();
```

### 2. Lazy Eager Loading (load)

Artiq yuklenmis collection-a sonradan eager load elave etmek:

```php
$orders = Order::all();

// Sonradan ehtiyac yaranir
if ($needUserData) {
    $orders->load('user');
}
```

### 3. withCount (Aggregate)

```php
// YANLIS: N+1
$users = User::all();
foreach ($users as $user) {
    echo $user->orders()->count(); // Her user ucun ayri COUNT query
}

// DOGRU: withCount
$users = User::withCount('orders')->get();
// SELECT users.*, (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) AS orders_count
// FROM users

foreach ($users as $user) {
    echo $user->orders_count; // Elave query yoxdur
}
```

### withSum, withAvg, withMin, withMax

```php
$users = User::withSum('orders', 'total_amount')
    ->withAvg('orders', 'total_amount')
    ->get();

echo $user->orders_sum_total_amount;
echo $user->orders_avg_total_amount;
```

### 4. JOIN ile hell

```php
$orders = Order::select('orders.*', 'users.name as user_name')
    ->join('users', 'users.id', '=', 'orders.user_id')
    ->get();
// Tek 1 query!

foreach ($orders as $order) {
    echo $order->user_name;
}
```

### 5. Subquery Select

```php
$users = User::addSelect([
    'latest_order_date' => Order::select('created_at')
        ->whereColumn('user_id', 'users.id')
        ->latest()
        ->limit(1),
])->get();
// Tek 1 query (subquery ile)
```

---

## N+1 Detection

### Laravel Debugbar

```php
// composer require barryvdh/laravel-debugbar --dev
// Queries tab-inda butun query-leri ve duplicate-lari gorursen
```

### Preventing N+1 (Laravel 8+)

```php
// AppServiceProvider.php
use Illuminate\Database\Eloquent\Model;

public function boot()
{
    // Development-de N+1 olsa exception at
    Model::preventLazyLoading(! app()->isProduction());
}
```

Bu aktiv olduqda, eager loading edilmemis relation-a muraciet etsen **LazyLoadingViolationException** alirsan:

```php
$orders = Order::all();
$orders->first()->user; // LazyLoadingViolationException!

// Fix:
$orders = Order::with('user')->get();
$orders->first()->user; // OK!
```

### Custom Logging

```php
// N+1 detect et ve log et (production-da)
Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
    Log::warning("N+1 detected: {$model::class}::{$relation}");
});
```

---

## Real-World Misallar

### API Resource-larda

```php
// YANLIS
class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user->name,      // N+1!
            'items_count' => $this->items->count(), // N+1!
        ];
    }
}

// Controller-de:
return OrderResource::collection(Order::all()); // N+1 + N+1!

// DOGRU
return OrderResource::collection(
    Order::with('user')->withCount('items')->get()
);
```

### Blade Template-larda

```php
// YANLIS (view-da N+1)
@foreach($orders as $order)
    {{ $order->user->name }}        {{-- N+1! --}}
    {{ $order->items->count() }}    {{-- N+1! --}}
@endforeach

// DOGRU: Controller-de eager load et
$orders = Order::with('user')->withCount('items')->get();
return view('orders.index', compact('orders'));
```

---

## Raw SQL ile N+1 helli

```php
// N+1 problem (manual)
$orders = DB::select('SELECT * FROM orders');
foreach ($orders as $order) {
    $user = DB::selectOne('SELECT * FROM users WHERE id = ?', [$order->user_id]);
}

// Fix: JOIN
$orders = DB::select('
    SELECT o.*, u.name as user_name 
    FROM orders o 
    JOIN users u ON u.id = o.user_id
');

// Fix: IN clause
$orders = DB::select('SELECT * FROM orders');
$userIds = array_column($orders, 'user_id');
$users = DB::select('SELECT * FROM users WHERE id IN (' . implode(',', $userIds) . ')');
$usersById = array_column($users, null, 'id');

foreach ($orders as $order) {
    $userName = $usersById[$order->user_id]->name;
}
```

---

## Interview suallari

**Q: N+1 problem nedir ve nece hell edersin?**
A: 1 query ile collection alinir, sonra her item ucun ayri query cagrilir. 100 item = 101 query. Hell: `with()` (eager loading) ile related data-ni 2 query-de al. Laravel-de `Model::preventLazyLoading()` ile development-de detect et.

**Q: `with` ve `load` arasinda ferq?**
A: `with()` query zamani eager load edir (query builder-e elave olunur). `load()` artiq yuklenmis collection-a sonradan eager load edir. Neticesi eynidir, amma `load()` conditional yukleme ucun faydalidir.

**Q: Eager loading hemishe daha yaxsidirmi?**
A: Yox. Eger 1000 order yukleyib yalniz 1-inin user-ine baxirsansa, 999 user bos yere yuklenib. Lazy loading bezen daha yaxsidir - meselen, conditional logic-de. Amma loop-da relation-a muraciet edirsense, hemishe eager loading istifade et.
