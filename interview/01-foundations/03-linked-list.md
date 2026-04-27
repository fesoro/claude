# Linked List (Junior ⭐)

## İcmal
Linked list — hər node-un bir data sahəsi və növbəti node-a (ya da doubly-linked-da əvvəlki node-a da) işarə edən pointer-ı olan ardıcıl data structure-dur. Array-dən fərqli olaraq elementlər yaddaşda ardıcıl deyil, pointer-lar vasitəsilə bir-birinə bağlıdır. Interview-larda linked list sualları pointer manipulation, recursion, cycle detection kimi konseptləri birlikdə sınayır.

## Niyə Vacibdir
Linked list interview sualları birbaşa pointer/reference idarəetməsini, side effect-lərdən qorunmağı, edge case handling-i ölçür. Bu konseptlər real layihələrdə de vacibdir: browser history, undo/redo stack-ləri, LRU cache implementation (HashMap + doubly linked list), OS-in free memory bloklarını idarəetməsi linked list-ə əsaslanır. Amazon, Google, Meta bu mövzunu sıx soruşur. Recursive thinking-i sınamaq üçün ideal qurğu hesab olunur.

## Əsas Anlayışlar

### Node Quruluşu:
```
Node {
    data: T
    next: Node | null      # singly
    prev: Node | null      # doubly (yalnız doubly-linked-da)
}
```

### Növlər:
- **Singly linked list**: Hər node yalnız `next`-i göstərir. Bir istiqamətdə traverse. Memory efficient.
- **Doubly linked list**: Hər node `next` + `prev`. Hər iki istiqamətdə traverse. Daha çox yaddaş (hər node üçün əlavə pointer). LRU cache, deque implementasiyası üçün lazımdır.
- **Circular linked list**: Sonuncu node `head`-i göstərir. Music playlist, round-robin scheduling üçün istifadə olunur.
- **Circular doubly linked list**: Hər iki istiqamətdə circular. OS process scheduling-də istifadə olunur.

### Əməliyyatlar və Komplekslikləri:
- **Access by index**: O(n) — pointer-larla gəzmək lazımdır (array-dən fərq!).
- **Search**: O(n) — linear scan.
- **Insert at head**: O(1) — yalnız head pointer-ı yeniləmək.
- **Insert at tail** (tail pointer varsa): O(1); yoxdursa O(n).
- **Insert in middle**: O(n) pozisiya tapmaq + O(1) link dəyişmək.
- **Delete at head**: O(1).
- **Delete by value**: O(n) — əvvəl tap, sonra sil.
- **Delete by pointer (reference varsa)**: O(1) — doubly linked list-də.

### Array vs Linked List:
| Xüsusiyyət | Array | Linked List |
|---|---|---|
| Access by index | O(1) | O(n) |
| Insert/Delete at front | O(n) | O(1) |
| Insert/Delete at end | O(1) amortized | O(1) with tail ptr |
| Memory | Contiguous | Scattered |
| Cache performance | Yaxşı | Pis |
| Dynamic size | Resize lazımdır | Natural |
| Extra memory | Yoxdur | Pointer-lar üçün |
| Iteration performance | Sürətli (cache hit) | Yavaş (cache miss) |

### Sentinel Node (Dummy Node):
- Head-dən əvvəl saxta bir node yerləşdirmək.
- Edge case-ləri sadələşdirir: "head-i silmək" artıq adi node silməklə eynidir.
- LeetCode məsələlərinin çoxunda faydalıdır.
- `dummy = ListNode(0); dummy.next = head` — standart pattern.

### Floyd's Cycle Detection (Slow/Fast Pointer):
- Slow pointer: hər addımda 1 irəli.
- Fast pointer: hər addımda 2 irəli.
- Əgər fast null-a çatırsa → cycle yoxdur.
- Əgər slow == fast → cycle var.
- **Cycle başlanğıcı tapma**: slow-u head-ə qaytar, ikisini eyni sürətlə irəlilət, görüşdükləri yer cycle başlanğıcıdır. Riyazi sübut: meeting point-dən cycle başlanğıcına məsafə = head-dən cycle başlanğıcına məsafə.

### Reverse Linked List:
- In-place: üç pointer (prev, current, next) istifadə et.
- Rekursiv həll mümkündür amma O(n) space (call stack).
- İterative: O(n) time, O(1) space — interview-da preferred.
- Sıra vacibdir: əvvəl `next`-i saxla, sonra reverse et, sonra irəlilə.

### Runner Technique (Two Pointer in Linked List):
- İki pointer müxtəlif sürətlə gedir.
- **Middle tapmaq**: Slow 1, fast 2 addım — fast sona çatanda slow middle-dədir.
- **N-th from end**: Birinci pointer n addım irəli, sonra hər ikisi birlikdə — birinci sona çatanda ikinci n-th-dir.
- **Cycle detection**: fast == slow → cycle.

### Linked List-i Reverse Edərək Palindrome Yoxlamaq:
1. Slow/fast pointer ilə middle tap.
2. Orta nöqtədən sonraki hissəni in-place reverse et.
3. İki pointer ilə müqayisə et.
4. (Optional) Original quruluşu bərpa et.

### Merge Sort on Linked List:
- Array merge sort kimi, amma middle tapmaq slow/fast pointer ilə.
- Merge: O(n) time, O(1) space — sadece pointer-ları yenilə.
- Total: O(n log n) time, O(log n) space (recursion call stack).

### Skip List:
- Linked list + multiple "express lane" pointer-lar.
- O(log n) average search — balanced BST alternativ.
- Redis-in sorted set-inin altında skip list var.

### Common Interview Patterns:
- Dummy head istifadəsi edge case azaldır.
- Reverse before/after operation (palindrome check, merge).
- Merge two sorted lists — merge sort-un alt addımı.
- Detect + remove cycle.
- K group reverse — iterative ya recursive.

## Praktik Baxış

**Interview-a yanaşma:**
Linked list sualına başlamadan əvvəl soruş: "Singly mi, doubly mi?", "Cycle ola bilərmi?", "Head/tail pointer var mı?", "Sorted-durmu?", "Size bilinirmi?". Kodu yazmadan əvvəl bir-iki nümunə üzərindən pointer hərəkətlərini vizualize et — interviewer-a çəkərək göstər.

**Nədən başlamaq lazımdır:**
- Edge case-ləri əvvəlcədən müəyyən et: boş list, tək node, iki node.
- Dummy head istifadəsini düşün — əksər hallarda kodu sadələşdirir.
- Pointer-ların hərəkətini sözlü izah et: "prev, curr, next-i saxlayıram, curr.next = prev, sonra irəli hərəkət edirəm."
- Diagramda pointer-ları çək — bunu interviewer üçün görünən edin.

**Follow-up suallar:**
- "Bu listin ortasını O(1) space ilə tapa bilərsənmi?"
- "Listin palindrome olub-olmadığını yoxla (O(1) space)."
- "İki sorted list-i merge et."
- "Listin son K elementini reverse et."
- "Bu node-u O(1)-də sil (head/tail-ə çıxışın yoxdur)."
- "Linked list-i in-place sort et — O(n log n), O(1) space."
- "K-group-da reverse et."

**Namizədlərin ümumi səhvləri:**
- `null` pointer check etməmək: `node.next.val` — NullPointerException.
- Pointer update sırasını səhv etmək: `curr.next = prev` etmədən `curr = curr.next` deməkdən əvvəl `next`-i saxlamaq.
- Off-by-one: "n-th from end"-də pointer-ların fərqi.
- Cycle olan listdə sonsuz loop.
- Reverse edərkən `prev = None` ilə başlamağı unutmaq.
- Dummy node-un `next`-ini qaytarmağı unutmaq: `return dummy.next`, `return dummy` yox.

**Yaxşı cavabı əla cavabdan fərqləndirən nədir:**
- Yaxşı cavab: Doğru nəticə, belə ki həll işləyir.
- Əla cavab: Dummy node-u niyə istifadə etdiyini izah edir, pointer hərəkətlərini sözlü şəkildə izah edir, edge case-ləri (boş, tək element) əvvəlcədən müəyyən edir, O(1) space üçün iterative həll seçir, pointer update sırasını şərh edir.

## Nümunələr

### Tipik Interview Sualı
"Singly linked list-in head node-u verilmişdir. Listin ikinci yarısı reversed olduğunda palindrome olub-olmadığını O(n) time, O(1) space ilə yoxlayın."

### Güclü Cavab
"Bu məsələni üç addımda həll edərəm: Birinci, slow/fast pointer ilə middle-i tapıram. İkinci, ikinci yarını in-place reverse edirəm. Üçüncü, hər iki yarını müqayisə edirəm. O(n) time, O(1) space — stack ya extra array istifadə etmirəm. Sonda listin orijinal formasını bərpa etməyi düşünürəm — interviewer bunu soruşmasa da, yaxşı practice-dir. Edge case: tək element həmişə palindrome-dur, iki element isə hər iki halda düzgün işləyir."

### Kod Nümunəsi
```python
class ListNode:
    def __init__(self, val=0, next=None):
        self.val = val
        self.next = next

# Reverse — O(n) time, O(1) space (iterative)
def reverse_list(head: ListNode) -> ListNode:
    prev = None
    curr = head
    while curr:
        next_node = curr.next   # növbəti saxla (itirməyək)
        curr.next = prev        # reverse et
        prev = curr             # irəli hərəkət
        curr = next_node
    return prev  # yeni head

# Reverse (recursive) — O(n) time, O(n) space (call stack)
def reverse_list_recursive(head: ListNode) -> ListNode:
    if not head or not head.next:
        return head
    new_head = reverse_list_recursive(head.next)
    head.next.next = head   # reverse link
    head.next = None
    return new_head

# Middle tapmaq — slow/fast pointer
def find_middle(head: ListNode) -> ListNode:
    slow = fast = head
    while fast and fast.next:
        slow = slow.next        # 1 addım
        fast = fast.next.next   # 2 addım
    return slow  # fast sona çatanda slow middle-dədir

# Cycle Detection — Floyd's Algorithm
def has_cycle(head: ListNode) -> bool:
    slow = fast = head
    while fast and fast.next:
        slow = slow.next
        fast = fast.next.next
        if slow == fast:
            return True
    return False

# Cycle başlanğıcı — Floyd's Phase 2
def detect_cycle(head: ListNode) -> ListNode:
    slow = fast = head
    while fast and fast.next:
        slow = slow.next
        fast = fast.next.next
        if slow == fast:
            # Phase 2: slow-u head-ə qaytar
            slow = head
            while slow != fast:
                slow = slow.next
                fast = fast.next
            return slow  # cycle başlanğıcı
    return None

# Merge Two Sorted Lists — dummy head ilə
def merge_two_lists(l1: ListNode, l2: ListNode) -> ListNode:
    dummy = ListNode(0)   # sentinel — edge case azaldır
    curr = dummy
    while l1 and l2:
        if l1.val <= l2.val:
            curr.next = l1
            l1 = l1.next
        else:
            curr.next = l2
            l2 = l2.next
        curr = curr.next
    curr.next = l1 or l2   # qalan hissəni birləşdir
    return dummy.next

# N-th from end silmək — dummy + two pointers
def remove_nth_from_end(head: ListNode, n: int) -> ListNode:
    dummy = ListNode(0, head)
    fast = slow = dummy
    # fast-ı n+1 addım irəli apar
    for _ in range(n + 1):
        fast = fast.next
    while fast:
        fast = fast.next
        slow = slow.next
    slow.next = slow.next.next  # silinən node-u keç
    return dummy.next

# Palindrome Check — O(n) time, O(1) space
def is_palindrome(head: ListNode) -> bool:
    # 1. middle tap
    slow = fast = head
    while fast and fast.next:
        slow = slow.next
        fast = fast.next.next
    # 2. ikinci yarını reverse et
    second_half = reverse_list(slow)
    # 3. müqayisə et
    p1, p2 = head, second_half
    result = True
    while p2:
        if p1.val != p2.val:
            result = False
            break
        p1 = p1.next
        p2 = p2.next
    # 4. (optional) original-ı bərpa et
    reverse_list(second_half)
    return result
```

### İkinci Nümunə — Reverse K Groups

**Sual**: Linked list-i K-lıq qruplarda reverse edin. `head = [1,2,3,4,5], k = 2` → `[2,1,4,3,5]`.

```python
def reverse_k_group(head: ListNode, k: int) -> ListNode:
    # K node var mı yoxla
    count = 0
    node = head
    while node and count < k:
        node = node.next
        count += 1
    if count < k:
        return head  # k-dan az node — reverse etmə

    # K node-u reverse et
    prev, curr = None, head
    for _ in range(k):
        nxt = curr.next
        curr.next = prev
        prev = curr
        curr = nxt

    # head indi quyruqdur, qalan hissəni recursiv həll et
    head.next = reverse_k_group(curr, k)
    return prev  # yeni head
```

## Praktik Tapşırıqlar
- LeetCode #206: Reverse Linked List (Easy) — iterative + recursive hər ikisini yaz, space fərqini müqayisə et.
- LeetCode #21: Merge Two Sorted Lists (Easy) — dummy node ilə. Dummy olmadan da sına.
- LeetCode #141: Linked List Cycle (Easy) — Floyd's algorithm.
- LeetCode #142: Linked List Cycle II (Medium) — cycle başlanğıcını tap. Floyd Phase 2.
- LeetCode #234: Palindrome Linked List (Easy) — O(1) space. Addım-addım izah et.
- LeetCode #19: Remove Nth Node from End (Medium) — two pointers.
- LeetCode #25: Reverse Nodes in K-Group (Hard) — recursive/iterative hər ikisi.
- LeetCode #23: Merge K Sorted Lists (Hard) — priority queue ilə O(n log k).
- Özünütəst: LRU cache-i linked list + hash map ilə necə qurarsan? Doubly linked list + dict.

## Əlaqəli Mövzular
- **Stack and Queue** — linked list üzərində stack/queue implement edilə bilər.
- **Two Pointers Technique** — slow/fast pointer linked list-in əsas texnikasıdır.
- **Recursion** — linked list suallarının çoxunun rekursiv həlli var.
- **Hash Table** — cycle detection-da hash set ilə alternatif həll, LRU cache-də hash map.
- **BFS and DFS** — graph traversal-ın linked list üzərindəki analogiyası.
