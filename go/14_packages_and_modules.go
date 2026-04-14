package main

import (
	"encoding/json"
	"fmt"
	"math"
	"math/rand"
	"os"
	"sort"
	"strconv"
	"strings"
	"time"
)

// ===============================================
// PAKETLER VE MODULLAR (PACKAGES & MODULES)
// ===============================================

// Go-da her fayl bir pakete aiddir
// Paketler kodu teşkilatlandirir ve yeniden istifadeye imkan verir

// MODUL YARATMAQ:
// go mod init github.com/username/project
// Bu go.mod faylini yaradir (package.json kimi)

// XARICI PAKET YUKLEMEK:
// go get github.com/paket/adi
// Avtomatik go.mod ve go.sum fayllarini yenileyir

// PAKET STRUKTURU ORNEGI:
// myproject/
//   go.mod
//   main.go
//   utils/
//     helper.go    (package utils)
//     math.go      (package utils)
//   models/
//     user.go      (package models)

// MUHUM QAYDALAR:
// - Boyuk herfe baslanir = export olunur (public) -> Hesabla()
// - Kicik herfe baslanir = export olunmur (private) -> hesabla()
// - Bu qayda funksiyalar, deyiskenler, struct saheleri - her sey ucun kecerlidir

func main() {

	// =============================================
	// STANDART KUTUPHANEDEKI MUHUM PAKETLER
	// =============================================

	// -------------------------------------------
	// 1. fmt - Formatlama ve cap etme
	// -------------------------------------------
	ad := "Orkhan"
	yas := 25

	fmt.Println("Sadə cap")                          // setir sonu ile
	fmt.Print("Setir sonu olmadan ")                  // setir sonu yox
	fmt.Printf("Ad: %s, Yas: %d\n", ad, yas)         // formatli cap
	fmt.Printf("Float: %.2f\n", 3.14159)             // 2 onluq
	fmt.Printf("Tip: %T\n", ad)                      // tipi goster
	fmt.Printf("Deyer: %v\n", ad)                    // default format
	fmt.Printf("Binary: %b\n", 42)                   // ikilik
	fmt.Printf("Hex: %x\n", 255)                     // on altilik

	s := fmt.Sprintf("Ad: %s", ad) // string-e yaz (cap etme)
	fmt.Println(s)

	// -------------------------------------------
	// 2. strings - String emeliyyatlari
	// -------------------------------------------
	metn := "Salam Dunya Salam Go"

	fmt.Println(strings.Contains(metn, "Go"))       // true - ehtiva edir?
	fmt.Println(strings.HasPrefix(metn, "Salam"))   // true - ile baslayir?
	fmt.Println(strings.HasSuffix(metn, "Go"))      // true - ile bitir?
	fmt.Println(strings.ToUpper(metn))              // BOYUK HERFLERE
	fmt.Println(strings.ToLower(metn))              // kicik herflere
	fmt.Println(strings.Replace(metn, "Salam", "Hey", 1))  // birinci deyisdirme
	fmt.Println(strings.ReplaceAll(metn, "Salam", "Hey"))   // hamisi
	fmt.Println(strings.Split(metn, " "))            // ["Salam","Dunya","Salam","Go"]
	fmt.Println(strings.Join([]string{"a", "b", "c"}, "-")) // "a-b-c"
	fmt.Println(strings.TrimSpace("  salam  "))      // "salam"
	fmt.Println(strings.Count(metn, "Salam"))         // 2
	fmt.Println(strings.Index(metn, "Dunya"))         // 6

	// -------------------------------------------
	// 3. strconv - Tip cevirmesi
	// -------------------------------------------
	// String -> eded
	numStr := "42"
	num, _ := strconv.Atoi(numStr) // string -> int
	fmt.Println("Atoi:", num)

	floatNum, _ := strconv.ParseFloat("3.14", 64) // string -> float64
	fmt.Println("ParseFloat:", floatNum)

	boolVal, _ := strconv.ParseBool("true") // string -> bool
	fmt.Println("ParseBool:", boolVal)

	// Eded -> string
	str := strconv.Itoa(42) // int -> string
	fmt.Println("Itoa:", str)

	floatStr := strconv.FormatFloat(3.14, 'f', 2, 64)
	fmt.Println("FormatFloat:", floatStr)

	// -------------------------------------------
	// 4. math - Riyazi funksiyalar
	// -------------------------------------------
	fmt.Println("Abs:", math.Abs(-5.5))       // 5.5
	fmt.Println("Ceil:", math.Ceil(3.2))       // 4
	fmt.Println("Floor:", math.Floor(3.8))     // 3
	fmt.Println("Round:", math.Round(3.5))     // 4
	fmt.Println("Max:", math.Max(5, 10))       // 10
	fmt.Println("Min:", math.Min(5, 10))       // 5
	fmt.Println("Sqrt:", math.Sqrt(16))        // 4
	fmt.Println("Pow:", math.Pow(2, 10))       // 1024
	fmt.Println("Pi:", math.Pi)                // 3.14159...

	// -------------------------------------------
	// 5. sort - Siralama
	// -------------------------------------------
	ededler := []int{5, 2, 8, 1, 9, 3}
	sort.Ints(ededler)
	fmt.Println("Siralenmis int:", ededler) // [1 2 3 5 8 9]

	sozler := []string{"banan", "alma", "ciyek"}
	sort.Strings(sozler)
	fmt.Println("Siralenmis string:", sozler) // [alma banan ciyek]

	// Xususi siralama
	sort.Slice(ededler, func(i, j int) bool {
		return ededler[i] > ededler[j] // azalan sira
	})
	fmt.Println("Azalan:", ededler)

	// -------------------------------------------
	// 6. time - Vaxt emeliyyatlari
	// -------------------------------------------
	indi := time.Now()
	fmt.Println("Indi:", indi)
	fmt.Println("Il:", indi.Year())
	fmt.Println("Ay:", indi.Month())
	fmt.Println("Gun:", indi.Day())

	// Formatlama (Go-da xususi tarix istifade olunur: 2006-01-02 15:04:05)
	fmt.Println("Format:", indi.Format("2006-01-02 15:04:05"))
	fmt.Println("Tarix:", indi.Format("02/01/2006"))

	// Vaxt ferqi
	sonra := indi.Add(2 * time.Hour)
	fmt.Println("2 saat sonra:", sonra.Format("15:04"))

	ferq := sonra.Sub(indi)
	fmt.Println("Ferq:", ferq)

	// -------------------------------------------
	// 7. os - Emeliyyat sistemi
	// -------------------------------------------
	// Arqumentler
	fmt.Println("Arqumentler:", os.Args)

	// Muhit deyiskenleri
	home := os.Getenv("HOME")
	fmt.Println("HOME:", home)

	// -------------------------------------------
	// 8. encoding/json - JSON
	// -------------------------------------------
	type Shexs struct {
		Ad    string  `json:"ad"`
		Yas   int     `json:"yas"`
		Email string  `json:"email,omitempty"`
	}

	// Struct -> JSON (Marshal)
	shexs := Shexs{Ad: "Orkhan", Yas: 25, Email: "orkhan@mail.az"}
	jsonData, _ := json.Marshal(shexs)
	fmt.Println("JSON:", string(jsonData))

	// Gozel formatlı JSON
	jsonGozel, _ := json.MarshalIndent(shexs, "", "  ")
	fmt.Println("Gozel JSON:")
	fmt.Println(string(jsonGozel))

	// JSON -> Struct (Unmarshal)
	jsonStr := `{"ad":"Eli","yas":30}`
	var shexs2 Shexs
	json.Unmarshal([]byte(jsonStr), &shexs2)
	fmt.Println("Unmarshal:", shexs2)

	// -------------------------------------------
	// 9. math/rand - Tesadufi eded
	// -------------------------------------------
	fmt.Println("Tesadufi:", rand.Intn(100))      // 0-99
	fmt.Println("Tesadufi float:", rand.Float64()) // 0.0-1.0
}
