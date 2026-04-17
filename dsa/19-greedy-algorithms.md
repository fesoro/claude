# Greedy Algorithms (Acgoz Algoritmler)

## Konsept (Concept)

Greedy algoritm her addimda lokal olaraq en yaxsi secimi edir, umid edir ki bu global optimal helle aparacaq. DP-den ferqli olaraq, greedy geri qayitmaz ve butun alt-problemleri arasdirmaz.

```
Greedy isleyir:          Greedy islemir:
  Activity Selection       0/1 Knapsack
  Coin Change (xususi)     Traveling Salesman
  Huffman Coding           Longest Path
  Minimum Spanning Tree    Subset Sum

Greedy nece isleyir:
  Problem: En az coin ile 36 cent ver (coins: 25, 10, 5, 1)
  Greedy: 25 + 10 + 1 = 3 coin ✓

  Problem: En az coin ile 6 (coins: 4, 3, 1)
  Greedy: 4 + 1 + 1 = 3 coin ✗
  Optimal: 3 + 3 = 2 coin
```

### Greedy Choice Property:
- Lokal optimal secim global optimal helle aparir
- Bu xususiyyeti isbat etmek lazimdir (exchange argument)

### Greedy vs DP:
```
DP:     Butun alt-problemleri hell et, en yaxsisini sec
Greedy: Yalniz bir secim et, geri baxma
DP:     O(n^2) ve ya O(n*W)
Greedy: Adeten O(n log n) sorting + O(n) scan
```

## Nece Isleyir? (How does it work?)

### Activity Selection:
```
Activities (start, end):
  A(1,4), B(3,5), C(0,6), D(5,7), E(3,9), F(5,9), G(6,10), H(8,11)

Siralama (end time-a gore):
  A(1,4), B(3,5), C(0,6), D(5,7), E(3,9), F(5,9), G(6,10), H(8,11)

Greedy:
  Select A(1,4) -> next start >= 4
  Select D(5,7) -> next start >= 7
  Select H(8,11) -> done!

Result: 3 activity [A, D, H]
```

## Implementasiya (Implementation)

```php
<?php

/**
 * Activity Selection
 * En cox activity sec ki overlap olmasin
 * Time: O(n log n), Space: O(1)
 */
function activitySelection(array $activities): array
{
    // End time-a gore sirala
    usort($activities, fn($a, $b) => $a[1] - $b[1]);

    $selected = [$activities[0]];
    $lastEnd = $activities[0][1];

    for ($i = 1; $i < count($activities); $i++) {
        if ($activities[$i][0] >= $lastEnd) {
            $selected[] = $activities[$i];
            $lastEnd = $activities[$i][1];
        }
    }

    return $selected;
}

/**
 * Fractional Knapsack
 * Esyalari bolmek olar (0/1 knapsack-den ferqli)
 * Time: O(n log n), Space: O(1)
 */
function fractionalKnapsack(array $items, int $capacity): float
{
    // Value/weight ratio-ya gore azalan sirala
    usort($items, fn($a, $b) => ($b['value'] / $b['weight']) <=> ($a['value'] / $a['weight']));

    $totalValue = 0.0;
    $remaining = $capacity;

    foreach ($items as $item) {
        if ($remaining <= 0) break;

        if ($item['weight'] <= $remaining) {
            $totalValue += $item['value'];
            $remaining -= $item['weight'];
        } else {
            // Hisseni gotur
            $fraction = $remaining / $item['weight'];
            $totalValue += $item['value'] * $fraction;
            $remaining = 0;
        }
    }

    return $totalValue;
}

/**
 * Coin Change - Greedy (yalniz xususi coin setleri ucun isleyir)
 * US coins [25, 10, 5, 1] ucun optimal isleyir
 * Time: O(n), Space: O(1)
 */
function coinChangeGreedy(array $coins, int $amount): array
{
    rsort($coins); // Boyukden baslayiq
    $result = [];

    foreach ($coins as $coin) {
        while ($amount >= $coin) {
            $result[] = $coin;
            $amount -= $coin;
        }
    }

    return $amount === 0 ? $result : []; // Bos = mumkun deyil
}

/**
 * Huffman Coding
 * Tezlik esasli optimal prefix code
 * Time: O(n log n), Space: O(n)
 */
function huffmanCoding(array $freq): array
{
    // Min-heap yaradir
    $pq = new SplPriorityQueue();
    $pq->setExtractFlags(SplPriorityQueue::EXTR_BOTH);

    foreach ($freq as $char => $f) {
        $pq->insert(['char' => $char, 'left' => null, 'right' => null], -$f);
    }

    while ($pq->count() > 1) {
        $left = $pq->extract();
        $right = $pq->extract();

        $merged = [
            'char' => null,
            'left' => $left['data'],
            'right' => $right['data'],
        ];

        $pq->insert($merged, $left['priority'] + $right['priority']);
    }

    $root = $pq->extract()['data'];

    // Kodlari cixar
    $codes = [];
    generateCodes($root, '', $codes);
    return $codes;
}

function generateCodes($node, string $code, array &$codes): void
{
    if ($node === null) return;

    if ($node['char'] !== null) {
        $codes[$node['char']] = $code ?: '0';
        return;
    }

    generateCodes($node['left'], $code . '0', $codes);
    generateCodes($node['right'], $code . '1', $codes);
}

/**
 * Jump Game (LeetCode 55)
 * Her index-den max jump mesafesi verilir, sona catmaq olurmu?
 * Time: O(n), Space: O(1)
 */
function canJump(array $nums): bool
{
    $maxReach = 0;

    for ($i = 0; $i < count($nums); $i++) {
        if ($i > $maxReach) return false;
        $maxReach = max($maxReach, $i + $nums[$i]);
    }

    return true;
}

/**
 * Jump Game II (LeetCode 45)
 * Minimum jump sayi
 * Time: O(n), Space: O(1)
 */
function jump(array $nums): int
{
    $jumps = 0;
    $currentEnd = 0;
    $farthest = 0;

    for ($i = 0; $i < count($nums) - 1; $i++) {
        $farthest = max($farthest, $i + $nums[$i]);
        if ($i === $currentEnd) {
            $jumps++;
            $currentEnd = $farthest;
        }
    }

    return $jumps;
}

/**
 * Gas Station (LeetCode 134)
 * Time: O(n), Space: O(1)
 */
function canCompleteCircuit(array $gas, array $cost): int
{
    $totalTank = 0;
    $currentTank = 0;
    $startStation = 0;

    for ($i = 0; $i < count($gas); $i++) {
        $diff = $gas[$i] - $cost[$i];
        $totalTank += $diff;
        $currentTank += $diff;

        if ($currentTank < 0) {
            $startStation = $i + 1;
            $currentTank = 0;
        }
    }

    return $totalTank >= 0 ? $startStation : -1;
}

/**
 * Task Scheduler (LeetCode 621)
 * Time: O(n), Space: O(1)
 */
function leastInterval(array $tasks, int $n): int
{
    $freq = array_count_values($tasks);
    rsort($freq);

    $maxFreq = $freq[0];
    $maxCount = 0;
    foreach ($freq as $f) {
        if ($f === $maxFreq) $maxCount++;
        else break;
    }

    // Formula: (maxFreq - 1) * (n + 1) + maxCount
    return max(count($tasks), ($maxFreq - 1) * ($n + 1) + $maxCount);
}

/**
 * Assign Cookies (LeetCode 455)
 * Time: O(n log n + m log m), Space: O(1)
 */
function findContentChildren(array $children, array $cookies): int
{
    sort($children);
    sort($cookies);

    $child = 0;
    $cookie = 0;

    while ($child < count($children) && $cookie < count($cookies)) {
        if ($cookies[$cookie] >= $children[$child]) {
            $child++;
        }
        $cookie++;
    }

    return $child;
}

// --- Test ---
$activities = [[1,4],[3,5],[0,6],[5,7],[3,9],[5,9],[6,10],[8,11]];
echo "Activities: " . count(activitySelection($activities)) . "\n"; // 3

$items = [
    ['weight' => 10, 'value' => 60],
    ['weight' => 20, 'value' => 100],
    ['weight' => 30, 'value' => 120],
];
echo "Fractional Knapsack: " . fractionalKnapsack($items, 50) . "\n"; // 240

echo "Coins for 36: " . implode(', ', coinChangeGreedy([25,10,5,1], 36)) . "\n"; // 25,10,1

echo "Can jump [2,3,1,1,4]: " . (canJump([2,3,1,1,4]) ? 'yes' : 'no') . "\n"; // yes
echo "Min jumps: " . jump([2,3,1,1,4]) . "\n"; // 2

$freq = ['a' => 5, 'b' => 9, 'c' => 12, 'd' => 13, 'e' => 16, 'f' => 45];
$codes = huffmanCoding($freq);
echo "Huffman codes:\n";
foreach ($codes as $char => $code) echo "  $char: $code\n";
```

## Vaxt ve Yaddas Murakkabliyi (Time & Space Complexity)

| Problem | Time | Space | Qeyd |
|---------|------|-------|------|
| Activity Selection | O(n log n) | O(1) | Sort + scan |
| Fractional Knapsack | O(n log n) | O(1) | Sort + scan |
| Huffman Coding | O(n log n) | O(n) | Priority queue |
| Jump Game | O(n) | O(1) | Single pass |
| Gas Station | O(n) | O(1) | Single pass |
| Task Scheduler | O(n) | O(1) | Frequency count |

## Tipik Meseler (Common Problems)

### 1. Non-overlapping Intervals (LeetCode 435)
```php
<?php
function eraseOverlapIntervals(array $intervals): int
{
    usort($intervals, fn($a, $b) => $a[1] - $b[1]);
    $count = 0;
    $lastEnd = $intervals[0][1];

    for ($i = 1; $i < count($intervals); $i++) {
        if ($intervals[$i][0] < $lastEnd) {
            $count++; // sil
        } else {
            $lastEnd = $intervals[$i][1];
        }
    }

    return $count;
}
```

### 2. Partition Labels (LeetCode 763)
```php
<?php
function partitionLabels(string $s): array
{
    $lastIndex = [];
    for ($i = 0; $i < strlen($s); $i++) {
        $lastIndex[$s[$i]] = $i;
    }

    $result = [];
    $start = 0;
    $end = 0;

    for ($i = 0; $i < strlen($s); $i++) {
        $end = max($end, $lastIndex[$s[$i]]);
        if ($i === $end) {
            $result[] = $end - $start + 1;
            $start = $end + 1;
        }
    }

    return $result;
}
```

### 3. Minimum Number of Arrows (LeetCode 452)
```php
<?php
function findMinArrowShots(array $points): int
{
    usort($points, fn($a, $b) => $a[1] <=> $b[1]);
    $arrows = 1;
    $lastEnd = $points[0][1];

    for ($i = 1; $i < count($points); $i++) {
        if ($points[$i][0] > $lastEnd) {
            $arrows++;
            $lastEnd = $points[$i][1];
        }
    }

    return $arrows;
}
```

## Interview Suallari

1. **Greedy nece isbat edilir?**
   - Exchange argument: optimal helden bir elementi greedy secim ile deyis, helin daha pis olmadiqini goster
   - Greedy stays ahead: her addimda greedy en az optimal qadar yaxsi olduqunu goster

2. **Greedy vs DP - ne vaxt hangisi?**
   - Greedy: lokal optimal = global optimal oldugunu isbat ede bilersen
   - DP: butun imkanlari arasdirmaq lazimdir
   - Greedy daha suretli amma hemise dogru deyil

3. **Activity Selection niye end time-a gore siralayin?**
   - En tez biten activity-ni sec ki basqalarina yer qalsin
   - Start time-a gore siralasan optimal olmur

4. **Huffman coding optimal oldugunu nece bilirik?**
   - Isbat olunub ki prefix-free code-lar arasinda Huffman en qisa ortalama bit uzunlugunu verir
   - Greedy: en az tezlikli 2 node-u birlesdir

## PHP/Laravel ile Elaqe

- **Task scheduling**: Laravel queue job prioritization
- **Load balancing**: Server-ler arasi is bolusdurme
- **Caching strategy**: LRU/LFU cache eviction greedy yanasmadi
- **Compression**: Huffman coding file compression-da istifade olunur
- **API rate limiting**: Greedy allocation of request quotas
