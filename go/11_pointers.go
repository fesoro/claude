package main

import "fmt"

// ===============================================
// POINTER (GOSTERICILER)
// ===============================================

// Pointer - deyiskenin yaddash unvanini saxlayir
// & - unvani al (address-of)
// * - unvandaki deyeri oxu/yaz (dereference)

// -------------------------------------------
// 1. Pointersiz funksiya (kopya ile isleyir)
// -------------------------------------------
func ikiArtir(n int) {
	n += 2 // yalniz KOPYANI deyisir, orijinala tesir etmir
}

// -------------------------------------------
// 2. Pointerli funksiya (orijinali deyisir)
// -------------------------------------------
func ikiArtirPtr(n *int) {
	*n += 2 // orijinal deyiskeni deyisir
}

// -------------------------------------------
// 3. Struct ile pointer
// -------------------------------------------
type Shexs struct {
	Ad  string
	Yas int
}

func yasArtir(s *Shexs) {
	s.Yas++ // Go avtomatik (*s).Yas edir
}

func main() {

	// -------------------------------------------
	// ESAS ANLAYIS
	// -------------------------------------------
	x := 42

	// & ile unvani al
	p := &x // p x-in unvanini saxlayir
	fmt.Println("x-in deyeri:", x)   // 42
	fmt.Println("x-in unvani:", p)   // 0xc000... (yaddash adresi)
	fmt.Println("p-nin deyeri:", *p)  // 42 (*p = p-nin gosterdiyi deyer)

	// * ile deyeri deyis
	*p = 100
	fmt.Println("x indi:", x) // 100 (pointer vasitesile deyisdi)

	// -------------------------------------------
	// Pointer tipi
	// -------------------------------------------
	var a int = 10
	var ptr *int = &a // *int = int-e pointer tipi

	fmt.Printf("Tip: %T, Deyer: %v\n", ptr, *ptr)

	// nil pointer (hec nəyə gostərmir)
	var nilPtr *int
	fmt.Println("Nil pointer:", nilPtr) // <nil>
	// *nilPtr  // PANIC! nil pointer dereference

	// -------------------------------------------
	// Funksiyada pointer istifadesi
	// -------------------------------------------
	n := 10
	ikiArtir(n) // kopya ile
	fmt.Println("ikiArtir sonrasi:", n) // 10 (deyismedi!)

	ikiArtirPtr(&n) // pointer ile
	fmt.Println("ikiArtirPtr sonrasi:", n) // 12 (deyisdi!)

	// -------------------------------------------
	// Struct pointer
	// -------------------------------------------
	adam := Shexs{Ad: "Orkhan", Yas: 25}
	yasArtir(&adam)
	fmt.Println("Yas:", adam.Yas) // 26

	// & ile struct yaratma
	adam2 := &Shexs{Ad: "Eli", Yas: 30}
	fmt.Println("Ad:", adam2.Ad) // Go avtomatik dereference edir

	// new ile pointer yaratma
	num := new(int) // *int qaytarir, deyer 0
	*num = 42
	fmt.Println("new:", *num)

	// -------------------------------------------
	// Pointer ne vaxt istifade olunur?
	// -------------------------------------------

	// 1. Boyuk struct-lari kopyalamaqdan qacinmaq ucun
	// 2. Funksiya icerisinde orijinal deyiskeni deyismek ucun
	// 3. nil deyer lazim olanda (pointer nil ola biler, int ola bilmez)

	// -------------------------------------------
	// MUHUM QEYDLER:
	// -------------------------------------------
	// - Go-da pointer arifmetikasi YOXDUR (C-den ferqli)
	// - Slice, map, channel artiq reference tipdir - pointer lazim deyil
	// - String, int, struct deyer tipdir - lazim olanda pointer istifade edin
}
