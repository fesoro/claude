package main

import "fmt"

// ===============================================
// SERT OPERATORLARI (CONDITIONAL STATEMENTS)
// ===============================================

func main() {

	// -------------------------------------------
	// 1. IF / ELSE IF / ELSE
	// -------------------------------------------
	yas := 20

	if yas >= 18 {
		fmt.Println("Boyukdur, ise gire biler")
	} else if yas >= 15 {
		fmt.Println("Yeniyetmedir")
	} else {
		fmt.Println("Usaqdir")
	}

	// QEYD: Go-da if sertinin etrafinda moterizə () YOXDUR
	// QEYD: { eyni setirde olmalidir (Go-da mecburidir)

	// -------------------------------------------
	// 2. IF ile qisa elan (short statement)
	// -------------------------------------------
	// if-den evvel deyisken elan etmek olar
	// Bu deyisken yalniz if/else bloku icerisinde movcuddur

	if n := 42; n > 40 {
		fmt.Println(n, "40-dan boyukdur")
	}
	// fmt.Println(n)  // XETA! n burada movcud deyil

	// -------------------------------------------
	// 3. SWITCH
	// -------------------------------------------
	// Bir nece serti yoxlamaq ucun if/else-den daha temizdir
	// Go-da break yazmaga ehtiyac YOXDUR (avtomatik dayanir)

	gun := "Cersembe"

	switch gun {
	case "BazarErtesi":
		fmt.Println("Heftenin 1-ci gunu")
	case "CersenbeAxsami":
		fmt.Println("Heftenin 2-ci gunu")
	case "Cersembe":
		fmt.Println("Heftenin 3-cu gunu")
	case "Cumeaxsami":
		fmt.Println("Heftenin 4-cu gunu")
	case "Cume":
		fmt.Println("Heftenin 5-ci gunu")
	case "Senbe", "Bazar": // bir nece deyeri bir case-de yoxlamaq
		fmt.Println("Istirahet gunu!")
	default:
		fmt.Println("Bele gun yoxdur")
	}

	// -------------------------------------------
	// 4. SWITCH sertsiz (expressionless switch)
	// -------------------------------------------
	// switch true ile eynidir - if/else yerine istifade olunur
	not := 85

	switch {
	case not >= 90:
		fmt.Println("Ela")
	case not >= 80:
		fmt.Println("Yaxsi")
	case not >= 70:
		fmt.Println("Kafi")
	case not >= 60:
		fmt.Println("Orta")
	default:
		fmt.Println("Qeyri-kafi")
	}

	// -------------------------------------------
	// 5. FALLTHROUGH
	// -------------------------------------------
	// Novbeti case-e kecmek isteyirsense (nadir istifade olunur)
	reqem := 1

	switch reqem {
	case 1:
		fmt.Println("Bir")
		fallthrough // novbeti case-e davam et
	case 2:
		fmt.Println("Iki")
		fallthrough
	case 3:
		fmt.Println("Uc")
	}
	// Cixis: Bir, Iki, Uc

	// -------------------------------------------
	// 6. Tip switch (Type Switch)
	// -------------------------------------------
	// Deyiskenin tipini yoxlamaq ucun (interface ile isleyir)
	var deyer interface{} = "salam"

	switch v := deyer.(type) {
	case int:
		fmt.Println("Bu int dir:", v)
	case string:
		fmt.Println("Bu string dir:", v)
	case bool:
		fmt.Println("Bu bool dur:", v)
	default:
		fmt.Printf("Bashqa tip: %T\n", v)
	}
}
