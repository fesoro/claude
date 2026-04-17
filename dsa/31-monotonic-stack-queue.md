# Monotonic Stack/Queue (Monoton Yığın/Növbə)

## Konsept (Concept)

**Monotonic Stack** — elementlerini ancaq artan (increasing) və ya azalan (decreasing) sırada saxlayan xüsusi stack-dir. Yeni element əlavə olunmamışdan əvvəl, monotonic şərti pozan elementlər çıxarılır.

**Monotonic Queue** — benzer prinsiplə işləyən, amma həm əvvəl, həm axırdan elementləri dəyişə bilən double-ended queue (deque).

```
Monotonic Decreasing Stack (elementler yuxari asagiya doğru azalır):

push 3 -> [3]
push 1 -> [3, 1]
push 4 -> 4 > 1-in ustunden, 4 > 3-un ustunden -> [4]
push 2 -> [4, 2]
push 5 -> 5 > 2 ve 5 > 4 -> [5]

Stack bottom-dan top-a: descending.
```

### Ne zaman istifadə etməli?
- **Next Greater / Smaller Element** problemləri
- **Previous Greater / Smaller Element**
- **Daily Temperatures**
- **Stock Span Problem**
- **Largest Rectangle in Histogram**
- **Sliding Window Maximum/Minimum** (deque versiyası)
- **Trapping Rain Water**
- **Remove K Digits** (leksikoqrafik minimum)

### Monotonic Stack növləri:
1. **Increasing stack**: Top-dan bottom-a artir (bottom: biggest)
2. **Decreasing stack**: Top-dan bottom-a azalir (bottom: smallest)

## Necə İşləyir? (How does it work?)

### Next Greater Element:
```
nums = [2, 1, 2, 4, 3, 1]

Sağdan sola gedirik ve decreasing stack saxlayırıq:

i=5, num=1: stack bos -> result[5] = -1, stack=[1]
i=4, num=3: 3 > 1 pop, stack bos -> result[4] = -1, stack=[3]
i=3, num=4: 4 > 3 pop, stack bos -> result[3] = -1, stack=[4]
i=2, num=2: 2 < 4 -> result[2] = 4, stack=[4,2]
i=1, num=1: 1 < 2 -> result[1] = 2, stack=[4,2,1]
i=0, num=2: 2 > 1 pop, 2 == 2 pop, 2 < 4 -> result[0] = 4, stack=[4,2]

Result: [4, 2, 4, -1, -1, -1]
```

### Largest Rectangle in Histogram:
```
heights = [2, 1, 5, 6, 2, 3]

Increasing monotonic stack (indexler saxlanır):

i=0, h=2: stack=[0], heights=[2]
i=1, h=1: 1 < 2 pop 0, width = 1-0 = 1, area = 2*1 = 2
         stack=[1], heights=[1]
i=2, h=5: stack=[1,2]
i=3, h=6: stack=[1,2,3]
i=4, h=2: 2 < 6 pop 3, width = 4-2-1 = 1, area = 6*1 = 6
         2 < 5 pop 2, width = 4-1-1 = 2, area = 5*2 = 10 (max!)
         stack=[1,4]
i=5, h=3: stack=[1,4,5]
End: pop 5, width = 6-4-1 = 1, area = 3*1 = 3
     pop 4, width = 6-1-1 = 4, area = 2*4 = 8
     pop 1, width = 6, area = 1*6 = 6

Max: 10
```

### Sliding Window Maximum (Monotonic Deque):
```
nums = [1, 3, -1, -3, 5, 3, 6, 7], k = 3

Decreasing deque (indexler):

i=0: deque=[0]
i=1: nums[0]=1 < 3 pop -> deque=[1]
i=2: deque=[1,2] (max: nums[1]=3)
     Window [1,3,-1] -> max = 3
i=3: deque=[1,2,3]
     front=1 hele windowda, Window [3,-1,-3] -> max = 3
i=4: front=1 window-dan cıxdı pop front
     nums[3]=-3 < 5 pop, nums[2]=-1 < 5 pop -> deque=[4]
     Window [-1,-3,5] -> max = 5
...

Result: [3,3,5,5,6,7]
```

## Implementasiya (Implementation)

```php
<?php

class MonotonicStack
{
    // Next Greater Element
    public function nextGreaterElements(array $nums): array
    {
        $n = count($nums);
        $result = array_fill(0, $n, -1);
        $stack = []; // decreasing stack of indexes

        for ($i = 0; $i < $n; $i++) {
            while (!empty($stack) && $nums[end($stack)] < $nums[$i]) {
                $idx = array_pop($stack);
                $result[$idx] = $nums[$i];
            }
            $stack[] = $i;
        }

        return $result;
    }

    // Next Greater Element II (circular array)
    public function nextGreaterElementsCircular(array $nums): array
    {
        $n = count($nums);
        $result = array_fill(0, $n, -1);
        $stack = [];

        // İki dəfə dolan (circular simulation)
        for ($i = 0; $i < 2 * $n; $i++) {
            $curr = $nums[$i % $n];
            while (!empty($stack) && $nums[end($stack)] < $curr) {
                $idx = array_pop($stack);
                $result[$idx] = $curr;
            }
            if ($i < $n) $stack[] = $i;
        }

        return $result;
    }

    // Daily Temperatures
    public function dailyTemperatures(array $temperatures): array
    {
        $n = count($temperatures);
        $result = array_fill(0, $n, 0);
        $stack = [];

        for ($i = 0; $i < $n; $i++) {
            while (!empty($stack) && $temperatures[end($stack)] < $temperatures[$i]) {
                $idx = array_pop($stack);
                $result[$idx] = $i - $idx;
            }
            $stack[] = $i;
        }

        return $result;
    }

    // Largest Rectangle in Histogram
    public function largestRectangleArea(array $heights): int
    {
        $heights[] = 0; // sentinel
        $stack = [];
        $maxArea = 0;
        $n = count($heights);

        for ($i = 0; $i < $n; $i++) {
            while (!empty($stack) && $heights[end($stack)] > $heights[$i]) {
                $h = $heights[array_pop($stack)];
                $w = empty($stack) ? $i : $i - end($stack) - 1;
                $maxArea = max($maxArea, $h * $w);
            }
            $stack[] = $i;
        }

        return $maxArea;
    }

    // Trapping Rain Water (monotonic stack version)
    public function trap(array $height): int
    {
        $stack = [];
        $water = 0;
        $n = count($height);

        for ($i = 0; $i < $n; $i++) {
            while (!empty($stack) && $height[end($stack)] < $height[$i]) {
                $bottom = array_pop($stack);
                if (empty($stack)) break;
                $left = end($stack);
                $width = $i - $left - 1;
                $h = min($height[$left], $height[$i]) - $height[$bottom];
                $water += $width * $h;
            }
            $stack[] = $i;
        }

        return $water;
    }

    // Remove K Digits (leksikoqrafik minimum)
    public function removeKdigits(string $num, int $k): string
    {
        $stack = [];

        for ($i = 0; $i < strlen($num); $i++) {
            while (!empty($stack) && $k > 0 && end($stack) > $num[$i]) {
                array_pop($stack);
                $k--;
            }
            $stack[] = $num[$i];
        }

        // Qalan k-ni axırdan sil
        while ($k > 0 && !empty($stack)) {
            array_pop($stack);
            $k--;
        }

        $result = ltrim(implode('', $stack), '0');
        return $result === '' ? '0' : $result;
    }
}

class MonotonicDeque
{
    // Sliding Window Maximum
    public function maxSlidingWindow(array $nums, int $k): array
    {
        $result = [];
        $deque = new SplDoublyLinkedList(); // indexlər saxlanılır

        for ($i = 0; $i < count($nums); $i++) {
            // Pəncərədən kənar indexləri sil (front)
            while (!$deque->isEmpty() && $deque->bottom() <= $i - $k) {
                $deque->shift();
            }
            // Kiçik elementləri sil (back)
            while (!$deque->isEmpty() && $nums[$deque->top()] < $nums[$i]) {
                $deque->pop();
            }
            $deque->push($i);

            if ($i >= $k - 1) {
                $result[] = $nums[$deque->bottom()];
            }
        }

        return $result;
    }

    // Sliding Window Minimum
    public function minSlidingWindow(array $nums, int $k): array
    {
        $result = [];
        $deque = new SplDoublyLinkedList();

        for ($i = 0; $i < count($nums); $i++) {
            while (!$deque->isEmpty() && $deque->bottom() <= $i - $k) {
                $deque->shift();
            }
            while (!$deque->isEmpty() && $nums[$deque->top()] > $nums[$i]) {
                $deque->pop();
            }
            $deque->push($i);

            if ($i >= $k - 1) {
                $result[] = $nums[$deque->bottom()];
            }
        }

        return $result;
    }
}

// Istifade
$ms = new MonotonicStack();
echo "Next greater: ";
print_r($ms->nextGreaterElements([2, 1, 2, 4, 3, 1]));
// [4, 2, 4, -1, -1, -1]

echo "Daily temperatures: ";
print_r($ms->dailyTemperatures([73, 74, 75, 71, 69, 72, 76, 73]));
// [1, 1, 4, 2, 1, 1, 0, 0]

echo "Largest rectangle: ";
echo $ms->largestRectangleArea([2, 1, 5, 6, 2, 3]); // 10

$md = new MonotonicDeque();
print_r($md->maxSlidingWindow([1, 3, -1, -3, 5, 3, 6, 7], 3));
// [3, 3, 5, 5, 6, 7]
```

## Vaxt və Yaddaş Mürəkkəbliyi (Time & Space Complexity)

| Əməliyyat | Time | Space |
|-----------|------|-------|
| Next Greater Element | O(n) | O(n) |
| Daily Temperatures | O(n) | O(n) |
| Largest Rectangle | O(n) | O(n) |
| Trapping Rain Water | O(n) | O(n) |
| Sliding Window Max | O(n) | O(k) |
| Remove K Digits | O(n) | O(n) |

**Qeyd**: Hər element stack-e yalnız bir dəfə daxil olur və bir dəfə çıxarılır, ona görə amortized O(n).

## Tipik Məsələlər (Common Problems)

### 1. Stock Span Problem
```php
public function calculateSpan(array $prices): array
{
    $n = count($prices);
    $span = [];
    $stack = []; // (price, span) cutleri

    foreach ($prices as $price) {
        $s = 1;
        while (!empty($stack) && $stack[count($stack)-1][0] <= $price) {
            $s += array_pop($stack)[1];
        }
        $stack[] = [$price, $s];
        $span[] = $s;
    }

    return $span;
}
// Input: [100, 80, 60, 70, 60, 75, 85]
// Output: [1, 1, 1, 2, 1, 4, 6]
```

### 2. Maximal Rectangle in Binary Matrix
```php
public function maximalRectangle(array $matrix): int
{
    if (empty($matrix)) return 0;
    $cols = count($matrix[0]);
    $heights = array_fill(0, $cols, 0);
    $maxArea = 0;

    foreach ($matrix as $row) {
        for ($j = 0; $j < $cols; $j++) {
            $heights[$j] = $row[$j] === '1' ? $heights[$j] + 1 : 0;
        }
        $maxArea = max($maxArea, $this->largestRectangleArea($heights));
    }
    return $maxArea;
}
```

### 3. Sum of Subarray Minimums
```php
public function sumSubarrayMins(array $arr): int
{
    $MOD = 1000000007;
    $n = count($arr);
    $left = array_fill(0, $n, 0);
    $right = array_fill(0, $n, 0);
    $stack = [];

    // Previous less element
    for ($i = 0; $i < $n; $i++) {
        while (!empty($stack) && $arr[end($stack)] >= $arr[$i]) {
            array_pop($stack);
        }
        $left[$i] = empty($stack) ? $i + 1 : $i - end($stack);
        $stack[] = $i;
    }

    $stack = [];
    // Next less element
    for ($i = $n - 1; $i >= 0; $i--) {
        while (!empty($stack) && $arr[end($stack)] > $arr[$i]) {
            array_pop($stack);
        }
        $right[$i] = empty($stack) ? $n - $i : end($stack) - $i;
        $stack[] = $i;
    }

    $sum = 0;
    for ($i = 0; $i < $n; $i++) {
        $sum = ($sum + $arr[$i] * $left[$i] * $right[$i]) % $MOD;
    }
    return $sum;
}
```

### 4. 132 Pattern Aşkar Etmek
```php
public function find132pattern(array $nums): bool
{
    $stack = [];
    $third = PHP_INT_MIN;

    for ($i = count($nums) - 1; $i >= 0; $i--) {
        if ($nums[$i] < $third) return true;
        while (!empty($stack) && end($stack) < $nums[$i]) {
            $third = array_pop($stack);
        }
        $stack[] = $nums[$i];
    }
    return false;
}
```

### 5. Online Stock Span (Streaming)
```php
class StockSpanner
{
    private array $stack = [];

    public function next(int $price): int
    {
        $span = 1;
        while (!empty($this->stack) && $this->stack[count($this->stack)-1][0] <= $price) {
            $span += array_pop($this->stack)[1];
        }
        $this->stack[] = [$price, $span];
        return $span;
    }
}
```

## Interview Sualları

1. **Monotonic stack ne üçün O(n) verir, halbuki içerisinde while loop var?**
   - Her element stack-e yalnız bir dəfə push və pop olunur. Amortized analysis-e görə O(n).

2. **Niyə indexleri yox, qiymətləri saxlayırıq?**
   - İndexleri saxlayanda həm qiymətə çatmaq, həm də məsafə (distance/width) hesablamaq mümkün olur.

3. **Sliding window maximum üçün niyə deque istifadə edirik?**
   - Həm başdan (window-dan çıxan indexləri), həm axırdan (yeni kiçik elementləri) silmək lazımdır. Stack bunu edə bilmir.

4. **Increasing vs decreasing stack nə zaman istifadə etməli?**
   - **Next/Previous Greater** -> decreasing stack
   - **Next/Previous Smaller** -> increasing stack

5. **Histogram problem-də sentinel niyə əlavə edirik?**
   - Sonda qalan stack elementlərini təbii şəkildə pop etmek üçün son əlavə olunan 0 işe yarayır.

6. **Monotonic stack-ı DP-dən ne fərqləndirir?**
   - DP overlap subproblems istifadə edir, monotonic stack isə "local dominance" qorunmasıdır.

7. **PHP-də array nece monotonic stack kimi işlədə bilərik?**
   - `array_push`, `array_pop`, `end()` funksiyaları ilə. Daha efficient olaraq `SplStack`.

## PHP/Laravel ilə Əlaqə

### Real-time analytics:
```php
// Her saat temperatur trend tapmaq
class TemperatureAnalyzer
{
    public function warmerDays(array $temperatures): array
    {
        $n = count($temperatures);
        $days = array_fill(0, $n, 0);
        $stack = [];

        for ($i = 0; $i < $n; $i++) {
            while (!empty($stack) && $temperatures[end($stack)] < $temperatures[$i]) {
                $prev = array_pop($stack);
                $days[$prev] = $i - $prev;
            }
            $stack[] = $i;
        }
        return $days;
    }
}
```

### Stock price analysis:
```php
// Laravel artisan command
class StockSpanCommand extends Command
{
    public function handle()
    {
        $prices = DB::table('stock_prices')
            ->where('symbol', 'AAPL')
            ->orderBy('date')
            ->pluck('price')
            ->toArray();

        $span = $this->calculateSpan($prices);
        // Her gün üçün neçə ardıcıl gün ərzində bu fiyatın altında qalıb?
    }
}
```

### Rate limiting monitor:
```php
// Son N saniyədə maksimum request sayı (sliding window max)
class RequestMonitor
{
    private SplDoublyLinkedList $deque;

    public function record(int $timestamp, int $requestCount): int
    {
        while (!$this->deque->isEmpty() && $this->deque->bottom()[0] < $timestamp - 60) {
            $this->deque->shift();
        }
        while (!$this->deque->isEmpty() && $this->deque->top()[1] < $requestCount) {
            $this->deque->pop();
        }
        $this->deque->push([$timestamp, $requestCount]);
        return $this->deque->bottom()[1]; // maksimum
    }
}
```

### Layihə nümunələri:
1. **Bid/ask price analytics** - trading platformalarda
2. **Server metric analysis** - son 5 dəqiqədə peak load
3. **Log analysis** - error burst detection
4. **Queue management** - priority-based message processing
5. **E-commerce** - price history trend analysis
