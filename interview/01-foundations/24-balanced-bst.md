# Balanced BST (AVL, Red-Black Tree) (Lead ⭐⭐⭐⭐)

## İcmal

Balanced BST — yüksəkliyini O(log n)-də saxlayan özü-balanslaşdıran binary search tree-dir. AVL tree hər node-da height fərqini ≤1 saxlayır (daha ciddi balans), Red-Black tree isə 5 rəng qaydasına əsaslanır (daha az rotation, insert/delete üçün daha sürətli). Java-nın `TreeMap`/`TreeSet`, C++-ın `std::map`/`std::set`, Linux kernel-in process scheduler — hamısı Red-Black tree-dən istifadə edir. Bu mövzu Lead-level interview-da daxili mexanizmləri başa düşməyi test edir.

## Niyə Vacibdir

Balanced BST-nin əhəmiyyəti ondadır ki, worst case O(n) olan adi BST-ni O(log n) guarantee-si olan struktura çevirir. Database B-tree-lərin, in-memory sorted structure-ların, OS scheduler-ların əsasıdır. Lead/Principal interview-larında soruşulan: "Java TreeMap-in daxilindəki mexanizm nədir?", "Hash map vs TreeMap — nə zaman hansını seçərsiniz?", "Database index üçün B-tree yoxsa Red-Black tree?" Bu suallar surface-level bilikdən daha dərini tələb edir.

## Əsas Anlayışlar

### Adi BST-nin Problemi

```
Sorted array-i insert etsəm: 1, 2, 3, 4, 5
BST görünüşü:
  1
   \
    2
     \
      3
       \
        4
         \
          5
```

Bu vəziyyətdə search O(n) olur — linked list kimi davranır. Balanced BST bu problemi həll edir.

### AVL Tree

**Balance Factor (BF)**:
`BF(node) = height(left_subtree) - height(right_subtree)`
AVL invariant: Hər node üçün `|BF| ≤ 1`.

**Height**: `height(null) = -1`, `height(leaf) = 0`.

**Rotation Növləri (4 tip)**:

```
1. Right Rotation (LL imbalance — sol ağır):
       y                 x
      / \               / \
     x   C    →       A   y
    / \                   / \
   A   B                 B   C

2. Left Rotation (RR imbalance — sağ ağır):
   x                    y
  / \                  / \
 A   y      →         x   C
    / \              / \
   B   C            A   B

3. Left-Right Rotation (LR imbalance):
   z                z              x
  /    Left-Rot    /   Right-Rot  / \
 y       →        x      →      y   z
  \              /
   x            y

4. Right-Left Rotation (RL imbalance):
  z                z              x
   \   Right-Rot    \  Left-Rot  / \
    y      →         x    →    z   y
   /                  \
  x                    y
```

**Insert**: BST insert et, yolu aşağıdan yuxarıya yenidən balans et.
**Delete**: BST delete et, balansı yenilə. Successor/predecessor ilə əvəzlə.
**Complexity**: O(log n) search, insert, delete — hər zaman guarantee.

**AVL-in üstünlüyü**: Red-Black-dan daha ciddi balans → search-intensive workload-larda daha yaxşı.
**AVL-in çatışmazlığı**: Insert/delete zamanı daha çox rotation → write-heavy workload-larda Red-Black daha sürətli.

### Red-Black Tree

**5 Qayda (invariant)**:
1. Hər node ya qırmızı, ya qara-dır.
2. Root **qara**-dır.
3. Hər leaf (NIL node) **qara**-dır (null pointer qara sayılır).
4. Qırmızı node-un hər iki uşağı **qara**-dır (qırmızı-qırmızı ardıcıllığı yoxdur).
5. Hər node-dan bütün leaf node-lara gedən yollarda eyni sayda **qara** node var (black-height).

**Nəticə**: Ən uzun yol ≤ 2 × ən qısa yol → height ≤ 2 log₂(n+1) → O(log n) guarantee.

**Niyə bu qaydalar height-i O(log n) saxlayır?**
- Qayda 5: Hər path-da eyni sayda qara node var — qara yüksəklik `bh`.
- Ən qısa yol: Yalnız qara node-lar → uzunluq `bh`.
- Ən uzun yol: Növbələşən qara-qırmızı → uzunluq `2*bh`.
- Node sayı: n ≥ 2^bh - 1 → bh ≤ log₂(n+1) → height ≤ 2*bh ≤ 2*log₂(n+1).

**Rebalancing Mexanizmi**:
- **Recolor**: Nisbətən ucuz, pointer dəyişmir. O(1).
- **Rotation**: LL/RR kimi, tree strukturunu dəyişir. O(1).
- Insert: Maksimum **2 rotation** (AVL-dən az).
- Delete: Maksimum **3 rotation** (AVL-dən az).

**AVL vs Red-Black**:
| | AVL | Red-Black |
|--|-----|-----------|
| Balans ciddiyi | Strict (\|BF\|≤1) | Relaxed (2x rule) |
| Height upper bound | 1.44 log₂(n) | 2 log₂(n+1) |
| Search | Daha sürətli | Bir az yavaş |
| Insert/Delete rotation | Daha çox | Az (max 2-3) |
| Memory overhead | Height saxlamaq (int) | Rəng biti (1 bit) |
| Tətbiq | Read-heavy | Write-heavy, DB index, OS |

### B-Tree (Database üçün)

- Multi-way search tree — hər node `t` ilə `2t` arası key saxlayır.
- **Disk-friendly**: Bir node = bir disk block → I/O minimizasiyası (disk seek baha).
- **B+ tree**: Bütün data leaf-larda, internal node-lar yalnız key. Range query sürətli (leaf-lər linked list-dir).
- PostgreSQL, MySQL index-ləri B+ tree-dir.
- B-tree daxilindəki axtarış hər node-da binary search.

**Niyə Red-Black yox, B-tree?**
- B-tree: Hər node-da çox key → tree daha enli, az dərin → az disk I/O.
- Bir disk block read = 4KB = yüzlərlə key. Bu səviyyəyə bir disk read lazımdır.
- Memory-da: Red-Black tree pointer chasing ucuzdur (cache-dədir). Diskdə: Hər pointer = disk seek.

### Treap (BST + Heap hybrid)

- Hər node-da: key (BST property) + random priority (max-heap property).
- Balanced olma ehtimalı yüksəkdir (randomized).
- Insert/Delete daha sadə implement olunur (rotation sadədir).
- Expected O(log n) — guarantee deyil (amma praktikada çox yaxşı).
- Merge/Split operasiyaları effektiv.

### Skip List

- Balanced BST-nin linked list-based alternatividir.
- Multi-level linked list: Alt level = bütün elementlər, üst level-lər = "express lane".
- Probabilistic balance: Expected O(log n).
- Redis sorted set-lərdə istifadə olunur.
- **Lock-free concurrent implementasiyası asandır** (B-tree-dən fərqli) — bu böyük üstünlük.
- Space: O(n log n) expected.

### Java TreeMap / C++ std::map

- Java `TreeMap`: Red-Black tree. `NavigableMap` interface — `floor`, `ceiling`, `subMap`.
- Java `TreeSet`: Red-Black tree set.
- C++ `std::map`: Red-Black tree (implementation-dependent, amma standart impls RBT).
- Python: Stdlib-də yoxdur. `sortedcontainers.SortedList` (skip list-based).
- PHP `SplBinarySearchTree`: BST-dir (balanced guarantee yoxdur default-da).

### HashMap vs TreeMap Seçimi

| HashMap/HashSet | TreeMap/TreeSet |
|---------|---------|
| O(1) average insert/lookup | O(log n) insert/lookup |
| Sorted order lazım deyil | Sorted iteration lazım |
| Range query yoxdur | Range query var (floorKey, subMap) |
| Memory daha az | Pointer overhead var |
| Hash collision riski | Collision yoxdur |
| Unordered iteration | Sorted iteration (in-order) |

## Praktik Baxış

### Interview Yanaşması

1. "Bu problemi HashMap ilə həll edə bilmərəm, çünki sorted order / range query lazımdır" → TreeMap.
2. AVL rotation-larını pseudocode ilə izah et (tam kod lazım olmaya bilər — rotate funksiyaları qoy).
3. Red-Black tree-nin 5 qaydasını əzbərdən say. Nəticəni (height O(log n)) əsaslandır.
4. B-tree-ni yad et: Database index kontekstindədir.
5. Skip list-i Redis sorted set ilə əlaqələndir.

### Nədən Başlamaq

- Əvvəlcə BST-nin invariant-ını söylə: `left < node < right`.
- Sonra "niyə balans lazımdır" — worst case linear (sorted insert).
- AVL: BF hesablama, 4 rotation tipi.
- RBT: 5 qayda → height O(log n) sübut.
- B-tree: Disk I/O minimizasiyası.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Java TreeMap-in insertion zamanı neçə rotation edə bilər?" — Max 2.
- "Database-də niyə B-tree, yaddaşda niyə Red-Black tree istifadə olunur?" — Disk I/O vs RAM.
- "Concurrent access üçün balanslaşdırılmış BST-ni necə thread-safe edərdiniz?" — Lock-free skip list.
- "Skip list Red-Black tree-dən üstün olduğu hal varmı?" — Concurrent, merge/split.
- "AVL-in 4 rotation növünü izah edin." — LL, RR, LR, RL.
- "Red-Black tree-nin 5 qaydasını sayın."

### Common Mistakes

- Red-Black tree-nin 5 qaydasını tam saya bilməmək.
- AVL-in yalnız iki rotation növünün olduğunu düşünmək (4-dür).
- B-tree ilə Binary tree-ni qarışdırmaq — B-tree multi-way, binary tree deyil.
- "Rotation O(1)-dir" deməyi unutmaq — bəziləri O(log n) hesab edir.
- B+ tree ilə B-tree-ni qarışdırmaq — B+ tree-də data yalnız leaf-lərdədir.
- Skip list-in probabilistic olduğunu (guarantee yox) bilməmək.

### Yaxşı → Əla Cavab

- **Yaxşı**: AVL və RBT-ni izah edir, Java TreeMap-i bilir.
- **Əla**: B-tree ilə fərqi izah edir (disk I/O), skip list alternativini qeyd edir (concurrent), concurrent data structure seçimi haqqında danışır, database index design-ına bağlayır, height upper bound-u sübut edir.

### Real Production Ssenariləri

- Java Collections: `TreeMap`, `TreeSet` — Red-Black tree.
- Linux kernel: Red-Black tree — process scheduling (CFS), virtual memory area management.
- PostgreSQL/MySQL: B+ tree — index storage.
- Redis: Skip list — sorted sets.
- Nginx: Red-Black tree — timer management.

## Nümunələr

### Tipik Interview Sualı

"Design bir data structure: `insert(val)`, `delete(val)`, `getRandom()` əməliyyatlarını O(log n) ilə dəstəkləsin."

### Güclü Cavab

Bu problemi `TreeSet` (Red-Black tree) + `ArrayList` ilə həll edə bilərdim. Insert/delete O(log n). `getRandom()` üçün: TreeSet-in `first()` + `higher()` ilə O(log n) random rank navigation.

Amma əslən O(1) `getRandom()` lazımdırsa: `HashMap` + `ArrayList` (LeetCode 380). Sorted order lazımdırsa isə trade-off-u açıq deyirəm: getRandom O(log n) olacaq.

Red-Black tree-nin 5 invariantını yada salaraq: Root qara, qırmızı-qırmızı ardıcıllığı yoxdur, eyni black-height, null leaf-lər qara. Bu qaydalar height-in 2log(n+1)-dən böyük olmayacağını təmin edir.

### Kod Nümunəsi

```python
# AVL Tree — Tam Implementasiya
class AVLNode:
    def __init__(self, val):
        self.val = val
        self.left = self.right = None
        self.height = 1

class AVLTree:
    def _height(self, node) -> int:
        return node.height if node else 0

    def _bf(self, node) -> int:
        return self._height(node.left) - self._height(node.right)

    def _update_height(self, node):
        node.height = 1 + max(self._height(node.left), self._height(node.right))

    def _right_rotate(self, y: AVLNode) -> AVLNode:
        """LL case: y sola ağır, sol uşaq x-i yuxarı qaldır"""
        x = y.left
        T2 = x.right
        x.right = y
        y.left = T2
        self._update_height(y)
        self._update_height(x)
        return x  # yeni root

    def _left_rotate(self, x: AVLNode) -> AVLNode:
        """RR case: x sağa ağır, sağ uşaq y-i yuxarı qaldır"""
        y = x.right
        T2 = y.left
        y.left = x
        x.right = T2
        self._update_height(x)
        self._update_height(y)
        return y  # yeni root

    def _rebalance(self, node: AVLNode) -> AVLNode:
        self._update_height(node)
        bf = self._bf(node)

        # LL case: sol-sol — right rotation
        if bf > 1 and self._bf(node.left) >= 0:
            return self._right_rotate(node)

        # LR case: sol-sağ — left rotation on left child, then right rotation
        if bf > 1 and self._bf(node.left) < 0:
            node.left = self._left_rotate(node.left)
            return self._right_rotate(node)

        # RR case: sağ-sağ — left rotation
        if bf < -1 and self._bf(node.right) <= 0:
            return self._left_rotate(node)

        # RL case: sağ-sol — right rotation on right child, then left rotation
        if bf < -1 and self._bf(node.right) > 0:
            node.right = self._right_rotate(node.right)
            return self._left_rotate(node)

        return node   # balans qorunur

    def insert(self, node: AVLNode, val: int) -> AVLNode:
        if not node:
            return AVLNode(val)
        if val < node.val:
            node.left = self.insert(node.left, val)
        elif val > node.val:
            node.right = self.insert(node.right, val)
        else:
            return node   # duplicate — ignore
        return self._rebalance(node)

    def search(self, node: AVLNode, val: int) -> bool:
        if not node:
            return False
        if val == node.val:
            return True
        if val < node.val:
            return self.search(node.left, val)
        return self.search(node.right, val)

# Java TreeMap istifadəsi — PHP developer üçün psevdokod
"""
TreeMap<Integer, String> map = new TreeMap<>();
map.put(3, "c");
map.put(1, "a");
map.put(5, "e");
map.put(2, "b");

// Sorted iteration — in-order: 1, 2, 3, 5
for (Map.Entry<Integer, String> e : map.entrySet()) {
    System.out.println(e.getKey() + " -> " + e.getValue());
}

// Navigation methods
map.floorKey(4);   // 3 — ≤4 olan ən böyük key
map.ceilingKey(4); // 5 — ≥4 olan ən kiçik key
map.lowerKey(3);   // 2 — <3 olan ən böyük key
map.higherKey(3);  // 5 — >3 olan ən kiçik key

// Range query
NavigableMap<Integer,String> sub = map.subMap(2, true, 4, false);
// {2:"b", 3:"c"} — [2, 4) aralığı

// Closest value search
Integer floor = map.floorKey(x);
Integer ceil = map.ceilingKey(x);
"""

# PHP: SortedList benzeri əməliyyat — bisect modulu
import bisect

class SortedList:
    """Python-da sorted list (interval axtarış üçün)"""
    def __init__(self):
        self.data = []

    def insert(self, val):
        bisect.insort(self.data, val)   # O(n) worst case, amma kiçik n üçün OK

    def floor(self, val):
        """val-dən ≤ olan ən böyük element"""
        idx = bisect.bisect_right(self.data, val) - 1
        return self.data[idx] if idx >= 0 else None

    def ceiling(self, val):
        """val-dən ≥ olan ən kiçik element"""
        idx = bisect.bisect_left(self.data, val)
        return self.data[idx] if idx < len(self.data) else None

# LeetCode 220 — Contains Duplicate III — TreeSet/SortedList ilə
def contains_nearby_almost_duplicate(nums: list, k: int, t: int) -> bool:
    from sortedcontainers import SortedList   # Python-da 3rd party
    window = SortedList()
    for i, num in enumerate(nums):
        # Ceiling: window-da num-t-dən ≥ olan ən kiçik element
        idx = window.bisect_left(num - t)
        if idx < len(window) and window[idx] <= num + t:
            return True
        window.add(num)
        if len(window) > k:
            window.remove(nums[i - k])
    return False
```

### İkinci Nümunə — Skyline Problem (TreeMap)

```python
# LeetCode 218 — Skyline Problem — TreeMap ilə
import heapq
from sortedcontainers import SortedList

def get_skyline(buildings: list) -> list:
    events = []
    for l, r, h in buildings:
        events.append((l, -h, 'start'))
        events.append((r, h, 'end'))
    events.sort(key=lambda x: (x[0], x[1]))

    result = []
    # max-heap: aktiv binaların hündürlükləri
    heap = [(0, float('inf'))]   # (mənfi hündürlük, sona qədər)
    for pos, h, t in events:
        if t == 'start':
            heapq.heappush(heap, (h, pos))   # h artıq mənfidir
        else:
            # Bu binanı "silin" (lazy deletion)
            pass  # simplified — real impl lazy deletion lazımdır
        # current max height
        while heap[0][1] <= pos:
            heapq.heappop(heap)
        max_h = -heap[0][0]
        if result and result[-1][1] == max_h:
            continue
        result.append([pos, max_h])
    return result
```

## Praktik Tapşırıqlar

1. LeetCode #220 — Contains Duplicate III (TreeSet floor/ceiling).
2. LeetCode #315 — Count of Smaller Numbers After Self (BST / BIT).
3. LeetCode #699 — Falling Squares (segment tree / sorted set).
4. LeetCode #218 — Skyline Problem (TreeMap / max-heap).
5. AVL tapşırığı: 10 elementi əl ilə AVL tree-yə insert et. Hər addımda BF-i hesabla, rotation tələb olunan anı göstər. Trace et.
6. Red-Black tapşırığı: 5 qaydasını əzbərlə. Niyə bu qaydalar height-i O(log n) saxladığını izah et.
7. Sistem dizayn: In-memory leaderboard sistemi — 10M user, O(log n) rank update, O(1) top-K. Balanced BST + Skip list müqayisəsi.
8. Özünütəst: HashMap vs TreeMap seçim meyarları — 5 real scenario yaz.
9. B-tree sual: PostgreSQL index üçün B-tree seçilir, RAM-da data saxlamaq üçün Red-Black tree. Niyə fərq var?
10. Concurrent tapşırığı: Redis sorted set skip list-dir. Thread-safe olması üçün niyə skip list Red-Black tree-dən üstündür?

## Əlaqəli Mövzular

- **Binary Search Tree** — Balanced BST-nin əsası. Unbalanced BST-nin problemlərini bilmək.
- **Segment Tree** — Range query üçün başqa specialized tree. Interval/aggregate queries.
- **Heap** — Priority queue vs sorted structure. O(log n) extract-min vs O(log n) search.
- **Hash Map** — O(1) average vs O(log n) guaranteed. Sorted order vs unsorted.
- **B-Tree / Database Index** — Disk-based balanced tree. Database design-da kritik.
- **Skip List** — Concurrent BST alternativ. Redis sorted sets.
