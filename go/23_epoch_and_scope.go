package main

import (
	"fmt"
	"time"
)

// ===============================================
// EPOCH (UNIX TIMESTAMP) VE SCOPE OF VARIABLES
// ===============================================

// -------------------------------------------
// Paket seviyyesi deyisken (global scope)
// -------------------------------------------
var globalDeyer = "hamisi gore biler"

func main() {

	// =====================
	// EPOCH / UNIX TIMESTAMP
	// =====================

	fmt.Println("=== Epoch / Unix Timestamp ===")

	// Epoch - 1 Yanvar 1970 00:00:00 UTC-den kecen saniye sayi
	// Unix timestamp adlanir

	indi := time.Now()

	// -------------------------------------------
	// 1. Cari timestamp almaq
	// -------------------------------------------
	unixSaniye := indi.Unix()           // saniyeler
	unixMilli := indi.UnixMilli()       // millisaniyeler
	unixMikro := indi.UnixMicro()       // mikrosaniyeler
	unixNano := indi.UnixNano()         // nanosaniyeler

	fmt.Println("Unix (saniye):      ", unixSaniye)
	fmt.Println("Unix (millisaniye): ", unixMilli)
	fmt.Println("Unix (mikrosaniye): ", unixMikro)
	fmt.Println("Unix (nanosaniye):  ", unixNano)

	// -------------------------------------------
	// 2. Timestamp -> Time cevirme
	// -------------------------------------------
	// Saniyeden
	t1 := time.Unix(1700000000, 0)
	fmt.Println("\n1700000000 ->", t1.Format("2006-01-02 15:04:05"))

	// Millisaniyeden
	t2 := time.UnixMilli(1700000000000)
	fmt.Println("1700000000000 ms ->", t2.Format("2006-01-02 15:04:05"))

	// -------------------------------------------
	// 3. Ferqli tarixlerin timestamp-i
	// -------------------------------------------
	dogumGunu := time.Date(1999, 5, 15, 10, 30, 0, 0, time.UTC)
	fmt.Println("\nDogum gunu:", dogumGunu.Format("2006-01-02"))
	fmt.Println("Timestamp:", dogumGunu.Unix())

	// -------------------------------------------
	// 4. Iki tarix arasi ferq
	// -------------------------------------------
	tarix1 := time.Date(2024, 1, 1, 0, 0, 0, 0, time.UTC)
	tarix2 := time.Date(2024, 12, 31, 0, 0, 0, 0, time.UTC)
	ferq := tarix2.Sub(tarix1)
	fmt.Printf("\nFerq: %d gun, %d saat\n", int(ferq.Hours()/24), int(ferq.Hours())%24)
	fmt.Println("Ferq saniye:", int(ferq.Seconds()))

	// -------------------------------------------
	// 5. Timestamp muqayisesi
	// -------------------------------------------
	evvel := time.Now()
	time.Sleep(10 * time.Millisecond)
	sonra := time.Now()

	fmt.Println("\nevvel.Before(sonra):", evvel.Before(sonra)) // true
	fmt.Println("sonra.After(evvel):", sonra.After(evvel))     // true
	fmt.Println("evvel.Equal(sonra):", evvel.Equal(sonra))     // false

	// -------------------------------------------
	// 6. Timezone ile
	// -------------------------------------------
	baku, _ := time.LoadLocation("Asia/Baku")
	bakuVaxt := time.Now().In(baku)
	fmt.Println("\nBaku vaxti:", bakuVaxt.Format("2006-01-02 15:04:05 MST"))

	utcVaxt := time.Now().UTC()
	fmt.Println("UTC vaxti:", utcVaxt.Format("2006-01-02 15:04:05 MST"))

	// =====================
	// SCOPE OF VARIABLES
	// =====================

	fmt.Println("\n\n=== Scope of Variables ===")

	// -------------------------------------------
	// 7. Paket seviyyesi (global)
	// -------------------------------------------
	fmt.Println("Global:", globalDeyer) // her yerden erise bilersiz

	// -------------------------------------------
	// 8. Funksiya seviyyesi (local)
	// -------------------------------------------
	lokal := "yalniz bu funksiyada"
	fmt.Println("Lokal:", lokal)

	// -------------------------------------------
	// 9. Blok seviyyesi (block scope)
	// -------------------------------------------
	{
		blokDeyer := "yalniz bu blokda"
		fmt.Println("Blok ici:", blokDeyer)
	}
	// fmt.Println(blokDeyer)  // XETA! blok xaricinde movcud deyil

	// -------------------------------------------
	// 10. If scope
	// -------------------------------------------
	if x := 42; x > 40 {
		fmt.Println("If ici x:", x)
	}
	// fmt.Println(x)  // XETA! x yalniz if blokunun icerisindedir

	// -------------------------------------------
	// 11. For scope
	// -------------------------------------------
	for i := 0; i < 3; i++ {
		// i yalniz dongu icerisinde movcuddur
		_ = i
	}
	// fmt.Println(i)  // XETA! i burada movcud deyil

	// -------------------------------------------
	// 12. Switch scope
	// -------------------------------------------
	switch n := 5; {
	case n > 0:
		fmt.Println("Switch ici n:", n) // n yalniz switch-de
	}
	// fmt.Println(n)  // XETA!

	// -------------------------------------------
	// 13. Kolgele me (Shadowing)
	// -------------------------------------------
	fmt.Println("\n=== Shadowing ===")

	deyer := "xarici"
	fmt.Println("Xarici:", deyer)

	{
		deyer := "daxili"            // YENI deyisken yaradir, xaricini kolgeler
		fmt.Println("Daxili:", deyer) // "daxili"
	}
	fmt.Println("Xarici yene:", deyer) // "xarici" - deyismeyib

	// Tehlukeli shadowing ornegi
	err := "esas xeta"
	if true {
		err := "yeni xeta" // DIQQET: bu YENI deyiskendir, xaricini deyismez!
		fmt.Println("Daxili err:", err)
	}
	fmt.Println("Xarici err:", err) // "esas xeta" - deyismeyib!

	// Duzgun yol: := yerine = istifade edin (eger xarici deyiskeni deyismek isteyirsinizse)
	err2 := "esas"
	if true {
		err2 = "deyismis" // = istifade edirik, := deyil
	}
	fmt.Println("Deyismis err2:", err2) // "deyismis"

	// -------------------------------------------
	// 14. Range over Iterators (Go 1.23+)
	// -------------------------------------------
	fmt.Println("\n=== Range over Iterators (Go 1.23+) ===")

	fmt.Println(`
Go 1.23 ile range ifadesi artiq xususi iterator funksiyalari ile isleyir:

// Iterator funksiya tipi
type Seq[V any] func(yield func(V) bool)
type Seq2[K, V any] func(yield func(K, V) bool)

// Xususi iterator
func Fibonacci() func(yield func(int) bool) {
    return func(yield func(int) bool) {
        a, b := 0, 1
        for {
            if !yield(a) {
                return
            }
            a, b = b, a+b
        }
    }
}

// Istifade
for v := range Fibonacci() {
    if v > 100 {
        break
    }
    fmt.Println(v)
}

// Backward iteration
for i, v := range slices.Backward([]string{"a", "b", "c"}) {
    fmt.Println(i, v) // 2 c, 1 b, 0 a
}
`)
}
