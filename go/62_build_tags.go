package main

import (
	"fmt"
	"runtime"
)

// ===============================================
// BUILD TAGS VE BUILD CONSTRAINTS
// ===============================================

// Build tags - mueyyen sertler altinda faylin kompilyasiya olunub-olunmayacagini idare edir
// Platform-specific kod, feature flags, test/debug rejimi ucun istifade olunur

func main() {

	fmt.Println("Cari platforma:", runtime.GOOS, runtime.GOARCH)

	fmt.Println(`
=======================================
1. FAYL ADI ILE CONSTRAINT (en sadə)
=======================================

Go fayl adina gore avtomatik platforma secir:

  config_linux.go     -> yalniz Linux-da kompilyasiya olunur
  config_darwin.go    -> yalniz macOS-da kompilyasiya olunur
  config_windows.go   -> yalniz Windows-da kompilyasiya olunur
  config_linux_amd64.go -> yalniz Linux AMD64-de

Misal:
  // fayl: path_linux.go
  package config
  const DefaultPath = "/etc/myapp/config.yaml"

  // fayl: path_windows.go
  package config
  const DefaultPath = "C:\\myapp\\config.yaml"

  // fayl: path_darwin.go
  package config
  const DefaultPath = "/usr/local/etc/myapp/config.yaml"

Fayl adi formatlari:
  *_GOOS.go           -> config_linux.go
  *_GOARCH.go         -> config_amd64.go
  *_GOOS_GOARCH.go    -> config_linux_amd64.go
  *_test.go           -> yalniz test zamani
=======================================
2. BUILD TAG ILE CONSTRAINT (Go 1.17+)
=======================================

Faylin en ustunde:

  //go:build linux
  //go:build darwin
  //go:build windows
  //go:build !windows          (windows xaric)
  //go:build linux && amd64    (VE)
  //go:build linux || darwin   (VEYA)
  //go:build linux && !arm64   (Linux, amma ARM64 deyil)

Misal:
`)

	// Platform-specific ornek
	platformOrnek := `
// ==========================================
// fayl: notifier_linux.go
// ==========================================
//go:build linux

package main

import "fmt"

func Notify(mesaj string) {
    // Linux-da notify-send istifade et
    fmt.Println("[Linux] Bildiris:", mesaj)
    // exec.Command("notify-send", mesaj).Run()
}

// ==========================================
// fayl: notifier_darwin.go
// ==========================================
//go:build darwin

package main

import "fmt"

func Notify(mesaj string) {
    // macOS-da osascript istifade et
    fmt.Println("[macOS] Bildiris:", mesaj)
    // exec.Command("osascript", "-e", "display notification ...").Run()
}

// ==========================================
// fayl: notifier_windows.go
// ==========================================
//go:build windows

package main

import "fmt"

func Notify(mesaj string) {
    fmt.Println("[Windows] Bildiris:", mesaj)
    // Windows toast notification
}
`
	fmt.Println(platformOrnek)

	fmt.Println(`
=======================================
3. XUSUSI BUILD TAGS (Feature Flags)
=======================================

// fayl: feature_premium.go
//go:build premium

package main

func PremiumXususiyyet() {
    // Yalniz premium build-de movcuddur
}

// Build etmek:
go build -tags premium ./...

// Bir nece tag:
go build -tags "premium debug" ./...
=======================================
4. CGO ILE CONSTRAINT
=======================================

//go:build cgo       -> yalniz CGO aktiv olanda
//go:build !cgo      -> yalniz CGO deaktiv olanda

// CGO-suz build (statik binary):
CGO_ENABLED=0 go build ./...
=======================================
5. DEBUG / RELEASE NAXISI
=======================================
`)

	debugOrnek := `
// ==========================================
// fayl: debug.go
// ==========================================
//go:build debug

package main

import "log"

func init() {
    log.Println("DEBUG rejimi aktivdir")
}

var DebugMode = true

func DebugLog(msg string) {
    log.Println("[DEBUG]", msg)
}

// ==========================================
// fayl: release.go
// ==========================================
//go:build !debug

package main

var DebugMode = false

func DebugLog(msg string) {
    // istehsalda hec ne etme
}

// Build emrleri:
// Development: go build -tags debug -o myapp ./...
// Production:  go build -o myapp ./...
`
	fmt.Println(debugOrnek)

	fmt.Println(`
=======================================
6. INTEGRATION TEST NAXISI
=======================================

// fayl: db_test.go
//go:build integration

package db

import "testing"

func TestDatabaseConnection(t *testing.T) {
    // Real database lazimdir
    // Normal "go test" ile islemez
}

// Isletmek:
go test -tags integration ./...
// Normal testler: go test ./... (integration testleri kecilir)
=======================================
7. GO VERSION CONSTRAINT
=======================================

//go:build go1.21       -> Go 1.21+ teleb edir
//go:build go1.22       -> Go 1.22+ teleb edir

// Yeni Go xususiyyetlerini istifade eden kod
// kohne versiyalarda kompilyasiya olunmaz
=======================================
8. CROSS COMPILATION ORNEKLERI
=======================================

# Linux AMD64
GOOS=linux GOARCH=amd64 go build -o bin/app-linux-amd64

# Linux ARM64 (Raspberry Pi, AWS Graviton)
GOOS=linux GOARCH=arm64 go build -o bin/app-linux-arm64

# macOS Intel
GOOS=darwin GOARCH=amd64 go build -o bin/app-darwin-amd64

# macOS Apple Silicon
GOOS=darwin GOARCH=arm64 go build -o bin/app-darwin-arm64

# Windows
GOOS=windows GOARCH=amd64 go build -o bin/app.exe

# Butun platformalar ucun build script:
#!/bin/bash
platforms=("linux/amd64" "linux/arm64" "darwin/amd64" "darwin/arm64" "windows/amd64")
for platform in "${platforms[@]}"; do
    GOOS=${platform%/*}
    GOARCH=${platform#*/}
    output="bin/myapp-${GOOS}-${GOARCH}"
    [[ "$GOOS" == "windows" ]] && output+=".exe"
    GOOS=$GOOS GOARCH=$GOARCH go build -o $output ./cmd/api
done
=======================================
MOVCUD GOOS / GOARCH SIYAHISI
=======================================
go tool dist list    # butun platform/arch kombinasiyalari
`)
}
