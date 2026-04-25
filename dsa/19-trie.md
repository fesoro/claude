# Trie (Senior)

## İcmal

**Trie** (prefix tree) — hərfli məlumatları (string-ləri) saxlamaq üçün ağac strukturudur. Hər node bir hərfi təmsil edir, kökdən hər node-a gedən yol bir prefix-i əmələ gətirir.

```
insert("cat"), insert("car"), insert("card"), insert("care")

       root
        |
        c
        |
        a
       / \
      t   r
     (*)  |
          d   e
         (*) (*)
```

`(*)` — word end marker.

---

## Niyə Vacibdir

Interview-lərdə Trie çox tez-tez soruşulur:
- Autocomplete / typeahead sistemi
- Spell checker
- IP routing (longest prefix match)
- Word search problemləri (Boggle, Word Search II)
- Prefix-based counting

---

## Əsas Anlayışlar

### TrieNode strukturu

```python
class TrieNode:
    def __init__(self):
        self.children = {}   # char → TrieNode
        self.is_end = False  # bu node söz sonu mu?
        # count = 0          # prefix sayı (optional)
```

### Trie operasyonları

| Operasiya | Time | Space |
|-----------|------|-------|
| insert    | O(L) | O(L)  |
| search    | O(L) | O(1)  |
| startsWith| O(L) | O(1)  |
| delete    | O(L) | O(1)  |

L = söz uzunluğu.

---

## Praktik Baxış

### Implementation (Python)

```python
class Trie:
    def __init__(self):
        self.root = TrieNode()

    def insert(self, word: str) -> None:
        node = self.root
        for ch in word:
            if ch not in node.children:
                node.children[ch] = TrieNode()
            node = node.children[ch]
        node.is_end = True

    def search(self, word: str) -> bool:
        node = self._find_node(word)
        return node is not None and node.is_end

    def starts_with(self, prefix: str) -> bool:
        return self._find_node(prefix) is not None

    def _find_node(self, prefix: str):
        node = self.root
        for ch in prefix:
            if ch not in node.children:
                return None
            node = node.children[ch]
        return node
```

### PHP Implementation

```php
class TrieNode {
    public array $children = [];
    public bool $isEnd = false;
}

class Trie {
    private TrieNode $root;

    public function __construct() {
        $this->root = new TrieNode();
    }

    public function insert(string $word): void {
        $node = $this->root;
        foreach (str_split($word) as $ch) {
            if (!isset($node->children[$ch])) {
                $node->children[$ch] = new TrieNode();
            }
            $node = $node->children[$ch];
        }
        $node->isEnd = true;
    }

    public function search(string $word): bool {
        $node = $this->findNode($word);
        return $node !== null && $node->isEnd;
    }

    public function startsWith(string $prefix): bool {
        return $this->findNode($prefix) !== null;
    }

    private function findNode(string $prefix): ?TrieNode {
        $node = $this->root;
        foreach (str_split($prefix) as $ch) {
            if (!isset($node->children[$ch])) return null;
            $node = $node->children[$ch];
        }
        return $node;
    }
}
```

### Trade-offs

| Müqayisə | Trie | HashMap |
|----------|------|---------|
| Prefix search | O(L) | O(n·L) |
| Memory | Yüksək (pointer overhead) | Aşağı |
| Exact search | O(L) | O(1) avg |
| Autocomplete | Natural | Mümkün deyil |

**Trie istifadə et**: prefix search, autocomplete, wildcard matching lazım olduqda.  
**HashMap istifadə et**: yalnız exact match lazım olduqda.

---

## Nümunələr

### Autocomplete (Prefix axtarışı)

```python
def autocomplete(trie: Trie, prefix: str) -> list[str]:
    """Verilmiş prefix ilə başlayan bütün sözlər."""
    results = []

    def dfs(node, current):
        if node.is_end:
            results.append(current)
        for ch, child in node.children.items():
            dfs(child, current + ch)

    node = trie._find_node(prefix)
    if node:
        dfs(node, prefix)
    return results
```

### Word Search II (LeetCode 212)

```python
def find_words(board, words):
    trie = Trie()
    for word in words:
        trie.insert(word)

    rows, cols = len(board), len(board[0])
    result = set()

    def dfs(node, r, c, path):
        ch = board[r][c]
        if ch not in node.children:
            return
        node = node.children[ch]
        path += ch
        if node.is_end:
            result.add(path)

        board[r][c] = '#'  # visited
        for dr, dc in [(0,1),(0,-1),(1,0),(-1,0)]:
            nr, nc = r + dr, c + dc
            if 0 <= nr < rows and 0 <= nc < cols and board[nr][nc] != '#':
                dfs(node, nr, nc, path)
        board[r][c] = ch  # restore

    for r in range(rows):
        for c in range(cols):
            dfs(trie.root, r, c, "")
    return list(result)
```

### Prefix Count (Leetcode 2185 variant)

```python
class CountTrie:
    def __init__(self):
        self.root = {}

    def insert(self, word):
        node = self.root
        for ch in word:
            node = node.setdefault(ch, {'_count': 0})
            node['_count'] += 1
        node['_end'] = True

    def count_prefix(self, prefix) -> int:
        node = self.root
        for ch in prefix:
            if ch not in node:
                return 0
            node = node[ch]
        return node.get('_count', 0)
```

---

## Praktik Tapşırıqlar

1. **LeetCode 208** — Implement Trie (əsas)
2. **LeetCode 211** — Design Add and Search Words Data Structure (wildcard `.`)
3. **LeetCode 212** — Word Search II (Trie + DFS)
4. **LeetCode 1268** — Search Suggestions System (autocomplete)
5. **LeetCode 648** — Replace Words (prefix replacement)

### Step-by-step: Wildcard Search

Wildcard `.` istənilən hərfə uyğun gəlir:

```python
def search_with_wildcard(node, word, i):
    if i == len(word):
        return node.is_end
    ch = word[i]
    if ch == '.':
        return any(
            search_with_wildcard(child, word, i + 1)
            for child in node.children.values()
        )
    if ch not in node.children:
        return False
    return search_with_wildcard(node.children[ch], word, i + 1)
```

---

## Ətraflı Qeydlər

### Memory optimallaşdırması

26 ingilis hərfi üçün `array[26]` daha sürətli, amma `dict` daha az yaddaş istifadə edir (sparse olduqda):

```python
# Array approach (26 hərfli əlifba üçün)
class TrieNode:
    def __init__(self):
        self.children = [None] * 26
        self.is_end = False

    def index(self, ch):
        return ord(ch) - ord('a')
```

### Compressed Trie (Radix Tree)

Tək-child zəncirləri birləşdirir, yaddaşı azaldır. Linux kernel-in routing table-ı bu strukturdan istifadə edir.

---

## Əlaqəli Mövzular

- [16-trees-basics.md](16-trees-basics.md) — Ağac əsasları
- [25-graphs-basics.md](25-graphs-basics.md) — Graph traversal (DFS/BFS)
- [38-advanced-string-algorithms.md](38-advanced-string-algorithms.md) — KMP, Aho-Corasick
- [15-string-algorithms.md](15-string-algorithms.md) — String pattern matching
