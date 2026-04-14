package main

import (
	"fmt"
	"reflect"
)

// ===============================================
// REFLECTION (EKS OLUNMA)
// ===============================================

// Reflection - proqramin oz tiplerini ve deyerlerini runtime-da yoxlamasina imkan verir
// JSON serialization, ORM, framework-lar reflection istifade edir
// Ehtiyatla istifade edin - yavashdir ve tip tehlukesizliyini azaldir

type Shexs struct {
	Ad    string `json:"ad" validate:"required"`
	Soyad string `json:"soyad"`
	Yas   int    `json:"yas" validate:"min=0,max=150"`
}

func (s Shexs) Salam() string {
	return "Salam, " + s.Ad
}

func (s *Shexs) YasDeyis(yeniYas int) {
	s.Yas = yeniYas
}

func main() {

	// -------------------------------------------
	// 1. TypeOf ve ValueOf - esas funksiyalar
	// -------------------------------------------
	x := 42
	t := reflect.TypeOf(x)
	v := reflect.ValueOf(x)

	fmt.Println("Tip:", t)           // int
	fmt.Println("Deyer:", v)         // 42
	fmt.Println("Kind:", t.Kind())   // int
	fmt.Println("Int deyer:", v.Int()) // 42

	// -------------------------------------------
	// 2. Ferqli tipleri yoxlamaq
	// -------------------------------------------
	deyerler := []interface{}{42, "salam", 3.14, true, []int{1, 2}}

	for _, d := range deyerler {
		t := reflect.TypeOf(d)
		v := reflect.ValueOf(d)
		fmt.Printf("Tip: %-12s Kind: %-10s Deyer: %v\n", t, t.Kind(), v)
	}

	// -------------------------------------------
	// 3. Struct sahelerini yoxlamaq
	// -------------------------------------------
	adam := Shexs{Ad: "Orkhan", Soyad: "Shukurlu", Yas: 25}
	tip := reflect.TypeOf(adam)
	deyer := reflect.ValueOf(adam)

	fmt.Printf("\nStruct: %s, Sahe sayi: %d\n", tip.Name(), tip.NumField())

	for i := 0; i < tip.NumField(); i++ {
		sahe := tip.Field(i)
		saheDeyeri := deyer.Field(i)
		fmt.Printf("  %s (%s) = %v\n", sahe.Name, sahe.Type, saheDeyeri)

		// Struct tag oxumaq
		jsonTag := sahe.Tag.Get("json")
		validateTag := sahe.Tag.Get("validate")
		if jsonTag != "" {
			fmt.Printf("    json tag: %s\n", jsonTag)
		}
		if validateTag != "" {
			fmt.Printf("    validate tag: %s\n", validateTag)
		}
	}

	// -------------------------------------------
	// 4. Metodlari yoxlamaq
	// -------------------------------------------
	fmt.Printf("\nMetod sayi: %d\n", tip.NumMethod())
	for i := 0; i < tip.NumMethod(); i++ {
		metod := tip.Method(i)
		fmt.Printf("  Metod: %s, Tip: %s\n", metod.Name, metod.Type)
	}

	// -------------------------------------------
	// 5. Deyeri deyismek (pointer lazimdir)
	// -------------------------------------------
	y := 100
	yPtr := reflect.ValueOf(&y).Elem() // Elem() pointer-in gosterdiyi deyeri alir

	if yPtr.CanSet() {
		yPtr.SetInt(200)
	}
	fmt.Println("\nDeyismis y:", y) // 200

	// Struct sahesini deyismek
	adam2 := &Shexs{Ad: "Test", Yas: 20}
	structDeyer := reflect.ValueOf(adam2).Elem()
	adSahesi := structDeyer.FieldByName("Ad")
	if adSahesi.CanSet() {
		adSahesi.SetString("Deyismis")
	}
	fmt.Println("Deyismis ad:", adam2.Ad) // Deyismis

	// -------------------------------------------
	// 6. Kind ile tip yoxlamasi
	// -------------------------------------------
	yoxla := func(v interface{}) {
		kind := reflect.TypeOf(v).Kind()
		switch kind {
		case reflect.Int, reflect.Int64:
			fmt.Println(v, "tam ededdir")
		case reflect.String:
			fmt.Println(v, "stringdir")
		case reflect.Slice:
			fmt.Println(v, "slice-dir")
		case reflect.Map:
			fmt.Println(v, "map-dir")
		case reflect.Struct:
			fmt.Println(v, "struct-dur")
		default:
			fmt.Println(v, "basqa tipdir:", kind)
		}
	}

	yoxla(42)
	yoxla("salam")
	yoxla([]int{1, 2})
	yoxla(map[string]int{"a": 1})
	yoxla(Shexs{})

	// -------------------------------------------
	// 7. Dinamik funksiya cagirilmasi
	// -------------------------------------------
	adam3 := Shexs{Ad: "Orkhan", Yas: 25}
	metodDeyer := reflect.ValueOf(adam3).MethodByName("Salam")
	netice := metodDeyer.Call(nil) // parametrsiz cagir
	fmt.Println("\nMetod neticesi:", netice[0])

	// -------------------------------------------
	// 8. Yeni instance yaratmaq
	// -------------------------------------------
	tipShexs := reflect.TypeOf(Shexs{})
	yeniDeyer := reflect.New(tipShexs).Elem()
	yeniDeyer.FieldByName("Ad").SetString("Yeni")
	yeniDeyer.FieldByName("Yas").SetInt(30)

	yeniShexs := yeniDeyer.Interface().(Shexs)
	fmt.Println("Yeni yaradilmis:", yeniShexs)

	// MUHUM QEYDLER:
	// - Reflection YAVASH-dir. Performance kritik yerlerde qaçının
	// - Kompilyasiya zamani tip yoxlamasi YOXDUR - runtime xetalari ola biler
	// - Yalniz export olunmus (boyuk herfl) saheler Set edile biler
	// - Mumkun olduqca generics ve ya interface istifade edin
	// - Reflection esas istifade yerleri: JSON/XML marshal, ORM, test framework
}
