# Text və HTML Templates (Senior)

## İcmal

Go standart kitabxanasında iki güclü template paketi var: `text/template` — ümumi mətn generasiyası üçün, `html/template` — XSS qorunması ilə HTML üçün. Code generator-lar, email şablonları, konfiqurasiya faylı generasiyası, static site generator-lar, CLI output formatlaması — bu paket real layihələrdə geniş istifadə olunur.

## Niyə Vacibdir

- `html/template` XSS-i avtomatik prevent edir — security-critical
- Code generasiyası üçün (`go generate`) mükəmməl
- Email template-ləri, Kubernetes manifests, Helm chart-lar Go template sintaksisi istifadə edir
- `text/template` ilə Makefile, Dockerfile, konfiqurasiya faylları generate etmək olar
- Xarici dependency olmadan — standart kitabxanaya daxildir

## Əsas Anlayışlar

### Template Sintaksisi

```
{{.Field}}          — struct/map field-i
{{.Method}}         — method çağırmaq
{{if .Condition}}   — şərt
{{range .Slice}}    — iterasiya
{{template "name"}} — başqa template-i daxil etmək
{{define "name"}}   — template təyin etmək
{{- ...}}          — whitespace strip (sol)
{{... -}}          — whitespace strip (sağ)
{{/* komment */}}  — komment
```

### `.` Nöqtəsinin Konteksti

Template daxilindəki `.` (dot) — cari scope-u bildirir. `range` blokunun içində `.` iterasiya elementi olur:

```
{{range .Items}}
  {{.Name}}   <!-- . burada item-dir, Items yox -->
{{end}}
```

Üst scope-a çatmaq üçün `$` — root kontekst:
```
{{range .Items}}
  {{$.Title}} — {{.Name}}
{{end}}
```

### text/template vs html/template

| Xüsusiyyət | text/template | html/template |
|------------|--------------|--------------|
| XSS protection | Yox | Bəli (auto-escape) |
| Context-aware escaping | Yox | Bəli |
| Sintaksis | Eyni | Eyni |
| İstifadə | Email text, code gen | HTML, web pages |

`html/template`-də `<script>alert('XSS')</script>` avtomatik `&lt;script&gt;...` olur.

### FuncMap — Xüsusi Funksiyalar

Template-ə öz funksiyalarını əlavə etmək:

```go
funcMap := template.FuncMap{
    "upper":    strings.ToUpper,
    "truncate": func(s string, n int) string { ... },
    "formatDate": func(t time.Time) string { return t.Format("02.01.2006") },
}

tmpl := template.New("t").Funcs(funcMap).Parse(src)
```

**Qayda:** `Funcs()` `Parse()`-dan əvvəl çağırılmalıdır.

### template.Must

Xəta handling üçün — əgər Parse xəta verərsə `panic` edir. Startup zamanı static template-lər üçün istifadə olunur:

```go
// Proqram başlayanda parse et — xəta varsa hemen bilin
var tmpl = template.Must(template.ParseFiles("email.html"))
```

Runtime-da istifadəçi giriş templatesi üçün `Must` istifadə etməyin.

## Praktik Baxış

### Real Layihələrdə İstifadə

- **Email şablonları:** HTML email-lər `html/template` ilə generate olunur
- **Code generation:** `go generate` + template ilə boilerplate kod
- **CLI tool output:** Tablo formatlaması, report
- **Kubernetes/Helm:** Manifest generasiyası (Helm Go template istifadə edir)
- **Konfiqurasiya:** `config.yaml.tmpl` → `config.yaml`
- **Dokumentasiya:** godoc-dan markdown generasiyası

### Template-i Fayl-lara Bölmək

```go
// Birdən çox fayldan yüklə
tmpl, err := template.ParseFiles(
    "templates/layout.html",
    "templates/header.html",
    "templates/footer.html",
)

// Glob pattern ilə
tmpl, err := template.ParseGlob("templates/*.html")

// Embed ilə (binary-ə daxil etmək)
//go:embed templates/*
var templateFS embed.FS
tmpl, err := template.ParseFS(templateFS, "templates/*.html")
```

### ExecuteTemplate vs Execute

```go
// Execute — bütün template-i işlət
tmpl.Execute(w, data)

// ExecuteTemplate — adlı template-i işlət (define ilə)
tmpl.ExecuteTemplate(w, "layout", data)
```

### Trade-off-lar

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| `text/template` | Built-in, sürətli, heç dependency yox | Logic az, kompleks template çətin |
| `html/template` | XSS auto-protection | text/template-dən yavaş |
| Xarici (templ, jet, pongo2) | Daha çox feature | Dependency əlavə olur |
| String concatenation | Çox sadə | XSS riski, oxunaqlı deyil |

### Anti-pattern-lər

```go
// Anti-pattern 1: html/template əvəzinə text/template istifadə
// İstifadəçidən gələn data template-ə girərsə XSS riski var
import "text/template" // HTML üçün YANLIŞ

// Düzgün:
import "html/template" // HTML üçün

// Anti-pattern 2: Template-i hər request-də parse etmək
func handler(w http.ResponseWriter, r *http.Request) {
    tmpl, _ := template.ParseFiles("page.html") // HƏR REQUEST-DƏ! Yavaş
    tmpl.Execute(w, data)
}

// Düzgün: startup-da parse et, sonra istifadə et
var tmpl = template.Must(template.ParseFiles("page.html"))

func handler(w http.ResponseWriter, r *http.Request) {
    tmpl.Execute(w, data)
}

// Anti-pattern 3: Execute xətasını ignore etmək
tmpl.Execute(w, data) // xəta yoxlanmır

// Düzgün:
if err := tmpl.Execute(w, data); err != nil {
    log.Printf("template execute error: %v", err)
}
```

## Nümunələr

### Nümunə 1: Email Şablonu

```go
package main

import (
    "bytes"
    "fmt"
    "html/template"
    "time"
)

type EmailData struct {
    UserName    string
    OrderID     string
    Items       []OrderItem
    Total       float64
    OrderDate   time.Time
    SupportURL  string
}

type OrderItem struct {
    Name     string
    Quantity int
    Price    float64
}

const emailTemplate = `
<!DOCTYPE html>
<html>
<body>
<h2>Salam, {{.UserName}}!</h2>
<p>{{.OrderDate.Format "02.01.2006"}} tarixli sifarişiniz təsdiqləndi.</p>

<h3>Sifariş #{{.OrderID}}</h3>
<table>
{{range .Items}}
<tr>
  <td>{{.Name}}</td>
  <td>{{.Quantity}} ədəd</td>
  <td>{{printf "%.2f" .Price}} AZN</td>
</tr>
{{end}}
</table>

<p><strong>Cəmi: {{printf "%.2f" .Total}} AZN</strong></p>

{{if gt .Total 100.0}}
<p>Pulsuz çatdırılma!</p>
{{else}}
<p>Çatdırılma haqqı: 3.99 AZN</p>
{{end}}

<p>Suallarınız üçün: <a href="{{.SupportURL}}">Dəstək</a></p>
</body>
</html>
`

func main() {
    tmpl := template.Must(template.New("email").Parse(emailTemplate))

    data := EmailData{
        UserName: "Orkhan",
        OrderID:  "ORD-2024-001",
        Items: []OrderItem{
            {Name: "Go Proqramlaşdırma Kitabı", Quantity: 1, Price: 45.00},
            {Name: "USB Hub", Quantity: 2, Price: 35.00},
        },
        Total:      115.00,
        OrderDate:  time.Now(),
        SupportURL: "https://support.example.com",
    }

    var buf bytes.Buffer
    if err := tmpl.Execute(&buf, data); err != nil {
        fmt.Println("Xəta:", err)
        return
    }

    fmt.Println("Email hazırdır, uzunluq:", buf.Len(), "byte")
    // Real layihədə: smtp.SendMail(...)
}
```

### Nümunə 2: Code Generator

```go
package main

import (
    "os"
    "text/template"
)

type RepositoryData struct {
    PackageName string
    EntityName  string
    TableName   string
    Fields      []Field
}

type Field struct {
    Name    string
    Type    string
    DBTag   string
}

const repoTemplate = `package {{.PackageName}}

import (
    "context"
    "database/sql"
    "fmt"
)

type {{.EntityName}} struct {
{{range .Fields}}    {{.Name}} {{.Type}} ` + "`" + `db:"{{.DBTag}}"` + "`" + `
{{end}}}

type {{.EntityName}}Repository struct {
    db *sql.DB
}

func New{{.EntityName}}Repository(db *sql.DB) *{{.EntityName}}Repository {
    return &{{.EntityName}}Repository{db: db}
}

func (r *{{.EntityName}}Repository) FindByID(ctx context.Context, id int) (*{{.EntityName}}, error) {
    var entity {{.EntityName}}
    query := "SELECT * FROM {{.TableName}} WHERE id = $1"
    err := r.db.QueryRowContext(ctx, query, id).Scan({{range $i, $f := .Fields}}{{if $i}}, {{end}}&entity.{{$f.Name}}{{end}})
    if err != nil {
        return nil, fmt.Errorf("{{.EntityName}}Repository.FindByID: %w", err)
    }
    return &entity, nil
}
`

func main() {
    tmpl := template.Must(template.New("repo").Parse(repoTemplate))

    data := RepositoryData{
        PackageName: "repository",
        EntityName:  "User",
        TableName:   "users",
        Fields: []Field{
            {Name: "ID", Type: "int", DBTag: "id"},
            {Name: "Name", Type: "string", DBTag: "name"},
            {Name: "Email", Type: "string", DBTag: "email"},
        },
    }

    // stdout-a yaz (real halda fayla yazılır)
    if err := tmpl.Execute(os.Stdout, data); err != nil {
        fmt.Fprintln(os.Stderr, "Xəta:", err)
    }
}
```

### Nümunə 3: CLI Table Output

```go
package main

import (
    "os"
    "text/template"
)

type Report struct {
    Title   string
    Headers []string
    Rows    [][]string
    Total   int
}

const tableTemplate = `
=== {{.Title}} ===
{{range .Headers}}{{printf "%-20s" .}}{{end}}
{{range .Headers}}{{printf "%-20s" (repeat "-" 20)}}{{end}}
{{range .Rows}}{{range .}}{{printf "%-20s" .}}{{end}}
{{end}}
Cəmi: {{.Total}} qeyd
`

func main() {
    funcMap := template.FuncMap{
        "repeat": func(s string, n int) string {
            result := ""
            for i := 0; i < n; i++ {
                result += s
            }
            return result
        },
    }

    tmpl := template.Must(template.New("table").Funcs(funcMap).Parse(tableTemplate))

    report := Report{
        Title:   "İstifadəçilər",
        Headers: []string{"ID", "Ad", "Email"},
        Rows: [][]string{
            {"1", "Orkhan Şükürlü", "orkhan@example.com"},
            {"2", "Əli Məmmədov", "ali@example.com"},
            {"3", "Vüsal Hüseynov", "vusal@example.com"},
        },
        Total: 3,
    }

    tmpl.Execute(os.Stdout, report)
}
```

### Nümunə 4: Layout ilə Web Handler

```go
package main

import (
    "html/template"
    "log"
    "net/http"
    "time"
)

// Startup-da bütün template-ləri yüklə
var templates = template.Must(template.New("").Funcs(template.FuncMap{
    "formatDate": func(t time.Time) string {
        return t.Format("02 January 2006")
    },
    "safeURL": func(s string) template.URL {
        return template.URL(s) // trusted URL
    },
}).ParseGlob("templates/*.html"))

type PageData struct {
    Title   string
    User    string
    Content interface{}
}

func indexHandler(w http.ResponseWriter, r *http.Request) {
    data := PageData{
        Title:   "Ana Səhifə",
        User:    "Orkhan",
        Content: map[string]interface{}{
            "Message": "Xoş gəldiniz!",
            "Date":    time.Now(),
        },
    }

    w.Header().Set("Content-Type", "text/html; charset=utf-8")
    if err := templates.ExecuteTemplate(w, "layout.html", data); err != nil {
        log.Printf("template error: %v", err)
        http.Error(w, "Internal Error", http.StatusInternalServerError)
    }
}

func main() {
    http.HandleFunc("/", indexHandler)
    log.Println("Server :8080-də işləyir")
    log.Fatal(http.ListenAndServe(":8080", nil))
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Konfiqurasiya Generator:**
`config.yaml.tmpl` template yazın. `AppName`, `Port`, `DatabaseURL`, `Features []string` sahələrini qəbul etsin. `go generate` ilə real konfiqurasiya faylı yaransın.

**Tapşırıq 2 — Multi-language Email:**
Şablon içindəki dil seçiminə görə (`lang: "az"` vs `"en"`) fərqli mətn göstərən email template yazın. FuncMap-ə `translate(key, lang string) string` funksiyası əlavə edin.

**Tapşırıq 3 — Markdown-dan HTML:**
Template ilə sadə Markdown konversiya edin: `**bold**`, `# Başlıq`, `- siyahı`. FuncMap-də regex istifadə edin.

**Tapşırıq 4 — Benchmark:**
Template-i bir dəfə parse edib 1000 dəfə execute etmə vs hər dəfə parse edib execute etmə — `testing.B` ilə ölçün.

## PHP ilə Müqayisə

PHP-də Blade (Laravel) və Twig (Symfony) kimi template mühərrikləri xarici paketlərdir. Go-da `text/template` və `html/template` standart kitabxanaya daxildir:

```php
{{-- Blade sintaksisi --}}
@foreach ($users as $user)
    <p>{{ $user->name }}</p>
@endforeach

@if ($total > 100)
    <p>Pulsuz çatdırılma!</p>
@endif
```

```go
// Go template sintaksisi
{{range .Users}}
    <p>{{.Name}}</p>
{{end}}

{{if gt .Total 100.0}}
    <p>Pulsuz çatdırılma!</p>
{{end}}
```

**Fərq:** Blade/Twig daha zəngin logic dəstəyi verir (class method call, filter chain). Go template-i qəsdən məhdudlaşdırılıb — mürəkkəb logic template-dən Go koduna köçürülməlidir. `html/template`-in XSS auto-escape funksiyası Blade-dəki `{{ }}` vs `{!! !!}` fərqinə bənzəyir.

## Əlaqəli Mövzular

- [20-json-encoding](20-json-encoding.md) — JSON serialization
- [30-io-reader-writer](30-io-reader-writer.md) — io.Writer interfeysi
- [31-go-embed](31-go-embed.md) — Template fayllarını binary-ə daxil etmək
- [33-http-server](33-http-server.md) — HTTP handler-lərdə template istifadəsi
- [50-xml-and-url](50-xml-and-url.md) — XML serialization
