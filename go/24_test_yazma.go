package main

import "fmt"

// ===============================================
// TEST YAZMA (TESTING)
// ===============================================

// Go-da test yazmaq cox sadedir - daxili "testing" paketi var
// Xarici framework lazim deyil!

// QAYDALAR:
// 1. Test fayli _test.go ile bitmalidir (orneyen: math_test.go)
// 2. Test funksiyasi Test ile baslamalidir (orneyen: TestTopla)
// 3. Parametr *testing.T olmalidir
// 4. "go test" emri ile isledilir

// -------------------------------------------
// TEST OLUNAN FUNKSIYALAR (normalde ayri faylda olur)
// -------------------------------------------
func Topla(a, b int) int {
	return a + b
}

func Cix(a, b int) int {
	return a - b
}

func Bol(a, b int) (int, error) {
	if b == 0 {
		return 0, fmt.Errorf("sifira bolmek olmaz")
	}
	return a / b, nil
}

func CutMu(n int) bool {
	return n%2 == 0
}

func main() {
	// Bu fayl yalniz izah ucundur.
	// Asagidaki test kodlarini ayri _test.go faylina yazmaq lazimdir.

	fmt.Println("Test izahati ucun bu fayli oxuyun")
	fmt.Println("Testleri isletmek ucun: go test ./...")
	fmt.Println()

	// Asagida test fayllinin necə gorunduyunu gosteririk:

	testKodu := `
// ==========================================
// FAYL: math_test.go
// ==========================================
package main

import "testing"

// -------------------------------------------
// 1. Sadə test
// -------------------------------------------
func TestTopla(t *testing.T) {
    netice := Topla(2, 3)
    gozlenen := 5

    if netice != gozlenen {
        t.Errorf("Topla(2,3) = %d; gozlenen %d", netice, gozlenen)
    }
}

// -------------------------------------------
// 2. Bir nece case ile test (Table-driven test)
// -------------------------------------------
// Go-da en cox istifade olunan test uslubu
func TestCix(t *testing.T) {
    testler := []struct {
        ad       string
        a, b     int
        gozlenen int
    }{
        {"musbet", 10, 3, 7},
        {"menfi netice", 3, 10, -7},
        {"sifir", 5, 5, 0},
        {"boyuk", 1000, 1, 999},
    }

    for _, tt := range testler {
        t.Run(tt.ad, func(t *testing.T) {
            netice := Cix(tt.a, tt.b)
            if netice != tt.gozlenen {
                t.Errorf("Cix(%d,%d) = %d; gozlenen %d",
                    tt.a, tt.b, netice, tt.gozlenen)
            }
        })
    }
}

// -------------------------------------------
// 3. Xeta testleme
// -------------------------------------------
func TestBol(t *testing.T) {
    // Normal hal
    netice, err := Bol(10, 2)
    if err != nil {
        t.Fatalf("Gozlenilmeyen xeta: %v", err)
    }
    if netice != 5 {
        t.Errorf("Bol(10,2) = %d; gozlenen 5", netice)
    }

    // Xeta hali
    _, err = Bol(10, 0)
    if err == nil {
        t.Error("Sifira bolmede xeta gozlenilirdi")
    }
}

// -------------------------------------------
// 4. Benchmark testi
// -------------------------------------------
func BenchmarkTopla(b *testing.B) {
    for i := 0; i < b.N; i++ {
        Topla(10, 20)
    }
}

// -------------------------------------------
// 5. TestMain - butun testler ucun setup/teardown
// -------------------------------------------
func TestMain(m *testing.M) {
    // Setup (testlerden evvel)
    fmt.Println("Testler baslanir...")

    // Testleri islet
    kod := m.Run()

    // Teardown (testlerden sonra)
    fmt.Println("Testler bitdi")

    os.Exit(kod)
}
`

	// TEST EMRLERI:
	fmt.Println("=== TEST EMRLERI ===")
	fmt.Println("go test              - cari paketde testleri islet")
	fmt.Println("go test ./...        - butun paketlerde testleri islet")
	fmt.Println("go test -v           - detalli cixis ile")
	fmt.Println("go test -run TestTopla - yalniz TestTopla islet")
	fmt.Println("go test -count=1     - cache-siz islet")
	fmt.Println("go test -cover       - code coverage goster")
	fmt.Println("go test -bench=.     - benchmark testlerini islet")
	fmt.Println("go test -race        - race condition yoxla")

	_ = testKodu
}
