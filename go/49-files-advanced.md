# Files — İrəliləmiş (Senior)

## İcmal

Go-da fayl əməliyyatlarının irəliləmiş tərəfləri: `path/filepath` ilə OS-a uyğun yol idarəsi, `WalkDir` ilə rekursiv qovluq gəzimi, müvəqqəti fayl/qovluq yaratma, `Glob` ilə nümunəyə uyğun axtarış, `Line Filter` pattern. Bu mövzu CLI tool-lar, deployment script-lər, fayl processor-lar yazarkən vacibdir.

## Niyə Vacibdir

- `filepath` paketi Windows/Linux/macOS arasında portable kod yazmağa imkan verir
- `WalkDir` — log analiz, fayl indexleme, bulk processing üçün
- Müvəqqəti fayllar test fixture-ları, upload processing, atomic write üçün
- Glob pattern — konfiqurasiya faylı axtarışı, template yükləmə
- `os.Stat` — fayl mövcudluğu, ölçüsü, icazəsi yoxlamaq

## Əsas Anlayışlar

### filepath Paketi

OS-a uyğun fayl yolu ayırıcısını avtomatik istifadə edir:

```go
// Linux/Mac: /home/user/projects/main.go
// Windows: C:\Users\user\projects\main.go
path := filepath.Join("home", "user", "projects", "main.go")

filepath.Dir("home/user/main.go")   // "home/user"
filepath.Base("home/user/main.go")  // "main.go"
filepath.Ext("photo.jpg")           // ".jpg"
filepath.Abs("./main.go")           // tam yol
filepath.Rel("/a", "/a/b/c")        // "b/c"
filepath.Clean("/a/../b/./c")       // "/b/c"
```

### WalkDir vs Walk

Go 1.16-dan `WalkDir` daha effektivdir — `DirEntry` obyekti qaytarır, `os.Stat` çağırmır:

```go
filepath.WalkDir(".", func(path string, d os.DirEntry, err error) error {
    if err != nil {
        return err
    }
    if d.IsDir() && d.Name() == "vendor" {
        return filepath.SkipDir  // bu qovluğu keç
    }
    if !d.IsDir() {
        // fayl ilə iş gör
    }
    return nil
})
```

`filepath.SkipAll` — bütün gəzimi dayandırır (Go 1.20+).

### Müvəqqəti Fayllar

```go
// Müvəqqəti fayl — "prefix-*.suffix" formatında ad alır
f, _ := os.CreateTemp("", "myapp-*.json") // "" = sistem temp qovluğu
defer os.Remove(f.Name())

// Müvəqqəti qovluq
dir, _ := os.MkdirTemp("", "myapp-*")
defer os.RemoveAll(dir)
```

**Niyə vacib:** Upload emal, atomic write (tmp-ya yaz, sonra rename), test fixture.

### Atomic File Write

```go
// Naively: os.WriteFile → fail olsa partial write
// Düzgün: tmp faylla atomic
func atomicWrite(path string, data []byte) error {
    tmp := path + ".tmp"
    if err := os.WriteFile(tmp, data, 0644); err != nil {
        return err
    }
    return os.Rename(tmp, path) // atomic — eyni disk partition-da
}
```

### os.Stat — Fayl Məlumatları

```go
info, err := os.Stat("myfile.txt")
if os.IsNotExist(err) {
    // fayl yoxdur
}

info.Name()    // "myfile.txt"
info.Size()    // byte ölçüsü
info.IsDir()   // qovluqdurmu?
info.Mode()    // icazə bits
info.ModTime() // son dəyişiklik tarixi
```

### Glob Pattern

```go
files, _ := filepath.Glob("*.go")      // cari qovluqda
files, _ := filepath.Glob("**/*.go")   // DİQQƏT: ** işləmir! filepath.WalkDir istifadə edin
```

**Qeyd:** `filepath.Glob` recursive deyil. `**` pattern-i dəstəkləmir. Rekursiv axtarış üçün `WalkDir` + `.Ext()` yoxlaması lazımdır.

### Line Filter Pattern

Unix pipeline-ı ilə işləyən alət:
```
cat access.log | ./filter | sort | uniq -c
```

Go proqramı stdin-dən oxuyub, emal edib, stdout-a yazır.

## Praktik Baxış

### Real Layihələrdə İstifadə

| Task | Paket/Funksiya |
|------|----------------|
| Fayl kopyalama | `io.Copy` + `os.Create` |
| Rekursiv silmə | `os.RemoveAll` |
| Atomic write | tmp + `os.Rename` |
| Qovluq mövcudluğu | `os.Stat` + `os.IsNotExist` |
| Bütün .log faylları | `WalkDir` + `filepath.Ext` |
| Config faylı axtarışı | `filepath.Glob` |
| Upload emal | `os.CreateTemp` + rename |

### Trade-off-lar

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| `os.ReadFile` | Sadə, tam oxuma | Böyük fayllar üçün memory problem |
| `bufio.Scanner` | Sətir-sətir, az memory | Stream emal |
| `io.Copy` | Effektiv, streaming | Bir neçə yazma operasiyası |
| `mmap` | Çox böyük fayl üçün sürətli | OS spesifik, mürəkkəb |

### Anti-pattern-lər

```go
// Anti-pattern 1: Böyük faylı bütöv oxumaq
data, _ := os.ReadFile("10gb-log.txt") // memory dolur!

// Düzgün: streaming
f, _ := os.Open("10gb-log.txt")
scanner := bufio.NewScanner(f)
for scanner.Scan() { ... }

// Anti-pattern 2: Windows-da path separator
path := "home/user/file" // / Linux-da OK, Windows-da yanlış
// Düzgün:
path := filepath.Join("home", "user", "file")

// Anti-pattern 3: Müvəqqəti faylı silməmək
f, _ := os.CreateTemp("", "upload-*")
// defer os.Remove(f.Name()) — unuldub! Resource leak

// Anti-pattern 4: filepath.Glob ilə recursive axtarış cəhdi
files, _ := filepath.Glob("**/*.go") // ** işləmir — boş nəticə qaytarır!

// Anti-pattern 5: WalkDir-i error olmadan
filepath.WalkDir(".", func(p string, d os.DirEntry, err error) error {
    // err yoxlanmır! Permission denied, symlink loop — bunlar gizli qalır
    // Düzgün: if err != nil { return err }
})
```

## Nümunələr

### Nümunə 1: Rekursiv Fayl Axtarışı

```go
package main

import (
    "fmt"
    "os"
    "path/filepath"
    "strings"
)

type FileFilter struct {
    Extension   string
    MinSize     int64
    MaxSize     int64
    ExcludeDirs []string
}

func FindFiles(root string, filter FileFilter) ([]string, error) {
    var results []string

    excludeSet := make(map[string]bool)
    for _, d := range filter.ExcludeDirs {
        excludeSet[d] = true
    }

    err := filepath.WalkDir(root, func(path string, d os.DirEntry, err error) error {
        if err != nil {
            return nil // permission denied kimi xətaları keç
        }

        if d.IsDir() {
            if excludeSet[d.Name()] {
                return filepath.SkipDir
            }
            return nil
        }

        // Extension yoxla
        if filter.Extension != "" && filepath.Ext(path) != filter.Extension {
            return nil
        }

        // Ölçü yoxla
        if filter.MinSize > 0 || filter.MaxSize > 0 {
            info, err := d.Info()
            if err != nil {
                return nil
            }
            size := info.Size()
            if filter.MinSize > 0 && size < filter.MinSize {
                return nil
            }
            if filter.MaxSize > 0 && size > filter.MaxSize {
                return nil
            }
        }

        results = append(results, path)
        return nil
    })

    return results, err
}

func main() {
    files, err := FindFiles(".", FileFilter{
        Extension:   ".go",
        ExcludeDirs: []string{".git", "vendor", "node_modules"},
    })
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }

    fmt.Printf("%d fayl tapıldı:\n", len(files))
    for _, f := range files {
        // Yalnız fayl adını göstər
        name := filepath.Base(f)
        if !strings.HasPrefix(name, "_") {
            fmt.Printf("  %s\n", f)
        }
    }
}
```

### Nümunə 2: Atomic File Write

```go
package main

import (
    "encoding/json"
    "fmt"
    "os"
    "path/filepath"
)

type Config struct {
    Version string            `json:"version"`
    Settings map[string]string `json:"settings"`
}

// atomicWriteJSON — partial write-dan qoruyur
func atomicWriteJSON(path string, v interface{}) error {
    data, err := json.MarshalIndent(v, "", "  ")
    if err != nil {
        return fmt.Errorf("marshal: %w", err)
    }

    // Eyni qovluqda tmp fayl yarat
    dir := filepath.Dir(path)
    tmp, err := os.CreateTemp(dir, ".tmp-*")
    if err != nil {
        return fmt.Errorf("tmp fayl: %w", err)
    }
    tmpName := tmp.Name()

    // Hər halda tmp faylı sil (rename uğurlu olsa da olmasa da)
    defer os.Remove(tmpName)

    // Yaz
    if _, err := tmp.Write(data); err != nil {
        tmp.Close()
        return fmt.Errorf("yazma: %w", err)
    }

    // Flush et
    if err := tmp.Sync(); err != nil {
        tmp.Close()
        return fmt.Errorf("sync: %w", err)
    }
    tmp.Close()

    // Atomic rename — bu ya tam keçir, ya da keçmir
    if err := os.Rename(tmpName, path); err != nil {
        return fmt.Errorf("rename: %w", err)
    }

    return nil
}

func main() {
    config := Config{
        Version: "1.2.0",
        Settings: map[string]string{
            "host": "localhost",
            "port": "8080",
            "env":  "production",
        },
    }

    if err := atomicWriteJSON("config.json", config); err != nil {
        fmt.Println("Xəta:", err)
        return
    }

    fmt.Println("Config atomik yazıldı: config.json")

    // Yoxlama: oxu
    data, _ := os.ReadFile("config.json")
    fmt.Println(string(data))

    os.Remove("config.json") // cleanup
}
```

### Nümunə 3: Directory Structure Analizi

```go
package main

import (
    "fmt"
    "os"
    "path/filepath"
    "sort"
)

type DirStats struct {
    TotalFiles int
    TotalDirs  int
    TotalSize  int64
    ByExt      map[string]int
    LargestFile struct {
        Path string
        Size int64
    }
}

func AnalyzeDir(root string) (*DirStats, error) {
    stats := &DirStats{
        ByExt: make(map[string]int),
    }

    err := filepath.WalkDir(root, func(path string, d os.DirEntry, err error) error {
        if err != nil {
            return nil
        }

        if d.IsDir() {
            if d.Name() == ".git" || d.Name() == "vendor" {
                return filepath.SkipDir
            }
            stats.TotalDirs++
            return nil
        }

        stats.TotalFiles++

        ext := filepath.Ext(path)
        if ext == "" {
            ext = "(no ext)"
        }
        stats.ByExt[ext]++

        info, err := d.Info()
        if err != nil {
            return nil
        }

        stats.TotalSize += info.Size()

        if info.Size() > stats.LargestFile.Size {
            stats.LargestFile.Size = info.Size()
            stats.LargestFile.Path = path
        }

        return nil
    })

    return stats, err
}

func main() {
    stats, err := AnalyzeDir(".")
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }

    fmt.Printf("Qovluq analizi:\n")
    fmt.Printf("  Fayllar: %d\n", stats.TotalFiles)
    fmt.Printf("  Qovluqlar: %d\n", stats.TotalDirs)
    fmt.Printf("  Ümumi ölçü: %.1f KB\n", float64(stats.TotalSize)/1024)

    if stats.LargestFile.Path != "" {
        fmt.Printf("  Ən böyük: %s (%.1f KB)\n",
            filepath.Base(stats.LargestFile.Path),
            float64(stats.LargestFile.Size)/1024)
    }

    // Extensionlara görə sırala
    type extCount struct {
        ext   string
        count int
    }
    var exts []extCount
    for ext, count := range stats.ByExt {
        exts = append(exts, extCount{ext, count})
    }
    sort.Slice(exts, func(i, j int) bool {
        return exts[i].count > exts[j].count
    })

    fmt.Println("\n  Extension bölgüsü:")
    for _, e := range exts {
        fmt.Printf("    %-15s %d fayl\n", e.ext, e.count)
    }
}
```

### Nümunə 4: Line Filter — Log Analyzer

```go
package main

import (
    "bufio"
    "fmt"
    "os"
    "strings"
)

// Unix pipe ilə işləyən log filteri
// Istifadə: cat access.log | go run filter.go ERROR
func main() {
    if len(os.Args) < 2 {
        fmt.Fprintln(os.Stderr, "İstifadə: program <filter-sözü>")
        os.Exit(1)
    }

    filterWord := strings.ToUpper(os.Args[1])
    lineNum := 0
    matchCount := 0

    scanner := bufio.NewScanner(os.Stdin)
    // Böyük sətir üçün buffer artır
    buf := make([]byte, 0, 64*1024)
    scanner.Buffer(buf, 1024*1024)

    for scanner.Scan() {
        lineNum++
        line := scanner.Text()

        if strings.Contains(strings.ToUpper(line), filterWord) {
            matchCount++
            fmt.Printf("[%d] %s\n", lineNum, line)
        }
    }

    if err := scanner.Err(); err != nil {
        fmt.Fprintln(os.Stderr, "Oxuma xətası:", err)
        os.Exit(1)
    }

    fmt.Fprintf(os.Stderr, "\n%d sətirdə %d uyğunluq tapıldı\n",
        lineNum, matchCount)
}
```

### Nümunə 5: Müvəqqəti Qovluqda Test

```go
package main

import (
    "fmt"
    "os"
    "path/filepath"
)

// Test fixture üçün müvəqqəti qovluq
func setupTestDir() (string, func(), error) {
    dir, err := os.MkdirTemp("", "test-*")
    if err != nil {
        return "", nil, err
    }

    cleanup := func() { os.RemoveAll(dir) }

    // Test faylları yarat
    files := map[string]string{
        "config.json": `{"env":"test"}`,
        "data.csv":    "id,name\n1,Test",
        "nested/sub/file.txt": "nested content",
    }

    for relPath, content := range files {
        fullPath := filepath.Join(dir, relPath)
        os.MkdirAll(filepath.Dir(fullPath), 0755)
        os.WriteFile(fullPath, []byte(content), 0644)
    }

    return dir, cleanup, nil
}

func main() {
    testDir, cleanup, err := setupTestDir()
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    defer cleanup()

    fmt.Println("Test qovluğu:", testDir)

    // Yoxla
    filepath.WalkDir(testDir, func(path string, d os.DirEntry, err error) error {
        if !d.IsDir() {
            rel, _ := filepath.Rel(testDir, path)
            info, _ := d.Info()
            fmt.Printf("  %s (%d byte)\n", rel, info.Size())
        }
        return nil
    })

    fmt.Println("Test bitdi, qovluq silinir...")
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Duplicate Finder:**
Hash (md5/sha256) ilə eyni məzmunlu faylları tapan proqram yazın. Böyük qovluqlarda duplicate-ları aşkar edin.

**Tapşırıq 2 — File Watcher:**
`time.Ticker` + `os.Stat` + `ModTime` ilə sadə fayl dəyişiklik monitor yazın. Real layihədə `fsnotify` paketi istifadə edilir.

**Tapşırıq 3 — Backup Tool:**
Qovluğu timestamp ilə archive et: `backup-2024-01-15-143022/`. Yalnız son N backup-ı saxla, köhnələri sil.

**Tapşırıq 4 — Config Merge:**
Bir neçə JSON konfiqurasiya faylını birləşdirən tool: `base.json` + `local.json` → merged config. Local-dakılar base-i override etsin.

## Əlaqəli Mövzular

- [13-file-operations](13-file-operations.md) — Fayl əsasları
- [30-io-reader-writer](30-io-reader-writer.md) — io.Reader/Writer
- [31-go-embed](31-go-embed.md) — Faylları binary-ə daxil etmək
- [48-processes-and-signals](48-processes-and-signals.md) — OS integration
- [70-docker-and-deploy](70-docker-and-deploy.md) — Container-da fayl sistemi
