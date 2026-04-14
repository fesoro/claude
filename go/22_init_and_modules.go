package main

import "fmt"

// ===============================================
// INIT FUNKSIYASI VE GO MODULES (DETALLI)
// ===============================================

// -------------------------------------------
// 1. init() funksiyasi
// -------------------------------------------
// - main()-den EVVEL avtomatik isleyir
// - Her faylda bir nece init() ola biler
// - Parametr almaz, deyer qaytarmaz
// - Paket seviyyesinde deyiskenleri hazirlamaq ucun istifade olunur
// - Import sirasi ile isleyir: A -> B import edirse, evvelce B-nin init()-i isleyir

// Islenme sirasi:
// 1. Import olunan paketlerin init()-leri
// 2. Paket seviyyesindeki deyiskenler
// 3. init() funksiyasi
// 4. main() funksiyasi

var konfiqurasiya string

func init() {
	// Proqram baslamazdan evvel hazirlanir
	konfiqurasiya = "production"
	fmt.Println("init() isledi - konfiqurasiya:", konfiqurasiya)
}

func init() {
	// Eyni faylda ikinci init() - bu da isleyecek
	fmt.Println("Ikinci init() isledi")
}

func main() {
	fmt.Println("main() isledi")
	fmt.Println("Konfiqurasiya:", konfiqurasiya)

	// CIXIS SIRASI:
	// init() isledi - konfiqurasiya: production
	// Ikinci init() isledi
	// main() isledi

	// =============================================
	// GO MODULES - DETALLI
	// =============================================

	fmt.Println(`
=== GO MODULES ===

1. Yeni modul yaratmaq:
   go mod init github.com/username/project

   Bu go.mod faylini yaradir. Numune:
   ----------------------------------------
   module github.com/username/project

   go 1.21

   require (
       github.com/gin-gonic/gin v1.9.1
       github.com/lib/pq v1.10.9
   )
   ----------------------------------------

2. Paket elave etmek:
   go get github.com/gin-gonic/gin          # en son versiya
   go get github.com/gin-gonic/gin@v1.9.1   # xususi versiya
   go get github.com/gin-gonic/gin@latest   # en son versiya

3. Istifade olunmayan paketleri temizlemek:
   go mod tidy

4. go.sum faylı:
   - Paketlerin hash deyerlerini saxlayir (tehlukesizlik ucun)
   - Avtomatik yaranir, el ile deyismeyin
   - Git-e commit edin

5. Vendor qovlugu (offline istifade):
   go mod vendor        # paketleri vendor/ qovluguna kopyala
   go build -mod=vendor # vendor-dan istifade et

6. Muhum emrler:
   go mod init     - yeni modul yaratmaq
   go mod tidy     - temizlemek (lazim olmayanlari sil, lazim olanlari elave et)
   go mod download - paketleri yukle
   go mod verify   - paketlerin duzgunluyunu yoxla
   go mod graph    - asililiq qrafikini goster
   go mod why      - niye bu paket lazimdir?
   go list -m all  - butun asililiqlar

7. Xususi paket yaratma:
   myproject/
   ├── go.mod
   ├── main.go          (package main)
   ├── utils/
   │   ├── helper.go    (package utils)
   │   └── math.go      (package utils)
   └── models/
       └── user.go      (package models)

   main.go-dan istifade:
   import "github.com/username/myproject/utils"
   import "github.com/username/myproject/models"

8. EXPORT QAYDALARI:
   Boyuk herf = Export (public)  -> utils.Hesabla()
   Kicik herf = Gizli (private) -> utils.hesabla()
   Bu qayda funksiya, struct, sahe, sabit - HER SEY ucun kecerlidir
`)
}
