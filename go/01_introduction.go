package main

// ===============================================
// GO DILINE GIRIS VE QURASDIRMA
// ===============================================

// Go (Golang) - Google terefinden yaradilmis, sadə, suretli ve guvenli proqramlasdirma dilidir.
// 2009-cu ilde Robert Griesemer, Rob Pike ve Ken Thompson terefinden yaradildi.

// Go-nun ustunlukleri:
// - Cox sadə sintaksis (oyrenmesi asandir)
// - Suretli kompilyasiya (kod tez islenir)
// - Daxili concurrency destəyi (goroutine)
// - Garbage collection (yaddas avtomatik temizlenir)
// - Statik tipli dil (xetalar kompilyasiya zamaninda tapilir)
// - Bir fayla kompilyasiya olunur (deploy etmek asandir)

// QURASDIRMA:
// 1. https://go.dev/dl/ saytindan yukle
// 2. Qurasdirdiqdan sonra terminalda yoxla:
//    go version
//
// 3. Yeni layihe yaratmaq ucun:
//    mkdir myproject
//    cd myproject
//    go mod init myproject
//
// 4. main.go faylini yarat ve islet:
//    go run main.go
//
// 5. Kompilyasiya etmek ucun:
//    go build main.go

import "fmt"

// Her Go proqrami "main" paketinden ve "main" funksiyasindan baslanir.
// Bu proqramin giris noqtesidir (entry point).
func main() {
	fmt.Println("Salam, Dunya!") // Ekrana metn yazir
	fmt.Println("Go dilini oyrenirik!")
}

// ISLETMEK UCUN:
// go run 01_giris_ve_qurasdirma.go
