# Hash Table / Hash Map (Junior ⭐)

## İcmal
Hash table — key-value cütlərini saxlayan, average case O(1) insert/delete/search əməliyyatları təmin edən data structure-dur. Daxilindəki hash function key-i array index-inə çevirir. Interview-larda hash map ən çox istifadə olunan "optimization tool"-dur: O(n²) brute force-u O(n)-ə çevirmək üçün.

## Niyə Vacibdir
Hash map olmadan müasir proqramlaşdırmanın böyük hissəsini təsəvvür etmək çətindir: database index-ləri, caching (key-value), routing tables, word frequency count, anagram detection. Interview-larda "hash map-i bilirsənmi?" sualı demək olar ki, hər optimizasiya müzakirəsinin arxasında gizlənir. Əksər "Easy" LeetCode sualları hash map bilmədən real O(n) ilə həll edilə bilməz.

## Əsas Anlayışlar

### Daxili İşləmə Prinsipi:
1. **Hash function**: `key` → integer (hash code). İdeal olaraq uniform distribution.
2. **Modulo**: `hash_code % array_size` → array index.
3. **Array of buckets**: Hər bucket bir ya bir neçə key-value cütü saxlayır.
4. **Lookup**: `key` verilir, hash hesablanır, bucket-a get, key müqayisəsi.

### Collision (Toqquşma):
- İki fərqli key eyni bucket-a düşür.
- **Separate chaining**: Hər bucket-da linked list. Çox element düşərsə O(n) worst case.
- **Open addressing (linear probing)**: Dolu bucket-dan sonrakı boş yeri axtar.
- **Quadratic probing**: `+1, +4, +9...` — clustering-i azaldır.
- **Double hashing**: İkinci hash function ilə probe step müəyyənləşir.
- Java `HashMap`: Separate chaining, bucket uzunluğu 8-dən çox olduqda red-black tree-yə keçir (Java 8+).

### Load Factor:
- `load_factor = n / capacity` (n: elementlər, capacity: array ölçüsü).
- Yüksək load factor → daha çox collision → performans düşür.
- Java `HashMap` default load factor: 0.75. Bu nisbət keçildikdə resize (rehash).
- **Rehash**: Capacity 2× artırılır, bütün elementlər yeni array-ə yenidən yerləşdirilir — O(n).
- Python dict-in initial capacity 8, load factor ~0.67.

### Komplekslik:
- **Average case**: O(1) get/put/delete — bu hash map-in vaadı.
- **Worst case**: O(n) — bütün key-lər eyni bucket-a düşdükdə (hash function çox pisdirsə ya adversarial input).
- **Space**: O(n) — n element saxlamaq üçün.
- **Rehash**: Amortized O(1) — bəzən O(n) resize, amma nadir.

### Hash Function Xüsusiyyətləri:
- **Deterministic**: Eyni key həmişə eyni hash qaytarır.
- **Uniform distribution**: Bucket-lara bərabər paylayır.
- **Fast to compute**: O(1) idealdir.
- String hash: `hash = 0; for char in s: hash = 31 * hash + ord(char)` — Java `String.hashCode()` benzəri.
- Integer hash: Əksər dillərdə integer özü hash kimi istifadə olunur (identity hash).

### Java HashMap vs HashSet:
- `HashMap<K, V>`: Key-value cütləri saxlayır.
- `HashSet<K>`: Yalnız key-lər saxlayır (daxilindəki `HashMap`-ın value-su dummy object).
- `LinkedHashMap`: Insertion order-ı qoruyur. LRU cache implementasiyası üçün.
- `TreeMap`: Sorted order, O(log n) əməliyyatlar — iteration sorted sırada.
- `ConcurrentHashMap`: Thread-safe variant. `synchronized HashMap`-dən daha sürətli.

### Python dict:
- Python 3.7+ `dict` insertion order-ı qoruyur.
- `dict` CPython-da open addressing istifadə edir.
- `collections.defaultdict`: Default value ilə — `KeyError` olmadan.
- `collections.Counter`: Frequency counting üçün xüsusi dict. `most_common(k)` metodu.
- `collections.OrderedDict`: Explicit ordered dict (artıq nadir lazım olur).

### Hash Map İstifadə Nümunələri:
- **Frequency counting**: `{element: count}`.
- **Two Sum**: `{value: index}`.
- **Graph adjacency list**: `{node: [neighbors]}`.
- **Caching/Memoization**: `{args: result}`.
- **Grouping**: `{key: [items_with_key]}`.
- **Set operations**: Intersection, union O(n+m)-də.
- **Counting characters**: `{char: frequency}` — anagram, permutation.
- **First unique**: Frequency map, sonra birinci freq=1 olan.

### Əlaqədar Strukturlar:
- **Hash Set**: Yalnız unique key-lər, membership check O(1).
- **Bloom Filter**: Probabilistic membership check, false positive mümkün, false negative yox.
- **LRU Cache**: `HashMap` + doubly linked list birlikdə. O(1) get + O(1) put.
- **Trie**: String prefix-lər üçün xüsusi hash-like structure.

### Hash Map Dizayn Müsahibəsi:
- İnterviewer "hash map-i implement et" soruşa bilər.
- Array of linked lists: `self.buckets = [[] for _ in range(capacity)]`.
- `hash_idx = hash(key) % len(self.buckets)`.
- Resize: load factor keçildikdə yeni, 2x array yarat, rehash.

### Thread Safety:
- Python `dict` GIL sayəsında single-thread safe, amma multi-thread-da race condition mümkün.
- Java `HashMap` thread-safe deyil — `ConcurrentHashMap` istifadə et.
- PHP array single-threaded — thread safety nadir lazım olur.

## Praktik Baxış

**Interview-a yanaşma:**
Hash map düşünmə zamanı: "Hər elementi bir dəfə görəndə sonradan yenidən istifadə edəcəyəm?" — əgər bəli, hash map. "Unique sayımı lazımdır?" — hash set. "Frequency lazımdır?" — `Counter` ya `{key: count}`. "O(n²) nested loop-u var?" — hash map ilə O(n)-ə endir.

**Nədən başlamaq lazımdır:**
- Brute force həllini izah et (adətən nested loop, O(n²)).
- "Daxili loop nə axtarır?" soruşun.
- Həmin axtarışı hash map-ə köçür → O(1) lookup.
- Trade-off: O(n) extra space.
- Key nə olacaq? Value nə olacaq? — bu iki sual hash map dizaynını müəyyənləşdirir.

**Follow-up suallar:**
- "Hash collision nədir? Necə həll olunur?"
- "Worst case niyə O(n)-dir?"
- "Hash map vs balanced BST (TreeMap) — haçan hansını seçərdiniz?"
- "Thread-safe hash map necə implement edilir?"
- "LRU cache-i hash map istifadə edərək necə qurarsınız?"
- "Load factor nədir? Niyə vacibdir?"
- "Java 8-də HashMap-in tree-fication-ı nə vaxt baş verir?"

**Namizədlərin ümumi səhvləri:**
- Hash map-in worst case O(n) olduğunu unutmaq.
- Mutable object-i (array, list) key kimi istifadə etmək — Python-da error, çünki unhashable.
- `dict` iteration zamanı dəyişiklik etmək (RuntimeError).
- Integer overflow: Custom hash function yazarkən.
- `None`/`null` key-lərini xüsusi işləməmək.
- Counter update-də `seen[key] = seen.get(key, 0) + 1` əvəzinə `seen[key] += 1` — KeyError.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: Hash map istifadə edib O(n) həll yazır.
- Əla cavab: Hash function-ın necə işlədiyini, collision resolution strategiyasını, load factor-ı, amortized complexity-ni izah edir. "Niyə hash map hash set-dən daha çox yaddaş istifadə edir?" sualına cavab verir.

## Nümunələr

### Tipik Interview Sualı
"İki string-in anagram olduğunu yoxlayın. `s = "anagram"`, `t = "nagaram"` → true. Hərf sırası fərqli ola bilər, ancaq hərf sayları eyni olmalıdır."

### Güclü Cavab
"İki yanaşma düşünürəm: Birinci — hər iki stringi sort edib müqayisə etmək: O(n log n) time, O(n) space. İkinci — hash map ilə frequency count: s-dəki hər hərfi say, sonra t-dəki hər hərfi azalt, nəticə sıfır olmalıdır: O(n) time, O(k) space (k: unique hərflər, ASCII üçün k≤26, yəni O(1) space). İkinci yanaşma daha optimal. Edge case: uzunluqları fərqlidirsə — false."

### Kod Nümunəsi
```python
from collections import Counter, defaultdict

# Anagram Check — O(n) time, O(1) space (ASCII)
def is_anagram(s: str, t: str) -> bool:
    if len(s) != len(t):
        return False
    count = [0] * 26
    for c in s:
        count[ord(c) - ord('a')] += 1
    for c in t:
        count[ord(c) - ord('a')] -= 1
    return all(c == 0 for c in count)

# Counter ilə alternativ (Unicode-da da işləyir)
def is_anagram_counter(s: str, t: str) -> bool:
    return Counter(s) == Counter(t)

# Group Anagrams — O(n*k) time, k = max string length
def group_anagrams(strs: list[str]) -> list[list[str]]:
    groups = defaultdict(list)
    for s in strs:
        key = tuple(sorted(s))   # sort anagram key-i
        groups[key].append(s)
    return list(groups.values())

# Subarray Sum Equals K — prefix sum + hash map — O(n)
def subarray_sum(nums: list[int], k: int) -> int:
    count = 0
    prefix_sum = 0
    seen = {0: 1}   # prefix_sum → occurrence count
    for num in nums:
        prefix_sum += num
        # prefix_sum - k əvvəl görülübsə, o subarray var
        count += seen.get(prefix_sum - k, 0)
        seen[prefix_sum] = seen.get(prefix_sum, 0) + 1
    return count

# First Non-Repeating Character — O(n)
def first_unique_char(s: str) -> int:
    count = Counter(s)
    for i, c in enumerate(s):
        if count[c] == 1:
            return i
    return -1

# LRU Cache — HashMap + Doubly Linked List
class LRUCache:
    class Node:
        def __init__(self, key=0, val=0):
            self.key, self.val = key, val
            self.prev = self.next = None

    def __init__(self, capacity: int):
        self.cap = capacity
        self.cache = {}  # key → node
        self.head = self.Node()   # dummy head (MRU tərəfi)
        self.tail = self.Node()   # dummy tail (LRU tərəfi)
        self.head.next = self.tail
        self.tail.prev = self.head

    def _remove(self, node):
        node.prev.next = node.next
        node.next.prev = node.prev

    def _insert_front(self, node):
        node.next = self.head.next
        node.prev = self.head
        self.head.next.prev = node
        self.head.next = node

    def get(self, key: int) -> int:
        if key not in self.cache:
            return -1
        node = self.cache[key]
        self._remove(node)
        self._insert_front(node)   # most recently used → önə
        return node.val

    def put(self, key: int, value: int) -> None:
        if key in self.cache:
            self._remove(self.cache[key])
        node = self.Node(key, value)
        self.cache[key] = node
        self._insert_front(node)
        if len(self.cache) > self.cap:
            lru = self.tail.prev
            self._remove(lru)
            del self.cache[lru.key]   # hash map-dən sil
```

### İkinci Nümunə — Longest Consecutive Sequence

**Sual**: Sorted olmayan array-də ən uzun ardıcıl sequence-in uzunluğunu tapın. O(n) time. `nums = [100,4,200,1,3,2]` → 4 (`[1,2,3,4]`).

**Cavab**: Hash set-ə bütün ədədləri əlavə et. Hər ədəd üçün: yalnız sequence-in başlanğıcı isə (yəni `num-1` set-dədir) saya başla. Bu sayəsində hər sequence yalnız bir dəfə sayılır — O(n).

```python
def longest_consecutive(nums: list[int]) -> int:
    num_set = set(nums)   # O(n) space, O(1) lookup
    max_len = 0
    for num in num_set:
        if num - 1 not in num_set:   # sequence başlanğıcı
            current = num
            length = 1
            while current + 1 in num_set:   # sequence uzadır
                current += 1
                length += 1
            max_len = max(max_len, length)
    return max_len
# Total O(n) — hər element bir dəfə ziyarət olunur
```

## Praktik Tapşırıqlar
- LeetCode #1: Two Sum (Easy) — hash map əsas tətbiq. `{complement: index}`.
- LeetCode #49: Group Anagrams (Medium) — defaultdict, sorted key.
- LeetCode #560: Subarray Sum Equals K (Medium) — prefix sum + hash map.
- LeetCode #146: LRU Cache (Medium) — hash map + doubly linked list.
- LeetCode #387: First Unique Character in String (Easy) — Counter.
- LeetCode #128: Longest Consecutive Sequence (Medium) — hash set, O(n).
- LeetCode #242: Valid Anagram (Easy) — frequency count.
- LeetCode #350: Intersection of Two Arrays II (Easy) — Counter intersection.
- Özünütəst: Python `dict` thread-safe-dirmi? Multi-threaded mühitdə nə istifadə etmək lazımdır?

## Əlaqəli Mövzular
- **Array and String Fundamentals** — hash map array-i O(n²)-dən O(n)-ə optimize edir.
- **Linked List** — LRU cache doubly linked list + hash map birləşməsidir.
- **Two Pointers Technique** — bəzən hash map + two pointer birlikdə.
- **Dynamic Programming** — memoization hash map ilə implement olunur.
- **Graph Fundamentals** — adjacency list `HashMap<Node, List<Node>>` kimi təmsil olunur.
