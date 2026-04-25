# Regular Expressions — Regexp (Middle)

## İcmal

Go-da regular expression dəstəyi standart `regexp` paketi vasitəsilə təmin olunur. Paket RE2 sintaksisini istifadə edir — bu PCRE deyil, buna görə bəzi pattern-lər (məsələn lookahead/lookbehind) dəstəklənmir. Əvəzinə performans zəmanəti var: RE2 heç vaxt eksponensial vaxt sərf etmir. PHP-nin `preg_*` funksiyalarından fərqli olaraq, Go-da regex obyekti əvvəlcədən compile edilir və yenidən istifadə olunur.

## Niyə Vacibdir

Real layihələrdə regexp-dən istifadə çox geniş yayılıb: email/telefon/URL validasiyası, log fayllarından data çıxarma, HTTP request parsing, konfigürasiya fayllarının analizi, mətn transformasiyaları. Go-da backend servislərin əksəriyyəti HTTP API-dir və giriş məlumatlarının yoxlanması üçün regexp tez-tez lazım olur.

## Əsas Anlayışlar

- `regexp.MustCompile` — pattern-i compile edir; xəta olarsa panic verir (başlanğıcda bir dəfə çağırılır)
- `regexp.Compile` — xəta qaytarır, production kodunda bu tövsiyə edilir
- `re.MatchString` — uyğunluq varsa `true` qaytarır
- `re.FindString` — ilk uyğunluğu string kimi qaytarır
- `re.FindAllString` — bütün uyğunluqları `[]string` kimi qaytarır; `-1` limitsiz deməkdir
- `re.FindStringSubmatch` — capture group-larla birlikdə ilk uyğunluğu qaytarır
- `re.ReplaceAllString` — bütün uyğunluqları replace edir
- `re.ReplaceAllStringFunc` — hər uyğunluğa funksiya tətbiq edir
- `re.Split` — pattern üzrə bölür
- Named groups: `(?P<ad>...)` — adlı capture group
- RE2 sintaksisi: lookahead (`(?=...)`) və lookbehind (`(?<=...)`) **dəstəklənmir**

## Praktik Baxış

**Real istifadə ssenariləri:**
- API endpoint-lərinin giriş validasiyası (email, telefon, UUID formatı)
- Log fayllarından error mesajlarını, IP ünvanlarını, tarix-vaxtı çıxarmaq
- Router-da path parametrlərini parse etmək
- Webhook payload-larından struktur çıxarmaq

**Trade-off-lar:**
- Sadə string əməliyyatları üçün `strings.Contains`, `strings.HasPrefix` daha sürətlidir — regexp-i yalnız lazım olduqda istifadə edin
- `MustCompile` vs `Compile`: paket səviyyəsində dəyişkən kimi saxlanacaq regex üçün `MustCompile` istifadə edin; dinamik pattern üçün `Compile` ilə xətanı idarə edin
- RE2 PCRE-dən yavaş deyil, hətta üstün olur çünki worst-case zaman O(n)

**Ümumi səhvlər:**
- Hər request-də `regexp.Compile` çağırmaq — çox bahalıdır; paketi global dəyişkən kimi saxlayın
- `FindString` ilə `FindStringSubmatch` qarışdırmaq — groups lazımsa ikincini istifadə edin
- Raw string literal `` ` `` əvəzinə `"..."` istifadə edib `\\d` yazmağı unutmaq

**PHP ilə fərqi:**
- PHP-də `preg_match('/pattern/', $str)` — Go-da `re.MatchString(str)`
- PHP-də PCRE (lookahead/lookbehind var) — Go-da RE2 (yoxdur)
- PHP-də `preg_replace_callback` — Go-da `ReplaceAllStringFunc`
- Go-da compile ayrı addımdır; PHP-də avtomatikdir (amma PHP içerdə cache edir)

## Nümunələr

### Nümunə 1: Compile bir dəfə, istifadə dəfələrlə

```go
package main

import (
    "fmt"
    "regexp"
)

// Paket səviyyəsində compile et — performans üçün vacibdir
var (
    emailRe = regexp.MustCompile(`^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$`)
    telRe   = regexp.MustCompile(`^\+994\d{9}$`)
    ipRe    = regexp.MustCompile(`^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$`)
)

func emailDogrudurmu(email string) bool {
    return emailRe.MatchString(email)
}

func main() {
    fmt.Println(emailDogrudurmu("orkhan@mail.az"))  // true
    fmt.Println(emailDogrudurmu("yanlis@"))          // false

    fmt.Println(telRe.MatchString("+994501234567"))  // true
    fmt.Println(telRe.MatchString("0501234567"))     // false

    fmt.Println(ipRe.MatchString("192.168.1.1"))     // true
}
```

### Nümunə 2: Capture groups — tarix parsing

```go
package main

import (
    "fmt"
    "regexp"
)

func main() {
    // Adsız capture groups
    tarixRe := regexp.MustCompile(`(\d{4})-(\d{2})-(\d{2})`)
    uygunlug := tarixRe.FindStringSubmatch("Tarix: 2024-03-15 idi")
    if uygunlug != nil {
        fmt.Println("Tam match:", uygunlug[0]) // 2024-03-15
        fmt.Println("İl:",       uygunlug[1])  // 2024
        fmt.Println("Ay:",       uygunlug[2])  // 03
        fmt.Println("Gün:",      uygunlug[3])  // 15
    }

    // Adlı capture groups — daha oxunaqlı
    adliRe := regexp.MustCompile(`(?P<ad>\w+):(?P<deyer>\d+)`)
    n := adliRe.FindStringSubmatch("yas:25")
    if n != nil {
        for i, ad := range adliRe.SubexpNames() {
            if i != 0 && ad != "" {
                fmt.Printf("%s = %s\n", ad, n[i])
            }
        }
    }
    // ad = yas
    // deyer = 25
}
```

### Nümunə 3: Replace və Split

```go
package main

import (
    "fmt"
    "regexp"
    "strconv"
)

func main() {
    // Bütün rəqəmləri X ilə replace et
    re := regexp.MustCompile(`\d+`)
    netic := re.ReplaceAllString("Mənim 3 pişiyim və 2 itim var", "X")
    fmt.Println(netic) // Mənim X pişiyim və X itim var

    // Hər rəqəmi iki qatına çıxar (funksiya ilə replace)
    ikiqat := re.ReplaceAllStringFunc("qiymət: 10, endirim: 5", func(s string) string {
        n, _ := strconv.Atoi(s)
        return strconv.Itoa(n * 2)
    })
    fmt.Println(ikiqat) // qiymət: 20, endirim: 10

    // Vergül, nöqtəli-vergül və ya boşluqla bölmə
    bolRe := regexp.MustCompile(`[,;\s]+`)
    parcalar := bolRe.Split("alma, armud; nar  üzüm", -1)
    fmt.Println(parcalar) // [alma armud nar üzüm]

    // Bütün uyğunluqları tap
    hamisi := re.FindAllString("10 alma, 20 armud, 30 nar", -1)
    fmt.Println(hamisi) // [10 20 30]
}
```

### Nümunə 4: Log faylından data çıxarma

```go
package main

import (
    "fmt"
    "regexp"
)

var logRe = regexp.MustCompile(
    `(?P<tarix>\d{4}-\d{2}-\d{2}) (?P<vaxt>\d{2}:\d{2}:\d{2}) (?P<seviye>ERROR|WARN|INFO) (?P<mesaj>.+)`,
)

type LogSatiri struct {
    Tarix  string
    Vaxt   string
    Seviye string
    Mesaj  string
}

func logParse(satir string) *LogSatiri {
    m := logRe.FindStringSubmatch(satir)
    if m == nil {
        return nil
    }
    names := logRe.SubexpNames()
    result := &LogSatiri{}
    for i, name := range names {
        switch name {
        case "tarix":
            result.Tarix = m[i]
        case "vaxt":
            result.Vaxt = m[i]
        case "seviye":
            result.Seviye = m[i]
        case "mesaj":
            result.Mesaj = m[i]
        }
    }
    return result
}

func main() {
    satir := "2024-03-15 10:30:45 ERROR database connection failed"
    log := logParse(satir)
    if log != nil {
        fmt.Printf("Tarix: %s, Səviyyə: %s, Mesaj: %s\n",
            log.Tarix, log.Seviye, log.Mesaj)
    }
}
```

## Praktik Tapşırıqlar

1. **URL validatoru:** Azərbaycan `.az` domenli URL-ləri yoxlayan regex yaz. `https://domain.az/path?query=value` formatını qəbul etsin.

2. **Log analizatoru:** Nginx access log faylından (`/var/log/nginx/access.log`) bütün `4xx` status kodlu request-ləri çıxaran funksiya yaz. IP, status kodu, URL-i ayrıca saxla.

3. **Template engine:** `{{name}}`, `{{email}}` kimi placeholder-ları olan mətn şablonunu `map[string]string` ilə doldur. `ReplaceAllStringFunc` istifadə et.

4. **Markdown link extractor:** Markdown fayldan `[text](url)` formatındakı bütün linkləri çıxar, text və url-i ayrıca struct-da saxla.

5. **Sensitive data masking:** Log mesajlarında `password=...`, `token=...`, `api_key=...` kimi sahələri `password=***` formatında gizlət.

## Əlaqəli Mövzular

- [12-strings-and-strconv.md](12-strings-and-strconv.md) — string manipulation əsasları, `strings.Contains`, `strings.Replace`
- [18-error-handling.md](18-error-handling.md) — `regexp.Compile` xətasını idarə etmək
- [20-json-encoding.md](20-json-encoding.md) — JSON field validasiyası ilə birlikdə istifadə
- [33-http-server.md](33-http-server.md) — HTTP handler-lərdə giriş validasiyası
- [35-middleware-and-routing.md](35-middleware-and-routing.md) — Router-da path pattern matching
