# Iterator (Middle ⭐⭐)

## İcmal
Iterator pattern collection-un daxili strukturunu (array, tree, linked list və s.) gizlədərək elementlər üzərindən ardıcıl keçməyə imkan verir. Client collection-un necə qurulduğunu bilmədən `foreach` ilə istifadə edə bilir. "Necə saxlandığı"nı "necə gəzildiyindən" ayırır.

## Niyə Vacibdir
PHP-nin SPL library-si, Laravel Collections və Generator-lar — hamısı Iterator üzərindədir. Böyük dataset-lərdə memory-efficient traversal (lazy loading, pagination), custom data structure-lar üçün `foreach` dəstəyi, filter/map pipeline-ları bu pattern olmadan mümkün deyil.

## Əsas Anlayışlar
- **Iterator interface**: `current()`, `key()`, `next()`, `rewind()`, `valid()` metodları
- **IteratorAggregate**: yalnız `getIterator()` implement edir, daha sadə yanaşma
- **SPL iterators**: PHP-nin built-in hazır iterator-ları (ArrayIterator, FilterIterator və s.)
- **Generator**: `yield` keyword ilə lightweight lazy iterator; tam Iterator interface lazım deyil
- **Lazy evaluation**: element yalnız tələb olunduqda hesablanır — böyük dataset-lər üçün vacib
- **Rewindable vs Forward-only**: Generator-lar yalnız irəli gedir; ArrayIterator rewind dəstəkləyir

## Praktik Baxış
- **Real istifadə**: API pagination (cursor-based, offset-based) üzərindən lazily iterate etmək, böyük CSV/JSON faylları oxumaq, tree/graph traversal, database cursor ilə böyük query nəticəsini stream etmək
- **Trade-off-lar**: custom iterator yazmaq 5 metod tələb edir — boilerplate çoxdur; Generator daha az kod tələb edir amma rewind edə bilmir
- **İstifadə etməmək**: sadə array-lər üçün (PHP-nin built-in array functions kifayətdir); collection bir dəfə yüklənəcəksə lazy-ness dəyərsizdir
- **Common mistakes**: iterator-un `current()`/`next()` metodlarında side effects etmək (state corrupts olur); exhausted generator-u yenidən istifadə etməyə çalışmaq
- **Anti-Pattern Nə Zaman Olur?**: İterasiya zamanı collection-u mutasiya etmək — `foreach ($collection as $item) { $collection->remove($item); }` — bu "collection modified during iteration" xətası yaradır və ya gözlənilməz davranışa səbəb olur; əvvəlcə toplayıb sonra sil. Generator-u istifadəçiyə birbaşa vermək əvəzinə bütün nəticəni array-ə `iterator_to_array()` ilə çevirmək — memory-efficient olmanın mənisini aradan qaldırır; lazy pipeline-ı axıra qədər lazy saxla.

## Nümunələr

### Ümumi Nümunə
Kitabxanadakı kitab kataloqunu düşünün. Kitablar həm rəf üzrə (fiziki order), həm müəllifə görə, həm mövzuya görə sıralana bilər. Iterator sayəsinde müştəri "kitabların necə saxlandığını" bilmədən fərqli traversal strategiyaları ilə eyni `foreach` kodu işlədə bilər.

### PHP/Laravel Nümunəsi

**PHP Iterator interface — tam implementasiya:**

```php
<?php

class NumberRange implements Iterator
{
    private int $current;

    public function __construct(
        private readonly int $start,
        private readonly int $end,
        private readonly int $step = 1
    ) {
        $this->current = $start;
    }

    public function current(): int  { return $this->current; }
    public function key(): int      { return ($this->current - $this->start) / $this->step; }
    public function next(): void    { $this->current += $this->step; }
    public function rewind(): void  { $this->current = $this->start; }
    public function valid(): bool   { return $this->current <= $this->end; }
}

foreach (new NumberRange(1, 100, 5) as $key => $value) {
    echo "$key: $value\n"; // 0: 1, 1: 6, 2: 11 ...
}
```

**IteratorAggregate — daha sadə yanaşma:**

```php
class UserCollection implements IteratorAggregate, Countable
{
    private array $users = [];

    public function add(User $user): void
    {
        $this->users[] = $user;
    }

    // Yalnız bu metodu implement etmək kifayətdir
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->users);
    }

    public function count(): int
    {
        return count($this->users);
    }
}

$collection = new UserCollection();
$collection->add(new User('Alice'));
$collection->add(new User('Bob'));

foreach ($collection as $user) {
    echo $user->name . "\n";
}

echo count($collection); // Countable interface sayəsində
```

**SPL built-in iterators:**

```php
// FilterIterator — şərtə görə filtr
class ActiveUserIterator extends FilterIterator
{
    public function accept(): bool
    {
        return $this->current()->is_active === true;
    }
}

$allUsers  = new ArrayIterator(User::all()->toArray());
$active    = new ActiveUserIterator($allUsers);

foreach ($active as $user) {
    // yalnız aktiv user-lər
}

// LimitIterator — pagination
$page    = 2;
$perPage = 10;
$limited = new LimitIterator($allUsers, ($page - 1) * $perPage, $perPage);

// RecursiveIteratorIterator — nested structure-ları flat iterate etmək
$directory = new RecursiveDirectoryIterator('/app');
$iterator  = new RecursiveIteratorIterator($directory);

foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') {
        echo $file->getPathname() . "\n";
    }
}
```

**Generator — ən praktik lazy iterator:**

```php
// Bütün sətirləri memory-ə yükləmədən böyük CSV oxumaq
function readCsvLazy(string $filePath): Generator
{
    $handle = fopen($filePath, 'r');
    $headers = fgetcsv($handle); // ilk sətir header

    while (($row = fgetcsv($handle)) !== false) {
        yield array_combine($headers, $row); // biri yüklə, biri ver
    }

    fclose($handle);
}

// 10 GB fayl — memory problem yoxdur
foreach (readCsvLazy('/data/transactions.csv') as $row) {
    Transaction::create($row);
}

// Generator ilə infinite sequence
function fibonacci(): Generator
{
    [$a, $b] = [0, 1];
    while (true) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
}

$fib = fibonacci();
for ($i = 0; $i < 10; $i++) {
    echo $fib->current() . " ";
    $fib->next();
}
// 0 1 1 2 3 5 8 13 21 34
```

**PaginatedResultIterator — API pagination-ı lazy iterate etmək:**

```php
class PaginatedApiIterator implements Iterator
{
    private array  $currentPageItems = [];
    private int    $currentIndex     = 0;
    private int    $currentPage      = 1;
    private bool   $hasMore          = true;
    private int    $globalKey        = 0;

    public function __construct(
        private readonly HttpClient $client,
        private readonly string $endpoint,
        private readonly int $perPage = 100
    ) {}

    public function rewind(): void
    {
        $this->currentPage      = 1;
        $this->currentIndex     = 0;
        $this->globalKey        = 0;
        $this->hasMore          = true;
        $this->currentPageItems = [];
        $this->loadPage();
    }

    public function valid(): bool
    {
        return $this->currentIndex < count($this->currentPageItems) || $this->hasMore;
    }

    public function current(): mixed
    {
        if ($this->currentIndex >= count($this->currentPageItems) && $this->hasMore) {
            $this->loadPage();
        }
        return $this->currentPageItems[$this->currentIndex] ?? null;
    }

    public function key(): int   { return $this->globalKey; }

    public function next(): void
    {
        $this->currentIndex++;
        $this->globalKey++;

        if ($this->currentIndex >= count($this->currentPageItems) && $this->hasMore) {
            $this->currentIndex = 0;
            $this->loadPage();
        }
    }

    private function loadPage(): void
    {
        $response = $this->client->get($this->endpoint, [
            'page'     => $this->currentPage,
            'per_page' => $this->perPage,
        ]);

        $this->currentPageItems = $response['data'];
        $this->hasMore          = $response['has_more'];
        $this->currentPage++;
        $this->currentIndex = 0;
    }
}

// İstifadəsi — API-nin neçə page olduğunu bilmədən iterate
$iterator = new PaginatedApiIterator($client, '/api/products');
foreach ($iterator as $key => $product) {
    Product::updateOrCreate(['sku' => $product['sku']], $product);
}
```

**Laravel Collection = Iterator:**

```php
// Laravel Collection-lar IteratorAggregate implement edir
$users = User::where('is_active', true)->get();

// foreach — Iterator sayəsində işləyir
foreach ($users as $user) {
    $user->sendMonthlyReport();
}

// lazy() — database cursor ilə memory-efficient
User::where('is_active', true)->lazy()->each(function (User $user) {
    $user->sendMonthlyReport();
});

// LazyCollection — generator-based
$lazyUsers = LazyCollection::make(function () {
    yield from User::cursor(); // PHP Generator
});

$lazyUsers
    ->filter(fn($u) => $u->hasSubscription())
    ->each(fn($u) => ProcessUser::dispatch($u));
```

## Praktik Tapşırıqlar
1. `Generator` istifadə edərək böyük Eloquent query-ni chunk-lara böldən `yield`-lə keçən lazy iterator yazın; memory istifadəsini `memory_get_usage()` ilə ölçün
2. Bir şirkətin department tree strukturu üçün `RecursiveIterator` implement edin — hər node-un children-ları var
3. Xarici bir API (məs: JSONPlaceholder `/posts`) üçün `PaginatedApiIterator` yazın, bütün posts-u fetch edin

## Əlaqəli Mövzular
- [../structural/05-composite.md](../structural/05-composite.md) — Tree structure iterate etmək üçün birlikdə istifadə olunur; Composite + Iterator birlikdə güclü tree traversal verir
- [../laravel/02-service-layer.md](../laravel/02-service-layer.md) — Service-lərdə lazy data processing
- [09-visitor.md](09-visitor.md) — Visitor iterator ilə traverse edilən structure-lara əməliyyat əlavə edir
