package main

import "fmt"

// ===============================================
// REKURSIYA (RECURSION)
// ===============================================

// Rekursiya - funksiya ozunu cagiranda bas verir
// Her rekursiv funksiyada DAYANDIRMA SERTI olmalidir!

// -------------------------------------------
// 1. Faktorial (n!)
// -------------------------------------------
// 5! = 5 * 4 * 3 * 2 * 1 = 120
func faktorial(n int) int {
	if n <= 1 {
		return 1 // dayandirma serti (base case)
	}
	return n * faktorial(n-1) // ozunu cagirma (recursive case)
}

// -------------------------------------------
// 2. Fibonacci
// -------------------------------------------
// 0, 1, 1, 2, 3, 5, 8, 13, 21, ...
func fibonacci(n int) int {
	if n <= 0 {
		return 0
	}
	if n == 1 {
		return 1
	}
	return fibonacci(n-1) + fibonacci(n-2)
}

// Memoization ile (suretli versiya)
func fibonacciMemo(n int, memo map[int]int) int {
	if n <= 0 {
		return 0
	}
	if n == 1 {
		return 1
	}
	if val, ok := memo[n]; ok {
		return val // artiq hesablanibsa, cache-den qaytir
	}
	memo[n] = fibonacciMemo(n-1, memo) + fibonacciMemo(n-2, memo)
	return memo[n]
}

// -------------------------------------------
// 3. Quvvet (power)
// -------------------------------------------
func quvvet(base, exp int) int {
	if exp == 0 {
		return 1
	}
	return base * quvvet(base, exp-1)
}

// -------------------------------------------
// 4. String tersleme
// -------------------------------------------
func tersCevir(s string) string {
	runes := []rune(s)
	if len(runes) <= 1 {
		return s
	}
	return tersCevir(string(runes[1:])) + string(runes[0])
}

// -------------------------------------------
// 5. Ededlerin cemi
// -------------------------------------------
func sliceCem(ededler []int) int {
	if len(ededler) == 0 {
		return 0
	}
	return ededler[0] + sliceCem(ededler[1:])
}

// -------------------------------------------
// 6. Binary Search (ikili axtaris)
// -------------------------------------------
func binarySearch(arr []int, hedef, sol, sag int) int {
	if sol > sag {
		return -1 // tapilmadi
	}
	orta := (sol + sag) / 2
	if arr[orta] == hedef {
		return orta
	}
	if hedef < arr[orta] {
		return binarySearch(arr, hedef, sol, orta-1)
	}
	return binarySearch(arr, hedef, orta+1, sag)
}

// -------------------------------------------
// 7. Hanoi quleleri
// -------------------------------------------
func hanoi(n int, mənbə, hedef, komekci string) {
	if n == 1 {
		fmt.Printf("Disk 1: %s -> %s\n", mənbə, hedef)
		return
	}
	hanoi(n-1, mənbə, komekci, hedef)
	fmt.Printf("Disk %d: %s -> %s\n", n, mənbə, hedef)
	hanoi(n-1, komekci, hedef, mənbə)
}

// -------------------------------------------
// 8. Qovluq agaci (tree)
// -------------------------------------------
type DosyaNode struct {
	Ad       string
	Qovluq   bool
	Icerik   []*DosyaNode
}

func agacYazdir(node *DosyaNode, prefix string, sonuncu bool) {
	budak := "├── "
	if sonuncu {
		budak = "└── "
	}
	fmt.Println(prefix + budak + node.Ad)

	yeniPrefix := prefix + "│   "
	if sonuncu {
		yeniPrefix = prefix + "    "
	}

	for i, child := range node.Icerik {
		agacYazdir(child, yeniPrefix, i == len(node.Icerik)-1)
	}
}

// -------------------------------------------
// 9. Tail recursion (kuyruk rekursiyasi)
// -------------------------------------------
// Son emeliyyat rekursiv cagirisdir - bezi dillerde optimallasdirila biler
// Go-da tail call optimization YOXDUR, amma pattern-i bilmek faydalidir
func faktorialTail(n, accumulator int) int {
	if n <= 1 {
		return accumulator
	}
	return faktorialTail(n-1, n*accumulator) // son emeliyyat ozunu cagirmaqdir
}

func main() {

	// 1. Faktorial
	fmt.Println("=== Faktorial ===")
	fmt.Println("5! =", faktorial(5))   // 120
	fmt.Println("10! =", faktorial(10)) // 3628800

	// 2. Fibonacci
	fmt.Println("\n=== Fibonacci ===")
	fmt.Print("Fibonacci: ")
	for i := 0; i < 10; i++ {
		fmt.Print(fibonacci(i), " ")
	}
	fmt.Println()

	// Memo ile (suretli)
	memo := make(map[int]int)
	fmt.Println("Fib(40):", fibonacciMemo(40, memo)) // aninda hesablanir

	// 3. Quvvet
	fmt.Println("\n=== Quvvet ===")
	fmt.Println("2^10 =", quvvet(2, 10)) // 1024

	// 4. String tersleme
	fmt.Println("\n=== String Tersleme ===")
	fmt.Println("salam ->", tersCevir("salam")) // malas

	// 5. Cem
	fmt.Println("\n=== Slice Cem ===")
	fmt.Println("Cem:", sliceCem([]int{1, 2, 3, 4, 5})) // 15

	// 6. Binary search
	fmt.Println("\n=== Binary Search ===")
	arr := []int{1, 3, 5, 7, 9, 11, 13, 15}
	fmt.Println("7 index:", binarySearch(arr, 7, 0, len(arr)-1))   // 3
	fmt.Println("10 index:", binarySearch(arr, 10, 0, len(arr)-1)) // -1

	// 7. Hanoi
	fmt.Println("\n=== Hanoi Quleleri (3 disk) ===")
	hanoi(3, "A", "C", "B")

	// 8. Fayl agaci
	fmt.Println("\n=== Fayl Agaci ===")
	root := &DosyaNode{Ad: "project", Qovluq: true, Icerik: []*DosyaNode{
		{Ad: "cmd", Qovluq: true, Icerik: []*DosyaNode{
			{Ad: "main.go", Qovluq: false},
		}},
		{Ad: "internal", Qovluq: true, Icerik: []*DosyaNode{
			{Ad: "handler.go", Qovluq: false},
			{Ad: "service.go", Qovluq: false},
		}},
		{Ad: "go.mod", Qovluq: false},
	}}
	fmt.Println(root.Ad)
	for i, child := range root.Icerik {
		agacYazdir(child, "", i == len(root.Icerik)-1)
	}

	// 9. Tail recursion
	fmt.Println("\n=== Tail Recursion ===")
	fmt.Println("5! (tail):", faktorialTail(5, 1)) // 120

	// MUHUM QEYDLER:
	// - Her zaman DAYANDIRMA SERTI olmalidir (yoxsa sonsuz dongu -> stack overflow)
	// - Go-da stack olcusu dinamikdir (1GB-a qeder boya biler)
	// - Cox derinlik lazim olanda iterativ (dongu) yanasmani tercih edin
	// - Memoization ile tekrarlanan hesablamalari qacindin
	// - Go-da tail call optimization YOXDUR
}
