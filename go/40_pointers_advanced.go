package main

import "fmt"

// ===============================================
// POINTER - IRELILEMIS MOVZULAR
// ===============================================

func main() {

	// -------------------------------------------
	// 1. POINTER TO POINTER (Double Pointer)
	// -------------------------------------------
	// Pointer-e pointer - bir pointer basqa pointere istinad edir
	// **int = int-e pointerin pointeri

	fmt.Println("=== Double Pointer ===")

	x := 42
	p := &x   // *int - x-e pointer
	pp := &p  // **int - p-ye pointer

	fmt.Println("x deyeri:    ", x)     // 42
	fmt.Println("p deyeri:    ", *p)    // 42 (p -> x)
	fmt.Println("pp deyeri:   ", **pp)  // 42 (pp -> p -> x)

	fmt.Printf("x adresi:     %p\n", &x)  // x-in adresi
	fmt.Printf("p saxlayir:   %p\n", p)   // x-in adresi (eyni)
	fmt.Printf("p adresi:     %p\n", &p)  // p-nin oz adresi
	fmt.Printf("pp saxlayir:  %p\n", pp)  // p-nin adresi (eyni)

	// Double pointer ile deyer deyisme
	**pp = 100
	fmt.Println("x indi:", x) // 100

	// Praktik istifade: funksiya icerisinde pointerin ozunu deyismek
	var ptr *int
	pointerDeyis(&ptr) // pointer-in pointer-ini gonderirik
	fmt.Println("ptr indi:", *ptr) // 999

	// -------------------------------------------
	// 2. COMPARING POINTERS (Pointer muqayisesi)
	// -------------------------------------------
	fmt.Println("\n=== Pointer Muqayisesi ===")

	a := 10
	b := 10
	c := a

	pa := &a
	pb := &b
	pa2 := &a

	// Pointer muqayisesi - adresler muqayise olunur, deyerler deyil!
	fmt.Println("pa == pa2:", pa == pa2) // true  (ikisi de a-ya istinad edir)
	fmt.Println("pa == pb: ", pa == pb)  // false (ferqli deyiskenler, deyerler eyni olsa da)

	// Deyer muqayisesi ucun * istifade edin
	fmt.Println("*pa == *pb:", *pa == *pb) // true (deyerler eynidir)

	// nil muqayisesi
	var nilPtr *int
	fmt.Println("nilPtr == nil:", nilPtr == nil) // true

	var p1 *int
	var p2 *int
	fmt.Println("nil == nil:   ", p1 == p2) // true (ikisi de nil)

	_ = c

	// -------------------------------------------
	// 3. POINTER SLICE VE MAP ILE
	// -------------------------------------------
	fmt.Println("\n=== Pointer Slice ===")

	// Pointer slice - boyuk struct-larin kopyalanmasinin qarsisini alir
	type Shexs struct {
		Ad  string
		Yas int
	}

	adamlar := []*Shexs{
		{Ad: "Eli", Yas: 25},
		{Ad: "Veli", Yas: 30},
		{Ad: "Orkhan", Yas: 28},
	}

	// Pointer vasitesile orijinali deyisir
	adamlar[0].Yas = 26
	fmt.Println("Eli yasi:", adamlar[0].Yas) // 26

	// Range ile pointer slice
	for _, adam := range adamlar {
		adam.Yas++ // orijinali deyisir (cunki pointer)
	}
	fmt.Println("Eli yasi++:", adamlar[0].Yas) // 27

	// -------------------------------------------
	// 4. FUNCTION POINTER (funksiya pointeri)
	// -------------------------------------------
	fmt.Println("\n=== Funksiya Pointeri ===")

	// Go-da funksiyalar birinci sinif vatandasdir
	// Pointer kimi otururule biler

	topla := func(a, b int) int { return a + b }
	cix := func(a, b int) int { return a - b }

	var emeliyyat func(int, int) int

	emeliyyat = topla
	fmt.Println("Topla:", emeliyyat(5, 3)) // 8

	emeliyyat = cix
	fmt.Println("Cix:", emeliyyat(5, 3)) // 2

	// nil funksiya pointer
	var nilFunc func()
	fmt.Println("nilFunc == nil:", nilFunc == nil) // true
	// nilFunc() // PANIC! nil function call
}

func pointerDeyis(pp **int) {
	deyer := 999
	*pp = &deyer // pointer-in ozunu deyisdirik
}
