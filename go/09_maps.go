package main

import "fmt"

// ===============================================
// MAP (XƏRITƏ / LÜĞƏt)
// ===============================================

// Map - acar-deyer (key-value) cutlerini saxlayan struktur
// Diger dillerde dictionary, hashmap, object adlanir

func main() {

	// -------------------------------------------
	// 1. Map yaratma
	// -------------------------------------------

	// a) Literal ile
	yaslar := map[string]int{
		"Eli":   25,
		"Veli":  30,
		"Orxan": 22,
	}
	fmt.Println("Yaslar:", yaslar)

	// b) make ile
	qiymetler := make(map[string]float64)
	qiymetler["cay"] = 1.50
	qiymetler["kofe"] = 3.00
	qiymetler["su"] = 0.50
	fmt.Println("Qiymetler:", qiymetler)

	// -------------------------------------------
	// 2. Deyere erise (access)
	// -------------------------------------------
	fmt.Println("Elinin yasi:", yaslar["Eli"]) // 25

	// Olmayan acari sorgulasaq sifir deyer qaytarir
	fmt.Println("Aydin:", yaslar["Aydin"]) // 0 (int ucun sifir deyer)

	// Acarin movcud olub-olmadigini yoxlamaq
	yas, var_mi := yaslar["Eli"]
	if var_mi {
		fmt.Println("Eli tapildi, yasi:", yas)
	}

	_, var_mi2 := yaslar["Aydin"]
	if !var_mi2 {
		fmt.Println("Aydin tapilmadi")
	}

	// -------------------------------------------
	// 3. Element elave etme ve yeniləme
	// -------------------------------------------
	yaslar["Aydin"] = 28 // yeni element
	yaslar["Eli"] = 26   // movcud elementi yenile
	fmt.Println("Yenilenmis:", yaslar)

	// -------------------------------------------
	// 4. Element silme (delete)
	// -------------------------------------------
	delete(yaslar, "Veli")
	fmt.Println("Silinmis:", yaslar)

	// -------------------------------------------
	// 5. Map uzerinde dongu
	// -------------------------------------------
	for acar, deyer := range yaslar {
		fmt.Printf("%s: %d\n", acar, deyer)
	}
	// QEYD: Map-de sira qarantiya olunmur!

	// -------------------------------------------
	// 6. Map uzunlugu
	// -------------------------------------------
	fmt.Println("Element sayi:", len(yaslar))

	// -------------------------------------------
	// 7. Ic-ice Map (nested map)
	// -------------------------------------------
	telebler := map[string]map[string]int{
		"Eli": {
			"riyaziyyat": 90,
			"fizika":     85,
		},
		"Veli": {
			"riyaziyyat": 75,
			"fizika":     80,
		},
	}
	fmt.Println("Elinin riyaziyyat notu:", telebler["Eli"]["riyaziyyat"])

	// -------------------------------------------
	// 8. Map reference tipdir
	// -------------------------------------------
	// Map kopyalansa, eyni melumata istinad edir
	orijinal := map[string]int{"a": 1, "b": 2}
	kopya := orijinal // eyni map-e istinad
	kopya["a"] = 999
	fmt.Println("Orijinal:", orijinal) // map[a:999 b:2] - deyishdi!

	// Esl kopya almaq ucun yeni map yaradib kopyalamaq lazimdir
	eslKopya := make(map[string]int)
	for k, v := range orijinal {
		eslKopya[k] = v
	}

	// -------------------------------------------
	// 9. Praktik misal: soz sayici
	// -------------------------------------------
	cumle := "alma armud alma nar alma armud"
	sozler := []string{}
	soz := ""
	for _, h := range cumle {
		if h == ' ' {
			if soz != "" {
				sozler = append(sozler, soz)
				soz = ""
			}
		} else {
			soz += string(h)
		}
	}
	if soz != "" {
		sozler = append(sozler, soz)
	}

	sayici := make(map[string]int)
	for _, s := range sozler {
		sayici[s]++
	}
	fmt.Println("Soz sayici:", sayici) // map[alma:3 armud:2 nar:1]
}
