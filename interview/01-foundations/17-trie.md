# Trie (Prefix Tree) (Senior ⭐⭐⭐)

## İcmal

Trie — string-ləri character-by-character saxlayan tree-based data structure-dur. Hər node bir simvolu təmsil edir; kökdən (root) node-a gedən yol bir prefix-i formalaşdırır. Autocomplete, spell checker, IP routing kimi real sistemlər trie-nin əsas tətbiqləridir. Interview-da trie sualları "niyə hash table əvəzinə trie?" sualına keçid edir — bu fərqi izah edə bilmək seniorluğun göstəricisidir.

## Niyə Vacibdir

Trie, string-intensive problemlərdə hash table-ın bacarmadığı şeyi edir: prefix-based axtarışı O(m) ilə həll edir (m — axtarılan sözün uzunluğu). Google, Amazon, Uber kimi şirkətlər autocomplete, search suggestion, phone directory kimi sistemlər quranda trie-dən istifadə edir. Interviewerlər bu sualı soruşanda namizədin "düzgün tool-u düzgün probleme" tətbiq edə bilmədiyini yoxlayırlar. Compressed trie, Aho-Corasick kimi variantları bilmək sizi average namizəddən fərqləndirir.

## Əsas Anlayışlar

### Trie Node Strukturu

```
Root
├── 'a' → TrieNode
│   ├── 'p' → TrieNode (is_end=False)
│   │   ├── 'p' → TrieNode (is_end=True)  ← "app"
│   │   └── 'l' → TrieNode
│   │       └── 'e' → TrieNode (is_end=True)  ← "apple"
└── 'b' → TrieNode
    └── 'a' → TrieNode
        └── 't' → TrieNode (is_end=True)  ← "bat"
```

- Hər node: `children` (map ya array), `is_end_of_word` flag
- `children` üçün iki variant:
  - `dict[char → TrieNode]` — sparse, az yaddaş, O(1) average access, wildcard üçün uyğun.
  - `TrieNode[26]` — dense, O(1) access, 26× daha çox yaddaş (yalnız lowercase ASCII üçün).
- `is_end` flag-i çox vacibdir: "app" axtaranda "apple"-ın prefix-inə görə false qaytarmamaq üçün.

### Əsas Əməliyyatlar və Complexity

| Əməliyyat | Time Complexity | Space |
|-----------|----------------|-------|
| Insert | O(m) | O(m) worst case (yeni node-lar) |
| Search (exact) | O(m) | O(1) |
| StartsWith (prefix) | O(m) | O(1) |
| Delete | O(m) | O(1) |
| Autocomplete | O(m + k) | O(k) — k nəticə sayı |

Burada m — söz/prefix-in uzunluğudur. n sözün ortalaması L olarsa, space: O(n * L) worst case, paylaşılan prefix-lər sayəsində əslində daha az.

### Trie vs Hash Table

| Kriteriya | Trie | Hash Table |
|-----------|------|-----------|
| Prefix search | O(m) — birbaşa | O(n*m) — hamısını gəz |
| Exact search | O(m) | O(1) amortized |
| Memory | Paylaşılan prefix-lər — effektiv | Hər söz tam saxlanır |
| Sorted iteration | O(n*m) DFS | Sort lazım: O(n log n) |
| Worst case | O(m) — deterministik | O(n) — hash collision |
| Prefix listing | Native | Mümkün deyil |

### Compressed Trie (Radix Tree / Patricia Tree)

- Tək child olan node-lar birləşdirilir: "inter" node-u → "view" və "nal" branch-ları.
- Nümunə: "interview" + "internal" → `[inter]` → `[view]` / `[nal]`.
- Space: O(n) — node sayı sözlər sayından artıq deyil.
- Nginx URL routing, Linux kernel IP routing-də istifadə olunur.
- Insertion daha mürəkkəb — node split lazımdır.

### Ternary Search Tree (TST)

- Hər node üç child: `left` (< char), `middle` (= char), `right` (> char).
- Trie-dən az yaddaş, hash table-dan daha yaxşı prefix support.
- BST-nin trie ilə hibridinə bənzəyir.
- Spell checker-lər üçün populyar.

### Suffix Trie / Suffix Array

- Sözün bütün suffix-lərini trie-yə əlavə et: "abc" → "abc", "bc", "c".
- Pattern matching: O(m) axtarış — text-də pattern-i tap.
- Suffix array — suffix trie-nin sıxılmış variantı, daha az yaddaş.
- Bioinformatika DNA axtarışında istifadə olunur.

### Aho-Corasick Alqoritmi

- Çoxlu pattern-ləri eyni anda text-də axtar.
- Trie + failure links (KMP alqoritminin generalizasiyası).
- Preprocessing: O(Σ pattern uzunluqları)
- Search: O(n + matches), n — text uzunluğu
- Antivirus software, firewall, plagiarism detection, log analysis-da istifadə olunur.
- Failure link: hazırkı node-dan mismatch olarsa hayana getmək lazımdır (KMP-dəki failure function kimi).

### Binary Trie (XOR Trie)

- Ədədlərin binary representation-larını saxlayır.
- "Array-dəki iki ədədin maksimum XOR-unu tap" → O(n * 32) ilə.
- `Maximum XOR of Two Numbers in Array` (LeetCode 421) — klassik trie problemi.
- Hər bit üçün 2 child: 0 və 1.

### Word Search / Wildcard Pattern

- `.` wildcard: trie-nin hər node-unda bütün children-i yoxla → DFS, O(m * 26^wildcard_count).
- `*` wildcard: daha mürəkkəb, regex engine-ə bənzər yanaşma, backtracking.
- Trie + DFS kombinasiyası: Word Search II kimi problemlər.

### Trie-də Delete Əməliyyatı

- **Sadə yanaşma**: `is_end_of_word = False` — node-ları saxla, həmin söz silinmiş sayılır.
- **Tam silmə**: Leaf node-lardan başlayaraq yuxarıya qədər sil (başqa söz ya prefix yoxdursa).
- Diqqətli olmaq lazım: "app" silinsə "apple" qalmalıdır. Node-u yalnız children yoxdursa sil.

### Real Sistem Trie-ləri

- **Autocomplete**: Frequency ilə trie — hər node-da top-K söz saxla (memory-heavy amma O(m) query). Alternativ: hər node-da min-heap.
- **DNS cache**: Domain hierarchy trie-yə uyğundur (`root` → `.com` → `google` → `www`).
- **IP routing**: Longest prefix match — router-lər binary trie istifadə edir (destination IP prefix).
- **Spell checker**: Edit distance + trie axtarışı (DFS + pruning).
- **T9 predictive text**: Çoxlu hərflər bir rəqəmə map olunur, trie ilə mümkün sözlər tapılır.

### Frequency-Based Autocomplete Dizayn

```
Hər node-da saxla:
- children map
- is_end flag
- top_k: min-heap (frequency, word) — k ölçülü

Insert zamanı:
- Bütün prefix node-larında top_k-ı yenilə.
- O(m * log k) insert.

Query zamanı:
- Prefix node-unu tap: O(m).
- top_k heap-i qaytar: O(k).
- Total: O(m + k log k) sort lazımdırsa.
```

## Praktik Baxış

### Interview Yanaşması

1. "String-lər üzərində prefix/autocomplete/search" → trie düşün.
2. `TrieNode` class-ını dizayn et: `children`, `is_end`. Sonra `Trie` class: `insert`, `search`, `startsWith`.
3. Recursive vs iterative — ikisini də göstər. Iterative daha oxunaqlı.
4. Space complexity haqqında danış: shared prefix-lərin faydası.
5. `children` üçün dict vs array — interview-da dict (əsasən lowercase), dict açıqla.

### Nədən Başlamaq

- `insert` → `search` → `startsWith` sırasıyla implement et.
- Test: "apple", "app", "application" — prefix-lər düzgün işləyirmi?
- Edge case: boş string, tək character, unicode (dict istifadə et array deyil).
- `is_end` flag-i hər zaman yoxla — sadəcə `_find_node(word) is not None` yetərli deyil.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Trie-ni serialize/deserialize edin" — BFS ilə JSON/string.
- "Top-K autocomplete suggestion-larını necə əldə edərsiniz?" — hər node-da frequency heap.
- "Çox böyük dictionary-ni memory-yə necə sığışdırarsınız?" — compressed trie, disk-based trie.
- "Case-insensitive search necə əlavə edərsiniz?" — lowercase normalize et.
- "Frequency-based ranking necə əlavə edərsiniz?" — node-da count saxla, heap/sorted order.
- "Trie-ni distributed sistemdə necə scale edərdiniz?" — prefix-ə görə sharding.

### Common Mistakes

- `is_end` flag-i unudmaq — "app" axtaranda "apple" prefix-inə görə `true` qaytarmaq.
- `children[26]` array-i initialize etməmək — `None` dolu array lazımdır.
- Delete əməliyyatında başqa sözləri pozmaq — uşaqları yoxlamadan node silmə.
- Trie vs hash table trade-off-larını izah edə bilməmək.
- Autocomplete-də yalnız `is_end=True` node-larını tapmamaq — prefix node-dan DFS lazımdır.
- Python-da `node.children.get(ch)` vs `ch in node.children` fərqini bilməmək.

### Yaxşı → Əla Cavab

- **Yaxşı**: Trie-ni düzgün implement edir, complexity bilir.
- **Əla**: Compressed trie-dən danışır, real dünyada autocomplete sistemi dizayn edir (distributed, frequency-based), Aho-Corasick-i bilir, memory footprint-i optimallaşdırır, binary trie (XOR problemi) üçün nümunə verir.

### Real Production Ssenariləri

- Google Search autocomplete: trie + frequency + personalization + geo.
- IDE autocomplete (IntelliJ, VS Code): trie + fuzzy matching.
- Linux `tab` completion: trie ilə filesystem yolları.
- Network router: IP prefix longest match → binary trie.
- Redis: Sorted Set-in lexicographic range query → trie kimi davranış.
- Elasticsearch: inverted index + trie prefix query.

## Nümunələr

### Tipik Interview Sualı

"LeetCode 642 — Design Search Autocomplete System: User yazarkən top-3 historical sentence suggestion verin, frequency-yə görə sıralanmış."

### Güclü Cavab

Bu sistemi trie + frequency count ilə implement edərdim. Hər trie node-unda həmin prefix-ə aid cümlələrin frequency map-ini saxlayıram. Yeni character gəldikdə trie-də həmin prefix-ə gedirəm, oradakı map-dən top-3-ü heap ilə seçirəm.

`#` gəldikdə (cümlə bitdi) — həmin cümləni trie-yə əlavə edirəm, bütün prefix node-larında frequency-ni artırıram.

Alternativ: Hər node-da `min-heap (k=3)` saxlamaq — real-time top-3 O(1) verir, amma insert O(m * log 3) = O(m) olur.

Trade-off: Frequency map-i node-da saxlamaq daha çox memory istifadə edir, amma query O(m) olur. Min-heap approach-da insert O(m) amma heap-ləri refresh etmək lazımdır.

### Kod Nümunəsi

```python
class TrieNode:
    def __init__(self):
        self.children: dict = {}
        self.is_end: bool = False

class Trie:
    def __init__(self):
        self.root = TrieNode()

    def insert(self, word: str) -> None:
        node = self.root
        for char in word:
            if char not in node.children:
                node.children[char] = TrieNode()
            node = node.children[char]
        node.is_end = True

    def search(self, word: str) -> bool:
        node = self._find_node(word)
        return node is not None and node.is_end  # is_end çox vacibdir!

    def starts_with(self, prefix: str) -> bool:
        return self._find_node(prefix) is not None

    def _find_node(self, prefix: str):
        node = self.root
        for char in prefix:
            if char not in node.children:
                return None
            node = node.children[char]
        return node

    def search_with_wildcard(self, word: str) -> bool:
        """'.' hər hansı bir simvolu ifadə edir"""
        def dfs(node, i):
            if i == len(word):
                return node.is_end
            ch = word[i]
            if ch == '.':
                return any(dfs(child, i + 1) for child in node.children.values())
            if ch not in node.children:
                return False
            return dfs(node.children[ch], i + 1)
        return dfs(self.root, 0)

    def autocomplete(self, prefix: str) -> list:
        """Prefix ilə başlayan bütün sözləri tap (DFS)"""
        node = self._find_node(prefix)
        if not node:
            return []
        results = []
        def dfs(node, path):
            if node.is_end:
                results.append(prefix + path)
            for char, child in node.children.items():
                dfs(child, path + char)
        dfs(node, "")
        return results

    def delete(self, word: str) -> bool:
        """Sözü trie-dən sil. True if deleted."""
        def _delete(node, word, depth):
            if depth == len(word):
                if not node.is_end:
                    return False   # söz yoxdur
                node.is_end = False
                return len(node.children) == 0   # node-u sil?
            char = word[depth]
            if char not in node.children:
                return False
            should_delete_child = _delete(node.children[char], word, depth + 1)
            if should_delete_child:
                del node.children[char]
                return not node.is_end and len(node.children) == 0
            return False
        return _delete(self.root, word, 0)

# Word Search II (Trie + DFS grid axtarışı) — O(m * n * 4^L)
def find_words(board: list[list[str]], words: list[str]) -> list[str]:
    trie = Trie()
    for w in words:
        trie.insert(w)

    rows, cols = len(board), len(board[0])
    result = set()

    def dfs(node, r, c, path):
        if node.is_end:
            result.add(path)
            node.is_end = False   # duplicate tapma üçün
        if r < 0 or r >= rows or c < 0 or c >= cols:
            return
        char = board[r][c]
        if char not in node.children or char == '#':
            return
        child = node.children[char]
        board[r][c] = '#'   # visited işarəsi
        for dr, dc in [(0,1),(0,-1),(1,0),(-1,0)]:
            dfs(child, r+dr, c+dc, path+char)
        board[r][c] = char  # restore (backtrack)
        # Optimization: dead branch-ı sil
        if not child.children and not child.is_end:
            del node.children[char]

    for r in range(rows):
        for c in range(cols):
            dfs(trie.root, r, c, "")
    return list(result)

# Binary Trie — Maximum XOR — O(n * 32)
class BinaryTrieNode:
    def __init__(self):
        self.children = [None, None]  # 0 və 1

class BinaryTrie:
    def __init__(self):
        self.root = BinaryTrieNode()

    def insert(self, num: int) -> None:
        node = self.root
        for i in range(31, -1, -1):   # MSB-dən LSB-ə
            bit = (num >> i) & 1
            if not node.children[bit]:
                node.children[bit] = BinaryTrieNode()
            node = node.children[bit]

    def max_xor(self, num: int) -> int:
        node = self.root
        xor = 0
        for i in range(31, -1, -1):
            bit = (num >> i) & 1
            want = 1 - bit   # ən böyük XOR üçün əks biti istəyirik
            if node.children[want]:
                xor |= (1 << i)
                node = node.children[want]
            else:
                node = node.children[bit]
        return xor

def find_maximum_xor(nums: list[int]) -> int:
    bt = BinaryTrie()
    for num in nums:
        bt.insert(num)
    return max(bt.max_xor(num) for num in nums)
```

### İkinci Nümunə — Frequency Autocomplete

```python
import heapq
from collections import defaultdict

class AutocompleteNode:
    def __init__(self):
        self.children = {}
        self.is_end = False
        self.freq = 0

class AutocompleteSystem:
    def __init__(self, sentences: list[str], times: list[int]):
        self.root = AutocompleteNode()
        self.current_input = ""
        for sentence, time in zip(sentences, times):
            self._insert(sentence, time)

    def _insert(self, sentence: str, freq: int) -> None:
        node = self.root
        for ch in sentence:
            if ch not in node.children:
                node.children[ch] = AutocompleteNode()
            node = node.children[ch]
        node.is_end = True
        node.freq += freq

    def input(self, c: str) -> list[str]:
        if c == '#':
            self._insert(self.current_input, 1)
            self.current_input = ""
            return []
        self.current_input += c
        node = self.root
        for ch in self.current_input:
            if ch not in node.children:
                return []   # prefix yoxdur
            node = node.children[ch]
        # DFS ilə bütün sözləri top-3-ə görə topla
        results = []
        def dfs(n, path):
            if n.is_end:
                results.append((-n.freq, path))  # min-heap üçün mənfi
            for ch, child in n.children.items():
                dfs(child, path + ch)
        dfs(node, self.current_input)
        results.sort()
        return [word for _, word in results[:3]]
```

## Praktik Tapşırıqlar

1. LeetCode #208 — Implement Trie (Prefix Tree) — klassik implementasiya. `children` üçün dict istifadə et.
2. LeetCode #211 — Design Add and Search Words Data Structure — wildcard `.`.
3. LeetCode #212 — Word Search II — trie + DFS grid. Pruning optimization əlavə et.
4. LeetCode #642 — Design Search Autocomplete System — frequency + trie.
5. LeetCode #421 — Maximum XOR of Two Numbers in Array — binary trie.
6. LeetCode #745 — Prefix and Suffix Search — double trie yanaşması.
7. LeetCode #677 — Map Sum Pairs — trie ilə prefix sum.
8. Design tapşırığı: Google search autocomplete sistemi dizayn et. Milyardlarla query, latency < 100ms, personalization, trending topics.
9. Özünü yoxla: Compressed trie implement et. Node sayının niyə azaldığını izah et.
10. Özünü yoxla: `is_end` flag-i olmadan trie-nin niyə düzgün işləmədiyini 2 nümunə ilə göstər.

## Əlaqəli Mövzular

- **String Matching (KMP, Aho-Corasick)** — Aho-Corasick trie + failure links-dir. KMP-nin generalizasiyası.
- **DFS/BFS** — Trie üzərindəki autocomplete/wildcard axtarışlar tree traversal-dır.
- **Hash Table** — Trie vs hash table trade-off interview-da tez-tez soruşulur.
- **Backtracking** — Word Search kimi problemlərdə trie + backtracking kombinasiyası.
- **Segment Tree** — Hər ikisi specialized tree, müxtəlif domain-lər.
- **Bit Manipulation** — Binary trie XOR problemi üçün bit operations istifadə edir.
