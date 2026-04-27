# Data Structures — Go-da (Senior)

## İcmal

Go standart kitabxanası slice, map, channel kimi əsas data structure-ları təqdim edir. Lakin Stack, Queue, Linked List, Tree, Graph, Priority Queue — bunların hazır implementasiyası yoxdur. Senior Go developer bu structure-ları özü yazır, `container/list` və `container/heap` paketlərini bilir, generics ilə type-safe implementasiya yaradır. Bu mövzu Go-nun dizayn qərarlarını başa düşmək üçün vacibdir.

## Niyə Vacibdir

- Generics (Go 1.18+) ilə type-safe, reusable data structure-lar mümkündür
- `container/heap` interface-i `sort.Interface`-ə bənzər — özünün implementasiyasını tələb edir
- Algorithm interview-larında və real layihələrdə (job queue, event buffer, cache) istifadə olunur
- Doğru data structure seçimi performansa böyük təsir edir

## Əsas Anlayışlar

### Stack — LIFO (Last In, First Out)

```
Push: [1] → [1,2] → [1,2,3]
Pop:  [1,2,3] → Pop 3 → [1,2]
```

Slice-ın sonu stack-ın top-u kimi istifadə olunur. `append` — push, `s[:n-1]` — pop.

### Queue — FIFO (First In, First Out)

```
Enqueue: [1] → [1,2] → [1,2,3]
Dequeue: [1,2,3] → Dequeue 1 → [2,3]
```

Slice-ın başından dequeue edəndə `s[1:]` — bu hər dəfə yeni slice yaradır, backing array eyni qalır. Böyük queue-lar üçün circular buffer daha effektivdir.

### Linked List

`container/list` — doubly linked list (ikitərəfli). Müxtəlif type-lar saxlaya bilər (`interface{}`).

Özünün implementasiyası generics ilə type-safe olur:

```go
type Node[T any] struct {
    Val  T
    Next *Node[T]
}
```

### Set — Unikal elementlər

Go-da Set yoxdur. `map[T]struct{}` idiomudur:

```go
set := make(map[string]struct{})
set["apple"] = struct{}{}
_, exists := set["apple"]
delete(set, "apple")
```

`struct{}` — sıfır byte, yalnız mövcudluğu yoxlamaq üçün.

### Binary Search Tree (BST)

- Insert: O(log n) average, O(n) worst case (sorted data)
- Search: O(log n) average
- In-order traversal: sıralı çıxış verir

Balanslanmamış BST deqenerasiya edə bilər. Production-da AVL tree, Red-Black tree (Go `btree` paketi) istifadə edilir.

### Heap (Priority Queue)

`container/heap` interface tələb edir:
- `Len() int`
- `Less(i, j int) bool` — min-heap üçün `h[i] < h[j]`
- `Swap(i, j int)`
- `Push(x interface{})`
- `Pop() interface{}`

Min-heap: ən kiçik element həmişə köküdə. `Less` əksinə yazılsa max-heap olur.

### Graph

Adjacency list: `map[string][]string` — ən sadə reprezentasiya. Böyük sparse graph-lar üçün effektivdir.

BFS (Breadth-First Search): qısa yol tapmaq, level-by-level traversal.
DFS (Depth-First Search): cycle aşkar etmək, topological sort.

## Praktik Baxış

### Real Layihələrdə İstifadə

| Data Structure | Use Case |
|---------------|----------|
| Stack | Undo/Redo, expression parsing, DFS recursive call simulation |
| Queue | Job queue, message buffer, BFS |
| Linked List | LRU cache (O(1) insert/delete), doubly linked deque |
| Set | Unique visitors, deduplication, tag filtering |
| BST | Range queries, autocomplete (sorted keys) |
| Heap | Priority task queue, top-K elements, Dijkstra algorithm |
| Graph | Social network, route planning, dependency resolution |

### Trade-off-lar

| Data Structure | Insert | Delete | Search | Memory |
|---------------|--------|--------|--------|--------|
| Slice (Stack/Queue) | O(1) amortized | O(n) front | O(n) | Compact |
| Linked List | O(1) | O(1) with ref | O(n) | Pointer overhead |
| BST | O(log n) | O(log n) | O(log n) | Node overhead |
| Map | O(1) avg | O(1) avg | O(1) avg | Hash overhead |
| Heap | O(log n) | O(log n) | O(1) min | Compact |

### Anti-pattern-lər

```go
// Anti-pattern 1: Queue üçün front-dan sil — memory leak
q.items = q.items[1:] // backing array-da köhnə elementlər qalır
// Böyük queue-lar üçün circular buffer istifadə edin

// Anti-pattern 2: container/list-i type assertion olmadan istifadə
e := ll.Front()
name := e.Value // interface{} — runtime type assertion lazım
name.(string)   // panic riski

// Anti-pattern 3: Görünüşcə korrekt amma yanlış heap init
h := &IntHeap{5, 3, 1}
// heap.Init(h) çağırılmadan // heap property pozulub!
heap.Push(h, 0) // yanlış nəticə

// Düzgün:
heap.Init(h)
heap.Push(h, 0)
```

### Generics ilə Type-Safe Stack

Go 1.18+ ilə `any` type constraint istifadə edərək reusable struktur:

```go
type Stack[T any] struct {
    items []T
}

func (s *Stack[T]) Push(v T) {
    s.items = append(s.items, v)
}

func (s *Stack[T]) Pop() (T, bool) {
    var zero T
    if len(s.items) == 0 {
        return zero, false
    }
    top := s.items[len(s.items)-1]
    s.items = s.items[:len(s.items)-1]
    return top, true
}
```

## Nümunələr

### Nümunə 1: Generic Stack və Queue

```go
package main

import "fmt"

// Generic Stack
type Stack[T any] struct {
    items []T
}

func (s *Stack[T]) Push(v T) {
    s.items = append(s.items, v)
}

func (s *Stack[T]) Pop() (T, bool) {
    var zero T
    if len(s.items) == 0 {
        return zero, false
    }
    top := s.items[len(s.items)-1]
    s.items = s.items[:len(s.items)-1]
    return top, true
}

func (s *Stack[T]) Peek() (T, bool) {
    var zero T
    if len(s.items) == 0 {
        return zero, false
    }
    return s.items[len(s.items)-1], true
}

func (s *Stack[T]) Len() int { return len(s.items) }

// Generic Queue
type Queue[T any] struct {
    items []T
}

func (q *Queue[T]) Enqueue(v T) {
    q.items = append(q.items, v)
}

func (q *Queue[T]) Dequeue() (T, bool) {
    var zero T
    if len(q.items) == 0 {
        return zero, false
    }
    front := q.items[0]
    q.items = q.items[1:]
    return front, true
}

func (q *Queue[T]) Len() int { return len(q.items) }

func main() {
    // String stack
    stack := &Stack[string]{}
    stack.Push("birinci")
    stack.Push("ikinci")
    stack.Push("üçüncü")

    for stack.Len() > 0 {
        v, _ := stack.Pop()
        fmt.Println("Stack Pop:", v)
    }
    // üçüncü, ikinci, birinci (LIFO)

    // Int queue
    q := &Queue[int]{}
    q.Enqueue(1)
    q.Enqueue(2)
    q.Enqueue(3)

    for q.Len() > 0 {
        v, _ := q.Dequeue()
        fmt.Println("Queue Dequeue:", v)
    }
    // 1, 2, 3 (FIFO)
}
```

### Nümunə 2: Generic Set

```go
package main

import "fmt"

type Set[T comparable] struct {
    items map[T]struct{}
}

func NewSet[T comparable]() *Set[T] {
    return &Set[T]{items: make(map[T]struct{})}
}

func (s *Set[T]) Add(v T) {
    s.items[v] = struct{}{}
}

func (s *Set[T]) Remove(v T) {
    delete(s.items, v)
}

func (s *Set[T]) Has(v T) bool {
    _, ok := s.items[v]
    return ok
}

func (s *Set[T]) Len() int { return len(s.items) }

func (s *Set[T]) Union(other *Set[T]) *Set[T] {
    result := NewSet[T]()
    for k := range s.items {
        result.Add(k)
    }
    for k := range other.items {
        result.Add(k)
    }
    return result
}

func (s *Set[T]) Intersection(other *Set[T]) *Set[T] {
    result := NewSet[T]()
    for k := range s.items {
        if other.Has(k) {
            result.Add(k)
        }
    }
    return result
}

func main() {
    a := NewSet[string]()
    a.Add("go")
    a.Add("php")
    a.Add("python")

    b := NewSet[string]()
    b.Add("java")
    b.Add("php")
    b.Add("go")

    fmt.Println("A ∪ B ölçüsü:", a.Union(b).Len())        // 4
    fmt.Println("A ∩ B ölçüsü:", a.Intersection(b).Len()) // 2 (go, php)
    fmt.Println("go var?", a.Has("go"))                     // true
}
```

### Nümunə 3: Priority Queue (Heap)

```go
package main

import (
    "container/heap"
    "fmt"
)

// Task — iş tapşırığı
type Task struct {
    Name     string
    Priority int // yüksək = vacib
}

// TaskHeap — max-heap (ən vacib task birinci)
type TaskHeap []Task

func (h TaskHeap) Len() int           { return len(h) }
func (h TaskHeap) Less(i, j int) bool { return h[i].Priority > h[j].Priority } // max-heap
func (h TaskHeap) Swap(i, j int)      { h[i], h[j] = h[j], h[i] }

func (h *TaskHeap) Push(x interface{}) {
    *h = append(*h, x.(Task))
}

func (h *TaskHeap) Pop() interface{} {
    old := *h
    n := len(old)
    task := old[n-1]
    *h = old[:n-1]
    return task
}

func main() {
    pq := &TaskHeap{
        {Name: "Email göndər", Priority: 1},
        {Name: "Kritik bug fix", Priority: 10},
        {Name: "Report yaz", Priority: 3},
        {Name: "Deploy et", Priority: 8},
    }
    heap.Init(pq)

    heap.Push(pq, Task{Name: "Production xətası!", Priority: 15})

    fmt.Println("Prioritet sırası ilə tapşırıqlar:")
    for pq.Len() > 0 {
        task := heap.Pop(pq).(Task)
        fmt.Printf("  [%2d] %s\n", task.Priority, task.Name)
    }
}
```

### Nümunə 4: Binary Search Tree

```go
package main

import "fmt"

type BST[T interface{ ~int | ~string | ~float64 }] struct {
    Val         T
    Left, Right *BST[T]
}

func (t *BST[T]) Insert(val T) *BST[T] {
    if t == nil {
        return &BST[T]{Val: val}
    }
    if val < t.Val {
        t.Left = t.Left.Insert(val)
    } else if val > t.Val {
        t.Right = t.Right.Insert(val)
    }
    return t
}

func (t *BST[T]) Search(val T) bool {
    if t == nil {
        return false
    }
    if val == t.Val {
        return true
    }
    if val < t.Val {
        return t.Left.Search(val)
    }
    return t.Right.Search(val)
}

func (t *BST[T]) InOrder(result *[]T) {
    if t == nil {
        return
    }
    t.Left.InOrder(result)
    *result = append(*result, t.Val)
    t.Right.InOrder(result)
}

func main() {
    var root *BST[int]
    for _, v := range []int{5, 3, 7, 1, 4, 6, 8} {
        root = root.Insert(v)
    }

    var sorted []int
    root.InOrder(&sorted)
    fmt.Println("Sıralı:", sorted) // [1 3 4 5 6 7 8]

    fmt.Println("4 var?", root.Search(4)) // true
    fmt.Println("9 var?", root.Search(9)) // false
}
```

### Nümunə 5: Graph — BFS ilə Qısa Yol

```go
package main

import "fmt"

type Graph struct {
    adj map[string][]string
}

func NewGraph() *Graph {
    return &Graph{adj: make(map[string][]string)}
}

func (g *Graph) AddEdge(from, to string) {
    g.adj[from] = append(g.adj[from], to)
    g.adj[to] = append(g.adj[to], from)
}

// BFS ilə qısa yol tap
func (g *Graph) ShortestPath(start, end string) []string {
    if start == end {
        return []string{start}
    }

    visited := map[string]bool{start: true}
    parent := map[string]string{}
    queue := []string{start}

    for len(queue) > 0 {
        node := queue[0]
        queue = queue[1:]

        for _, neighbor := range g.adj[node] {
            if visited[neighbor] {
                continue
            }
            visited[neighbor] = true
            parent[neighbor] = node
            queue = append(queue, neighbor)

            if neighbor == end {
                // Yolu geri izlə
                path := []string{end}
                for cur := end; cur != start; cur = parent[cur] {
                    path = append([]string{parent[cur]}, path...)
                }
                return path
            }
        }
    }
    return nil // yol yoxdur
}

func main() {
    g := NewGraph()
    g.AddEdge("Bakı", "Gəncə")
    g.AddEdge("Bakı", "Sumqayıt")
    g.AddEdge("Gəncə", "Mingəçevir")
    g.AddEdge("Sumqayıt", "Quba")
    g.AddEdge("Mingəçevir", "Şəki")
    g.AddEdge("Quba", "Şəki")

    path := g.ShortestPath("Bakı", "Şəki")
    fmt.Println("Qısa yol:", path)
    // Bakı → Gəncə → Mingəçevir → Şəki
    // və ya Bakı → Sumqayıt → Quba → Şəki
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — LRU Cache:**
Doubly Linked List + Map istifadə edərək O(1) get/put olan LRU (Least Recently Used) cache implementasiya edin. Capacity dolduqda ən az istifadə olunanı silin.

**Tapşırıq 2 — Job Queue:**
Priority Queue əsasında job scheduler yazın. Hər job-ın `Priority int`, `ExecuteAt time.Time`, `Handler func()` field-ləri olsun.

**Tapşırıq 3 — Expression Parser:**
Stack istifadə edərək sadə matematik ifadəni hesablayan kalkulator yazın: `"3 + 4 * 2"` → `11`.

**Tapşırıq 4 — Dependency Resolver:**
Graph-da topological sort (DFS) ilə package dependency-lərini resolve edin. Circular dependency aşkar edin.

**Tapşırıq 5 — Deduplication Pipeline:**
Set istifadə edərək böyük data stream-dən duplicate-ları filtrləyən pipeline yazın. Concurrent-safe olsun (`sync.RWMutex` ilə).

## PHP ilə Müqayisə

PHP-nin `SplStack`, `SplQueue`, `SplDoublyLinkedList` kimi hazır sinifləri var. Go-da bu strukturlar əl ilə yazılır — standart kitabxanada yoxdur:

```php
// PHP — hazır SplStack
$stack = new SplStack();
$stack->push("birinci");
$stack->push("ikinci");
echo $stack->pop(); // "ikinci"

// PHP — SplPriorityQueue
$pq = new SplPriorityQueue();
$pq->insert("task", 10);
```

```go
// Go — öz implementasiyanı yaz
type Stack[T any] struct {
    items []T
}
func (s *Stack[T]) Push(v T) { s.items = append(s.items, v) }
func (s *Stack[T]) Pop() (T, bool) { ... }
```

**Fərq:** PHP hazır OOP class-larla data structure-ları təqdim edir. Go-da generics ilə öz type-safe implementasiyanı yazmaq idiomatikdir. Bu Go-nun minimalist dizayn fəlsəfəsinin nəticəsidir.

## Əlaqəli Mövzular

- [08-arrays-and-slices](08-arrays-and-slices.md) — Slice əsasları
- [09-maps](09-maps.md) — Map əsasları
- [29-generics](29-generics.md) — Generic type-lar
- [41-slice-advanced](41-slice-advanced.md) — Slice-ın daxili mexanizmi
- [56-advanced-concurrency](56-advanced-concurrency.md) — Concurrent data structure-lar
- [68-profiling-and-benchmarking](68-profiling-and-benchmarking.md) — Data structure benchmark
