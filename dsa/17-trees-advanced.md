# Trees - Advanced (Senior)

## Konsept (Concept)

Self-balancing tree-ler BST-nin worst case O(n)-ni O(log n)-e endirmek ucundur. Her insert/delete-den sonra balans qaydasi saxlanilir.

```
AVL Tree (Height-balanced):
        30 (bf=1)
       /  \
   20(0)   40(0)
   / \
 10   25
Balance Factor (bf) = height(left) - height(right)
Butun node-lar ucun: bf in {-1, 0, 1}

Red-Black Tree:
        8(B)
       /    \
    4(R)    12(R)
   / \      / \
 2(B) 6(B) 10(B) 14(B)
B=Black, R=Red
Qayda: root qara, red node-un usaqlari qara, her yol eyni sayi qara node

B-Tree (order m=3):
      [10, 20]
     /    |    \
  [3,7] [12,15] [25,30]
Her node m-1 qeder key, m qeder usaq
Disk ucun optimize - az I/O

Trie (Prefix Tree):
        (root)
       /  |   \
      a   b    c
     / \  |
    p   n d
    |   |
    p   d
    |
    l
    |
    e
  "apple", "and", "bd"
```

## Nece Isleyir? (How does it work?)

### AVL Rotations
```
Right Rotation (sola agir):      Left Rotation (saga agir):
     30                              10
    /                                  \
   20          ->    20                 20      ->     20
  /                 /  \                  \           /  \
 10               10    30                 30       10    30

Left-Right:                    Right-Left:
    30                            10
   /                                \
  10      -> sol rotation(10)        30   -> sag rotation(30)
    \        sonra sag rotation(30)  /       sonra sol rotation(10)
    20                              20

     20                              20
    /  \                            /  \
  10    30                        10    30
```

## Implementasiya (Implementation)

### AVL Tree
```php
<?php
class AVLNode {
    public int $val;
    public ?AVLNode $left = null;
    public ?AVLNode $right = null;
    public int $height = 0;

    public function __construct(int $val) {
        $this->val = $val;
    }
}

class AVLTree {
    private ?AVLNode $root = null;

    private function height(?AVLNode $node): int {
        return $node === null ? -1 : $node->height;
    }

    private function balanceFactor(?AVLNode $node): int {
        return $node === null ? 0 : $this->height($node->left) - $this->height($node->right);
    }

    private function updateHeight(AVLNode $node): void {
        $node->height = 1 + max($this->height($node->left), $this->height($node->right));
    }

    private function rotateRight(AVLNode $y): AVLNode {
        $x = $y->left;
        $y->left = $x->right;
        $x->right = $y;
        $this->updateHeight($y);
        $this->updateHeight($x);
        return $x;
    }

    private function rotateLeft(AVLNode $x): AVLNode {
        $y = $x->right;
        $x->right = $y->left;
        $y->left = $x;
        $this->updateHeight($x);
        $this->updateHeight($y);
        return $y;
    }

    private function balance(AVLNode $node): AVLNode {
        $bf = $this->balanceFactor($node);

        if ($bf > 1) { // Sol agir
            if ($this->balanceFactor($node->left) < 0) {
                $node->left = $this->rotateLeft($node->left); // Left-Right
            }
            return $this->rotateRight($node); // Right rotation
        }

        if ($bf < -1) { // Sag agir
            if ($this->balanceFactor($node->right) > 0) {
                $node->right = $this->rotateRight($node->right); // Right-Left
            }
            return $this->rotateLeft($node); // Left rotation
        }

        return $node;
    }

    public function insert(int $val): void {
        $this->root = $this->insertNode($this->root, $val);
    }

    private function insertNode(?AVLNode $node, int $val): AVLNode {
        if ($node === null) return new AVLNode($val);

        if ($val < $node->val) {
            $node->left = $this->insertNode($node->left, $val);
        } else {
            $node->right = $this->insertNode($node->right, $val);
        }

        $this->updateHeight($node);
        return $this->balance($node);
    }
}
```

### Trie (Prefix Tree)
```php
<?php
class TrieNode {
    public array $children = [];
    public bool $isEnd = false;
}

class Trie {
    private TrieNode $root;

    public function __construct() {
        $this->root = new TrieNode();
    }

    // Insert: O(m) m = soz uzunlugu
    public function insert(string $word): void {
        $node = $this->root;
        for ($i = 0; $i < strlen($word); $i++) {
            $ch = $word[$i];
            if (!isset($node->children[$ch])) {
                $node->children[$ch] = new TrieNode();
            }
            $node = $node->children[$ch];
        }
        $node->isEnd = true;
    }

    // Search: O(m)
    public function search(string $word): bool {
        $node = $this->findNode($word);
        return $node !== null && $node->isEnd;
    }

    // StartsWith: O(m)
    public function startsWith(string $prefix): bool {
        return $this->findNode($prefix) !== null;
    }

    private function findNode(string $prefix): ?TrieNode {
        $node = $this->root;
        for ($i = 0; $i < strlen($prefix); $i++) {
            $ch = $prefix[$i];
            if (!isset($node->children[$ch])) return null;
            $node = $node->children[$ch];
        }
        return $node;
    }

    // Autocomplete: verilmis prefix ile bashlayan butun sozler
    public function autocomplete(string $prefix): array {
        $node = $this->findNode($prefix);
        if ($node === null) return [];

        $results = [];
        $this->dfs($node, $prefix, $results);
        return $results;
    }

    private function dfs(TrieNode $node, string $current, array &$results): void {
        if ($node->isEnd) $results[] = $current;
        foreach ($node->children as $ch => $child) {
            $this->dfs($child, $current . $ch, $results);
        }
    }
}

// Istifade:
$trie = new Trie();
$trie->insert("apple");
$trie->insert("app");
$trie->insert("application");
$trie->search("app");           // true
$trie->startsWith("app");       // true
$trie->autocomplete("app");     // ["app", "apple", "application"]
```

### Segment Tree (Range Query)
```php
<?php
class SegmentTree {
    private array $tree;
    private int $n;

    public function __construct(array $arr) {
        $this->n = count($arr);
        $this->tree = array_fill(0, 4 * $this->n, 0);
        $this->build($arr, 1, 0, $this->n - 1);
    }

    private function build(array &$arr, int $node, int $start, int $end): void {
        if ($start === $end) {
            $this->tree[$node] = $arr[$start];
            return;
        }
        $mid = intdiv($start + $end, 2);
        $this->build($arr, 2 * $node, $start, $mid);
        $this->build($arr, 2 * $node + 1, $mid + 1, $end);
        $this->tree[$node] = $this->tree[2 * $node] + $this->tree[2 * $node + 1];
    }

    // Range sum query: O(log n)
    public function query(int $l, int $r): int {
        return $this->queryHelper(1, 0, $this->n - 1, $l, $r);
    }

    private function queryHelper(int $node, int $start, int $end, int $l, int $r): int {
        if ($r < $start || $end < $l) return 0;
        if ($l <= $start && $end <= $r) return $this->tree[$node];

        $mid = intdiv($start + $end, 2);
        return $this->queryHelper(2 * $node, $start, $mid, $l, $r)
             + $this->queryHelper(2 * $node + 1, $mid + 1, $end, $l, $r);
    }

    // Point update: O(log n)
    public function update(int $index, int $val): void {
        $this->updateHelper(1, 0, $this->n - 1, $index, $val);
    }

    private function updateHelper(int $node, int $start, int $end, int $idx, int $val): void {
        if ($start === $end) {
            $this->tree[$node] = $val;
            return;
        }
        $mid = intdiv($start + $end, 2);
        if ($idx <= $mid) {
            $this->updateHelper(2 * $node, $start, $mid, $idx, $val);
        } else {
            $this->updateHelper(2 * $node + 1, $mid + 1, $end, $idx, $val);
        }
        $this->tree[$node] = $this->tree[2 * $node] + $this->tree[2 * $node + 1];
    }
}
```

### Fenwick Tree (Binary Indexed Tree)
```php
<?php
class FenwickTree {
    private array $tree;
    private int $n;

    public function __construct(int $n) {
        $this->n = $n;
        $this->tree = array_fill(0, $n + 1, 0);
    }

    // Point update: O(log n)
    public function update(int $i, int $delta): void {
        $i++; // 1-indexed
        while ($i <= $this->n) {
            $this->tree[$i] += $delta;
            $i += $i & (-$i); // Next responsible node
        }
    }

    // Prefix sum [0, i]: O(log n)
    public function query(int $i): int {
        $i++;
        $sum = 0;
        while ($i > 0) {
            $sum += $this->tree[$i];
            $i -= $i & (-$i); // Parent
        }
        return $sum;
    }

    // Range sum [l, r]: O(log n)
    public function rangeQuery(int $l, int $r): int {
        return $this->query($r) - ($l > 0 ? $this->query($l - 1) : 0);
    }
}
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Struktur | Search | Insert | Delete | Space |
|----------|--------|--------|--------|-------|
| AVL Tree | O(log n) | O(log n) | O(log n) | O(n) |
| Red-Black | O(log n) | O(log n) | O(log n) | O(n) |
| B-Tree | O(log n) | O(log n) | O(log n) | O(n) |
| Trie | O(m) | O(m) | O(m) | O(alphabet * m * n) |
| Segment Tree | O(log n) | O(log n) | - | O(4n) |
| Fenwick Tree | O(log n) | O(log n) | - | O(n) |

m = key/string uzunlugu, n = element sayi

## Tipik Meseleler (Common Problems)

### 1. Word Search II (Trie + DFS)
```php
<?php
function findWords(array $board, array $words): array {
    $trie = new Trie();
    foreach ($words as $word) $trie->insert($word);

    $result = [];
    $rows = count($board);
    $cols = count($board[0]);

    for ($i = 0; $i < $rows; $i++) {
        for ($j = 0; $j < $cols; $j++) {
            dfsBoard($board, $i, $j, $trie->getRoot(), '', $result);
        }
    }
    return array_unique($result);
}

function dfsBoard(array &$board, int $r, int $c, TrieNode $node, string $word, array &$result): void {
    if ($r < 0 || $r >= count($board) || $c < 0 || $c >= count($board[0])) return;
    $ch = $board[$r][$c];
    if ($ch === '#' || !isset($node->children[$ch])) return;

    $word .= $ch;
    $node = $node->children[$ch];
    if ($node->isEnd) $result[] = $word;

    $board[$r][$c] = '#';
    dfsBoard($board, $r+1, $c, $node, $word, $result);
    dfsBoard($board, $r-1, $c, $node, $word, $result);
    dfsBoard($board, $r, $c+1, $node, $word, $result);
    dfsBoard($board, $r, $c-1, $node, $word, $result);
    $board[$r][$c] = $ch;
}
```

### 2. Range Sum Query - Mutable
Segment Tree ve ya Fenwick Tree istifade edin (yuxaridaki implementasiya).

### 3. Kth Smallest in BST
```php
<?php
function kthSmallest(?TreeNode $root, int $k): int {
    $stack = [];
    $current = $root;
    $count = 0;

    while ($current !== null || !empty($stack)) {
        while ($current !== null) {
            $stack[] = $current;
            $current = $current->left;
        }
        $current = array_pop($stack);
        $count++;
        if ($count === $k) return $current->val;
        $current = $current->right;
    }
    return -1;
}
```

## Interview Suallari

**S: AVL vs Red-Black tree ferqi?**
C: AVL daha ciddi balanced-dir (height ferqi max 1), axtaris daha suretli. Red-Black daha az rotation edir, insert/delete daha suretli. C++ map = Red-Black, database index-ler = B-tree.

**S: B-tree niye database-de istifade olunur?**
C: Disk I/O minimize edir. Genis node-lar (yuzlerle key) bir disk block-a sigir. Height az olur (3-4), az disk read lazim. B+ tree-de data yalniz leaf-lerde -- range scan effektiv.

**S: Trie ne vaxt hash table-dan yaxsidir?**
C: Prefix axtarisi lazim olduqda (autocomplete), sozlerin sorted sirasi, wildcard matching. Hash table yalniz exact match ucun.

**S: Segment tree vs Fenwick tree?**
C: Fenwick daha sadedir ve az yaddas istifade edir. Amma yalniz prefix emeliyyatlari ucun isleyir (sum, xor). Segment tree daha umumi -- min, max, GCD ucun de istifade oluna biler.

## PHP/Laravel ile Elaqe

```php
<?php
// 1. MySQL InnoDB = B+ Tree
// PRIMARY KEY -> Clustered Index (B+ tree, data leaf-lerde)
// SECONDARY INDEX -> Non-clustered (B+ tree, pointer leaf-lerde)
// EXPLAIN SELECT * FROM users WHERE email = 'x' -- index istifadesini gor

// 2. Redis Sorted Set = Skip List (balanced tree alternativi)
// ZADD leaderboard 100 "user:1"
// ZRANGEBYSCORE leaderboard 50 100 -- range query

// 3. PHP SplMaxHeap, SplMinHeap (tree-based)

// 4. Autocomplete sistemi (Trie)
// Laravel Scout + Trie index ile suretli search suggestions

// 5. Elasticsearch inverted index (trie variant)
// Laravel Scout + Elasticsearch driver

// 6. Nested Set Model (Laravel packages):
// baum/baum, kalnoy/nestedset
// Tree traversal SQL ile:
// SELECT * FROM categories WHERE lft BETWEEN parent.lft AND parent.rgt

// 7. Route matching (radix tree / trie variant)
// Laravel router daxili olaraq trie-benze struktur istifade edir
```
