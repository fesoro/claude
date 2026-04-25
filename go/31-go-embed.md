# go:embed Direktivi (Middle)

## İcmal

`//go:embed` direktivi Go 1.16 ilə gəlmişdir. Faylları (HTML, template, konfiqurasiya, statik asset) birbaşa Go binary-sinə daxil etməyə imkan verir. Bu sayədə tətbiq yayımlanarkən əlavə fayl qovluqları lazım olmur — hər şey bir `.exe` / binary-dədir.

## Niyə Vacibdir

Deployment sadələşir: artıq `public/`, `templates/`, `config/` qovluqlarını serverə köçürmək lazım deyil. Konteyner image-ləri kiçilir. Versiya uyğunsuzluğu (binary v1.2, amma template-lər v1.1) problemi yox olur. Go-da statik fayl serveri qurmaq, template render etmək, default konfiqurasiya fayllarını daxil etmək üçün artıq `rice`, `statik`, `go-bindata` kimi üçüncü tərəf kitabxanalar lazım deyil.

## Əsas Anlayışlar

- **`//go:embed <pattern>`** — direktiv; `import _ "embed"` tələb edir
- **`string`** — tək fayl mətnini string kimi embed edir
- **`[]byte`** — tək fayl məzmununu byte slice kimi embed edir
- **`embed.FS`** — birdən çox fayl/qovluq üçün virtual fayl sistemi
- **`embed.FS` metodları** — `Open`, `ReadFile`, `ReadDir` (standart `fs.FS` interfeysini implement edir)
- **glob pattern** — `static/*`, `templates/**/*.html` formatında fayl seçimi
- **`_` (underscore)** — gizli faylları daxil etmək üçün `all:` prefiksi lazımdır

## Praktik Baxış

**PHP ilə müqayisə:**

```
PHP                                →  Go
file_get_contents("config.json")   →  //go:embed config.json; var cfg []byte
__DIR__."/templates/..."            →  embed.FS
Packaging: composer + assets       →  Bir binary, hər şey içəridə
```

**Ne vaxt embed istifadə etmək:**

| Ssenari | Seçim |
|---------|-------|
| HTML template-lər | `embed.FS` |
| Default config faylı | `[]byte` və ya `string` |
| Static fayllar (CSS, JS) | `embed.FS` → `http.FileServer` |
| SQL migration faylları | `embed.FS` |
| Sertifikat faylları | `[]byte` |
| Fayl tez-tez dəyişir (dev) | Embed etmə — disk-dən oxu |

**Trade-off-lar:**
- Binary ölçüsü artır — böyük media faylları üçün uyğun deyil
- Fayl dəyişdikdə yenidən compile lazımdır
- Development-də dəyişiklikləri görə bilmək üçün flag-la disk/embed seçim mexanizmi faydalıdır

**Common mistakes:**
- Direktivi kommentdən fərqli sətirdə yazmaq — `//go:embed` direktiv comment-dir, boşluq olmadan yazın
- `import _ "embed"` unutmaq — `string` və `[]byte` üçün mütləqdir
- Glob pattern-ların gizli faylları (`.env`, `.gitignore`) daxil etməməsindən xəbərsiz olmaq

## Nümunələr

### Nümunə 1: Tək fayl — string və []byte

```go
package main

import (
    _ "embed" // string/[]byte embed üçün mütləqdir
    "fmt"
)

//go:embed config.json
var configJSON string // tək fayl → string

//go:embed version.txt
var version []byte // tək fayl → []byte

//go:embed README.md
var readme string

func main() {
    fmt.Println("Config:", configJSON)
    fmt.Println("Version:", string(version))
    fmt.Println("README uzunluğu:", len(readme))
}
```

```json
// config.json (layihə qovluğunda)
{
  "database": "postgres://localhost/myapp",
  "port": 8080
}
```

```
// version.txt
1.2.3
```

### Nümunə 2: embed.FS — çox fayl

```go
package main

import (
    "embed"
    "fmt"
    "io/fs"
)

//go:embed templates
var templateFS embed.FS

//go:embed static
var staticFS embed.FS

//go:embed migrations/*.sql
var migrationsFS embed.FS

func main() {
    // Fayl oxumaq
    data, err := templateFS.ReadFile("templates/index.html")
    if err != nil {
        panic(err)
    }
    fmt.Println("Template:", string(data))

    // Qovluqdakı faylları siyahıla
    entries, _ := templateFS.ReadDir("templates")
    for _, entry := range entries {
        fmt.Printf("Fayl: %s (qovluq: %v)\n", entry.Name(), entry.IsDir())
    }

    // Bütün faylları gəz (recursive)
    fs.WalkDir(migrationsFS, ".", func(path string, d fs.DirEntry, err error) error {
        if !d.IsDir() {
            fmt.Println("Migration:", path)
        }
        return nil
    })
}
```

```
layihə strukturu:
├── main.go
├── templates/
│   ├── index.html
│   ├── layout.html
│   └── partials/
│       └── nav.html
├── static/
│   ├── css/main.css
│   └── js/app.js
└── migrations/
    ├── 001_create_users.sql
    └── 002_create_orders.sql
```

### Nümunə 3: HTML template-ləri embed etmək

```go
package main

import (
    "embed"
    "html/template"
    "net/http"
    "time"
)

//go:embed templates/*.html
var templatesFS embed.FS

// Template-ləri yüklə
var tmpl = template.Must(template.ParseFS(templatesFS, "templates/*.html"))

type PageData struct {
    Title   string
    Message string
    Time    time.Time
}

func indexHandler(w http.ResponseWriter, r *http.Request) {
    data := PageData{
        Title:   "Salam Dünya",
        Message: "embed.FS ilə template",
        Time:    time.Now(),
    }
    tmpl.ExecuteTemplate(w, "index.html", data)
}

func main() {
    http.HandleFunc("/", indexHandler)
    http.ListenAndServe(":8080", nil)
}
```

```html
<!-- templates/index.html -->
<!DOCTYPE html>
<html>
<head><title>{{.Title}}</title></head>
<body>
    <h1>{{.Message}}</h1>
    <p>Vaxt: {{.Time.Format "2006-01-02 15:04:05"}}</p>
</body>
</html>
```

### Nümunə 4: Static fayl serveri

```go
package main

import (
    "embed"
    "io/fs"
    "net/http"
)

//go:embed static
var staticFiles embed.FS

func main() {
    // embed.FS-dən http.FileServer üçün sub-FS yarat
    // "static" qovluğunu strip et — URL /css/main.css olsun, /static/css/main.css yox
    subFS, err := fs.Sub(staticFiles, "static")
    if err != nil {
        panic(err)
    }

    mux := http.NewServeMux()

    // Static fayllar: /css/main.css, /js/app.js
    mux.Handle("/static/", http.StripPrefix("/static/", http.FileServer(http.FS(subFS))))

    // API route
    mux.HandleFunc("/api/health", func(w http.ResponseWriter, r *http.Request) {
        w.Write([]byte(`{"status":"ok"}`))
    })

    http.ListenAndServe(":8080", mux)
}
```

### Nümunə 5: SQL migration runner

```go
package main

import (
    "database/sql"
    "embed"
    "fmt"
    "io/fs"
    "sort"
    "strings"

    _ "github.com/lib/pq"
)

//go:embed migrations/*.sql
var migrationsFS embed.FS

func runMigrations(db *sql.DB) error {
    // Migration fayllarını siyahıla
    entries, err := migrationsFS.ReadDir("migrations")
    if err != nil {
        return fmt.Errorf("migration qovluğu oxunmadı: %w", err)
    }

    // Faylları sırala
    sort.Slice(entries, func(i, j int) bool {
        return entries[i].Name() < entries[j].Name()
    })

    for _, entry := range entries {
        if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".sql") {
            continue
        }

        path := "migrations/" + entry.Name()
        sql, err := fs.ReadFile(migrationsFS, path)
        if err != nil {
            return fmt.Errorf("%s oxunmadı: %w", path, err)
        }

        fmt.Printf("Migration icra edilir: %s\n", entry.Name())
        if _, err := db.Exec(string(sql)); err != nil {
            return fmt.Errorf("%s icra edilmədı: %w", entry.Name(), err)
        }
    }
    return nil
}

func main() {
    db, err := sql.Open("postgres", "postgres://user:pass@localhost/mydb?sslmode=disable")
    if err != nil {
        panic(err)
    }
    defer db.Close()

    if err := runMigrations(db); err != nil {
        panic(err)
    }
    fmt.Println("Bütün migrationlar tamamlandı")
}
```

### Nümunə 6: Development vs Production rejimi

```go
package main

import (
    "embed"
    "io/fs"
    "net/http"
    "os"
)

//go:embed static
var embeddedStatic embed.FS

func getStaticFS() http.FileSystem {
    // Development-də disk-dən oxu (dəyişikliklər dərhal görünür)
    if os.Getenv("ENV") == "development" {
        return http.Dir("./static")
    }

    // Production-da binary-dən oxu
    subFS, _ := fs.Sub(embeddedStatic, "static")
    return http.FS(subFS)
}

func main() {
    staticFS := getStaticFS()
    http.Handle("/static/", http.StripPrefix("/static/", http.FileServer(staticFS)))
    http.ListenAndServe(":8080", nil)
}
```

### Nümunə 7: Default konfiqurasiya faylı

```go
package main

import (
    _ "embed"
    "encoding/json"
    "fmt"
    "os"
)

//go:embed config.default.json
var defaultConfig []byte

type Config struct {
    Host     string `json:"host"`
    Port     int    `json:"port"`
    LogLevel string `json:"log_level"`
    Database struct {
        MaxConns int `json:"max_connections"`
    } `json:"database"`
}

func loadConfig() (*Config, error) {
    cfg := &Config{}

    // 1. Default konfiqurasiyadan başla
    if err := json.Unmarshal(defaultConfig, cfg); err != nil {
        return nil, fmt.Errorf("default config: %w", err)
    }

    // 2. Əgər varsa, disk-dəki config ilə üzün yaz
    if data, err := os.ReadFile("config.json"); err == nil {
        if err := json.Unmarshal(data, cfg); err != nil {
            return nil, fmt.Errorf("config.json: %w", err)
        }
    }

    return cfg, nil
}

func main() {
    cfg, err := loadConfig()
    if err != nil {
        panic(err)
    }
    fmt.Printf("Host: %s:%d, LogLevel: %s\n", cfg.Host, cfg.Port, cfg.LogLevel)
}
```

```json
// config.default.json
{
  "host": "localhost",
  "port": 8080,
  "log_level": "info",
  "database": {
    "max_connections": 10
  }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1: Mini static file server**

Bir qovluqdakı bütün HTML, CSS, JS fayllarını embed edib HTTP server qurun. `/` endpoint-i `index.html`-i qaytarsın, `/static/` isə CSS/JS fayllarını.

**Tapşırıq 2: Email template engine**

`templates/email/` qovluğundakı `.html` template-ləri embed edin. `RenderEmail(name string, data any) (string, error)` funksiyası yazın. Template-ləri yalnız bir dəfə parse edin (init-də).

**Tapşırıq 3: Migration tracker**

SQL migration fayllarını embed edin. `migrations` cədvəlini yaradın. Hər migration-un icra edilib-edilmədiyini yoxlayın. Yalnız yenilərini icra edin. `down` migration dəstəyi əlavə edin.

```sql
-- migrations/001_create_users.sql
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

## Ətraflı Qeydlər

**Glob pattern qaydaları:**

```go
//go:embed templates/*.html         // yalnız birbaşa templates/ içindəkilər
//go:embed templates/**/*.html      // Bu işləMİR — Go embed recursive deyil
//go:embed templates               // bütün qovluq (recursive) — qaydası budur
//go:embed all:static              // gizli fayllar da daxil olmaqla (. ilə başlayanlar)
```

**Bir neçə pattern:**

```go
//go:embed templates static migrations
var assetsFS embed.FS
```

**Test-lərdə embed:**

```go
// Test faylında embed istifadə edə bilərsiniz — eyni sintaksis
//go:embed testdata/fixture.json
var testFixture []byte
```

## Əlaqəli Mövzular

- `14-packages-and-modules` — Go module sistemi
- `13-file-operations` — adi fayl oxuma ilə müqayisə
- `33-http-server` — HTTP static file server
- `46-text-templates` — `html/template` ilə işləmək
- `37-database` — migration runner
- `39-environment-and-config` — konfiqurasiya idarəsi
