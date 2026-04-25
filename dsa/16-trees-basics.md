# Trees - Basics (Senior)

## Konsept (Concept)

Tree iyerarxik data strukturudur. Bir kok (root) node var ve her node-un uaq (child) node-lari ola biler. Binary tree-de her node-un maksimum 2 usagi var.

```
Binary Tree:
            8
          /   \
        4      12
       / \    /  \
      2   6  10   14
     / \   \
    1   3   7

BST (Binary Search Tree) qaydalari:
- Sol alt agac < node
- Sag alt agac > node
- Her alt agac ozu de BST-dir

Terminologiya:
- Root: 8 (en ust node)
- Leaf: 1, 3, 7, 10, 14 (usagi olmayan)
- Height: 3 (root-dan en uzaq leaf-e qeder)
- Depth: node-un root-dan mesafesi (root=0)
- Parent/Child: 4 parent-dir, 2 ve 6 child-dir
- Sibling: 2 ve 6 qardashdir
```

## Nece Isleyir? (How does it work?)

### BST axtaris addim-addim:
```
6-ni axtar (root = 8):
1. 6 < 8 -> sola get
2. 6 > 4 -> saga get
3. 6 == 6 -> TAPILDI!

15-i axtar (root = 8):
1. 15 > 8 -> saga get
2. 15 > 12 -> saga get
3. 15 > 14 -> saga get, amma sag null
4. TAPILMADI
```

### Traversallar (gezme usullari):
```
         1
        / \
       2   3
      / \
     4   5

Inorder (Sol, Kok, Sag):   4, 2, 5, 1, 3
Preorder (Kok, Sol, Sag):  1, 2, 4, 5, 3
Postorder (Sol, Sag, Kok): 4, 5, 2, 3, 1
Level-order (BFS):         1, 2, 3, 4, 5
```

## Implementasiya (Implementation)

### TreeNode ve BST
```php
<?php
class TreeNode {
    public int $val;
    public ?TreeNode $left;
    public ?TreeNode $right;

    public function __construct(int $val, ?TreeNode $left = null, ?TreeNode $right = null) {
        $this->val = $val;
        $this->left = $left;
        $this->right = $right;
    }
}

class BST {
    private ?TreeNode $root = null;

    // Elave: O(h) -- h = height
    public function insert(int $val): void {
        $this->root = $this->insertNode($this->root, $val);
    }

    private function insertNode(?TreeNode $node, int $val): TreeNode {
        if ($node === null) return new TreeNode($val);

        if ($val < $node->val) {
            $node->left = $this->insertNode($node->left, $val);
        } elseif ($val > $node->val) {
            $node->right = $this->insertNode($node->right, $val);
        }
        return $node;
    }

    // Axtaris: O(h)
    public function search(int $val): ?TreeNode {
        return $this->searchNode($this->root, $val);
    }

    private function searchNode(?TreeNode $node, int $val): ?TreeNode {
        if ($node === null || $node->val === $val) return $node;

        return $val < $node->val
            ? $this->searchNode($node->left, $val)
            : $this->searchNode($node->right, $val);
    }

    // Silme: O(h)
    public function delete(int $val): void {
        $this->root = $this->deleteNode($this->root, $val);
    }

    private function deleteNode(?TreeNode $node, int $val): ?TreeNode {
        if ($node === null) return null;

        if ($val < $node->val) {
            $node->left = $this->deleteNode($node->left, $val);
        } elseif ($val > $node->val) {
            $node->right = $this->deleteNode($node->right, $val);
        } else {
            // Node tapildi - 3 hal:
            // 1. Leaf node
            if ($node->left === null && $node->right === null) {
                return null;
            }
            // 2. Bir usaq
            if ($node->left === null) return $node->right;
            if ($node->right === null) return $node->left;
            // 3. Iki usaq: inorder successor ile evezle
            $successor = $this->findMin($node->right);
            $node->val = $successor->val;
            $node->right = $this->deleteNode($node->right, $successor->val);
        }
        return $node;
    }

    private function findMin(TreeNode $node): TreeNode {
        while ($node->left !== null) $node = $node->left;
        return $node;
    }
}
```

### Traversallar
```php
<?php
// Inorder (BST-de sorted sirani verir)
function inorder(?TreeNode $root): array {
    if ($root === null) return [];
    return array_merge(
        inorder($root->left),
        [$root->val],
        inorder($root->right)
    );
}

// Preorder (tree serialize etmek ucun)
function preorder(?TreeNode $root): array {
    if ($root === null) return [];
    return array_merge(
        [$root->val],
        preorder($root->left),
        preorder($root->right)
    );
}

// Postorder (silme emeliyyatlari, expression trees)
function postorder(?TreeNode $root): array {
    if ($root === null) return [];
    return array_merge(
        postorder($root->left),
        postorder($root->right),
        [$root->val]
    );
}

// Level-order (BFS)
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

// Iterative Inorder (stack ile)
function inorderIterative(?TreeNode $root): array {
    $result = [];
    $stack = [];
    $current = $root;

    while ($current !== null || !empty($stack)) {
        while ($current !== null) {
            $stack[] = $current;
            $current = $current->left;
        }
        $current = array_pop($stack);
        $result[] = $current->val;
        $current = $current->right;
    }

    return $result;
}
```

### Height ve Depth
```php
<?php
// Agacin hundurluyunu tap
function treeHeight(?TreeNode $root): int {
    if ($root === null) return -1; // bosh agac = -1 (edge count)
    // return 0 etsek node count olur

    return 1 + max(treeHeight($root->left), treeHeight($root->right));
}

// Balanced tree yoxlamasi
function isBalanced(?TreeNode $root): bool {
    return checkBalance($root) !== -1;
}

function checkBalance(?TreeNode $root): int {
    if ($root === null) return 0;

    $left = checkBalance($root->left);
    if ($left === -1) return -1;

    $right = checkBalance($root->right);
    if ($right === -1) return -1;

    if (abs($left - $right) > 1) return -1;

    return 1 + max($left, $right);
}
```

### Diger Esas Meseleler
```php
<?php
// Iki agac eynidirmi?
function isSameTree(?TreeNode $p, ?TreeNode $q): bool {
    if ($p === null && $q === null) return true;
    if ($p === null || $q === null) return false;
    return $p->val === $q->val
        && isSameTree($p->left, $q->left)
        && isSameTree($p->right, $q->right);
}

// Mirror/Symmetric tree
function isSymmetric(?TreeNode $root): bool {
    return isMirror($root?->left, $root?->right);
}

function isMirror(?TreeNode $a, ?TreeNode $b): bool {
    if ($a === null && $b === null) return true;
    if ($a === null || $b === null) return false;
    return $a->val === $b->val
        && isMirror($a->left, $b->right)
        && isMirror($a->right, $b->left);
}

// Agaci terse cevir (invert binary tree)
function invertTree(?TreeNode $root): ?TreeNode {
    if ($root === null) return null;
    [$root->left, $root->right] = [$root->right, $root->left];
    invertTree($root->left);
    invertTree($root->right);
    return $root;
}

// Maksimum yol cemi (path sum)
function maxPathSum(?TreeNode $root): int {
    $maxSum = PHP_INT_MIN;
    maxPathHelper($root, $maxSum);
    return $maxSum;
}

function maxPathHelper(?TreeNode $node, int &$maxSum): int {
    if ($node === null) return 0;

    $left = max(0, maxPathHelper($node->left, $maxSum));
    $right = max(0, maxPathHelper($node->right, $maxSum));

    $maxSum = max($maxSum, $left + $right + $node->val);

    return $node->val + max($left, $right);
}

// Lowest Common Ancestor (BST)
function lowestCommonAncestor(?TreeNode $root, int $p, int $q): ?TreeNode {
    if ($root === null) return null;

    if ($p < $root->val && $q < $root->val) {
        return lowestCommonAncestor($root->left, $p, $q);
    }
    if ($p > $root->val && $q > $root->val) {
        return lowestCommonAncestor($root->right, $p, $q);
    }
    return $root; // Ayrilma noqtesi = LCA
}

// BST validity yoxla
function isValidBST(?TreeNode $root, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool {
    if ($root === null) return true;
    if ($root->val <= $min || $root->val >= $max) return false;
    return isValidBST($root->left, $min, $root->val)
        && isValidBST($root->right, $root->val, $max);
}
```

### Serialize/Deserialize
```php
<?php
function serialize(?TreeNode $root): string {
    if ($root === null) return 'null';
    return $root->val . ',' . serialize($root->left) . ',' . serialize($root->right);
}

function deserialize(string $data): ?TreeNode {
    $values = explode(',', $data);
    $index = 0;
    return deserializeHelper($values, $index);
}

function deserializeHelper(array &$values, int &$index): ?TreeNode {
    if ($index >= count($values) || $values[$index] === 'null') {
        $index++;
        return null;
    }
    $node = new TreeNode((int)$values[$index++]);
    $node->left = deserializeHelper($values, $index);
    $node->right = deserializeHelper($values, $index);
    return $node;
}
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Emeliyyat | BST Average | BST Worst | Balanced BST |
|-----------|-------------|-----------|-------------|
| Search | O(log n) | O(n) | O(log n) |
| Insert | O(log n) | O(n) | O(log n) |
| Delete | O(log n) | O(n) | O(log n) |
| Traversal | O(n) | O(n) | O(n) |

Worst case: Degenerate tree (butun node-lar bir terefde -- linked list kimi)
```
1 -> 2 -> 3 -> 4 -> 5  (height = n, degenerate/skewed tree)
```

Space: O(n) saxlamaq ucun, O(h) rekursiya ucun (h = height)

## Tipik Meseleler (Common Problems)

1. **Validate BST** - min/max range ile yoxla
2. **Invert Binary Tree** - Her node-un usaqlarini deyis
3. **Maximum Depth** - Recursive: 1 + max(left, right)
4. **Level Order Traversal** - BFS ile seviyye-seviyye
5. **Lowest Common Ancestor** - BST: bolunme noqtesi, BT: recursive check

## Interview Suallari

**S: BST-nin massivden ustunluyu?**
C: Sorted massivde insert O(n) (shift lazim), BST-de O(log n). Amma random access yoxdur.

**S: Balanced vs Unbalanced tree ferqi?**
C: Balanced-de height O(log n), emeliyyatlar O(log n). Unbalanced-de height O(n) ola biler (linked list kimi), emeliyyatlar O(n).

**S: Inorder traversal BST-de niye sorted verir?**
C: BST qaydasi: sol < kok < sag. Inorder sira: sol, kok, sag -- bu artan sirani verir.

**S: Tree ne vaxt hash table-dan yaxsidir?**
C: Range query (5-den 10-a qeder elementler), min/max tapmaq, sorted sirada iterate. Hash table bunlari O(n)-de edir, BST O(log n + k)-da.

## PHP/Laravel ile Elaqe

```php
<?php
// 1. Database B-tree indexes
// MySQL InnoDB PRIMARY KEY -> clustered B+ tree
// SELECT * FROM users WHERE id = 5 -> B+ tree axtaris O(log n)

// 2. Laravel nested set / closure table
// Kateqoriya iyerarxiyasi:
// - Elektronika
//   - Telefonlar
//     - iPhone
//     - Samsung
//   - Kompyuterler

// 3. File system - tree struktur
// vendor/laravel/framework/src/...

// 4. DOM tree (HTML parsing)

// 5. Laravel Collection tree operations
function buildTree(array $items, ?int $parentId = null): array {
    $tree = [];
    foreach ($items as $item) {
        if ($item['parent_id'] === $parentId) {
            $children = buildTree($items, $item['id']);
            if ($children) $item['children'] = $children;
            $tree[] = $item;
        }
    }
    return $tree;
}

// 6. Expression parsing (AST - Abstract Syntax Tree)
// Blade template compiler tree structure
```
