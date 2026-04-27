# Lazy Loading Strategies (Middle ⭐⭐)

## İcmal

Lazy loading — resursu (məlumat, modul, şəkil, əlaqəli model) yalnız həqiqətən lazım olduqda yükləmə strategiyasıdır. Eager loading ilə əksidir: eager loading hamısını öncədən yükləyir, lazy loading gözləyir. Hər ikisinin öz yeri var — birini digərindən üstün tutmaq kontekstdən asılıdır.

## Niyə Vacibdir

Lazy loading düzgün istifadə olunmadıqda N+1 probleminin mənbəyidir — backend performansının ən çox rast gəlinən düşməni. Düzgün istifadə olunduqda isə resurs istehlakını azaldır: yalnız istifadə olunan data RAM-a gəlir. Müsahibədə Laravel developer-dən "Lazy loading nədir? N+1-ə necə əlaqəlidir?" — bu sualın cavabı Junior/Middle-ın DB anlayışını ölçür.

## Əsas Anlayışlar

- **Lazy loading (Eloquent default):**
  - Relasiyaya ilk müraciətdə yüklənir
  - `$order->user` — hər çağırışda query göndərir (yüklənməyibsə)
  - Convenient, amma loop-da N+1 problemini yaradır

- **Eager loading (`with()`):**
  - İlk query ilə birlikdə relasiyaları da yüklə
  - `Order::with('user', 'items')->get()` — 2-3 query, N+1 yoxdur
  - Loop-da relasiyaya müraciət — artıq query yoxdur

- **Lazy eager loading (`load()`):**
  - Model artıq yüklənibsə, sonradan relasiya yüklə
  - `$orders->load('user')` — toplu yükləyir (N+1 olmadan)

- **Conditional eager loading:**
  - `with(['items' => fn($q) => $q->where('active', true)])`
  - `when($request->has('with_user'), fn($q) => $q->with('user'))`

- **PHP-də lazy loading:**
  - **Lazy initialization** — property ilk istifadədə yaradılır
  - **Generators** — `yield` ilə lazy iteration (memory-efficient)
  - **PHP 8.4 Lazy Objects** — native deferred initialization

- **Frontend kontekstdə lazy loading:**
  - Image lazy loading: `<img loading="lazy">`
  - Code splitting: JavaScript module-ları lazım olanda yüklə
  - Intersection Observer API

- **Database-level lazy:**
  - **Cursor-based iteration** — `LazyCollection::cursor()` — server-side cursor
  - **Generator-based** — `LazyCollection::make()` ilə custom

## Praktik Baxış

**N+1 problem və həlli:**

```php
// ❌ Classic N+1: 1 + N query
$orders = Order::all(); // 1 query: bütün sifarişlər
foreach ($orders as $order) {
    echo $order->user->name;   // +1 query: hər sifariş üçün user
    // 100 sifariş = 101 query
}

// ✅ Eager loading: 2 query
$orders = Order::with('user')->get();
foreach ($orders as $order) {
    echo $order->user->name;   // cache-dən, query yoxdur
}

// ✅ Lazy eager loading (model artıq varsa):
$orders = Order::all();
$orders->load('user'); // 1 əlavə query, hamısını yükləyir
foreach ($orders as $order) {
    echo $order->user->name; // query yoxdur
}
```

**LazyCollection (böyük dataset):**

```php
// ❌ get() — bütün 1M record RAM-a gəlir
User::all()->each(fn($u) => $this->process($u));

// ✅ cursor() — server-side cursor, 1 model RAM-da
User::cursor()->each(fn($u) => $this->process($u));

// ✅ lazy() — chunk-based LazyCollection
User::lazy(1000)->each(fn($u) => $this->process($u));

// ✅ LazyCollection transform pipeline
User::where('active', true)
    ->cursor()
    ->filter(fn($u) => $u->hasSubscription())
    ->map(fn($u) => $u->toMailData())
    ->each(fn($data) => Mail::to($data['email'])->queue(new NewsletterMail($data)));
```

**PHP Generator lazy loading:**

```php
function readLargeFile(string $path): Generator
{
    $handle = fopen($path, 'r');
    try {
        while (!feof($handle)) {
            yield fgets($handle);
        }
    } finally {
        fclose($handle);
    }
}

// 10GB fayl üçün yalnız 1 sətir RAM-da
foreach (readLargeFile('/data/huge.csv') as $line) {
    $this->processLine($line);
}
```

**Lazy initialization pattern:**

```php
class ReportService
{
    private ?array $cachedConfig = null;

    // ❌ Constructor-da yüklə (lazım olmasa belə):
    public function __construct()
    {
        $this->cachedConfig = config('reports'); // həmişə yüklənir
    }

    // ✅ Lazy initialization:
    private function getConfig(): array
    {
        return $this->cachedConfig ??= config('reports'); // ilk istifadədə yüklənir
    }
}
```

**PHP 8.4 Lazy Objects:**

```php
// PHP 8.4+
use ReflectionClass;

$reflector = new ReflectionClass(HeavyService::class);
$proxy = $reflector->newLazyGhost(function (HeavyService $instance) {
    $instance->__construct(); // yalnız istifadə zamanı çağırılır
});

// $proxy artıq var, amma HeavyService::__construct() çağırılmayıb
// İlk property/method müraciətdə initializer işləyir
```

**Eloquent strict mode (lazy loading-i qadağan et):**

```php
// AppServiceProvider — development-da N+1 detect
Model::preventLazyLoading(! app()->isProduction());

// Production-da log et (crash etmə):
Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
    Log::warning('Lazy loading detected', [
        'model' => get_class($model),
        'relation' => $relation,
        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
    ]);
});
```

**Conditional eager loading:**

```php
// API endpoint: müştəri lazım olan relasiyaları seçir
class OrderController
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query();

        // Client ?include=user,items,items.product göndərə bilər
        $allowed = ['user', 'items', 'items.product', 'payments'];
        $includes = array_intersect(
            explode(',', $request->get('include', '')),
            $allowed
        );

        if ($includes) {
            $query->with($includes);
        }

        return response()->json($query->paginate(20));
    }
}
```

**Trade-offs:**

| | Lazy Loading | Eager Loading |
|---|---|---|
| Memory | Az (lazım olan qədər) | Çox (hamısı öncədən) |
| Query sayı | N+1 riski | Az, öncədən |
| Simplicity | Sadə kod | `with()` əlavə etmək lazım |
| Loop-da | Təhlükəli | Tövsiyə olunur |
| API (optional include) | Uyğun | Uyğun |

**Common mistakes:**
- Loop içindəki relasiya müraciəti (N+1)
- `cursor()` istifadə edəndə relasiya yükləmək (N+1, çünki eager load olmur)
- `preventLazyLoading()` production-da açmaq (crash risk)
- Bütün relasiyaları həmişə with() etmək (lazımsız data)

## Nümunələr

### Real Ssenari: API optimizasiyası

```php
// ❌ Pis API design: hər request 50+ query
class InvoiceController
{
    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        return response()->json([
            'id' => $invoice->id,
            'client' => $invoice->client->name,       // +1 query
            'items' => $invoice->items->map(fn($i) => [
                'product' => $i->product->name,       // +N query
                'price' => $i->price,
            ]),
            'payments' => $invoice->payments->sum('amount'), // +1 query
        ]);
    }
}

// ✅ Yaxşı: 1 query + 3 eager load = 4 query
class InvoiceController
{
    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::with([
            'client:id,name,email',
            'items.product:id,name,sku',
            'payments:id,invoice_id,amount',
        ])
        ->withSum('payments', 'amount')
        ->findOrFail($id);

        return response()->json(new InvoiceResource($invoice));
    }
}
```

### Kod Nümunəsi

```php
<?php

// LazyCollection ilə CSV export (memory-safe)
class OrderExportService
{
    public function exportToCsv(string $status): void
    {
        $headers = ['id', 'user_email', 'total', 'created_at'];

        $handle = fopen('php://output', 'w');
        fputcsv($handle, $headers);

        // 1M+ sifariş olsa belə yalnız 1 model RAM-da
        Order::where('status', $status)
            ->with('user:id,email')  // eager load ilə N+1 qarşısı
            ->cursor()
            ->each(function (Order $order) use ($handle) {
                fputcsv($handle, [
                    $order->id,
                    $order->user->email,
                    $order->total,
                    $order->created_at->toIso8601String(),
                ]);
            });

        fclose($handle);
    }
}
```

## Praktik Tapşırıqlar

1. **N+1 aşkar et:** `Model::preventLazyLoading(true)` aktiv et, mövcud bir controller-i çağır, hansi relasiyaların lazy load olduğunu tap.

2. **Memory benchmark:** `Order::all()` vs `Order::cursor()` vs `Order::lazy()` — 50K record üçün `memory_get_peak_usage()` ilə müqayisə et.

3. **Generator yaz:** Böyük bir CSV faylını oxuyan, hər sətri `yield` edən generator yaz, `foreach` ilə istehlak et.

4. **API includes:** `?include=user,items` query parameter qəbul edən, yalnız icazəli relasiyaları yükləyən bir controller yaz.

5. **Strict mode log:** Production-safe lazy loading violation handler yaz, Log::warning ilə qeyd et, Telescope-da izlə.

## Əlaqəli Mövzular

- `02-query-optimization.md` — N+1 və query performance
- `08-pagination-strategies.md` — Böyük data-nı hissə-hissə vermək
- `09-async-batch-processing.md` — Batch processing ilə lazy iteration
- `03-caching-layers.md` — Lazy load nəticələrini cache-ləmək
- `15-indexing-strategy.md` — Lazy load etdikdə index effektini anlamaq
