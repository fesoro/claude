package main

import "fmt"

// ===============================================
// DONGULER (LOOPS)
// ===============================================

// Go-da YALNIZ bir dongu var: for
// while, do-while YOXDUR - for her seyi edir

func main() {

	// -------------------------------------------
	// 1. Klassik FOR dongusu
	// -------------------------------------------
	// for baslangic; sert; addim { ... }
	for i := 0; i < 5; i++ {
		fmt.Println("i =", i)
	}
	// Cixis: 0, 1, 2, 3, 4

	// -------------------------------------------
	// 2. WHILE kimi for (yalniz sert ile)
	// -------------------------------------------
	sayi := 1
	for sayi <= 5 {
		fmt.Println("sayi =", sayi)
		sayi++
	}

	// -------------------------------------------
	// 3. SONSUZ DONGU (infinite loop)
	// -------------------------------------------
	// for { ... } - break ile dayandirilir
	counter := 0
	for {
		if counter >= 3 {
			break // donguden cix
		}
		fmt.Println("Sonsuz dongu:", counter)
		counter++
	}

	// -------------------------------------------
	// 4. CONTINUE - novbeti iterasiyaya kec
	// -------------------------------------------
	// Tek ededleri kec, cut ededleri yaz
	for i := 0; i < 10; i++ {
		if i%2 != 0 {
			continue // bu iterasiyanin qalanini kec
		}
		fmt.Println("Cut eded:", i)
	}
	// Cixis: 0, 2, 4, 6, 8

	// -------------------------------------------
	// 5. FOR RANGE - kolleksiyalar uzerinde gezme
	// -------------------------------------------

	// Massiv/slice uzerinde
	meyveler := []string{"alma", "armud", "nar", "uzum"}
	for index, deger := range meyveler {
		fmt.Printf("index: %d, deger: %s\n", index, deger)
	}

	// Yalniz deyer lazimsa (index-i ignore et)
	for _, meyve := range meyveler {
		fmt.Println("Meyve:", meyve)
	}

	// Yalniz index lazimsa
	for i := range meyveler {
		fmt.Println("Index:", i)
	}

	// String uzerinde (rune-lar ile)
	for i, herf := range "Salam" {
		fmt.Printf("index: %d, herf: %c\n", i, herf)
	}

	// Map uzerinde
	yaslar := map[string]int{
		"Eli":    25,
		"Veli":   30,
		"Orxan":  22,
	}
	for ad, yas := range yaslar {
		fmt.Printf("%s - %d yash\n", ad, yas)
	}

	// -------------------------------------------
	// 6. IC-ICE DONGU (Nested loops)
	// -------------------------------------------
	for i := 1; i <= 3; i++ {
		for j := 1; j <= 3; j++ {
			fmt.Printf("%d x %d = %d\n", i, j, i*j)
		}
	}

	// -------------------------------------------
	// 7. LABEL ile break/continue
	// -------------------------------------------
	// Ic-ice dongulerde xarici dongunu dayandirmaq ucun

xarici:
	for i := 0; i < 3; i++ {
		for j := 0; j < 3; j++ {
			if i == 1 && j == 1 {
				break xarici // xarici dongunu dayandirin
			}
			fmt.Printf("i=%d, j=%d\n", i, j)
		}
	}
}
