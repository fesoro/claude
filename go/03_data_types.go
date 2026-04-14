package main

import "fmt"

// ===============================================
// MELUMAT TIPLERI (DATA TYPES)
// ===============================================

func main() {

	// -------------------------------------------
	// 1. TAM EDEDLER (Integers)
	// -------------------------------------------
	// int     - platforma asili (32 ve ya 64 bit)
	// int8    - -128 den 127 ye
	// int16   - -32768 den 32767 ye
	// int32   - -2 milyard den 2 milyard a
	// int64   - cox boyuk ededler
	// uint    - yalniz musbet (unsigned)
	// uint8   - 0 dan 255 e (byte ile eynidir)
	// uint16  - 0 dan 65535 e
	// uint32  - 0 dan 4 milyarda
	// uint64  - cox boyuk musbet ededler

	var kicik int8 = 127
	var boyuk int64 = 9223372036854775807
	var musbet uint = 42

	fmt.Println("int8:", kicik)
	fmt.Println("int64:", boyuk)
	fmt.Println("uint:", musbet)

	// -------------------------------------------
	// 2. ONLUQ EDEDLER (Floating Point)
	// -------------------------------------------
	// float32 - 7 reqem deqiqlik
	// float64 - 15 reqem deqiqlik (default, en cox istifade olunan)

	var qiymet float64 = 19.99
	var pi float32 = 3.14

	fmt.Println("Qiymet:", qiymet)
	fmt.Println("Pi:", pi)

	// -------------------------------------------
	// 3. STRING (Metn)
	// -------------------------------------------
	// Qosa dirmaq ("") ile yazilir
	// Deyisdirile bilmez (immutable)

	ad := "Orkhan"
	soyad := "Shukurlu"
	tam := ad + " " + soyad // string birlesdirme (concatenation)

	fmt.Println("Tam ad:", tam)
	fmt.Println("Ad uzunlugu:", len(ad)) // byte sayisi

	// Coxsetirli string - backtick (`) ile
	metn := `Bu birinci setir
Bu ikinci setir
Bu ucuncu setir`
	fmt.Println(metn)

	// -------------------------------------------
	// 4. BOOL (Mentiqi tip)
	// -------------------------------------------
	// Yalniz iki deyer ola biler: true ve ya false

	dogru := true
	yanlis := false

	fmt.Println("Dogru:", dogru)
	fmt.Println("Yanlis:", yanlis)
	fmt.Println("Ve:", dogru && yanlis)   // false (AND)
	fmt.Println("Veya:", dogru || yanlis) // true  (OR)
	fmt.Println("Deyil:", !dogru)         // false (NOT)

	// -------------------------------------------
	// 5. BYTE ve RUNE
	// -------------------------------------------
	// byte = uint8 (ASCII simvol)
	// rune = int32 (Unicode simvol - UTF-8)

	var harf byte = 'A' // ASCII deyer: 65
	var emoji rune = 'Z'

	fmt.Println("Byte:", harf)   // 65
	fmt.Println("Rune:", emoji)

	// String-den byte ve rune almaq
	s := "Salam"
	fmt.Println("Birinci byte:", s[0])         // 83 (S-nin ASCII kodu)
	fmt.Printf("Birinci herf: %c\n", s[0])    // S

	// MUHUM: string[i] byte qaytarir, rune deyil!
	// Azerbaycan herfleri (e, u, o, s, c, g, i) UTF-8-de 2 byte tutur
	az := "Şəhər"
	fmt.Println("len(Şəhər):", len(az))          // byte sayisi (herflerin sayi deyil!)
	fmt.Println("rune sayi:", len([]rune(az)))    // esl herf sayisi
	// String uzerinde duzgun gezmek ucun range istifade edin:
	for i, r := range az {
		fmt.Printf("index %d: %c (rune: %d)\n", i, r, r)
	}

	// -------------------------------------------
	// 6. TIP CEVIRMESI (Type Conversion)
	// -------------------------------------------
	// Go-da avtomatik tip cevirmesi YOXDUR
	// Aciq sekilde cevirmelisiz

	var tam_eded int = 42
	var onluq float64 = float64(tam_eded) // int -> float64
	var yeniden int = int(onluq)           // float64 -> int

	fmt.Println("int -> float64:", onluq)
	fmt.Println("float64 -> int:", yeniden)

	// int tiplerini bir-birine cevirme
	var a int32 = 100
	var b int64 = int64(a)
	fmt.Println("int32 -> int64:", b)

	// String ve eded cevirmeleri "strconv" paketi ile olur (sonraki dersde)

	// -------------------------------------------
	// 7. TIPINI OYRENMEK
	// -------------------------------------------
	x := 42
	y := 3.14
	z := "hello"
	w := true

	fmt.Printf("x tipi: %T\n", x) // int
	fmt.Printf("y tipi: %T\n", y) // float64
	fmt.Printf("z tipi: %T\n", z) // string
	fmt.Printf("w tipi: %T\n", w) // bool
}
