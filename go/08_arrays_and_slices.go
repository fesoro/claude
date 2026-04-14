package main

import "fmt"

// ===============================================
// MASSIVLER VE SLICELAR (ARRAYS & SLICES)
// ===============================================

func main() {

	// =====================
	// MASSIV (ARRAY)
	// =====================
	// Sabit olculu, tipi eyni olan elementler toplusu
	// Olcusu deyisdirile bilmez

	// -------------------------------------------
	// 1. Massiv elan etme
	// -------------------------------------------
	var ededler [5]int // 5 elementli int massivi (hamisi 0)
	fmt.Println("Bos massiv:", ededler)

	// Deyer vermek
	ededler[0] = 10
	ededler[1] = 20
	ededler[2] = 30
	fmt.Println("Doldurulmus:", ededler)

	// Elan zamani deger vermek
	meyveler := [3]string{"alma", "armud", "nar"}
	fmt.Println("Meyveler:", meyveler)

	// Olcunu Go-ya hesablatmaq
	rengller := [...]string{"qirmizi", "yashil", "goy"} // ... = olcunu oz hesabla
	fmt.Println("Rengller:", rengller)
	fmt.Println("Uzunluq:", len(rengller))

	// =====================
	// SLICE
	// =====================
	// Dinamik olculu massiv - EN COX istifade olunan struktur
	// Olcusu boyuye ve kicile biler

	// -------------------------------------------
	// 2. Slice yaratma yollari
	// -------------------------------------------

	// a) Literal ile
	sehirler := []string{"Baku", "Gence", "Sumqayit"}
	fmt.Println("Sehirler:", sehirler)

	// b) make ile (baslangic olcusu ve tutumu)
	// make([]tip, uzunluq, tutum)
	sayi := make([]int, 3, 5) // 3 element, 5 tutum
	fmt.Println("make slice:", sayi)
	fmt.Println("Uzunluq:", len(sayi)) // 3
	fmt.Println("Tutum:", cap(sayi))   // 5

	// c) Massivden slice almaq
	massiv := [5]int{10, 20, 30, 40, 50}
	dilim := massiv[1:4] // index 1, 2, 3 (4 daxil deyil)
	fmt.Println("Dilim:", dilim) // [20 30 40]

	// -------------------------------------------
	// 3. Slice kesmeler (slicing)
	// -------------------------------------------
	s := []int{0, 1, 2, 3, 4, 5, 6, 7, 8, 9}

	fmt.Println("s[2:5]:", s[2:5]) // [2 3 4]
	fmt.Println("s[:3]: ", s[:3])  // [0 1 2]     (baslangicdan 3-e)
	fmt.Println("s[7:]: ", s[7:])  // [7 8 9]     (7-den sona)
	fmt.Println("s[:]:  ", s[:])   // hamisi

	// -------------------------------------------
	// 4. APPEND - element elave etme
	// -------------------------------------------
	var liste []int // nil slice
	liste = append(liste, 1)
	liste = append(liste, 2, 3, 4) // bir nece element
	fmt.Println("Liste:", liste)    // [1 2 3 4]

	// Slice-i slice-e elave etme
	digeri := []int{5, 6, 7}
	liste = append(liste, digeri...) // ... ile acilir
	fmt.Println("Birlesdirilmis:", liste) // [1 2 3 4 5 6 7]

	// -------------------------------------------
	// 5. COPY - kopyalama
	// -------------------------------------------
	mənbə := []int{1, 2, 3}
	kopya := make([]int, len(mənbə))
	copy(kopya, mənbə)
	fmt.Println("Kopya:", kopya)

	// Kopyanin deyisdirilmesi menbeni tesir etmir
	kopya[0] = 999
	fmt.Println("Menbe:", mənbə) // [1 2 3] - deyismedi
	fmt.Println("Kopya:", kopya) // [999 2 3]

	// -------------------------------------------
	// 6. Element silme
	// -------------------------------------------
	siyahi := []string{"a", "b", "c", "d", "e"}
	// "c" (index 2) sil
	siyahi = append(siyahi[:2], siyahi[3:]...)
	fmt.Println("Silinmis:", siyahi) // [a b d e]

	// -------------------------------------------
	// 7. Slice uzerinde dongu
	// -------------------------------------------
	rəqəmlər := []int{10, 20, 30, 40}

	// for range ile
	for i, v := range rəqəmlər {
		fmt.Printf("index %d: %d\n", i, v)
	}

	// -------------------------------------------
	// 8. Coxolculu massiv/slice (2D)
	// -------------------------------------------
	matris := [][]int{
		{1, 2, 3},
		{4, 5, 6},
		{7, 8, 9},
	}
	fmt.Println("Matris:", matris)
	fmt.Println("Matris[1][2]:", matris[1][2]) // 6

	// -------------------------------------------
	// 9. NIL slice vs BOS slice
	// -------------------------------------------
	var nilSlice []int          // nil (movcud deyil)
	bosSlice := []int{}         // bos (movcuddur amma bos)
	makeSlice := make([]int, 0) // bos (movcuddur amma bos)

	fmt.Println("nil slice == nil:", nilSlice == nil)     // true
	fmt.Println("bos slice == nil:", bosSlice == nil)     // false
	fmt.Println("make slice == nil:", makeSlice == nil)   // false

	// Lakin hamisi ucun len = 0 ve append isleyir
	fmt.Println("nil len:", len(nilSlice))
	nilSlice = append(nilSlice, 1) // problem yoxdur
	fmt.Println("Append sonrasi:", nilSlice)
}
