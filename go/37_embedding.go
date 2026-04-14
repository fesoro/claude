package main

import (
	_ "embed"
	"embed"
	"fmt"
	"io/fs"
	"net/http"
)

// ===============================================
// GO EMBED - FAYLLARI BINARY-YE DAXIL ETME
// ===============================================

// Go 1.16+ ile faylları kompilyasiya zamanı binary-ye daxil ede bilersiz
// HTML, CSS, JS, SQL migration, config şablonları ucun ideal
// Deploy zamani ayri fayl daşimaga ehtiyac qalmir!

// -------------------------------------------
// 1. Tek fayl embed etmek (string kimi)
// -------------------------------------------
//go:embed templates/salam.txt
var salamMetni string

// -------------------------------------------
// 2. Tek fayl embed etmek (byte slice kimi)
// -------------------------------------------
//go:embed templates/logo.png
var logoData []byte

// -------------------------------------------
// 3. Butov qovlug embed etmek
// -------------------------------------------
//go:embed templates/*
var sablonlar embed.FS

// -------------------------------------------
// 4. Bir nece naxis ile embed
// -------------------------------------------
//go:embed static/*.css static/*.js
var staticFiles embed.FS

// -------------------------------------------
// 5. SQL migration fayllari
// -------------------------------------------
//go:embed migrations/*.sql
var migrationlar embed.FS

func main() {

	// -------------------------------------------
	// String embed istifade
	// -------------------------------------------
	fmt.Println("Salam metni:")
	fmt.Println(salamMetni)

	// -------------------------------------------
	// Byte embed istifade
	// -------------------------------------------
	fmt.Printf("Logo olcusu: %d byte\n", len(logoData))

	// -------------------------------------------
	// FS embed istifade - fayllari oxumaq
	// -------------------------------------------
	// Tek fayl oxumaq
	mezmun, err := sablonlar.ReadFile("templates/salam.txt")
	if err != nil {
		fmt.Println("Oxuma xetasi:", err)
	} else {
		fmt.Println("FS-den oxundu:", string(mezmun))
	}

	// Qovluqu gezme
	fmt.Println("\nEmbed olunmus fayllar:")
	fs.WalkDir(sablonlar, ".", func(yol string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if !d.IsDir() {
			info, _ := d.Info()
			fmt.Printf("  %s (%d byte)\n", yol, info.Size())
		}
		return nil
	})

	// -------------------------------------------
	// Migration fayllarini oxumaq
	// -------------------------------------------
	girisler, err := migrationlar.ReadDir("migrations")
	if err != nil {
		fmt.Println("Migration xetasi:", err)
	} else {
		fmt.Println("\nMigration fayllari:")
		for _, giris := range girisler {
			mezmun, _ := migrationlar.ReadFile("migrations/" + giris.Name())
			fmt.Printf("--- %s ---\n%s\n", giris.Name(), string(mezmun))
		}
	}

	// -------------------------------------------
	// HTTP ile embed olunmus fayllari servis etmek
	// -------------------------------------------
	// Statik faylları birbaşa binary-den servis edir
	// Ayri fayl sistemi lazim deyil!

	staticSub, _ := fs.Sub(staticFiles, "static")
	http.Handle("/static/", http.StripPrefix("/static/", http.FileServer(http.FS(staticSub))))

	// HTML şablonunu embed-den oxuyub render etmek
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		data, _ := sablonlar.ReadFile("templates/index.html")
		w.Header().Set("Content-Type", "text/html")
		w.Write(data)
	})

	// FAYL STRUKTURU ORNEGI:
	// myproject/
	// ├── main.go
	// ├── templates/
	// │   ├── salam.txt
	// │   ├── index.html
	// │   └── logo.png
	// ├── static/
	// │   ├── style.css
	// │   └── app.js
	// └── migrations/
	//     ├── 001_create_users.sql
	//     └── 002_add_email.sql

	// MUHUM QAYDALAR:
	// - //go:embed direktivinden evvel bos setir olmamalidir
	// - Embed olunan fayllar modulun qovlugunda olmalidir
	// - .. (parent directory) istifade etmek OLMAZ
	// - Gizli fayllar (.ile baslayanlar) default olaraq daxil edilmir
	// - Butun qovlugu daxil etmek: //go:embed all:templates
	//   (all: gizli fayllari da daxil edir)

	fmt.Println("\n// go build ile kompilyasiya edin - butun fayllar binary-ye daxildir")
	fmt.Println("// Tek binary deploy edin - xarici fayl lazim deyil!")
}
