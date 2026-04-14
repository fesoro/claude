package main

import (
	"fmt"
	"math"
)

// ===============================================
// INTERFEYS (INTERFACE)
// ===============================================

// Interface - metodlarin imzalarini (signature) mueyyen edir
// Hansi struct-in hansi metodlari olmali oldugunu bildirir
// Go-da interface AVTOMATIK tətbiq olunur (implements yazmaga ehtiyac yoxdur)

// -------------------------------------------
// 1. Interface elan etme
// -------------------------------------------
type Sekil interface {
	Sahe() float64
	Cevre() float64
}

// -------------------------------------------
// 2. Struct-lar interface-i tetbiq edir
// -------------------------------------------

// Duzbucaqli
type Duzbucaqli struct {
	En, Boy float64
}

func (d Duzbucaqli) Sahe() float64 {
	return d.En * d.Boy
}

func (d Duzbucaqli) Cevre() float64 {
	return 2 * (d.En + d.Boy)
}

// Daire
type Daire struct {
	Radius float64
}

func (da Daire) Sahe() float64 {
	return math.Pi * da.Radius * da.Radius
}

func (da Daire) Cevre() float64 {
	return 2 * math.Pi * da.Radius
}

// Ucbucaq
type Ucbucaq struct {
	A, B, C float64 // terefler
	Hundurluk float64
}

func (u Ucbucaq) Sahe() float64 {
	return 0.5 * u.A * u.Hundurluk
}

func (u Ucbucaq) Cevre() float64 {
	return u.A + u.B + u.C
}

// -------------------------------------------
// 3. Interface parametr kimi istifade
// -------------------------------------------
// Ferqli tipli sekilleri eyni funksiyaya gondermek olar
func sekilMelumati(s Sekil) {
	fmt.Printf("Tip: %T\n", s)
	fmt.Printf("Sahe: %.2f\n", s.Sahe())
	fmt.Printf("Cevre: %.2f\n", s.Cevre())
	fmt.Println("---")
}

// -------------------------------------------
// 4. Bos interface (empty interface)
// -------------------------------------------
// interface{} ve ya any - istənilən tipi qəbul edir
func yazdir(deyer interface{}) {
	fmt.Printf("Deyer: %v, Tip: %T\n", deyer, deyer)
}

// Go 1.18+ den sonra "any" istifade olunur (interface{} ile eynidir)
func yazdir2(deyer any) {
	fmt.Printf("Deyer: %v, Tip: %T\n", deyer, deyer)
}

// -------------------------------------------
// 5. Stringer interface (fmt paketi)
// -------------------------------------------
// fmt.Println cagiranda avtomatik isleyir
type Oyuncu struct {
	Ad  string
	Xal int
}

func (o Oyuncu) String() string {
	return fmt.Sprintf("%s (%d xal)", o.Ad, o.Xal)
}

// -------------------------------------------
// 6. Error interface
// -------------------------------------------
// Go-da error bir interface-dir:
// type error interface { Error() string }
type XususiBug struct {
	Kod   int
	Mesaj string
}

func (x XususiBug) Error() string {
	return fmt.Sprintf("Xeta %d: %s", x.Kod, x.Mesaj)
}

func main() {

	// Interface istifadesi
	d := Duzbucaqli{En: 5, Boy: 3}
	da := Daire{Radius: 7}
	u := Ucbucaq{A: 6, B: 8, C: 10, Hundurluk: 4}

	sekilMelumati(d)
	sekilMelumati(da)
	sekilMelumati(u)

	// Slice of interface
	sekiller := []Sekil{d, da, u}
	toplam := 0.0
	for _, s := range sekiller {
		toplam += s.Sahe()
	}
	fmt.Printf("Toplam sahe: %.2f\n\n", toplam)

	// Bos interface
	yazdir(42)
	yazdir("salam")
	yazdir(true)
	yazdir(3.14)

	// Type assertion - interface-den esl tipi almaq
	var s Sekil = Daire{Radius: 5}

	// a) Sadə assertion (uygun deyilse panic verir)
	daire := s.(Daire)
	fmt.Println("Daire radius:", daire.Radius)

	// b) Tehlukesiz assertion (comma-ok pattern)
	daire2, ok := s.(Daire)
	if ok {
		fmt.Println("Bu dairedir, radius:", daire2.Radius)
	}

	duzbucaqli, ok := s.(Duzbucaqli)
	if !ok {
		fmt.Println("Bu duzbucaqli deyil")
	}
	_ = duzbucaqli

	// c) Type switch
	switch v := s.(type) {
	case Daire:
		fmt.Println("Daire, radius:", v.Radius)
	case Duzbucaqli:
		fmt.Println("Duzbucaqli, en:", v.En)
	default:
		fmt.Printf("Basqa tip: %T\n", v)
	}

	// Stringer
	oyuncu := Oyuncu{Ad: "Orkhan", Xal: 100}
	fmt.Println(oyuncu) // Orkhan (100 xal) - String() avtomatik cagrilir

	// Error interface
	xeta := XususiBug{Kod: 404, Mesaj: "tapilmadi"}
	fmt.Println(xeta) // Xeta 404: tapilmadi

	// -------------------------------------------
	// 7. Interface terkibi (composition)
	// -------------------------------------------
	// Interface-ler bir-birini ehtiva ede biler
	type Oxuyan interface {
		Oxu(p []byte) (n int, err error)
	}

	type Yazan interface {
		Yaz(p []byte) (n int, err error)
	}

	type OxuyanYazan interface {
		Oxuyan
		Yazan
	}
	// OxuyanYazan - hem Oxu hem Yaz metodlarina sahib olmali
	_ = (*OxuyanYazan)(nil) // sadece tip yoxlaması
}
