package main

import (
	"fmt"
	"strconv"
	"strings"
	"unsafe"
)

// ===============================================
// STRINGS VE STRCONV PAKETLERI
// ===============================================

// Go-da string immutable (deyisdirilmez) tipdir.
// String uzerinde her deyisiklik yeni string yaradir.
// Bu sebebden boyuk melumatlarla isliyende strings.Builder istifade edin.

func main() {

	// -------------------------------------------
	// 1. strings.Contains, HasPrefix, HasSuffix
	// -------------------------------------------

	fmt.Println("=== 1. Contains, HasPrefix, HasSuffix ===")

	metn := "Salam, Go dilini oyrenirik!"

	// Contains - stringin icinde alt-string varmi?
	fmt.Println(strings.Contains(metn, "Go"))    // true
	fmt.Println(strings.Contains(metn, "Java"))  // false

	// HasPrefix - stringin evvelinde varmi?
	fmt.Println(strings.HasPrefix(metn, "Salam")) // true
	fmt.Println(strings.HasPrefix(metn, "Go"))     // false

	// HasSuffix - stringin sonunda varmi?
	fmt.Println(strings.HasSuffix(metn, "!"))       // true
	fmt.Println(strings.HasSuffix(metn, "oyrenirik")) // false (sonunda ! var)

	// -------------------------------------------
	// 2. Replace, Split, Join
	// -------------------------------------------

	fmt.Println("\n=== 2. Replace, Split, Join ===")

	// Replace - stringde evezleme
	// Son parametr: neche defe evez etsin (-1 = hamisi)
	yeni := strings.Replace("aaa bbb aaa", "aaa", "ccc", 1)
	fmt.Println(yeni) // ccc bbb aaa

	hamisi := strings.ReplaceAll("aaa bbb aaa", "aaa", "ccc")
	fmt.Println(hamisi) // ccc bbb ccc

	// Split - stringi parcalara bol
	parcalar := strings.Split("alma,armud,nar", ",")
	fmt.Println(parcalar)    // [alma armud nar]
	fmt.Println(parcalar[0]) // alma

	// Join - parcalari birleshdir
	birleshmish := strings.Join(parcalar, " | ")
	fmt.Println(birleshmish) // alma | armud | nar

	// -------------------------------------------
	// 3. ToUpper, ToLower, TrimSpace
	// -------------------------------------------

	fmt.Println("\n=== 3. ToUpper, ToLower, TrimSpace ===")

	fmt.Println(strings.ToUpper("salam"))   // SALAM
	fmt.Println(strings.ToLower("SALAM"))   // salam

	// TrimSpace - evveldeki ve sondaki bosluqlari sil
	bosluglu := "   salam dunya   "
	fmt.Println(strings.TrimSpace(bosluglu)) // "salam dunya"

	// Trim - mueyyenlemish simvollari sil
	fmt.Println(strings.Trim("***salam***", "*")) // salam

	// TrimLeft, TrimRight - yalniz sol/sag terefden
	fmt.Println(strings.TrimLeft("###metn", "#"))  // metn
	fmt.Println(strings.TrimRight("metn###", "#")) // metn

	// -------------------------------------------
	// 4. Repeat, Count, Index
	// -------------------------------------------

	fmt.Println("\n=== 4. Repeat, Count, Index ===")

	// Repeat - stringi tekrarla
	fmt.Println(strings.Repeat("Go! ", 3)) // Go! Go! Go!

	// Count - alt-string neche defe var
	fmt.Println(strings.Count("banana", "a"))  // 3
	fmt.Println(strings.Count("banana", "na")) // 2

	// Index - alt-stringin ilk movqeyi (-1 = tapilmadi)
	fmt.Println(strings.Index("salam dunya", "dunya")) // 6
	fmt.Println(strings.Index("salam dunya", "mars"))  // -1

	// LastIndex - sonuncu movqe
	fmt.Println(strings.LastIndex("go go go", "go")) // 6

	// -------------------------------------------
	// 5. strings.NewReader
	// -------------------------------------------

	fmt.Println("\n=== 5. strings.NewReader ===")

	// NewReader - stringden io.Reader yaradir
	// Fayl kimi oxumaq lazim olduqda istifade olunur
	reader := strings.NewReader("Salam, Reader!")
	fmt.Println("Uzunluq:", reader.Len())  // 14
	fmt.Println("Olcu:", reader.Size())     // 14

	// Byte-byte oxumaq
	buf := make([]byte, 5)
	n, _ := reader.Read(buf)
	fmt.Println("Oxunan:", string(buf[:n])) // Salam

	// -------------------------------------------
	// 6. strings.Builder (Effektiv birleshme)
	// -------------------------------------------

	fmt.Println("\n=== 6. strings.Builder ===")

	// PROBLEM: String birleshme (+) her defe yeni string yaradir
	// Bu yavashdir, chunki her defe yaddas ayrilir

	// PIS USUL (yavash - boyuk melumatda istifade etmeyin):
	result := ""
	for i := 0; i < 5; i++ {
		result += fmt.Sprintf("element_%d ", i) // Her defe yeni string yaranir!
	}
	fmt.Println("Pis usul:", result)

	// YAXSI USUL (suretli - strings.Builder istifade edin):
	var builder strings.Builder
	for i := 0; i < 5; i++ {
		builder.WriteString(fmt.Sprintf("element_%d ", i))
	}
	fmt.Println("Yaxsi usul:", builder.String())

	// Builder metodlari
	var b strings.Builder
	b.WriteString("Salam")  // String yaz
	b.WriteByte(' ')         // Tek byte yaz
	b.WriteRune('🇦🇿')       // Unicode simvol yaz
	b.WriteString(" Go!")
	fmt.Println(b.String())
	fmt.Println("Uzunluq:", b.Len())
	b.Reset() // Temizle, yeniden istifade et

	// -------------------------------------------
	// 7. strconv.Atoi ve strconv.Itoa
	// -------------------------------------------

	fmt.Println("\n=== 7. strconv.Atoi / Itoa ===")

	// Atoi - String -> Int (ASCII to Integer)
	eded, err := strconv.Atoi("42")
	if err != nil {
		fmt.Println("Xeta:", err)
	} else {
		fmt.Println("String -> Int:", eded) // 42
	}

	// Sehv format olduqda
	_, err = strconv.Atoi("abc")
	if err != nil {
		fmt.Println("Xeta:", err) // strconv.Atoi: parsing "abc": invalid syntax
	}

	// Itoa - Int -> String (Integer to ASCII)
	s := strconv.Itoa(123)
	fmt.Println("Int -> String:", s) // "123"

	// -------------------------------------------
	// 8. strconv.ParseFloat, ParseBool
	// -------------------------------------------

	fmt.Println("\n=== 8. ParseFloat, ParseBool ===")

	// ParseFloat - String -> Float
	// Ikinci parametr: bit olcusu (32 ve ya 64)
	f, err := strconv.ParseFloat("3.14", 64)
	if err == nil {
		fmt.Println("ParseFloat:", f) // 3.14
	}

	// ParseBool - String -> Bool
	// "1", "t", "T", "TRUE", "true", "True" -> true
	// "0", "f", "F", "FALSE", "false", "False" -> false
	boolDeyer, err := strconv.ParseBool("true")
	if err == nil {
		fmt.Println("ParseBool:", boolDeyer) // true
	}

	// ParseInt - muextelif bazalarda
	// Parametrler: string, baza (2/8/10/16), bit olcusu
	ikili, _ := strconv.ParseInt("1010", 2, 64)
	fmt.Println("Ikili 1010 =", ikili) // 10

	onaltiliq, _ := strconv.ParseInt("ff", 16, 64)
	fmt.Println("Hex ff =", onaltiliq) // 255

	// -------------------------------------------
	// 9. strconv.FormatInt, FormatFloat
	// -------------------------------------------

	fmt.Println("\n=== 9. FormatInt, FormatFloat ===")

	// FormatInt - reqemi mueyyenlemish bazada stringe cevir
	fmt.Println("10 ikili:", strconv.FormatInt(10, 2))   // 1010
	fmt.Println("255 hex:", strconv.FormatInt(255, 16))  // ff
	fmt.Println("8 sekizlik:", strconv.FormatInt(8, 8))  // 10

	// FormatFloat - float-i stringe cevir
	// Parametrler: deyer, format, dequiqlik, bit olcusu
	// Format: 'f' (normal), 'e' (elmi), 'g' (qisa)
	fmt.Println(strconv.FormatFloat(3.14159, 'f', 2, 64))  // 3.14
	fmt.Println(strconv.FormatFloat(3.14159, 'e', 4, 64))  // 3.1416e+00
	fmt.Println(strconv.FormatFloat(3.14159, 'g', -1, 64)) // 3.14159

	// FormatBool
	fmt.Println(strconv.FormatBool(true)) // "true"

	// -------------------------------------------
	// 10. fmt.Sprintf ile String Formatlama
	// -------------------------------------------

	fmt.Println("\n=== 10. fmt.Sprintf ===")

	ad := "Orkhan"
	yash := 25
	boy := 1.78

	// Sprintf - formatlanmish string yaradir (capa vermir)
	netice := fmt.Sprintf("Ad: %s, Yash: %d, Boy: %.1f", ad, yash, boy)
	fmt.Println(netice) // Ad: Orkhan, Yash: 25, Boy: 1.8

	// Faydali format specifiers:
	// %s - string
	// %d - integer (onluq)
	// %f - float, %.2f - 2 reqem dequiqlik
	// %t - boolean
	// %v - her hansi deyerin defolt formati
	// %+v - struct fieldlerin adlari ile
	// %#v - Go sintaksisinde goster
	// %T - tipi goster
	// %b - ikili (binary)
	// %x - onaltiliq (hex)
	// %o - sekizlik (octal)
	// %p - pointer adresi
	// %q - quoted string

	fmt.Sprintf("%05d", 42)                       // "00042" - sol terefden sifirla doldur
	fmt.Println(fmt.Sprintf("%-10s|", "sol"))     // "sol       |" - sola duzle
	fmt.Println(fmt.Sprintf("%10s|", "sag"))      // "       sag|" - saga duzle
	fmt.Println(fmt.Sprintf("%q", "salam dunya")) // "\"salam dunya\""

	// -------------------------------------------
	// 11. String Immutability ve Performance
	// -------------------------------------------

	fmt.Println("\n=== 11. String Immutability ===")

	// Go-da string deyisdirilmezdir (immutable)
	// Yeni string yaratmadan bir simvolu deyishe bilmezsiniz
	str := "Salam"
	// str[0] = 'H' // XETA! String immutable-dir

	// Deyishmek ucun []byte-a cevirin
	baytlar := []byte(str)
	baytlar[0] = 'H'
	yeniStr := string(baytlar)
	fmt.Println(yeniStr) // Halam

	// String-in daxili strukturu:
	// string = pointer + length (2 soz)
	// []byte = pointer + length + capacity (3 soz)
	fmt.Println("string olcusu:", unsafe.Sizeof(str))     // 16 (64-bit sistemde)
	fmt.Println("[]byte olcusu:", unsafe.Sizeof(baytlar)) // 24

	// -------------------------------------------
	// 12. []byte vs string vs []rune Cevirmeleri
	// -------------------------------------------

	fmt.Println("\n=== 12. []byte vs string vs []rune ===")

	// string -> []byte (UTF-8 baytlari)
	s1 := "Salam"
	b1 := []byte(s1)
	fmt.Println("[]byte:", b1) // [83 97 108 97 109]

	// []byte -> string
	s2 := string(b1)
	fmt.Println("string:", s2) // Salam

	// string -> []rune (Unicode simvollari)
	// Azerbaycan dilinde ve ya emoji olduqda vacibdir
	s3 := "Salam 🌍"
	r1 := []rune(s3)
	fmt.Println("[]rune:", r1)         // Unicode noqteleri
	fmt.Println("rune sayi:", len(r1)) // 7 (simvol sayi)
	fmt.Println("byte sayi:", len(s3)) // 10 (bayt sayi - emoji 4 bayt tutur)

	// MUHUM FERQ:
	// len(string) -> bayt sayini qaytarir
	// len([]rune(string)) -> simvol sayini qaytarir
	azerbaycan := "ə" // Azerbaycan herfi
	fmt.Printf("'%s' - bayt: %d, simvol: %d\n",
		azerbaycan, len(azerbaycan), len([]rune(azerbaycan)))
	// 'ə' - bayt: 2, simvol: 1

	// String uzerinde range - rune-larla isleyir
	for i, ch := range "Aşə" {
		fmt.Printf("index=%d, rune=%c, kod=%d\n", i, ch, ch)
	}
	// Diqqet: index bayt movqeyidir, rune movqeyi deyil!

	// -------------------------------------------
	// 13. Praktik numune: CSV Parser
	// -------------------------------------------

	fmt.Println("\n=== 13. Praktik Numune: CSV Parser ===")

	csv := `ad,yash,sheher
Eli,25,Baki
Aysel,30,Gence
Kenan,22,Sumqayit`

	setirler := strings.Split(csv, "\n")
	bashliq := strings.Split(setirler[0], ",")
	fmt.Println("Bashliqlar:", bashliq)

	for _, setir := range setirler[1:] {
		sutunlar := strings.Split(setir, ",")
		if len(sutunlar) == 3 {
			ad := strings.TrimSpace(sutunlar[0])
			yash, _ := strconv.Atoi(strings.TrimSpace(sutunlar[1]))
			sheher := strings.TrimSpace(sutunlar[2])
			fmt.Printf("%s %d yashinda, %s sheherinden\n", ad, yash, sheher)
		}
	}

	// -------------------------------------------
	// 14. Praktik numune: URL Builder
	// -------------------------------------------

	fmt.Println("\n=== 14. Praktik Numune: URL Builder ===")

	baseURL := "https://api.example.com"
	endpoint := "/users"
	params := map[string]string{
		"page":  "1",
		"limit": "10",
		"sort":  "name",
	}

	var urlBuilder strings.Builder
	urlBuilder.WriteString(baseURL)
	urlBuilder.WriteString(endpoint)
	urlBuilder.WriteByte('?')

	i := 0
	for key, val := range params {
		if i > 0 {
			urlBuilder.WriteByte('&')
		}
		urlBuilder.WriteString(key)
		urlBuilder.WriteByte('=')
		urlBuilder.WriteString(val)
		i++
	}
	fmt.Println("URL:", urlBuilder.String())
}

// ISLETMEK UCUN:
// go run 63_strings_and_strconv.go
