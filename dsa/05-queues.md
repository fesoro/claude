# Queues (Junior)

## Konsept (Concept)

Queue FIFO (First In, First Out) prinsipi ile isleyen data strukturudur. Ilk elave olunan element ilk cixarilir -- market novbesi kimi.

```
Enqueue (arxa)                    Dequeue (on)
    |                                 |
    v                                 v
  +----+----+----+----+----+----+
  | 60 | 50 | 40 | 30 | 20 | 10 |
  +----+----+----+----+----+----+
  rear                        front

Circular Queue:
       front
        |
        v
  +----+----+----+----+----+
  | 30 | 40 |    |    | 20 |
  +----+----+----+----+----+
              ^         ^
              |         rear (conceptual wrap)
              rear

Deque (Double-ended Queue):
  pushFront  popFront
      |          |
      v          v
  +----+----+----+----+----+
  | 10 | 20 | 30 | 40 | 50 |
  +----+----+----+----+----+
      ^                    ^
      |                    |
  popBack              pushBack
```

## Nece Isleyir? (How does it work?)

**Esas emeliyyatlar:**
1. **enqueue(x)** - Arxaya element elave et: O(1)
2. **dequeue()** - Ondan element cixar: O(1)
3. **front/peek()** - On elementi gos: O(1)
4. **isEmpty()** - Boshdurmu yoxla: O(1)

**Circular Queue ustunluyu:** Massivde dequeue etdikde evvelden shift etmek lazim deyil. Front ve rear indeksleri modular arifmetika ile hereket edir.

## Implementasiya (Implementation)

### Array-based Queue (Simple)
```php
<?php
class SimpleQueue {
    private array $data = [];

    public function enqueue(mixed $val): void {
        $this->data[] = $val; // O(1)
    }

    public function dequeue(): mixed {
        if ($this->isEmpty()) {
            throw new UnderflowException('Queue is empty');
        }
        return array_shift($this->data); // O(n) -- yavas!
    }

    public function front(): mixed {
        return $this->data[0] ?? throw new UnderflowException('Queue is empty');
    }

    public function isEmpty(): bool {
        return empty($this->data);
    }
}
```

### Circular Queue
```php
<?php
class CircularQueue {
    private array $data;
    private int $front = 0;
    private int $rear = -1;
    private int $size = 0;
    private int $capacity;

    public function __construct(int $capacity) {
        $this->capacity = $capacity;
        $this->data = array_fill(0, $capacity, null);
    }

    public function enqueue(mixed $val): bool {
        if ($this->isFull()) return false;

        $this->rear = ($this->rear + 1) % $this->capacity;
        $this->data[$this->rear] = $val;
        $this->size++;
        return true;
    }

    public function dequeue(): mixed {
        if ($this->isEmpty()) return null;

        $val = $this->data[$this->front];
        $this->front = ($this->front + 1) % $this->capacity;
        $this->size--;
        return $val;
    }

    public function front(): mixed {
        return $this->isEmpty() ? null : $this->data[$this->front];
    }

    public function isEmpty(): bool { return $this->size === 0; }
    public function isFull(): bool { return $this->size === $this->capacity; }
}
```

### PHP SplQueue
```php
<?php
$queue = new SplQueue();

$queue->enqueue('task1');
$queue->enqueue('task2');
$queue->enqueue('task3');

echo $queue->dequeue(); // 'task1'
echo $queue->bottom();  // 'task2' (front)
echo $queue->top();     // 'task3' (rear)
echo $queue->count();   // 2

foreach ($queue as $item) {
    echo $item . " "; // task2 task3
}
```

### Priority Queue
```php
<?php
// PHP SplPriorityQueue - max-heap ile realize olunub
$pq = new SplPriorityQueue();

$pq->insert('low task', 1);
$pq->insert('critical task', 10);
$pq->insert('medium task', 5);

echo $pq->extract(); // 'critical task' (en yuksek priority)
echo $pq->extract(); // 'medium task'
echo $pq->extract(); // 'low task'

// Min-priority ucun menfi deyer istifade et:
$pq->insert('task', -$priority);

// Hem data hem priority gostermek:
$pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
$item = $pq->extract();
// ['data' => 'task', 'priority' => 5]
```

### Queue with Two Stacks
```php
<?php
// Interview classic: 2 stack ile queue realize et
class QueueWithStacks {
    private array $pushStack = [];
    private array $popStack = [];

    public function enqueue(mixed $val): void {
        $this->pushStack[] = $val; // O(1)
    }

    public function dequeue(): mixed {
        if (empty($this->popStack)) {
            // pushStack-i popStack-e kocur (terse)
            while (!empty($this->pushStack)) {
                $this->popStack[] = array_pop($this->pushStack);
            }
        }
        return array_pop($this->popStack); // Amortized O(1)
    }

    public function front(): mixed {
        if (empty($this->popStack)) {
            while (!empty($this->pushStack)) {
                $this->popStack[] = array_pop($this->pushStack);
            }
        }
        return end($this->popStack);
    }

    public function isEmpty(): bool {
        return empty($this->pushStack) && empty($this->popStack);
    }
}

// Niye amortized O(1)?
// Her element 1 defe push, 1 defe pop olunur her iki stack-de
// Toplam: 2n emeliyyat / n dequeue = O(1) amortized
```

### BFS with Queue
```php
<?php
// BFS - Breadth-First Search
function bfs(array $graph, int $start): array {
    $visited = [$start => true];
    $queue = new SplQueue();
    $queue->enqueue($start);
    $result = [];

    while (!$queue->isEmpty()) {
        $node = $queue->dequeue();
        $result[] = $node;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $visited[$neighbor] = true;
                $queue->enqueue($neighbor);
            }
        }
    }

    return $result;
}

// Level-order Tree Traversal
function levelOrder(?TreeNode $root): array {
    if ($root === null) return [];

    $result = [];
    $queue = new SplQueue();
    $queue->enqueue($root);

    while (!$queue->isEmpty()) {
        $levelSize = $queue->count();
        $level = [];

        for ($i = 0; $i < $levelSize; $i++) {
            $node = $queue->dequeue();
            $level[] = $node->val;

            if ($node->left) $queue->enqueue($node->left);
            if ($node->right) $queue->enqueue($node->right);
        }

        $result[] = $level;
    }

    return $result;
}
```

### Task Scheduler Problem
```php
<?php
// n=2 cooldown ile tasklari planlasdirmaq
// ["A","A","A","B","B","B"], n=2
// Cavab: A B _ A B _ A B = 8

function leastInterval(array $tasks, int $n): int {
    $freq = array_count_values($tasks);
    rsort($freq);

    $maxFreq = $freq[0];
    $idleSlots = ($maxFreq - 1) * $n;

    for ($i = 1; $i < count($freq); $i++) {
        $idleSlots -= min($freq[$i], $maxFreq - 1);
    }

    $idleSlots = max(0, $idleSlots);
    return count($tasks) + $idleSlots;
}
```

### Sliding Window Maximum (Deque istifade ile)
```php
<?php
function maxSlidingWindow(array $nums, int $k): array {
    $deque = []; // Indeksleri saxlayir, azalan sirada
    $result = [];

    for ($i = 0; $i < count($nums); $i++) {
        // Pencereden cixanlari sil
        while (!empty($deque) && $deque[0] < $i - $k + 1) {
            array_shift($deque);
        }

        // Arxadan kicikleri sil
        while (!empty($deque) && $nums[end($deque)] < $nums[$i]) {
            array_pop($deque);
        }

        $deque[] = $i;

        if ($i >= $k - 1) {
            $result[] = $nums[$deque[0]];
        }
    }

    return $result;
}

// [1,3,-1,-3,5,3,6,7], k=3
// Window [1,3,-1] max=3
// Window [3,-1,-3] max=3
// Window [-1,-3,5] max=5
// ...
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Emeliyyat | Simple Queue | Circular Queue | Priority Queue |
|-----------|-------------|----------------|----------------|
| enqueue | O(1) | O(1) | O(log n) |
| dequeue | O(n)* | O(1) | O(log n) |
| peek | O(1) | O(1) | O(1) |
| isEmpty | O(1) | O(1) | O(1) |

*array_shift sebebi ile. Linked-list based olsa O(1).

Space: O(n)

## Tipik Meseleler (Common Problems)

### 1. Number of Islands (BFS)
```php
<?php
function numIslands(array &$grid): int {
    $count = 0;
    $rows = count($grid);
    $cols = count($grid[0]);

    for ($i = 0; $i < $rows; $i++) {
        for ($j = 0; $j < $cols; $j++) {
            if ($grid[$i][$j] === '1') {
                $count++;
                bfsFlood($grid, $i, $j, $rows, $cols);
            }
        }
    }
    return $count;
}

function bfsFlood(array &$grid, int $r, int $c, int $rows, int $cols): void {
    $queue = new SplQueue();
    $queue->enqueue([$r, $c]);
    $grid[$r][$c] = '0';
    $dirs = [[0,1],[0,-1],[1,0],[-1,0]];

    while (!$queue->isEmpty()) {
        [$cr, $cc] = $queue->dequeue();
        foreach ($dirs as [$dr, $dc]) {
            $nr = $cr + $dr;
            $nc = $cc + $dc;
            if ($nr >= 0 && $nr < $rows && $nc >= 0 && $nc < $cols && $grid[$nr][$nc] === '1') {
                $grid[$nr][$nc] = '0';
                $queue->enqueue([$nr, $nc]);
            }
        }
    }
}
```

### 2. Rotten Oranges (Multi-source BFS)
```php
<?php
function orangesRotting(array &$grid): int {
    $queue = new SplQueue();
    $fresh = 0;
    $rows = count($grid);
    $cols = count($grid[0]);

    for ($i = 0; $i < $rows; $i++) {
        for ($j = 0; $j < $cols; $j++) {
            if ($grid[$i][$j] === 2) $queue->enqueue([$i, $j]);
            elseif ($grid[$i][$j] === 1) $fresh++;
        }
    }

    if ($fresh === 0) return 0;
    $minutes = -1;
    $dirs = [[0,1],[0,-1],[1,0],[-1,0]];

    while (!$queue->isEmpty()) {
        $size = $queue->count();
        $minutes++;
        for ($k = 0; $k < $size; $k++) {
            [$r, $c] = $queue->dequeue();
            foreach ($dirs as [$dr, $dc]) {
                $nr = $r + $dr;
                $nc = $c + $dc;
                if ($nr >= 0 && $nr < $rows && $nc >= 0 && $nc < $cols && $grid[$nr][$nc] === 1) {
                    $grid[$nr][$nc] = 2;
                    $fresh--;
                    $queue->enqueue([$nr, $nc]);
                }
            }
        }
    }

    return $fresh === 0 ? $minutes : -1;
}
```

## Interview Suallari

**S: Queue ne vaxt istifade olunur?**
C: BFS, task scheduling, print queue, message queue, breadth-level processing, rate limiting.

**S: Circular queue-nun ustunluyu?**
C: Adi array queue-da dequeue O(n)-dir (shift lazim). Circular queue-da front pointer hereket edir, shift lazim deyil -- O(1).

**S: Priority queue nece realize olunur?**
C: Adeten binary heap ile. Insert ve extract O(log n). Array sort ile olsa extract O(n log n) olardir.

**S: 2 stack ile queue niye amortized O(1)?**
C: Her element iki defe kocurulur (push->pop stack). n element ucun toplam 2n is. Dequeue basina: 2n/n = O(1).

## PHP/Laravel ile Elaqe

```php
<?php
// 1. Laravel Queue sistemi
// Queue driver-ler: database, redis, sqs, beanstalkd

// Job yaratmaq:
// php artisan make:job ProcessOrder
class ProcessOrder implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void {
        // Isi gor...
    }
}

// Dispatch:
// ProcessOrder::dispatch($order);

// Priority queues:
// ProcessOrder::dispatch($order)->onQueue('high');

// Worker:
// php artisan queue:work --queue=high,default

// 2. Laravel Event Broadcasting
// Event -> Queue -> Broadcast to websocket

// 3. Rate Limiting (queue pattern)
// Token bucket, sliding window -- queue ile realize olunur

// 4. PHP SplPriorityQueue istifadesi
$pq = new SplPriorityQueue();
$pq->insert(['job' => 'email'], 5);
$pq->insert(['job' => 'report'], 1);
$pq->insert(['job' => 'alert'], 10);
// alert, email, report sirasinda islenir

// 5. Message queues (RabbitMQ, Kafka):
// Producer -> Queue -> Consumer
// Laravel Horizon: Redis queue monitoring
```
