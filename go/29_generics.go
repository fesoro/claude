package main

import (
	"fmt"

	"golang.org/x/exp/constraints"
)

// ===============================================
// GENERICS (Go 1.18+)
// ===============================================

// Generics - ferqli tiplerle isleyen funksiya/struct yazmaga imkan verir
// Eyni kodu int, float, string ucun ayri-ayri yazmaga ehtiyac qalmir

// -------------------------------------------
// 1. Generic funksiya
// -------------------------------------------
// [T any] - T istənilən tip ola biler
func Yazdir[T any](deyerler []T) {
	for _, v := range deyerler {
		fmt.Print(v, " ")
	}
	fmt.Println()
}

// -------------------------------------------
// 2. Tip mehdudiyyeti (Type Constraint)
// -------------------------------------------
// Yalniz muqayise oluna bilen ve toplana bilen tiplerle islemek

// Oz constraint-imizi yaratmaq
type Eded interface {
	~int | ~int8 | ~int16 | ~int32 | ~int64 |
		~float32 | ~float64
}

func Cem[T Eded](ededler []T) T {
	var toplam T
	for _, n := range ededler {
		toplam += n
	}
	return toplam
}

func EnBoyuk[T constraints.Ordered](ededler []T) T {
	max := ededler[0]
	for _, v := range ededler[1:] {
		if v > max {
			max = v
		}
	}
	return max
}

func EnKicik[T constraints.Ordered](ededler []T) T {
	min := ededler[0]
	for _, v := range ededler[1:] {
		if v < min {
			min = v
		}
	}
	return min
}

// -------------------------------------------
// 3. comparable constraint
// -------------------------------------------
// == ve != ile muqayise oluna bilen tipler ucun
func Ehtiva[T comparable](slice []T, hedef T) bool {
	for _, v := range slice {
		if v == hedef {
			return true
		}
	}
	return false
}

// -------------------------------------------
// 4. Generic struct
// -------------------------------------------
type Yigin[T any] struct {
	elementler []T
}

func (y *Yigin[T]) Push(deyer T) {
	y.elementler = append(y.elementler, deyer)
}

func (y *Yigin[T]) Pop() (T, bool) {
	var sifir T
	if len(y.elementler) == 0 {
		return sifir, false
	}
	son := y.elementler[len(y.elementler)-1]
	y.elementler = y.elementler[:len(y.elementler)-1]
	return son, true
}

func (y *Yigin[T]) Bos() bool {
	return len(y.elementler) == 0
}

func (y *Yigin[T]) Olcu() int {
	return len(y.elementler)
}

// -------------------------------------------
// 5. Generic Map funksiyasi
// -------------------------------------------
func Map[T any, U any](slice []T, f func(T) U) []U {
	netice := make([]U, len(slice))
	for i, v := range slice {
		netice[i] = f(v)
	}
	return netice
}

func Filter[T any](slice []T, f func(T) bool) []T {
	var netice []T
	for _, v := range slice {
		if f(v) {
			netice = append(netice, v)
		}
	}
	return netice
}

func main() {

	// 1. Generic funksiya
	Yazdir([]int{1, 2, 3, 4, 5})
	Yazdir([]string{"salam", "dunya"})
	Yazdir([]float64{1.1, 2.2, 3.3})

	// 2. Eded emeliyyatlari
	fmt.Println("Int cem:", Cem([]int{1, 2, 3, 4, 5}))
	fmt.Println("Float cem:", Cem([]float64{1.1, 2.2, 3.3}))

	fmt.Println("En boyuk int:", EnBoyuk([]int{3, 1, 4, 1, 5, 9}))
	fmt.Println("En boyuk string:", EnBoyuk([]string{"banan", "alma", "ciyek"}))

	fmt.Println("En kicik:", EnKicik([]int{5, 2, 8, 1, 9}))

	// 3. Ehtiva
	fmt.Println("5 var mi:", Ehtiva([]int{1, 2, 3, 4, 5}, 5))       // true
	fmt.Println("Go var mi:", Ehtiva([]string{"Go", "Rust"}, "Go"))  // true
	fmt.Println("Py var mi:", Ehtiva([]string{"Go", "Rust"}, "Py"))  // false

	// 4. Generic Stack
	intYigin := &Yigin[int]{}
	intYigin.Push(10)
	intYigin.Push(20)
	intYigin.Push(30)
	fmt.Println("Olcu:", intYigin.Olcu()) // 3

	deyer, ok := intYigin.Pop()
	fmt.Println("Pop:", deyer, ok) // 30 true

	strYigin := &Yigin[string]{}
	strYigin.Push("salam")
	strYigin.Push("dunya")
	s, _ := strYigin.Pop()
	fmt.Println("String pop:", s) // dunya

	// 5. Map ve Filter
	ededler := []int{1, 2, 3, 4, 5}

	ikiqat := Map(ededler, func(n int) int { return n * 2 })
	fmt.Println("Ikiqat:", ikiqat) // [2 4 6 8 10]

	stringler := Map(ededler, func(n int) string {
		return fmt.Sprintf("eded_%d", n)
	})
	fmt.Println("Stringler:", stringler) // [eded_1 eded_2 ...]

	cutler := Filter(ededler, func(n int) bool { return n%2 == 0 })
	fmt.Println("Cutler:", cutler) // [2 4]
}
