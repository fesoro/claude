package main

import "fmt"

// ===============================================
// FUZZ TESTING (Go 1.18+)
// ===============================================

// Fuzz test - avtomatik tesadufi girislerle proqrami yoxlayir
// Gozlenilmeyen xetalari, panic-leri, crash-lari tapir
// Normal testlerin tapa bilmediyi edge case-leri tapir

func main() {
	fmt.Println("Fuzz test ornekleri - _test.go faylina yazin ve 'go test -fuzz=.' ile isledin")

	kodlar := `
// ==========================================
// FAYL: utils.go
// ==========================================
package utils

import (
    "errors"
    "fmt"
    "strings"
    "unicode/utf8"
)

// -------------------------------------------
// Test edilecek funksiyalar
// -------------------------------------------

// String-i ters cevir
func TersCevir(s string) string {
    runes := []rune(s)
    for i, j := 0, len(runes)-1; i < j; i, j = i+1, j-1 {
        runes[i], runes[j] = runes[j], runes[i]
    }
    return string(runes)
}

// URL slug yaratmaq
func Slugify(s string) string {
    s = strings.ToLower(s)
    s = strings.TrimSpace(s)
    var result []rune
    prevDash := false
    for _, r := range s {
        if r >= 'a' && r <= 'z' || r >= '0' && r <= '9' {
            result = append(result, r)
            prevDash = false
        } else if !prevDash && len(result) > 0 {
            result = append(result, '-')
            prevDash = true
        }
    }
    return strings.TrimRight(string(result), "-")
}

// JSON parse etmek
func ParseYas(s string) (int, error) {
    if s == "" {
        return 0, errors.New("bos string")
    }
    yas := 0
    for _, c := range s {
        if c < '0' || c > '9' {
            return 0, fmt.Errorf("reqem deyil: %c", c)
        }
        yas = yas*10 + int(c-'0')
        if yas > 200 {
            return 0, errors.New("yas cox boyukdur")
        }
    }
    return yas, nil
}

// ==========================================
// FAYL: utils_test.go
// ==========================================
package utils

import (
    "testing"
    "unicode/utf8"
)

// -------------------------------------------
// 1. ESAS FUZZ TEST
// -------------------------------------------
// Funksiya adi FuzzXxx ile baslamalidir
// Parametr *testing.F olmalidir

func FuzzTersCevir(f *testing.F) {
    // Seed corpus - baslangic test deyerleri
    // Fuzz engine bunlari baslangic noqtesi kimi istifade edir
    f.Add("salam")
    f.Add("Go dili")
    f.Add("")
    f.Add("12345")
    f.Add("!@#$%")
    f.Add("Azərbaycan")

    // Fuzz funksiyasi - tesadufi girislerle cagrilir
    f.Fuzz(func(t *testing.T, giris string) {
        // UTF-8 duzgunluyunu yoxla
        if !utf8.ValidString(giris) {
            t.Skip("UTF-8 deyil")
        }

        // Ters cevirmeyi iki defe etsek, orijinali almaliduq
        netice := TersCevir(giris)
        ikiqat := TersCevir(netice)

        if ikiqat != giris {
            t.Errorf("TersCevir(TersCevir(%q)) = %q, gozlenen %q", giris, ikiqat, giris)
        }

        // Uzunlug deyismemelidir
        if utf8.RuneCountInString(netice) != utf8.RuneCountInString(giris) {
            t.Errorf("Uzunlug ferqli: giris=%d, netice=%d",
                utf8.RuneCountInString(giris), utf8.RuneCountInString(netice))
        }
    })
}

// -------------------------------------------
// 2. Bir nece parametrli fuzz
// -------------------------------------------
func FuzzSlugify(f *testing.F) {
    f.Add("Salam Dunya")
    f.Add("  bosluqlu  metn  ")
    f.Add("BOYUK HERFLER")
    f.Add("xususi!@#simvollar")
    f.Add("")

    f.Fuzz(func(t *testing.T, giris string) {
        netice := Slugify(giris)

        // Slug-da boyuk herf olmamalidir
        for _, r := range netice {
            if r >= 'A' && r <= 'Z' {
                t.Errorf("Boyuk herf tapildi: %q -> %q", giris, netice)
            }
        }

        // Slug boslluq ile baslamaz/bitmez
        if len(netice) > 0 && (netice[0] == '-' || netice[len(netice)-1] == '-') {
            t.Errorf("Dash ile baslayir/bitir: %q -> %q", giris, netice)
        }
    })
}

// -------------------------------------------
// 3. Xeta testleme fuzzing
// -------------------------------------------
func FuzzParseYas(f *testing.F) {
    f.Add("25")
    f.Add("0")
    f.Add("200")
    f.Add("abc")
    f.Add("")
    f.Add("-5")

    f.Fuzz(func(t *testing.T, giris string) {
        yas, err := ParseYas(giris)

        if err != nil {
            // Xeta olsa, deyer 0 olmalidir
            if yas != 0 {
                t.Errorf("Xeta var amma yas = %d (0 olmalidir)", yas)
            }
            return
        }

        // Ugurlu olsa, yas 0-200 arasinda olmalidir
        if yas < 0 || yas > 200 {
            t.Errorf("Yas araligdan kenarda: %d", yas)
        }
    })
}

// -------------------------------------------
// 4. Byte slice ile fuzz
// -------------------------------------------
func FuzzByteParse(f *testing.F) {
    f.Add([]byte("test"))
    f.Add([]byte{0, 1, 2, 3})
    f.Add([]byte{})

    f.Fuzz(func(t *testing.T, data []byte) {
        // Proqram crash etmemelidir
        // istənilən giris ile panic olmamalidir
        _ = string(data) // sadə ornek
    })
}
`

	fmt.Println(kodlar)

	fmt.Println(`
=== FUZZ TEST EMRLERI ===
go test -fuzz=FuzzTersCevir              # bir testi fuzz et
go test -fuzz=FuzzTersCevir -fuzztime=30s # 30 saniye fuzz et
go test -fuzz=.                          # butun fuzz testleri
go test -fuzz=. -fuzztime=1m             # 1 deqiqe
go test -fuzz=. -fuzztime=10000x         # 10000 iterasiya

=== XETA TAPILDIGDA ===
Fuzz engine xeta tapsa, giris deyerini saxlayir:
testdata/fuzz/FuzzTersCevir/xeta_hash
Bu fayli git-e commit edin - regression test kimi isleyecek.
Sonraki "go test" bu deyeri avtomatik yoxlayacaq.

=== NE VAXT FUZZ ISTIFADE ETMELI ===
- String/byte parse emeliyyatlari
- Serialization/Deserialization (JSON, XML, protobuf)
- Kriptografik funksiyalar
- File format parserleri
- Istənilən xarici girisle isleyen funksiyalar

=== MUHUM QEYDLER ===
- Seed corpus muhimdir - yaxsi baslangic nqoteleri verin
- Fuzz funksiyasi deterministik olmalidir (eyni giris = eyni netice)
- t.Skip() ile etibarsiz girisleri kecin
- Property-based testing prinsipi: netice haqqinda qaydalar yazin
`)
}
