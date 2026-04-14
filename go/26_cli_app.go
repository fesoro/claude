package main

import (
	"flag"
	"fmt"
	"os"
	"strings"
)

// ===============================================
// CLI PROQRAM YARATMA
// ===============================================

// Go CLI aleti yaratmaq ucun idealdır
// Docker, Kubernetes, Terraform - hamisı Go ile yazılıb

func main() {

	// -------------------------------------------
	// 1. OS.ARGS - xam arqumentler
	// -------------------------------------------
	fmt.Println("Proqram adi:", os.Args[0])
	fmt.Println("Arqumentler:", os.Args[1:]) // ilk element proqram adidir

	// Misal: go run main.go salam dunya
	// os.Args = ["main", "salam", "dunya"]

	// -------------------------------------------
	// 2. FLAG paketi - bayraqlari parse etmek
	// -------------------------------------------
	// En cox istifade olunan usul

	ad := flag.String("ad", "Dunya", "Salamlama ucun ad")
	sayi := flag.Int("sayi", 1, "Nece defe salam demeli")
	boyuk := flag.Bool("boyuk", false, "Boyuk herflerle yazsin")
	port := flag.Int("port", 8080, "Server portu")

	// Xususi istifade mesaji
	flag.Usage = func() {
		fmt.Fprintf(os.Stderr, "Istifade: %s [bayraklar]\n\n", os.Args[0])
		fmt.Fprintln(os.Stderr, "Bayraklar:")
		flag.PrintDefaults()
		fmt.Fprintln(os.Stderr, "\nOrnekler:")
		fmt.Fprintln(os.Stderr, "  myapp -ad Orkhan -sayi 3")
		fmt.Fprintln(os.Stderr, "  myapp -boyuk -ad Go")
	}

	flag.Parse() // parse etmeyi unutma!

	// Flag olmayan arqumentler
	qalanArgs := flag.Args()
	fmt.Println("Qalan arqumentler:", qalanArgs)

	// Bayraqlari istifade et
	for i := 0; i < *sayi; i++ {
		mesaj := fmt.Sprintf("Salam, %s!", *ad)
		if *boyuk {
			mesaj = strings.ToUpper(mesaj)
		}
		fmt.Println(mesaj)
	}
	fmt.Println("Port:", *port)

	// Isletme ornekleri:
	// go run main.go -ad Orkhan -sayi 3
	// go run main.go -boyuk -ad Go
	// go run main.go -help

	// -------------------------------------------
	// 3. Alt emrler (subcommands)
	// -------------------------------------------
	// git add, git commit kimi alt emrler yaratmaq

	if len(os.Args) < 2 {
		fmt.Println("Alt emr lazimdir: add, list, delete")
		return
	}

	// Her alt emr ucun ayri FlagSet
	addCmd := flag.NewFlagSet("add", flag.ExitOnError)
	addAd := addCmd.String("ad", "", "Elave edilecek ad")

	listCmd := flag.NewFlagSet("list", flag.ExitOnError)
	listLimit := listCmd.Int("limit", 10, "Nece element goster")

	deleteCmd := flag.NewFlagSet("delete", flag.ExitOnError)
	deleteID := deleteCmd.Int("id", 0, "Silinecek ID")

	switch os.Args[1] {
	case "add":
		addCmd.Parse(os.Args[2:])
		fmt.Println("ELAVE:", *addAd)

	case "list":
		listCmd.Parse(os.Args[2:])
		fmt.Println("SIYAHI, limit:", *listLimit)

	case "delete":
		deleteCmd.Parse(os.Args[2:])
		fmt.Println("SIL, ID:", *deleteID)

	default:
		fmt.Println("Bilinmeyen emr:", os.Args[1])
		os.Exit(1)
	}

	// Isletme:
	// go run main.go add -ad "Yeni element"
	// go run main.go list -limit 5
	// go run main.go delete -id 3

	// -------------------------------------------
	// 4. Reng ve formatlama (ANSI kodlari)
	// -------------------------------------------
	const (
		Qirmizi = "\033[31m"
		Yashil  = "\033[32m"
		Sari    = "\033[33m"
		Goy     = "\033[34m"
		Sifirla = "\033[0m"
	)

	fmt.Println(Qirmizi + "XETA: Bir sey pis getdi!" + Sifirla)
	fmt.Println(Yashil + "UGUR: Emeliyyat tamamlandi!" + Sifirla)
	fmt.Println(Sari + "XEBARDARLIQ: Diqqet edin!" + Sifirla)
	fmt.Println(Goy + "MELUMAT: Isleyir..." + Sifirla)

	// -------------------------------------------
	// 5. Cixis kodu (exit code)
	// -------------------------------------------
	// 0 = ugurlu, 1+ = xeta
	// os.Exit(0) - ugurlu
	// os.Exit(1) - umumi xeta
	// os.Exit(2) - istifade xetasi

	// QEYD: Production CLI ucun cobra kitabxanasina baxin:
	// go get github.com/spf13/cobra
	// Docker, Kubernetes, Hugo - hamisi cobra istifade edir
}
