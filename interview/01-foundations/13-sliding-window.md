# Sliding Window Technique (Middle ⭐⭐)

## İcmal
Sliding Window — array/string üzərindəki davamlı subarray/substring-ləri O(n²)-dən O(n)-ə endirən texnikadır. Pəncərə (window) soldan sağa sürüşür: sağ tərəf genişlənir, sol tərəf lazım olduqda daralır. Fixed-size window (sabit ölçülü) və variable-size window (dəyişkən ölçülü) olmaqla iki variant var.

## Niyə Vacibdir
Sliding window "subarray/substring şərti" məsələlərinin standart həllidir. Max/min sum subarray, longest window with condition, minimum window substring — bu tip suallar Google, Amazon, Bloomberg texniki interview-larında çox çıxır. Backend developer kimi real layihələrdə: rate limiting (sliding window rate limiter), moving average, stream processing — bu texnika birbaşa tətbiq olunur.

## Əsas Anlayışlar

### Fixed-Size Window:
- Window ölçüsü `k` sabitdir.
- İlk `k` elementi ilə window-u yarat.
- Hər addımda: sağdan yeni element əlavə et, soldan köhnəni çıxart.
- Max sum subarray of size k, moving average, anagram find.
- O(n) time — hər element bir dəfə daxil, bir dəfə çıxır.

### Variable-Size Window:
- Window ölçüsü şərtə görə dəyişir.
- `right` pointer həmişə genişlənir.
- Şərt pozulduqda `left` pointer içəriyə gəlir (window daralır).
- Longest substring without repeating characters, minimum window substring.
- Şərt bərpa olunana qədər `left` irəliləyə bilər.

### Sliding Window Template (Variable):
```python
left = 0
window_state = {}  # ya set, ya ya counter, ya sum
result = 0

for right in range(len(s)):
    # sağdan element əlavə et — window genişlənir
    add(s[right], window_state)

    # şərt pozulduqda pəncərəni dar
    while invalid(window_state):
        remove(s[left], window_state)
        left += 1

    # şərt ödəndiyi anda nəticəni yenilə
    result = max(result, right - left + 1)   # ya ya min
```

### Window Genişlənmə vs Daralma:
- **Genişlənmə**: `right` sağa gedir, yeni element window-a daxil olur.
- **Daralma**: `left` sağa gedir, köhnə element window-dan çıxır.
- Şərt: Window-dakı elementlər müəyyən şərti ödəməlidir.
- "At most K distinct" suallarında `while distinct > k: left++`.

### Longest Substring Without Repeating Characters:
- Window: Tekrar olmayan hərflər.
- Hash set ilə window-u izlə.
- Tekrar gördükdə `left` pointer irəlilədir.
- O(n) time, O(k) space — k unique hərflər (ASCII üçün O(1)).
- Daha effektiv: Hash map `{char: last_index}` → `left = max(left, last_idx+1)`.

### Minimum Window Substring:
- `t` string-indəki bütün hərfləri ehtiva edən `s`-nin ən kiçik window-u.
- `need` counter-ı + `have` counter-ı.
- `formed` dəyişəni: neçə unique hərf tam ödəndi?
- Window `t`-nin bütün tələblərini ödədikdə nəticəni güncəllə, sonra `left` irəlilədir.
- O(n + m) time — n = len(s), m = len(t).

### Sliding Window Maximum (Monotonic Deque):
- Fixed-size window-da maximum tapmaq.
- Naive: O(nk). Optimal: O(n) — monotonic deque.
- Deque arxasında kiçik elementləri at (onlar artıq heç vaxt maximum ola bilməz).
- Deque önü həmişə window-dakı maximum-dur (index olaraq saxlanır).
- Window-dan çıxmış index-i önündən çıxart.

### At Most K Distinct Characters:
- "Ən çoxu K fərqli element saxlayan ən uzun subarray."
- Counter-da distinct element sayısı K-dan böyük olduqda `left` irəlilədir.
- Counter `[char] -= 1`, sıfıra düşəndə counter-dan sil.
- At most K → Exactly K: `atMost(K) - atMost(K-1)`.

### Subarray Sum Equals K:
- Müsbət ədədlər: Sliding window işləyir (sağa genişlən, sol daralt).
- **Mənfi ədədlər**: Sliding window işləmir (prefix sum + hash map lazımdır).
- Bu fərqi bilmək vacibdir — interview-da mütləq soruşulur!
- Mənfi ola bilərsə: "Sliding window işləmir çünki left-i irəlilətsəm sum azala bilər ya da artabilir."

### Rate Limiting — Real Tətbiq:
- Sliding window rate limiter: Son N saniyədəki request sayını izlə.
- Queue-da timestamp-ləri saxla, köhnəni çıxart, yenisini əlavə et.
- `len(queue) < limit` → allow; əks halda → reject.
- Redis-də: `ZADD` + `ZRANGEBYSCORE` + `ZREMRANGEBYSCORE`.

### Fixed-Size vs Variable Sliding Window Seçimi:
- "K ölçülü subarray-ın max/min/sum/average" → fixed-size.
- "Şərti ödəyən ən uzun/ən qısa subarray/substring" → variable.
- "Sabit window deyil, amma window-un ölçüsü artıb-azala bilər" → variable.

### Window State İdarəetməsi:
- **Sum**: `window_sum += nums[right]; window_sum -= nums[left]`.
- **Counter/Frequency**: `Counter(window)` ya `dict` — element əlavə/çıxarma.
- **Set**: Unique elementlər üçün. Dartma: set-dən çıxarma O(1).
- **Deque**: Maximum/minimum saxlamaq üçün monotonic deque.

### Shrinking Window Pattern:
- Bəzən window-u tam daraltmaq lazım olmur — yalnız bir addım.
- "Longest valid window at any point" — `left += 1` (əvvəlki answer-dan az ola bilməz).
- "Minimum window" — `while valid: try shrink`.

### Permutation in String:
- `s1`-nin hər hansı permutasiyası `s2`-də ardıcıl substring kimi mövcuddurmu?
- Fixed window ölçüsü = `len(s1)`.
- Window-dakı char frequency = `s1`-in char frequency → tapıldı.
- `Counter(s1) == Counter(window)` — O(k) müqayisəsi hər addımda.
- Daha effektiv: `matches` counter ilə O(1) güncəllə.

## Praktik Baxış

**Interview-a yanaşma:**
Sliding window siqnalları: "Subarray / substring", "contiguous", "longest / shortest / minimum", "at most K", "contains all". Bu sözlər görüldükdə sliding window düşün. Əvvəlcə "şərt nədir?" soruşun, sonra window state-i necə idarə edəcəyinizi müəyyən edin.

**Nədən başlamaq lazımdır:**
- Window state-i nə saxlayacaq: set, counter, sum, frequency map?
- Şərt nədir: `len(window) == k`, `sum == target`, `distinct chars <= k`?
- Daralma nə vaxt: Şərt pozulduqda.
- Nəticə nə vaxt hesablanır: Hər valid window-da (longest axtarırsansa), ya ya hər daralma öncəsindəmi?
- `right - left + 1` — window-un cari ölçüsü.

**Follow-up suallar:**
- "Mənfi ədədlər olsaydı sliding window işləyərmi?"
- "Sliding window rate limiter necə işləyir?"
- "Window maximum-unu O(1)-də saxlamaq üçün nə lazımdır?"
- "Bu həlli streaming data üçün adapt edə bilərsənmi?"
- "At most K → Exactly K çevirmə nə deməkdir?"
- "Sliding window vs prefix sum — haçan hansını seçərsiniz?"

**Namizədlərin ümumi səhvləri:**
- Mənfi ədədlər olan array-də sliding window tətbiq etmək (yalnız müsbətdə işləyir).
- Window daralarkən state-i doğru güncəllməmək (counter 0-a düşəndə delete etmək).
- Fixed-size window üçün ilk window-u ayrıca qurmaq əvəzinə sadece loop içindəki şərt ilə handle etmək (hər iki üsul işləyir).
- `right - left + 1` əvəzinə `right - left` yazmaq (off-by-one).
- Variable window-da `while` əvəzinə `if` istifadəsi — birdən çox addım lazım ola bilər.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: Window-u düzgün sürüşdürür, düzgün nəticə verir.
- Əla cavab: Fixed vs variable seçimini izah edir, mənfi ədəd məhdudiyyətini vurğulayır, monotonic deque-ni bilir, real-world tətbiqini (rate limiter) qeyd edə bilir, "Exactly K = atMost(K) - atMost(K-1)" trükünü bilir.

## Nümunələr

### Tipik Interview Sualı
"String `s` verilmişdir. Tekrarlanan hərf olmayan ən uzun substring-in uzunluğunu tapın. `s = "abcabcbb"` → 3 (`"abc"`). `s = "bbbbb"` → 1. `s = "pwwkew"` → 3 (`"wke"`)."

### Güclü Cavab
"Variable-size sliding window tətbiq edirəm. Window-u hash map ilə izləyirəm: `{char: last_index}`. `right` genişlənir; `s[right]` artıq window-dadırsa `left = max(left, last_idx + 1)`. Bu `left` üçün daha effektiv — set variant-da hərfi çıxarana qədər bir-bir irəliləyirdik, indi dərhal atlamaq olur. O(n) time, O(k) space — k = unique hərflər (ASCII üçün O(1)). Edge case: boş string → 0."

### Kod Nümunəsi
```python
# Longest Substring Without Repeating Chars — Hash Map variant — O(n)
def length_of_longest_substring(s: str) -> int:
    char_idx = {}   # {char: last index}
    left = 0
    max_len = 0
    for right, char in enumerate(s):
        if char in char_idx and char_idx[char] >= left:
            left = char_idx[char] + 1   # dərhal atla
        char_idx[char] = right
        max_len = max(max_len, right - left + 1)
    return max_len

# Maximum Sum Subarray of Size K — Fixed window — O(n)
def max_sum_subarray(nums: list[int], k: int) -> int:
    window_sum = sum(nums[:k])   # ilk window
    max_sum = window_sum
    for i in range(k, len(nums)):
        window_sum += nums[i] - nums[i - k]   # slide: əlavə et - çıxart
        max_sum = max(max_sum, window_sum)
    return max_sum

# Minimum Window Substring — O(n + m)
from collections import Counter
def min_window(s: str, t: str) -> str:
    if not t or not s:
        return ""
    need = Counter(t)           # tələb olunan
    have = {}                   # window-da olanlar
    formed = 0                  # neçə unique hərf tamamlandı
    required = len(need)        # neçə unique hərf tələb olunur
    left = 0
    min_len = float('inf')
    min_window_str = ""
    for right in range(len(s)):
        char = s[right]
        have[char] = have.get(char, 0) + 1
        if char in need and have[char] == need[char]:
            formed += 1         # bu hərf tam ödəndi
        while formed == required:    # valid window — daralt
            if right - left + 1 < min_len:
                min_len = right - left + 1
                min_window_str = s[left:right+1]
            # sol tərəfdən daralt
            left_char = s[left]
            have[left_char] -= 1
            if left_char in need and have[left_char] < need[left_char]:
                formed -= 1    # bu hərf artıq tam deyil
            left += 1
    return min_window_str

# Sliding Window Maximum — Monotonic Deque — O(n)
from collections import deque
def max_sliding_window(nums: list[int], k: int) -> list[int]:
    result = []
    dq = deque()   # index-lər saxlanır (decreasing values)
    left = 0
    for right in range(len(nums)):
        # arxadan kiçik elementləri çıxart (onlar artıq maximum ola bilməz)
        while dq and nums[dq[-1]] < nums[right]:
            dq.pop()
        dq.append(right)
        # window-dan çıxmış index-i önündən çıxart
        if dq[0] < left:
            dq.popleft()
        # window tamamlandıqda nəticə əlavə et
        if right >= k - 1:
            result.append(nums[dq[0]])   # dq[0] həmişə maximum
            left += 1
    return result

# Longest Subarray with at most K zeros — Variable window
def longest_ones(nums: list[int], k: int) -> int:
    left = 0
    zeros = 0
    max_len = 0
    for right in range(len(nums)):
        if nums[right] == 0:
            zeros += 1
        while zeros > k:         # şərt pozuldu → dar
            if nums[left] == 0:
                zeros -= 1
            left += 1
        max_len = max(max_len, right - left + 1)
    return max_len

# Permutation in String — Fixed window + Counter match — O(n)
def check_inclusion(s1: str, s2: str) -> bool:
    if len(s1) > len(s2):
        return False
    need = Counter(s1)
    window = Counter(s2[:len(s1)])
    if window == need:
        return True
    for i in range(len(s1), len(s2)):
        # Sağdan əlavə et
        window[s2[i]] += 1
        # Soldan çıxart
        left_char = s2[i - len(s1)]
        window[left_char] -= 1
        if window[left_char] == 0:
            del window[left_char]
        if window == need:
            return True
    return False
```

### İkinci Nümunə — Longest Repeating Character Replacement

**Sual**: String-də ən çoxu k hərf dəyişdirərək eyni hərflərdən ibarət ən uzun substring tapın. `s = "AABABBA", k = 1` → 4 (`"AABA"` → `"AAAA"`).

**Cavab**: Sliding window. Window ölçüsü - ən çox görülən hərf sayı ≤ k olduqca valid. Bunu pozmadan window-u sürüşdür.

```python
def character_replacement(s: str, k: int) -> int:
    count = {}
    left = 0
    max_freq = 0   # window-dakı ən çox görülən hərf sayı
    result = 0
    for right in range(len(s)):
        count[s[right]] = count.get(s[right], 0) + 1
        max_freq = max(max_freq, count[s[right]])
        # window_size - max_freq > k → k-dan çox dəyişiklik lazımdır
        if (right - left + 1) - max_freq > k:
            count[s[left]] -= 1
            left += 1   # bir addım daralt — əvvəlki max-ı keçmədikdə result artmaz
        result = max(result, right - left + 1)
    return result
# O(n) time, O(1) space (26 hərif = constant)
```

## Praktik Tapşırıqlar
- LeetCode #3: Longest Substring Without Repeating Characters (Medium) — klassik variable window.
- LeetCode #76: Minimum Window Substring (Hard) — counter + formed. Ən çətin sliding window.
- LeetCode #239: Sliding Window Maximum (Hard) — monotonic deque. Deque-nun məntiqini izah et.
- LeetCode #567: Permutation in String (Medium) — fixed window + anagram check.
- LeetCode #643: Maximum Average Subarray I (Easy) — fixed window sum.
- LeetCode #424: Longest Repeating Character Replacement (Medium) — max_freq trick.
- LeetCode #1004: Max Consecutive Ones III (Medium) — at most K zeros. Variable window.
- LeetCode #209: Minimum Size Subarray Sum (Medium) — variable window + sum condition.
- Özünütəst: Rate limiter-i sliding window ilə implement et (timestamp queue). Redis-də necə edilir?

## Əlaqəli Mövzular
- **Two Pointers Technique** — sliding window two pointers-ın xüsusi tətbiqidir (left/right).
- **Hash Table / Hash Map** — window state-i saxlamaq üçün counter/map.
- **Stack and Queue** — sliding window maximum üçün monotonic deque.
- **Array and String Fundamentals** — sliding window array/string manipulation texnikasıdır.
- **Dynamic Programming** — bəzən DP mövzusuna sliding window alternativi ola bilir (max subarray).
