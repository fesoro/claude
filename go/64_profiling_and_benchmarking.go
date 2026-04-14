package main

import (
	"fmt"
	"os"
	"runtime"
	"runtime/pprof"
	"time"
)

// ===============================================
// PROFILING VE BENCHMARKING
// ===============================================

// Proqramin harada yavasladigini ve cox yaddas istifade etdiyini tapmaq ucun
// Go-da daxili pprof aleti var - xarici alet lazim deyil

func main() {

	// -------------------------------------------
	// 1. RUNTIME MELUMATLARI
	// -------------------------------------------
	fmt.Println("=== Runtime Melumatlari ===")

	var m runtime.MemStats
	runtime.ReadMemStats(&m)

	fmt.Printf("Ayrilmis yaddas:  %d KB\n", m.Alloc/1024)
	fmt.Printf("Toplam ayrilmis:  %d KB\n", m.TotalAlloc/1024)
	fmt.Printf("Sistem yaddasi:   %d KB\n", m.Sys/1024)
	fmt.Printf("GC sayi:          %d\n", m.NumGC)
	fmt.Printf("Goroutine sayi:   %d\n", runtime.NumGoroutine())
	fmt.Printf("CPU sayisi:       %d\n", runtime.NumCPU())
	fmt.Printf("Go versiyasi:     %s\n", runtime.Version())

	// -------------------------------------------
	// 2. CPU PROFILING (proqrammatik)
	// -------------------------------------------
	fmt.Println("\n=== CPU Profiling ===")

	cpuFile, err := os.Create("cpu.prof")
	if err != nil {
		fmt.Println("Xeta:", err)
		return
	}
	defer cpuFile.Close()

	pprof.StartCPUProfile(cpuFile)
	// ... burada profilin alinacaq kod isleyir ...
	agirIs()
	pprof.StopCPUProfile()

	fmt.Println("cpu.prof yazildi")
	fmt.Println("Analiz ucun: go tool pprof cpu.prof")

	// -------------------------------------------
	// 3. MEMORY PROFILING
	// -------------------------------------------
	fmt.Println("\n=== Memory Profiling ===")

	// Yaddas seren is gor
	yaddasisEren()

	memFile, err := os.Create("mem.prof")
	if err != nil {
		fmt.Println("Xeta:", err)
		return
	}
	defer memFile.Close()

	runtime.GC() // GC islet ki deqiq netice olsun
	pprof.WriteHeapProfile(memFile)

	fmt.Println("mem.prof yazildi")
	fmt.Println("Analiz ucun: go tool pprof mem.prof")

	// -------------------------------------------
	// 4. VAXT OLCME (manual benchmarking)
	// -------------------------------------------
	fmt.Println("\n=== Vaxt Olcme ===")

	baslangic := time.Now()
	agirIs()
	muddet := time.Since(baslangic)
	fmt.Printf("agirIs sureti: %v\n", muddet)

	// Daha deqiq olcme
	baslangic2 := time.Now()
	for i := 0; i < 100; i++ {
		yungulIs()
	}
	ortaMuddet := time.Since(baslangic2) / 100
	fmt.Printf("yungulIs orta sureti: %v\n", ortaMuddet)

	// -------------------------------------------
	// 5. BENCHMARK TESTLERI (_test.go faylinda)
	// -------------------------------------------
	benchKod := `
// fayl: performance_test.go
package main

import (
    "fmt"
    "strings"
    "testing"
)

// Sadə benchmark
func BenchmarkTopla(b *testing.B) {
    for i := 0; i < b.N; i++ {
        _ = 2 + 3
    }
}

// String birlesdirme muqayisesi
func BenchmarkStringConcat(b *testing.B) {
    for i := 0; i < b.N; i++ {
        s := ""
        for j := 0; j < 100; j++ {
            s += "x"  // YAVAS - her defə yeni string yaranir
        }
    }
}

func BenchmarkStringBuilder(b *testing.B) {
    for i := 0; i < b.N; i++ {
        var sb strings.Builder
        for j := 0; j < 100; j++ {
            sb.WriteString("x")  // SURETLI - buffer istifade edir
        }
        _ = sb.String()
    }
}

func BenchmarkSprintf(b *testing.B) {
    for i := 0; i < b.N; i++ {
        _ = fmt.Sprintf("ad: %s, yas: %d", "Orkhan", 25)
    }
}

// Yaddas ayirmasini olcmek
func BenchmarkSliceAppend(b *testing.B) {
    b.ReportAllocs() // ayirma hesabatini goster
    for i := 0; i < b.N; i++ {
        s := make([]int, 0)
        for j := 0; j < 1000; j++ {
            s = append(s, j)  // cox reallocation
        }
    }
}

func BenchmarkSlicePrealloc(b *testing.B) {
    b.ReportAllocs()
    for i := 0; i < b.N; i++ {
        s := make([]int, 0, 1000) // evvelceden tutum ver
        for j := 0; j < 1000; j++ {
            s = append(s, j)  // reallocation yoxdur
        }
    }
}

// Sub-benchmark
func BenchmarkMap(b *testing.B) {
    sizes := []int{10, 100, 1000, 10000}
    for _, size := range sizes {
        b.Run(fmt.Sprintf("size=%d", size), func(b *testing.B) {
            for i := 0; i < b.N; i++ {
                m := make(map[int]int, size)
                for j := 0; j < size; j++ {
                    m[j] = j
                }
            }
        })
    }
}
`
	fmt.Println(benchKod)

	// -------------------------------------------
	// 6. HTTP PPROF (canli profiling)
	// -------------------------------------------
	fmt.Println(`
=== HTTP PPROF (canli server profiling) ===

import _ "net/http/pprof"  // yalniz import edin, otomatik qeydiyyat olunur

func main() {
    // Debug endpoint-leri avtomatik elave olunur:
    // http://localhost:6060/debug/pprof/
    go http.ListenAndServe(":6060", nil)
    // ... esas proqram ...
}

Brauzerden: http://localhost:6060/debug/pprof/
CPU profil: go tool pprof http://localhost:6060/debug/pprof/profile?seconds=30
Heap:       go tool pprof http://localhost:6060/debug/pprof/heap
Goroutine:  go tool pprof http://localhost:6060/debug/pprof/goroutine

=== PPROF EMRLERI ===
go tool pprof cpu.prof           # interaktiv rejim
  top                            # en cox vaxt alan funksiyalar
  top10                          # ilk 10
  list funcName                  # funksiya detallar
  web                            # brauzerda qrafik (graphviz lazim)
  png                            # PNG shekil

go test -bench=. -benchmem              # benchmark + yaddas
go test -bench=. -benchtime=5s          # 5 saniye benchmark
go test -bench=. -count=5               # 5 defe tekrarla
go test -bench=. -cpuprofile=cpu.prof   # benchmark + CPU profil
go test -bench=. -memprofile=mem.prof   # benchmark + memory profil

=== PERFORMANS TOVSIYELER ===
1. strings.Builder istifade edin (+ ile birlesdirme deyil)
2. Slice-lara evvelceden tutum verin: make([]T, 0, n)
3. Map-lere evvelceden tutum verin: make(map[K]V, n)
4. sync.Pool ile obyektleri yeniden istifade edin
5. Pointer-leri lazim olmadiqda istifade etmeyin (escape analysis)
6. Interface-leri hot path-da azaldin
7. Goroutine-leri lazim olmadiqda yaratmayin
`)

	// Temizlik
	os.Remove("cpu.prof")
	os.Remove("mem.prof")
}

func agirIs() {
	toplam := 0
	for i := 0; i < 10_000_000; i++ {
		toplam += i
	}
}

func yungulIs() {
	_ = 2 + 2
}

func yaddasisEren() {
	// Coxlu yaddas ayir
	data := make([][]byte, 100)
	for i := range data {
		data[i] = make([]byte, 10000)
	}
	_ = data
}
