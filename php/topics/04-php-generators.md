# PHP Generators (Junior)

## Mündəricat
1. [Generator nədir?](#generator-nədir)
2. [yield açar sözü](#yield-açar-sözü)
3. [Generator Metodları](#generator-metodları)
4. [Memory Efficiency](#memory-efficiency)
5. [Lazy Evaluation](#lazy-evaluation)
6. [Generator Pipeline-ları](#generator-pipeline-ları)
7. [Real Nümunələr](#real-nümunələr)
8. [Generators vs Iterator vs Collection](#generators-vs-iterator-vs-collection)
9. [yield from — Generator Delegation](#yield-from--generator-delegation)
10. [Laravel: LazyCollection](#laravel-lazycollection)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Generator nədir?

Generator — `yield` açar sözü olan funksiya. Çağırıldıqda dərhal icra edilmir, `Generator` obyekti qaytarır.

*Generator — `yield` açar sözü olan funksiya. Çağırıldıqda dərhal icra  üçün kod nümunəsi:*
```php
// Adi funksiya — bütün dəyərləri qaytarır
function normalFunction(): array
{
    return [1, 2, 3, 4, 5];  // Bütün array yaddaşa yüklənir
}

// Generator funksiya — tək-tək dəyər qaytarır
function generatorFunction(): Generator
{
    yield 1;
    yield 2;
    yield 3;
    yield 4;
    yield 5;
}

// İstifadə eynidir:
foreach (normalFunction() as $val) { echo $val; }
foreach (generatorFunction() as $val) { echo $val; }

// Amma yaddaş istifadəsi çox fərqlidir!
```

**Generator icra axını:**

```
generatorFunction() çağırılır
    │
    ▼
Generator obyekti qaytarılır (icra başlamayıb!)
    │
    ▼
foreach başlayır → current() çağırılır
    │
    ▼
İcra başlayır... → "yield 1" — dəyər verilir, DONDURULUR
    │
    ▼
foreach növbəti iterasiya → next() → İcra davam edir
    │
    ▼
"yield 2" — dəyər verilir, yenidən DONDURULUR
    │
    ... davam edir ...
```

---

## yield açar sözü

**1. Sadə yield:**

```php
function range_generator(int $start, int $end): Generator
{
    for ($i = $start; $i <= $end; $i++) {
        yield $i;
    }
}

foreach (range_generator(1, 1_000_000) as $num) {
    // Yalnız cari $num yaddaşda saxlanır!
}
```

**2. yield key => value:**

```php
function indexedGenerator(): Generator
{
    yield 'ad' => 'Əli';
    yield 'yaş' => 25;
    yield 'şəhər' => 'Bakı';
}

foreach (indexedGenerator() as $key => $value) {
    echo "$key: $value\n";
}
// ad: Əli
// yaş: 25
// şəhər: Bakı
```

**3. send() ilə iki tərəfli kommunikasiya:**

```php
function logger(): Generator
{
    while (true) {
        $message = yield;  // send() ilə dəyər alır
        echo "[LOG] $message\n";
    }
}

$log = logger();
$log->current();          // Generator-u başlat
$log->send('Xəta baş verdi');   // "[LOG] Xəta baş verdi"
$log->send('İstifadəçi daxil oldu'); // "[LOG] İstifadəçi daxil oldu"
```

---

## Generator Metodları

*Generator Metodları üçün kod nümunəsi:*
```php
$gen = (function() {
    yield 'birinci';
    yield 'ikinci';
    return 'son dəyər';
})();

$gen->current();     // 'birinci' — cari dəyər
$gen->key();         // 0 — cari açar
$gen->next();        // növbəti dəyərə keç
$gen->valid();       // true/false — generator bitibmi?
$gen->rewind();      // Başa qayıt (yalnız bir dəfə)
$gen->send($val);    // Dəyər göndər + next()
$gen->throw($ex);    // Generator içinə exception at
$gen->getReturn();   // return dəyərini al (tamamlandıqdan sonra)
```

---

## Memory Efficiency

**Problem: 1 milyon sətir DB-dən yükləmək**

```php
// ❌ Yanlış — bütün data yaddaşa yüklənir
function getAllUsers(): array
{
    return DB::table('users')->get()->toArray();
    // 1M user × ~500 bytes = ~500MB RAM istifadə edir!
}

// ✅ Düzgün — Generator ilə yalnız bir sətir yaddaşda
function getUsersLazy(): Generator
{
    $offset = 0;
    $limit = 1000;
    
    while (true) {
        $users = DB::table('users')
            ->offset($offset)
            ->limit($limit)
            ->get();
        
        if ($users->isEmpty()) {
            break;
        }
        
        foreach ($users as $user) {
            yield $user;
        }
        
        $offset += $limit;
    }
}

// İstifadə:
foreach (getUsersLazy() as $user) {
    processUser($user);  // Eyni anda yalnız 1 user yaddaşda
}
```

**Yaddaş müqayisəsi:**

```php
// Test: 100,000 element
$start = memory_get_usage();

// Array versiyası:
$data = range(1, 100000);
echo (memory_get_usage() - $start) / 1024 . " KB\n";  // ~4000 KB

// Generator versiyası:
function gen() { for ($i = 1; $i <= 100000; $i++) yield $i; }
$start = memory_get_usage();
foreach (gen() as $v) {}
echo (memory_get_usage() - $start) / 1024 . " KB\n";  // ~1 KB
```

---

## Lazy Evaluation

Generator-lar lazy-dir: dəyər yalnız lazım olduqda hesablanır.

*Generator-lar lazy-dir: dəyər yalnız lazım olduqda hesablanır üçün kod nümunəsi:*
```php
// Sonsuz ardıcıllıq (array ilə mümkün deyil!)
function fibonacci(): Generator
{
    [$a, $b] = [0, 1];
    while (true) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
}

// Yalnız ilk 10 Fibonacci rəqəmini al
$fib = fibonacci();
for ($i = 0; $i < 10; $i++) {
    echo $fib->current() . ' ';
    $fib->next();
}
// 0 1 1 2 3 5 8 13 21 34

// Sonsuz tapşırıq növbəsi
function infiniteQueue(array $initialTasks): Generator
{
    $queue = $initialTasks;
    while (!empty($queue)) {
        $task = array_shift($queue);
        $newTasks = yield $task;
        if ($newTasks !== null) {
            $queue = array_merge($queue, $newTasks);
        }
    }
}
```

---

## Generator Pipeline-ları

Generator-ları zəncirvari şəkildə birləşdirmək:

*Generator-ları zəncirvari şəkildə birləşdirmək üçün kod nümunəsi:*
```php
// Pipeline: oxu → filtrele → çevir → çap et
function readLines(string $file): Generator
{
    $handle = fopen($file, 'r');
    while (($line = fgets($handle)) !== false) {
        yield trim($line);
    }
    fclose($handle);
}

function filterEmpty(Generator $lines): Generator
{
    foreach ($lines as $line) {
        if (!empty($line)) {
            yield $line;
        }
    }
}

function parseCSV(Generator $lines): Generator
{
    foreach ($lines as $line) {
        yield str_getcsv($line);
    }
}

function transformRows(Generator $rows): Generator
{
    foreach ($rows as $row) {
        yield [
            'name'  => $row[0] ?? '',
            'email' => $row[1] ?? '',
            'age'   => (int)($row[2] ?? 0),
        ];
    }
}

// Pipeline qurulması — yaddaş effektiv!
$pipeline = transformRows(
    parseCSV(
        filterEmpty(
            readLines('users.csv')
        )
    )
);

foreach ($pipeline as $user) {
    saveUser($user);
}
// Eyni anda yalnız 1 sətir yaddaşda işlənir!
```

---

## Real Nümunələr

**1. Böyük CSV oxumaq:**

```php
function readCsvLazy(string $path): Generator
{
    $file = new SplFileObject($path);
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
    
    $headers = null;
    foreach ($file as $row) {
        if ($headers === null) {
            $headers = $row;
            continue;
        }
        yield array_combine($headers, $row);
    }
}

foreach (readCsvLazy('million_users.csv') as $user) {
    User::create($user);  // RAM istifadəsi sabit qalır
}
```

**2. Database cursor pagination:**

```php
function cursorPaginate(string $table, int $chunk = 500): Generator
{
    $lastId = 0;
    
    while (true) {
        $rows = DB::table($table)
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit($chunk)
            ->get();
        
        if ($rows->isEmpty()) {
            return;
        }
        
        foreach ($rows as $row) {
            yield $row;
        }
        
        $lastId = $rows->last()->id;
    }
}

foreach (cursorPaginate('orders') as $order) {
    processOrder($order);
}
```

**3. Log faylını axın şəklində işləmək:**

```php
function tailLog(string $path): Generator
{
    $handle = fopen($path, 'r');
    fseek($handle, 0, SEEK_END);  // Fayl sonuna git
    
    while (true) {
        $line = fgets($handle);
        if ($line === false) {
            usleep(100000);  // 100ms gözlə
            continue;
        }
        yield $line;
    }
}

// Real-time log monitoring
foreach (tailLog('/var/log/nginx/access.log') as $line) {
    if (str_contains($line, '500')) {
        alert("Server xətası: $line");
    }
}
```

---

## Generators vs Iterator vs Collection

```
┌──────────────────┬───────────────┬──────────────┬─────────────────┐
│                  │  Generator    │  Iterator    │  Collection     │
├──────────────────┼───────────────┼──────────────┼─────────────────┤
│ Yaddaş           │ O(1)          │ O(1) mümkün  │ O(n)            │
│ Reusable         │ ❌ (bir dəfə) │ ✅           │ ✅              │
│ Rewind           │ ❌ (çətin)   │ ✅           │ ✅              │
│ Random access    │ ❌           │ ❌           │ ✅              │
│ Lazy             │ ✅           │ ✅ mümkün    │ ❌              │
│ Kod sadəliyi     │ ✅ (yield)   │ Boilerplate  │ ✅              │
│ Kompozisiya      │ ✅ pipeline  │ Orta          │ Zəngin metodlar │
└──────────────────┴───────────────┴──────────────┴─────────────────┘
```

---

## yield from — Generator Delegation

PHP 7.0+ ilə `yield from` başqa generator-a və ya array-ə delegate etmək imkanı verir:

*PHP 7.0+ ilə `yield from` başqa generator-a və ya array-ə delegate etm üçün kod nümunəsi:*
```php
function inner(): Generator
{
    yield 1;
    yield 2;
    return 'inner tamamlandı';
}

function outer(): Generator
{
    yield 0;
    $result = yield from inner();  // inner-in bütün dəyərləri yield edilir
    echo "Inner qaytardı: $result\n";  // "inner tamamlandı"
    yield 3;
}

foreach (outer() as $v) {
    echo $v . ' ';
}
// 0 1 2 3
// Inner qaytardı: inner tamamlandı
```

**Ağac strukturunu rekursiv traverse etmək:**

```php
function traverseTree(array $node): Generator
{
    yield $node['value'];
    
    foreach ($node['children'] ?? [] as $child) {
        yield from traverseTree($child);  // Rekursiv delegation
    }
}

$tree = [
    'value' => 1,
    'children' => [
        ['value' => 2, 'children' => [
            ['value' => 4],
            ['value' => 5],
        ]],
        ['value' => 3],
    ]
];

foreach (traverseTree($tree) as $val) {
    echo $val . ' ';
}
// 1 2 4 5 3
```

---

## Laravel: LazyCollection

Laravel `LazyCollection` daxilən PHP Generator-larından istifadə edir:

*Laravel `LazyCollection` daxilən PHP Generator-larından istifadə edir üçün kod nümunəsi:*
```php
// LazyCollection — generator-based, yaddaş effektiv
LazyCollection::make(function () {
    $handle = fopen('large.csv', 'r');
    while (($line = fgets($handle)) !== false) {
        yield $line;
    }
})->chunk(1000)->each(function ($chunk) {
    // 1000 sətirlik batch
    DB::table('imports')->insert($chunk->toArray());
});

// DB::cursor() — LazyCollection qaytarır
User::query()->cursor()->each(function (User $user) {
    // Yalnız 1 model yaddaşda
    $user->sendWelcomeEmail();
});

// chunk() vs cursor() fərqi:
// chunk(): Hər chunk üçün ayrı DB sorğusu
// cursor(): 1 sorğu, PHP generator ilə sətirləri tək-tək verir

// lazy() — Eloquent collection-ı lazy-ə çevir
User::where('active', 1)->lazy()->each(function ($user) {
    // Memory-efficient
});
```

**Collection vs LazyCollection:**

```php
// Collection — bütün data yaddaşa
$users = User::all();  // 100k user = ~100MB

// LazyCollection — generator
$users = User::cursor();  // ~2MB (sabit)

// Hər ikisi eyni metodları dəstəkləyir:
User::cursor()
    ->filter(fn($u) => $u->is_active)
    ->map(fn($u) => $u->email)
    ->each(fn($email) => Mail::send($email));
```

---

## Generator ilə Coroutine Pattern (PHP 7.x legacy)

PHP 8.1 Fiber-lərdən əvvəl generator-lar coroutine kimi istifadə edilirdi. Bu pattern köhnə kodlarda hələ görünür:

*PHP 8.1 Fiber-lərdən əvvəl generator-lar coroutine kimi istifadə edili üçün kod nümunəsi:*
```php
// Ənənəvi generator-based coroutine (PHP 7.x-də async simulation)
function asyncTask(): Generator
{
    // yield - "gözlə, başqa iş et" deməkdir
    $result = yield fetchFromDatabase(1); // suspend, event loop başqa iş edir
    echo "DB nəticəsi: $result\n";

    $apiResult = yield callExternalApi('https://api.example.com');
    echo "API nəticəsi: $apiResult\n";
}

// Scheduler (sadələşdirilmiş)
function runCoroutines(Generator ...$coroutines): void
{
    while (!empty($coroutines)) {
        foreach ($coroutines as $key => $coroutine) {
            if (!$coroutine->valid()) {
                unset($coroutines[$key]);
                continue;
            }
            $promise = $coroutine->current(); // yield dəyərini al (Promise/Future)
            $promise->then(fn($result) => $coroutine->send($result)); // tamamlandıqda davam et
        }
        // Event loop bir iterasiya dövr edir
    }
}
```

**PHP 8.1+ Fiber-lərlə eyni şey daha sadə:**

```php
// Fiber ilə eyni pattern (daha güclü, ayrı execution stack)
$fiber = new Fiber(function(): void {
    $result = Fiber::suspend(fetchFromDatabase(1));
    echo "DB nəticəsi: $result\n";
});
```

---

## Praktiki Nümunə: Import Pipeline

*Praktiki Nümunə: Import Pipeline üçün kod nümunəsi:*
```php
<?php
declare(strict_types=1);

// Böyük Excel faylı import — memory sabit qalır

function readExcelRows(string $path): Generator
{
    // PhpSpreadsheet lazy read
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($path);
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
        if ($rowIndex === 1) continue; // Header sətrini atla

        $cellIterator = $row->getCellIterator();
        $data = [];
        foreach ($cellIterator as $cell) {
            $data[] = $cell->getValue();
        }
        yield $data; // Hər sətri tək-tək yüklə
    }
}

function validateRow(Generator $rows): Generator
{
    foreach ($rows as $row) {
        if (empty($row[0]) || empty($row[1])) {
            continue; // Invalid sətirləri keç
        }
        yield [
            'name'  => trim((string) $row[0]),
            'email' => trim((string) $row[1]),
            'phone' => trim((string) ($row[2] ?? '')),
        ];
    }
}

function batchInsert(Generator $rows, int $batchSize = 500): Generator
{
    $batch = [];
    foreach ($rows as $row) {
        $batch[] = $row;
        if (count($batch) >= $batchSize) {
            yield $batch; // Batch-i yield et
            $batch = [];  // Batch-i sıfırla
        }
    }
    if (!empty($batch)) {
        yield $batch; // Son qalan batch
    }
}

// Pipeline-ı işlət
$pipeline = batchInsert(
    validateRow(
        readExcelRows('large_import.xlsx')
    ),
    batchSize: 500
);

$totalImported = 0;
foreach ($pipeline as $batch) {
    DB::table('users')->insert($batch); // Batch insert
    $totalImported += count($batch);
    echo "Import edildi: $totalImported\n";
}
// 100,000 sətirlik Excel: ~5MB RAM (array olsaydı ~200MB)
```

---

## İntervyu Sualları

**1. Generator nədir, adi funksiyadan nə ilə fərqlənir?**
Generator `yield` olan funksiya. Çağırıldıqda dərhal icra edilmir, `Generator` obyekti qaytarır. Adi funksiya bütün nəticəni qaytarır (yaddaşda saxlayır), generator isə tək-tək dəyər verir. Bu böyük datasetlərdə yaddaşı qənaət edir.

**2. 1 milyon sətirlik CSV-ni minimal yaddaşla necə oxuyarsınız?**
Generator ilə: `fgets()` loop-unda `yield` istifadə edilir. Hər dəfə yalnız bir sətir yaddaşda saxlanır. LazyCollection ilə də eyni effekt əldə edilir.

**3. `yield from` nə işə yarayır?**
Başqa generator-a, array-ə və ya `Traversable`-a delegate edir. Outer generator inner-in bütün dəyərlərini sanki öz `yield`-ləri kimi verir. `return` dəyərini də ala bilir.

**4. Generator-u `rewind` edə bilərsinizmi?**
Generatorlar adətən yalnız bir dəfə iterate edilə bilər. `rewind()` yalnız iteration hələ başlamamışdırsa çağırıla bilər. Başlanmışdırsa exception atır.

**5. `Generator::send()` necə işləyir?**
`send($value)` çağırıldıqda, həm `next()` kimi işləyir (növbəti `yield`-ə keçir), həm də `$value` cari `yield` ifadəsinin dəyəri olur: `$received = yield;`

**6. LazyCollection nədir, Collection-dan fərqi?**
LazyCollection PHP Generator əsasında qurulub. `Collection` bütün datanı yaddaşa yükləyir. `LazyCollection` element-ləri tələb olduqda hesablayır — böyük datasetlərdə yaddaşı qənaət edir. Əksər metodları eynidir (`filter`, `map`, `each`).

**7. Generator pipeline nədir?**
Bir neçə generator-un ardıcıl birləşdirilməsi: `transform(parse(filter(read())))`. Hər addım növbəti addıma tək-tək dəyər ötürür. Bütün pipeline boyunca yalnız bir element yaddaşda saxlanır.

**8. Infinite sequence (sonsuz ardıcıllıq) nümunəsi verin.**
Fibonacci generator: `while (true) { yield $a; [$a,$b]=[$b,$a+$b]; }` — heç vaxt bitmir, lazım olan qədər element götürülür.

**9. `Generator::throw()` nə üçün istifadə edilir?**
Generator içinə xarici kontekstdən exception göndərmək üçün: `$gen->throw(new \RuntimeException('Xəta'))`. Generator-un cari `yield` ifadəsi bu exception-ı atır. Bu, generator pipeline-larında xəta vəziyyətini bildirmək, ya da generator-u xətalı olaraq bitirmək üçün istifadə edilir. Məsələn, fayl oxuyan generator-a diskdə yer olmadığını bildirmək.

**10. Generator-lar ilə coroutine-based async nə deməkdir?**
PHP 8.1 Fiber-lərdən əvvəl generator-lar (yield) ilə cooperative multitasking simulate edilirdi. `ReactPHP` və `Amp v2` generator-ları coroutine kimi istifadə edirdi: `yield asyncOperation()` ilə gözləyir, sonra `send($result)` ilə davam etdirirdi. Bu pattern artıq Fiber ilə əvəzlənib, lakin PHP 7.x-dəki async kodunda hələ görünür.

**11. `SplDoublyLinkedList` vs Generator — böyük queue-larda hansı seçilmər?**
Böyük queue-larda generator yaddaş baxımından daha effektivdir (O(1)), lakin yalnız irəli iterasiya mümkündür. `SplDoublyLinkedList` hər iki istiqamətdə gəzməyə, O(1) əlavə/silməyə imkan verir, amma bütün elementi yaddaşda saxlayır. Queue işlənməsi (FIFO, bir dəfə oxuma) üçün generator daha yaxşıdır.

**12. `iterator_to_array()` generator-la istifadəsinin riski nədir?**
`iterator_to_array($gen)` bütün generator dəyərlərini bir anda array-ə yükləyir — generator-un bütün yaddaş üstünlüyü sıfırlanır. Debug üçün faydalıdır, amma production kodunda generator pipeline-ını array-ə çevirmək mənasız olur. Əlavə risk: key collision — eyni key iki dəfə yield edilsə sonuncusu ilkini üstələyir. `iterator_to_array($gen, false)` ilə key-ləri atlamaq olar.

---

## Anti-patternlər

**1. Böyük dataset-ləri əvvəlcə array-ə yükləyib sonra generator ilə işlətmək**
`$rows = DB::table('logs')->get()->toArray()` ilə hamısını RAM-a çəkib sonra işləmək — generator-ın bütün faydasını sıfırlayır, yaddaş problemi qalır. `DB::table('logs')->cursor()` ya da `LazyCollection` ilə birbaşa lazy oxu.

**2. Generator-u bir neçə dəfə iterate etməyə çalışmaq**
Eyni generator instance-ını iki `foreach` ilə gəzmək — generator yalnız bir dəfə istifadə edilə bilər, ikinci `foreach` boş keçər, gözlənilməz nəticə verir. Generator-u yenidən istifadə etmək lazımdırsa, generator function-ı yenidən çağır, ya da datanı cache et.

**3. `yield from` ilə exception propagation-ı nəzərə almamaq**
Inner generator-dan gələn exception-ları outer generator-da handle etməmək — exception yayılır, catch edilmədən tətbiqin üst qatına çatır, hansı generator-dan gəldiyini anlamaq çətin olur. `yield from` istifadə edərkən exception handling-i hər iki generator üçün planla.

**4. Generator pipeline-ı debug etmək üçün ara nəticəsiz işlətmək**
Uzun generator zəncirini (`read → filter → transform → write`) birbaşa icra edib nəyi yield etdiyini bilməmək — xəta harada baş verdiyini tapmaq çox çətindir. Debug üçün pipeline-ın hər addımına `iterator_to_array()` əlavə edib ara nəticəni yoxla.

**5. Memory-efficiency tələb etməyən sadə hallarda generator işlətmək**
Kiçik (10-100 element) statik array-lər üçün generator yazmaq — generator overhead (function call, state machine) sadə array iteration-dan daha yavaş ola bilər, kod mürəkkəbliyi artır. Generator-ı yalnız böyük dataset, infinite sequence ya da lazy evaluation lazım olan hallarda işlət.

**6. `Generator::send()` ilə ilk `next()` çağırışını unutmaq**
Generator-u yaradıb birbaşa `send($value)` çağırmaq — generator hələ birinci `yield`-ə çatmayıb, göndərilən dəyər itirilir. Generator-u ilk dəfə `current()` ya da `next()` ilə birinci `yield`-ə apar, sonra `send()` işlət.
