package main

import (
	"bufio"
	"fmt"
	"os"
)

// ===============================================
// FAYL EMELIYYATLARI (FILE I/O)
// ===============================================

func main() {

	// -------------------------------------------
	// 1. Fayla yazma (butov)
	// -------------------------------------------
	metn := []byte("Salam Dunya!\nBu Go-dan yazildi.\n")
	err := os.WriteFile("test.txt", metn, 0644)
	if err != nil {
		fmt.Println("Yazma xetasi:", err)
		return
	}
	fmt.Println("Fayl yazildi")

	// -------------------------------------------
	// 2. Fayldan oxuma (butov)
	// -------------------------------------------
	data, err := os.ReadFile("test.txt")
	if err != nil {
		fmt.Println("Oxuma xetasi:", err)
		return
	}
	fmt.Println("Mezmun:")
	fmt.Println(string(data))

	// -------------------------------------------
	// 3. Fayl yaratma ve yazma (os.Create)
	// -------------------------------------------
	fayl, err := os.Create("test2.txt")
	if err != nil {
		fmt.Println("Yaratma xetasi:", err)
		return
	}
	defer fayl.Close() // funksiya bitende fayli bagla

	fayl.WriteString("Birinci setir\n")
	fayl.WriteString("Ikinci setir\n")
	fayl.WriteString("Ucuncu setir\n")
	fmt.Println("test2.txt yazildi")

	// -------------------------------------------
	// 4. Faylin movcudlugunu yoxlama
	// -------------------------------------------
	_, err = os.Stat("test.txt")
	if os.IsNotExist(err) {
		fmt.Println("Fayl movcud deyil")
	} else {
		fmt.Println("Fayl movcuddur")
	}

	// -------------------------------------------
	// 5. Fayildan setir-setir oxuma (bufio)
	// -------------------------------------------
	fayl2, err := os.Open("test2.txt")
	if err != nil {
		fmt.Println("Acma xetasi:", err)
		return
	}
	defer fayl2.Close()

	scanner := bufio.NewScanner(fayl2)
	setirNo := 1
	for scanner.Scan() {
		fmt.Printf("Setir %d: %s\n", setirNo, scanner.Text())
		setirNo++
	}

	// -------------------------------------------
	// 6. Fayla elave etme (append)
	// -------------------------------------------
	fayl3, err := os.OpenFile("test.txt", os.O_APPEND|os.O_WRONLY, 0644)
	if err != nil {
		fmt.Println("Acma xetasi:", err)
		return
	}
	defer fayl3.Close()

	fayl3.WriteString("Elave edilmis setir\n")
	fmt.Println("Elave edildi")

	// -------------------------------------------
	// 7. Qovluq emeliyyatlari
	// -------------------------------------------
	// Qovluq yaratma
	err = os.Mkdir("testdir", 0755)
	if err != nil && !os.IsExist(err) {
		fmt.Println("Mkdir xetasi:", err)
	}

	// Ic-ice qovluq yaratma
	err = os.MkdirAll("testdir/alt/qovluq", 0755)
	if err != nil {
		fmt.Println("MkdirAll xetasi:", err)
	}

	// Qovluq mezmununu oxuma
	girisler, err := os.ReadDir(".")
	if err != nil {
		fmt.Println("ReadDir xetasi:", err)
		return
	}
	fmt.Println("\nCari qovluqdaki fayllar:")
	for _, giris := range girisler {
		tip := "FAYL"
		if giris.IsDir() {
			tip = "DIR "
		}
		fmt.Printf("  [%s] %s\n", tip, giris.Name())
	}

	// -------------------------------------------
	// 8. Fayl ve qovluq silme
	// -------------------------------------------
	os.Remove("test.txt")              // tek fayl sil
	os.Remove("test2.txt")
	os.RemoveAll("testdir")            // qovluq ve mezmununu sil
	fmt.Println("\nTest fayllari silindi")

	// -------------------------------------------
	// 9. Fayl adini deyisme
	// -------------------------------------------
	// os.Rename("kohne.txt", "yeni.txt")

	// -------------------------------------------
	// MUHUM QEYDLER:
	// -------------------------------------------
	// - defer fayl.Close() HER ZAMAN istifade edin
	// - os.ReadFile/os.WriteFile kicik fayllar ucun rahatdir
	// - Boyuk fayllar ucun bufio.Scanner istifade edin
	// - Fayl icazelerinde: 0644 = sahibi oxu/yaz, basqalari yalniz oxu
}
