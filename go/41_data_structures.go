package main

import (
	"container/heap"
	"container/list"
	"fmt"
)

// ===============================================
// MELUMAT STRUKTURLARI (DATA STRUCTURES)
// ===============================================

// Go-da slice, map, channel daxilidir
// Diger strukturlari oz yaratmaq ve ya container paketi ile istifade etmek olar

func main() {

	// -------------------------------------------
	// 1. STACK (Yigin) - LIFO
	// -------------------------------------------
	// Son giren birinci cixir (Last In First Out)
	// Slice ile rahat implementasiya olunur

	fmt.Println("=== Stack ===")

	type Stack[T any] struct {
		items []T
	}

	push := func(s *Stack[int], v int) { s.items = append(s.items, v) }
	pop := func(s *Stack[int]) (int, bool) {
		if len(s.items) == 0 {
			return 0, false
		}
		v := s.items[len(s.items)-1]
		s.items = s.items[:len(s.items)-1]
		return v, true
	}
	peek := func(s *Stack[int]) (int, bool) {
		if len(s.items) == 0 {
			return 0, false
		}
		return s.items[len(s.items)-1], true
	}

	s := &Stack[int]{}
	push(s, 10)
	push(s, 20)
	push(s, 30)

	v, _ := peek(s)
	fmt.Println("Peek:", v) // 30

	v, _ = pop(s)
	fmt.Println("Pop:", v) // 30
	v, _ = pop(s)
	fmt.Println("Pop:", v) // 20

	// -------------------------------------------
	// 2. QUEUE (Novbe) - FIFO
	// -------------------------------------------
	// Birinci giren birinci cixir (First In First Out)

	fmt.Println("\n=== Queue ===")

	type Queue[T any] struct {
		items []T
	}

	enqueue := func(q *Queue[string], v string) { q.items = append(q.items, v) }
	dequeue := func(q *Queue[string]) (string, bool) {
		if len(q.items) == 0 {
			return "", false
		}
		v := q.items[0]
		q.items = q.items[1:]
		return v, true
	}

	q := &Queue[string]{}
	enqueue(q, "Birinci")
	enqueue(q, "Ikinci")
	enqueue(q, "Ucuncu")

	d, _ := dequeue(q)
	fmt.Println("Dequeue:", d) // Birinci
	d, _ = dequeue(q)
	fmt.Println("Dequeue:", d) // Ikinci

	// -------------------------------------------
	// 3. LINKED LIST (Bagli siyahi)
	// -------------------------------------------
	// container/list - daxili ikili bagli siyahi

	fmt.Println("\n=== Linked List ===")

	ll := list.New()
	ll.PushBack("Birinci")
	ll.PushBack("Ikinci")
	ll.PushBack("Ucuncu")
	ll.PushFront("Sifirinci")

	// Gezme
	for e := ll.Front(); e != nil; e = e.Next() {
		fmt.Print(e.Value, " ")
	}
	fmt.Println()

	// Element silme
	for e := ll.Front(); e != nil; e = e.Next() {
		if e.Value == "Ikinci" {
			ll.Remove(e)
			break
		}
	}
	fmt.Println("Silinmis, uzunluq:", ll.Len())

	// Xususi Linked List
	type Node struct {
		Value int
		Next  *Node
	}

	// 1 -> 2 -> 3 -> nil
	head := &Node{Value: 1, Next: &Node{Value: 2, Next: &Node{Value: 3}}}
	for n := head; n != nil; n = n.Next {
		fmt.Print(n.Value, " -> ")
	}
	fmt.Println("nil")

	// -------------------------------------------
	// 4. SET (Coxluq) - unikal elementler
	// -------------------------------------------
	// Go-da Set yoxdur, map[T]struct{} istifade olunur

	fmt.Println("\n=== Set ===")

	type Set[T comparable] struct {
		items map[T]struct{}
	}

	newSet := func() *Set[string] { return &Set[string]{items: make(map[string]struct{})} }
	add := func(s *Set[string], v string) { s.items[v] = struct{}{} }
	has := func(s *Set[string], v string) bool { _, ok := s.items[v]; return ok }
	remove := func(s *Set[string], v string) { delete(s.items, v) }
	size := func(s *Set[string]) int { return len(s.items) }

	set := newSet()
	add(set, "alma")
	add(set, "armud")
	add(set, "alma") // tekrar - elave olunmaz

	fmt.Println("alma var:", has(set, "alma"))  // true
	fmt.Println("nar var:", has(set, "nar"))     // false
	fmt.Println("Olcu:", size(set))              // 2

	remove(set, "alma")
	fmt.Println("Silmeden sonra:", size(set))    // 1

	// -------------------------------------------
	// 5. BINARY TREE (Ikili agac)
	// -------------------------------------------
	fmt.Println("\n=== Binary Search Tree ===")

	type TreeNode struct {
		Value       int
		Left, Right *TreeNode
	}

	var insert func(root *TreeNode, val int) *TreeNode
	insert = func(root *TreeNode, val int) *TreeNode {
		if root == nil {
			return &TreeNode{Value: val}
		}
		if val < root.Value {
			root.Left = insert(root.Left, val)
		} else if val > root.Value {
			root.Right = insert(root.Right, val)
		}
		return root
	}

	var inOrder func(root *TreeNode)
	inOrder = func(root *TreeNode) {
		if root == nil {
			return
		}
		inOrder(root.Left)
		fmt.Print(root.Value, " ")
		inOrder(root.Right)
	}

	var search func(root *TreeNode, val int) bool
	search = func(root *TreeNode, val int) bool {
		if root == nil {
			return false
		}
		if val == root.Value {
			return true
		}
		if val < root.Value {
			return search(root.Left, val)
		}
		return search(root.Right, val)
	}

	var tree *TreeNode
	for _, v := range []int{5, 3, 7, 1, 4, 6, 8} {
		tree = insert(tree, v)
	}

	fmt.Print("In-order: ")
	inOrder(tree) // 1 2 3 4 5 6 7 8
	fmt.Println()

	fmt.Println("4 var:", search(tree, 4))  // true
	fmt.Println("9 var:", search(tree, 9))  // false

	// -------------------------------------------
	// 6. HEAP (Priority Queue)
	// -------------------------------------------
	// container/heap - daxili heap interface

	fmt.Println("\n=== Heap (Priority Queue) ===")

	type IntHeap []int

	ih := &IntHeap{5, 3, 8, 1, 9, 2}
	heap.Init(ih)

	heap.Push(ih, 0)
	fmt.Println("Min:", (*ih)[0]) // 0 (en kicik)

	for ih.Len() > 0 {
		fmt.Print(heap.Pop(ih), " ") // sirali cixir: 0 1 2 3 5 8 9
	}
	fmt.Println()

	// -------------------------------------------
	// 7. GRAPH (Qrafik)
	// -------------------------------------------
	fmt.Println("\n=== Graph ===")

	type Graph struct {
		adjacency map[string][]string
	}

	newGraph := func() *Graph {
		return &Graph{adjacency: make(map[string][]string)}
	}

	addEdge := func(g *Graph, from, to string) {
		g.adjacency[from] = append(g.adjacency[from], to)
		g.adjacency[to] = append(g.adjacency[to], from) // yonsuz
	}

	// BFS - Eni boyunca axtaris
	bfs := func(g *Graph, start string) {
		visited := make(map[string]bool)
		queue := []string{start}
		visited[start] = true

		for len(queue) > 0 {
			node := queue[0]
			queue = queue[1:]
			fmt.Print(node, " ")

			for _, neighbor := range g.adjacency[node] {
				if !visited[neighbor] {
					visited[neighbor] = true
					queue = append(queue, neighbor)
				}
			}
		}
		fmt.Println()
	}

	g := newGraph()
	addEdge(g, "A", "B")
	addEdge(g, "A", "C")
	addEdge(g, "B", "D")
	addEdge(g, "C", "D")
	addEdge(g, "D", "E")

	fmt.Print("BFS: ")
	bfs(g, "A") // A B C D E
}

// Heap interface implementasiyasi
type IntHeap []int

func (h IntHeap) Len() int           { return len(h) }
func (h IntHeap) Less(i, j int) bool { return h[i] < h[j] } // min-heap
func (h IntHeap) Swap(i, j int)      { h[i], h[j] = h[j], h[i] }

func (h *IntHeap) Push(x interface{}) {
	*h = append(*h, x.(int))
}

func (h *IntHeap) Pop() interface{} {
	old := *h
	n := len(old)
	x := old[n-1]
	*h = old[:n-1]
	return x
}
