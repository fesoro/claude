# Sparse Table (Lead)

## İcmal

**Sparse Table** — statik massivdə Range Minimum Query (RMQ) və ya idempotent operasiyaları (min, max, GCD) **O(1)** zamanda icra edən data strukturudur. Build O(n log n), amma hər query O(1).

**Key məhdudiyyət**: Array dəyişməməlidir (static). Update gəldikdə Fenwick Tree və ya Segment Tree daha uyğundur.

```
Array:  [2, 4, 3, 1, 6, 7, 8, 9, 1, 7]
Index:   0  1  2  3  4  5  6  7  8  9

RMQ(2, 7) = min(3, 1, 6, 7, 8, 9) = 1   → O(1)
RMQ(0, 4) = min(2, 4, 3, 1, 6) = 1      → O(1)
```

---

## Niyə Vacibdir

- **Static RMQ**: Sorğular çox, update yoxdur — ən sürətli seçim
- **LCA (Lowest Common Ancestor)**: Ağaclarda LCA-ya endirilir (Euler tour + RMQ)
- **Sliding Window Maximum**: Deque alternativi
- **Competitive programming**: Range min/max/gcd problemlərinin standart həlli

| Yanaşma | Build | Query | Update |
|---------|-------|-------|--------|
| Naive | O(1) | O(n) | O(1) |
| Prefix Min | O(n) | O(n) | O(n) |
| Segment Tree | O(n) | O(log n) | O(log n) |
| Sparse Table | O(n log n) | **O(1)** | ❌ |

---

## Əsas Anlayışlar

### Fikir
`st[i][j]` = `arr[i..i + 2^j - 1]` aralığının minimumu.

Uzunluğu `2^j` olan intervalları saxlayırıq. Hər query `[l, r]` üçün iki overlapping interval tapırıq ki, onların birləşməsi `[l, r]`-i tam örtür:
```
k = floor(log2(r - l + 1))

RMQ(l, r) = min(st[l][k], st[r - 2^k + 1][k])
```

İki interval overlap edə bilər — bu problem deyil, çünki min idempotentdir: `min(a, a) = a`.

### Vizual
```
Arr:  [2, 4, 3, 1, 6, 7, 8, 9]
       0  1  2  3  4  5  6  7

j=0 (len=1):  [2] [4] [3] [1] [6] [7] [8] [9]
j=1 (len=2):  [2,4]→2 [3,4]→3 [1,3]→1 [1,6]→1 [6,7]→6 [7,8]→7 [8,9]→8
j=2 (len=4):  [2,4,3,1]→1 [3,1,6,7]→1 [1,6,7,8]→1 [6,7,8,9]→6
j=3 (len=8):  [2,4,3,1,6,7,8,9]→1

RMQ(2,6): len=5, k=floor(log2(5))=2, 2^2=4
  min(st[2][2], st[6-4+1][2]) = min(st[2][2], st[3][2])
  = min(min(3,1,6,7), min(1,6,7,8)) = min(1, 1) = 1 ✓
```

---

## Praktik Baxış

### PHP İmplementasiyası

```php
class SparseTable
{
    private array $st;    // st[i][j] = min of arr[i..i+2^j-1]
    private array $log2;  // precomputed log2 values
    private int $n;

    public function __construct(array $arr)
    {
        $this->n = count($arr);
        $this->buildLog();
        $this->build($arr);
    }

    private function buildLog(): void
    {
        $this->log2 = array_fill(0, $this->n + 1, 0);
        for ($i = 2; $i <= $this->n; $i++) {
            $this->log2[$i] = $this->log2[(int)($i / 2)] + 1;
        }
    }

    private function build(array $arr): void
    {
        $k = $this->log2[$this->n] + 1;
        // st[i][j] = min of arr[i .. i+2^j-1]
        $this->st = array_fill(0, $this->n, array_fill(0, $k, PHP_INT_MAX));

        // Base case: j=0, hər element özüdür
        for ($i = 0; $i < $this->n; $i++) {
            $this->st[$i][0] = $arr[$i];
        }

        // Fill: j = 1, 2, 3, ...
        for ($j = 1; (1 << $j) <= $this->n; $j++) {
            for ($i = 0; $i + (1 << $j) - 1 < $this->n; $i++) {
                $this->st[$i][$j] = min(
                    $this->st[$i][$j - 1],
                    $this->st[$i + (1 << ($j - 1))][$j - 1]
                );
            }
        }
    }

    // Range Minimum Query [l, r] (inclusive) → O(1)
    public function queryMin(int $l, int $r): int
    {
        $k = $this->log2[$r - $l + 1];
        return min(
            $this->st[$l][$k],
            $this->st[$r - (1 << $k) + 1][$k]
        );
    }

    // Range Maximum Query (max idempotentdir, eyni üsul işləyir)
    public function queryMax(int $l, int $r): int
    {
        // Max üçün ayrıca sparse table lazımdır (yuxarıdakı min üçün)
        // Burada sadə nümunə — produksiyada ayrıca class
        $k = $this->log2[$r - $l + 1];
        return max(
            $this->st[$l][$k],
            $this->st[$r - (1 << $k) + 1][$k]
        );
    }
}

// İstifadə:
$st = new SparseTable([2, 4, 3, 1, 6, 7, 8, 9, 1, 7]);
echo $st->queryMin(2, 7);  // 1
echo $st->queryMin(0, 4);  // 1
echo $st->queryMin(4, 9);  // 1
```

### GCD ilə Sparse Table

```php
// GCD də idempotentdir: gcd(a, a) = a
class SparseTableGCD
{
    private array $st;
    private array $log2;
    private int $n;

    public function __construct(array $arr)
    {
        $this->n = count($arr);
        $this->log2 = [0, 0];
        for ($i = 2; $i <= $this->n; $i++) {
            $this->log2[$i] = $this->log2[(int)($i / 2)] + 1;
        }

        $k = ($this->log2[$this->n] ?? 0) + 1;
        $this->st = [];
        for ($i = 0; $i < $this->n; $i++) {
            $this->st[$i][0] = $arr[$i];
        }
        for ($j = 1; (1 << $j) <= $this->n; $j++) {
            for ($i = 0; $i + (1 << $j) <= $this->n; $i++) {
                $this->st[$i][$j] = $this->gcd(
                    $this->st[$i][$j - 1],
                    $this->st[$i + (1 << ($j - 1))][$j - 1]
                );
            }
        }
    }

    public function query(int $l, int $r): int
    {
        $k = $this->log2[$r - $l + 1];
        return $this->gcd(
            $this->st[$l][$k],
            $this->st[$r - (1 << $k) + 1][$k]
        );
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }
}
```

### Non-idempotent Operasiyalar üçün (Sum): O(log n) query

```php
// Sum idempotent deyil (sum(a,a) = 2a ≠ a), overlap olmaz
// O(log n) query — amma sum üçün Fenwick Tree daha yaxşıdır
class SparseTableSum
{
    private array $st;
    private int $n;

    public function __construct(array $arr)
    {
        $this->n = count($arr);
        $k = max(1, (int)log($this->n, 2) + 1);
        $this->st = array_fill(0, $this->n, array_fill(0, $k + 1, 0));

        for ($i = 0; $i < $this->n; $i++) {
            $this->st[$i][0] = $arr[$i];
        }
        for ($j = 1; (1 << $j) <= $this->n; $j++) {
            for ($i = 0; $i + (1 << $j) - 1 < $this->n; $i++) {
                $this->st[$i][$j] = $this->st[$i][$j-1]
                    + $this->st[$i + (1 << ($j-1))][$j-1];
            }
        }
    }

    // O(log n) — decompose into powers of 2
    public function query(int $l, int $r): int
    {
        $sum = 0;
        for ($j = (int)log($r - $l + 1, 2); $j >= 0; $j--) {
            if ((1 << $j) <= $r - $l + 1) {
                $sum += $this->st[$l][$j];
                $l += (1 << $j);
            }
        }
        return $sum;
    }
}
```

---

## Nümunələr

### Sliding Window Minimum (Deque alternativi)

```php
// Sliding window minimum — Sparse Table ilə O(n) (n queries × O(1) each)
function slidingWindowMin(array $nums, int $k): array
{
    $st = new SparseTable($nums);
    $result = [];
    for ($i = 0; $i <= count($nums) - $k; $i++) {
        $result[] = $st->queryMin($i, $i + $k - 1);
    }
    return $result;
}
// [1,3,-1,-3,5,3,6,7], k=3 → [-1,-3,-3,-3,3,3]
```

### LCA (Lowest Common Ancestor) ilə Euler Tour

```php
// Ağacda LCA = RMQ on Euler tour
// Euler tour: DFS zamanı hər node-u enter+exit zamanı qeyd et
// LCA(u,v) = euler_tour[i..j]-nın minimumu (depth-a görə)

function buildEulerTour(array $tree, int $root): array
{
    $euler = [];
    $depth = [];
    $first = [];

    $dfs = function(int $node, int $d, ?int $parent) use (&$dfs, &$tree, &$euler, &$depth, &$first) {
        $first[$node] = count($euler);
        $euler[] = $node;
        $depth[$node] = $d;

        foreach ($tree[$node] as $child) {
            if ($child !== $parent) {
                $dfs($child, $d + 1, $node);
                $euler[] = $node;
            }
        }
    };

    $dfs($root, 0, null);
    return [$euler, $depth, $first];
}

function lca(int $u, int $v, array $euler, array $depth, array $first, SparseTable $st): int
{
    $l = min($first[$u], $first[$v]);
    $r = max($first[$u], $first[$v]);
    // Depth ən az olan node = LCA
    $minDepthIdx = $st->queryMin($l, $r); // Depth-indexed sparse table lazımdır
    return $euler[$minDepthIdx];
}
```

---

## Vaxt və Yaddaş Mürəkkəbliyi

| Əməliyyat | Sparse Table | Segment Tree | Fenwick Tree |
|-----------|-------------|--------------|--------------|
| Build | O(n log n) | O(n) | O(n log n) |
| RMQ / Range Min/Max | **O(1)** | O(log n) | ❌ |
| Range Sum | O(log n) | O(log n) | O(log n) |
| Point Update | ❌ | O(log n) | O(log n) |
| Space | O(n log n) | O(4n) | O(n) |

**Nə vaxt Sparse Table seç:**
- Array statikdir (dəyişmir)
- RMQ sorğuları çoxdur
- Minimum latency tələb olunur

---

## Praktik Tapşırıqlar

1. **LeetCode 239** — Sliding Window Maximum (Sparse Table ilə O(n) həll)
2. **LeetCode 1793** — Maximum Score of a Good Subarray
3. **SPOJ RMQ** — Range Minimum Query (klassik problem)
4. **LeetCode 2334** — Subarray With Elements Greater Than Varying Threshold
5. **LCA problems** — Ağaclarda Euler Tour + Sparse Table

### Step-by-step: RMQ Build

```
arr = [5, 2, 8, 1, 4, 9, 3]  (n=7)

j=0 (2^0=1, hər element):
  st[0][0]=5, st[1][0]=2, ..., st[6][0]=3

j=1 (2^1=2 element interval):
  st[0][1]=min(5,2)=2, st[1][1]=min(2,8)=2, st[2][1]=min(8,1)=1
  st[3][1]=min(1,4)=1, st[4][1]=min(4,9)=4, st[5][1]=min(9,3)=3

j=2 (2^2=4 element):
  st[0][2]=min(st[0][1],st[2][1])=min(2,1)=1
  st[1][2]=min(st[1][1],st[3][1])=min(2,1)=1
  st[2][2]=min(st[2][1],st[4][1])=min(1,4)=1
  st[3][2]=min(st[3][1],st[5][1])=min(1,3)=1

Query RMQ(1,5): len=5, k=floor(log2(5))=2
  min(st[1][2], st[5-4+1][2]) = min(st[1][2], st[2][2]) = min(1,1) = 1 ✓
```

---

## Interview Sualları

**1. Niyə Sparse Table yalnız idempotent operasiyalar üçün O(1) query verir?**
Overlap edən iki interval istifadə edirik. `min(a, a) = a` — overlap zərərsizdir. `sum(a, a) = 2a` — ikiqat sayılır, yanlış nəticə verir.

**2. GCD, XOR üçün Sparse Table işləyirmi?**
- GCD: bəli (gcd(a,a) = a, idempotent)
- XOR: xeyr (xor(a,a) = 0, idempotent deyil)
- OR, AND: bəli (OR(a,a) = a, idempotent)

**3. Sparse Table-in yaddaşı nə qədərdir?**
O(n log n) — n=10^5 üçün ~1.7 milyon element ≈ ~13 MB int32 üçün.

**4. Sparse Table niyə Segment Tree-dən sürətlidir query üçün?**
Segment Tree-də O(log n) node ziyarəti var, cache misslər mümkündür. Sparse Table-də iki array access — hardware prefetcher üçün idealdır.

**5. Build niyə O(n log n)?**
`log n` səviyyə, hər səviyyədə `n` element. Cəmi: n × log n.

---

## PHP/Laravel ilə Əlaqə

```php
// 1. Time-series analytics (statik data üçün)
// Log faylından yüklənmiş saatlik metrik — min latency tap
$latencies = loadHourlyLatencies(); // statik array
$st = new SparseTable($latencies);

// Dashboard: istənilən period üçün min latency O(1)
$minLatency = $st->queryMin($fromHour, $toHour);

// 2. Config-based range lookups
// Pricing tiers, threshold əsaslı hesablamalar

// 3. Batch report generation
// Hər report eyni data üzərindədir, sadəcə fərqli range
// N range query × O(1) = O(N) cəmi — çox sürətli

// 4. Read-heavy microservice
// Katalog/inventory — update nadir, read tez-tez
// Cache invalidate edəndə yenidən qur: O(n log n) once
```

---

## Əlaqəli Mövzular

- [10-prefix-sum.md](10-prefix-sum.md) — Statik prefix sum (O(1) range sum, amma min/max yox)
- [43-fenwick-tree.md](43-fenwick-tree.md) — Dinamik range sum (update dəstəkləyir)
- [39-segment-tree-advanced.md](39-segment-tree-advanced.md) — Ən güclü range structure (update + lazy)
- [16-trees-basics.md](16-trees-basics.md) — LCA üçün Sparse Table-in ağac tərəfi
