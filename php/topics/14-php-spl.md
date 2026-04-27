# PHP SPL (Standard PHP Library) (Junior)

## Mündəricat
1. [SPL nədir?](#spl-nədir)
2. [SplStack və SplQueue](#splstack-və-splqueue)
3. [SplFixedArray — Memory Effektivliyi](#splfixedarray--memory-effektivliyi)
4. [SplHeap, SplMinHeap, SplMaxHeap](#splheap-splminheap-splmaxheap)
5. [SplPriorityQueue](#splpriorityqueue)
6. [SplObjectStorage](#splobjectstorage)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## SPL nədir?

```
SPL — PHP-nin daxili data strukturları və interfeys kolleksiyası.
Xarici kitabxana tələb etmir, core PHP-nin hissəsidir.

Nə vaxt SPL istifadə etmək lazımdır:
  - Böyük data strukturları (array əvəzinə)
  - Spesifik access pattern (LIFO, FIFO, priority)
  - Object-keyed storage lazım olduqda
  - Memory-sensitive əməliyyatlarda

array vs SPL:
  array    → universal, flexible, amma overhead-li
  SplStack → LIFO semantics, explicit
  SplQueue → FIFO semantics, explicit
  SplFixedArray → sabit ölçü, daha az RAM
```

---

## SplStack və SplQueue

```
SplStack — LIFO (Last In, First Out)
  push() → əlavə et
  pop()  → sonuncunu götür
  top()  → sonuncuya bax (götürmə)

SplQueue — FIFO (First In, First Out)
  enqueue() → arxaya əlavə et
  dequeue() → önündən götür
  bottom()  → önündəkini gör (götürmə)

LIFO (Stack):          FIFO (Queue):
push(A)  → [A]         enqueue(A) → [A]
push(B)  → [A,B]       enqueue(B) → [A,B]
push(C)  → [A,B,C]     enqueue(C) → [A,B,C]
pop()    → C           dequeue()  → A
pop()    → B           dequeue()  → B
```

---

## SplFixedArray — Memory Effektivliyi

```
Adi array vs SplFixedArray memory müqayisəsi:

10,000 integer elementli array:
  array:        ~800 KB
  SplFixedArray: ~80 KB  (10x daha az!)

Niyə?
  array: hash table + bucket + pointer overhead
  SplFixedArray: sadə C array, sabit ölçü

Məhdudiyyətlər:
  - Ölçü dəyişdirilə bilməz (fromArray() istisna)
  - Yalnız integer index
  - String key yoxdur
```

---

## SplHeap, SplMinHeap, SplMaxHeap

```
Heap — ağac strukturu, root həmişə min/max element.

SplMinHeap: ən kiçik element həmişə üstdə
SplMaxHeap: ən böyük element həmişə üstdə

insert(5) insert(3) insert(8) insert(1):

MinHeap:        MaxHeap:
    1               8
   / \             / \
  3   8           5   3
 /                 \
5                   1

extract() → 1   extract() → 8
extract() → 3   extract() → 5

İstifadə: priority queue, dijkstra, scheduling
```

---

## SplPriorityQueue

```
Priority ilə element əlavə et, ən yüksək prioritet əvvəl çıxır.

insert(data, priority)
extract() → ən yüksək prioritetli element

Nümunə: job queue
  insert('email',   1)  → aşağı prioritet
  insert('payment', 3)  → yüksək prioritet
  insert('report',  2)  → orta prioritet

extract() → payment (priority: 3)
extract() → report  (priority: 2)
extract() → email   (priority: 1)

Eyni priority varsa: insertion order saxlanılmır!
```

---

## SplObjectStorage

```
Object-ləri key kimi istifadə etməyə imkan verir.
Həm set (unikal obyektlər), həm map (object→data) kimi işləyir.

array ilə olmur:
  $map[$object] = 'data'; // → "Illegal offset type" xətası

SplObjectStorage ilə:
  $storage->attach($object, 'data'); ✅

İstifadə halları:
  - Event listener-ları izləmək
  - Object relationship mapping
  - Weak reference alternativ (PHP 8-dən WeakMap da var)
  - Object-based cache
```

---

## PHP İmplementasiyası

```php
<?php
// SplStack — call stack simulation
$stack = new SplStack();
$stack->push('frame1');
$stack->push('frame2');
$stack->push('frame3');

echo $stack->top();  // frame3
echo $stack->pop();  // frame3
echo $stack->count(); // 2

// SplQueue — task queue
$queue = new SplQueue();
$queue->enqueue(['type' => 'email', 'to' => 'user@example.com']);
$queue->enqueue(['type' => 'sms', 'to' => '+994501234567']);

while (!$queue->isEmpty()) {
    $task = $queue->dequeue();
    echo "Processing: {$task['type']}\n";
}
```

```php
<?php
// SplFixedArray — memory benchmark
$size = 100_000;

// Adi array
$start = memory_get_usage();
$array = range(0, $size - 1);
$arrayMemory = memory_get_usage() - $start;

// SplFixedArray
$start = memory_get_usage();
$fixed = SplFixedArray::fromArray(range(0, $size - 1));
$fixedMemory = memory_get_usage() - $start;

echo "Array:        " . round($arrayMemory / 1024 / 1024, 2) . " MB\n";
echo "SplFixedArray: " . round($fixedMemory / 1024 / 1024, 2) . " MB\n";
// Array:         7.63 MB
// SplFixedArray: 1.53 MB
```

```php
<?php
// SplMinHeap — top-K elements tapmaq
class TopKFinder
{
    private SplMinHeap $heap;

    public function __construct(private int $k) {
        $this->heap = new SplMinHeap();
    }

    public function add(int $value): void
    {
        $this->heap->insert($value);

        // Heap ölçüsünü k ilə məhdudlaşdır
        if ($this->heap->count() > $this->k) {
            $this->heap->extract(); // ən kiçiyi çıxar
        }
    }

    public function getTopK(): array
    {
        $result = [];
        $clone = clone $this->heap;
        while (!$clone->isEmpty()) {
            $result[] = $clone->extract();
        }
        return array_reverse($result);
    }
}

$finder = new TopKFinder(3);
foreach ([5, 2, 8, 1, 9, 3, 7] as $num) {
    $finder->add($num);
}
print_r($finder->getTopK()); // [9, 8, 7]
```

```php
<?php
// SplPriorityQueue — job dispatcher
class JobDispatcher
{
    private SplPriorityQueue $queue;

    public function __construct() {
        $this->queue = new SplPriorityQueue();
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
    }

    public function dispatch(string $job, int $priority): void
    {
        $this->queue->insert($job, $priority);
    }

    public function processNext(): ?string
    {
        if ($this->queue->isEmpty()) return null;
        $item = $this->queue->extract();
        return $item['data'];
    }
}

$dispatcher = new JobDispatcher();
$dispatcher->dispatch('send_newsletter', 1);
$dispatcher->dispatch('process_payment', 10);
$dispatcher->dispatch('generate_report', 5);

echo $dispatcher->processNext(); // process_payment
echo $dispatcher->processNext(); // generate_report
echo $dispatcher->processNext(); // send_newsletter
```

```php
<?php
// SplObjectStorage — event listener registry
class EventEmitter
{
    private SplObjectStorage $listeners;

    public function __construct() {
        $this->listeners = new SplObjectStorage();
    }

    public function on(object $listener, string $event): void
    {
        $this->listeners->attach($listener, $event);
    }

    public function off(object $listener): void
    {
        $this->listeners->detach($listener);
    }

    public function emit(string $event, mixed $data): void
    {
        foreach ($this->listeners as $listener) {
            if ($this->listeners[$listener] === $event) {
                $listener->handle($data);
            }
        }
    }
}
```

---

## İntervyu Sualları

- `SplFixedArray` vs `array` — nə vaxt `SplFixedArray` seçərsiniz?
- `SplStack` vs `array_push/array_pop` fərqi nədir?
- `SplObjectStorage` vs `WeakMap` (PHP 8) — hansını nə vaxt?
- `SplPriorityQueue`-da eyni priority-li elementlər hansı sıra ilə çıxır?
- Dijkstra alqoritmi üçün hansı SPL strukturunu seçərdiniz?
- 1 milyon elementli listdə hər zaman minimum elementi tez tapmaq lazımdır — hansı strukturu seçərsiniz?
