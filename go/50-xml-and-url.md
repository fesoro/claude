# XML və URL (Senior)

## İcmal

Go standart kitabxanası XML parsing və URL idarəsi üçün tam dəstək verir. `encoding/xml` — struct tag-larla XML marshal/unmarshal, `net/url` — URL parsing, query parametrləri, encoding. PHP Laravel-dən fərqli olaraq Go-da bu əməliyyatlar üçün xarici kitabxana lazım deyil. Legacy sistemlərlə inteqrasiya (bank API-ləri, SOAP), sitemap generasiyası, RSS feed, webhook URL validasiyası üçün vacibdir.

## Niyə Vacibdir

- Çoxlu bank, dövlət, B2B API-ləri hələ XML formatında işləyir
- RSS/Atom feed-lər XML-dir
- Kubernetes manifests YAML-dir (YAML XML-in superset-i deyil, amma XML parsing anlayışları transferable)
- URL parsing — query injection-ın qarşısını almaq, API gateway, redirect logic
- `net/url` — HTTP client-lərdə URL düzgün qurmaq üçün `fmt.Sprintf` əvəzi

## Əsas Anlayışlar

### XML Struct Tag-ları

```go
type Product struct {
    XMLName  xml.Name `xml:"product"`           // root element adı
    ID       int      `xml:"id,attr"`            // attribute kimi
    Name     string   `xml:"name"`               // element kimi
    Price    float64  `xml:"price"`
    Tags     []string `xml:"tags>tag"`           // iç-içə: <tags><tag>...</tag></tags>
    Internal string   `xml:"-"`                  // ignore et
    Desc     string   `xml:",chardata"`          // element mətni (body text)
    Comment  string   `xml:",comment"`           // XML komment
}
```

**Vacib tag variantları:**
- `xml:"name"` → `<name>value</name>`
- `xml:"name,attr"` → `<element name="value">`
- `xml:"name,omitempty"` → boş olsa yazma
- `xml:"-"` → marshal/unmarshal-da ignore
- `xml:"a>b>c"` → nested: `<a><b><c>value</c></b></a>`
- `xml:",chardata"` → element-in mətn məzmunu
- `xml:",innerxml"` → raw XML kimi saxla

### Marshal vs Unmarshal

```go
// Struct → XML
data, err := xml.Marshal(v)
data, err := xml.MarshalIndent(v, "", "  ") // formatted

// XML → Struct
err := xml.Unmarshal([]byte(xmlStr), &v)
```

### URL Strukturu

```
https://user:pass@api.example.com:8080/v1/users?page=2&limit=10#section
  │       │    │      │              │    │         │              │
Scheme  User Pass   Host          Port  Path     Query         Fragment
```

```go
u, _ := url.Parse(rawURL)
u.Scheme    // "https"
u.Host      // "api.example.com:8080"
u.Hostname() // "api.example.com"
u.Port()    // "8080"
u.Path      // "/v1/users"
u.RawQuery  // "page=2&limit=10"
u.Fragment  // "section"
u.User.Username()  // "user"
pass, _ := u.User.Password() // "pass"
```

### Query Parametrləri

```go
params := u.Query() // url.Values — map[string][]string
params.Get("page")  // "2" — ilk dəyər
params.Add("tag", "go")
params.Set("limit", "20") // əvəz et
params.Del("page")        // sil
encoded := params.Encode() // "limit=20&tag=go" (sıralı)
```

**Çoxlu eyni key:**
```go
params.Add("tag", "go")
params.Add("tag", "api")
params["tag"] // ["go", "api"]
```

### URL Encoding

```go
// Query string encoding: boşluq → +, & → %26
url.QueryEscape("salam & xoş gəldiniz")
// "salam+%26+xo%C5%9F+g%C9%99ldiniz"

// Path encoding: boşluq → %20, / → %2F
url.PathEscape("my folder/my file.txt")
// "my%20folder%2Fmy%20file.txt"
```

**Fərq:** `QueryEscape` form data üçün (GET params), `PathEscape` URL path segment üçün.

## Praktik Baxış

### Real Layihələrdə İstifadə

**XML:**
- Bank API inteqrasiyası (ödəniş, açıqlama)
- Sitemap.xml generasiyası (SEO)
- RSS/Atom feed oxuma/yaratma
- SOAP service-lərlə işləmək
- Maven/Gradle pom.xml parsing

**URL:**
- API Gateway-də URL routing
- OAuth2 redirect URL qurmaq
- Webhook URL validasiyası
- Pagination-da cursor URL encoding
- Query injection-ın qarşısını almaq

### URL Qurma — fmt.Sprintf Əvəzi

```go
// YANLIŞ — injection riski, encoding yoxdur
url := fmt.Sprintf("https://api.example.com/search?q=%s", userInput)

// DÜZGÜN
base, _ := url.Parse("https://api.example.com/search")
params := base.Query()
params.Set("q", userInput) // avtomatik encode olur
base.RawQuery = params.Encode()
finalURL := base.String()
```

### Nisbi URL-ləri Birləşdirmək

```go
base, _ := url.Parse("https://example.com/api/v1/")
ref, _ := url.Parse("users?id=5")
result := base.ResolveReference(ref)
// https://example.com/api/v1/users?id=5
```

### Trade-off-lar

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| xml.Marshal | Built-in, struct tag | SOAP kimi mürəkkəb XML üçün az |
| Dinamik XML | Naməlum struktur | Type safety yox |
| xmlwriter | Streaming, böyük XML | Xarici dependency |
| url.Parse | Standart, güvənli | Sadə hallarda verbose |

### Anti-pattern-lər

```go
// Anti-pattern 1: xml.XMLName-i yanlış yerləşdirmək
type Root struct {
    Name string `xml:"name"` // XMLName yoxdur — root element adı struct adı olur
}
// Düzgün:
type Root struct {
    XMLName xml.Name `xml:"myroot"` // root element: <myroot>
    Name    string   `xml:"name"`
}

// Anti-pattern 2: URL-i string əmələ gətirmək
rawURL := "https://api.com/v1/" + path + "?token=" + token
// Token-də & varsa — URL sınır!

// Anti-pattern 3: Unmarshal xətasını ignore etmək
xml.Unmarshal(data, &v) // xəta varsa v yarımçıq doldurulub
// Düzgün:
if err := xml.Unmarshal(data, &v); err != nil {
    return fmt.Errorf("XML parse: %w", err)
}

// Anti-pattern 4: URL Fragment-i API sorğusunda göndərmək
// Fragment (#section) server-ə göndərilmir — yalnız browser istifadə edir

// Anti-pattern 5: url.Values.Encode() olmadan manual RawQuery
u.RawQuery = "key=" + value // encoding yox — inject riski
// Düzgün:
params := u.Query()
params.Set("key", value)
u.RawQuery = params.Encode()
```

## Nümunələr

### Nümunə 1: XML Marshal / Unmarshal — Bank Statement

```go
package main

import (
    "encoding/xml"
    "fmt"
)

type BankStatement struct {
    XMLName     xml.Name     `xml:"BankStatement"`
    AccountID   string       `xml:"AccountID,attr"`
    Currency    string       `xml:"Currency,attr"`
    OpenBalance float64      `xml:"Balance>OpenBalance"`
    CloseBalance float64     `xml:"Balance>CloseBalance"`
    Transactions []Transaction `xml:"Transactions>Transaction"`
}

type Transaction struct {
    ID     string  `xml:"id,attr"`
    Date   string  `xml:"Date"`
    Debit  float64 `xml:"Debit,omitempty"`
    Credit float64 `xml:"Credit,omitempty"`
    Note   string  `xml:"Note"`
}

func main() {
    stmt := BankStatement{
        AccountID:    "AZ12IBAC00000000123456789",
        Currency:     "AZN",
        OpenBalance:  1000.00,
        CloseBalance: 1250.50,
        Transactions: []Transaction{
            {ID: "TXN001", Date: "2024-01-15", Credit: 500.00, Note: "Maaş"},
            {ID: "TXN002", Date: "2024-01-16", Debit: 249.50, Note: "Alış-veriş"},
        },
    }

    // Struct → XML
    data, err := xml.MarshalIndent(stmt, "", "  ")
    if err != nil {
        fmt.Println("Marshal xətası:", err)
        return
    }

    fullXML := xml.Header + string(data)
    fmt.Println(fullXML)

    // XML → Struct
    var parsed BankStatement
    if err := xml.Unmarshal([]byte(fullXML), &parsed); err != nil {
        fmt.Println("Unmarshal xətası:", err)
        return
    }

    fmt.Printf("Hesab: %s\n", parsed.AccountID)
    fmt.Printf("Kapanış balansı: %.2f %s\n", parsed.CloseBalance, parsed.Currency)
    fmt.Printf("Əməliyyat sayı: %d\n", len(parsed.Transactions))
}
```

### Nümunə 2: RSS Feed Generator

```go
package main

import (
    "encoding/xml"
    "fmt"
    "time"
)

type RSS struct {
    XMLName xml.Name `xml:"rss"`
    Version string   `xml:"version,attr"`
    Channel Channel  `xml:"channel"`
}

type Channel struct {
    Title       string `xml:"title"`
    Link        string `xml:"link"`
    Description string `xml:"description"`
    Language    string `xml:"language"`
    Items       []Item `xml:"item"`
}

type Item struct {
    Title       string `xml:"title"`
    Link        string `xml:"link"`
    Description string `xml:"description"`
    PubDate     string `xml:"pubDate"`
    GUID        string `xml:"guid"`
}

func generateRSS(posts []map[string]string) ([]byte, error) {
    var items []Item
    for _, post := range posts {
        items = append(items, Item{
            Title:       post["title"],
            Link:        post["url"],
            Description: post["excerpt"],
            PubDate:     post["date"],
            GUID:        post["url"],
        })
    }

    feed := RSS{
        Version: "2.0",
        Channel: Channel{
            Title:       "Go Bloq",
            Link:        "https://blog.example.com",
            Description: "Go proqramlaşdırma haqqında",
            Language:    "az",
            Items:       items,
        },
    }

    return xml.MarshalIndent(feed, "", "  ")
}

func main() {
    posts := []map[string]string{
        {
            "title":   "Go-da Goroutines",
            "url":     "https://blog.example.com/goroutines",
            "excerpt": "Go-da paralel proqramlaşdırma...",
            "date":    time.Now().Format(time.RFC1123Z),
        },
        {
            "title":   "Go Interfaces",
            "url":     "https://blog.example.com/interfaces",
            "excerpt": "Interface-lərin düzgün istifadəsi...",
            "date":    time.Now().Add(-24 * time.Hour).Format(time.RFC1123Z),
        },
    }

    data, err := generateRSS(posts)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }

    fmt.Println(xml.Header + string(data))
}
```

### Nümunə 3: URL Builder — API Client

```go
package main

import (
    "fmt"
    "net/url"
    "time"
)

type APIClient struct {
    baseURL *url.URL
    token   string
}

func NewAPIClient(baseURL, token string) (*APIClient, error) {
    u, err := url.Parse(baseURL)
    if err != nil {
        return nil, fmt.Errorf("yanlış URL: %w", err)
    }
    return &APIClient{baseURL: u, token: token}, nil
}

func (c *APIClient) buildURL(path string, params map[string]string) string {
    // Base URL-dən başla
    u := *c.baseURL // kopyala
    u.Path = u.Path + path

    // Query parametrləri
    q := u.Query()
    for k, v := range params {
        q.Set(k, v)
    }

    // Auth token əlavə et
    if c.token != "" {
        q.Set("access_token", c.token)
    }

    u.RawQuery = q.Encode()
    return u.String()
}

func main() {
    client, err := NewAPIClient("https://api.example.com/v2", "my-secret-token")
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }

    // Pagination URL
    usersURL := client.buildURL("/users", map[string]string{
        "page":     "2",
        "limit":    "20",
        "sort":     "created_at",
        "order":    "desc",
        "search":   "Orkhan Şükürlü", // xüsusi simvollar avtomatik encode olur
    })
    fmt.Println("Users URL:")
    fmt.Println(" ", usersURL)

    // Tarix filtri
    reportURL := client.buildURL("/reports/transactions", map[string]string{
        "from": time.Now().Add(-30 * 24 * time.Hour).Format("2006-01-02"),
        "to":   time.Now().Format("2006-01-02"),
        "type": "all",
    })
    fmt.Println("\nReport URL:")
    fmt.Println(" ", reportURL)
}
```

### Nümunə 4: URL Validasiya Middleware

```go
package main

import (
    "fmt"
    "net/url"
    "strings"
)

type URLValidator struct {
    allowedSchemes []string
    allowedHosts   []string
    maxLength      int
}

func NewURLValidator() *URLValidator {
    return &URLValidator{
        allowedSchemes: []string{"https", "http"},
        maxLength:      2048,
    }
}

func (v *URLValidator) WithAllowedHosts(hosts ...string) *URLValidator {
    v.allowedHosts = hosts
    return v
}

func (v *URLValidator) Validate(rawURL string) error {
    if len(rawURL) > v.maxLength {
        return fmt.Errorf("URL çox uzundur: %d > %d", len(rawURL), v.maxLength)
    }

    u, err := url.ParseRequestURI(rawURL)
    if err != nil {
        return fmt.Errorf("URL formatı yanlışdır: %w", err)
    }

    // Scheme yoxla
    validScheme := false
    for _, s := range v.allowedSchemes {
        if u.Scheme == s {
            validScheme = true
            break
        }
    }
    if !validScheme {
        return fmt.Errorf("icazəsiz scheme: %s", u.Scheme)
    }

    // Host yoxla (əgər müəyyən edilmişsə)
    if len(v.allowedHosts) > 0 {
        hostname := strings.ToLower(u.Hostname())
        allowed := false
        for _, h := range v.allowedHosts {
            if hostname == strings.ToLower(h) || strings.HasSuffix(hostname, "."+strings.ToLower(h)) {
                allowed = true
                break
            }
        }
        if !allowed {
            return fmt.Errorf("icazəsiz host: %s", hostname)
        }
    }

    // Fragment yoxla — server-ə getmir
    if u.Fragment != "" {
        return fmt.Errorf("URL fragment (#) istifadə edilə bilməz")
    }

    return nil
}

func main() {
    validator := NewURLValidator().
        WithAllowedHosts("example.com", "api.example.com")

    testURLs := []string{
        "https://api.example.com/v1/users?page=1",
        "http://evil.com/attack",
        "javascript:alert(1)",
        "https://api.example.com/data#fragment",
        "not-a-url",
        "https://sub.example.com/valid", // subdomain
    }

    for _, u := range testURLs {
        err := validator.Validate(u)
        if err != nil {
            fmt.Printf("❌ %s\n   → %v\n", u, err)
        } else {
            fmt.Printf("✓ %s\n", u)
        }
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Sitemap Generator:**
Wordpress/Laravel CMS-dən URL siyahısını alıb `sitemap.xml` yaradan tool yazın. `<url>`, `<loc>`, `<lastmod>`, `<changefreq>`, `<priority>` elementlərini daxil edin.

**Tapşırıq 2 — SOAP Client:**
Sadə SOAP request göndərin. Body XML kimi marshal edin, response-u unmarshal edin. `SOAPEnvelope`, `SOAPBody`, `SOAPHeader` struct-larını yazın.

**Tapşırıq 3 — URL Shortener:**
Uzun URL-i qısa koda map edən in-memory URL shortener API yazın. Redirect zamanı `net/url`-dən istifadə edin.

**Tapşırıq 4 — Query Parser Middleware:**
HTTP request-dən query parametrlərini parse edib, pagination məlumatını (`page`, `limit`, `sort`, `order`) standart struct-a çevirən middleware yazın.

## Əlaqəli Mövzular

- [20-json-encoding](20-json-encoding.md) — JSON encoding ilə müqayisə
- [33-http-server](33-http-server.md) — HTTP server-də URL işləmə
- [34-http-client](34-http-client.md) — HTTP client-də URL qurmaq
- [46-text-templates](46-text-templates.md) — Template ilə XML/HTML generasiyası
- [62-security](62-security.md) — URL injection qorunması
