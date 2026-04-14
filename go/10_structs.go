package main

import "fmt"

// ===============================================
// STRUCTLAR (STRUCTURES)
// ===============================================

// Struct - bir nece ferqli tipli melumati birlesdiren xususi tipdir
// Diger dillerdeki class-a oxsardir (amma class deyil)

// -------------------------------------------
// 1. Struct elan etme
// -------------------------------------------
type Shexs struct {
	Ad    string
	Soyad string
	Yas   int
	Email string
}

// -------------------------------------------
// 2. Metodlar (Methods)
// -------------------------------------------
// Struct-a bagli funksiya. Receiver ile elan olunur.

// Deyer receiver - struct-un kopyasi ile isleyir
func (s Shexs) TamAd() string {
	return s.Ad + " " + s.Soyad
}

// Pointer receiver - orijinal struct-u deyisdirir
func (s *Shexs) YasArtir() {
	s.Yas++
}

// -------------------------------------------
// 3. Constructor pattern (yaratma funksiyasi)
// -------------------------------------------
func YeniShexs(ad, soyad string, yas int) Shexs {
	return Shexs{
		Ad:    ad,
		Soyad: soyad,
		Yas:   yas,
		Email: ad + "@mail.az",
	}
}

// -------------------------------------------
// 4. Ic-ice struct (embedded/nested)
// -------------------------------------------
type Unvan struct {
	Sheher string
	Kuce   string
}

type Isci struct {
	Shexs        // embedded (anonim) - Shexs-in butun sahelerini elave edir
	Vezife string
	Maas   float64
	Unvan  Unvan  // adli ic-ice struct
}

// -------------------------------------------
// 5. Struct tag-leri (JSON ucun)
// -------------------------------------------
type Mehsul struct {
	Ad     string  `json:"ad"`
	Qiymet float64 `json:"qiymet"`
	Stok   int     `json:"stok,omitempty"`
}

func main() {

	// -------------------------------------------
	// Struct yaratma yollari
	// -------------------------------------------

	// a) Sahə adlari ile (tovsiyə olunan)
	adam := Shexs{
		Ad:    "Orkhan",
		Soyad: "Shukurlu",
		Yas:   25,
		Email: "orkhan@mail.az",
	}
	fmt.Println("Adam:", adam)

	// b) Sira ile (tovsiyə olunmur - sahelerin sirasi deyiserse sorun yaranir)
	adam2 := Shexs{"Eli", "Veliyev", 30, "eli@mail.az"}
	fmt.Println("Adam2:", adam2)

	// c) Sifir deyerle
	var adam3 Shexs // butun saheler sifir deyer alir
	fmt.Println("Bos adam:", adam3)

	// d) Constructor ile
	adam4 := YeniShexs("Veli", "Aliyev", 28)
	fmt.Println("Constructor:", adam4)

	// -------------------------------------------
	// Saheye erise
	// -------------------------------------------
	fmt.Println("Ad:", adam.Ad)
	fmt.Println("Yas:", adam.Yas)
	adam.Email = "yeni@mail.az" // deyisdire bilirik
	fmt.Println("Yeni email:", adam.Email)

	// -------------------------------------------
	// Metodlar
	// -------------------------------------------
	fmt.Println("Tam ad:", adam.TamAd())

	fmt.Println("Yas evvel:", adam.Yas)
	adam.YasArtir() // pointer receiver - orijinali deyisdirir
	fmt.Println("Yas sonra:", adam.Yas)

	// -------------------------------------------
	// Pointer ile struct
	// -------------------------------------------
	p := &adam // adam-in pointer-i
	fmt.Println("Pointer ile ad:", p.Ad) // Go avtomatik dereference edir
	p.Yas = 50
	fmt.Println("Deyismis yas:", adam.Yas) // 50

	// new ile (nadir istifade olunur)
	adam5 := new(Shexs) // *Shexs pointer qaytarir
	adam5.Ad = "Test"
	fmt.Println("new ile:", adam5)

	// -------------------------------------------
	// Ic-ice struct
	// -------------------------------------------
	isci := Isci{
		Shexs:  Shexs{Ad: "Eli", Soyad: "Hesenov", Yas: 35},
		Vezife: "Proqramci",
		Maas:   5000,
		Unvan: Unvan{
			Sheher: "Baku",
			Kuce:   "Nizami 10",
		},
	}

	// Embedded struct sahelrine birbaşa erise bilerik
	fmt.Println("Isci adi:", isci.Ad)    // Shexs.Ad - birbaşa
	fmt.Println("Vezife:", isci.Vezife)
	fmt.Println("Sheher:", isci.Unvan.Sheher) // adli struct - tam yol lazim
	fmt.Println("Tam ad:", isci.TamAd())      // Shexs metodu da isleyir

	// -------------------------------------------
	// Struct muqayisesi
	// -------------------------------------------
	a := Shexs{Ad: "Test", Yas: 20}
	b := Shexs{Ad: "Test", Yas: 20}
	fmt.Println("Beraberdir:", a == b) // true

	// -------------------------------------------
	// Anonim struct (bir defəlik istifade)
	// -------------------------------------------
	config := struct {
		Host string
		Port int
	}{
		Host: "localhost",
		Port: 8080,
	}
	fmt.Println("Config:", config)
}
