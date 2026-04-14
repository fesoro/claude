package main

import (
	"fmt"
	"regexp"
)

// ===============================================
// REGEXP - REGULYAR IFADELER
// ===============================================

// Metn icinde naxislari (pattern) axtarmaq, yoxlamaq ve deyismek ucun

func main() {

	// -------------------------------------------
	// 1. Sadə uygunluq yoxlamasi (match)
	// -------------------------------------------
	uygun, _ := regexp.MatchString(`go`, "I love golang")
	fmt.Println("Uygun:", uygun) // true

	// -------------------------------------------
	// 2. Kompilyasiya olunmus regexp (daha suretli)
	// -------------------------------------------
	// Tekrar istifade ucun HER ZAMAN Compile edin
	re := regexp.MustCompile(`\d+`) // reqemleri tap

	fmt.Println("Tapildi:", re.FindString("Menim 3 pishiyim var")) // "3"

	// Butun uygunluqlari tap
	hamisi := re.FindAllString("10 alma, 20 armud, 30 nar", -1)
	fmt.Println("Hamisi:", hamisi) // [10 20 30]

	// -------------------------------------------
	// 3. Esas naxislar (patterns)
	// -------------------------------------------
	//  .        - istənilən bir simvol
	//  \d       - reqem (0-9)
	//  \D       - reqem olmayan
	//  \w       - herf, reqem ve ya _ (word character)
	//  \W       - word character olmayan
	//  \s       - boslluq (space, tab, newline)
	//  \S       - boslluq olmayan
	//  [abc]    - a, b ve ya c
	//  [a-z]    - a-dan z-ye
	//  [^abc]   - a, b, c xaric
	//  ^        - setirin baslangici
	//  $        - setirin sonu
	//  *        - 0 ve ya daha cox tekrar
	//  +        - 1 ve ya daha cox tekrar
	//  ?        - 0 ve ya 1 tekrar
	//  {n}      - tam n tekrar
	//  {n,m}    - n-den m-ye qeder tekrar
	//  (...)    - qrup
	//  |        - veya (alternation)

	// -------------------------------------------
	// 4. Praktik numuneler
	// -------------------------------------------

	// Email yoxlamasi
	emailRe := regexp.MustCompile(`^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$`)
	fmt.Println("Email dogru:", emailRe.MatchString("orkhan@mail.az"))  // true
	fmt.Println("Email yanlis:", emailRe.MatchString("yanlis@"))        // false

	// Telefon nomresi
	telRe := regexp.MustCompile(`^\+994\d{9}$`)
	fmt.Println("Tel dogru:", telRe.MatchString("+994501234567"))  // true
	fmt.Println("Tel yanlis:", telRe.MatchString("0501234567"))    // false

	// IP adres (sadə)
	ipRe := regexp.MustCompile(`^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$`)
	fmt.Println("IP dogru:", ipRe.MatchString("192.168.1.1"))    // true

	// -------------------------------------------
	// 5. Qruplar ile tutma (capture groups)
	// -------------------------------------------
	tarixRe := regexp.MustCompile(`(\d{4})-(\d{2})-(\d{2})`)
	uygunlug := tarixRe.FindStringSubmatch("Tarix: 2024-03-15 idi")
	if uygunlug != nil {
		fmt.Println("Tam:", uygunlug[0])  // 2024-03-15
		fmt.Println("Il:", uygunlug[1])   // 2024
		fmt.Println("Ay:", uygunlug[2])   // 03
		fmt.Println("Gun:", uygunlug[3])  // 15
	}

	// Adli qruplar
	adliRe := regexp.MustCompile(`(?P<ad>\w+):(?P<deyer>\d+)`)
	n := adliRe.FindStringSubmatch("yas:25")
	if n != nil {
		for i, ad := range adliRe.SubexpNames() {
			if i != 0 && ad != "" {
				fmt.Printf("%s = %s\n", ad, n[i])
			}
		}
	}

	// -------------------------------------------
	// 6. Deyisdirme (replace)
	// -------------------------------------------
	re2 := regexp.MustCompile(`\d+`)
	yeni := re2.ReplaceAllString("Menim 3 pishiyim ve 2 itim var", "X")
	fmt.Println("Deyisdirilmis:", yeni) // "Menim X pishiyim ve X itim var"

	// Funksiya ile deyisdirme
	re3 := regexp.MustCompile(`\d+`)
	ikiqat := re3.ReplaceAllStringFunc("qiymet: 10, endirim: 5", func(s string) string {
		// Her reqemi tapib ikiqat artir
		return s + s
	})
	fmt.Println("Ikiqat:", ikiqat) // qiymet: 1010, endirim: 55

	// -------------------------------------------
	// 7. Bolme (split)
	// -------------------------------------------
	bolRe := regexp.MustCompile(`[,;\s]+`) // vergul, noqteli-vergul ve ya boslluq
	parcalar := bolRe.Split("alma, armud; nar  uzum", -1)
	fmt.Println("Parcalar:", parcalar) // [alma armud nar uzum]

	// -------------------------------------------
	// 8. Index - movqeyi tapmaq
	// -------------------------------------------
	re4 := regexp.MustCompile(`\b\w{5}\b`) // 5 herfli sozler
	tapildi := re4.FindAllString("salam dunya hello world Go", -1)
	fmt.Println("5 herfli:", tapildi) // [salam dunya hello world]
}
