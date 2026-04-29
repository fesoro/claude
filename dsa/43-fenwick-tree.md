# Fenwick Tree / Binary Indexed Tree (Lead)

## İcmal

**Fenwick Tree** (Binary Indexed Tree, BIT) — dinamik prefix sum-ları həm query, həm update üçün O(log n) zamanda icra edən data strukturudur. 1994-cü ildə Peter Fenwick tərəfindən kəşf edilib.

**Segment Tree ilə müqayisə:**
- Fenwick Tree: daha az kod, daha az yaddaş, amma yalnız prefix sum / point update
- Segment Tree: daha çevik (min/max/custom), amma daha mürəkkəb

```
Array: [1, 7, 2, 0, 9, 5, 3, 8]  (1-indexed)
Index:  1  2  3  4  5  6  7  8

Fenwick Tree (BIT):
BIT[1] = 1          (index 1)
BIT[2] = 1+7 = 8    (index 1..2)
BIT[3] = 2          (index 3)
BIT[4] = 1+7+2+0=10 (index 1..4)
BIT[5] = 9          (index 5)
BIT[6] = 9+5 = 14   (index 5..6)
BIT[7] = 3          (index 7)
BIT[8] = 1+7+2+0+9+5+3+8=35 (index 1..8)
```

---

## Niyə Vacibdir

**Problem:** Dinamik range sum — array dəyişir, amma aralıq cəmlərini tez tapmaq lazımdır.

| Yanaşma | Update | Prefix Query |
|---------|--------|--------------|
| Sadə array | O(1) | O(n) |
| Prefix sum array | O(n) | O(1) |
| Fenwick Tree | O(log n) | O(log n) |
| Segment Tree | O(log n) | O(log n) |

Fenwick Tree Segment Tree-dən iki dəfə daha az yaddaş istifadə edir və kodu yarı qədər qısadır.

---

## Əsas Anlayışlar

### Lowbit (LSB) əməliyyatı
```
lowbit(i) = i & (-i)

i = 6 = 110 (binary)
-i = ...11111010 (two's complement)
i & (-i) = 000...0010 = 2

i = 8 = 1000
-i = ...11111000
i & (-i) = 1000 = 8
```

### Nə anlamına gəlir?
- `BIT[i]` sonuncu `lowbit(i)` elementi əhatə edir
- Misal: `BIT[6]` = sum[5..6], çünki `lowbit(6) = 2`
- Misal: `BIT[8]` = sum[1..8], çünki `lowbit(8) = 8`

### Update qaydası
`i`-ci elementi `val` qədər artır — bütün BIT node-ları güncəllənirlər:
```
update(6, +delta):
  BIT[6]  += delta   (lowbit=2, covers 5..6)
  BIT[8]  += delta   (lowbit=8, covers 1..8)
  (next: i += lowbit(i))
```

### Query qaydası
Prefix sum [1..i]:
```
query(7):
  sum += BIT[7]   (covers 7..7, lowbit=1)
  sum += BIT[6]   (covers 5..6, lowbit=2)
  sum += BIT[4]   (covers 1..4, lowbit=4)
  (next: i -= lowbit(i))
```

---

## Praktik Baxış

### PHP İmplementasiyası

```php
class FenwickTree
{
    private array $bit;
    private int $n;

    public function __construct(int $n)
    {
        $this->n = $n;
        $this->bit = array_fill(0, $n + 1, 0);
    }

    // Array-dən qur: O(n)
    public static function fromArray(array $nums): self
    {
        $n = count($nums);
        $ft = new self($n);
        foreach ($nums as $i => $val) {
            $ft->update($i + 1, $val); // 1-indexed
        }
        return $ft;
    }

    // Point update: arr[i] += delta → O(log n)
    public function update(int $i, int $delta): void
    {
        while ($i <= $this->n) {
            $this->bit[$i] += $delta;
            $i += $i & (-$i); // i += lowbit(i)
        }
    }

    // Prefix sum [1..i] → O(log n)
    public function query(int $i): int
    {
        $sum = 0;
        while ($i > 0) {
            $sum += $this->bit[$i];
            $i -= $i & (-$i); // i -= lowbit(i)
        }
        return $sum;
    }

    // Range sum [l..r] → O(log n)
    public function rangeQuery(int $l, int $r): int
    {
        return $this->query($r) - $this->query($l - 1);
    }
}

// İstifadə:
$ft = FenwickTree::fromArray([1, 7, 2, 0, 9, 5, 3, 8]);
echo $ft->rangeQuery(1, 5); // 1+7+2+0+9 = 19
$ft->update(3, 3);           // arr[3] += 3 → arr[3] = 5
echo $ft->rangeQuery(1, 5); // 1+7+5+0+9 = 22
```

### 2D Fenwick Tree

```php
class FenwickTree2D
{
    private array $bit;
    private int $rows;
    private int $cols;

    public function __construct(int $rows, int $cols)
    {
        $this->rows = $rows;
        $this->cols = $cols;
        $this->bit = array_fill(0, $rows + 1, array_fill(0, $cols + 1, 0));
    }

    public function update(int $row, int $col, int $delta): void
    {
        for ($i = $row; $i <= $this->rows; $i += $i & (-$i)) {
            for ($j = $col; $j <= $this->cols; $j += $j & (-$j)) {
                $this->bit[$i][$j] += $delta;
            }
        }
    }

    public function query(int $row, int $col): int
    {
        $sum = 0;
        for ($i = $row; $i > 0; $i -= $i & (-$i)) {
            for ($j = $col; $j > 0; $j -= $j & (-$j)) {
                $sum += $this->bit[$i][$j];
            }
        }
        return $sum;
    }

    // (r1,c1) → (r2,c2) region sum: O(log(rows) * log(cols))
    public function regionQuery(int $r1, int $c1, int $r2, int $c2): int
    {
        return $this->query($r2, $c2)
             - $this->query($r1 - 1, $c2)
             - $this->query($r2, $c1 - 1)
             + $this->query($r1 - 1, $c1 - 1);
    }
}
```

### Order Statistics (daha kiçik ədədlərin sayı)

```php
// Massivdə hər element üçün solundan neçə kiçik ədəd olduğunu tap
function countSmaller(array $nums): array
{
    $sorted = $nums;
    sort($sorted);
    $rank = array_flip(array_unique($sorted)); // dəyər → rank (1-indexed)

    // Rankları 1-dən başlayaq
    $i = 1;
    foreach (array_unique($sorted) as $v) {
        $rank[$v] = $i++;
    }

    $maxRank = count($rank);
    $ft = new FenwickTree($maxRank);
    $result = [];

    // Sağdan sola gezirik
    for ($i = count($nums) - 1; $i >= 0; $i--) {
        $r = $rank[$nums[$i]];
        $result[] = $ft->query($r - 1); // r-dən kiçik olan ədədlər
        $ft->update($r, 1);
    }

    return array_reverse($result);
}
// nums = [5, 2, 6, 1] → [2, 1, 1, 0]
// Mənası: 5-dən əvvəl 2 kiçik (2,1); 2-dən əvvəl 1 kiçik (1)...
```

### O(n) Build Optimization

```php
// Standart fromArray O(n log n) — amma O(n)-də qurmaq mümkündür
public static function buildO_n(array $nums): self
{
    $n = count($nums);
    $ft = new self($n);
    // Birbaşa BIT-ə kopyala (1-indexed)
    for ($i = 1; $i <= $n; $i++) {
        $ft->bit[$i] += $nums[$i - 1];
        $j = $i + ($i & (-$i)); // parent
        if ($j <= $n) {
            $ft->bit[$j] += $ft->bit[$i];
        }
    }
    return $ft;
}
```

---

## Nümunələr

### Range Sum Query - Mutable (LeetCode 307)

```php
class NumArray
{
    private FenwickTree $ft;
    private array $nums;

    public function __construct(array $nums)
    {
        $this->nums = $nums;
        $this->ft = FenwickTree::fromArray($nums);
    }

    // O(log n)
    public function update(int $index, int $val): void
    {
        $this->ft->update($index + 1, $val - $this->nums[$index]);
        $this->nums[$index] = $val;
    }

    // O(log n)
    public function sumRange(int $left, int $right): int
    {
        return $this->ft->rangeQuery($left + 1, $right + 1);
    }
}
```

### Reverse Pairs (LeetCode 493)

```php
// Reversed pairs: i < j && nums[i] > 2 * nums[j]
function reversePairs(array $nums): int
{
    $sorted = $nums;
    sort($sorted);
    $sorted = array_unique($sorted);
    $rank = [];
    foreach (array_values($sorted) as $i => $v) {
        $rank[$v] = $i + 1;
    }

    $n = count($nums);
    $m = count($rank);
    $ft = new FenwickTree($m);
    $count = 0;

    for ($i = $n - 1; $i >= 0; $i--) {
        // Nums[j] < nums[i] / 2 olan j-ləri tap
        $target = (int)floor(($nums[$i] - 1) / 2);
        // Binary search: sorted-da target-dən <= olanları tap
        $lo = 0;
        $hi = count($sorted) - 1;
        $pos = 0;
        while ($lo <= $hi) {
            $mid = (int)(($lo + $hi) / 2);
            if ($sorted[$mid] <= $target) {
                $pos = $mid + 1;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }
        $count += $ft->query($pos);
        $ft->update($rank[$nums[$i]], 1);
    }

    return $count;
}
```

---

## Vaxt və Yaddaş Mürəkkəbliyi

| Əməliyyat | Fenwick Tree | Prefix Sum | Segment Tree |
|-----------|-------------|------------|--------------|
| Build | O(n log n) | O(n) | O(n) |
| Point Update | O(log n) | O(n) | O(log n) |
| Prefix Query | O(log n) | O(1) | O(log n) |
| Range Query | O(log n) | O(1) | O(log n) |
| Space | O(n) | O(n) | O(4n) |

**2D Fenwick Tree:**
- Build: O(n·m·log(n)·log(m))
- Update/Query: O(log(n) × log(m))

---

## Praktik Tapşırıqlar

1. **LeetCode 307** — Range Sum Query - Mutable (klassik BIT problemi)
2. **LeetCode 315** — Count of Smaller Numbers After Self
3. **LeetCode 493** — Reverse Pairs
4. **LeetCode 1649** — Create Sorted Array through Instructions
5. **LeetCode 2179** — Count Good Triplets in an Array (2D BIT ilə)

### Step-by-step: Point Update + Range Query

```
Məsələ: [3, 2, -1, 6, 5, 4, -3, 3, 7, 2, 3]
Query:  sum(2..6) = 2+(-1)+6+5+4 = 16
Update: arr[4] = 10 (əvvəl 6 idi, delta = +4)
Query:  sum(2..6) = 2+(-1)+10+5+4 = 20

1. Build: FenwickTree::fromArray(...)
2. rangeQuery(2, 6) → query(6) - query(1) = ?
3. update(4, +4) → BIT[4], BIT[8], ... yenilənir
4. rangeQuery(2, 6) yenidən
```

---

## Interview Sualları

**1. Fenwick Tree niyə Segment Tree-dən üstündür?**
Kod sadəliyi, yaddaş (O(n) vs O(4n)), cache-friendliness. Lakin yalnız prefix aggregation dəstəkləyir (sum, xor); min/max, lazy propagation yoxdur.

**2. `lowbit(i) = i & (-i)` niyə doğru işləyir?**
Two's complement-də `-i = ~i + 1`. Beləliklə `i & (-i)` ən aşağı set bitini təcrid edir — hər node-un əhatə etdiyi aralığın uzunluğudur.

**3. Fenwick Tree-nin range update + point query versiyası varmı?**
Bəli — difference array texnikası ilə: `update(l, +delta)`, `update(r+1, -delta)` edib point query ilə prefix sum alırıq.

**4. Fenwick Tree min/max dəstəkləyirmi?**
Standart variant xeyr (çünki silinmə/update asan deyil). Yalnız toplama (sum), XOR, GCD — assosiativ, kommutativ, tersinə çevrilə bilən operasiyalar.

**5. 2D Fenwick Tree-nin time complexity-si?**
O(log(n) × log(m)) hər update/query üçün. 3D üçün O(log³n) olur.

---

## PHP/Laravel ilə Əlaqə

```php
// 1. Analytics dashboard — real-time sayğac yeniləmələri
class DashboardAnalytics
{
    private FenwickTree $ft;
    private const DAYS = 365;

    public function __construct()
    {
        $this->ft = new FenwickTree(self::DAYS);
    }

    public function addSale(int $dayOfYear, int $amount): void
    {
        $this->ft->update($dayOfYear, $amount);
    }

    public function totalSales(int $fromDay, int $toDay): int
    {
        return $this->ft->rangeQuery($fromDay, $toDay);
    }
}

// 2. Leaderboard — rank hesablaması
// Oyunçunun cari score-u dəyişəndə, onun global rank-ını O(log n)-de tap
// 3. Inventory tracking — stok dəyişmələri, range query
// 4. Event counting — A/B test: X-dən Y-ə qədər ərizə sayı
```

---

## Əlaqəli Mövzular

- [10-prefix-sum.md](10-prefix-sum.md) — Prefix Sum əsasları (statik array üçün)
- [39-segment-tree-advanced.md](39-segment-tree-advanced.md) — Daha güclü alternativ (range update/query)
- [44-sparse-table.md](44-sparse-table.md) — Statik RMQ üçün O(1) query
- [02-arrays.md](02-arrays.md) — Array əsasları
