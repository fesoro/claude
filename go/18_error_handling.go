package main

import (
	"errors"
	"fmt"
	"os"
	"strconv"
)

// ===============================================
// XETA ISLEME (ERROR HANDLING)
// ===============================================

// Go-da try/catch YOXDUR
// Xetalar deyer kimi qaytarilir ve yoxlanilir
// Bu Go-nun en muhum prinsiplerinden biridir

// -------------------------------------------
// 1. Əsas xeta pattern-i
// -------------------------------------------
func bol(a, b float64) (float64, error) {
	if b == 0 {
		return 0, errors.New("sifira bolmek mumkun deyil")
	}
	return a / b, nil // nil = xeta yoxdur
}

// -------------------------------------------
// 2. fmt.Errorf ile formatli xeta
// -------------------------------------------
func yasYoxla(yas int) error {
	if yas < 0 {
		return fmt.Errorf("yas menfi ola bilmez: %d", yas)
	}
	if yas > 150 {
		return fmt.Errorf("yas real deyil: %d", yas)
	}
	return nil
}

// -------------------------------------------
// 3. Xususi xeta tipi (Custom Error)
// -------------------------------------------
type DogrulamaXetasi struct {
	Sahe  string
	Mesaj string
}

func (d *DogrulamaXetasi) Error() string {
	return fmt.Sprintf("dogrulama xetasi: %s - %s", d.Sahe, d.Mesaj)
}

func emailYoxla(email string) error {
	if len(email) == 0 {
		return &DogrulamaXetasi{
			Sahe:  "email",
			Mesaj: "bos ola bilmez",
		}
	}
	return nil
}

// -------------------------------------------
// 4. Xeta sarmasi (Error Wrapping) - Go 1.13+
// -------------------------------------------
func faylOxu(ad string) error {
	_, err := os.Open(ad)
	if err != nil {
		// %w ile xetanin ustune mesaj elave edirik, amma orijinal xetani saxlayiriq
		return fmt.Errorf("fayl oxunarkən xəta: %w", err)
	}
	return nil
}

// -------------------------------------------
// 5. Sentinel errors (bilinen xetalar)
// -------------------------------------------
var (
	ErrTapilmadi  = errors.New("tapilmadi")
	ErrIcaze_yox  = errors.New("icaze yoxdur")
)

func istifadeciTap(id int) (string, error) {
	if id <= 0 {
		return "", ErrTapilmadi
	}
	if id == 999 {
		return "", ErrIcaze_yox
	}
	return "Orkhan", nil
}

func main() {

	// 1. Esas xeta yoxlamasi
	netice, err := bol(10, 0)
	if err != nil {
		fmt.Println("XETA:", err)
	} else {
		fmt.Println("Netice:", netice)
	}

	netice, err = bol(10, 3)
	if err != nil {
		fmt.Println("XETA:", err)
	} else {
		fmt.Printf("Netice: %.2f\n", netice)
	}

	// 2. Yas yoxlamasi
	if err := yasYoxla(-5); err != nil {
		fmt.Println("XETA:", err)
	}

	// 3. Xususi xeta
	if err := emailYoxla(""); err != nil {
		fmt.Println("XETA:", err)

		// Xususi xeta tipini yoxlamaq (type assertion)
		var dogrulamaErr *DogrulamaXetasi
		if errors.As(err, &dogrulamaErr) {
			fmt.Println("Sahe:", dogrulamaErr.Sahe)
			fmt.Println("Mesaj:", dogrulamaErr.Mesaj)
		}
	}

	// 4. Wrapped error
	err = faylOxu("movcud_olmayan_fayl.txt")
	if err != nil {
		fmt.Println("XETA:", err)

		// Orijinal xetanin os.PathError oldugunu yoxla
		var pathErr *os.PathError
		if errors.As(err, &pathErr) {
			fmt.Println("Fayl yolu:", pathErr.Path)
		}
	}

	// 5. Sentinel error muqayisesi
	_, err = istifadeciTap(0)
	if errors.Is(err, ErrTapilmadi) {
		fmt.Println("Istifadeci tapilmadi")
	}

	_, err = istifadeciTap(999)
	if errors.Is(err, ErrIcaze_yox) {
		fmt.Println("Icaze yoxdur")
	}

	// -------------------------------------------
	// 6. Strconv ile xeta ornegi
	// -------------------------------------------
	deger, err := strconv.Atoi("abc") // string -> int
	if err != nil {
		fmt.Println("Cevirme xetasi:", err)
	} else {
		fmt.Println("Deyer:", deger)
	}

	deger, err = strconv.Atoi("42")
	if err != nil {
		fmt.Println("Cevirme xetasi:", err)
	} else {
		fmt.Println("Deyer:", deger) // 42
	}

	// -------------------------------------------
	// 7. PANIC ve RECOVER
	// -------------------------------------------
	// panic - ciddi xeta, proqram dayanir (try/catch kimi deyil, nadir istifade olunur)
	// recover - panic-i tutur (yalniz defer icerisinde isleyir)

	// defer ile recover ornegi
	func() {
		defer func() {
			if r := recover(); r != nil {
				fmt.Println("Panic tutuldu:", r)
			}
		}()

		fmt.Println("Panic-den evvel")
		panic("bir sey pis getdi!") // proqram burada normalde dayanardi
		// Bu setir hec vaxt islemeyecek
	}()

	fmt.Println("Proqram davam edir (recover sayesinde)")

	// QEYD: Panic yalniz proqramin davametmesinin mumkun olmadigi hallarda istifade olunmalidir
	// Normal xetalar ucun HER ZAMAN error qaytarin
}
