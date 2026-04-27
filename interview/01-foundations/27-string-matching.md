# String Matching Algorithms (KMP, Rabin-Karp) (Lead ⭐⭐⭐⭐)

## İcmal
String matching — text-dən pattern-in bütün (ya da birinci) occurrence-larını tapmaq problemidir. Naive yanaşma O(n*m), amma KMP (Knuth-Morris-Pratt) O(n+m)-ə endirir. Rabin-Karp rolling hash istifadə edərək O(n+m) average complexity verir, birdən çox pattern üçün də uyğundur. Aho-Corasick isə çoxlu pattern üçün KMP-nin generalizasiyasıdır. Bu alqoritmlər text editor, plagiarism detection, bioinformatics (DNA matching) kimi sahələrin əsasıdır.

## Niyə Vacibdir
KMP-nin failure function ideyası bir çox advanced alqoritmin qurulduğu fundamental anlayışdır. Aho-Corasick antivirus, network intrusion detection, axtarış motorlarında istifadə olunur. Rabin-Karp-ın rolling hash ideyası plagiarism detection sistemlərinin (TurnItIn kimi), substring hashing-in əsasıdır. Lead interview-larında: "text search engine-i optimal necə implement ederdiniz?" sualı bu alqoritmlərin seçim meyarlarını bilməyi tələb edir.

## Əsas Anlayışlar

### Naive String Matching — O(n*m)
- Hər mövqe üçün pattern-i tam müqayisə et
- n = text uzunluğu, m = pattern uzunluğu
- Ən yaxşı hal: O(n) (ilk simvolda mismatch)
- Ən pis hal: O(n*m) — `"aaa...a"` text, `"aaa...b"` pattern

### KMP (Knuth-Morris-Pratt)

**Əsas İdea**: Mismatch baş verdikdə text pointer-i geri çəkmə. Əvəzinə pattern-in prefix-suffix uyğunluğundan istifadə edərək pattern pointer-ini doğru mövqeyə qoy.

**Failure Function (Partial Match Table / LPS Array)**
`lps[i]` = `pattern[0..i]`-nin ən uzun proper prefix-i (özü olmadan) ki, həm də suffix-dir.
- "ABAB": `lps = [0, 0, 1, 2]`
- "AAAB": `lps = [0, 1, 2, 0]`
- "ABCABC": `lps = [0, 0, 0, 1, 2, 3]`

**LPS Necə Hesablanır**: O(m)
```
lps[0] = 0
j = 0  (prefix pointer)
for i in 1..m-1:
    while j > 0 and pattern[i] != pattern[j]:
        j = lps[j-1]  # backtrak
    if pattern[i] == pattern[j]:
        j += 1
    lps[i] = j
```

**KMP Search**: O(n)
```
j = 0  (pattern pointer)
for i in 0..n-1:
    while j > 0 and text[i] != pattern[j]:
        j = lps[j-1]  # mismatch → backtrak pattern-də
    if text[i] == pattern[j]:
        j += 1
    if j == m:
        # match tapıldı, mövqe: i - m + 1
        j = lps[j-1]  # növbəti match üçün
```

**Niyə O(n)?** `i` heç vaxt geri getmir, `j`-nin ümumi artımı = ümumi azalmasından çox ola bilməz → amortized O(n).

### Rabin-Karp — Rolling Hash

**Əsas İdea**: Pattern-in hash-ini hesabla. Text-in hər m-uzunluqlu substring-inin hash-ini rolling hash ilə O(1)-də yenilə. Hash uyğun gəldikdə tam müqayisə et.

**Rolling Hash Formula**:
`hash(s[i..i+m-1]) = (hash(s[i-1..i+m-2]) - s[i-1] * base^(m-1)) * base + s[i+m-1]`

Modulo ilə:
`hash = ((prev_hash - s[i-1] * h) * base + s[i+m-1]) % mod`

Burada `h = base^(m-1) % mod`.

**Complexity**:
- Average: O(n + m) — hash collision nadir
- Worst case: O(n*m) — çoxlu false positive (collision)
- Multiple patterns: O(n + Σm_i) — bütün pattern hash-lərini set-ə sal, O(1) lookup

**Double Hashing**: İki fərqli (base, mod) cütü istifadə et — collision ehtimalı çox azalır.

### Z-Algorithm — O(n+m)
- `z[i]` = `s[i:]` ilə `s[0:]`-ın ən uzun ortaq prefix-i
- KMP-yə alternativ, bəzi hallarda daha intuitiv
- Pattern + `$` + Text birləşdir, Z-array hesabla
- `z[i] == len(pattern)` → match

### Boyer-Moore — Praktik Ən Sürətli
- Sağdan sola müqayisə edir
- Bad character rule + Good suffix rule
- Best case: O(n/m) — uzun pattern, rare alphabet-də çox sürətli
- Worst case: O(nm)
- Praktikada grep, text editor-lar BM istifadə edir

### Aho-Corasick — Çoxlu Pattern

KMP-nin generalizasiyası: trie + failure links
1. Bütün pattern-ləri trie-yə insert et
2. KMP-nin failure function analoqu: trie-də failure links qur (BFS ilə)
3. Text-i bir dəfə scan et, bütün matches tap

**Complexity**:
- Build: O(Σ|pattern| * alphabet_size)
- Search: O(n + matches)
- Antivirus signature matching, grep-in çoxlu pattern variantında istifadə olunur

### String Hashing (General)
`hash(s) = Σ s[i] * base^i mod p`

Polynomial rolling hash:
- `base = 31` (lowercase letters), `p = 10^9 + 7`
- Prefix hash array ilə O(1) substring hash

**Collision probability**: ~1/p üçün hər cüt → double hash ilə ~1/p² → praktikada sıfır

### Longest Happy Prefix (KMP tətbiqi)
- String özünün hem prefix, həm də suffix olan ən uzun substring-i
- LPS array-in son dəyəri = cavab

## Praktik Baxış

### Interview Yanaşması
1. Single pattern, full match lazım → KMP
2. Multiple patterns → Aho-Corasick (ya da trie + KMP)
3. Substring hashing → Rabin-Karp
4. Practical grep-like search → Boyer-Moore (bilmək yaxşıdır)
5. LPS array-i explain etmək — KMP-nin interview-da ən çətin hissəsidir

### Nədən Başlamaq
- Naive approach söylə, problemi izah et
- KMP: LPS array-i misal üzərindəki qur
- Search fazasını LPS istifadə edərək izah et
- Complexity analysis: O(n+m), niyə O(n*m) deyil

### Ümumi Follow-up Suallar
- "LPS array-i niyə baxıb string-i yenidən tarayır?" (backtracking-siz O(n))
- "Rolling hash-da collision niyə baş verir? Necə azaltmaq olar?"
- "100 pattern-i eyni anda text-dən tapmaq lazımdır — optimal yanaşma?"
- "KMP vs Z-algorithm — fərq nədir?"

### Namizədlərin Ümumi Səhvləri
- LPS array-i düzgün hesablamamaq (edge case: `j = lps[j-1]` backtrak)
- Rolling hash-da overflow → Python-da problem yox, Java/C++-da `long` istifadə
- Rabin-Karp-da false positive-ləri tam müqayisə ilə yoxlamamaq
- KMP-nin search fazasında match tapıldıqda `j = lps[j-1]` etməyi unutmaq (overlapping matches)

### Yaxşı → Əla Cavab
- Yaxşı: KMP-ni implement edir, O(n+m) deyir
- Əla: LPS-nin arxasındakı ideyanı "prefix = suffix" olaraq intuitive izah edir, Rabin-Karp rolling hash formülünü türədir, Aho-Corasick-i bilir, Boyer-Moore-un praktik üstünlüyünü qeyd edir

## Nümunələr

### Tipik Interview Sualı
"LeetCode 28 — Find the Index of the First Occurrence in a String: `haystack`-dən `needle`-ı tapın. `strstr()` built-in-ini istifadə etmə. Optimal alqoritm tətbiq edin."

### Güclü Cavab
KMP alqoritmi ilə O(n+m) həll edərdim. Əvvəlcə `needle` üçün LPS (Longest Proper Prefix which is also Suffix) array-ini hesablayıram.

LPS-nin mənası: `lps[i]` = `needle[0..i]` substring-inin ən uzun proper prefix-i ki, həm də suffix-dir. Məsələn, `needle = "ABAB"` üçün `lps = [0,0,1,2]`.

Search fazasında: `haystack[i]` ilə `needle[j]` müqayisə edirəm. Mismatch olduqda `j = lps[j-1]` — needle pointer-ini geri çəkirəm, amma `i`-ni asla geri çəkmirəm. Bu sayədə hər simvol yalnız bir dəfə işlənir → O(n). LPS-nin hesablanması O(m). Total: O(n+m).

### Kod Nümunəsi
```python
def compute_lps(pattern):
    m = len(pattern)
    lps = [0] * m
    j = 0
    for i in range(1, m):
        while j > 0 and pattern[i] != pattern[j]:
            j = lps[j - 1]  # backtrak
        if pattern[i] == pattern[j]:
            j += 1
        lps[i] = j
    return lps

def kmp_search(text, pattern):
    if not pattern:
        return 0
    n, m = len(text), len(pattern)
    lps = compute_lps(pattern)
    matches = []
    j = 0
    for i in range(n):
        while j > 0 and text[i] != pattern[j]:
            j = lps[j - 1]
        if text[i] == pattern[j]:
            j += 1
        if j == m:
            matches.append(i - m + 1)
            j = lps[j - 1]  # overlapping match üçün
    return matches

# Rabin-Karp
def rabin_karp(text, pattern):
    n, m = len(text), len(pattern)
    if m > n:
        return []

    BASE, MOD = 31, 10**9 + 7
    h = pow(BASE, m - 1, MOD)  # BASE^(m-1) mod MOD

    def char_val(c):
        return ord(c) - ord('a') + 1

    # Pattern hash
    p_hash = 0
    for c in pattern:
        p_hash = (p_hash * BASE + char_val(c)) % MOD

    # Text-in ilk window hash-i
    t_hash = 0
    for c in text[:m]:
        t_hash = (t_hash * BASE + char_val(c)) % MOD

    matches = []

    for i in range(n - m + 1):
        if t_hash == p_hash:
            if text[i:i+m] == pattern:  # false positive yoxla
                matches.append(i)
        if i < n - m:
            # Rolling hash: sol simvolu çıxart, sağ simvolu əlavə et
            t_hash = (t_hash - char_val(text[i]) * h) % MOD
            t_hash = (t_hash * BASE + char_val(text[i + m])) % MOD
            t_hash = (t_hash + MOD) % MOD  # mənfi olmasın

    return matches

# Z-Algorithm
def z_algorithm(s):
    n = len(s)
    z = [0] * n
    z[0] = n
    l, r = 0, 0
    for i in range(1, n):
        if i < r:
            z[i] = min(r - i, z[i - l])
        while i + z[i] < n and s[z[i]] == s[i + z[i]]:
            z[i] += 1
        if i + z[i] > r:
            l, r = i, i + z[i]
    return z

def z_search(text, pattern):
    s = pattern + '$' + text  # '$' pattern-də olmayan simvol
    z = z_algorithm(s)
    m = len(pattern)
    return [i - m - 1 for i in range(m+1, len(s)) if z[i] == m]

# String Hashing — substring comparison O(1)
class StringHasher:
    def __init__(self, s, base=31, mod=10**9+7):
        n = len(s)
        self.mod = mod
        self.prefix = [0] * (n + 1)
        self.power = [1] * (n + 1)
        for i, c in enumerate(s):
            self.prefix[i+1] = (self.prefix[i] * base + ord(c)) % mod
            self.power[i+1] = self.power[i] * base % mod

    def get_hash(self, l, r):  # [l, r] inclusive, 0-indexed
        return (self.prefix[r+1] - self.prefix[l] * self.power[r-l+1]) % self.mod
```

## Praktik Tapşırıqlar
- LeetCode 28 — Find the Index of the First Occurrence
- LeetCode 459 — Repeated Substring Pattern (KMP tətbiqi)
- LeetCode 686 — Repeated String Match
- LeetCode 1392 — Longest Happy Prefix (LPS = cavab)
- LeetCode 1044 — Longest Duplicate Substring (binary search + Rabin-Karp)
- LeetCode 214 — Shortest Palindrome (KMP ilə)
- **Multi-pattern tapşırığı**: 100 keyword-in hər birini 1 GB text-dən tap. Aho-Corasick-i implement et
- **Sistem tapşırığı**: Plagiarism detection — iki document arasında 5+ sözdən ibarət ortaq phrase-ları tap

## Əlaqəli Mövzular
- **Trie** — Aho-Corasick trie + failure links-dir; trie-ni bilmək vacibdir
- **Hashing** — Rabin-Karp rolling hash-ın əsasıdır
- **Dynamic Programming** — LPS hesablanması DP ideyasına yaxındır
- **Bit Manipulation** — bəzi string comparison optimallaşmaları bitmask istifadə edir
- **Suffix Array / Suffix Tree** — daha ümumi string məsələləri üçün (LCS, LRS, ...)
