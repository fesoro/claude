# Linked Lists (Junior)

## Konsept (Concept)

Linked list her bir node-un data ve novbeti node-a pointer saxladigi xetti data strukturudur. Massivden ferqli olaraq, elementler yaddashda ardicil deyil, pointerlele baglaniir.

```
Singly Linked List:
 head
  |
  v
+---+---+    +---+---+    +---+---+    +---+------+
| 1 | *-+--->| 2 | *-+--->| 3 | *-+--->| 4 | null |
+---+---+    +---+---+    +---+---+    +---+------+

Doubly Linked List:
       head                                    tail
        |                                       |
        v                                       v
+------+---+---+    +---+---+---+    +---+---+------+
| null | 1 | *-+--->| * | 2 | *-+--->| * | 3 | null |
+------+---+---+    +---+---+---+    +---+---+------+
                <---+-*-+   +   <---+-*-+
                
Circular Linked List:
+---+---+    +---+---+    +---+---+
| 1 | *-+--->| 2 | *-+--->| 3 | *-+--+
+---+---+    +---+---+    +---+---+  |
  ^                                   |
  +-----------------------------------+
```

## Nece Isleyir? (How does it work?)

### Singly Linked List emeliyyatlari:

**Evvele elave (prepend):**
```
Evvel:  head -> [1] -> [2] -> [3] -> null
Elave:  [0]

Addim 1: Yeni node yarad, next = head
         [0] -> [1] -> [2] -> [3] -> null
Addim 2: head = yeni node
  head -> [0] -> [1] -> [2] -> [3] -> null
```

**Ortadan silme:**
```
Evvel:  head -> [1] -> [2] -> [3] -> [4] -> null
Sil:    [2]

Addim 1: [1]-in next-ini [2]-nin next-ine yonlendir
  head -> [1] -> [3] -> [4] -> null
         [2] artiq erisilmezdir (garbage collected)
```

## Implementasiya (Implementation)

### Singly Linked List
```php
<?php
class ListNode {
    public int $val;
    public ?ListNode $next;

    public function __construct(int $val, ?ListNode $next = null) {
        $this->val = $val;
        $this->next = $next;
    }
}

class SinglyLinkedList {
    private ?ListNode $head = null;
    private int $size = 0;

    // Evvele elave: O(1)
    public function prepend(int $val): void {
        $this->head = new ListNode($val, $this->head);
        $this->size++;
    }

    // Sona elave: O(n) - tail pointer olmadan
    public function append(int $val): void {
        $newNode = new ListNode($val);
        if ($this->head === null) {
            $this->head = $newNode;
        } else {
            $current = $this->head;
            while ($current->next !== null) {
                $current = $current->next;
            }
            $current->next = $newNode;
        }
        $this->size++;
    }

    // Indeksle erisim: O(n)
    public function get(int $index): ?int {
        $current = $this->head;
        for ($i = 0; $i < $index && $current !== null; $i++) {
            $current = $current->next;
        }
        return $current?->val;
    }

    // Deyere gore silme: O(n)
    public function delete(int $val): bool {
        if ($this->head === null) return false;

        if ($this->head->val === $val) {
            $this->head = $this->head->next;
            $this->size--;
            return true;
        }

        $current = $this->head;
        while ($current->next !== null) {
            if ($current->next->val === $val) {
                $current->next = $current->next->next;
                $this->size--;
                return true;
            }
            $current = $current->next;
        }
        return false;
    }

    // Capa cixar
    public function display(): string {
        $result = [];
        $current = $this->head;
        while ($current !== null) {
            $result[] = $current->val;
            $current = $current->next;
        }
        return implode(' -> ', $result) . ' -> null';
    }
}
```

### Linked List-i Terse Cevirmek
```php
<?php
// Iterative: O(n) vaxt, O(1) yer
function reverseList(?ListNode $head): ?ListNode {
    $prev = null;
    $current = $head;

    while ($current !== null) {
        $next = $current->next;   // Novbetini saxla
        $current->next = $prev;    // Istiqameti deyis
        $prev = $current;          // prev-i ireli cek
        $current = $next;          // current-i ireli cek
    }

    return $prev; // Yeni head
}

// Vizual:
// null <- [1]    [2] -> [3] -> null   (prev=1, curr=2)
// null <- [1] <- [2]    [3] -> null   (prev=2, curr=3)
// null <- [1] <- [2] <- [3]           (prev=3, curr=null)
// Yeni head = 3

// Recursive: O(n) vaxt, O(n) yer (call stack)
function reverseListRecursive(?ListNode $head): ?ListNode {
    if ($head === null || $head->next === null) {
        return $head;
    }

    $newHead = reverseListRecursive($head->next);
    $head->next->next = $head;
    $head->next = null;

    return $newHead;
}
```

### Floyd's Cycle Detection (Fast/Slow Pointer)
```php
<?php
// Dovr (cycle) ashkarlama: O(n) vaxt, O(1) yer
function hasCycle(?ListNode $head): bool {
    $slow = $head;
    $fast = $head;

    while ($fast !== null && $fast->next !== null) {
        $slow = $slow->next;         // 1 addim
        $fast = $fast->next->next;   // 2 addim

        if ($slow === $fast) {
            return true; // Dovr var
        }
    }

    return false; // Dovr yoxdur
}

// Dovrun baslangic node-unu tap
function detectCycleStart(?ListNode $head): ?ListNode {
    $slow = $head;
    $fast = $head;

    // 1. Gorusme noqtesini tap
    while ($fast !== null && $fast->next !== null) {
        $slow = $slow->next;
        $fast = $fast->next->next;
        if ($slow === $fast) break;
    }

    if ($fast === null || $fast->next === null) return null;

    // 2. head-den ve gorusme noqtesinden eyni suretde hereket et
    $slow = $head;
    while ($slow !== $fast) {
        $slow = $slow->next;
        $fast = $fast->next;
    }

    return $slow; // Dovrun baslangici
}
```

### Ortanca Node-u Tapmaq (Fast/Slow)
```php
<?php
function findMiddle(?ListNode $head): ?ListNode {
    $slow = $head;
    $fast = $head;

    while ($fast !== null && $fast->next !== null) {
        $slow = $slow->next;
        $fast = $fast->next->next;
    }

    return $slow;
}
// [1] -> [2] -> [3] -> [4] -> [5]
//  s      f
//        s            f
//              s                  f (null)
// Cavab: 3
```

### Iki Sorted Linked List-i Birlesdirmek
```php
<?php
function mergeTwoLists(?ListNode $l1, ?ListNode $l2): ?ListNode {
    $dummy = new ListNode(0);
    $current = $dummy;

    while ($l1 !== null && $l2 !== null) {
        if ($l1->val <= $l2->val) {
            $current->next = $l1;
            $l1 = $l1->next;
        } else {
            $current->next = $l2;
            $l2 = $l2->next;
        }
        $current = $current->next;
    }

    $current->next = $l1 ?? $l2;

    return $dummy->next;
}
```

### Palindrome Yoxlamasi
```php
<?php
function isPalindrome(?ListNode $head): bool {
    // 1. Ortani tap
    $slow = $head;
    $fast = $head;
    while ($fast !== null && $fast->next !== null) {
        $slow = $slow->next;
        $fast = $fast->next->next;
    }

    // 2. Ikinci yarini terse cevir
    $prev = null;
    while ($slow !== null) {
        $next = $slow->next;
        $slow->next = $prev;
        $prev = $slow;
        $slow = $next;
    }

    // 3. Iki yarini muqayise et
    $left = $head;
    $right = $prev;
    while ($right !== null) {
        if ($left->val !== $right->val) return false;
        $left = $left->next;
        $right = $right->next;
    }

    return true;
}
```

### PHP SplDoublyLinkedList
```php
<?php
$list = new SplDoublyLinkedList();

$list->push(1);    // Sona elave
$list->push(2);
$list->push(3);
$list->unshift(0); // Evvele elave

echo $list->top();    // 3 (son element)
echo $list->bottom(); // 0 (ilk element)

$list->pop();      // Sondan silme
$list->shift();    // Evvelden silme

// Iterator mode
$list->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO);
foreach ($list as $val) {
    echo $val . " ";
}

// Ferqli rejimler:
// IT_MODE_FIFO | IT_MODE_KEEP   - Normal evvelden sona
// IT_MODE_LIFO | IT_MODE_KEEP   - Tersden evvele
// IT_MODE_FIFO | IT_MODE_DELETE - Oxudugca sil
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Emeliyyat | Singly | Doubly | Massiv |
|-----------|--------|--------|--------|
| Evvele elave | O(1) | O(1) | O(n) |
| Sona elave | O(n)* | O(1) | O(1) amort |
| Ortaya elave | O(n) | O(n) | O(n) |
| Evvelden silme | O(1) | O(1) | O(n) |
| Sondan silme | O(n) | O(1) | O(1) |
| Axtaris | O(n) | O(n) | O(n) |
| Indeksle erisim | O(n) | O(n) | O(1) |

*tail pointer ile O(1)

Space: O(n) -- amma her node elave pointer yaddashi istifade edir

## Tipik Meseleler (Common Problems)

1. **Reverse Linked List** - Yuxaridaki implementasiya
2. **Detect Cycle** - Floyd's algorithm
3. **Merge Two Sorted Lists** - Yuxaridaki implementasiya
4. **Remove Nth Node From End** - Two pointer (fast n addim qabaga)
5. **Intersection of Two Lists** - Uzunluq ferqini hesabla, sonra eyni anda hereket et

### Remove Nth Node From End
```php
<?php
function removeNthFromEnd(?ListNode $head, int $n): ?ListNode {
    $dummy = new ListNode(0, $head);
    $fast = $dummy;
    $slow = $dummy;

    // fast-i n+1 addim ireli cek
    for ($i = 0; $i <= $n; $i++) {
        $fast = $fast->next;
    }

    // Eyni anda hereket et, fast sona catanda slow silinecek node-dan birdir
    while ($fast !== null) {
        $fast = $fast->next;
        $slow = $slow->next;
    }

    $slow->next = $slow->next->next;
    return $dummy->next;
}
```

## Interview Suallari

**S: Linked list-in massivden ustunluyu nedr?**
C: Evvele/ortaya elave/silme O(1)-dir (node artiq tapilibsa). Boyut deyisdirilmesi lazim deyil. Amma random access yoxdur -- O(n).

**S: Niye dummy node istifade edirik?**
C: Head node-un silinmesi ve ya head-den evvel elave hallarini ayrica idare etmemek ucun. Kod sadeleir.

**S: Floyd's algoritmi niye isleyir?**
C: Fast pointer slow-dan 2x suretli gedir. Dovr varsa, mesafe her addimda 1 azalir, mutleq gorusecekler. Dovr baslangicini tapmaq ucun riyazi subut var: head-den dovrn baslangicina mesafe = gorusme noqtesinden dovrun baslangicina mesafe.

**S: Singly vs Doubly ne vaxt secmelidir?**
C: Singly: yaddas az, yalniz ireli getmek lazimsa. Doubly: geriye getmek lazimsa, sondan silme O(1) lazimsa. Misal: browser history (doubly), blockchain (singly).

## PHP/Laravel ile Elaqe

```php
<?php
// PHP-de linked list nadir istifade olunur, cunki PHP array
// hash map kimi isleyir ve evvelki/sonraki element ucun
// daxili doubly linked list saxlayir.

// Amma bu konseptler vardir:
// 1. Laravel Middleware Pipeline - linked list kimi isleyir
//    Her middleware novbeti middleware-i cagrir

// 2. SplDoublyLinkedList - PHP SPL kutubxanesi
//    SplStack ve SplQueue bunun uzerinde qurulub

// 3. Event Listeners chain
//    $dispatcher->listen('event', [Handler1::class, 'handle']);
//    $dispatcher->listen('event', [Handler2::class, 'handle']);

// 4. Eloquent Relations - lazy loading zaman linked kimi yuklenir

// 5. Iterator pattern:
class LinkedListIterator implements Iterator {
    private ?ListNode $current;
    private ?ListNode $head;
    private int $position = 0;

    public function __construct(?ListNode $head) {
        $this->head = $head;
        $this->current = $head;
    }

    public function current(): mixed { return $this->current->val; }
    public function key(): int { return $this->position; }
    public function next(): void {
        $this->current = $this->current->next;
        $this->position++;
    }
    public function rewind(): void {
        $this->current = $this->head;
        $this->position = 0;
    }
    public function valid(): bool { return $this->current !== null; }
}
```

## Praktik Tapşırıqlar

1. **LeetCode 206** — Reverse Linked List
2. **LeetCode 21** — Merge Two Sorted Lists
3. **LeetCode 141** — Linked List Cycle (Floyd's)
4. **LeetCode 19** — Remove Nth Node From End
5. **LeetCode 234** — Palindrome Linked List

### Step-by-step: Reverse Linked List

```
Başlanğıc: 1 → 2 → 3 → 4 → null
prev=null, curr=1

Step 1: next=2, 1→null, prev=1, curr=2
Step 2: next=3, 2→1,    prev=2, curr=3
Step 3: next=4, 3→2,    prev=3, curr=4
Step 4: next=null, 4→3, prev=4, curr=null

Nəticə: 4 → 3 → 2 → 1 → null
```

## Əlaqəli Mövzular

- [04-stacks.md](04-stacks.md) — Stack Linked List üzərində qurula bilər
- [05-queues.md](05-queues.md) — Doubly linked list ilə O(1) dequeue
- [25-graphs-basics.md](25-graphs-basics.md) — Adjacency list graph reprezentasiyası
- [35-design-problems.md](35-design-problems.md) — LRU Cache (Hash + Doubly Linked List)
