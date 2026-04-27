# Greedy Algorithms (Senior ⭐⭐⭐)

## İcmal

Greedy alqoritm hər addımda lokal optimal seçim edərək qlobal optimal həllə çatmağa çalışır. Geri qayıtmır (backtracking-dən fərqli), bütün imkanları yoxlamır (DP-dən fərqli). Bütün problemlər greedy ilə həll olmur — greedy-nin işləyəcəyini sübut etmək çox vacibdir. Interview-da greedy suallara "niyə bu seçim optimal-dır?" sualını cavablandırmaq bacarığı tələb olunur. Greedy-nin gözəlliyi: düzgün problemi üçün O(n log n) ya O(n) complexity verir — DP-nin O(n²)-dən çox effektiv.

## Niyə Vacibdir

Greedy alqoritmlər effektiv olduqda adətən O(n log n) və ya O(n) complexity verir — DP-nin O(n²) və ya O(n³)-ünə nisbətən əhəmiyyətli fərqdir. Real sistemlərdə: task scheduling (OS process scheduling), Huffman encoding (zip, gzip — data compression), network routing (Dijkstra, Prim greedy-dir), resource allocation. Google, Meta, Amazon kimi şirkətlər greedy-ni test edir, çünki namizədin problem haqqında dərin düşünüb-düşünmədiyini göstərir — greedy seçimin niyə düzgün olduğunu formal şəkildə əsaslandırmaq lazımdır.

## Əsas Anlayışlar

### Greedy Seçimin Düzgünlüyünü Sübut Etmək

İki klassik metod:

1. **Greedy stays ahead (Greedy irəlidədir)**: Hər addımda greedy həll digər hər hansı həlldən "az pisdir". Induction ilə: addım 0-da düzdür, addım k-da düzgünlük qorunur → son addımda da düzgündür.

2. **Exchange argument (Dəyişmə arqumenti)**: Optimal həllin greedy seçimdən fərqləndiyi yeri tap → greedy seçimi ilə swap et → nəticənin pisləşmədiyini göstər → greedy-nin optimal içindəkindən daha pis olmadığı sübut olunur.

Bu metodları bilmək interview-da sizi fərqləndirir. "Bu greedy işləyir" deməyin — niyə işlədiyini izah et.

### Klassik Greedy Problemlər

**Activity Selection (Interval Scheduling)**
- Mümkün qədər çox interval seçmək (overlapping olmadan).
- Greedy seçim: **Ən erkən bitən intervalı seç.**
- Niyə işləyir: Ən erkən bitən seçim sonrakı intervallar üçün maksimum boşluq saxlayır. Exchange argument: başqa interval seçsən, o da ən erkən bitəndən erkən bitmə — amma ən erkən bitən artıq ən qısadır, contradict.
- Complexity: O(n log n) sort + O(n) selection = O(n log n).
- **Tələ**: "Ən qısa interval" seçim düzgün deyil — counter-example: {[1,10], [2,3], [4,5]} — ən qısa [2,3] seçsən yalnız 2 interval, amma ən erkən bitən ilə [2,3] + [4,5] = 2 interval. Bu fərq azdır amma başqa vəziyyətdə [1,5], [3,4], [6,10] üçün ən qısa seçim 2 interval, ən erkən bitən 3 interval.

**Fractional Knapsack**
- Value/weight ratio-ya görə sırala, ən yüksəkdən başla.
- Fraksiyalı seçim mümkündür — kilo-kəsmə.
- **0/1 Knapsack greedy ilə həll olmur** — bu fərqi bilmək kritikdir.
- Fractional mümkün olduqda greedy optimal, 0/1-də DP lazımdır.

**Huffman Encoding**
- Frequency-yə görə variable-length codes.
- Min-heap: Ən az frequentli iki node-u birləşdir, heap-ə qaytar.
- Optimal prefix code — heç bir kod başqa kodun prefix-i deyil (prefix-free).
- Complexity: O(n log n).
- Tətbiq: ZIP, GZIP, PNG-nin DEFLATE alqoritmi.

**Dijkstra Alqoritmi (Greedy + Priority Queue)**
- "Hazırda bilinən ən qısa yollu vertex-i" seç — greedy seçim.
- Mənfi edge olmadıqda optimal.
- Niyə mənfi edge-də işləmir: "Finalized" node-un distansı sonra azala bilər — greedy invariant pozulur.

**Prim Alqoritmi (MST — Greedy)**
- Hər addımda ağacı ən ucuz edge ilə genişləndir.
- Kruskal da greedy: Global ən ucuz edge-i seç (cycle yaratmırsa).

**Jump Game**
- Hər mövqedən `nums[i]` addım atla bilərəm, sona çata bilərəmmi?
- Greedy: Hər indeksdə əldə edə biləcəyim maksimum mövqeyi izlə.
- `max_reach = max(max_reach, i + nums[i])`.
- O(n) bir pass — DP-nin O(n²)-dən çox üstün.

**Gas Station**
- Total gas >= total cost olarsa həmişə həll var (bu özü greedy insight-dır).
- Greedy: Ardıcıl gəz, tank mənfi olarsa başlanğıc nöqtəsini sıfırla.
- O(n), bir pass.

**Candy Distribution**
- Hər uşaq ən az 1 şəkər. Yüksək rating olan qonşusundan çox şəkər almalıdır.
- Two-pass greedy: Sol → sağ, sonra sağ → sol.
- O(n) time, O(n) space.

**Task Scheduler**
- CPU task-ları execute et, eyni task-lar arasında minimum n interval cooldown.
- Greedy: Ən frequent task-ı əvvəl seç.
- Math formula: `max(n + 1 - len(tasks), 0) * (max_count - 1) + count_of_max`.

### Greedy-nin İşləmədiyi Hallar

- **0/1 Knapsack**: Fraksiya yoxdur, DP lazımdır.
- **Coin Change** (ümumi): Bəzi coin sistemlərində greedy işləmir.
  - US coins (25, 10, 5, 1) üçün greedy işləyir.
  - `{1, 3, 4}` coins, target 6: greedy → 4+1+1=3 sikkə; optimal → 3+3=2 sikkə.
- **Shortest path (mənfi edge)**: Bellman-Ford lazımdır.
- **TSP (Traveling Salesman)**: NP-hard. Greedy "nearest neighbor" yaxınlaşma verir, optimal deyil.
- **Matrix Chain Multiplication**: DP lazımdır, greedy işləmir.

### Interval Problemlər (Greedy ilə)

- **Meeting Rooms II** (minimum otaq sayı): Min-heap + sort. Start-a görə sort, ən erkən bitən otağı reutilize et.
- **Non-overlapping Intervals**: Minimum silinəcək interval sayı — activity selection-un inversi. Ən erkən bitənləri saxla.
- **Merge Intervals**: Sort + scan — greedy birləşdirmə. End-i uzat ya növbəti interval əlavə et.
- **Partition Labels**: Hər simvolun son görüntüsünü izlə. Window-u son occurrence-ə qədər uzat.
- **Minimum Number of Arrows**: Overlapping balloon-ları minimum oxla partlatma. Activity selection variantı.

### Greedy ile DP Seçimi

| Greedy işləyir | DP lazımdır |
|----------------|-------------|
| Lokal seçim qlobal optimalı pozmir | Lokal seçim sonrakı imkanları məhdudlaşdırır |
| Optimal substructure + **Greedy choice property** | Yalnız Optimal substructure |
| Fractional Knapsack | 0/1 Knapsack |
| Activity Selection | Edit Distance |
| Huffman Encoding | Matrix Chain Multiplication |
| Dijkstra (non-negative) | Bellman-Ford (negative edge) |

### Greedy Choice Property (Formal)

Bir problem üçün greedy işləyir əgər: Lokal optimal seçim həmişə hansısa qlobal optimal həllin bir hissəsidir. Başqa sözlə, optimal həlldə greedy seçimi daxildir (ya da greedy seçimi ilə əvəz etmək mümkündür).

Bu property-ni sübut etmədən "greedy işləyəcək" demək interviewer-in gözündə zəiflikdir.

## Praktik Baxış

### Interview Yanaşması

1. Problemi brute force ilə düşün.
2. "Hansı lokal seçim qlobal optimal-a aparar?" sual ver.
3. Counter-example tap: Bu seçim hər zaman işləyirmi?
4. Greedy choice property-ni sözlü izah et (exchange argument ya stays-ahead).
5. Koddan əvvəl ən azı bir misalla trace et.
6. Əgər greedy işləmirsə → DP-yə keç. Bu keçidi bacarmaq da önəmlidir.

### Nədən Başlamaq

- Əvvəlcə sort strategy-ni müəyyən et. "Nəyə görə sıralamaq optimal seçimi asanlaşdırır?"
- "Hər addımda nə seçirəm, niyə bu seçim digərindən yaxşıdır?" sualını özünə ver.
- Edge case: Boş array, bir element, bütün eyni, negative values.
- Həmişə trace etmə: `nums = [2,1,1,2]` kimi kiçik input ilə sına.

### Follow-up Suallar (İnterviewerlər soruşur)

- "Bu greedy həll həmişə işləyirmi? Counter-example göstər."
- "DP ilə müqayisə et — space/time trade-off nədir?"
- "Coin change-i arbitrary coin sistemi üçün həll et" — DP-yə keçid siqnalı.
- "Bu alqoritmi distributed sistemdə necə istifadə edərdiniz?"
- "Greedy seçiminin optimal olduğunu necə sübut edirsiniz?"
- "Activity selection-da niyə ən qısa interval seçim yanlışdır?"

### Common Mistakes

- Greedy-nin işləyəcəyini sübut etmədən birbaşa kod yazmaq.
- Coin change-i greedy ilə həll etməyə çalışmaq (general case DP lazımdır).
- Activity selection-da "ən erkən başlayan" yerinə "ən qısa" seçmək (yanlışdır — nümunə göstər).
- Sort etməyi unutmaq — greedy-nin ilk addımı çox vaxt sort-dur.
- Heap-siz interval problem həll etməyə çalışmaq (meeting rooms II üçün heap optimal).
- "Greedy həmişə DP-dən sürətlidir" demək — yalnız greedy choice property varsa.

### Yaxşı → Əla Cavab

- **Yaxşı**: Doğru greedy seçim edir, kod yazır, complexity verir.
- **Əla**: Seçimin niyə optimal olduğunu exchange argument ilə sübut edir, greedy vs DP fərqini aydın izah edir, edge case-ləri əhatə edir, coin change (general) üçün "greedy işləmir çünki..." deyir, Huffman-ın optimal prefix code olduğunu bilir.

### Real Production Ssenariləri

- gzip/zip Huffman encoding — hər gün istifadə etdiyimiz texnologiya.
- Linux kernel CPU scheduler (CFS — Completely Fair Scheduler): greedy-based.
- Network routing protocols: OSPF Dijkstra işlədir.
- Cloud resource allocation: bin packing (greedy approximation).
- Database query optimizer: greedy join order estimation.

## Nümunələr

### Tipik Interview Sualı

"Verilmiş meeting interval-larını (start, end) nəzərə alaraq minimum sayda meeting room lazım olduğunu tapın."

### Güclü Cavab

Bu problemi min-heap ilə həll edərdim. Məntiq belədir: Meeting-lər başlanğıc vaxtına görə sıralanır. Hər meeting üçün mövcud otaqların bitiş vaxtlarını min-heap-də saxlayıram.

Yeni meeting başlayanda: Heap-in top-u (ən erkən boşalan otaq) bu meeting-in başlanğıcından əvvəl ya bərabərdirsə — həmin otağı yenidən istifadə et (top-u bu meeting-in bitiş vaxtı ilə əvəzlə). Yoxdursa — yeni otaq aç (heap-ə əlavə et). Cavab heap-in ölçüsüdür.

Niyə bu greedy seçim optimal-dır: Ən erkən boşalan otağı seçmək, digər otaqların mövcudluğunu sonrakı meeting-lər üçün maksimum saxlayır. Exchange argument: Əgər optimal həll fərqli otaq seçirsə, greedy ilə swap etsək heç nə pisləşməz (ən erkən boşalan otağı seçmişik).

Complexity: O(n log n) sort + O(n log n) heap əməliyyatları = O(n log n) total.

### Kod Nümunəsi

```python
import heapq
from typing import List

# Meeting Rooms II — minimum otaq sayı O(n log n)
def min_meeting_rooms(intervals: List[List[int]]) -> int:
    if not intervals:
        return 0
    intervals.sort(key=lambda x: x[0])   # start vaxtına görə sort
    heap = []  # min-heap, bitiş vaxtları saxlayır
    for start, end in intervals:
        if heap and heap[0] <= start:
            # Ən erkən boşalan otaq bu meeting üçün uyğundur
            heapq.heapreplace(heap, end)  # pop + push atomically
        else:
            heapq.heappush(heap, end)     # yeni otaq aç
    return len(heap)

# Activity Selection — maksimum interval sayı O(n log n)
def max_activities(intervals: List[List[int]]) -> int:
    # ən erkən bitən intervalı seç (greedy)
    intervals.sort(key=lambda x: x[1])   # bitiş vaxtına görə sırala
    count = 0
    last_end = float('-inf')
    for start, end in intervals:
        if start >= last_end:
            count += 1
            last_end = end
    return count

# Non-overlapping Intervals — minimum silinəcək O(n log n)
def erase_overlap_intervals(intervals: List[List[int]]) -> int:
    if not intervals:
        return 0
    intervals.sort(key=lambda x: x[1])   # ən erkən bitənləri saxla
    count = 0
    last_end = float('-inf')
    for start, end in intervals:
        if start >= last_end:
            last_end = end   # bu interval saxlanır
        else:
            count += 1       # bu interval silinir
    return count

# Merge Intervals — O(n log n)
def merge_intervals(intervals: List[List[int]]) -> List[List[int]]:
    intervals.sort(key=lambda x: x[0])
    result = []
    for start, end in intervals:
        if result and result[-1][1] >= start:
            result[-1][1] = max(result[-1][1], end)   # birləşdir
        else:
            result.append([start, end])
    return result

# Jump Game — O(n) bir pass
def can_jump(nums: List[int]) -> bool:
    max_reach = 0
    for i, jump in enumerate(nums):
        if i > max_reach:
            return False   # bu nöqtəyə çata bilmirik
        max_reach = max(max_reach, i + jump)
    return True

# Jump Game II — minimum addım sayı O(n)
def jump_game_ii(nums: List[int]) -> int:
    jumps = 0
    current_end = 0
    farthest = 0
    for i in range(len(nums) - 1):
        farthest = max(farthest, i + nums[i])
        if i == current_end:    # bu "level" bitdi
            jumps += 1
            current_end = farthest   # növbəti level sona qədər
    return jumps

# Partition Labels — O(n)
def partition_labels(s: str) -> List[int]:
    last_occurrence = {ch: i for i, ch in enumerate(s)}
    result = []
    start = end = 0
    for i, ch in enumerate(s):
        end = max(end, last_occurrence[ch])  # window-u son occurrence-ə qədər uzat
        if i == end:   # partition bitdi
            result.append(end - start + 1)
            start = end + 1
    return result

# Task Scheduler — O(n)
def least_interval(tasks: List[str], n: int) -> int:
    from collections import Counter
    freq = Counter(tasks)
    max_count = max(freq.values())
    count_of_max = sum(1 for v in freq.values() if v == max_count)
    # Formula: max_count - 1 frame, hər frame n+1 slot
    result = (max_count - 1) * (n + 1) + count_of_max
    return max(result, len(tasks))   # idle olmadan da bitə bilər

# Huffman Encoding — O(n log n)
def huffman_codes(char_freq: dict) -> dict:
    import heapq
    # heap: (frequency, char, code)
    heap = [(f, i, ch, "") for i, (ch, f) in enumerate(char_freq.items())]
    heapq.heapify(heap)
    counter = len(heap)

    while len(heap) > 1:
        f1, _, ch1, code1 = heapq.heappop(heap)
        f2, _, ch2, code2 = heapq.heappop(heap)
        # Birləşdir: sol → '0', sağ → '1'
        heapq.heappush(heap, (f1 + f2, counter, f"({ch1},{ch2})", ""))
        counter += 1
    return heap  # simplified — real impl kod assignment edir

# Fractional Knapsack — O(n log n)
def fractional_knapsack(weights: List[float], values: List[float], W: float) -> float:
    items = sorted(zip(values, weights), key=lambda x: x[0]/x[1], reverse=True)
    total_value = 0.0
    for value, weight in items:
        if W >= weight:
            total_value += value
            W -= weight
        else:
            total_value += value * (W / weight)   # fraksiyalı seçim
            break
    return total_value

# Gas Station — O(n)
def can_complete_circuit(gas: List[int], cost: List[int]) -> int:
    total_gas = sum(gas) - sum(cost)
    if total_gas < 0:
        return -1   # həll yoxdur
    tank = 0
    start = 0
    for i in range(len(gas)):
        tank += gas[i] - cost[i]
        if tank < 0:
            start = i + 1   # başlanğıc nöqtəsini sıfırla
            tank = 0
    return start
```

### İkinci Nümunə — Exchange Argument Nümunəsi

```python
# Interval Scheduling: Niyə "ən erkən başlayan" yanlışdır?
# Counter-example:
intervals = [[1, 10], [2, 3], [4, 5]]
# Ən erkən başlayan seçim: [1,10] → 1 interval
# Ən erkən bitən seçim: [2,3] → sonra [4,5] → 2 interval ✓

# Activity Selection-ı exchange argument ilə sübut:
# Optimal həll O* ilə greedy həll G eyni olmaya bilər.
# O*-da ən erkən bitən interval olmaya bilər.
# O*-dəki ilk intervali G-nin ilk intervali (ən erkən bitən) ilə swap et.
# Swap sonra O* ən az G qədər interval saxlayır (ən erkən bitən daha az yer tutur).
# → Greedy seçim optimal həlin içindəkindən daha pis deyil.

# Coin Change: Greedy işləmir (general case)
def coin_change_greedy_wrong(coins, amount):
    coins.sort(reverse=True)
    count = 0
    for coin in coins:
        while amount >= coin:
            amount -= coin
            count += 1
    return count if amount == 0 else -1

# coins = [1, 3, 4], amount = 6
# Greedy: 4 + 1 + 1 = 3 sikkə
# Optimal: 3 + 3 = 2 sikkə
# Niyə yanlış: Böyük coin seçmək sonraki seçimləri pisləşdirir.
# DP: dp[6] = min(dp[6-1]+1, dp[6-3]+1, dp[6-4]+1) = min(dp[5], dp[3], dp[2])+1
```

## Praktik Tapşırıqlar

1. LeetCode #55 — Jump Game — O(n) greedy. Can vs cannot reach ayrımını izah et.
2. LeetCode #45 — Jump Game II — minimum addım sayı. BFS vs greedy fərqi.
3. LeetCode #253 — Meeting Rooms II — heap ilə min otaq sayı.
4. LeetCode #435 — Non-overlapping Intervals — silmə miqdarını minimizə et.
5. LeetCode #621 — Task Scheduler — frequency formula.
6. LeetCode #763 — Partition Labels — last occurrence greedy.
7. LeetCode #406 — Queue Reconstruction by Height — iki kriteriya ilə greedy.
8. LeetCode #1710 — Maximum Units on a Truck — fractional knapsack variantı.
9. Sübut tapşırığı: Activity selection-da "ən qısa interval" greedy-nin işləmədiyini counter-example ilə göstər.
10. Design: File compression sistemi üçün Huffman-ı necə implement edərdin? Optimal prefix code olduğunu necə sübut edərdin?

## Əlaqəli Mövzular

- **Dynamic Programming** — Greedy işləmədikdə DP-yə keç. "Greedy choice property" varsa greedy, yalnız "optimal substructure" varsa DP.
- **Heap / Priority Queue** — Greedy alqoritmlərin əksəriyyəti heap istifadə edir (Huffman, Dijkstra, Prim, Meeting Rooms).
- **Sorting** — Greedy-nin ilk addımı çox vaxt sort-dur. Sort strategiyası greedy seçimi müəyyən edir.
- **Shortest Path (Dijkstra)** — Greedy alqoritm olan Dijkstra-nın niyə greedy olduğunu izah et.
- **Divide and Conquer** — Greedy ilə müqayisəli düşüncə. D&C müstəqil alt-problemlər üçün.
- **Graph Fundamentals** — Prim, Kruskal — MST greedy alqoritmlər.
