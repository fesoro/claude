# Binary Search Tree (Middle ⭐⭐)

## İcmal
Binary Search Tree (BST) — hər node üçün sol subtree-dəki bütün dəyərlər node-dan kiçik, sağ subtree-dəki bütün dəyərlər böyük olan binary tree-dir. Bu property sayəsində axtarış, insert, delete əməliyyatları balanced BST-də O(log n)-dir. Interview-larda BST həm implementasiya, həm property-yoxlama, həm də in-order traversal gözləntiləri ilə çıxır.

## Niyə Vacibdir
BST anlayışı database index-lərinin (B-tree, B+ tree), set/map data structure-larının (Java `TreeMap`, C++ `std::map`) təməlini izah edir. "BST-dən k-th smallest element necə tapılır?", "In-order traversal niyə sorted qaytarır?" kimi suallar backend developer üçün vacibdir. Balanced BST (AVL, Red-Black) sənaye standartı data structure-larının arxasındadır.

## Əsas Anlayışlar

### BST Property:
- Hər node `x` üçün: `left subtree-dəki bütün dəyərlər < x.val < right subtree-dəki bütün dəyərlər`.
- Bu property bütün subtree-lər üçün geçərlidir — yalnız birbaşa child-lar üçün deyil.
- **Tələ**: `node.left.val < node.val` düzgündür, amma sol subtree-dəki bütün dəyərlər `< node.val` olmalıdır.
- Duplicate-lər: Adətən BST-də yoxdur, amma `<= left` ya `>= right` kimi extend edilə bilər.

### Əməliyyatlar:
- **Search**: O(h) — h = height. Balanced BST-də O(log n), skewed-da O(n).
- **Insert**: O(h). Yeni node həmişə leaf kimi əlavə olunur. BST property-ni qoru.
- **Delete**: O(h). Üç hal: leaf (birbaşa sil), bir child (child-ı yerə qoy), iki child (in-order successor ilə əvəzlə).
- **In-order traversal**: O(n) — sorted sıra verir. BST-nin ən vacib xüsusiyyəti.
- **Min/Max**: O(h) — ən sol / ən sağ node.
- **Floor/Ceiling**: O(h) — x-dən böyük olmayan / kiçik olmayan element.

### BST Delete — İki Child Halı:
- In-order successor (sağ subtree-nin ən kiçik elementi) ilə əvəzlə.
- Ya da in-order predecessor (sol subtree-nin ən böyük elementi).
- Successor tapıldıqdan sonra onu sil (o ya leaf, ya bir right child var).
- Successor-ın left child-ı olmur — BST property-dən.

### Balanced vs Unbalanced BST:
- Sorted array-i BST-yə sequential insert etsən: skewed tree (linked list kimi) → O(n) search.
- Balanced: height = O(log n), əməliyyatlar O(log n).
- Unbalanced: height = O(n), əməliyyatlar O(n).
- **AVL tree**: Hər insert/delete-dən sonra balancing (rotation). Strict balance. Search-heavy workload üçün.
- **Red-Black tree**: Daha yumşaq balans qaydaları. Insert/delete daha sürətli. Java `TreeMap`, Linux kernel istifadə edir.

### In-order Traversal = Sorted Output:
- BST-nin in-order traversal-ı (LNR: left → node → right) sorted sıra qaytarır.
- Bu property interview suallarında çox istifadə olunur: "K-th smallest element".
- BST-ni sorted array-ə çevirmək: in-order traversal → O(n) time, O(n) space.
- "İki BST-yi merge et": Hər birini in-order → iki sorted array-i merge → balanced BST.

### Validate BST:
- Sadə yanaşma (yanlış): Hər node üçün yalnız birbaşa child-larla müqayisə etmək.
- Yanlış nümunə: Root=10, left=5, left.right=15 → 15 < 10-dan böyükdür — invalid amma sadə check keçər.
- Doğru yanaşma: Min/max range keçirmək. Root üçün `(-∞, +∞)`, sol child üçün `(-∞, root.val)`, sağ child üçün `(root.val, +∞)`.

### BST-dən Array/Sorted List:
- In-order traversal ilə sorted array çıxarılır: O(n) time, O(n) space.
- In-order iterative: stack-la; morris traversal: O(1) space.
- Python-da generator kimi yield edilə bilər — memory efficient.

### BST Lower/Upper Bound:
- `floor(x)`: x-dən böyük olmayan ən böyük dəyər.
- `ceiling(x)`: x-dən kiçik olmayan ən kiçik dəyər.
- Binary search kimi navigate et, potensial cavabı yol boyunca izlə.
- Hər addımda "yaxşı cavab görüncə saxla, daha yaxşısını axtar".

### Successor/Predecessor:
- **In-order successor**: Sağ subtree varsa — sağ subtree-nin mini. Yoxdursa — yuxarı qalx, ilk "sola dönmə" nöqtəsi.
- **In-order predecessor**: Sol subtree varsa — sol subtree-nin max-ı.
- Interview-da: Parent pointer varsa daha asan; yoxdursa root-dan başlamaq lazımdır.

### BST Serialization:
- Pre-order traversal ilə BST-ni serialize etmək olar (null markers olmadan!). Çünki pre-order + BST property strukturu tam bərpa edir.
- Binary tree-dən fərqli olaraq — BST üçün daha az məlumat saxlanır.

### Augmented BST:
- Hər node-da əlavə məlumat saxlamaq: subtree ölçüsü, count, min/max.
- "K-th smallest in O(log n)": hər node-da subtree ölçüsü saxla, left.size+1 = rank.
- Order statistic tree (OS tree) adı verilir.

### BST nə vaxt istifadə etmək:
- Sorted data + sürətli insert/delete/search lazımdırsa → balanced BST (TreeMap/TreeSet).
- Yalnız axtarış lazımdırsa, data değişməyir → sorted array + binary search (cache-friendly).
- Frequency sayımı → hash map (O(1) average vs O(log n) BST).
- Range queries → segment tree ya balanced BST.

## Praktik Baxış

**Interview-a yanaşma:**
BST sualı üçün: "In-order traversal sorted qaytarır" — bu mexanizm çox sualı açır. "Validate" soruşulursa: range-keçirmə strategiyası. "Delete" soruşulursa: iki child halı üçün successor-ı hazırla.

**Nədən başlamaq lazımdır:**
- BST property-ni sözlü izah et.
- In-order traversal-ın sorted nəticə verdiyini qeyd et.
- Balanced vs unbalanced complexity fərqini vurğula.
- Edge case: boş tree, tək node, yalnız sol/sağ subtree.
- Delete-in üç halını sadalayın.

**Follow-up suallar:**
- "BST-ni validate edin. Yalnız parent-child müqayisəsi kifayətvarmı?"
- "K-th smallest element-i tapin."
- "BST-yə closest value olan element-i tapin."
- "İki BST-nin eyni olduğunu yoxlayin."
- "BST-ni balanced hala gətiriniz (sorted array-dən BST qur)."
- "In-order traversal-ı iterative (explicit stack) ilə yazın."
- "BST-nin n-th node-unu O(h) ilə tapın (augmented BST olmadan)."

**Namizədlərin ümumi səhvləri:**
- Validate BST-də yalnız `node.left.val < node.val` yoxlamaq — subtree bütünlüklə yanlış ola bilər.
- Delete — iki child halında successor-ı sildikdən sonra node.val-ı dəyişməyi unutmaq.
- BST search-in worst case O(n) olduğunu unutmaq (skewed tree).
- "Balanced BST" deyərkən AVL ilə Red-Black fərqini bilməmək.
- In-order successor taparkən parent pointer olmadıqda necə tapacağını bilməmək.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: BST property-ni bilir, search/insert işlədən kod yazır.
- Əla cavab: Validate BST-nin range keçirməsi mexanizmini izah edir, in-order = sorted əlaqəsini izah edir, balanced BST-nin niyə vacib olduğunu (O(n) worst case vs O(log n)) vurğulayır, sənaye-level tree-ləri (Red-Black, AVL) qeyd edir.

## Nümunələr

### Tipik Interview Sualı
"Bir binary tree-nin valid BST olub-olmadığını yoxlayın. Nümunə: `[2,1,3]` → true. `[5,1,4,null,null,3,6]` → false (4-ün sağ child-ı 3 < 5-dən kiçikdir)."

### Güclü Cavab
"Yalnız hər node-un birbaşa child-ları ilə müqayisəsi kifayət deyil — BST property bütün subtree üçün keçərlidir. Düzgün həll: min/max range keçirmək. Root üçün `(-∞, +∞)`. Sol child-ı çağıranda `max = root.val`. Sağ child-ı çağıranda `min = root.val`. Hər node üçün `min < node.val < max` yoxlanır. O(n) time, O(h) space."

### Kod Nümunəsi
```python
class TreeNode:
    def __init__(self, val=0, left=None, right=None):
        self.val = val
        self.left = left
        self.right = right

# Validate BST — range keçirmə
def is_valid_bst(root: TreeNode) -> bool:
    def validate(node, min_val=float('-inf'), max_val=float('inf')):
        if not node:
            return True
        if not (min_val < node.val < max_val):   # range yoxla
            return False
        return (validate(node.left, min_val, node.val) and    # sol üçün max = node.val
                validate(node.right, node.val, max_val))      # sağ üçün min = node.val
    return validate(root)

# BST Search — O(h)
def search_bst(root: TreeNode, val: int) -> TreeNode:
    if not root or root.val == val:
        return root
    if val < root.val:
        return search_bst(root.left, val)
    return search_bst(root.right, val)

# BST Insert — O(h)
def insert_into_bst(root: TreeNode, val: int) -> TreeNode:
    if not root:
        return TreeNode(val)      # leaf kimi əlavə et
    if val < root.val:
        root.left = insert_into_bst(root.left, val)
    else:
        root.right = insert_into_bst(root.right, val)
    return root

# BST Delete — O(h) — üç hal
def delete_node(root: TreeNode, key: int) -> TreeNode:
    if not root:
        return None
    if key < root.val:
        root.left = delete_node(root.left, key)
    elif key > root.val:
        root.right = delete_node(root.right, key)
    else:                        # bu node-u sil
        if not root.left:
            return root.right    # hal 1: sol child yoxdur
        if not root.right:
            return root.left     # hal 2: sağ child yoxdur
        # Hal 3: İki child — in-order successor tap
        successor = root.right
        while successor.left:    # sağ subtree-nin mini
            successor = successor.left
        root.val = successor.val          # successor ilə əvəzlə
        root.right = delete_node(root.right, successor.val)   # successoru sil
    return root

# K-th Smallest — in-order traversal — O(n) time, O(h) space
def kth_smallest(root: TreeNode, k: int) -> int:
    result = [0]
    count = [0]
    def inorder(node):
        if not node or count[0] >= k:
            return
        inorder(node.left)     # sol əvvəl
        count[0] += 1
        if count[0] == k:
            result[0] = node.val
            return
        inorder(node.right)    # sonra sağ
    inorder(root)
    return result[0]

# K-th Smallest — iterative stack — O(h) space
def kth_smallest_iterative(root: TreeNode, k: int) -> int:
    stack = []
    curr = root
    count = 0
    while curr or stack:
        while curr:
            stack.append(curr)
            curr = curr.left    # ən sola get
        curr = stack.pop()
        count += 1
        if count == k:
            return curr.val
        curr = curr.right       # sağa keç
    return -1

# Sorted Array → Balanced BST — O(n)
def sorted_array_to_bst(nums: list[int]) -> TreeNode:
    if not nums:
        return None
    mid = len(nums) // 2      # orta element root olur
    root = TreeNode(nums[mid])
    root.left = sorted_array_to_bst(nums[:mid])
    root.right = sorted_array_to_bst(nums[mid+1:])
    return root

# BST Range Sum — tək key deyil, [low, high] aralığı
def range_sum_bst(root: TreeNode, low: int, high: int) -> int:
    if not root:
        return 0
    total = 0
    if low <= root.val <= high:
        total += root.val
    if root.val > low:        # sol subtree-də daha kiçik ola bilər
        total += range_sum_bst(root.left, low, high)
    if root.val < high:       # sağ subtree-də daha böyük ola bilər
        total += range_sum_bst(root.right, low, high)
    return total
```

### İkinci Nümunə — Closest BST Value

**Sual**: BST-də verilən `target` float-a ən yaxın dəyəri tapın.

**Cavab**: Binary search kimi navigate et. Hər node-da closest-i yenilə. `target < node.val` isə sola get, əks halda sağa.

```python
def closest_value(root: TreeNode, target: float) -> int:
    closest = root.val
    node = root
    while node:
        # Daha yaxını tapıldımı?
        if abs(node.val - target) < abs(closest - target):
            closest = node.val
        # Axtarış istiqamətini seç
        if target < node.val:
            node = node.left
        elif target > node.val:
            node = node.right
        else:
            return node.val    # exact match
    return closest
# O(h) time, O(1) space
```

## Praktik Tapşırıqlar
- LeetCode #98: Validate Binary Search Tree (Medium) — range keçirməsi. Sadə müqayisəni niyə işləmədiyini izah et.
- LeetCode #700: Search in a BST (Easy) — basic BST search.
- LeetCode #701: Insert into a BST (Medium) — recursive insert.
- LeetCode #450: Delete Node in a BST (Medium) — üç hal. Successor tapma.
- LeetCode #230: Kth Smallest Element in BST (Medium) — in-order. Iterative variantını da yaz.
- LeetCode #108: Convert Sorted Array to BST (Easy) — balanced BST. Orta element root.
- LeetCode #235: LCA of BST (Medium) — BST property ilə optimize et. Binary tree LCA-dan fərqi?
- LeetCode #530: Minimum Absolute Difference in BST (Easy) — in-order + consecutive diff.
- Özünütəst: BST-nin in-order traversal-ını iterative (explicit stack) ilə yaz. Morris traversal-ı araşdır.

## Əlaqəli Mövzular
- **Binary Tree** — BST binary tree-nin special case-idir.
- **Binary Search** — BST axtarışı binary search-in tree versiyasıdır.
- **Balanced BST** — AVL, Red-Black tree — Java `TreeMap`, C++ `map`.
- **BFS and DFS** — BST traversal DFS (in/pre/post-order) istifadə edir.
- **Sorting Algorithms** — in-order traversal sayəsində BST-dən sorted sıra əldə olunur.
