package main

import (
	"fmt"
	"os"
	"path/filepath"
)

// ===============================================
// FAYL - IRELILEMIS (Paths, Temp, Directories)
// ===============================================

func main() {

	// -------------------------------------------
	// 1. FILE PATHS (path/filepath)
	// -------------------------------------------
	fmt.Println("=== File Paths ===")

	// Yollari birlesdirme (OS-a uygun separator istifade edir)
	yol := filepath.Join("home", "orkhan", "projects", "main.go")
	fmt.Println("Join:", yol) // home/orkhan/projects/main.go

	// Qovluq ve fayl adini ayirma
	qovluq := filepath.Dir(yol)
	fayl := filepath.Base(yol)
	fmt.Println("Dir:", qovluq)   // home/orkhan/projects
	fmt.Println("Base:", fayl)    // main.go

	// Uzantini alma
	ext := filepath.Ext("photo.jpg")
	fmt.Println("Ext:", ext) // .jpg

	// Uzantisiz ad
	adsiz := filepath.Base("photo.jpg")
	adsiz = adsiz[:len(adsiz)-len(filepath.Ext(adsiz))]
	fmt.Println("Adsiz:", adsiz) // photo

	// Absolute yol
	abs, _ := filepath.Abs("main.go")
	fmt.Println("Abs:", abs)

	// Nisbi yol
	nisbi, _ := filepath.Rel("/home/orkhan", "/home/orkhan/projects/main.go")
	fmt.Println("Rel:", nisbi) // projects/main.go

	// Yolu temizleme
	temiz := filepath.Clean("/home/orkhan/../orkhan/./projects")
	fmt.Println("Clean:", temiz) // /home/orkhan/projects

	// Separator
	fmt.Println("Separator:", string(filepath.Separator)) // / (Linux/Mac) veya \ (Windows)

	// Uygunlasma (match)
	uygun, _ := filepath.Match("*.go", "main.go")
	fmt.Println("Match *.go:", uygun) // true

	// -------------------------------------------
	// 2. GLOB - naxisla fayl axtarisi
	// -------------------------------------------
	fmt.Println("\n=== Glob ===")

	goFayllari, _ := filepath.Glob("*.go")
	fmt.Println("*.go fayllari:", len(goFayllari))
	for i, f := range goFayllari {
		if i >= 5 {
			fmt.Println("  ...")
			break
		}
		fmt.Println(" ", f)
	}

	// -------------------------------------------
	// 3. WALK - qovluqu rekursiv gezme
	// -------------------------------------------
	fmt.Println("\n=== Walk ===")

	sayi := 0
	filepath.WalkDir(".", func(yol string, d os.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if sayi >= 10 {
			return filepath.SkipAll // gezmeyi dayandır
		}
		if d.IsDir() && d.Name() == ".git" {
			return filepath.SkipDir // bu qovlugu kec
		}
		if !d.IsDir() {
			fmt.Printf("  %s\n", yol)
			sayi++
		}
		return nil
	})

	// -------------------------------------------
	// 4. TEMPORARY FILES AND DIRECTORIES
	// -------------------------------------------
	fmt.Println("\n=== Muveqqeti Fayllar ===")

	// Muveqqeti fayl yaratma
	tmpFile, err := os.CreateTemp("", "myapp-*.txt")
	if err != nil {
		fmt.Println("Xeta:", err)
		return
	}
	fmt.Println("Tmp fayl:", tmpFile.Name())

	// Fayla yaz
	tmpFile.WriteString("Muveqqeti melumat")
	tmpFile.Close()

	// Oxu
	data, _ := os.ReadFile(tmpFile.Name())
	fmt.Println("Mezmun:", string(data))

	// Sil
	os.Remove(tmpFile.Name())
	fmt.Println("Tmp fayl silindi")

	// Muveqqeti qovluq yaratma
	tmpDir, err := os.MkdirTemp("", "myapp-*")
	if err != nil {
		fmt.Println("Xeta:", err)
		return
	}
	fmt.Println("Tmp qovluq:", tmpDir)

	// Qovluqda fayl yarat
	tmpFilePath := filepath.Join(tmpDir, "data.txt")
	os.WriteFile(tmpFilePath, []byte("test"), 0644)

	// Temizle
	os.RemoveAll(tmpDir)
	fmt.Println("Tmp qovluq silindi")

	// -------------------------------------------
	// 5. LINE FILTERS (setir filteri)
	// -------------------------------------------
	fmt.Println("\n=== Line Filter ===")

	// Line filter - stdin-den oxuyub, emal edib, stdout-a yazir
	// Unix pipe ile istifade olunur: cat file.txt | myfilter

	fmt.Println(`
Numune line filter proqrami:

package main

import (
    "bufio"
    "fmt"
    "os"
    "strings"
)

func main() {
    scanner := bufio.NewScanner(os.Stdin)
    setirNo := 1

    for scanner.Scan() {
        setir := scanner.Text()

        // Boyuk herfe cevir
        boyuk := strings.ToUpper(setir)

        // Setir nomresi ile yaz
        fmt.Printf("%3d: %s\n", setirNo, boyuk)
        setirNo++
    }

    if err := scanner.Err(); err != nil {
        fmt.Fprintln(os.Stderr, "oxuma xetasi:", err)
        os.Exit(1)
    }
}

// Istifade:
// echo "salam dunya" | go run filter.go
// cat dosya.txt | go run filter.go
// cat dosya.txt | go run filter.go > netice.txt
`)

	// -------------------------------------------
	// 6. Fayl melumatlari (stat)
	// -------------------------------------------
	fmt.Println("=== Fayl Melumatlari ===")

	info, err := os.Stat(".")
	if err == nil {
		fmt.Println("Ad:", info.Name())
		fmt.Println("Olcu:", info.Size())
		fmt.Println("Qovluqdur:", info.IsDir())
		fmt.Println("Icaze:", info.Mode())
		fmt.Println("Deyisdirilme:", info.ModTime().Format("2006-01-02 15:04:05"))
	}

	// -------------------------------------------
	// 7. Symlink
	// -------------------------------------------
	// os.Symlink("orijinal.txt", "link.txt")  // simvolik link yarat
	// hedef, _ := os.Readlink("link.txt")     // linkin hədəfini oxu
	// info, _ := os.Lstat("link.txt")         // linkin oz melumati (hedəf deyil)
}
