# CLI Tətbiqi (Middle)

## İcmal

Go CLI alətlər yaratmaq üçün ideal dildir: Docker, Kubernetes, Terraform, Hugo — hamısı Go ilə yazılmışdır. Standart `flag` paketi sadə toollar üçün kifayətdir; mürəkkəb subcommand-lı CLI-lər üçün isə `cobra` kitabxanası sənaye standartıdır.

## Niyə Vacibdir

Backend developer olaraq tez-tez köməkçi alətlər lazım olur: migration runner, data importer, cron job, deploy script. Go ilə bu alətləri statik binary kimi paylaşmaq olur — heç bir runtime dependency tələb etmir.

## Əsas Anlayışlar

- **os.Args** — proqrama verilən xam arqumentlər (birinci element proqram adıdır)
- **flag** — standart flag parsing paketi (`-name value` formatı)
- **FlagSet** — subcommand-lar üçün ayrı flag qrupu
- **flag.Parse()** — flagları parse etmək (unutmaq asan ümumi xəta)
- **cobra** — `git`, `kubectl` kimi mürəkkəb CLI-lər üçün populyar kitabxana
- **Exit code** — 0 uğurlu, 1+ xəta (CI/CD pipeline-larda vacibdir)

## Praktik Baxış

**Ne vaxt flag, ne vaxt cobra:**

| Ssenari | Seçim |
|---------|-------|
| 1-2 sadə flag | `flag` paketi |
| Subcommand lazımdır | `cobra` |
| Autocomplete lazımdır | `cobra` |
| Production CLI tool | `cobra` |

**Trade-off-lar:**
- `flag` paketi yalnız `-flag value` formatını dəstəkləyir, `--flag` yox
- `cobra` öyrənmə əyrisi var amma autocomplete, man page, help avtomatik gəlir
- Binary ölçüsü: `flag` ilə ~2MB, `cobra` ilə ~5-8MB (hər iki halda çox kiçikdir)

**Common mistakes:**
- `flag.Parse()` çağırmazmısınız — bütün flag-lar default dəyərdə qalır
- `os.Args[1]` yoxlamadan `os.Args[1]` oxumaq — index out of range
- Error-ları stderr əvəzinə stdout-a yazmaq — pipe ilə istifadəni çətinləşdirir

## Nümunələr

### Nümunə 1: os.Args — xam arqumentlər

```go
package main

import (
    "fmt"
    "os"
)

func main() {
    fmt.Println("Proqram adı:", os.Args[0])
    fmt.Println("Arqumentlər:", os.Args[1:])

    if len(os.Args) < 2 {
        fmt.Fprintln(os.Stderr, "Ən azı bir arqument lazımdır")
        os.Exit(1)
    }

    // go run main.go salam dünya
    // os.Args = ["main", "salam", "dünya"]
    for i, arg := range os.Args[1:] {
        fmt.Printf("Arqument %d: %s\n", i+1, arg)
    }
}
```

### Nümunə 2: flag paketi — sadə CLI

```go
package main

import (
    "flag"
    "fmt"
    "os"
    "strings"
)

func main() {
    // Flag tərifləri (ad, default, izahat)
    ad    := flag.String("ad", "Dünya", "Salamlama üçün ad")
    sayı  := flag.Int("sayı", 1, "Neçə dəfə salam deməli")
    böyük := flag.Bool("böyük", false, "Böyük hərflərlə yazsın")
    port  := flag.Int("port", 8080, "Server portu")

    // Xüsusi help mesajı
    flag.Usage = func() {
        fmt.Fprintf(os.Stderr, "İstifadə: %s [flaglar]\n\n", os.Args[0])
        fmt.Fprintln(os.Stderr, "Flaglar:")
        flag.PrintDefaults()
        fmt.Fprintln(os.Stderr, "\nNümunələr:")
        fmt.Fprintln(os.Stderr, "  myapp -ad Orkhan -sayı 3")
        fmt.Fprintln(os.Stderr, "  myapp -böyük -ad Go")
    }

    flag.Parse() // Bunu unutmayın!

    // Flag olmayan qalan arqumentlər
    qalanArgs := flag.Args()
    if len(qalanArgs) > 0 {
        fmt.Println("Əlavə arqumentlər:", qalanArgs)
    }

    // Flagları istifadə et
    for i := 0; i < *sayı; i++ {
        mesaj := fmt.Sprintf("Salam, %s!", *ad)
        if *böyük {
            mesaj = strings.ToUpper(mesaj)
        }
        fmt.Println(mesaj)
    }
    fmt.Println("Port:", *port)

    // İşlətmə nümunələri:
    // go run main.go -ad Orkhan -sayı 3
    // go run main.go -böyük -ad Go
    // go run main.go -help
}
```

### Nümunə 3: Subcommand-lar (git kimi)

```go
package main

import (
    "flag"
    "fmt"
    "os"
)

func main() {
    if len(os.Args) < 2 {
        fmt.Fprintln(os.Stderr, "Alt əmr lazımdır: add, list, delete")
        fmt.Fprintln(os.Stderr, "İstifadə: mytool <əmr> [flaglar]")
        os.Exit(1)
    }

    // Hər subcommand üçün ayrı FlagSet
    addCmd    := flag.NewFlagSet("add", flag.ExitOnError)
    addName   := addCmd.String("ad", "", "Əlavə ediləcək ad")
    addEmail  := addCmd.String("email", "", "İstifadəçi email")

    listCmd   := flag.NewFlagSet("list", flag.ExitOnError)
    listLimit := listCmd.Int("limit", 10, "Neçə element göstər")
    listJSON  := listCmd.Bool("json", false, "JSON formatında göstər")

    deleteCmd := flag.NewFlagSet("delete", flag.ExitOnError)
    deleteID  := deleteCmd.Int("id", 0, "Silinəcək ID")
    deleteForce := deleteCmd.Bool("force", false, "Təsdiq olmadan sil")

    switch os.Args[1] {
    case "add":
        addCmd.Parse(os.Args[2:])
        if *addName == "" {
            fmt.Fprintln(os.Stderr, "XƏTA: -ad flag-ı mütləqdir")
            addCmd.Usage()
            os.Exit(1)
        }
        fmt.Printf("ƏLAVƏ EDİLDİ: %s <%s>\n", *addName, *addEmail)

    case "list":
        listCmd.Parse(os.Args[2:])
        if *listJSON {
            fmt.Printf(`{"limit":%d,"items":[]}`+"\n", *listLimit)
        } else {
            fmt.Printf("SİYAHI (limit: %d)\n", *listLimit)
        }

    case "delete":
        deleteCmd.Parse(os.Args[2:])
        if *deleteID == 0 {
            fmt.Fprintln(os.Stderr, "XƏTA: -id flag-ı mütləqdir")
            os.Exit(1)
        }
        if !*deleteForce {
            fmt.Printf("ID=%d silinəcək. Əminsiniz? (y/N): ", *deleteID)
            var cavab string
            fmt.Scan(&cavab)
            if cavab != "y" && cavab != "Y" {
                fmt.Println("Əməliyyat ləğv edildi")
                os.Exit(0)
            }
        }
        fmt.Printf("SİLİNDİ: ID=%d\n", *deleteID)

    case "help", "-help", "--help":
        printHelp()

    default:
        fmt.Fprintf(os.Stderr, "Bilinməyən əmr: %s\n", os.Args[1])
        printHelp()
        os.Exit(1)
    }
}

func printHelp() {
    fmt.Println("İstifadə: mytool <əmr> [flaglar]")
    fmt.Println()
    fmt.Println("Əmrlər:")
    fmt.Println("  add    -ad <ad> [-email <email>]    İstifadəçi əlavə et")
    fmt.Println("  list   [-limit <n>] [-json]          Siyahıya bax")
    fmt.Println("  delete -id <id> [-force]             Sil")
}
```

### Nümunə 4: Rəng və formatlama (ANSI kodları)

```go
package main

import (
    "fmt"
    "os"
    "runtime"
)

// ANSI rəng kodları
const (
    Qırmızı  = "\033[31m"
    Yaşıl    = "\033[32m"
    Sarı     = "\033[33m"
    Mavi     = "\033[34m"
    Boz      = "\033[90m"
    Qalın    = "\033[1m"
    Sıfırla  = "\033[0m"
)

// isTerminal — terminal dəstəkləyirsə rəng istifadə et
func isTerminal() bool {
    return runtime.GOOS != "windows" // sadə yoxlama
}

func printSuccess(msg string) {
    if isTerminal() {
        fmt.Println(Yaşıl + "✓ " + msg + Sıfırla)
    } else {
        fmt.Println("[OK] " + msg)
    }
}

func printError(msg string) {
    if isTerminal() {
        fmt.Fprintln(os.Stderr, Qırmızı+"✗ "+msg+Sıfırla)
    } else {
        fmt.Fprintln(os.Stderr, "[ERROR] "+msg)
    }
}

func printWarning(msg string) {
    if isTerminal() {
        fmt.Println(Sarı + "⚠ " + msg + Sıfırla)
    } else {
        fmt.Println("[WARN] " + msg)
    }
}

func printInfo(msg string) {
    if isTerminal() {
        fmt.Println(Mavi + "ℹ " + msg + Sıfırla)
    } else {
        fmt.Println("[INFO] " + msg)
    }
}

func main() {
    printSuccess("Migration tamamlandı")
    printError("Verilənlər bazasına qoşula bilmədi")
    printWarning("Köhnə versiya istifadə olunur")
    printInfo("3 istifadəçi tapıldı")

    // Exit kodları (CI/CD üçün vacibdir)
    // os.Exit(0) — uğurlu
    // os.Exit(1) — ümumi xəta
    // os.Exit(2) — istifadə xətası (yanlış flag)
}
```

### Nümunə 5: cobra ilə peşəkar CLI

```go
// go get github.com/spf13/cobra
// Docker, Kubernetes, Hugo — hamısı cobra istifadə edir

package main

import (
    "fmt"
    "os"

    "github.com/spf13/cobra"
)

func main() {
    rootCmd := &cobra.Command{
        Use:   "mytool",
        Short: "Layihə idarəetmə aləti",
        Long:  `mytool — backend layihələri üçün köməkçi CLI.`,
    }

    // "add" subcommand
    var addEmail string
    addCmd := &cobra.Command{
        Use:   "add [ad]",
        Short: "İstifadəçi əlavə et",
        Args:  cobra.ExactArgs(1),
        RunE: func(cmd *cobra.Command, args []string) error {
            name := args[0]
            fmt.Printf("Əlavə edildi: %s <%s>\n", name, addEmail)
            return nil
        },
    }
    addCmd.Flags().StringVarP(&addEmail, "email", "e", "", "İstifadəçi email (mütləq)")
    addCmd.MarkFlagRequired("email")

    // "list" subcommand
    var listLimit int
    listCmd := &cobra.Command{
        Use:   "list",
        Short: "İstifadəçiləri göstər",
        Run: func(cmd *cobra.Command, args []string) {
            fmt.Printf("Siyahı (limit: %d)\n", listLimit)
        },
    }
    listCmd.Flags().IntVarP(&listLimit, "limit", "l", 10, "Nəticə limiti")

    rootCmd.AddCommand(addCmd, listCmd)

    if err := rootCmd.Execute(); err != nil {
        os.Exit(1)
    }
    // cobra avtomatik help, version, autocomplete əlavə edir
    // mytool --help
    // mytool add --help
    // mytool add Orkhan --email orkhan@example.com
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1: File converter CLI**

CSV faylını JSON-a çevirən CLI yazın. Flaglar: `-input input.csv`, `-output output.json`, `-pretty` (formatted JSON). Xəta halında stderr-ə yaz və exit code 1 qaytar.

```go
// İstifadə:
// ./converter -input data.csv -output data.json -pretty
// ./converter -input data.csv | jq .  # stdout-a yazır
```

**Tapşırıq 2: HTTP health checker**

Bir neçə URL-i yoxlayan CLI: `-urls url1,url2,url3`, `-timeout 5s`, `-parallel` (eyni anda yoxla). Nəticəni rəngli cədvəl kimi göstər.

```go
// İstifadə:
// ./healthcheck -urls https://api1.com,https://api2.com -timeout 3s -parallel
// Output:
// ✓ https://api1.com  200  45ms
// ✗ https://api2.com  timeout  3000ms
```

**Tapşırıq 3: Migration runner**

SQL migration fayllarını oxuyub run edən CLI yazın. Subcommand-lar: `migrate up`, `migrate down`, `migrate status`. Database URL `-dsn` flag-ı ilə alınsın.

```go
// İstifadə:
// ./migrator -dsn "postgres://..." migrate up
// ./migrator -dsn "postgres://..." migrate down --steps 2
// ./migrator migrate status
```

## PHP ilə Müqayisə

```
PHP Artisan              →  Go CLI
php artisan make:model   →  ./mytool generate model
$this->argument('name')  →  flag.String("name", "", "...")
$this->option('force')   →  flag.Bool("force", false, "...")
$this->info("OK")        →  fmt.Println("\033[32mOK\033[0m")
$this->error("Fail")     →  fmt.Fprintln(os.Stderr, "Fail")
```

Go CLI binary-ləri PHP Artisan-dan fərqli olaraq heç bir runtime tələb etmədən işləyir — statik binary kimi paylaşılır.

## Əlaqəli Mövzular

- `14-packages-and-modules` — CLI binary-ni modul kimi qurmaq
- `13-file-operations` — faylları oxumaq/yazmaq
- `28-context` — timeout ilə CLI əməliyyatları
- `25-logging` — CLI-da log yazmaq
- `../backend/12-processes-and-signals` — SIGINT ilə graceful shutdown
- `../backend/07-environment-and-config` — environment variable oxumaq
