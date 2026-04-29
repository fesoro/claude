# Segment Tree - Advanced (Lead)

## Konsept (Concept)

**Segment Tree** — range query və range update-ləri O(log n) zamanda icra edən ağac strukturudur. Bu fayl **qabaqcıl** variantları əhatə edir:

- **Lazy Propagation**: Range update-ləri də O(log n)-də etmək üçün
- **Persistent Segment Tree**: Keçmiş versiyaları saxlamaq
- **2D Segment Tree**: Matris üzərində range query
- **Segment Tree with Merge**: Kiçik ağacları birləşdirmək (HLD, DSU on Tree)

### Niyə Lazy Propagation?
Adi segment tree-də range update-ləri O(n log n) alır (hər element ayrı-ayrı yenilənməlidir). Lazy propagation ilə update-i **sonraya təxirə salırıq** və lazım gəldikdə aşağı yayırıq — O(log n).

### Niyə Persistent?
Hər update-dən sonra köhnə versiya da əlçatan olmalıdır. Məsələn: "k-cı update-dən əvvəl range sum nə idi?"

## Necə İşləyir?

### Lazy Propagation Ideyası
Hər node üçün **`lazy[node]`** saxlayırıq — "bu node-un övladlarına tətbiq edilməsi gözlənilən update".

```
Range update(l, r, val):
  1. Əgər cari segment tam daxildədirsə:
       tree[node] += (segment length) * val
       lazy[node] += val   // push later
       return
  2. Push down pending lazy-lərini övladlara
  3. Rekursiya et sol və sağ övladla
  4. tree[node] = tree[left] + tree[right]
```

### Push Down
```
push_down(node):
  if lazy[node] != 0:
    apply lazy to left child
    apply lazy to right child
    lazy[node] = 0
```

### Persistent
Hər update **yeni node-lar yaradır** yalnız dəyişən yol boyunca. Köhnə root saxlanılır. Space: O((n + q) log n).

## İmplementasiya (Implementation) - PHP

### 1. Lazy Propagation (Range Sum + Range Add)

```php
class LazySegmentTree {
    private array $tree;
    private array $lazy;
    private int $n;

    public function __construct(array $arr) {
        $this->n = count($arr);
        $this->tree = array_fill(0, 4 * $this->n, 0);
        $this->lazy = array_fill(0, 4 * $this->n, 0);
        $this->build($arr, 1, 0, $this->n - 1);
    }

    private function build(array $arr, int $node, int $start, int $end): void {
        if ($start === $end) {
            $this->tree[$node] = $arr[$start];
            return;
        }
        $mid = intdiv($start + $end, 2);
        $this->build($arr, 2 * $node, $start, $mid);
        $this->build($arr, 2 * $node + 1, $mid + 1, $end);
        $this->tree[$node] = $this->tree[2 * $node] + $this->tree[2 * $node + 1];
    }

    private function pushDown(int $node, int $start, int $end): void {
        if ($this->lazy[$node] !== 0) {
            $mid = intdiv($start + $end, 2);
            $left = 2 * $node;
            $right = 2 * $node + 1;
            $this->tree[$left] += ($mid - $start + 1) * $this->lazy[$node];
            $this->lazy[$left] += $this->lazy[$node];
            $this->tree[$right] += ($end - $mid) * $this->lazy[$node];
            $this->lazy[$right] += $this->lazy[$node];
            $this->lazy[$node] = 0;
        }
    }

    public function updateRange(int $l, int $r, int $val): void {
        $this->update(1, 0, $this->n - 1, $l, $r, $val);
    }

    private function update(int $node, int $start, int $end, int $l, int $r, int $val): void {
        if ($r < $start || $end < $l) return;
        if ($l <= $start && $end <= $r) {
            $this->tree[$node] += ($end - $start + 1) * $val;
            $this->lazy[$node] += $val;
            return;
        }
        $this->pushDown($node, $start, $end);
        $mid = intdiv($start + $end, 2);
        $this->update(2 * $node, $start, $mid, $l, $r, $val);
        $this->update(2 * $node + 1, $mid + 1, $end, $l, $r, $val);
        $this->tree[$node] = $this->tree[2 * $node] + $this->tree[2 * $node + 1];
    }

    public function queryRange(int $l, int $r): int {
        return $this->query(1, 0, $this->n - 1, $l, $r);
    }

    private function query(int $node, int $start, int $end, int $l, int $r): int {
        if ($r < $start || $end < $l) return 0;
        if ($l <= $start && $end <= $r) return $this->tree[$node];
        $this->pushDown($node, $start, $end);
        $mid = intdiv($start + $end, 2);
        return $this->query(2 * $node, $start, $mid, $l, $r)
             + $this->query(2 * $node + 1, $mid + 1, $end, $l, $r);
    }
}

// İstifadə
$st = new LazySegmentTree([1, 2, 3, 4, 5]);
$st->updateRange(1, 3, 10);       // [1, 12, 13, 14, 5]
echo $st->queryRange(0, 4) . "\n"; // 45
```

### 2. Range Assign + Range Min

```php
class LazyRangeMinAssign {
    private array $tree;
    private array $lazy;
    private array $hasLazy;
    private int $n;

    public function __construct(int $n) {
        $this->n = $n;
        $this->tree = array_fill(0, 4 * $n, 0);
        $this->lazy = array_fill(0, 4 * $n, 0);
        $this->hasLazy = array_fill(0, 4 * $n, false);
    }

    private function pushDown(int $node): void {
        if ($this->hasLazy[$node]) {
            foreach ([2 * $node, 2 * $node + 1] as $child) {
                $this->tree[$child] = $this->lazy[$node];
                $this->lazy[$child] = $this->lazy[$node];
                $this->hasLazy[$child] = true;
            }
            $this->hasLazy[$node] = false;
        }
    }

    public function assign(int $l, int $r, int $val, int $node = 1, int $s = 0, int $e = -1): void {
        if ($e === -1) $e = $this->n - 1;
        if ($r < $s || $e < $l) return;
        if ($l <= $s && $e <= $r) {
            $this->tree[$node] = $val;
            $this->lazy[$node] = $val;
            $this->hasLazy[$node] = true;
            return;
        }
        $this->pushDown($node);
        $m = intdiv($s + $e, 2);
        $this->assign($l, $r, $val, 2 * $node, $s, $m);
        $this->assign($l, $r, $val, 2 * $node + 1, $m + 1, $e);
        $this->tree[$node] = min($this->tree[2 * $node], $this->tree[2 * $node + 1]);
    }

    public function queryMin(int $l, int $r, int $node = 1, int $s = 0, int $e = -1): int {
        if ($e === -1) $e = $this->n - 1;
        if ($r < $s || $e < $l) return PHP_INT_MAX;
        if ($l <= $s && $e <= $r) return $this->tree[$node];
        $this->pushDown($node);
        $m = intdiv($s + $e, 2);
        return min(
            $this->queryMin($l, $r, 2 * $node, $s, $m),
            $this->queryMin($l, $r, 2 * $node + 1, $m + 1, $e)
        );
    }
}
```

### 3. Persistent Segment Tree (Simplified)

```php
class PersistentNode {
    public function __construct(
        public int $sum = 0,
        public ?PersistentNode $left = null,
        public ?PersistentNode $right = null
    ) {}
}

class PersistentSegmentTree {
    private int $n;
    public array $versions = [];

    public function __construct(int $n) {
        $this->n = $n;
        $this->versions[] = $this->build(0, $n - 1);
    }

    private function build(int $s, int $e): PersistentNode {
        if ($s === $e) return new PersistentNode();
        $m = intdiv($s + $e, 2);
        return new PersistentNode(0, $this->build($s, $m), $this->build($m + 1, $e));
    }

    public function update(int $version, int $idx, int $val): int {
        $root = $this->versions[$version];
        $newRoot = $this->updateHelper($root, 0, $this->n - 1, $idx, $val);
        $this->versions[] = $newRoot;
        return count($this->versions) - 1;
    }

    private function updateHelper(PersistentNode $node, int $s, int $e, int $idx, int $val): PersistentNode {
        if ($s === $e) return new PersistentNode($node->sum + $val);
        $m = intdiv($s + $e, 2);
        if ($idx <= $m) {
            $newLeft = $this->updateHelper($node->left, $s, $m, $idx, $val);
            $newNode = new PersistentNode($newLeft->sum + $node->right->sum, $newLeft, $node->right);
        } else {
            $newRight = $this->updateHelper($node->right, $m + 1, $e, $idx, $val);
            $newNode = new PersistentNode($node->left->sum + $newRight->sum, $node->left, $newRight);
        }
        return $newNode;
    }

    public function query(int $version, int $l, int $r): int {
        return $this->queryHelper($this->versions[$version], 0, $this->n - 1, $l, $r);
    }

    private function queryHelper(PersistentNode $node, int $s, int $e, int $l, int $r): int {
        if ($r < $s || $e < $l) return 0;
        if ($l <= $s && $e <= $r) return $node->sum;
        $m = intdiv($s + $e, 2);
        return $this->queryHelper($node->left, $s, $m, $l, $r)
             + $this->queryHelper($node->right, $m + 1, $e, $l, $r);
    }
}
```

### 4. 2D Segment Tree (Konseptual)

```php
// 2D Segment Tree — hər "x" node öz "y" segment tree-yə sahibdir
class SegmentTree2D {
    private array $tree; // tree[xNode][yNode]
    private int $rows, $cols;

    public function __construct(array $matrix) {
        $this->rows = count($matrix);
        $this->cols = count($matrix[0]);
        $this->tree = [];
        $this->buildX(1, 0, $this->rows - 1, $matrix);
    }

    private function buildX(int $vx, int $lx, int $rx, array $matrix): void {
        if ($lx === $rx) {
            $this->tree[$vx] = array_fill(0, 4 * $this->cols, 0);
            $this->buildY($vx, 1, 0, $this->cols - 1, $matrix[$lx]);
            return;
        }
        $mx = intdiv($lx + $rx, 2);
        $this->buildX(2 * $vx, $lx, $mx, $matrix);
        $this->buildX(2 * $vx + 1, $mx + 1, $rx, $matrix);
        $this->tree[$vx] = array_fill(0, 4 * $this->cols, 0);
        $this->mergeBuildY($vx, 1, 0, $this->cols - 1);
    }

    private function buildY(int $vx, int $vy, int $ly, int $ry, array $row): void {
        if ($ly === $ry) { $this->tree[$vx][$vy] = $row[$ly]; return; }
        $my = intdiv($ly + $ry, 2);
        $this->buildY($vx, 2 * $vy, $ly, $my, $row);
        $this->buildY($vx, 2 * $vy + 1, $my + 1, $ry, $row);
        $this->tree[$vx][$vy] = $this->tree[$vx][2 * $vy] + $this->tree[$vx][2 * $vy + 1];
    }

    private function mergeBuildY(int $vx, int $vy, int $ly, int $ry): void {
        if ($ly === $ry) {
            $this->tree[$vx][$vy] = $this->tree[2 * $vx][$vy] + $this->tree[2 * $vx + 1][$vy];
            return;
        }
        $my = intdiv($ly + $ry, 2);
        $this->mergeBuildY($vx, 2 * $vy, $ly, $my);
        $this->mergeBuildY($vx, 2 * $vy + 1, $my + 1, $ry);
        $this->tree[$vx][$vy] = $this->tree[$vx][2 * $vy] + $this->tree[$vx][2 * $vy + 1];
    }

    public function query(int $x1, int $y1, int $x2, int $y2): int {
        return $this->queryX(1, 0, $this->rows - 1, $x1, $x2, $y1, $y2);
    }

    private function queryX(int $vx, int $lx, int $rx, int $x1, int $x2, int $y1, int $y2): int {
        if ($x2 < $lx || $rx < $x1) return 0;
        if ($x1 <= $lx && $rx <= $x2) return $this->queryY($vx, 1, 0, $this->cols - 1, $y1, $y2);
        $mx = intdiv($lx + $rx, 2);
        return $this->queryX(2 * $vx, $lx, $mx, $x1, $x2, $y1, $y2)
             + $this->queryX(2 * $vx + 1, $mx + 1, $rx, $x1, $x2, $y1, $y2);
    }

    private function queryY(int $vx, int $vy, int $ly, int $ry, int $y1, int $y2): int {
        if ($y2 < $ly || $ry < $y1) return 0;
        if ($y1 <= $ly && $ry <= $y2) return $this->tree[$vx][$vy];
        $my = intdiv($ly + $ry, 2);
        return $this->queryY($vx, 2 * $vy, $ly, $my, $y1, $y2)
             + $this->queryY($vx, 2 * $vy + 1, $my + 1, $ry, $y1, $y2);
    }
}
```

## Vaxt və Yaddaş Mürəkkəbliyi

| Variant | Build | Update | Query | Space |
|---------|-------|--------|-------|-------|
| Lazy SegTree | O(n) | O(log n) range | O(log n) | O(n) |
| Persistent | O(n) | O(log n) per version | O(log n) | O((n+q) log n) |
| 2D SegTree | O(n·m) | O(log n · log m) | O(log n · log m) | O(n·m) |
| SegTree w/ Merge | O(n log n) | O(log² n) | O(log n) | O(n log n) |

## Tipik Məsələlər (Common Problems)

### 1. Range Update + Range Sum (Lazy)
Sinif `LazySegmentTree` yuxarıda. "Add val to [l, r], then query sum [x, y]".

### 2. Range Max with Range Add
```php
class RangeMaxAdd {
    private array $tree, $lazy;
    private int $n;

    public function __construct(int $n) {
        $this->n = $n;
        $this->tree = array_fill(0, 4 * $n, 0);
        $this->lazy = array_fill(0, 4 * $n, 0);
    }

    private function push(int $node): void {
        foreach ([2 * $node, 2 * $node + 1] as $ch) {
            $this->tree[$ch] += $this->lazy[$node];
            $this->lazy[$ch] += $this->lazy[$node];
        }
        $this->lazy[$node] = 0;
    }

    public function update(int $l, int $r, int $val, int $node = 1, int $s = 0, int $e = -1): void {
        if ($e === -1) $e = $this->n - 1;
        if ($r < $s || $e < $l) return;
        if ($l <= $s && $e <= $r) { $this->tree[$node] += $val; $this->lazy[$node] += $val; return; }
        $this->push($node);
        $m = intdiv($s + $e, 2);
        $this->update($l, $r, $val, 2 * $node, $s, $m);
        $this->update($l, $r, $val, 2 * $node + 1, $m + 1, $e);
        $this->tree[$node] = max($this->tree[2 * $node], $this->tree[2 * $node + 1]);
    }

    public function queryMax(int $l, int $r, int $node = 1, int $s = 0, int $e = -1): int {
        if ($e === -1) $e = $this->n - 1;
        if ($r < $s || $e < $l) return PHP_INT_MIN;
        if ($l <= $s && $e <= $r) return $this->tree[$node];
        $this->push($node);
        $m = intdiv($s + $e, 2);
        return max(
            $this->queryMax($l, $r, 2 * $node, $s, $m),
            $this->queryMax($l, $r, 2 * $node + 1, $m + 1, $e)
        );
    }
}
```

### 3. K-th Smallest in Range (Persistent)
`version[r] - version[l-1]` fərqi ilə sayıq. Tətbiq: offline array query üçün.

### 4. Painting Segments (Range Assign)
Yuxarıdakı `LazyRangeMinAssign` variant.

### 5. 2D Sum Query
`SegmentTree2D` — matrisin hər alt-düzbucaqlı cəmini O(log²n) zamanda verir.

## Interview Sualları

**1. Lazy Propagation niyə lazımdır?**
Range update-ləri tam segment tree-də O(n)-dir — hər element yenilənməlidir. Lazy update-i təxirə salır və yalnız lazım gələndə aşağı yayır — O(log n).

**2. Lazy array-i nə saxlayır?**
Bu node-un **bütün alt-elementlərinə** tətbiq edilməli, amma hələ tətbiq edilməmiş update-i. Push down zamanı övladlara köçürülür.

**3. Range add vs Range assign arasında fərq nədir?**
- **Add**: lazy-lər toplanır (`lazy[ch] += lazy[parent]`)
- **Assign**: lazy üstündən yazılır (`lazy[ch] = lazy[parent]`), `hasLazy` flag-i lazımdır ki, "assign 0" ilə "heç bir lazy yoxdur" arasında fərq qoyula bilsin.

**4. Persistent Segment Tree hansı tətbiqlərdə lazımdır?**
- "Version history" saxlamaq (undo/redo sistemləri)
- Offline range query (k-th smallest in range)
- Geospatial index-lərin anlıq snapshot-ları

**5. 2D Segment Tree nə qədər yaddaş tutur?**
O(n × m × 4 × 4) = O(16 nm), yəni O(nm). Amma praktiki olaraq yaddaş çox istifadə olunur və 2D Binary Indexed Tree daha yüngül alternativdir.

**6. Lazy propagation-da invariant nədir?**
`tree[node]` — **bütün lazy-lər artıq tətbiq edildikdən sonra** doğru dəyəri saxlayır. Yalnız `tree[child]`-lər yenilənməyə bilər — onların lazy-ləri hələ də gözləyir.

**7. Segment Tree vs Fenwick Tree?**
- **Fenwick**: daha sadə, daha sürətli (~2x), amma yalnız prefix-ə uyğun əməliyyatlar (sum, XOR)
- **Segment Tree**: daha ümumi (min, max, GCD), lazy propagation, range assign

**8. SegTree node sayı niyə 4n?**
Tam binary tree-də yarpaq sayı n üçün, hündürlük `ceil(log2(n))`. Ən pis hal node sayı `2^(log2(n)+2) = 4n`-ə qədər çıxır. Təhlükəsiz allocation.

**9. Iterative Segment Tree nədir?**
Rekursiya əvəzinə döngü ilə yazılmış segtree. Daha sürətli (cache-friendly), amma lazy ilə yazmaq çətindir.

**10. Segment Tree Beats nədir?**
Range chmin/chmax (hər element ilə verilən qiyməti min/max etmək) kimi "təxirə salmaq olmayan" əməliyyatları O(log² n) amortized həll edən metod. Advanced CP texnikası.

## PHP/Laravel ilə Əlaqə

- **Analytics dashboards**: "son N gün ərzində X-Y intervalı üçün ən yüksək trafik". Cache yerinə real-time lazy tree.
- **Time-series DB alternative**: kiçik verilənlər üçün in-memory lazy tree (PHP-də CLI worker-ləri).
- **Redis**: Sorted Set əsasən Skip List-dir, amma range aggregations üçün Redis Streams + in-memory seg tree kombinasiyası mümkündür.
- **Version control sistemləri**: Persistent SegTree git-ə bənzər versiya sistemi ilə uyğun gəlir.
- **Qeyd**: PHP-in CPU sürəti C++-dan yavaşdır; praktikada segtree-i Redis-in server-side script-lərində (Lua) və ya xüsusi mikroservisdə yazmaq daha yaxşıdır.

---

## Praktik Tapşırıqlar

1. **LeetCode 307** — Range Sum Query - Mutable (Segment Tree vs Fenwick Tree)
2. **LeetCode 699** — Falling Squares (coordinate compression + segment tree)
3. **LeetCode 715** — Range Module (lazy propagation ilə range set/unset)
4. **LeetCode 732** — My Calendar III (lazy segtree ilə max overlap count)
5. **LeetCode 850** — Rectangle Area II (coordinate compression + sweep line)

### Step-by-step: Lazy Propagation (range add, range sum)

```
arr = [1, 3, 5, 7, 9, 11]
tree built → seqments: [0-5]=36, [0-2]=9, [3-5]=27, ...

rangeAdd(1, 3, +2):  // arr[1..3] += 2
  segment [0-5]: lazy[root] += 2*(3-1+1) = push to children lazily
  [1-2] fully covered: lazy[node]+=2, tree[node]+=2*2=4
  [3-3] fully covered: lazy[node]+=2, tree[node]+=2

rangeSum(0,3): propagate lazy before querying
  → correct sum without O(n) update ✓
```

---

## Əlaqəli Mövzular

- [43-fenwick-tree.md](43-fenwick-tree.md) — Fenwick Tree (daha sadə, yalnız prefix sum)
- [44-sparse-table.md](44-sparse-table.md) — Sparse Table (statik RMQ, O(1) query)
- [10-prefix-sum.md](10-prefix-sum.md) — Statik prefix sum (ən sadə range structure)
- [16-trees-basics.md](16-trees-basics.md) — Binary tree əsasları
