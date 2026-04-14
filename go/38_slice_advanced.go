package main

import (
	"fmt"
	"reflect"
	"slices"
	"sort"
)

// ===============================================
// SLICE - IRELILEMIS EMELIYYATLAR
// ===============================================

func main() {

	// -------------------------------------------
	// 1. COMPARING SLICES (Muqayise)
	// -------------------------------------------
	fmt.Println("=== Slice Muqayisesi ===")

	// Go-da slice-lari == ile muqayise etmek OLMAZ (yalniz nil ile olar)
	// reflect.DeepEqual istifade edin (yavasdir)

	a := []int{1, 2, 3}
	b := []int{1, 2, 3}
	c := []int{1, 2, 4}

	fmt.Println("a == b:", reflect.DeepEqual(a, b)) // true
	fmt.Println("a == c:", reflect.DeepEqual(a, c)) // false

	// Go 1.21+ slices paketi (daha suretli)
	fmt.Println("slices.Equal(a,b):", slices.Equal(a, b))   // true
	fmt.Println("slices.Equal(a,c):", slices.Equal(a, c))   // false

	// Xususi muqayise funksiyasi
	fmt.Println("Compare:", slices.Compare(a, c)) // -1 (a < c)

	// String slice muqayisesi
	s1 := []string{"salam", "dunya"}
	s2 := []string{"salam", "dunya"}
	fmt.Println("String equal:", slices.Equal(s1, s2)) // true

	// El ile muqayise (kohne Go versiyalari ucun)
	equal := func(x, y []int) bool {
		if len(x) != len(y) {
			return false
		}
		for i := range x {
			if x[i] != y[i] {
				return false
			}
		}
		return true
	}
	fmt.Println("El ile:", equal(a, b)) // true

	// -------------------------------------------
	// 2. SORTING SLICES (Siralama)
	// -------------------------------------------
	fmt.Println("\n=== Slice Siralama ===")

	// Sadə siralama
	ededler := []int{5, 2, 8, 1, 9, 3}
	sort.Ints(ededler)
	fmt.Println("Artan:", ededler) // [1 2 3 5 8 9]

	// Azalan siralama
	sort.Sort(sort.Reverse(sort.IntSlice(ededler)))
	fmt.Println("Azalan:", ededler) // [9 8 5 3 2 1]

	// Xususi siralama
	type Shexs struct {
		Ad  string
		Yas int
	}

	adamlar := []Shexs{
		{"Veli", 30},
		{"Eli", 25},
		{"Orkhan", 28},
	}

	// Yasa gore sirala
	sort.Slice(adamlar, func(i, j int) bool {
		return adamlar[i].Yas < adamlar[j].Yas
	})
	fmt.Println("Yasa gore:", adamlar)

	// Ada gore sirala
	sort.Slice(adamlar, func(i, j int) bool {
		return adamlar[i].Ad < adamlar[j].Ad
	})
	fmt.Println("Ada gore:", adamlar)

	// Stabildir mi? (eyni deyerli elementlerin sirasi qorunur)
	sort.SliceStable(adamlar, func(i, j int) bool {
		return adamlar[i].Yas < adamlar[j].Yas
	})

	// Go 1.21+ slices paketi ile
	slices.SortFunc(adamlar, func(a, b Shexs) int {
		return a.Yas - b.Yas
	})
	fmt.Println("slices.SortFunc:", adamlar)

	// Siralanibmi yoxla
	fmt.Println("Siralanib:", sort.IntsAreSorted([]int{1, 2, 3})) // true
	fmt.Println("Siralanib:", slices.IsSorted([]int{3, 1, 2}))     // false

	// -------------------------------------------
	// 3. TRIMMING SLICES (Kesme)
	// -------------------------------------------
	fmt.Println("\n=== Slice Trimming ===")

	s := []int{0, 0, 1, 2, 3, 0, 0}

	// Baslangicdan sifrlari sil
	start := 0
	for start < len(s) && s[start] == 0 {
		start++
	}

	// Sondan sifrlari sil
	end := len(s)
	for end > start && s[end-1] == 0 {
		end--
	}

	trimmed := s[start:end]
	fmt.Println("Trimmed:", trimmed) // [1 2 3]

	// Sertə uygun elementleri sil
	nums := []int{1, 2, 3, 4, 5, 6, 7, 8, 9, 10}
	// Tek ededleri saxla
	filtered := nums[:0] // eyni backing array
	for _, n := range nums {
		if n%2 != 0 {
			filtered = append(filtered, n)
		}
	}
	fmt.Println("Tekler:", filtered) // [1 3 5 7 9]

	// -------------------------------------------
	// 4. SPLITTING SLICES (Bolme)
	// -------------------------------------------
	fmt.Println("\n=== Slice Splitting ===")

	data := []int{1, 2, 3, 4, 5, 6, 7, 8, 9}

	// Ortadan bol
	mid := len(data) / 2
	sol := data[:mid]
	sag := data[mid:]
	fmt.Println("Sol:", sol) // [1 2 3 4]
	fmt.Println("Sag:", sag) // [5 6 7 8 9]

	// N olculu parcalara bol (chunk)
	chunk := func(slice []int, size int) [][]int {
		var parcalar [][]int
		for i := 0; i < len(slice); i += size {
			end := i + size
			if end > len(slice) {
				end = len(slice)
			}
			parcalar = append(parcalar, slice[i:end])
		}
		return parcalar
	}

	parcalar := chunk(data, 3)
	fmt.Println("Parcalar:", parcalar) // [[1 2 3] [4 5 6] [7 8 9]]

	parcalar2 := chunk(data, 4)
	fmt.Println("Parcalar:", parcalar2) // [[1 2 3 4] [5 6 7 8] [9]]

	// -------------------------------------------
	// 5. DIGER FAYDALI EMELIYYATLAR
	// -------------------------------------------
	fmt.Println("\n=== Diger Emeliyyatlar ===")

	// Tersleme (reverse)
	rev := []int{1, 2, 3, 4, 5}
	slices.Reverse(rev)
	fmt.Println("Ters:", rev) // [5 4 3 2 1]

	// Unikal elementler
	tekrar := []int{1, 2, 2, 3, 3, 3, 4}
	slices.Sort(tekrar)
	unikal := slices.Compact(tekrar) // ardisil tekrarlari silir
	fmt.Println("Unikal:", unikal) // [1 2 3 4]

	// Element axtarisi
	idx := slices.Index([]string{"a", "b", "c"}, "b")
	fmt.Println("Index:", idx) // 1

	// Ehtiva edir?
	fmt.Println("Contains:", slices.Contains([]int{1, 2, 3}, 2)) // true

	// Min / Max
	fmt.Println("Min:", slices.Min([]int{5, 2, 8, 1})) // 1
	fmt.Println("Max:", slices.Max([]int{5, 2, 8, 1})) // 8
}
