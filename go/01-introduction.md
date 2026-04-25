# Go Dilinə Giriş (Junior)

## İcmal

Go (Golang) — Google tərəfindən 2009-cu ildə Robert Griesemer, Rob Pike və Ken Thompson tərəfindən yaradılmış, sadə, sürətli və etibarlı proqramlaşdırma dilidir. Statik tipli, compiled dildir — yəni xətalar runtime-da deyil, kompilyasiya zamanında aşkarlanır. Go, xüsusilə backend servislər, CLI alətlər və yüksək yüklü sistemlər üçün istifadə olunur.

## Niyə Vacibdir

PHP/Laravel developer üçün Go-ya keçid strateji addımdır: microservice-lər, high-performance API-lər, Kubernetes operators və cloud-native tooling-in böyük hissəsi Go-da yazılır. Go-nun binary deployment modeli — Docker image-lərini minimuma endirməyə, startup time-ı sıfıra yaxınlaşdırmağa imkan verir. Bundan əlavə, Go-nun daxili concurrency mexanizmi (goroutines) PHP-nin process-based modelindən fundamental fərqlidir və real layihələrdə böyük performance üstünlüyü verir.

## Əsas Anlayışlar

- **Package** — hər Go faylı bir pakete aiddir; `main` paketi proqramın giriş nöqtəsidir
- **`func main()`** — proqramın başladığı yer; PHP-dəki `index.php` kimi düşünün
- **`go run`** — faylı kompilyasiya etmədən birbaşa işlət (development üçün)
- **`go build`** — yekun binary faylı yarat (production üçün)
- **`go mod init`** — yeni modul yarat; PHP-dəki `composer init` kimidir
- **`fmt` paketi** — standard kitabxanadakı formatting/printing paketi
- **`fmt.Println`** — konsola çıxış; PHP-dəki `echo` kimidir, amma newline əlavə edir
- **Garbage Collection** — yaddaş idarəetməsi avtomatikdir; PHP kimi manual `free()` yoxdur
- **Single binary** — `go build` nəticəsində tək executable fayl yaranır; heç bir dependency lazım deyil

## Praktik Baxış

**Real layihədə istifadə:**
- Microservice-lər — sürətli startup, aşağı memory footprint
- CLI alətlər — cross-platform single binary deploy
- gRPC server-lər — PHP-nin zəif olduğu yüksək throughput ssenarilər
- Background worker-lər — queue consumer, cron job

**PHP ilə fərqi:**
- PHP hər request üçün prosess başladır; Go daima işlər
- PHP `echo` — Go `fmt.Println` (amma birbaşa HTTP response yazmır)
- PHP-dəki `composer.json` → Go-da `go.mod`
- PHP-dəki `vendor/` → Go-da `$GOPATH/pkg/mod/`

**Trade-off-lar:**
- Go daha verbose-dur: PHP-dəki 1 sətir kod Go-da 3-4 sətir tuta bilər
- Lakin bu verbosity intentional-dır — kod daha oxunaqlı olur
- Error handling explicit-dir: Go-da ignore etmək çətindir, PHP-də asandır

**Common mistakes:**
- `package main` yazmağı unutmaq — bütün Go fayllarında paket adı məcburidir
- `import "fmt"` yazmadan `fmt.Println` çağırmaq — Go istifadə olunmayan import-a icazə vermir
- `{}` açılan mötərizəni yeni sətirə qoymaq — Go-da compile error verir

## Nümunələr

### Nümunə 1: İlk Go proqramı

```go
package main

import "fmt"

func main() {
    fmt.Println("Salam, Dünya!")
    fmt.Println("Go dilini öyrənirik!")
}
```

### Nümunə 2: Layihə strukturu və modul yaratmaq

```go
// Terminalda:
// mkdir myapi
// cd myapi
// go mod init github.com/username/myapi
// → go.mod faylı yaranır

// main.go:
package main

import "fmt"

func main() {
    fmt.Println("API servisi başladı")
}

// İşlətmək:
// go run main.go
// Kompilyasiya:
// go build -o myapi main.go
// ./myapi
```

### Nümunə 3: fmt paketi ilə formatlı çıxış

```go
package main

import "fmt"

func main() {
    ad := "Orkhan"
    yas := 25

    fmt.Println("Sadə çıxış")
    fmt.Printf("Ad: %s, Yaş: %d\n", ad, yas)   // formatlaşdırılmış
    fmt.Printf("Tip: %T, Dəyər: %v\n", ad, ad) // %T → tipi göstər

    // Sprintf — çap etmədən string yaradan
    mesaj := fmt.Sprintf("İstifadəçi: %s", ad)
    fmt.Println(mesaj)
}
```

## Go Alətlər Ekosistemi

Go-nun daxili alətləri (external tool quraşdırmağa ehtiyac yoxdur):

```bash
go run main.go          # kompilyasiya etmədən işlət (dev)
go build -o myapp .     # binary yarat (production)
go test ./...           # bütün testləri işlət
go fmt ./...            # kodu avtomatik formatla (PHP-CS-Fixer kimi)
go vet ./...            # ümumi xətaları tap (static analysis)
go mod tidy             # istifadə olunmayan dependency-ləri sil
go mod download         # bütün dependency-ləri yüklə (composer install kimi)
go doc fmt.Println      # terminal içindən dokumentasiyaya bax
```

**IDE dəstəyi:** VS Code + `gopls` extension (Go Language Server) — autocomplete, error detection, refactoring.

## PHP → Go: Əsas Fərqlər Cədvəli

| Konsept | PHP | Go |
|---------|-----|-----|
| Paket manager | `composer.json` | `go.mod` |
| Dependency qovluğu | `vendor/` | `$GOPATH/pkg/mod/` |
| Giriş nöqtəsi | `index.php` | `func main()` |
| Çıxış | `echo`, `print` | `fmt.Println`, `fmt.Printf` |
| Xəta idarəsi | `try/catch` | `if err != nil` |
| Null | `null` | `nil` |
| Array | `array()`, `[]` | `[]int{}`, `map[string]int{}` |
| Tip yoxlama | `gettype()`, `instanceof` | type assertion, type switch |
| Deployment | PHP-FPM + web server | single binary |

## Praktik Tapşırıqlar

1. Go-nu quraşdır (`go version` ilə yoxla), `hello-world` adlı yeni modul yarat (`go mod init`), `main.go` faylında öz adını, şəhərini və peşəni çap et.

2. `go build` ilə binary yarat, ölçüsünə bax. Sonra `go build -ldflags="-s -w"` flag-ları ilə rebuild et — fərqi müqayisə et.

3. PHP-dəki bir `echo "..."` kod blokunu Go-ya çevir: `fmt.Println`, `fmt.Printf`, `fmt.Sprintf` arasındakı fərqi anlamaq üçün hər birini istifadə et.

4. `go fmt` və `go vet` əmrlərini öyrən — intentional bir format xətası yaz, `go fmt` ilə düzəlt, `go vet` ilə ümumi bir problem tap.

5. Go-nun rəsmi [tour.golang.org](https://tour.golang.org) saytında "Basics" bölümünü keç — xüsusilə "Packages" və "Imports" dərslərini.

## Əlaqəli Mövzular

- [02-variables.md](02-variables.md) — dəyişkənlər və sabitlər
- [03-data-types.md](03-data-types.md) — məlumat tipləri
- [14-packages-and-modules.md](14-packages-and-modules.md) — paketlər və modullar dərindən
- [07-functions.md](07-functions.md) — funksiyalar
