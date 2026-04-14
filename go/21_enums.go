package main

import (
	"encoding/json"
	"fmt"
)

// ===============================================
// ENUMS (Saymali tipler)
// ===============================================

// Go-da enum acar sozu YOXDUR
// iota + const + xususi tip ile enum yaradilir

// -------------------------------------------
// 1. Sadə enum (iota ile)
// -------------------------------------------
type Gun int

const (
	Bazar       Gun = iota // 0
	BazarErtesi            // 1
	CersenbeAxsami         // 2
	Cersembe               // 3
	Cumeaxsami             // 4
	Cume                   // 5
	Senbe                  // 6
)

// String metodu - enum-u oxunaqli edir
func (g Gun) String() string {
	adlar := [...]string{
		"Bazar",
		"Bazar ertesi",
		"Cersembe axsami",
		"Cersembe",
		"Cume axsami",
		"Cume",
		"Senbe",
	}
	if g < Bazar || g > Senbe {
		return "Bilinmeyen"
	}
	return adlar[g]
}

// -------------------------------------------
// 2. Sifirdan baslamayan enum
// -------------------------------------------
type HttpStatus int

const (
	StatusOK           HttpStatus = 200
	StatusCreated      HttpStatus = 201
	StatusBadRequest   HttpStatus = 400
	StatusUnauthorized HttpStatus = 401
	StatusNotFound     HttpStatus = 404
	StatusServerError  HttpStatus = 500
)

func (s HttpStatus) String() string {
	switch s {
	case StatusOK:
		return "OK"
	case StatusCreated:
		return "Created"
	case StatusBadRequest:
		return "Bad Request"
	case StatusUnauthorized:
		return "Unauthorized"
	case StatusNotFound:
		return "Not Found"
	case StatusServerError:
		return "Internal Server Error"
	default:
		return fmt.Sprintf("Unknown(%d)", int(s))
	}
}

// -------------------------------------------
// 3. String enum
// -------------------------------------------
type Reng string

const (
	Qirmizi Reng = "qirmizi"
	Yashil  Reng = "yashil"
	Goy     Reng = "goy"
	Sari    Reng = "sari"
)

func (r Reng) Etibarlimi() bool {
	switch r {
	case Qirmizi, Yashil, Goy, Sari:
		return true
	}
	return false
}

// -------------------------------------------
// 4. Bit flag enum (bir nece deyer eyni anda)
// -------------------------------------------
type Icaze int

const (
	Oxu   Icaze = 1 << iota // 1  (001)
	Yaz                     // 2  (010)
	Islet                   // 4  (100)
)

func (i Icaze) String() string {
	s := ""
	if i&Oxu != 0 {
		s += "Oxu "
	}
	if i&Yaz != 0 {
		s += "Yaz "
	}
	if i&Islet != 0 {
		s += "Islet "
	}
	if s == "" {
		return "Yox"
	}
	return s
}

// -------------------------------------------
// 5. JSON ile isleyen enum
// -------------------------------------------
type Status int

const (
	Aktiv   Status = iota
	Passiv
	Silinmis
)

var statusAdlari = map[Status]string{
	Aktiv:    "aktiv",
	Passiv:   "passiv",
	Silinmis: "silinmis",
}

var statusDeyerleri = map[string]Status{
	"aktiv":    Aktiv,
	"passiv":   Passiv,
	"silinmis": Silinmis,
}

func (s Status) MarshalJSON() ([]byte, error) {
	ad, ok := statusAdlari[s]
	if !ok {
		return nil, fmt.Errorf("bilinmeyen status: %d", s)
	}
	return json.Marshal(ad)
}

func (s *Status) UnmarshalJSON(data []byte) error {
	var ad string
	if err := json.Unmarshal(data, &ad); err != nil {
		return err
	}
	val, ok := statusDeyerleri[ad]
	if !ok {
		return fmt.Errorf("bilinmeyen status: %s", ad)
	}
	*s = val
	return nil
}

func (s Status) String() string {
	if ad, ok := statusAdlari[s]; ok {
		return ad
	}
	return "bilinmeyen"
}

func main() {

	// 1. Sadə enum
	fmt.Println("=== Sadə Enum ===")
	bugun := Cersembe
	fmt.Println("Bugun:", bugun)       // Cersembe
	fmt.Println("Deyeri:", int(bugun)) // 3

	// Switch ile
	switch bugun {
	case Senbe, Bazar:
		fmt.Println("Istirahet gunu!")
	default:
		fmt.Println("Is gunu")
	}

	// 2. HTTP Status
	fmt.Println("\n=== HTTP Status ===")
	status := StatusNotFound
	fmt.Printf("%d %s\n", int(status), status) // 404 Not Found

	// 3. String enum
	fmt.Println("\n=== String Enum ===")
	r := Qirmizi
	fmt.Println("Reng:", r)                    // qirmizi
	fmt.Println("Etibarlimi:", r.Etibarlimi()) // true

	yanlis := Reng("benovseyi")
	fmt.Println("Etibarlimi:", yanlis.Etibarlimi()) // false

	// 4. Bit flags
	fmt.Println("\n=== Bit Flags ===")
	icaze := Oxu | Yaz // 3 (011)
	fmt.Println("Icazeler:", icaze)

	// Yoxlama
	fmt.Println("Oxu var?:  ", icaze&Oxu != 0)   // true
	fmt.Println("Islet var?:", icaze&Islet != 0)  // false

	// Icaze elave et
	icaze |= Islet // 7 (111)
	fmt.Println("Islet elave:", icaze)

	// Icaze sil
	icaze &^= Yaz // 5 (101)
	fmt.Println("Yaz silindi:", icaze)

	// 5. JSON ile
	fmt.Println("\n=== JSON Enum ===")

	type Istifadeci struct {
		Ad     string `json:"ad"`
		Status Status `json:"status"`
	}

	ist := Istifadeci{Ad: "Orkhan", Status: Aktiv}
	jsonData, _ := json.Marshal(ist)
	fmt.Println("JSON:", string(jsonData)) // {"ad":"Orkhan","status":"aktiv"}

	var ist2 Istifadeci
	json.Unmarshal([]byte(`{"ad":"Eli","status":"passiv"}`), &ist2)
	fmt.Printf("Unmarshal: %+v\n", ist2) // {Ad:Eli Status:1}
	fmt.Println("Status:", ist2.Status)    // passiv

	// Butun deyerleri listele
	fmt.Println("\n=== Butun Statuslar ===")
	for status, ad := range statusAdlari {
		fmt.Printf("  %d: %s\n", int(status), ad)
	}
}
