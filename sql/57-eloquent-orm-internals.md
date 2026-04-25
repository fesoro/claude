# Eloquent / ORM Internals (Hydration, Chunking, Lazy) (Middle)

## ORM Overhead

Raw query vs Eloquent - ferqi nece anlayaq?

```php
// Raw PDO
$stmt = DB::getPdo()->query('SELECT * FROM users LIMIT 10000');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ~50ms, 4 MB memory

// Query Builder
$rows = DB::table('users')->limit(10000)->get();
// ~70ms, 8 MB memory (stdClass per row)

// Eloquent
$users = User::limit(10000)->get();
// ~250ms, 25 MB memory (Model object per row + casts + relations)
```

> **Qaida:** 1000+ row-luq read-only operation-da Eloquent-i bypass et. Bulk INSERT/UPDATE-de Query Builder istifade et.

---

## Hydration Process

Eloquent `Model::hydrate($rows)` cagiranda ne olur:

```php
// Eloquent daxilinde (sadelesdirilmis):
public static function hydrate(array $items): Collection
{
    $instance = new static;
    $models = [];
    
    foreach ($items as $item) {
        $model = $instance->newFromBuilder((array) $item);
        // 1. Yeni Model instance
        // 2. attributes array doldur
        // 3. exists = true (yenilenmis sayilir)
        // 4. original = attributes (dirty tracking ucun)
        $models[] = $model;
    }
    
    return $instance->newCollection($models);
}
```

**Hydration-da bahali emeliyyatlar:**

1. **Type casting** - `$casts` property
   ```php
   protected $casts = [
       'meta' => 'array',          // json_decode her row-da
       'price' => 'decimal:2',     // BCMath cast
       'created_at' => 'datetime', // Carbon::parse her row-da
   ];
   ```

2. **Carbon date parsing** - 1000 row -> 1000 Carbon obyekti -> ~30 ms overhead

3. **Mutator/Accessor** - `getNameAttribute()` her access-de cagrilir

4. **Hidden/visible filtering** - `toArray()`-de her field check

5. **Model events** - `retrieved` event her row ucun

---

## Eager Loading Internals

```php
$posts = Post::with('comments', 'author')->get();
```

Daxilde 3 query gedir (N+1 yox):

```sql
-- 1. SELECT * FROM posts;
-- 2. SELECT * FROM comments WHERE post_id IN (1, 2, 3, ...);
-- 3. SELECT * FROM users WHERE id IN (10, 20, 30, ...);
```

**WHERE IN limiti:**
- MySQL: `max_allowed_packet` (default 16MB) - cox boyuk IN list error verir
- PostgreSQL: prepared statement parameter limiti 65535
- Helli: chunkable eager loading

```php
// Boyuk dataset-de eager load problemi
$users = User::with('orders')->get();
// 100K user -> WHERE id IN (1, 2, ..., 100000) -> packet error

// Helli: chunkById + nested with
User::with('orders')->chunkById(1000, function ($users) {
    foreach ($users as $user) {
        // process
    }
});
```

**`with()` vs `load()`:**

```php
// with() - query basinda eager load (1 + N queries)
$posts = Post::with('comments')->get();

// load() - sonradan lazy eager load (eyni query sayi)
$posts = Post::all();
$posts->load('comments'); // sonra
```

**`loadMissing()` - yalniz yuklenmeyenler:**

```php
$posts->loadMissing('comments');
// Eger relation onceden yuklenmisse, yeniden query getmir
```

---

## chunk() vs chunkById() vs lazy() vs lazyById() vs cursor()

### `chunk()` - LIMIT/OFFSET

```php
User::orderBy('id')->chunk(1000, function ($users) {
    foreach ($users as $user) {
        // ...
    }
});
```

```sql
SELECT * FROM users ORDER BY id LIMIT 1000 OFFSET 0;
SELECT * FROM users ORDER BY id LIMIT 1000 OFFSET 1000;
SELECT * FROM users ORDER BY id LIMIT 1000 OFFSET 2000;
```

**Problem:** OFFSET deep paging cox yavas (DB butun row-lari skan edir, sonra skip edir). 1M row-da OFFSET 999000 ~ 5 saniye.

**Ikinci problem:** Chunk icinde row UPDATE/DELETE etsen, OFFSET shift olur, row skip ola biler!

### `chunkById()` - WHERE id > last_id (KEYSET PAGINATION)

```php
User::where('active', true)->chunkById(1000, function ($users) {
    foreach ($users as $user) {
        $user->update(['processed' => true]);
    }
});
```

```sql
SELECT * FROM users WHERE active = 1 AND id > 0 ORDER BY id LIMIT 1000;
SELECT * FROM users WHERE active = 1 AND id > 1000 ORDER BY id LIMIT 1000;
SELECT * FROM users WHERE active = 1 AND id > 2000 ORDER BY id LIMIT 1000;
```

**Ustunlukler:**
- O(log N) - index seek, deep paging suretli
- Row deyisse bele safe (ID hemise irelileyir)

**Diqqet:** `chunkById` icinde transaction istifade etsen, lock holding artir.

### `lazy()` - Generator + chunk

```php
foreach (User::lazy(1000) as $user) {
    // Bir-bir gelir, amma daxilde 1000-lik chunk fetch edir
}
```

LIMIT/OFFSET istifade edir (chunk() kimi). Sintaksis daha rahatdir.

### `lazyById()` - Generator + chunkById

```php
foreach (User::where('active', true)->lazyById(1000) as $user) {
    $user->processed = true;
    $user->save();
}
```

**En cox tovsiye olunan** boyuk dataset-de iteration ucun.

### `cursor()` - PHP Generator + DB cursor (single row)

```php
foreach (User::cursor() as $user) {
    // Her seferde 1 row fetch (PDO unbuffered)
}
```

```sql
SELECT * FROM users; -- BUTUN result PHP-ye axir
```

**Ferq:** `cursor()` ele 1 query-dir, amma row-lari **streaming** edir (PHP buffered yox).

**MySQL diqqet:** `cursor()` ucun `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false` lazimdir, eks halda butun result memory-ye yuklenir.

```php
// config/database.php
'mysql' => [
    'options' => [
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ],
],
```

### Muqayise cedveli

| Method | Query sayi | Memory | Deep paging | Mid-iteration UPDATE safe |
|--------|-----------|--------|-------------|---------------------------|
| `get()` | 1 | Yuksek (full hydration) | N/A | N/A |
| `chunk(N)` | ceil(total/N) | N row | Yavas (OFFSET) | Yox |
| `chunkById(N)` | ceil(total/N) | N row | Suretli (id seek) | Beli |
| `lazy(N)` | ceil(total/N) | N row + 1 | Yavas | Yox |
| `lazyById(N)` | ceil(total/N) | N row + 1 | Suretli | Beli |
| `cursor()` | 1 | 1 row (streaming) | N/A | DB-asili |

---

## Memory Profiling Misalı

```php
// 1M row export

// 1. get() - OOM
User::all()->each(fn($u) => fputcsv($f, $u->toArray()));
// Memory: 4 GB+ (allocated_bytes_peak)

// 2. cursor() - 50 MB
foreach (User::cursor() as $u) {
    fputcsv($f, $u->toArray());
}

// 3. lazyById() - 50 MB, daha safe
foreach (User::lazyById(5000) as $u) {
    fputcsv($f, $u->toArray());
}

// 4. Query Builder + cursor() - 10 MB
foreach (DB::table('users')->cursor() as $row) {
    fputcsv($f, (array) $row);
}
```

---

## `toBase()` - Eloquent-i Bypass et

```php
// Eloquent (bahali hydration)
$emails = User::where('active', true)->pluck('email');

// Query Builder (cox suretli)
$emails = User::where('active', true)->toBase()->pluck('email');
// Yaxud
$emails = DB::table('users')->where('active', true)->pluck('email');
```

`toBase()` Eloquent scope-larini saxlayir, amma Model hydration-i atlayir. Read-only data-da 5-10x suretli.

---

## Model Events Overhead

```php
class User extends Model {
    protected static function booted(): void
    {
        static::saving(function ($user) {
            // Her save-de cagrilir
        });
    }
}
```

**Bulk operation-da event firing:**

```php
// 1. Eloquent - her row event firing edir (yavas)
User::where('active', true)->update(['notified' => true]);
// Aslinda BU event firing etmir! Bilavasite SQL UPDATE.

// 2. Yalniz model save() event firing edir:
User::where('active', true)->each(fn($u) => $u->update(['notified' => true]));
// Her user ucun: retrieving, retrieved, saving, updating, updated, saved
```

> **Diqqet:** `update()` (mass update) event firing **etmir**. `each()` cycle event firing edir.

**Event-leri muveqqeti deaktiv et:**

```php
User::withoutEvents(function () {
    User::find(1)->update(['name' => 'X']);
});
```

---

## `$fillable` vs `$guarded`

```php
// $fillable - whitelist (ag liste)
protected $fillable = ['name', 'email'];

// $guarded - blacklist (qara liste)
protected $guarded = ['id', 'admin'];

// $guarded = [] - heç bir qoruma yox (mass assignment vulnerability)
```

**Mass assignment hucumu:**

```php
// FORM:
// <input name="name">
// <input name="email">
// HACKER:
// <input name="is_admin" value="1">

User::create($request->all());
// $guarded = [] olsa, is_admin=1 set olur!
```

**Helli:** `$fillable` istifade et ya da `$request->only(['name', 'email'])`.

---

## `touch()` ve `updated_at` Cascade

```php
class Comment extends Model {
    protected $touches = ['post']; // comment yenilenende post.updated_at da yenilenir
}
```

```php
$comment = Comment::find(1);
$comment->update(['body' => 'Yeni metn']);
// Avtomatik: post.updated_at = NOW() (lazimsiz query!)
```

**Performance problemi:** Cox comment update-de N+1 update gedir. `withoutTouching()` ile baglat.

---

## `Model::preventLazyLoading()`

Lazy loading (N+1 problemi) qarsisini almaq:

```php
// AppServiceProvider boot()
use Illuminate\Database\Eloquent\Model;

Model::preventLazyLoading(! app()->isProduction());
```

```php
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name; 
    // LazyLoadingViolationException dev-de!
    // Production-da silently allow (default)
}
```

**Helli:** `Post::with('author')->get()` istifade et.

**Elave kontrol:**

```php
Model::preventSilentlyDiscardingAttributes(); // unfillable attribute warn et
Model::preventAccessingMissingAttributes();   // null deyil, exception
```

---

## Custom Casts (Laravel 7+)

```php
class Money implements CastsAttributes
{
    public function get($model, $key, $value, $attributes): MoneyVO
    {
        return new MoneyVO(amount: $value, currency: $attributes['currency']);
    }
    
    public function set($model, $key, $value, $attributes): array
    {
        return [
            'amount' => $value->amount,
            'currency' => $value->currency,
        ];
    }
}

class Order extends Model {
    protected $casts = [
        'total' => Money::class,
    ];
}

$order->total->amount; // MoneyVO obyekti
```

---

## Attribute Classes (Laravel 9+)

`get/set` mutator-larin modern usulu:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Model {
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn($value) => ucfirst($value),
            set: fn($value) => strtolower($value),
        )->shouldCache(); // accessor result cache
    }
}
```

Eski usul (`getNameAttribute`) hele isleyir, amma yeni layihelerde `Attribute` istifade et.

---

## Transactions Inside chunkById (Lock Awareness)

```php
// PIS - chunk icinde transaction lock saxlayir
User::chunkById(1000, function ($users) {
    DB::transaction(function () use ($users) {
        foreach ($users as $user) {
            $user->update(['processed' => true]);
        }
        // 1000 row lock ~ uzun muddet
    });
});

// YAXSI - kicik transaction-lar
User::chunkById(1000, function ($users) {
    foreach ($users as $user) {
        DB::transaction(function () use ($user) {
            $user->update(['processed' => true]);
        });
    }
});
```

---

## HasManyThrough vs Explicit JOIN

```php
// Eloquent
class Country extends Model {
    public function posts() {
        return $this->hasManyThrough(Post::class, User::class);
    }
}
$posts = Country::find(1)->posts; // 2 query

// Manual JOIN - 1 query, daha suretli aggregate ucun
$posts = DB::table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('users.country_id', 1)
    ->get();
```

---

## Mutator/Accessor Performance

```php
class Product extends Model {
    public function getFinalPriceAttribute(): float
    {
        return $this->price * (1 - $this->discount); // her access-de hesablanir
    }
}

// 10000 product loop-da, $product->final_price 10000 defe hesablanir
```

**Helli:** `Attribute::make()->shouldCache()` yaxud DB-de generated column saxla.

---

## Interview suallari

**Q: chunk vs chunkById vs cursor - hansini secim?**
A: 1) Read-only, sade iteration: `cursor()` (memory-efficient, 1 query). 2) Mutating data (UPDATE/DELETE): `chunkById()` (OFFSET shift problemini hell edir). 3) Sade pagination, kicik dataset: `chunk()`. lazy()-larini sintaksis ucun istifade et (chunk-in `foreach` versiyasi).

**Q: Eloquent-de N+1 problemini nece tap?**
A: 1) `Model::preventLazyLoading()` boot()-da (dev-de exception atir). 2) Laravel Telescope/Debugbar query log. 3) `withCount` agregat ucun, `with` relation eager load ucun. 4) Production-da Spatie/laravel-query-detector.

**Q: `update()` (mass) ile `each(fn=>save())` arasinda ferq?**
A: `update()` 1 SQL UPDATE icra edir, model event firing etmir, accessor/mutator cagrilmir. `each()` her row ucun retrieving/saving/updating event firing edir, observer-leri tetikleyir, audit log uygundur. Bulk pure data update-de `update()`, business logic-de `each()`.

**Q: `cursor()` MySQL-de niye butun result memory-ye yukleyir?**
A: PDO default-da buffered query istifade edir (butun result client-de saxlanir). `cursor()` PHP generator-dur, amma DB drive-i hele de full result fetch edir. `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false` qoymaq lazimdir ki, real streaming olsun.

**Q: `$fillable` vs `$guarded` - hansini istifade?**
A: `$fillable` (whitelist) hemise daha guvenli. Yeni column elave olunsa, default protected qalir. `$guarded = []` (boş) production-da xetadir - mass assignment vulnerability acir. `$guarded = ['id']` minimumdur, amma yeni sensitive field elave olunsa unudula biler.
