package main

import "fmt"

// ===============================================
// DEYISKENLER (VARIABLES)
// ===============================================

// Deyisken - yaddashda melumat saxlayan qutudur.
// Go-da deyisken elan etmeyin bir nece yolu var.

func main() {

	// -------------------------------------------
	// 1. VAR ile elan etme (var keyword)
	// -------------------------------------------
	// var ad tip = dəyər
	var ad string = "Orkhan"
	var yas int = 25
	var boy float64 = 1.75
	var evlidir bool = false

	fmt.Println("Ad:", ad)
	fmt.Println("Yas:", yas)
	fmt.Println("Boy:", boy)
	fmt.Println("Evlidir:", evlidir)

	// -------------------------------------------
	// 2. Tipi yazmadan elan etme (type inference)
	// -------------------------------------------
	// Go tipi ozunden tapir
	var sheher = "Baku"  // string oldugunu ozunden bilir
	var nomre = 42       // int oldugunu ozunden bilir
	var qiymet = 19.99   // float64 oldugunu ozunden bilir
	var aktiv = true     // bool oldugunu ozunden bilir

	fmt.Println("Sheher:", sheher)
	fmt.Println("Nomre:", nomre)
	fmt.Println("Qiymet:", qiymet)
	fmt.Println("Aktiv:", aktiv)

	// -------------------------------------------
	// 3. Qisa elan etme := (short declaration)
	// -------------------------------------------
	// En cox istifade olunan usul. Yalniz funksiya icerisinde isleyir.
	mesaj := "Salam Go!"     // var mesaj string = "Salam Go!" ile eynidir
	sayi := 100              // var sayi int = 100 ile eynidir
	faiz := 3.14             // var faiz float64 = 3.14 ile eynidir
	dogrudur := true         // var dogrudur bool = true ile eynidir

	fmt.Println("Mesaj:", mesaj)
	fmt.Println("Sayi:", sayi)
	fmt.Println("Faiz:", faiz)
	fmt.Println("Dogrudur:", dogrudur)

	// -------------------------------------------
	// 4. Bir nece deyiskeni birden elan etme
	// -------------------------------------------
	var (
		olke    string = "Azerbaycan"
		paytaxt string = "Baku"
		ehali   int    = 10000000
	)
	fmt.Println(olke, paytaxt, ehali)

	// Bir setirde bir nece deyisken
	a, b, c := 1, 2, 3
	fmt.Println(a, b, c)

	// -------------------------------------------
	// 5. Sifir deyerler (zero values)
	// -------------------------------------------
	// Elan olunub deyer verilmemis deyiskenlerin default deyeri olur:
	var s string  // "" (bos string)
	var i int     // 0
	var f float64 // 0.0
	var bo bool   // false

	fmt.Println("Bos string:", s)
	fmt.Println("Sifir int:", i)
	fmt.Println("Sifir float:", f)
	fmt.Println("Sifir bool:", bo)

	// -------------------------------------------
	// 6. Sabitler (constants)
	// -------------------------------------------
	// const ile elan olunur, deyeri deyisdirile bilmez
	const pi = 3.14159
	const appAd = "MenimApp"
	const maxSayi = 1000

	// pi = 3.14  // XETA! Sabiti deyismek olmaz
	fmt.Println("Pi:", pi)
	fmt.Println("App adi:", appAd)

	// Bir nece sabit birden
	const (
		gun   = 7
		ay    = 12
		il    = 365
	)
	fmt.Println("Bir ilde", il, "gun var")

	// -------------------------------------------
	// 7. iota - sabitler ucun sayici
	// -------------------------------------------
	// iota 0-dan baslanir ve her setirde 1 artir
	const (
		Bazar       = iota // 0
		BazarErtesi        // 1
		CersenbeAxsami     // 2
		Cersembe           // 3
		Cumeaxsami         // 4
		Cume               // 5
		Senbe              // 6
	)
	fmt.Println("Bazar:", Bazar)           // 0
	fmt.Println("BazarErtesi:", BazarErtesi) // 1
	fmt.Println("Cume:", Cume)             // 5
}
