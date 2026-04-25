# Advanced String Algorithms (Lead)

## Konsept (Concept)

Bu fayl **qabaqcıl string alqoritmlərini** əhatə edir:

1. **Z-algorithm** — pattern matching O(n+m)
2. **Manacher's algorithm** — ən uzun palindromik alt-sətir O(n)
3. **Suffix Array** — bütün suffix-ləri sort et, çoxsaylı query-lər üçün
4. **Suffix Automaton** — string-in bütün alt-sətirlərini dəstəkləyən minimal DFA
5. **Aho-Corasick** — eyni anda çoxlu pattern axtarışı

Sadə KMP və Rabin-Karp (fayl 25-də) əsasları əhatə edir; burada daha güclü alətlər.

## Necə İşləyir?

### Z-Algorithm
`Z[i]` = `s[i..n-1]` ilə `s[0..n-1]`-in ortaq ən uzun prefix uzunluğu. Hesablama O(n). Pattern matching: `P$T` düzəlt, Z array-ini hesabla, harada `Z[i] = |P|` olarsa — orada match var.

### Manacher
Hər mərkəz üçün maksimum palindrome radiusunu saxla. Simmetriya istifadə edərək təkrarlanan hesablamadan qaçır. O(n).

### Suffix Array
String-in bütün suffix-lərini leksikoqrafik sort et. LCP (Longest Common Prefix) massivi ilə birlikdə güclü alətdir.

### Aho-Corasick
KMP-in genişlənmiş variantı: bütün pattern-ləri Trie-də saxla, failure function-lar əlavə et. Time: O(n + m + z) (z = match sayı).

## İmplementasiya (Implementation) - PHP

### 1. Z-Algorithm

```php
function zFunction(string $s): array {
    $n = strlen($s);
    $z = array_fill(0, $n, 0);
    $l = $r = 0;
    for ($i = 1; $i < $n; $i++) {
        if ($i < $r) {
            $z[$i] = min($r - $i, $z[$i - $l]);
        }
        while ($i + $z[$i] < $n && $s[$z[$i]] === $s[$i + $z[$i]]) {
            $z[$i]++;
        }
        if ($i + $z[$i] > $r) {
            $l = $i;
            $r = $i + $z[$i];
        }
    }
    return $z;
}

// Pattern matching with Z
function zPatternMatch(string $text, string $pattern): array {
    $combined = $pattern . '$' . $text;
    $z = zFunction($combined);
    $m = strlen($pattern);
    $positions = [];
    for ($i = $m + 1; $i < strlen($combined); $i++) {
        if ($z[$i] === $m) $positions[] = $i - $m - 1;
    }
    return $positions;
}
```

### 2. Manacher's Algorithm

```php
// Ən uzun palindromik alt-sətir O(n)
function manacher(string $s): string {
    // "abc" → "^#a#b#c#$"
    if (empty($s)) return '';
    $t = '^';
    for ($i = 0; $i < strlen($s); $i++) $t .= '#' . $s[$i];
    $t .= '#$';

    $n = strlen($t);
    $p = array_fill(0, $n, 0);
    $c = $r = 0;
    for ($i = 1; $i < $n - 1; $i++) {
        $mirror = 2 * $c - $i;
        if ($i < $r) $p[$i] = min($r - $i, $p[$mirror]);
        while ($t[$i + 1 + $p[$i]] === $t[$i - 1 - $p[$i]]) {
            $p[$i]++;
        }
        if ($i + $p[$i] > $r) {
            $c = $i;
            $r = $i + $p[$i];
        }
    }

    $maxLen = 0;
    $centerIdx = 0;
    for ($i = 1; $i < $n - 1; $i++) {
        if ($p[$i] > $maxLen) {
            $maxLen = $p[$i];
            $centerIdx = $i;
        }
    }
    $start = intdiv($centerIdx - $maxLen, 2);
    return substr($s, $start, $maxLen);
}
```

### 3. Suffix Array (Sade O(n log² n))

```php
function buildSuffixArray(string $s): array {
    $n = strlen($s);
    $sa = range(0, $n - 1);
    $rank = [];
    for ($i = 0; $i < $n; $i++) $rank[$i] = ord($s[$i]);
    $tmp = array_fill(0, $n, 0);

    for ($k = 1; $k < $n; $k *= 2) {
        $cmp = function ($a, $b) use (&$rank, $k, $n) {
            if ($rank[$a] !== $rank[$b]) return $rank[$a] - $rank[$b];
            $ra = $a + $k < $n ? $rank[$a + $k] : -1;
            $rb = $b + $k < $n ? $rank[$b + $k] : -1;
            return $ra - $rb;
        };
        usort($sa, $cmp);
        $tmp[$sa[0]] = 0;
        for ($i = 1; $i < $n; $i++) {
            $tmp[$sa[$i]] = $tmp[$sa[$i-1]];
            if ($cmp($sa[$i-1], $sa[$i]) !== 0) $tmp[$sa[$i]]++;
        }
        $rank = $tmp;
        if ($rank[$sa[$n-1]] === $n - 1) break;
    }
    return $sa;
}

// Kasai's algorithm — LCP array O(n)
function buildLCP(string $s, array $sa): array {
    $n = strlen($s);
    $rank = array_fill(0, $n, 0);
    for ($i = 0; $i < $n; $i++) $rank[$sa[$i]] = $i;
    $lcp = array_fill(0, $n, 0);
    $h = 0;
    for ($i = 0; $i < $n; $i++) {
        if ($rank[$i] > 0) {
            $j = $sa[$rank[$i] - 1];
            while ($i + $h < $n && $j + $h < $n && $s[$i + $h] === $s[$j + $h]) $h++;
            $lcp[$rank[$i]] = $h;
            if ($h > 0) $h--;
        } else {
            $h = 0;
        }
    }
    return $lcp;
}
```

### 4. Aho-Corasick

```php
class AhoCorasick {
    private array $goto = [[]];
    private array $fail = [0];
    private array $output = [[]];
    private int $stateCount = 1;

    public function addPattern(string $pattern, int $id): void {
        $state = 0;
        for ($i = 0; $i < strlen($pattern); $i++) {
            $ch = $pattern[$i];
            if (!isset($this->goto[$state][$ch])) {
                $this->goto[$this->stateCount] = [];
                $this->output[$this->stateCount] = [];
                $this->fail[$this->stateCount] = 0;
                $this->goto[$state][$ch] = $this->stateCount++;
            }
            $state = $this->goto[$state][$ch];
        }
        $this->output[$state][] = $id;
    }

    public function build(): void {
        $queue = [];
        foreach ($this->goto[0] as $ch => $next) {
            $this->fail[$next] = 0;
            $queue[] = $next;
        }
        while (!empty($queue)) {
            $r = array_shift($queue);
            foreach ($this->goto[$r] as $ch => $u) {
                $queue[] = $u;
                $state = $this->fail[$r];
                while ($state !== 0 && !isset($this->goto[$state][$ch])) {
                    $state = $this->fail[$state];
                }
                $this->fail[$u] = $this->goto[$state][$ch] ?? 0;
                if ($this->fail[$u] === $u) $this->fail[$u] = 0;
                $this->output[$u] = array_merge(
                    $this->output[$u],
                    $this->output[$this->fail[$u]]
                );
            }
        }
    }

    public function search(string $text): array {
        $state = 0;
        $matches = [];
        for ($i = 0; $i < strlen($text); $i++) {
            $ch = $text[$i];
            while ($state !== 0 && !isset($this->goto[$state][$ch])) {
                $state = $this->fail[$state];
            }
            $state = $this->goto[$state][$ch] ?? 0;
            foreach ($this->output[$state] as $patId) {
                $matches[] = ['position' => $i, 'patternId' => $patId];
            }
        }
        return $matches;
    }
}

// İstifadə
$ac = new AhoCorasick();
$ac->addPattern('he', 0);
$ac->addPattern('she', 1);
$ac->addPattern('his', 2);
$ac->addPattern('hers', 3);
$ac->build();
print_r($ac->search('ushers'));
```

### 5. Suffix Automaton (Simplified)

```php
class SuffixAutomaton {
    public array $len = [0];
    public array $link = [-1];
    public array $trans = [[]];
    public int $last = 0;
    public int $size = 1;

    public function extend(string $c): void {
        $cur = $this->size++;
        $this->len[$cur] = $this->len[$this->last] + 1;
        $this->trans[$cur] = [];
        $this->link[$cur] = -1;
        $p = $this->last;
        while ($p !== -1 && !isset($this->trans[$p][$c])) {
            $this->trans[$p][$c] = $cur;
            $p = $this->link[$p];
        }
        if ($p === -1) {
            $this->link[$cur] = 0;
        } else {
            $q = $this->trans[$p][$c];
            if ($this->len[$p] + 1 === $this->len[$q]) {
                $this->link[$cur] = $q;
            } else {
                $clone = $this->size++;
                $this->len[$clone] = $this->len[$p] + 1;
                $this->link[$clone] = $this->link[$q];
                $this->trans[$clone] = $this->trans[$q];
                while ($p !== -1 && ($this->trans[$p][$c] ?? -1) === $q) {
                    $this->trans[$p][$c] = $clone;
                    $p = $this->link[$p];
                }
                $this->link[$q] = $clone;
                $this->link[$cur] = $clone;
            }
        }
        $this->last = $cur;
    }

    public function build(string $s): void {
        for ($i = 0; $i < strlen($s); $i++) $this->extend($s[$i]);
    }

    public function contains(string $t): bool {
        $state = 0;
        for ($i = 0; $i < strlen($t); $i++) {
            if (!isset($this->trans[$state][$t[$i]])) return false;
            $state = $this->trans[$state][$t[$i]];
        }
        return true;
    }
}
```

## Vaxt və Yaddaş Mürəkkəbliyi

| Alqoritm | Build | Query | Space |
|----------|-------|-------|-------|
| Z-algorithm | O(n) | O(n+m) match | O(n) |
| Manacher | O(n) | — | O(n) |
| Suffix Array | O(n log² n) / O(n log n) | O(m log n) substring | O(n) |
| LCP (Kasai) | O(n) | — | O(n) |
| Suffix Automaton | O(n) | O(m) substring check | O(n) |
| Aho-Corasick | O(Σ|P|) | O(n + z) | O(Σ|P|) |

## Tipik Məsələlər (Common Problems)

### 1. Longest Palindromic Substring (Manacher)
Yuxarıdakı `manacher()` funksiyası. LeetCode 5.

### 2. Pattern Matching with Wildcards (Z)
Pattern `a*bc` → hər "*" üçün ayrı match yoxla (və ya Z extended).

### 3. Count Distinct Substrings (Suffix Array / Automaton)
```php
function countDistinctSubstrings(string $s): int {
    $n = strlen($s);
    $sa = buildSuffixArray($s);
    $lcp = buildLCP($s, $sa);
    $total = intdiv($n * ($n + 1), 2);
    for ($i = 1; $i < $n; $i++) $total -= $lcp[$i];
    return $total;
}
```

### 4. Multiple Pattern Matching in Log File (Aho-Corasick)
Log-da 1000 virus imzasını bir paskalda tap. Aho-Corasick O(n + sum(|p|) + matches).

### 5. Shortest Common Superstring (Suffix Automaton)
Bütün string-ləri özündə saxlayan ən qısa string. Greedy + suffix automaton.

## Interview Sualları

**1. Z-algorithm KMP-dən necə fərqlənir?**
Hər ikisi O(n+m) pattern matching həll edir. Z sadə və yazması asandır — `P$T` düz birləşdirmə ilə işləyir. KMP `failure` funksiyası ilə işləyir, daha yaxşı yaddaş istifadəsi.

**2. Manacher niyə O(n)?**
Simmetriya istifadə edərək əvvəlki hesablamalardan bəhrələnir. Pointer `r` monoton olaraq artır — toplam iş O(n).

**3. Suffix Array-ın LCP massivi nə üçündür?**
`LCP[i]` = `suffix(sa[i-1])` və `suffix(sa[i])`-nin ortaq prefixi. Tətbiq: **distinct substrings sayını tapmaq, longest repeated substring, range min query**.

**4. Suffix Automaton nə saxlayır?**
String-in **bütün distinct alt-sətirlərini** qəbul edən minimum DFA. State sayı ≤ 2n-1.

**5. Aho-Corasick nə vaxt KMP-dən yaxşıdır?**
Çoxlu pattern (məs. 1000+) eyni mətn üzərində axtarılanda. KMP ilə `O(sum(m_i) + k·n)`; Aho-Corasick ilə `O(sum(m_i) + n + z)`.

**6. Z-algorithm-dən istifadə edərək LCP hesablamaq olarmı?**
Bəli, amma birbaşa deyil. Suffix array + Kasai daha sərfəlidir. Z əsasən online pattern matching üçündür.

**7. Manacher-də niyə separator char əlavə olunur?**
Tək və cüt uzunluqlu palindromları eyni şəkildə emal etmək üçün. `"aba"` → `"#a#b#a#"` — həmişə tək uzunluqda.

**8. Suffix Automaton vs Suffix Tree?**
- **Suffix Tree**: bütün suffix-ləri trie-də sıxlaşdır (Ukkonen O(n)), yazması çətin
- **Suffix Automaton**: daha yığcam (≤ 2n state), yazması nisbətən asan, eyni problemləri həll edir

**9. Aho-Corasick-də `fail` link nə üçündür?**
Current state-də mismatch olanda, ən uzun uyğun "suffix of path"-ə keç. KMP-nin failure function-ın trie üzərindəki variantıdır.

**10. Real dünyada Aho-Corasick?**
- Antivirus imza axtarışı
- Network intrusion detection (Snort)
- Spam filter — banned kəlmə siyahısı
- Bioinformatika — DNA motif search

## PHP/Laravel ilə Əlaqə

- **Full-text search**: PHP ilə sadə: `strpos`, `preg_match`. Miqyasda isə Elasticsearch/Meilisearch — onlar Aho-Corasick və suffix automaton-a bənzər strukturlardan istifadə edir.
- **Content moderation**: banned sözlər siyahısı — Aho-Corasick-in ideal tətbiqi.
- **Log analysis**: eyni anda 100+ error pattern axtarmaq — Aho-Corasick.
- **Laravel validator**: böyük input-larda palindrome/pattern yoxlanışı → Z və Manacher custom rule kimi istifadə oluna bilər.
- **Performance**: PHP string əməliyyatları C-də işlədiyi üçün sürətli. Amma mürəkkəb strukturlar (suffix automaton) PHP array-lərinin overhead-i səbəbindən C++/Go-dan yavaş olur — yüksək yüklü sistemlərdə xüsusi mikroservis.
