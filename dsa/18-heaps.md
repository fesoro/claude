# Heaps (Senior)

## Konsept (Concept)

Heap complete binary tree-dir, her node usaqlarindan boyuk (max-heap) ve ya kicikdir (min-heap). Array ile realize olunur.

```
Max-Heap:              Min-Heap:
       90                    1
      /  \                 /   \
    70    80              3     5
   / \   /              / \   /
  40 50 60             7   9  8

Array representation (0-indexed):
[90, 70, 80, 40, 50, 60]

Parent(i) = (i-1) / 2
Left(i)   = 2*i + 1
Right(i)  = 2*i + 2

Index: 0   1   2   3   4   5
       90  70  80  40  50  60
       ^parent  ^left ^right of index 1
```

## Nece Isleyir? (How does it work?)

### Insert (Bubble Up / Sift Up):
```
Insert 95 into max-heap [90, 70, 80, 40, 50, 60]:

1. Sona elave:    [90, 70, 80, 40, 50, 60, 95]
2. Parent(6)=2:   95 > 80? Beli, swap -> [90, 70, 95, 40, 50, 60, 80]
3. Parent(2)=0:   95 > 90? Beli, swap -> [95, 70, 90, 40, 50, 60, 80]
4. Root-a catdi, dayandir.
```

### Extract Max (Bubble Down / Sift Down):
```
Extract max from [95, 70, 90, 40, 50, 60, 80]:

1. Max = 95 (root), son elementi root-a qoy: [80, 70, 90, 40, 50, 60]
2. 80 vs children (70, 90): 90 > 80, swap -> [90, 70, 80, 40, 50, 60]
3. 80 vs children (60): 80 > 60, dayandir.
```

## Implementasiya (Implementation)

### Min-Heap
```php
<?php
class MinHeap {
    private array $heap = [];

    public function insert(int $val): void {
        $this->heap[] = $val;
        $this->bubbleUp(count($this->heap) - 1);
    }

    public function extractMin(): int {
        if (empty($this->heap)) throw new UnderflowException();

        $min = $this->heap[0];
        $last = array_pop($this->heap);

        if (!empty($this->heap)) {
            $this->heap[0] = $last;
            $this->bubbleDown(0);
        }

        return $min;
    }

    public function peek(): int {
        return $this->heap[0] ?? throw new UnderflowException();
    }

    public function size(): int {
        return count($this->heap);
    }

    private function bubbleUp(int $i): void {
        while ($i > 0) {
            $parent = intdiv($i - 1, 2);
            if ($this->heap[$parent] <= $this->heap[$i]) break;
            [$this->heap[$parent], $this->heap[$i]] = [$this->heap[$i], $this->heap[$parent]];
            $i = $parent;
        }
    }

    private function bubbleDown(int $i): void {
        $n = count($this->heap);
        while (true) {
            $smallest = $i;
            $left = 2 * $i + 1;
            $right = 2 * $i + 2;

            if ($left < $n && $this->heap[$left] < $this->heap[$smallest]) {
                $smallest = $left;
            }
            if ($right < $n && $this->heap[$right] < $this->heap[$smallest]) {
                $smallest = $right;
            }

            if ($smallest === $i) break;

            [$this->heap[$i], $this->heap[$smallest]] = [$this->heap[$smallest], $this->heap[$i]];
            $i = $smallest;
        }
    }
}
```

### Heapify (Build Heap) - O(n)
```php
<?php
function heapify(array &$arr): void {
    $n = count($arr);
    // Son parent-den basla, yuxariya dog get
    for ($i = intdiv($n, 2) - 1; $i >= 0; $i--) {
        siftDown($arr, $i, $n);
    }
}

function siftDown(array &$arr, int $i, int $n): void {
    while (true) {
        $largest = $i;
        $left = 2 * $i + 1;
        $right = 2 * $i + 2;

        if ($left < $n && $arr[$left] > $arr[$largest]) $largest = $left;
        if ($right < $n && $arr[$right] > $arr[$largest]) $largest = $right;

        if ($largest === $i) break;
        [$arr[$i], $arr[$largest]] = [$arr[$largest], $arr[$i]];
        $i = $largest;
    }
}
// Niye O(n)? Asagi seviyyelerde cox node var amma az is gorurler.
// Toplam: sum(n/2^(h+1) * h) for h=0..log(n) = O(n)
```

### Heap Sort
```php
<?php
function heapSort(array &$arr): void {
    $n = count($arr);

    // 1. Max-heap yarad: O(n)
    for ($i = intdiv($n, 2) - 1; $i >= 0; $i--) {
        siftDown($arr, $i, $n);
    }

    // 2. Bir-bir extract: O(n log n)
    for ($end = $n - 1; $end > 0; $end--) {
        [$arr[0], $arr[$end]] = [$arr[$end], $arr[0]]; // Max-i sona qoy
        siftDown($arr, 0, $end); // Qalanini heapify
    }
}
// Toplam: O(n log n), in-place, amma stable deyil
```

### Top-K Problems
```php
<?php
// K en boyuk elementi tap: O(n log k)
function topK(array $nums, int $k): array {
    $minHeap = new SplMinHeap();

    foreach ($nums as $num) {
        $minHeap->insert($num);
        if ($minHeap->count() > $k) {
            $minHeap->extract(); // En kicik cixsin
        }
    }

    $result = [];
    while (!$minHeap->isEmpty()) {
        $result[] = $minHeap->extract();
    }
    return $result;
}

// K-ci en boyuk element: O(n log k)
function kthLargest(array $nums, int $k): int {
    $minHeap = new SplMinHeap();
    foreach ($nums as $num) {
        $minHeap->insert($num);
        if ($minHeap->count() > $k) {
            $minHeap->extract();
        }
    }
    return $minHeap->top();
}

// Alternativ: PHP SplPriorityQueue
function topKFrequent(array $nums, int $k): array {
    $freq = array_count_values($nums);
    $pq = new SplPriorityQueue();

    foreach ($freq as $num => $count) {
        $pq->insert($num, $count);
    }

    $result = [];
    for ($i = 0; $i < $k; $i++) {
        $result[] = $pq->extract();
    }
    return $result;
}
```

### Merge K Sorted Arrays
```php
<?php
function mergeKSorted(array $arrays): array {
    $pq = new SplPriorityQueue();
    $result = [];

    // Her massivden ilk elementi elave et
    foreach ($arrays as $i => $arr) {
        if (!empty($arr)) {
            $pq->insert([$arr[0], $i, 0], -$arr[0]); // min-priority
        }
    }

    while (!$pq->isEmpty()) {
        [$val, $arrIdx, $elemIdx] = $pq->extract();
        $result[] = $val;

        $nextIdx = $elemIdx + 1;
        if ($nextIdx < count($arrays[$arrIdx])) {
            $nextVal = $arrays[$arrIdx][$nextIdx];
            $pq->insert([$nextVal, $arrIdx, $nextIdx], -$nextVal);
        }
    }

    return $result;
}
// k massiv, toplam n element: O(n log k)
```

### Median Finder (Two Heaps)
```php
<?php
class MedianFinder {
    private SplMaxHeap $maxHeap; // Sol yarim (kicikler)
    private SplMinHeap $minHeap; // Sag yarim (boyukler)

    public function __construct() {
        $this->maxHeap = new SplMaxHeap();
        $this->minHeap = new SplMinHeap();
    }

    public function addNum(int $num): void {
        $this->maxHeap->insert($num);

        // Balans: maxHeap-in max-i minHeap-in min-inden boyuk olmamali
        $this->minHeap->insert($this->maxHeap->extract());

        // Size balans: maxHeap >= minHeap
        if ($this->maxHeap->count() < $this->minHeap->count()) {
            $this->maxHeap->insert($this->minHeap->extract());
        }
    }

    public function findMedian(): float {
        if ($this->maxHeap->count() > $this->minHeap->count()) {
            return $this->maxHeap->top();
        }
        return ($this->maxHeap->top() + $this->minHeap->top()) / 2.0;
    }
}
// addNum(1): max=[1], min=[]        median=1
// addNum(2): max=[1], min=[2]       median=1.5
// addNum(3): max=[2,1], min=[3]     median=2
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Emeliyyat | Vaxt |
|-----------|------|
| Insert | O(log n) |
| Extract Min/Max | O(log n) |
| Peek Min/Max | O(1) |
| Build Heap (heapify) | O(n) |
| Heap Sort | O(n log n) |
| Search | O(n) |

Space: O(n)

## Tipik Meseleler (Common Problems)

### 1. Task Scheduler
```php
<?php
function leastInterval(array $tasks, int $n): int {
    $freq = array_count_values($tasks);
    $maxHeap = new SplMaxHeap();
    foreach ($freq as $f) $maxHeap->insert($f);

    $time = 0;
    while (!$maxHeap->isEmpty()) {
        $temp = [];
        for ($i = 0; $i <= $n; $i++) {
            if (!$maxHeap->isEmpty()) {
                $count = $maxHeap->extract();
                if ($count > 1) $temp[] = $count - 1;
            }
            $time++;
            if ($maxHeap->isEmpty() && empty($temp)) break;
        }
        foreach ($temp as $t) $maxHeap->insert($t);
    }
    return $time;
}
```

### 2. Sort Characters by Frequency
```php
<?php
function frequencySort(string $s): string {
    $freq = array_count_values(str_split($s));
    $pq = new SplPriorityQueue();

    foreach ($freq as $char => $count) {
        $pq->insert($char, $count);
    }

    $result = '';
    while (!$pq->isEmpty()) {
        $char = $pq->extract();
        $result .= str_repeat($char, $freq[$char]);
    }
    return $result;
}
// "tree" -> "eert" ve ya "eetr"
```

### 3. K Closest Points to Origin
```php
<?php
function kClosest(array $points, int $k): array {
    $maxHeap = new SplPriorityQueue();

    foreach ($points as $point) {
        $dist = $point[0] ** 2 + $point[1] ** 2;
        $maxHeap->insert($point, $dist); // max-heap by distance

        if ($maxHeap->count() > $k) {
            $maxHeap->extract(); // En uzagi cixar
        }
    }

    $result = [];
    while (!$maxHeap->isEmpty()) {
        $result[] = $maxHeap->extract();
    }
    return $result;
}
```

## Interview Suallari

**S: Heap vs BST ferqi?**
C: Heap yalniz min/max-i suretli verir O(1). BST her elementi axtara bilir O(log n). Heap array-based (cache-friendly), BST pointer-based.

**S: Niye build heap O(n)-dir, n insert O(n log n) deyil?**
C: Asagi seviyyelerde cox node var amma az is gorurler. Riyazi olaraq: sum(n/2^(h+1) * h) = O(n). Tek-tek insert etsek her biri O(log n), toplam O(n log n).

**S: Heap sort niye practice-de nadir istifade olunur?**
C: Stable deyil, cache performance yavas (uzaq indekslere jump edir). QuickSort ve MergeSort adeten daha suretlidir.

**S: Two heaps pattern ne vaxt istifade olunur?**
C: Median tapmaq, data stream-de percentile, sliding window median.

## PHP/Laravel ile Elaqe

```php
<?php
// 1. SplMinHeap, SplMaxHeap, SplPriorityQueue
$minHeap = new SplMinHeap();
$maxHeap = new SplMaxHeap();

// 2. Laravel Priority Queue (Job dispatching)
// ProcessOrder::dispatch($order)->onQueue('high');
// php artisan queue:work --queue=high,default,low

// 3. Task scheduling (OS level)
// Laravel Scheduler internally daxili olaraq priority sistemi istifade edir
// $schedule->job(new HeartbeatJob)->everyMinute();

// 4. Event priority
// Laravel event listener priority:
// Event::listen('event', [Handler::class, 'handle'], priority: 10);

// 5. Rate limiting (leaky bucket = queue/heap pattern)

// 6. Database query optimization
// SELECT * FROM products ORDER BY price LIMIT 10
// MySQL internally top-K ucun heap istifade edir
```
