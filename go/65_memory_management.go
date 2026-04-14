package main

import (
	"fmt"
	"runtime"
	"strings"
	"unsafe"
)

// ===============================================
// YADDAS IDARE ETME (MEMORY MANAGEMENT)
// ===============================================

// Go-da yaddas avtomatik idare olunur (Garbage Collector)
// Amma neyin harada saxlandigini bilmek performans ucun vacibdir

func main() {

	// -------------------------------------------
	// 1. STACK vs HEAP
	// -------------------------------------------
	fmt.Println("=== Stack vs Heap ===")

	// STACK:
	// - Suretli (funksiya cagirisi ile otomatik ayrilir/azad olunur)
	// - Funksiya bitende otomatik temizlenir
	// - Olcusu mehdudur (default ~1MB, goroutine ucun 8KB-den baslanir)
	// - Lokal deyiskenler adeten stack-de olur

	// HEAP:
	// - Nisbeten yavas (GC idare edir)
	// - Paylasilan melumat burada saxlanir
	// - Olcusu boyukdur (sistem yaddasi)
	// - Pointer qaytarilan deyiskenler heap-e "qacar" (escape)

	// Stack-de qalan (escape etmir)
	x := 42       // stack
	y := x + 1    // stack
	_ = y

	// Heap-e qacan (escape edir)
	p := heapeQac() // pointer qaytarir -> heap-de olacaq
	fmt.Println("Heap-den:", *p)

	// -------------------------------------------
	// 2. ESCAPE ANALYSIS
	// -------------------------------------------
	// Go kompilyatoru qerar verir: stack ve ya heap?
	// Yoxlamaq ucun: go build -gcflags="-m" main.go

	fmt.Println("\n=== Escape Analysis ===")
	fmt.Println("Yoxlamaq ucun: go build -gcflags='-m' main.go")
	fmt.Println("Detalli:       go build -gcflags='-m -m' main.go")

	// Escape eden hallar:
	// 1. Pointer qaytarilir
	// 2. Interface-e teyinat (fmt.Println boxing edir)
	// 3. Closure-da xarici deyiskene istinad
	// 4. Slice/map boyuk ve ya dinamik olculu olanda
	// 5. Channel vasitesile gonderilende

	// Stack-de qalir
	a := [3]int{1, 2, 3} // kicik massiv, escape etmir
	_ = a

	// Heap-e qacar
	b := make([]int, 10000) // boyuk slice, heap-e qacar
	_ = b

	// -------------------------------------------
	// 3. GARBAGE COLLECTOR (GC)
	// -------------------------------------------
	fmt.Println("\n=== Garbage Collector ===")

	var m runtime.MemStats
	runtime.ReadMemStats(&m)
	fmt.Printf("GC sayi: %d\n", m.NumGC)
	fmt.Printf("Son GC: %d ns\n", m.PauseNs[(m.NumGC+255)%256])

	// El ile GC cagirmaq (nadir hallarda)
	runtime.GC()

	// GC-ni tenzimlemek
	// GOGC=100 (default) - heap ikiqat boyuyende GC isleyir
	// GOGC=50  - daha tez-tez GC (az yaddas, cox CPU)
	// GOGC=200 - daha nadir GC (cox yaddas, az CPU)
	// GOGC=off - GC-ni sondur (xususi hallar ucun)
	// runtime/debug.SetGCPercent(100)

	// GOMEMLIMIT (Go 1.19+) - yaddas limiti
	// GOMEMLIMIT=1GiB - 1GB-dan cox istifade etme

	// -------------------------------------------
	// 4. SIZEOF - Tipin yaddas olcusu
	// -------------------------------------------
	fmt.Println("\n=== Tip Olculeri ===")
	fmt.Printf("bool:    %d byte\n", unsafe.Sizeof(bool(false)))
	fmt.Printf("int8:    %d byte\n", unsafe.Sizeof(int8(0)))
	fmt.Printf("int16:   %d byte\n", unsafe.Sizeof(int16(0)))
	fmt.Printf("int32:   %d byte\n", unsafe.Sizeof(int32(0)))
	fmt.Printf("int64:   %d byte\n", unsafe.Sizeof(int64(0)))
	fmt.Printf("int:     %d byte\n", unsafe.Sizeof(int(0)))
	fmt.Printf("float32: %d byte\n", unsafe.Sizeof(float32(0)))
	fmt.Printf("float64: %d byte\n", unsafe.Sizeof(float64(0)))
	fmt.Printf("string:  %d byte\n", unsafe.Sizeof(""))       // 16 (pointer + len)
	fmt.Printf("slice:   %d byte\n", unsafe.Sizeof([]int{}))  // 24 (pointer + len + cap)
	fmt.Printf("pointer: %d byte\n", unsafe.Sizeof((*int)(nil)))

	// -------------------------------------------
	// 5. STRUCT PADDING ve ALIGNMENT
	// -------------------------------------------
	fmt.Println("\n=== Struct Padding ===")

	// Yanlis siralama - cox yaddas istifade edir
	type Pis struct {
		a bool    // 1 byte + 7 byte padding
		b float64 // 8 byte
		c bool    // 1 byte + 3 byte padding
		d int32   // 4 byte
	} // Cem: 24 byte

	// Yaxsi siralama - az yaddas
	type Yaxsi struct {
		b float64 // 8 byte
		d int32   // 4 byte
		a bool    // 1 byte
		c bool    // 1 byte + 2 byte padding
	} // Cem: 16 byte

	fmt.Printf("Pis struct:  %d byte\n", unsafe.Sizeof(Pis{}))   // 24
	fmt.Printf("Yaxsi struct: %d byte\n", unsafe.Sizeof(Yaxsi{})) // 16
	fmt.Println("Qayda: Saheleri boyukden kiciye siralayiniz")

	// -------------------------------------------
	// 6. STRING YADDAS OPTIMIZASIYASI
	// -------------------------------------------
	fmt.Println("\n=== String Optimizasiya ===")

	// YANLIS: + ile birlesdirme (her defe yeni string yaranir)
	pis := func() string {
		s := ""
		for i := 0; i < 100; i++ {
			s += "x" // her defe yeni yaddas ayrilir!
		}
		return s
	}

	// DOGRU: strings.Builder (buffer istifade edir)
	yaxsi := func() string {
		var sb strings.Builder
		sb.Grow(100) // evvelceden yer ayir
		for i := 0; i < 100; i++ {
			sb.WriteString("x")
		}
		return sb.String()
	}

	_ = pis()
	_ = yaxsi()
	fmt.Println("strings.Builder istifade edin!")

	// -------------------------------------------
	// 7. SLICE YADDAS TELELERI
	// -------------------------------------------
	fmt.Println("\n=== Slice Teleleri ===")

	// Tele 1: Boyuk slice-dan kicik dilim
	boyukSlice := make([]byte, 1_000_000) // 1MB
	kicikDilim := boyukSlice[:10]          // yalniz 10 byte lazim
	// AMA: kicikDilim hala 1MB-lik massive istinad edir!
	// GC 1MB-ni temizleye bilmez

	// Helli: kopyalayiniz
	kopya := make([]byte, 10)
	copy(kopya, boyukSlice[:10])
	_ = kicikDilim
	_ = kopya
	// Indi boyukSlice GC terefinden temizlene biler

	// Tele 2: Slice sifirlanmasi
	s := []int{1, 2, 3, 4, 5}
	s = s[:0]        // uzunlugu sifirla amma tutumu saxla
	// s = nil        // tamamilə azad et (tutumu da)
	_ = s

	// -------------------------------------------
	// 8. SYNC.POOL ile yeniden istifade
	// -------------------------------------------
	fmt.Println("\n=== Yaddas Tovsiyələri ===")
	fmt.Println("1. Slice/map-a evvelceden tutum verin: make([]T, 0, n)")
	fmt.Println("2. strings.Builder istifade edin (+ deyil)")
	fmt.Println("3. Struct sahelerini boyukden kiciye siralayin")
	fmt.Println("4. Boyuk slice-dan kicik dilim alirsinizsa, kopyalayin")
	fmt.Println("5. sync.Pool ile muveqqeti obyektleri yeniden istifade edin")
	fmt.Println("6. Pointer-leri lazim olmayanda istifade etmeyin (heap escape)")
	fmt.Println("7. go build -gcflags='-m' ile escape analysis edin")
	fmt.Println("8. GOGC ve GOMEMLIMIT ile GC-ni tenzimleyin")
}

// Heap-e qacan funksiya
func heapeQac() *int {
	x := 42
	return &x // x heap-e qacar cunki pointer qaytarilir
}
