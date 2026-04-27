# Stack and Queue (Junior ⭐)

## İcmal
Stack — LIFO (Last In, First Out) prinsipilə işləyən linear data structure-dur: son əlavə edilən element ilk çıxarılır. Queue — FIFO (First In, First Out): ilk əlavə edilən element ilk çıxarılır. Bu iki data structure interview-larda həm birbaşa (implementation, edge case), həm dolayı (BFS, DFS, expression evaluation, monotonic patterns) olaraq çıxır.

## Niyə Vacibdir
Stack və Queue kompüter elminin əsas abstraksiyalarıdır: call stack (rekursiya), undo/redo, browser history, job queue, BFS level-order traversal — hamısı bu strukturlara əsaslanır. Interview-larda "monotonic stack" sualları (daily temperatures, next greater element) senior-a baxmaya başladıqca tez-tez çıxır. Meta, Amazon, Google-da bu mövzu həm data structure dizaynı, həm alqoritm sualları kontekstində işlənir.

## Əsas Anlayışlar

### Stack:
- **Push**: Üstə element əlavə et — O(1).
- **Pop**: Üstdən element çıxart — O(1).
- **Peek/Top**: Üstdəki elementi bax, çıxartma — O(1).
- **isEmpty**: Boş olub-olmadığını yoxla — O(1).
- Implementation: Array (dynamic) ya da linked list üzərindən.
- Python: `list` — `append()` push, `pop()` pop. Built-in stack.
- Java: `Deque<Integer> stack = new ArrayDeque<>()` — `Stack` class legacy, `ArrayDeque` preferred.
- Real use cases: function call stack, undo/redo, expression parsing, DFS, browser back button.

### Queue:
- **Enqueue**: Arxaya element əlavə et — O(1).
- **Dequeue**: Önündən element çıxart — O(1).
- **Front/Peek**: Önündəki elementi bax — O(1).
- Implementation: Linked list (O(1) her iki əməliyyat) ya da circular array.
- Python: `collections.deque` — `append()` enqueue, `popleft()` dequeue.
- Java: `Queue<Integer> q = new LinkedList<>()` — `offer()` enqueue, `poll()` dequeue.
- Real use cases: BFS, task/job queue, print queue, message broker.

### Deque (Double-Ended Queue):
- Həm önə, həm arxaya O(1) əlavə/silmə.
- Stack + Queue-nun birləşməsi.
- Sliding window maximum problemlərində istifadə olunur.
- Python: `collections.deque`. Java: `ArrayDeque<Integer>`.

### Monotonic Stack:
- Stack-in daima artan ya azalan sırada saxlandığı variant.
- **Monotonic increasing stack**: Növbəti kiçik element tapılır. Stack artan sırada.
- **Monotonic decreasing stack**: Növbəti böyük element tapılır. Stack azalan sırada.
- "Next Greater Element", "Daily Temperatures", "Largest Rectangle in Histogram" bu pattern.
- Hər element bir dəfə push, bir dəfə pop — O(n) total.

### Stack ilə Rekursiya Simulyasiyası:
- Hər rekursiv alqoritm iterative stack ilə yazıla bilər.
- Call stack overflow halında explicit stack daha güvenlidir.
- DFS adətən stack (explicit ya da call stack via recursion) istifadə edir.
- Tree pre-order iterative: root-u stack-ə at, pop et, sağ+sol push et.

### İki Stack ilə Queue:
- İki stack-dən queue qurmaq — məşhur interview sualı.
- `stack_in` + `stack_out`: enqueue `stack_in`-ə push, dequeue `stack_out`-dan pop.
- `stack_out` boş olduqda `stack_in`-dəki hər şeyi `stack_out`-a köç.
- Amortized O(1) per operation. Worst case O(n) (köçürmə zamanı).

### Queue ilə Stack:
- İki queue ilə stack — reversed pattern.
- Biri "current", digəri "temp" kimi işlənir.
- Push: yeni elementi boş queue-ya at, köhnə queue-nun hər şeyini arxasına əlavə et.
- O(n) push, O(1) pop — ya da əksi.

### Priority Queue (Min/Max Heap):
- Normal queue-dan fərqli olaraq priority-ə görə çıxarma.
- Hər `dequeue` ən kiçik (min-heap) ya ən böyük (max-heap) elementi qaytarır.
- O(log n) insert, O(log n) delete, O(1) peek.
- Python: `heapq` (min-heap by default), negation ilə max-heap.
- Java: `PriorityQueue<Integer>`.

### Expression Evaluation:
- Infix → postfix çevirmək üçün stack (Shunting Yard algorithm).
- Postfix evaluate etmək üçün stack: ədəd görüncə push, operator görüncə iki pop, əməliyyat et, push.
- Bracket validation üçün stack: `(`, `[`, `{` push; `)`, `]`, `}` olduqda pop + uyğunluq yoxla.

### Circular Queue (Ring Buffer):
- Fixed-size array üzərindən queue. `head` + `tail` pointer-lar.
- `tail = (tail + 1) % capacity` — circular movement.
- Memory efficient — fix size queue-lar üçün.
- Producer-consumer problem-lərdə istifadə olunur.

### Stack-ın Tətbiqləri — Real Dünya:
- **Call stack**: Funksiya çağırışlarının izlənməsi. Stack overflow = dərin recursion.
- **Undo/Redo**: Text editor-larda. Undo — pop, Redo — ikinci stack.
- **Browser history**: Back button = pop.
- **Syntax checking**: Compiler-larda bracket, parentheses validation.
- **Expression parsing**: Math expressions, SQL parser.

### Queue-nun Tətbiqləri — Real Dünya:
- **BFS**: Level-by-level graph/tree traversal.
- **Task queue**: Web server-da request-ləri sırayla işlə (Laravel Queue, RabbitMQ).
- **Print queue**: OS printer spooler.
- **Bandwidth management**: Network packet queue.
- **Sliding window rate limiter**: Timestamp-lər queue-da, köhnə olanlar çıxarılır.

## Praktik Baxış

**Interview-a yanaşma:**
Stack/Queue sualına başlamadan: "Bu LIFO mi, FIFO mi?" soruşmadan əvvəl problem statement-i oxu. Çox vaxt "son görülən..." LIFO, "növbəti işlənəcək..." FIFO deməkdir. Monotonic stack istifadəsi görürsənsə, bu adətən "next greater/smaller element" pattern-dir.

**Nədən başlamaq lazımdır:**
- Stack lazım olub-olmadığını anlamaq üçün: "Geriyə qayıtmaq lazımdırmı? LIFO lazımdırmı?" — stack.
- Queue lazım olub-olmadığı: "Sırayla işləmək lazımdırmı? Level-by-level?" — queue.
- Monotonic stack: "Növbəti böyük/kiçik element", "sol/sağdakı boundary" — bu pattern-in işarəsi.
- "Bu sual bir vəziyyəti izləyir, başa çatanda nə etmək lazımdır?" — stack.

**Follow-up suallar:**
- "Stack-i yalnız queue istifadə edərək implement et."
- "Minimum element O(1)-də qaytaran stack dizayn et (Min Stack)."
- "Bu sualı recursive yox, iterative stack ilə həll et."
- "Monotonic stack nədir? Haçan istifadə olunur?"
- "Circular queue-nu fixed-size array ilə implement et."
- "Priority queue ilə normal queue fərqi nədir? Implementation complexity?"
- "Rate limiter sliding window queue ilə necə implement edilir?"

**Namizədlərin ümumi səhvləri:**
- Pop etmədən əvvəl stack-in boş olub-olmadığını yoxlamamaq.
- Python `list.pop(0)` istifadəsi — O(n), `deque.popleft()` istifadə et.
- Queue üçün array istifadə edərək `dequeue` O(n) etmək.
- Monotonic stack-i qurmaqda sıranı (push nə vaxt, pop nə vaxt) qarışdırmaq.
- Min Stack-da `min_stack` push zamanı current min-i saxlamağı unutmaq.
- Java-da `Stack` class-ını istifadə etmək — legacy, `ArrayDeque` preferred.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: Stack/queue-nu düzgün seçir, işlədən kod yazır.
- Əla cavab: Niyə stack/queue seçdiyini izah edir, amortized complexity-ni bilir, monotonic stack pattern-ni tanıyır, language-specific implementation detallarını bilir (Java `ArrayDeque` vs `Stack`), real-world tətbiqlərini qeyd edə bilir.

## Nümunələr

### Tipik Interview Sualı
"Aşağıdakı parenezlərin (`(`, `)`, `{`, `}`, `[`, `]`) valid olub-olmadığını yoxlayın. Input: `"({[]})"` → true, `"([)]"` → false."

### Güclü Cavab
"Bu klassik bracket validation məsələsidir, stack ilə O(n) time, O(n) space ilə həll edilir. Açıq bracket-ları stack-ə push edirəm. Bağlı bracket görüncə stack-in top-unu çıxarıb uyğunluğu yoxlayıram. Əgər stack boşdursa ya uyğunsuzluq varsa — false. Sonunda stack boş olmalıdır. Edge case: boş string → true, yalnız açıq bracketlər → false."

### Kod Nümunəsi
```python
# Bracket Validation — O(n) time, O(n) space
def is_valid(s: str) -> bool:
    stack = []
    mapping = {')': '(', '}': '{', ']': '['}
    for char in s:
        if char in mapping:              # bağlı bracket
            top = stack.pop() if stack else '#'
            if mapping[char] != top:
                return False
        else:
            stack.append(char)           # açıq bracket push
    return not stack  # stack boş olmalıdır

# Min Stack — O(1) minimum — paralel stack saxla
class MinStack:
    def __init__(self):
        self.stack = []
        self.min_stack = []   # paralel min tracker

    def push(self, val: int) -> None:
        self.stack.append(val)
        min_val = min(val, self.min_stack[-1] if self.min_stack else val)
        self.min_stack.append(min_val)  # current min-i saxla

    def pop(self) -> None:
        self.stack.pop()
        self.min_stack.pop()

    def top(self) -> int:
        return self.stack[-1]

    def get_min(self) -> int:
        return self.min_stack[-1]   # O(1)!

# Daily Temperatures — Monotonic Decreasing Stack — O(n)
def daily_temperatures(temperatures: list[int]) -> list[int]:
    n = len(temperatures)
    result = [0] * n
    stack = []  # index-ləri saxlayır (decreasing temperatures)

    for i, temp in enumerate(temperatures):
        # temp stack top-dan böyükdürsə — cavab tapıldı
        while stack and temperatures[stack[-1]] < temp:
            idx = stack.pop()
            result[idx] = i - idx   # neçə gün gözlədik
        stack.append(i)
    return result

# İki Stack ilə Queue — amortized O(1)
class MyQueue:
    def __init__(self):
        self.in_stack = []
        self.out_stack = []

    def push(self, x: int) -> None:
        self.in_stack.append(x)   # O(1)

    def pop(self) -> int:
        self._transfer()
        return self.out_stack.pop()   # O(1) amortized

    def peek(self) -> int:
        self._transfer()
        return self.out_stack[-1]

    def empty(self) -> bool:
        return not self.in_stack and not self.out_stack

    def _transfer(self) -> None:
        if not self.out_stack:
            while self.in_stack:
                self.out_stack.append(self.in_stack.pop())

# Circular Queue — Fixed-Size Array
class MyCircularQueue:
    def __init__(self, k: int):
        self.size = k
        self.queue = [0] * k
        self.head = 0
        self.tail = 0
        self.count = 0

    def enqueue(self, val: int) -> bool:
        if self.count == self.size:
            return False
        self.queue[self.tail] = val
        self.tail = (self.tail + 1) % self.size
        self.count += 1
        return True

    def dequeue(self) -> bool:
        if self.count == 0:
            return False
        self.head = (self.head + 1) % self.size
        self.count -= 1
        return True

    def front(self) -> int:
        return self.queue[self.head] if self.count > 0 else -1
```

### İkinci Nümunə — Largest Rectangle in Histogram

**Sual**: Histogram verilmişdir. Ən böyük dikdörtgenin sahəsini tapın. `heights = [2,1,5,6,2,3]` → 10.

**Cavab**: Monotonic increasing stack. Hər sütun üçün sol və sağ boundary tapılır. Stack-dəki elementlər artan sırada saxlanır. Element stack top-dan kiçikdirsə — top-u pop et, sahəni hesabla.

```python
def largest_rectangle_area(heights: list[int]) -> int:
    stack = []   # (index, height) — monotonic increasing
    max_area = 0

    for i, h in enumerate(heights):
        start = i
        while stack and stack[-1][1] > h:
            idx, height = stack.pop()
            # Bu height-ın sağ boundary = i, sol boundary = stack[-1] sonrası
            max_area = max(max_area, height * (i - idx))
            start = idx   # sol boundary geri çəkildi
        stack.append((start, h))

    # Stack-da qalanlar — sağ boundary array sonu
    for idx, height in stack:
        max_area = max(max_area, height * (len(heights) - idx))

    return max_area
```

## Praktik Tapşırıqlar
- LeetCode #20: Valid Parentheses (Easy) — stack ilə bracket validation.
- LeetCode #155: Min Stack (Medium) — O(1) minimum. Paralel stack texnikası.
- LeetCode #739: Daily Temperatures (Medium) — monotonic decreasing stack.
- LeetCode #232: Implement Queue using Stacks (Easy) — iki stack, amortized O(1).
- LeetCode #225: Implement Stack using Queues (Easy) — iki queue.
- LeetCode #84: Largest Rectangle in Histogram (Hard) — monotonic stack. Ən çətin stack sualı.
- LeetCode #239: Sliding Window Maximum (Hard) — monotonic deque.
- LeetCode #496: Next Greater Element I (Easy) — monotonic stack başlanğıcı.
- Özünütəst: Hanı halda stack, hansında queue, hansında deque seçərdin? 5 real scenario qur.

## Əlaqəli Mövzular
- **BFS and DFS** — Queue BFS-də, Stack (ya call stack) DFS-də istifadə olunur.
- **Binary Tree** — Level-order traversal queue tələb edir.
- **Recursion** — Rekursiya implicit call stack istifadəsi; iterative stack ilə simulate edilə bilər.
- **Sliding Window Technique** — Sliding window maximum üçün monotonic deque.
- **Heap / Priority Queue** — Queue-nun priority-based variantı. O(log n) operations.
