package main

import (
	"fmt"
	"math"
)

// ===============================================
// FUNKSIYALAR (FUNCTIONS)
// ===============================================

// Funksiya - tekrarlanan kodu bir yere yigib ad vermekdir.
// func ad(parametrler) qaytarma_tipi { ... }

// -------------------------------------------
// 1. Sadə funksiya (parametrsiz, deyersiz)
// -------------------------------------------
func salam() {
	fmt.Println("Salam dunya!")
}

// -------------------------------------------
// 2. Parametrli funksiya
// -------------------------------------------
func salamVer(ad string) {
	fmt.Println("Salam,", ad)
}

// Eyni tipli parametrler qisa yazilis
func topla(a, b int) {
	fmt.Println("Cem:", a+b)
}

// -------------------------------------------
// 3. Deyer qaytaran funksiya (return)
// -------------------------------------------
func kvadrat(n int) int {
	return n * n
}

func daire_sahesi(radius float64) float64 {
	return math.Pi * radius * radius
}

// -------------------------------------------
// 4. Bir nece deyer qaytarma (multiple return)
// -------------------------------------------
// Bu Go-nun en xususi xususiyyetlerinden biridir!
func bol(a, b int) (int, int) {
	natice := a / b
	qaliq := a % b
	return natice, qaliq
}

// -------------------------------------------
// 5. Adli qaytarma (named return)
// -------------------------------------------
func hesabla(a, b int) (cem int, ferq int) {
	cem = a + b
	ferq = a - b
	return // "naked return" - adli deyerleri qaytarir
}

// -------------------------------------------
// 6. Xeta qaytarma (error handling pattern)
// -------------------------------------------
func bol_tehlukesiz(a, b int) (int, error) {
	if b == 0 {
		return 0, fmt.Errorf("sifira bolmek olmaz")
	}
	return a / b, nil // nil = xeta yoxdur
}

// -------------------------------------------
// 7. Variadic funksiya (sonsuz sayda parametr)
// -------------------------------------------
// ... ile istediyin qeder parametr gondermek olar
func cem(ededler ...int) int {
	toplam := 0
	for _, n := range ededler {
		toplam += n
	}
	return toplam
}

// -------------------------------------------
// 8. Funksiya parametr kimi (first-class function)
// -------------------------------------------
func tətbiqEt(ededler []int, f func(int) int) []int {
	netice := make([]int, len(ededler))
	for i, v := range ededler {
		netice[i] = f(v)
	}
	return netice
}

func main() {

	// 1. Sadə funksiya
	salam()

	// 2. Parametrli
	salamVer("Orkhan")
	topla(5, 3)

	// 3. Deyer qaytaran
	fmt.Println("5-in kvadrati:", kvadrat(5))
	fmt.Println("Daire sahesi:", daire_sahesi(5.0))

	// 4. Bir nece deyer
	natice, qaliq := bol(17, 5)
	fmt.Printf("17 / 5 = %d, qaliq = %d\n", natice, qaliq)

	// 5. Adli qaytarma
	c, f := hesabla(10, 3)
	fmt.Printf("Cem: %d, Ferq: %d\n", c, f)

	// 6. Xeta ile
	sonuc, err := bol_tehlukesiz(10, 0)
	if err != nil {
		fmt.Println("XETA:", err)
	} else {
		fmt.Println("Netice:", sonuc)
	}

	// 7. Variadic
	fmt.Println("Cem:", cem(1, 2, 3, 4, 5)) // 15
	fmt.Println("Cem:", cem(10, 20))          // 30

	// Slice-i variadic-e gondermek
	ededler := []int{1, 2, 3}
	fmt.Println("Cem:", cem(ededler...)) // ... ile acilir

	// 8. Anonim funksiya (anonymous/lambda)
	ikiqat := func(n int) int {
		return n * 2
	}
	fmt.Println("Ikiqat 5:", ikiqat(5)) // 10

	// Dərhal isleyen anonim funksiya (IIFE)
	func() {
		fmt.Println("Bu derhal isledi!")
	}()

	// Funksiya parametr kimi
	eded_list := []int{1, 2, 3, 4, 5}
	kvadratlar := tətbiqEt(eded_list, func(n int) int {
		return n * n
	})
	fmt.Println("Kvadratlar:", kvadratlar) // [1 4 9 16 25]

	// -------------------------------------------
	// 9. CLOSURE (baglama)
	// -------------------------------------------
	// Daxili funksiya xarici deyiskene erise biler
	sayici := func() func() int {
		sayi := 0
		return func() int {
			sayi++
			return sayi
		}
	}()

	fmt.Println(sayici()) // 1
	fmt.Println(sayici()) // 2
	fmt.Println(sayici()) // 3

	// -------------------------------------------
	// 10. DEFER
	// -------------------------------------------
	// defer - funksiya bitende en sonda isleyir (LIFO sirayla)
	// Fayl baglamaq, resurs temizlemek ucun istifade olunur

	fmt.Println("Basladi")
	defer fmt.Println("Bu en sonda isleyecek (1)")
	defer fmt.Println("Bu sondan evvel isleyecek (2)")
	fmt.Println("Davam edir")

	// Cixis sirasi: Basladi, Davam edir, (2), (1)
}
