# Fuzz Testing (Lead)

## İcmal

Fuzz testing — proqrama avtomatik olaraq gözlənilməz, random input göndərərək crash, panic və ya yanlış davranış axtarır. Go 1.18-dən `go test -fuzz` built-in dəstəkdir — xarici alət lazım deyil.

## Niyə Vacibdir

- Parser, serializer, kriptografiya kodu üçün kritik — edge case-ləri tapır
- Unit test yazdığınızda düşünmədiyiniz input-ları avtomatik tapır
- Go standart kitabxanası özü fuzz ilə test edilir — `encoding/json`, `net/http`
- Production-da nadir amma dağıdıcı bug-ları tapır: nil pointer, integer overflow, OOB

## Əsas Anlayışlar

**Fuzz funksiyası:**
```go
func FuzzXxx(f *testing.F) {
    // Seed corpus — başlanğıc nümunə input-lar
    f.Add("example input")
    f.Add(42, true)

    // Fuzz target — f.Add tiplərinə uyğun arqumentlər
    f.Fuzz(func(t *testing.T, input string) {
        // Crash verməməlidir — panic = bug
        result := MyFunction(input)
        _ = result
    })
}
```

**Corpus:**
- Seed corpus: `f.Add()` ilə əlavə edilir — başlanğıc input-lar
- Generated corpus: fuzzer tapılan maraqlı input-ları `testdata/fuzz/FuzzXxx/` qovluğuna saxlayır
- Sonrakı testlərdə avtomatik istifadə edilir

**`-fuzz` vs normal test:**
- `go test` — yalnız seed corpus ilə işlər
- `go test -fuzz=FuzzXxx` — sonsuz loop, avtomatik mutasiya

## Praktik Baxış

**Nə vaxt fuzz test yaz:**
- İstifadəçi inputunu parse edən kod (JSON, CSV, URL, HTML)
- Kriptografiya funksiyaları
- Sıxma/açma (compression)
- Protokol implementasiyaları
- Riyazi funksiyalar (overflow mümkündürsə)

**Nə vaxt lazım deyil:**
- Database sorğuları (input DB-yə gedir, sistem resursu lazımdır)
- HTTP handler-lər (fuzzer üçün mock lazımdır, ağırdır)
- Sadə utility funksiyaları

**Common mistakes:**
- Fuzz target-i sonsuz loop kimi unub saxlamaq — `-fuzz` yalnız aktiv test zamanı
- Corpus fayllarını `.gitignore`-a əlavə etmək — tapılan bug-lar itirilir
- Fuzz target-də resource leak — hər çağırışda fayl/goroutine açmaq

## Nümunələr

### Nümunə 1: JSON parser fuzz testi

```go
package parser_test

import (
    "encoding/json"
    "testing"
)

// FuzzJSONRoundtrip — JSON encode → decode dönüşümünün düzgünlüyünü yoxla
func FuzzJSONRoundtrip(f *testing.F) {
    // Seed corpus — başlanğıc nümunələr
    f.Add(`{"name":"Orxan","age":30}`)
    f.Add(`{}`)
    f.Add(`{"nested":{"key":"value"}}`)
    f.Add(`[1,2,3]`)
    f.Add(`"string"`)
    f.Add(`null`)
    f.Add(`true`)

    f.Fuzz(func(t *testing.T, data string) {
        // İstənilən JSON-u decode etməyə cəhd et
        var v interface{}
        if err := json.Unmarshal([]byte(data), &v); err != nil {
            return // Yanlış JSON — problem deyil
        }

        // Uğurlu decode → encode → decode uyğun olmalıdır
        encoded, err := json.Marshal(v)
        if err != nil {
            t.Fatalf("Marshal xətası: %v", err) // BUG — decode etdi, encode edə bilmədi
        }

        var v2 interface{}
        if err := json.Unmarshal(encoded, &v2); err != nil {
            t.Fatalf("İkinci Unmarshal xətası: %v", err) // BUG
        }
    })
}
```

```bash
# Fuzz testi çalışdır (Ctrl+C ilə dayanır)
go test -fuzz=FuzzJSONRoundtrip -fuzztime=60s

# Yalnız corpus ilə normal test
go test -run=FuzzJSONRoundtrip
```

### Nümunə 2: Öz parser-i fuzz et

```go
package parser_test

import (
    "strings"
    "testing"
    "unicode"
)

// Sadə ifadə parser — fuzz ile test edilir
func parseExpression(s string) (int, error) {
    s = strings.TrimSpace(s)
    if s == "" {
        return 0, fmt.Errorf("boş ifadə")
    }

    // "NUM op NUM" formatını parse et: "3 + 5"
    parts := strings.Fields(s)
    if len(parts) != 3 {
        return 0, fmt.Errorf("yanlış format")
    }

    left, err := strconv.Atoi(parts[0])
    if err != nil {
        return 0, err
    }

    right, err := strconv.Atoi(parts[2])
    if err != nil {
        return 0, err
    }

    switch parts[1] {
    case "+":
        return left + right, nil
    case "-":
        return left - right, nil
    case "*":
        return left * right, nil
    case "/":
        if right == 0 {
            return 0, fmt.Errorf("sıfıra bölmə")
        }
        return left / right, nil
    default:
        return 0, fmt.Errorf("naməlum operator: %s", parts[1])
    }
}

func FuzzParseExpression(f *testing.F) {
    f.Add("3 + 5")
    f.Add("10 - 3")
    f.Add("6 * 7")
    f.Add("10 / 2")
    f.Add("10 / 0")

    f.Fuzz(func(t *testing.T, input string) {
        // Panic olmamalıdır — xəta qaytar, crash etmə
        result, err := parseExpression(input)
        if err != nil {
            return // Xəta gözləniləndir
        }

        // Əgər uğurlu nəticə varsa — reasonable range-də olmalıdır
        _ = result
    })
}
```

### Nümunə 3: HTTP handler fuzz testi

```go
package handler_test

import (
    "net/http"
    "net/http/httptest"
    "strings"
    "testing"
)

func FuzzSearchHandler(f *testing.F) {
    // Seed corpus — normal axtarış sorğuları
    f.Add("golang")
    f.Add("go test")
    f.Add("")
    f.Add("'; DROP TABLE users; --") // SQL injection cəhdi
    f.Add("<script>alert('xss')</script>")
    f.Add(strings.Repeat("A", 10000)) // çox uzun string

    f.Fuzz(func(t *testing.T, query string) {
        req := httptest.NewRequest(http.MethodGet,
            "/search?q="+url.QueryEscape(query), nil)
        w := httptest.NewRecorder()

        SearchHandler(w, req)

        // Panic olmadı — bura çatdıq deməlidir
        // 500 qaytarmamalıdır (əgər internal error deyilsə)
        if w.Code == http.StatusInternalServerError {
            t.Errorf("Search handler 500 qaytardı — query: %q", query)
        }
    })
}
```

### Nümunə 4: Fuzz corpus faylları — tapılan bug-lar

```
testdata/fuzz/FuzzSearchHandler/
├── 48b3b16e5c3d7f4a         ← fuzzer tapıb saxladı — crash yaratdı
├── a1c2d3e4f5b6c7d8         ← başqa crash
└── seed/
    ├── 0                    ← f.Add("golang")
    ├── 1                    ← f.Add("go test")
    └── 2                    ← f.Add("")
```

```bash
# Konkret corpus faylı ilə test et
go test -run=FuzzSearchHandler/48b3b16e5c3d7f4a

# Bu tapılan bug-u reproduce edir
```

### Nümunə 5: Custom type fuzz testi

```go
package email_test

import (
    "testing"
    "strings"
)

type Email struct {
    value string
}

func NewEmail(s string) (Email, error) {
    s = strings.TrimSpace(strings.ToLower(s))
    if !strings.Contains(s, "@") {
        return Email{}, fmt.Errorf("@ yoxdur")
    }
    parts := strings.SplitN(s, "@", 2)
    if len(parts[0]) == 0 || len(parts[1]) == 0 {
        return Email{}, fmt.Errorf("boş hissə")
    }
    if !strings.Contains(parts[1], ".") {
        return Email{}, fmt.Errorf("domain yanlışdır")
    }
    return Email{value: s}, nil
}

func FuzzNewEmail(f *testing.F) {
    f.Add("user@example.com")
    f.Add("invalid")
    f.Add("@")
    f.Add("user@")
    f.Add("@domain.com")
    f.Add("a@b.c")

    f.Fuzz(func(t *testing.T, input string) {
        email, err := NewEmail(input)
        if err != nil {
            return // Xəta OK
        }

        // Uğurlu email-də @ olmalıdır
        if !strings.Contains(email.value, "@") {
            t.Errorf("Email-də @ yoxdur: %q", email.value)
        }

        // Boş olmamalıdır
        if email.value == "" {
            t.Error("Email boşdur")
        }
    })
}
```

### Nümunə 6: CI-da fuzz testi

```yaml
# .github/workflows/fuzz.yml
name: Fuzz Tests

on:
  schedule:
    - cron: '0 2 * * *'  # Hər gecə saat 02:00

jobs:
  fuzz:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-go@v5
        with:
          go-version: '1.23'

      - name: Fuzz — JSON parser
        run: go test -fuzz=FuzzJSONRoundtrip -fuzztime=300s ./internal/parser/

      - name: Fuzz — Email validator
        run: go test -fuzz=FuzzNewEmail -fuzztime=120s ./internal/domain/

      # Tapılan corpus fayllarını artifact kimi saxla
      - name: Upload corpus
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: fuzz-corpus
          path: testdata/fuzz/
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
CSV parser yazın: `"a,b,c"` → `[]string{"a","b","c"}`. Fuzz testi ilə crash axtarın. 60 saniyə çalışdırın.

**Tapşırıq 2:**
`NewEmail(string)` funksiyası üçün fuzz testi yazın. `f.Add` ilə 10 seed əlavə edin. 30 saniyə çalışdırın. Corpus fayllarını nəzərdən keçirin.

**Tapşırıq 3:**
Tapılan corpus faylını `testdata/fuzz/` qovluğuna commit edin. `go test -run=FuzzXxx` ilə normal test kimi işləyin — CI-da da keçməlidir.

## PHP ilə Müqayisə

PHP-nin native fuzz testing dəstəyi yoxdur. Java-nın JQF, Rust-ın `cargo-fuzz` alətləri var. Go 1.18-dən built-in dəstəklənir — xarici alət lazım deyil.

```php
// PHP — fuzz testing yoxdur
// Alternativ: PHP-Fuzzer (üçüncü tərəf, aktiv deyil)
// composer require nikic/php-fuzzer

// Adətən istifadə olunan: property-based testing
// composer require eris/eris

// Eris ilə property test (fuzz deyil, amma bənzər)
$this->forAll(
    Generator\string()
)->then(function (string $input) {
    $result = parseEmail($input);
    // assert...
});
```

```go
// Go — built-in fuzz, xarici alət lazım deyil
func FuzzParseEmail(f *testing.F) {
    f.Add("user@example.com")
    f.Add("invalid")

    f.Fuzz(func(t *testing.T, input string) {
        // Panic olmamalıdır
        _, _ = NewEmail(input)
    })
}

// go test -fuzz=FuzzParseEmail -fuzztime=60s
```

**Əsas fərqlər:**
- PHP: native fuzz yoxdur — üçüncü tərəf alət lazımdır; Go: `go test -fuzz` built-in
- PHP property-based testing (Eris): developer müəyyən etdiyi diapazon; Go fuzzer: avtomatik mutasiya — heç düşünmədiyiniz input-ları tapır
- Go corpus: tapılan crash-lar `testdata/fuzz/` qovluğuna yazılır, növbəti testlərdə avtomatik çalışır
- Java JQF / Rust cargo-fuzz: Go-ya bənzər built-in fuzzing dəstəyi

## Əlaqəli Mövzular

- [24-testing.md](24-testing.md) — Unit test əsasları
- [52-mocking-and-testify.md](52-mocking-and-testify.md) — Test yardımçı alətlər
- [84-testcontainers.md](84-testcontainers.md) — İnteqrasiya testlər
- [68-profiling-and-benchmarking.md](68-profiling-and-benchmarking.md) — Benchmark (fuzz ilə birlikdə)
