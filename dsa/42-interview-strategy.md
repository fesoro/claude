# Interview Strategy (Lead)

## Konsept (Concept)

Texniki müsahibədə **kod yazmağı bilmək kifayət deyil**. Müsahib sənin **problemə yanaşma tərzini**, **kommunikasiya bacarığını** və **trade-off düşüncəni** qiymətləndirir. Elə buna görə standart bir **problem-solving framework** faydalıdır — sıxıntı altında struktur saxlamağa kömək edir.

### UMPIRE Framework
- **U** — **Understand** (Problemi anla)
- **M** — **Match** (Tanıdığın pattern-lə uyğunlaşdır)
- **P** — **Plan** (Həlli planla, pseudo-code yaz)
- **I** — **Implement** (Kod yaz)
- **R** — **Review** (Kodu yoxla, edge case-ləri izlə)
- **E** — **Evaluate** (Zaman/yaddaş mürəkkəbliyini qiymətləndir, optimallaşdır)

### Niyə framework?
- **Stress-də donub qalmamaq** — növbəti addım aydın olur
- **Müsahibə heç bir şey söyləmədən başlamamaq** — "ağıllı görünmək" deyil, **proses göstərmək** vacibdir
- **Qismən kredit** — bütün kod yazılmasa belə, düzgün yanaşma sənə nail olur

## Necə İşləyir?

### 1. Understand (2-5 dəqiqə)
- **Problemi öz sözlərinlə təkrar et** — "Yəni sən deyirsən ki, mənə massiv verilir, mən ən uzun alt-sıra tapmalıyam, hansı ki..."
- **Nümunə üzərində əl ilə işlə** — bir-iki test case-i gözdən keçir
- **Clarifying sualları ver** (aşağıda siyahı)

### 2. Match (1-2 dəqiqə)
Problemin tanıdığın pattern-ə uyğun olub-olmadığına bax:
- "Ən uzun alt-sıra" → DP və ya sliding window?
- "K-cı ən böyük" → Heap, quickselect
- "Dəyişikliklər sayı az, query çox" → segment tree / BIT
- "Siklik olan graph" → DFS + visited set
- "Tree-də path sum" → DFS + backtracking

### 3. Plan (3-5 dəqiqə)
- **Kağızda (ya da ağ lövhədə) pseudo-code yaz**
- **Data strukturları qeyd et** — hansı lazımdır?
- **Vaxt mürəkkəbliyini təxmin et** — "brute force O(n²)-dir, amma binary search ilə O(n log n)-ə düşə bilər"
- **Müsahibdən təsdiq al** — "Bu yanaşma sənin üçün uyğundurmu?" (bu mühümdür!)

### 4. Implement (15-25 dəqiqə)
- **Yüksək səslə fikirləş** — "İndi for loop-da keçirəm, çünki..."
- **Məsələni kiçik hissələrə böl** — hər funksiya bir iş görsün
- **Dəyişən adları oxunaqlı olsun** — `i, j, k` yerinə `left, right, mid`

### 5. Review (3-5 dəqiqə)
- **Kodu sətir-sətir oxu** — özün üçün code review et
- **Off-by-one səhvlərini axtarma** — `<` yoxsa `<=`?
- **Null check-lər** — boş massiv, tək element?
- **Düzgün qaytarırsan?** — void yerinə data qaytarmalısan bəlkə

### 6. Evaluate (2-3 dəqiqə)
- **Time complexity** — worst, average, best
- **Space complexity** — nə qədər extra memory?
- **Optimize edə bilərəm?** — "O(n) space-ı O(1)-ə endirsəm olar, amma..."
- **Trade-off-lar** — "Bu həll sürətlidir, amma daha çox yaddaş istifadə edir"

## İmplementasiya (Implementation) - PHP

### Nümunə: "Two Sum" problemi üzərindən UMPIRE

**Problem**: Verilmiş massiv və `target` üçün iki indeks qaytar ki, `nums[i] + nums[j] == target`.

### 1. Understand

```php
/*
Clarifying suallar:
- Nümunə verə bilərsən? → nums=[2,7,11,15], target=9 → [0,1]
- Mənfi ədədlər ola bilər? → Bəli
- Duplicate dəyərlər? → Bəli, amma eyni indeksi iki dəfə istifadə etmək olmaz
- Həmişə bir həll varmı? → Bəli (məsələ şərti)
- Birdən çox həll varsa? → Hər hansını qaytar
- Massivin ölçüsü? → 2 ≤ n ≤ 10^4
*/
```

### 2. Match
- "İki element tap ki, cəmi target-ə bərabər olsun" → **Hash Map pattern** (`target - x`-i yoxla) və ya **Two Pointers** (sorted olsa)
- Massiv sorted deyil — Hash Map daha uyğundur

### 3. Plan

```
Pseudo-code:
  seen = {}                          // dəyər → indeks
  for i, x in nums:
    complement = target - x
    if complement in seen:
      return [seen[complement], i]
    seen[x] = i
  return []
```

Zaman: O(n), Yaddaş: O(n).

### 4. Implement

```php
/**
 * Hash Map yanaşması — O(n) zaman, O(n) yaddaş
 *
 * @param int[] $nums
 * @return int[]
 */
function twoSum(array $nums, int $target): array
{
    $seen = []; // value => index
    foreach ($nums as $i => $x) {
        $complement = $target - $x;
        if (isset($seen[$complement])) {
            return [$seen[$complement], $i];
        }
        $seen[$x] = $i;
    }
    return [];
}
```

### 5. Review — test et

```php
var_dump(twoSum([2, 7, 11, 15], 9));     // [0, 1]
var_dump(twoSum([3, 2, 4], 6));          // [1, 2]
var_dump(twoSum([3, 3], 6));             // [0, 1]  — duplicate
var_dump(twoSum([-1, -2, -3, -4], -7));  // [2, 3] — mənfi
var_dump(twoSum([1, 2], 10));            // []     — həll yoxdur
```

### 6. Evaluate
- **Zaman**: O(n) — bir dəfə keçirik
- **Yaddaş**: O(n) — worst case bütün elementlər hash map-də
- **Optimize edə bilərik?** — Sorted olsa O(1) yaddaş (two pointers), amma sort O(n log n)

### Clarifying suallarının checklist-i

```php
/**
 * Ümumi clarifying suallar — hər məsələdə ver
 */
function askClarifyingQuestions(): array
{
    return [
        // INPUT
        'Input tipi?'               => 'int[], string[], graph adjacency list?',
        'Ölçü məhdudiyyəti?'        => '1 ≤ n ≤ 10^5 olsa O(n^2) düşməz',
        'Dəyər aralığı?'            => 'Mənfi? 0 ola bilər? Çox böyük?',
        'Duplicates?'               => 'Təkrar dəyərlər ola bilərmi?',
        'Sorted?'                   => 'Massiv sıralıdır mı?',
        'Empty?'                    => 'Boş input ola bilərmi?',

        // OUTPUT
        'Output formatı?'           => 'Index? Value? Boolean?',
        'Birdən çox cavab?'         => 'Hər hansını qaytarım, yoxsa hamısını?',
        'Order vacibdirmi?'         => 'Sıralı qaytarım?',

        // CONSTRAINTS
        'Vaxt limiti?'              => 'Online judge 1 saniyə qəbul edir',
        'Yaddaş limiti?'            => 'In-place lazımdır?',
        'Modify input olar mı?'     => 'Məsələn sort etmək olar mı?',

        // EDGE CASES
        'Tək element?'              => '',
        'Bütün elementlər eyni?'    => '',
        'Overflow?'                 => 'int32 həddini aşa bilər?',
    ];
}
```

### Common Pattern Recognition

```php
function whichPattern(string $hint): string {
    return match (true) {
        str_contains($hint, 'longest subarray')    => 'Sliding Window',
        str_contains($hint, 'pair with sum')       => 'Two Pointers / Hash Map',
        str_contains($hint, 'sorted array search') => 'Binary Search',
        str_contains($hint, 'K-th largest')        => 'Heap / Quickselect',
        str_contains($hint, 'permutations')        => 'Backtracking',
        str_contains($hint, 'shortest path')       => 'BFS / Dijkstra',
        str_contains($hint, 'cycle in graph')      => 'DFS + visited',
        str_contains($hint, 'topological order')   => 'Topological sort',
        str_contains($hint, 'min/max range')       => 'Segment tree / Sparse table',
        str_contains($hint, 'optimal substructure')=> 'DP',
        str_contains($hint, 'local optimal works') => 'Greedy',
        default                                    => 'Brute force-dən başla',
    };
}
```

### Test Case Generation — tipik şablonlar

```php
$cases = [
    [[2, 7, 11, 15], 9, [0, 1]],        // happy path
    [[3, 3], 6, [0, 1]],                // duplicates
    [[-1, -2, -3, -4], -7, [2, 3]],     // negatives
    [[0, 4, 3, 0], 0, [0, 3]],          // zero pair
    [[1, 2], 10, []],                   // no solution
    [[5], 5, []],                       // single element
    [[], 0, []],                        // empty
];

foreach ($cases as [$nums, $target, $expected]) {
    $actual = twoSum($nums, $target);
    $ok = $actual === $expected ? 'PASS' : 'FAIL';
    echo "$ok " . json_encode($actual) . " vs " . json_encode($expected) . "\n";
}
```

### Trade-off müzakirə nümunəsi

Problem: `int[] $nums`-da duplicate varmı? Üç yanaşma:

```php
// 1) Brute — O(n^2) zaman, O(1) yaddaş
function hasDupBrute(array $n): bool {
    for ($i = 0; $i < count($n); $i++)
        for ($j = $i + 1; $j < count($n); $j++)
            if ($n[$i] === $n[$j]) return true;
    return false;
}

// 2) Sort — O(n log n) zaman, O(1) yaddaş (input dəyişir)
function hasDupSort(array $n): bool {
    sort($n);
    for ($i = 1; $i < count($n); $i++)
        if ($n[$i] === $n[$i - 1]) return true;
    return false;
}

// 3) Hash Set — O(n) zaman, O(n) yaddaş
function hasDupHash(array $n): bool {
    $seen = [];
    foreach ($n as $x) { if (isset($seen[$x])) return true; $seen[$x] = 1; }
    return false;
}
```

| Həll     | Zaman      | Yaddaş | Input dəyişir? |
|----------|------------|--------|----------------|
| Brute    | O(n²)      | O(1)   | Yox            |
| Sort     | O(n log n) | O(1)   | Bəli           |
| Hash Set | O(n)       | O(n)   | Yox            |

Məsləhət: "n nə qədər böyükdür?" və "yaddaş limiti?" sualı ilə başla.

## Vaxt və Yaddaş Mürəkkəbliyi

Bu fayl strategiya haqqındadır, amma müsahibədə trade-off vermək üçün baş-qayda cədvəli:

| Girişin ölçüsü (n)  | Qəbul olunan mürəkkəblik       |
|---------------------|--------------------------------|
| n ≤ 10              | O(n!)                          |
| n ≤ 20              | O(2^n)                         |
| n ≤ 500             | O(n^3)                         |
| n ≤ 5,000           | O(n^2)                         |
| n ≤ 10^6            | O(n log n)                     |
| n ≤ 10^8            | O(n)                           |
| n > 10^8            | O(log n) və ya O(1)            |

**PHP üçün qeyd**: PHP digər dillərə görə 3-5 dəfə yavaşdır, ona görə bu limitləri 2-3 dəfə endir.

## Tipik Məsələlər (Common Problems)

Müsahibədə tez-tez rast gəlinən problem tipləri:

1. **Array / String** — Two Sum, Reverse String, Valid Anagram
2. **Linked List** — Reverse, Cycle Detection, Merge K Lists
3. **Tree / Graph** — BFS, DFS, Lowest Common Ancestor, Diameter
4. **DP** — Longest Common Subsequence, Knapsack, Climbing Stairs
5. **Sliding Window** — Longest Substring Without Repeating Characters
6. **Backtracking** — N-Queens, Subsets, Permutations
7. **Binary Search** — Find Peak Element, Search in Rotated Array
8. **Heap** — K-th Largest, Merge K Sorted Lists, Top K Frequent
9. **Design** — LRU Cache, Twitter, Rate Limiter
10. **System-level** — URL Shortener, Chat System (bu **system design** sualdır)

## Interview Sualları

### 1. Müsahibəyə necə hazırlaşmısan?
**Cavab**: Strukturlu plan etmişəm:
- LeetCode Top 150 / NeetCode Roadmap
- Hər pattern üçün 5-10 problem
- Həftədə mock interview
- Sistem dizayn üçün "Designing Data-Intensive Applications", "System Design Interview" (Alex Xu)
- Behavioral üçün STAR formatında 10-15 hekayə

### 2. Problemi başa düşmədikdə nə edirsən?
**Cavab**: 
- **Susmuram** — müsahibin fikir prosesimi görməsi vacibdir
- Sualı **öz sözlərimlə təkrar edirəm**
- **Kiçik nümunə** yaradıb əl ilə işləyirəm
- Clarifying suallar verirəm — input, output, edge case
- "Bu fikir ağlıma gəldi, amma əminəm deyil, belə yanaşmaq olar mı?" — kömək istəyirəm açıq

### 3. Optimal həll tapa bilməzsən, nə edirsən?
**Cavab**: 
- **Brute force-lə başlayıram** — "heç olmasa bir işləyən həll olsun"
- Sonra "Bu O(n^2)-dir, necə endirə bilərəm?" sualını ucadan düşünürəm
- Pattern-ə uyğunlaşdıraraq (hash map, two pointers, DP) optimize edirəm
- Brute force 0 koddan yaxşıdır — **qismən kredit alırsan**

### 4. Bug tapdıqda nə edirsən?
**Cavab**: 
- **Panic etmirəm** — tam normal hadisədir
- Kodu sətir-sətir yoxlayıram, müəyyən bir test case ilə
- `echo` / `var_dump` istifadə edib vəziyyəti yüksək səsdə izah edirəm
- "Bunu yoxlamaq üçün `i=2, j=3` olduğu ana baxaq" — sistematik

### 5. Vaxt bitir və kod bitməyib, nə edirsən?
**Cavab**: 
- **Müsahibə xəbər verirəm** — "Əsas məntiq tamamdır, yalnız edge case qalıb"
- **Boşluqları pseudo-code** ilə doldururam — "burada null check lazımdır"
- **Yaxşı hissəni vurğulayıram** — "əsas alqoritm düzgündür"
- Heç bir halda **yalandan "bitdi"** demirəm

### 6. Çoxlu həll varsa, hansını yazmalısan?
**Cavab**: 
- **Müsahibdən soruş** — "Brute force-dən başlayım, yoxsa optimallaşdırılmış?"
- Adətən: **brute force-u sözlə izah et**, sonra **optimal versiyanı** kod yaz
- **Trade-off-ları izah et** — "Bu daha sürətlidir, amma daha çox yaddaş"

### 7. Behavioral sualdı "conflict" barəsində — STAR formatını göstər
**Cavab**: STAR = Situation, Task, Action, Result
- **Situation**: Köhnə layihədə frontend komandası API formatını dəyişdirmək istəyirdi
- **Task**: Mən backend tərəfdən production-da breaking change-dən yayınmalıydım
- **Action**: Meeting təşkil etdim, versioning (v1/v2) təklif etdim, migration plan yazdım
- **Result**: Hər iki komanda razı qaldı, 2 ayda tam migration oldu

### 8. Senin ən böyük texniki uğurun nədir?
**Cavab**: Real layihədən konkret nümunə — sayı, təsiri ölç. Məsələn:
- "Laravel layihədə N+1 query problemini eager loading ilə həll etdim"
- "Cavab müddəti 2.3s-dən 180ms-ə endi"
- "Aylıq 10k istifadəçiyə təsir göstərdi"
- "Öyrəndiklərimi komanda ilə technical writeup ilə bölüşdüm"

### 9. Bilmədiyin texnologiya haqqında soruşurlar, nə edirsən?
**Cavab**: 
- **Açıq-aydın de** — "bu texnologiyanı ətraflı bilmirəm"
- **Əlaqəli biliyini göstər** — "Redis istifadə etmişəm, Memcached ilə əsaslı fərqləri oxşar olmalıdır"
- **Öyrənməyə həvəsini göstər** — "maraqlıdır, sənədlərini gəzərdim"
- Qətiyyən **uydurmanı çalışma** — müsahib bunu həmin an tutur

### 10. Verdikləri vəzifə ilə mükafat uyğun deyilsə?
**Cavab**: 
- **Müsahibdən sonra**, HR/recruiter ilə danışarsan
- **Araşdırma et** — levels.fyi, Glassdoor ilə market median tap
- **Sübut gətir** — "Bu rol üçün market median X-dir, mənim 5 il təcrübəm var"
- **Alternativlər təklif et** — "Əgər baza çətindirsə, bonus və ya equity ola bilər"

## Common Pitfalls (Qarşısı Alınmalı Səhvlər)

1. **Dərhal koda keçmək** — Əvvəl anla, plan qur
2. **Susmaq** — Yüksək səslə fikirləş
3. **Clarifying suallar verməmək** — həmişə ver
4. **Edge case-ləri unutmaq** — boş, tək element, overflow
5. **Optimize etmədən "bitdi" demək** — trade-off həmişə müzakirə et
6. **Müsahibə aşağıdan baxmaq** — o sənin tərəfindədir, kömək istə
7. **"Bilmirəm" demək qorxaq görünmək** — dürüstlük daha yaxşıdır
8. **Variable adlarını `a, b, c` qoymaq** — oxunaqlılıq vacibdir
9. **Magic number-lər** — `86400` əvəzinə `SECONDS_IN_DAY`
10. **Test etməmək** — bitdikdən sonra kod üzərindən keç

## PHP/Laravel ilə Əlaqə

### Laravel backend müsahibəsinə xas mövzular

**Core**: Eloquent N+1 (`with`, `load`), Service Container / DI, Queue (sync/redis/database/sqs), Cache driver-lər, Middleware pipeline, Event/Listener/Observer, Form Request validation, Policy/Gate, API Resources, Migration+Seeder.

**DB performance**: N+1 həlli eager loading, `chunk()`/`chunkById()`/`lazy()` böyük dataset üçün, composite/covering index, `DB::listen()` query log, read replica, transactions və deadlock.

**Scale**: Horizontal scaling (load balancer + stateless), session Redis-də, queue worker horizontal, CDN + asset, hot/cold cache layer, sharding vs read replica.

### Sistem dizayn: "URL Shortener" — UMPIRE üzərindən

- **U**: 100M URL/gün, 7 char short, analytics var, custom alias ops, expiry 1 il
- **M**: Hash + Base62, Key-value storage (Redis + MySQL)
- **P**: `POST /shorten` → base62(hash(url)) → save; `GET /{short}` → cache lookup → 301
- **I**: Laravel route + controller + Redis cache + MySQL persistence
- **R**: Collision → salt retry; rate limit; auth for alias
- **E**: 100M/gün ≈ 1200 req/s avg, 12k peak; Redis hit 95% → DB 600 req/s; storage 100M×365×200B ≈ 7 TB/il

### Real müsahibə məsləhətləri (PHP developer üçün)

1. **Laravel "sehrindən" uzaq dur** — müsahib vanilla PHP kodu görmək istəyir
2. **SOLID prinsiplərini tətbiq et** — xüsusilə Service class-larında
3. **Repository pattern** bəzi komandalar sevir, bəziləri yox — soruş
4. **Test yazmağa hazır ol** — Feature test, Unit test fərqlərini bil
5. **Queue, Cache, Event** — bunlar yüksək performans suallarında lazımdır
6. **`toArray()` ilə Eloquent model arasında fərq** — memory baxımından vacib
7. **Immutable objects və value objects** — senior üçün gözlənilir
8. **Service Provider bindinq-ləri** — container-i dərindən anla

### Son söz: Mindset
- **Müsahibə rəqabət deyil, əməkdaşlıqdır** — müsahib sənə kömək edir
- **Səhv etmək normal** — vacib olan necə davrandığındır
- **Hər müsahibə təcrübədir** — rədd cavabı uğursuzluq deyil, öyrənmədir
- **Öz-özünə şübhə normaldır** — impostor syndrome çox təcrübəli developer-lərdə də var
- **Prosesi sev, nəticəni çox da vacib tutma** — rollar və şirkətlər dəyişir, bacarıqların qalır

---

## Praktik Tapşırıqlar

Mock interview məşqləri — hər birini 25 dəqiqə limiitilə həll et:

1. **LeetCode 1** — Two Sum (giriş — clarify, brute → optimal, complexity)
2. **LeetCode 206** — Reverse Linked List (data structure, edge cases: empty, single)
3. **LeetCode 102** — Binary Tree Level Order Traversal (BFS, verbal izah)
4. **LeetCode 200** — Number of Islands (DFS/BFS seçim, trade-off izah et)
5. **LeetCode 56** — Merge Intervals (sort decision, invariant izah)

### Step-by-step: UMPIRE metodu ilə Two Sum

```
U — Understand:
  "İki ədəd tapmaq lazımdır ki, onların cəmi target olsun"
  Suallar: duplicates ola bilərmi? həmişə bir cavab var?

M — Match:
  Array + cüt axtarış → Hash Map pattern

P — Plan:
  map = {}
  hər num üçün: complement = target - num
  əgər complement map-dədirsə → cavab tapıldı
  yoxdursa map[num] = index

I — Implement:
  (2-3 dəqiqədə yaz)

R — Review:
  [2,7,11,15], target=9:
  i=0(2): map={2:0}
  i=1(7): complement=2, map-dədir → [map[2],1]=[0,1] ✓

E — Evaluate:
  Time: O(n), Space: O(n)
  Edge case: [3,3], target=6 → işləyir (indeks fərqlidir)
```

---

## Əlaqəli Mövzular

- [40-complexity-cheatsheet.md](40-complexity-cheatsheet.md) — Complexity sürətli baxış
- [41-leetcode-patterns.md](41-leetcode-patterns.md) — Pattern recognition
- [01-big-o-notation.md](01-big-o-notation.md) — Complexity analizi əsasları
