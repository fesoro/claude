package main

import (
	"fmt"
	"unsafe"
)

// ===============================================
// UNSAFE PAKET VE CGO ESASLARI
// ===============================================

// unsafe paketi Go-nun tip tehlukesizlik sistemini kecmeye imkan verir
// Yalniz zeruri hallarda istifade edin - sehv istifade proqrami crash ede biler
// CGO - Go-dan C kodunu cagirmaga imkan veren mexanizmdir

// -------------------------------------------
// Numune struct-lar
// -------------------------------------------

type DaxiliStrukt struct {
	A byte    // 1 bayt
	B int32   // 4 bayt
	C byte    // 1 bayt
	D int64   // 8 bayt
}

type OptimalStrukt struct {
	D int64   // 8 bayt
	B int32   // 4 bayt
	A byte    // 1 bayt
	C byte    // 1 bayt
	// Padding az olur cunki boyuk tiplar evvel gelir
}

type Shexs struct {
	Ad  string
	Yas int
	Boy float64
}

func main() {

	// =============================================
	// 1. unsafe.Sizeof - tipin olcusu (bayt)
	// =============================================
	fmt.Println("=== unsafe.Sizeof ===")

	fmt.Println("bool olcusu:    ", unsafe.Sizeof(true))         // 1
	fmt.Println("int8 olcusu:    ", unsafe.Sizeof(int8(0)))      // 1
	fmt.Println("int16 olcusu:   ", unsafe.Sizeof(int16(0)))     // 2
	fmt.Println("int32 olcusu:   ", unsafe.Sizeof(int32(0)))     // 4
	fmt.Println("int64 olcusu:   ", unsafe.Sizeof(int64(0)))     // 8
	fmt.Println("float64 olcusu: ", unsafe.Sizeof(float64(0)))   // 8
	fmt.Println("string olcusu:  ", unsafe.Sizeof("salam"))      // 16 (pointer + len)
	fmt.Println("slice olcusu:   ", unsafe.Sizeof([]int{}))      // 24 (pointer + len + cap)

	// -------------------------------------------
	// Struct padding - yaddash hizalanmasi
	// -------------------------------------------
	fmt.Println("\n=== Struct Padding ===")

	// DaxiliStrukt - sehv siralama, artiq padding
	fmt.Println("DaxiliStrukt olcusu: ", unsafe.Sizeof(DaxiliStrukt{}))
	// A(1) + padding(3) + B(4) + C(1) + padding(7) + D(8) = 24

	// OptimalStrukt - duzgun siralama, az padding
	fmt.Println("OptimalStrukt olcusu:", unsafe.Sizeof(OptimalStrukt{}))
	// D(8) + B(4) + A(1) + C(1) + padding(2) = 16

	// =============================================
	// 2. unsafe.Alignof - hizalanma televi
	// =============================================
	fmt.Println("\n=== unsafe.Alignof ===")

	// Her tipin hizalanma televi var - yaddashda neche baytin qati unvaninda olmalidir
	fmt.Println("byte alignof:   ", unsafe.Alignof(byte(0)))     // 1
	fmt.Println("int16 alignof:  ", unsafe.Alignof(int16(0)))    // 2
	fmt.Println("int32 alignof:  ", unsafe.Alignof(int32(0)))    // 4
	fmt.Println("int64 alignof:  ", unsafe.Alignof(int64(0)))    // 8
	fmt.Println("string alignof: ", unsafe.Alignof(""))          // 8

	// =============================================
	// 3. unsafe.Offsetof - struct sahesinin ofseti
	// =============================================
	fmt.Println("\n=== unsafe.Offsetof ===")

	s := Shexs{}
	fmt.Println("Ad ofseti: ", unsafe.Offsetof(s.Ad))   // 0
	fmt.Println("Yas ofseti:", unsafe.Offsetof(s.Yas))   // 16
	fmt.Println("Boy ofseti:", unsafe.Offsetof(s.Boy))   // 24

	// =============================================
	// 4. unsafe.Pointer - pointer cevirmeleri
	// =============================================
	fmt.Println("\n=== unsafe.Pointer ===")

	// unsafe.Pointer her hansi pointer tipine cevirmek ucun "korpu" rolunu oynayir
	// Go-da normal halda *int -> *float64 cevirme mumkun deyil
	// Amma unsafe.Pointer vasitesile mumkundur

	// --- Numune: int64 -> float64 bit seviyyesinde cevrilme ---
	deger := int64(42)
	// Addim 1: *int64 -> unsafe.Pointer -> *float64
	floatPtr := (*float64)(unsafe.Pointer(&deger))
	fmt.Printf("int64 %d-in float64 bit pattern-i: %f\n", deger, *floatPtr)

	// --- Numune: iki ferqli tipin eyni yaddashi paylashmasi ---
	var eded uint32 = 0x41424344 // ASCII: A=41, B=42, C=43, D=44
	baytlar := (*[4]byte)(unsafe.Pointer(&eded))
	fmt.Printf("uint32 0x%X baytlari: %v\n", eded, baytlar)

	// =============================================
	// 5. Struct sahelerine ofset ile erishmek
	// =============================================
	fmt.Println("\n=== Struct sahelerine ofset ile erishmek ===")

	shexs := Shexs{Ad: "Orxan", Yas: 30, Boy: 1.80}

	// Yas sahesin unsafe.Pointer + ofset ile deyishmek
	shexsPtr := unsafe.Pointer(&shexs)
	yasOffset := unsafe.Offsetof(shexs.Yas)

	// Pointer arifmetikasi: baza + ofset = sahenin unvani
	yasPtr := (*int)(unsafe.Pointer(uintptr(shexsPtr) + yasOffset))
	*yasPtr = 25 // Yasi deyishdik

	fmt.Println("Yeni yas:", shexs.Yas) // 25

	// Boy sahesine erishmek
	boyOffset := unsafe.Offsetof(shexs.Boy)
	boyPtr := (*float64)(unsafe.Pointer(uintptr(shexsPtr) + boyOffset))
	*boyPtr = 1.85

	fmt.Println("Yeni boy:", shexs.Boy) // 1.85

	// =============================================
	// 6. uintptr qaydalar ve tehlukeler
	// =============================================
	fmt.Println("\n=== uintptr qaydalar ===")

	// MUHUM QAYDALAR:
	// 1. uintptr sadece eded tipdir - GC onu pointer kimi gormur
	// 2. uintptr-i uzun muddet saxlamaq tehlukedir - GC obyekti kocure biler
	// 3. Pointer arifmetikasi BIR ifadede olmalidir:
	//    DUZGUN:  (*int)(unsafe.Pointer(uintptr(p) + offset))
	//    SEHV:    tmp := uintptr(p); ptr := (*int)(unsafe.Pointer(tmp + offset))

	// Numune - duzgun istifade
	massiv := [5]int{10, 20, 30, 40, 50}
	elemPtr := unsafe.Pointer(&massiv[0])
	elemOlcu := unsafe.Sizeof(massiv[0])

	// 3-cu elemente erishmek (index 2)
	ucuncuPtr := (*int)(unsafe.Pointer(uintptr(elemPtr) + 2*elemOlcu))
	fmt.Println("3-cu element:", *ucuncuPtr) // 30

	// =============================================
	// 7. String ve Slice daxili strukturu
	// =============================================
	fmt.Println("\n=== String/Slice daxili strukturu ===")

	// Go-da string daxilen 2 sahedir: pointer + len
	type stringHeader struct {
		Data uintptr
		Len  int
	}

	// Go-da slice daxilen 3 sahedir: pointer + len + cap
	type sliceHeader struct {
		Data uintptr
		Len  int
		Cap  int
	}

	metn := "Salam Dunya"
	sh := (*stringHeader)(unsafe.Pointer(&metn))
	fmt.Printf("String: data=%x, len=%d\n", sh.Data, sh.Len)

	dilim := make([]int, 5, 10)
	slh := (*sliceHeader)(unsafe.Pointer(&dilim))
	fmt.Printf("Slice: data=%x, len=%d, cap=%d\n", slh.Data, slh.Len, slh.Cap)

	// =============================================
	// 8. CGO Esaslari (komment kimi)
	// =============================================
	fmt.Println("\n=== CGO Esaslari (komment numuneler) ===")

	// CGO - Go-dan C funksiyalarini cagirmaga imkan verir
	// Istifade ucun: import "C" yazmaq lazimdir
	//
	// --- Sadə CGO numunesi (ayri faylda ishledir): ---
	//
	// /*
	// #include <stdio.h>
	// #include <stdlib.h>
	//
	// void salam_c() {
	//     printf("C-den salam!\n");
	// }
	//
	// int topla_c(int a, int b) {
	//     return a + b;
	// }
	// */
	// import "C"
	//
	// func main() {
	//     C.salam_c()
	//     netice := C.topla_c(C.int(10), C.int(20))
	//     fmt.Println("C topla:", int(netice))
	//
	//     // C string ile ishlemek
	//     cs := C.CString("Go-dan gelen metn")
	//     defer C.free(unsafe.Pointer(cs)) // MUHUM: C.free ile azad etmek lazimdir!
	//     fmt.Println(C.GoString(cs))
	// }
	//
	// --- CGO compile etmek ucun: ---
	// CGO_ENABLED=1 go build main.go
	//
	// --- CGO ile C kitabxanalari baglamaq: ---
	// // #cgo LDFLAGS: -lm
	// // #include <math.h>
	// import "C"
	// netice := C.sqrt(C.double(144.0))

	// =============================================
	// 9. CGO Performans Meseleleri
	// =============================================
	fmt.Println("\n=== CGO Performans Meseleleri ===")

	// CGO cagirishi normal Go funksiya cagirishindan ~100x yavashdir
	// Sebebler:
	// 1. Go stack -> C stack kecidi lazimdir
	// 2. Go scheduler goroutine-i OS thread-e baglayir
	// 3. GC CGO cagirishi zamani pointer-leri izleye bilmir
	//
	// TOVSIYYELER:
	// - Kicik funksiyalari CGO ile cagirmayin - Go-da yazin
	// - Batch edin: 1000 kicik cagiris yerine 1 boyuk cagiris
	// - Sik cagirilan yerlerde CGO-dan qacinmaq
	// - Alternativ: syscall paketi (Linux/Unix-de)

	fmt.Println("CGO cagiris overhead: ~100-150ns per call")
	fmt.Println("Normal Go cagiris:    ~1-5ns per call")

	// =============================================
	// 10. Ne zaman unsafe istifade etmeli?
	// =============================================
	fmt.Println("\n=== Ne zaman unsafe istifade etmeli? ===")

	// ISTIFADE EDIN:
	// 1. Performans kritik yerlerde (zero-copy cevirme)
	// 2. Sistem seviyye proqramlasdirma (OS, driver)
	// 3. C kitabxanalarile inteqrasiya
	// 4. Struct padding analizi ve optimizasiyasi
	// 5. reflect paketi ile ishlemeyen hallar

	// ISTIFADE ETMEYIN:
	// 1. Normal biznes logikasinda
	// 2. Daha sadə yol varsa
	// 3. Kodun portativ olmasi vacibdirse
	// 4. Komandadaki haminin unsafe bilmediyi hallarda
	// 5. Test yazmaq cetinleshirse

	fmt.Println("Qayda: Evvelce tehlukesiz yol tapin.")
	fmt.Println("unsafe yalniz son cera olaraq istifade edin!")

	// =============================================
	// 11. Real-world istifade numunesli
	// =============================================
	fmt.Println("\n=== Real-world numuneler ===")

	// Numune 1: Zero-copy string -> []byte cevirme (yalniz oxumaq ucun!)
	metn2 := "Bu zero-copy numunedir"
	b := unsafeStringToBytes(metn2)
	fmt.Println("Zero-copy bytes:", string(b))

	// Numune 2: Struct olculerini analiz etmek
	strukturAnaliz()
}

// -------------------------------------------
// Zero-copy string -> []byte (YALNIZ OXUMAQ UCUN!)
// Deyishmek OLMAZ - undefined behavior olar
// -------------------------------------------
func unsafeStringToBytes(s string) []byte {
	return unsafe.Slice(unsafe.StringData(s), len(s))
}

// -------------------------------------------
// Struct padding analizi
// -------------------------------------------
func strukturAnaliz() {
	fmt.Println("\n--- Struct Padding Analizi ---")

	type Misal struct {
		A bool    // 1 bayt
		B float64 // 8 bayt
		C int32   // 4 bayt
	}

	var m Misal
	fmt.Printf("Misal olcusu: %d bayt\n", unsafe.Sizeof(m))
	fmt.Printf("  A offset=%d, olcu=%d\n", unsafe.Offsetof(m.A), unsafe.Sizeof(m.A))
	fmt.Printf("  B offset=%d, olcu=%d\n", unsafe.Offsetof(m.B), unsafe.Sizeof(m.B))
	fmt.Printf("  C offset=%d, olcu=%d\n", unsafe.Offsetof(m.C), unsafe.Sizeof(m.C))

	// Optimal versiya
	type MisalOptimal struct {
		B float64 // 8 bayt
		C int32   // 4 bayt
		A bool    // 1 bayt
	}

	var mo MisalOptimal
	fmt.Printf("\nMisalOptimal olcusu: %d bayt\n", unsafe.Sizeof(mo))
	fmt.Printf("  B offset=%d, olcu=%d\n", unsafe.Offsetof(mo.B), unsafe.Sizeof(mo.B))
	fmt.Printf("  C offset=%d, olcu=%d\n", unsafe.Offsetof(mo.C), unsafe.Sizeof(mo.C))
	fmt.Printf("  A offset=%d, olcu=%d\n", unsafe.Offsetof(mo.A), unsafe.Sizeof(mo.A))

	fmt.Println("\nQeyd: Saheleri boyukden kiciye siralayanda padding azalir!")
}
