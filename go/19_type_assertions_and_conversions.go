package main

import (
	"fmt"
	"math"
)

// ===============================================
// TYPE ASSERTIONS VE TYPE CONVERSIONS
// ===============================================

// Go-da iki ferqli mexanizm var:
// 1. Type Assertion - interface-den konkret tipe catmaq
// 2. Type Conversion - bir tipi basqa tipe cevirmek

// -------------------------------------------
// 1. Numune interface-ler
// -------------------------------------------

type Heyvan interface {
	Ses() string
}

type It struct {
	Ad string
}

func (i It) Ses() string {
	return "Hav hav!"
}

func (i It) Gez() string {
	return i.Ad + " gezir"
}

type Pisik struct {
	Ad string
}

func (p Pisik) Ses() string {
	return "Miyav!"
}

func (p Pisik) Miyavla() string {
	return p.Ad + " miyavlayir"
}

func main() {

	// -------------------------------------------
	// 2. Type Assertion: i.(Type)
	// -------------------------------------------

	fmt.Println("=== 2. Type Assertion ===")

	// Interface deyisheninden konkret tipe catmaq
	var h Heyvan = It{Ad: "Rex"}

	// Type assertion - interface-den It tipine
	it := h.(It)
	fmt.Println(it.Ad)   // Rex
	fmt.Println(it.Gez()) // Rex gezir

	// TEHLIKE: Sehv tip olduqda panic verir!
	// pisik := h.(Pisik) // PANIC: interface conversion: main.Heyvan is main.It, not main.Pisik

	// -------------------------------------------
	// 3. Tehlukesiz Type Assertion (ok pattern)
	// -------------------------------------------

	fmt.Println("\n=== 3. Tehlukesiz Assertion (ok) ===")

	// "comma ok" pattern - panic vermir
	pisik, ok := h.(Pisik)
	if ok {
		fmt.Println("Bu pisikdir:", pisik.Ad)
	} else {
		fmt.Println("Bu pisik deyil!") // Bu isleyecek
	}

	it2, ok := h.(It)
	if ok {
		fmt.Println("Bu itdir:", it2.Ad) // Rex
	}

	// -------------------------------------------
	// 4. Type Switch
	// -------------------------------------------

	fmt.Println("\n=== 4. Type Switch ===")

	heyvanlar := []Heyvan{
		It{Ad: "Rex"},
		Pisik{Ad: "Mishmish"},
		It{Ad: "Karabash"},
		Pisik{Ad: "Boncuk"},
	}

	for _, h := range heyvanlar {
		// Type switch - tipi yoxlayib mueyyenlesdir
		switch v := h.(type) {
		case It:
			fmt.Printf("It: %s, %s\n", v.Ad, v.Gez())
		case Pisik:
			fmt.Printf("Pisik: %s, %s\n", v.Ad, v.Miyavla())
		default:
			fmt.Println("Namelum heyvan")
		}
	}

	// -------------------------------------------
	// 5. any/interface{} ile Type Assertion
	// -------------------------------------------

	fmt.Println("\n=== 5. any/interface{} ===")

	// any = interface{} (Go 1.18+)
	// Her hansi tipi saxlaya biler

	var deyerler []any = []any{
		42,
		"salam",
		true,
		3.14,
		[]int{1, 2, 3},
		map[string]int{"a": 1},
		nil,
	}

	for _, d := range deyerler {
		switch v := d.(type) {
		case int:
			fmt.Printf("int: %d\n", v)
		case string:
			fmt.Printf("string: %q\n", v)
		case bool:
			fmt.Printf("bool: %t\n", v)
		case float64:
			fmt.Printf("float64: %.2f\n", v)
		case []int:
			fmt.Printf("[]int: %v\n", v)
		case map[string]int:
			fmt.Printf("map: %v\n", v)
		case nil:
			fmt.Println("nil deyer")
		default:
			fmt.Printf("namelum tip: %T\n", v)
		}
	}

	// -------------------------------------------
	// 6. Type Conversion (Tip Cevirmeleri)
	// -------------------------------------------

	fmt.Println("\n=== 6. Type Conversion ===")

	// Go-da avtomatik tip cevirme YOXDUR
	// Explicit (aciq) cevirme lazimdir

	// int -> float64
	var tam int = 42
	var onluq float64 = float64(tam)
	fmt.Println("int -> float64:", onluq) // 42.0

	// float64 -> int (kesilme olur, yuvarlama yox!)
	var pi float64 = 3.99
	var tamPi int = int(pi)
	fmt.Println("float64 -> int:", tamPi) // 3 (kesildi, 4 deyil!)

	// int32 -> int64
	var kicik int32 = 100
	var boyuk int64 = int64(kicik)
	fmt.Println("int32 -> int64:", boyuk)

	// int64 -> int32 (DIQQET: overflow ola biler!)
	var cox int64 = math.MaxInt32 + 1
	var azaldilmis int32 = int32(cox)
	fmt.Println("Overflow numune:", azaldilmis) // Menfi reqem olacaq!

	// uint ve int arasinda
	var u uint = 42
	var i2 int = int(u)
	fmt.Println("uint -> int:", i2)

	// -------------------------------------------
	// 7. String <-> []byte, []rune Cevirmeleri
	// -------------------------------------------

	fmt.Println("\n=== 7. String <-> []byte, []rune ===")

	// string -> []byte (suretlidir, amma kopya yaradir)
	s := "Salam"
	b := []byte(s)
	fmt.Println("[]byte:", b) // [83 97 108 97 109]

	// []byte -> string
	s2 := string(b)
	fmt.Println("string:", s2) // Salam

	// string -> []rune (Unicode simvollari)
	az := "Şəhər"
	r := []rune(az)
	fmt.Println("[]rune:", r)                    // Unicode noqteleri
	fmt.Printf("Simvol sayi: %d, Bayt sayi: %d\n", len(r), len(az))

	// []rune -> string
	s3 := string(r)
	fmt.Println("string:", s3) // Şəhər

	// Tek reqem -> string (Unicode simvol)
	fmt.Println(string(rune(65)))  // A
	fmt.Println(string(rune(399))) // Ə

	// -------------------------------------------
	// 8. Type Conversion vs Type Assertion FERQI
	// -------------------------------------------

	fmt.Println("\n=== 8. Conversion vs Assertion ===")

	// TYPE CONVERSION: konkret tip -> konkret tip
	// Yeni deyer yaradir, tiplerin uygun olmasi lazimdir
	var x int = 42
	var y float64 = float64(x) // Conversion
	fmt.Println("Conversion:", y)

	// TYPE ASSERTION: interface -> konkret tip
	// Interfacein icindeki deyere catir
	var iface interface{} = 42
	z := iface.(int) // Assertion
	fmt.Println("Assertion:", z)

	// Conversion ile yeni tip yaratmaq
	type Manat float64
	type Dollar float64

	var qiymet Manat = 100
	var usd Dollar = Dollar(qiymet) // Conversion (eyni underlying type)
	fmt.Println("Manat -> Dollar:", usd)

	// -------------------------------------------
	// 9. Interface Yoxlamasi (Implements check)
	// -------------------------------------------

	fmt.Println("\n=== 9. Interface Yoxlamasi ===")

	// Interface-i implement edib-etmediyi yoxlamaq
	type Yazici interface {
		Yaz(metn string)
	}

	type Jurnal struct{}
	func() {
		// Compile-time yoxlama:
		// Bu setir compile olmasa, Jurnal Yazici-ni implement etmir
		// var _ Yazici = Jurnal{} // Xeta verecek chunki Yaz() metodu yoxdur
		// var _ Yazici = (*Jurnal)(nil) // Pointer receiver ucun yoxlama

		fmt.Println("Interface uygunlugunu compile-time-da yoxlaya bilersiniz")
	}()

	// Runtime-da interface yoxlamasi
	var herhansi interface{} = It{Ad: "Rex"}

	if heyvan, ok := herhansi.(Heyvan); ok {
		fmt.Println("Heyvan interface-ini implement edir:", heyvan.Ses())
	}

	// -------------------------------------------
	// 10. Umumiy Xetalar (Common Gotchas)
	// -------------------------------------------

	fmt.Println("\n=== 10. Umumiy Xetalar ===")

	// XETA 1: nil interface assertion
	var nilIface interface{} = nil
	// _ = nilIface.(int) // PANIC: interface conversion: interface is nil, not int
	_, ok = nilIface.(int)
	fmt.Println("nil assertion ok:", ok) // false

	// XETA 2: JSON reqemleri float64 olur
	var jsonData interface{} = float64(42) // JSON-dan gelen reqem
	// _ = jsonData.(int) // PANIC! int deyil, float64-dur
	floatVal := jsonData.(float64)
	intVal := int(floatVal) // Evvel float64, sonra int-e cevir
	fmt.Println("JSON reqem:", intVal)

	// XETA 3: []interface{} != []string
	// Go-da slice-lar arasinda avtomatik cevirme yoxdur
	strs := []string{"a", "b", "c"}
	// var ifaces []interface{} = strs // XETA! Compile olmaz

	// Dongu ile cevirmek lazimdir
	ifaces := make([]interface{}, len(strs))
	for idx, s := range strs {
		ifaces[idx] = s
	}
	fmt.Println("Cevirilmish slice:", ifaces)

	// XETA 4: Pointer ve value receiver ferqi
	// Eger method pointer receiver ile teyin olunubsa,
	// yalniz pointer interface-i implement edir
	fmt.Println("\nPointer/Value receiver ferqine diqqet edin!")
}

// ISLETMEK UCUN:
// go run 65_type_assertions_and_conversions.go
