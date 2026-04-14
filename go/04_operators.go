package main

import "fmt"

// ===============================================
// OPERATORLAR (OPERATORS)
// ===============================================

func main() {

	// -------------------------------------------
	// 1. RIYAZI OPERATORLAR (Arithmetic)
	// -------------------------------------------
	a, b := 10, 3

	fmt.Println("Toplama:  ", a+b)  // 13
	fmt.Println("Cixma:    ", a-b)  // 7
	fmt.Println("Vurma:    ", a*b)  // 30
	fmt.Println("Bolme:    ", a/b)  // 3  (tam eded bolmesi - qaliq atilir)
	fmt.Println("Qaliq:    ", a%b)  // 1  (modulus - bolmeden qalan)

	// Float bolmesi
	x, y := 10.0, 3.0
	fmt.Println("Float bolme:", x/y) // 3.3333...

	// -------------------------------------------
	// 2. MUQAYISE OPERATORLARI (Comparison)
	// -------------------------------------------
	// Netice her zaman bool (true/false) olur

	fmt.Println("10 == 3:", a == b) // false (beraberdir?)
	fmt.Println("10 != 3:", a != b) // true  (ferqlidir?)
	fmt.Println("10 > 3: ", a > b)  // true  (boyukdur?)
	fmt.Println("10 < 3: ", a < b)  // false (kicikdir?)
	fmt.Println("10 >= 3:", a >= b) // true  (boyuk-beraberdir?)
	fmt.Println("10 <= 3:", a <= b) // false (kicik-beraberdir?)

	// -------------------------------------------
	// 3. MENTIQI OPERATORLAR (Logical)
	// -------------------------------------------
	d, e := true, false

	fmt.Println("true && false:", d && e) // false (VE - ikisi de true olmali)
	fmt.Println("true || false:", d || e) // true  (VEYA - biri true olmali)
	fmt.Println("!true:        ", !d)     // false (DEYIL - eksini verir)

	// Praktik misal
	yas := 25
	maas := 3000
	fmt.Println("Kredit ala biler:", yas >= 18 && maas >= 2000) // true

	// -------------------------------------------
	// 4. TEYINAT OPERATORLARI (Assignment)
	// -------------------------------------------
	c := 10
	fmt.Println("Baslangic:", c) // 10

	c += 5  // c = c + 5
	fmt.Println("c += 5:   ", c) // 15

	c -= 3  // c = c - 3
	fmt.Println("c -= 3:   ", c) // 12

	c *= 2  // c = c * 2
	fmt.Println("c *= 2:   ", c) // 24

	c /= 4  // c = c / 4
	fmt.Println("c /= 4:   ", c) // 6

	c %= 4  // c = c % 4
	fmt.Println("c %%= 4:  ", c) // 2

	// -------------------------------------------
	// 5. ARTIRIM/AZALMA (Increment/Decrement)
	// -------------------------------------------
	sayi := 5
	sayi++ // sayi = sayi + 1
	fmt.Println("sayi++:", sayi) // 6

	sayi-- // sayi = sayi - 1
	fmt.Println("sayi--:", sayi) // 5

	// QEYD: Go-da ++sayi ve --sayi (prefix) YOXDUR
	// QEYD: sayi++ ifade deyil, statement-dir (deyer qaytarmaz)

	// -------------------------------------------
	// 6. BIT OPERATORLARI (Bitwise) - qisa
	// -------------------------------------------
	p, q := 5, 3 // binary: 0101, 0011

	fmt.Println("AND:  ", p&q)  // 1  (0001)
	fmt.Println("OR:   ", p|q)  // 7  (0111)
	fmt.Println("XOR:  ", p^q)  // 6  (0110)
	fmt.Println("Left: ", p<<1) // 10 (1010) - sola surme
	fmt.Println("Right:", p>>1) // 2  (0010) - saga surme
}
