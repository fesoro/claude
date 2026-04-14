package main

import "fmt"

// ===============================================
// STRUCT - IRELILEMIS MOVZULAR
// ===============================================

// -------------------------------------------
// 1. Function as a Field in Structure
// -------------------------------------------
type Button struct {
	Ad       string
	Reng     string
	OnClick  func()              // parametrsiz funksiya sahesi
	OnHover  func(x, y int)      // parametrli funksiya sahesi
	Validate func(string) bool   // deyer qaytaran funksiya sahesi
}

// -------------------------------------------
// 2. Promoted Fields ve Methods
// -------------------------------------------
type Heyvan struct {
	Ad  string
	Yas int
}

func (h Heyvan) Ses() string {
	return h.Ad + " ses cixarir"
}

func (h Heyvan) Melumat() string {
	return fmt.Sprintf("%s, %d yash", h.Ad, h.Yas)
}

type It struct {
	Heyvan        // embedded - Heyvan saheleri ve metodlari promote olunur
	Cins   string
}

// It oz Ses() metodunu tanimlayarsa, Heyvan-inki override olunur
func (i It) Ses() string {
	return i.Ad + " hürkür" // Heyvan.Ses() yerine bu isleyir
}

type Pisik struct {
	Heyvan
	EvPisiyiMi bool
}

// Pisik oz Ses() tanimlamir - Heyvan.Ses() istifade olunur

func main() {

	// -------------------------------------------
	// 1. Funksiya sahesi olan struct
	// -------------------------------------------
	fmt.Println("=== Function as Field ===")

	btn := Button{
		Ad:   "Gonder",
		Reng: "goy",
		OnClick: func() {
			fmt.Println("Dugme basildi!")
		},
		OnHover: func(x, y int) {
			fmt.Printf("Mouse: (%d, %d)\n", x, y)
		},
		Validate: func(metn string) bool {
			return len(metn) > 0
		},
	}

	btn.OnClick()             // Dugme basildi!
	btn.OnHover(100, 200)     // Mouse: (100, 200)
	fmt.Println("Etibarlimi:", btn.Validate("salam")) // true
	fmt.Println("Etibarlimi:", btn.Validate(""))       // false

	// Funksiya sahesini sonradan deyismek
	btn.OnClick = func() {
		fmt.Println("Yeni click handler!")
	}
	btn.OnClick()

	// Nil funksiya sahesi yoxlamasi
	btn2 := Button{Ad: "Bos"}
	if btn2.OnClick != nil {
		btn2.OnClick()
	} else {
		fmt.Println("OnClick teyinlanmayib")
	}

	// Callback pattern
	type HttpHandler struct {
		Path      string
		OnRequest func(method, path string) string
	}

	handler := HttpHandler{
		Path: "/api/users",
		OnRequest: func(method, path string) string {
			return fmt.Sprintf("[%s] %s -> 200 OK", method, path)
		},
	}
	fmt.Println(handler.OnRequest("GET", handler.Path))

	// -------------------------------------------
	// 2. Promoted Fields
	// -------------------------------------------
	fmt.Println("\n=== Promoted Fields ===")

	it := It{
		Heyvan: Heyvan{Ad: "Boncuk", Yas: 3},
		Cins:   "Kangal",
	}

	// Heyvan saheleri birbaşa erise bilersiz (promoted)
	fmt.Println("Ad:", it.Ad)         // Heyvan.Ad - promoted
	fmt.Println("Yas:", it.Yas)       // Heyvan.Yas - promoted
	fmt.Println("Cins:", it.Cins)     // It.Cins - oz sahesi

	// Tam yol ile de erise bilersiz
	fmt.Println("Tam yol:", it.Heyvan.Ad) // eyni netice

	// -------------------------------------------
	// 3. Promoted Methods
	// -------------------------------------------
	fmt.Println("\n=== Promoted Methods ===")

	// It oz Ses() metodunu tanimlayib - override
	fmt.Println("It sesi:", it.Ses())           // "Boncuk hürkür"
	fmt.Println("Heyvan sesi:", it.Heyvan.Ses()) // "Boncuk ses cixarir" (orijinal)

	// It Melumat() tanimlamayib - Heyvan-inki istifade olunur (promoted)
	fmt.Println("Melumat:", it.Melumat()) // "Boncuk, 3 yash"

	pisik := Pisik{
		Heyvan:     Heyvan{Ad: "Miyav", Yas: 2},
		EvPisiyiMi: true,
	}
	// Pisik oz Ses() tanimlamayib - Heyvan-inki isleyir
	fmt.Println("Pisik sesi:", pisik.Ses()) // "Miyav ses cixarir"

	// -------------------------------------------
	// 4. Bir nece embedded struct - ad conflicti
	// -------------------------------------------
	fmt.Println("\n=== Ad Conflicti ===")

	type A struct{ X int }
	type B struct{ X int }
	type C struct {
		A
		B
	}

	c := C{A: A{X: 1}, B: B{X: 2}}
	// fmt.Println(c.X)  // XETA! Hansi X? A.X ve ya B.X?
	fmt.Println("A.X:", c.A.X) // 1 - tam yol ile erise bilerik
	fmt.Println("B.X:", c.B.X) // 2

	// -------------------------------------------
	// 5. Scope of Variables ile struct
	// -------------------------------------------
	fmt.Println("\n=== Struct Scope ===")

	type Config struct {
		Host string
		Port int
	}

	// Blok scope - deyisken yalniz blok icerisinde movcuddur
	{
		cfg := Config{Host: "localhost", Port: 8080}
		fmt.Println("Blok ici:", cfg)
	}
	// fmt.Println(cfg)  // XETA! cfg burada movcud deyil
}
