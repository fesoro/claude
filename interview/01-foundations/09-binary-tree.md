# Binary Tree (Middle ⭐⭐)

## İcmal
Binary tree — hər node-un ən çox iki child-ı (left, right) olan hierarchical data structure-dur. Recursion, tree traversal, level-order processing kimi əsas konseptlər bu mövzuda birləşir. Interview-larda binary tree sualları kompüterlərindən asılı olmayaraq demək olar hər orta/senior texniki interview-da müzakirə olunur.

## Niyə Vacibdir
Binary tree anlayışı olmadan BST, heap, trie, segment tree, syntax tree kimi strukturları başa düşmək mümkün deyil. Praktikdə: JSON/XML AST-ləri, database execution plans, file system hierarchies, decision trees — hamısı tree strukturuna əsaslanır. Google, Meta, Amazon-da "binary tree" mövzusu orta çətinliklı sualların böyük qismini təşkil edir — xüsusilə recursive tree manipulation.

## Əsas Anlayışlar

### Terminologiya:
- **Root**: Kökün node-u (parent-i yoxdur).
- **Leaf**: Hər iki child-ı null olan node.
- **Height**: Root-dan ən uzaq leaf-ə gedən ən uzun yol. Recursion-da: `1 + max(height(left), height(right))`.
- **Depth**: Root-dan müəyyən node-a gedən yol uzunluğu.
- **Level**: Depth ilə eyni, root level 0-dır (bəzən 1 sayılır — interview başında dəqiqləşdir).
- **Subtree**: Hər hansı bir node + onun bütün törəmələri.
- **Balanced tree**: Sol və sağ subtree-lərinin hündürlükləri fərqi ≤ 1.
- **Ancestor/Descendant**: Node-un yuxarısındakılar/aşağısındakılar.

### Binary Tree Xüsusiyyətləri:
- n node-lu binary tree-nin maximum hündürlüyü: n-1 (skewed/degenerate tree — linked list kimi).
- Balanced binary tree-nin hündürlüyü: O(log n).
- Level k-da maximum 2ᵏ node var.
- n node-lu complete binary tree-nin hündürlüyü: ⌊log₂ n⌋.
- n node-lu binary tree-nin minimum hündürlüyü: ⌈log₂(n+1)⌉ - 1.

### Tree Traversal Növləri:
- **Pre-order (NLR)**: Node → Left → Right. Root həmişə birincidır. Kopyalama, serialization.
- **In-order (LNR)**: Left → Node → Right. BST-də sorted sıra verir. En çox istifadə olunan.
- **Post-order (LRN)**: Left → Right → Node. Delete/cleanup üçün. Alt-nəticələri birləşdir.
- **Level-order (BFS)**: Hər niveli soldan sağa. Queue istifadə edir. Shortest path, width.

### Rekursiv Traversal Şablonu:
```
def traverse(node):
    if not node:        # base case — null check vacibdir
        return
    # pre-order: burada işlə (node-u əvvəl işlə)
    traverse(node.left)
    # in-order: burada işlə (left-dən sonra, right-dan əvvəl)
    traverse(node.right)
    # post-order: burada işlə (hər iki child-dan sonra)
```

### Full, Complete, Perfect Binary Tree:
- **Full**: Hər node-un ya 0, ya 2 child-ı var. Hiç bir node-un 1 child-ı yoxdur.
- **Complete**: Bütün level-lər dolu, son level soldan sağa dolur. Heap-in quruluşu. Array-də efficient saxlanır.
- **Perfect**: Bütün leaf-lər eyni level-dədir, bütün node-ların 2 child-ı var. n = 2^(h+1) - 1.
- **Degenerate/Skewed**: Hər node-un yalnız bir child-ı var. Effektiv olaraq linked list.

### Tree Height Hesablamaq:
- `height(null) = 0` ya da `-1` (convention fərqli ola bilər — interview-da dəqiqləşdir).
- `height(node) = 1 + max(height(left), height(right))`.
- O(n) time, O(h) space (h = height, recursion stack).
- Post-order traversal — əvvəl alt-nəticəni, sonra üst-nəticəni hesabla.

### Diameter:
- Diameter: İstənilən iki node arasındakı ən uzun yol (root keçməyə bilər).
- `diameter through node = left_height + right_height` (edge sayı).
- Post-order traversal + global max saxlamaq.
- `max_diameter[0] = max(max_diameter[0], left_h + right_h)`.

### Balanced Check:
- Hər node üçün: `|height(left) - height(right)| <= 1`.
- Naive O(n²): hər node üçün height hesabla.
- Optimal O(n): Post-order-da height + balanced yoxlamasını birləşdir, -1 "unbalanced siqnalı" kimi qaytar.
- `-1` return etmək "kəsimlər" pattern-i — post-order kombinasiya.

### Path Sum:
- Root-dan leaf-ə gedən yolda cəm = target.
- Pre-order DFS: `remaining = target - node.val`, leaf-də `remaining == 0` yoxla.
- `has_path_sum(node, remaining) = has_path_sum(left, rem-val) or has_path_sum(right, rem-val)`.

### Lowest Common Ancestor (LCA):
- İki node-un ən yaxın ortaq əcdadı.
- Post-order: sol və sağdan hər ikisini tapdıqda — bu node LCA-dır.
- O(n) time.
- BST-də daha sürətli: BST property istifadə edərək O(h).

### Maximum Path Sum:
- İstənilən node-dan node-a gedən ən böyük cəm.
- Post-order: hər node üçün "bu node keçən max path" = `left_gain + node.val + right_gain`.
- Mənfi gain-i atla: `max(0, gain)` — mənfi cəm path-i pisləşdirir.
- Global max global variable-da saxla.

### Serialization/Deserialization:
- Binary tree-ni string-ə çevirmək (serialize) + geri bərpa etmək (deserialize).
- Pre-order + null markers: `"1,2,None,None,3,None,None"`.
- BFS-based serialize: Level-order, null-ları daxil et.

### Morris Traversal:
- O(1) space ilə in-order traversal — call stack yoxdur!
- Temporary link: current-in predecessor-ının right-ını current-ə bağla.
- Nadir amma interview-da "O(1) space in-order" soruşulduqda.

## Praktik Baxış

**Interview-a yanaşma:**
Binary tree sualı görəndə: (1) Traversal növü lazımdırmı — pre/in/post/level? (2) Global state lazımdırmı (max depth, max path sum) — nonlocal/class variable. (3) Return dəyəri nə olmalıdır — recursion çağırışından nə gəlir?

**Nədən başlamaq lazımdır:**
- Traversal şablonu seç: "Alt-nəticəni yuxarıya qaldırmaq lazımdır?" — post-order. "Yuxarıdan aşağıya state ötür?" — pre-order.
- Base case müəyyən et: `if not node: return ...` — null üçün nə qaytarmaq lazımdır?
- Post-order düşünmək: "Alt-problemlərin nəticəsindən üst-problemi necə qurum?"
- Global state: `nonlocal` ya class variable — Python-da `self.result = 0`.

**Follow-up suallar:**
- "Traversal-ı iterative yaz (stack/queue ilə)."
- "Ağacı serialize/deserialize et."
- "Mirror image (invert) et."
- "İki tree-nin eyni olduğunu yoxla."
- "Maximum path sum tapmaq (node-dan node-a, root keçməyə bilər)."
- "N-th level-in node-larını qaytar."
- "Vertical order traversal."

**Namizədlərin ümumi səhvləri:**
- Null node-u yoxlamadan `node.val` istifadəsi → NullPointerException.
- Post-order vs pre-order seçimini səhv etmək (diameter, balanced üçün post-order lazımdır).
- Global variable-ı düzgün idarə etməmək (Python-da `nonlocal` ya class variable).
- Space complexity-ni unutmaq: O(h) recursion stack — skewed tree-də O(n).
- "Height" terminini fərqli convention ilə işlətmək — interview başında dəqiqləşdir.
- Maximum path sum-da mənfi gain-i atlamağı unutmaq.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: Traversal-ı düzgün implement edir.
- Əla cavab: Traversal növünü niyə seçdiyini izah edir (pre/in/post/level), post-order-ın "bottom-up" tərəfini vurğulayır, space complexity-ni (height vs n) izah edir, iterative variantı da bəhs edir, global state idarəetməsini göstərir.

## Nümunələr

### Tipik Interview Sualı
"Binary tree-nin maximum depth-ini (hündürlüyünü) hesablayın. Nümunə: `[3,9,20,null,null,15,7]` → 3."

### Güclü Cavab
"Rekursiv post-order həlli: hər node üçün sol və sağ subtree-nin depth-ini hesabla, daha böyüyünü al, 1 artır. Base case: null node-un depth-i 0. T(n) = O(n) — hər node bir dəfə ziyarət olunur. Space: O(h) — recursion stack hündürlüyü qədər. Balanced tree-də O(log n), skewed tree-də O(n). Iterative variant: BFS (queue ilə level-order), level sayını say — bu daha intuitive ola bilər bəzi insanlar üçün, amma eyni komplekslik."

### Kod Nümunəsi
```python
class TreeNode:
    def __init__(self, val=0, left=None, right=None):
        self.val = val
        self.left = left
        self.right = right

# Max Depth — post-order recursion — O(n) time, O(h) space
def max_depth(root: TreeNode) -> int:
    if not root:
        return 0
    return 1 + max(max_depth(root.left), max_depth(root.right))

# Invert Binary Tree — pre-order — O(n)
def invert_tree(root: TreeNode) -> TreeNode:
    if not root:
        return None
    root.left, root.right = root.right, root.left   # swap əvvəlcə
    invert_tree(root.left)
    invert_tree(root.right)
    return root

# Level Order Traversal — BFS ilə — O(n) time, O(w) space
from collections import deque
def level_order(root: TreeNode) -> list[list[int]]:
    if not root:
        return []
    result = []
    queue = deque([root])
    while queue:
        level_size = len(queue)   # bu leveldə neçə node var
        level = []
        for _ in range(level_size):
            node = queue.popleft()
            level.append(node.val)
            if node.left:  queue.append(node.left)
            if node.right: queue.append(node.right)
        result.append(level)
    return result

# Is Balanced — O(n) post-order (optimal)
def is_balanced(root: TreeNode) -> bool:
    def check(node):
        if not node:
            return 0       # height 0
        left = check(node.left)
        right = check(node.right)
        if left == -1 or right == -1:
            return -1      # -1: unbalanced siqnalı — tez çıx
        if abs(left - right) > 1:
            return -1      # bu node balanced deyil
        return 1 + max(left, right)
    return check(root) != -1

# Diameter — O(n) post-order — global max
def diameter_of_binary_tree(root: TreeNode) -> int:
    max_diameter = [0]   # list — nonlocal alternative
    def height(node):
        if not node:
            return 0
        left_h = height(node.left)
        right_h = height(node.right)
        # Bu node-dan keçən path
        max_diameter[0] = max(max_diameter[0], left_h + right_h)
        return 1 + max(left_h, right_h)
    height(root)
    return max_diameter[0]

# Lowest Common Ancestor — O(n)
def lca(root: TreeNode, p: TreeNode, q: TreeNode) -> TreeNode:
    if not root or root == p or root == q:
        return root   # base case: null ya ya tapıldı
    left = lca(root.left, p, q)
    right = lca(root.right, p, q)
    if left and right:   # hər iki tərəfdə tapıldı — bu LCA
        return root
    return left or right  # yalnız bir tərəfdə

# Maximum Path Sum — O(n) post-order
def max_path_sum(root: TreeNode) -> int:
    result = [float('-inf')]
    def gain(node):
        if not node:
            return 0
        left_gain = max(0, gain(node.left))   # mənfi gain-i at
        right_gain = max(0, gain(node.right))
        # Bu node üçün path sum
        result[0] = max(result[0], node.val + left_gain + right_gain)
        # Parent-ə yalnız bir istiqamət qaytara bilərik
        return node.val + max(left_gain, right_gain)
    gain(root)
    return result[0]
```

### İkinci Nümunə — Serialize and Deserialize

**Sual**: Binary tree-ni string-ə serialize et, geri deserialize et.

**Cavab**: Pre-order traversal + null markers. Deserialize zamanı index-i artır.

```python
def serialize(root: TreeNode) -> str:
    vals = []
    def pre_order(node):
        if not node:
            vals.append('None')
            return
        vals.append(str(node.val))
        pre_order(node.left)
        pre_order(node.right)
    pre_order(root)
    return ','.join(vals)

def deserialize(data: str) -> TreeNode:
    vals = iter(data.split(','))
    def build():
        val = next(vals)
        if val == 'None':
            return None
        node = TreeNode(int(val))
        node.left = build()
        node.right = build()
        return node
    return build()
```

## Praktik Tapşırıqlar
- LeetCode #104: Maximum Depth of Binary Tree (Easy) — post-order. BFS ilə də sına.
- LeetCode #226: Invert Binary Tree (Easy) — pre-order.
- LeetCode #102: Binary Tree Level Order Traversal (Medium) — BFS. Level count.
- LeetCode #110: Balanced Binary Tree (Easy) — O(n) post-order. Naive O(n²) ilə müqayisə et.
- LeetCode #543: Diameter of Binary Tree (Easy) — global max. nonlocal vs class var.
- LeetCode #236: Lowest Common Ancestor (Medium) — post-order LCA.
- LeetCode #124: Binary Tree Maximum Path Sum (Hard) — post-order. Mənfi gain.
- LeetCode #297: Serialize and Deserialize Binary Tree (Hard). Pre-order + null markers.
- Özünütəst: Pre/in/post order-ın hər birini iterative stack ilə implement et.

## Əlaqəli Mövzular
- **Binary Search Tree** — binary tree-nin sıralı versiyası, BST property.
- **BFS and DFS** — level-order = BFS, diğər traversallar = DFS variantları.
- **Recursion** — tree traversal recursion-ın canonical tətbiqidir.
- **Heap / Priority Queue** — complete binary tree strukturuna əsaslanır.
- **Balanced BST** — AVL, Red-Black tree — balanced binary tree variantları.
