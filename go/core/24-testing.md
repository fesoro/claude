# Testing (Middle)

## İcmal

Go-da test yazmaq üçün xarici framework lazım deyil — standart `testing` paketi güclü alətlər təqdim edir. Test faylları `_test.go` şəkilçisi ilə, test funksiyaları `Test` prefiksi ilə başlamalıdır. Go-nun ən güclü test pattern-i **table-driven tests** — bir funksiyanı onlarla fərqli input ilə yoxlamaq üçün idiomatic yanaşma. Benchmark, subtests (`t.Run`), `TestMain`, coverage — hamısı built-in.

## Niyə Vacibdir

Go-da test framework qurulumu sıfırdır — sadəcə `go test ./...` yetər. Table-driven testlər kodu daha az, əhatəni daha çox edir. CI/CD pipeline-da `go test -race` ilə race condition avtomatik tapılır. `go test -cover` ilə coverage görünür. Bu alətlər birlikdə məhsuldar test mühiti yaradır.

## Əsas Anlayışlar

- **Test fayl adı**: `math_test.go` — `_test.go` şəkilçisi mütləqdir
- **`testing.T`**: test funksiyasının parametri — xəta bildirmək, alt testlər açmaq üçün
- **`t.Errorf`**: test uğursuz edir, lakin davam edir
- **`t.Fatalf`**: test uğursuz edir və dərhal dayanır
- **`t.Run(name, func)`**: subtest — table-driven testlərin əsası
- **`testing.B`**: benchmark funksiyasının parametri; `b.N` dönmə sayı
- **`b.ResetTimer()`**: setup zamanını benchmark-dan çıxarmaq
- **`TestMain(m *testing.M)`**: bütün testlərdən əvvəl/sonra setup/teardown
- **`t.Helper()`**: köməkçi funksiyada çağırılır — xəta satır nömrəsi düzgün göstərilir
- **`t.Parallel()`**: testlər paralel işlədilir
- **`t.Cleanup(fn)`**: test bitəndə çalışdırılacaq funksiya
- **`testing.Short()`**: `-short` flag ilə uzun testlər atlanır

## Praktik Baxış

**Real istifadə ssenariləri:**
- Biznes məntiqinin table-driven test ilə bütün edge case-lərini əhatə etmək
- HTTP handler-ləri `httptest` paketi ilə test etmək
- Database-dən asılı kodu mock interface ilə test etmək
- CI/CD-də `go test -race -cover ./...` ilə keyfiyyət yoxlaması

**Trade-off-lar:**
- `testing` paketi assertion metodları yoxdur — `if result != expected { t.Errorf(...) }` yazılır; `testify` paketi bunu sadələşdirir
- Table-driven testlər uzun olsa da — bir dəfə yazılır, çox case əhatə edilir
- `t.Parallel()` testləri sürətləndirir, lakin paylaşılan state varsa race condition riski
- Mock interface-lər test üçün çox faydalı, lakin production kodu interface-ə uyğunlaşdırılmalıdır

**Ümumi səhvlər:**
- Test faylını `_test.go` ilə bitirməmək — `go test` tapmır
- `t.Fatal` ilə `t.Error` qarışdırmaq — `Fatal` daha ilk xətada dayanır, bəzən bütün uğursuzluqları görmək lazımdır
- Subtest-siz table-driven — `t.Run` olmadan ilk uğursuzluqdan sonra nə uğursuz olduğunu anlamaq çətindir
- `t.Helper()` çağırmamaq — xəta köməkçi funksiyada deyil, test funksiyasında göstərilməlidir
- Global state istifadəsi — paralel testlərdə race condition verir

## Nümunələr

### Nümunə 1: Sadə test — əsas pattern

```go
// Fayl: math.go
package math

func Topla(a, b int) int {
    return a + b
}

func Bol(a, b int) (int, error) {
    if b == 0 {
        return 0, fmt.Errorf("sıfıra bölmək olmaz")
    }
    return a / b, nil
}
```

```go
// Fayl: math_test.go
package math

import (
    "testing"
)

func TestTopla(t *testing.T) {
    netice := Topla(2, 3)
    gozlenen := 5

    if netice != gozlenen {
        t.Errorf("Topla(2, 3) = %d; gözlənən %d", netice, gozlenen)
    }
}

func TestBol_Normal(t *testing.T) {
    netice, err := Bol(10, 2)
    if err != nil {
        t.Fatalf("Gözlənilməz xəta: %v", err) // dərhal dayanır
    }
    if netice != 5 {
        t.Errorf("Bol(10, 2) = %d; gözlənən 5", netice)
    }
}

func TestBol_SifirBolme(t *testing.T) {
    _, err := Bol(10, 0)
    if err == nil {
        t.Error("Sıfıra bölmədə xəta gözlənilirdi")
    }
}
```

### Nümunə 2: Table-driven tests — Go-nun ən güclü test pattern-i

```go
// Fayl: validator_test.go
package validator

import (
    "testing"
)

func EmailYoxla(email string) bool {
    return len(email) > 3 && strings.Contains(email, "@")
}

func TestEmailYoxla(t *testing.T) {
    // Table — bütün test case-lər bir yerdə
    tests := []struct {
        ad       string  // test adı (t.Run üçün)
        email    string  // giriş
        gozlenen bool    // gözlənən nəticə
    }{
        {
            ad:       "düzgün email",
            email:    "orkhan@mail.az",
            gozlenen: true,
        },
        {
            ad:       "@ yoxdur",
            email:    "orkhanmail.az",
            gozlenen: false,
        },
        {
            ad:       "boş string",
            email:    "",
            gozlenen: false,
        },
        {
            ad:       "çox qısa",
            email:    "a@b",
            gozlenen: false,
        },
        {
            ad:       "Unicode email",
            email:    "orkhan@şəhər.az",
            gozlenen: true,
        },
    }

    for _, tt := range tests {
        // t.Run — hər case ayrı subtest
        // uğursuz olduqda: "TestEmailYoxla/düzgün_email" kimi göstərilir
        t.Run(tt.ad, func(t *testing.T) {
            netice := EmailYoxla(tt.email)
            if netice != tt.gozlenen {
                t.Errorf("EmailYoxla(%q) = %v; gözlənən %v",
                    tt.email, netice, tt.gozlenen)
            }
        })
    }
}
```

### Nümunə 3: Xəta hallarını test etmək — errors.Is ilə

```go
// Fayl: user_service_test.go
package service

import (
    "errors"
    "testing"
)

var ErrNotFound = errors.New("tapılmadı")
var ErrForbidden = errors.New("icazə yoxdur")

func UserGetir(id int) (*User, error) {
    switch id {
    case 0:
        return nil, ErrNotFound
    case 999:
        return nil, fmt.Errorf("icazə rədd edildi: %w", ErrForbidden)
    case 1:
        return &User{ID: 1, Ad: "Orkhan"}, nil
    }
    return nil, ErrNotFound
}

func TestUserGetir(t *testing.T) {
    tests := []struct {
        ad          string
        id          int
        wantUser    bool
        wantErr     error // nil = xəta gözlənmir
    }{
        {
            ad:       "mövcud istifadəçi",
            id:       1,
            wantUser: true,
            wantErr:  nil,
        },
        {
            ad:      "tapılmayan istifadəçi",
            id:      0,
            wantErr: ErrNotFound,
        },
        {
            ad:      "icazəsiz istifadəçi",
            id:      999,
            wantErr: ErrForbidden,
        },
    }

    for _, tt := range tests {
        t.Run(tt.ad, func(t *testing.T) {
            user, err := UserGetir(tt.id)

            if tt.wantErr != nil {
                if err == nil {
                    t.Fatalf("Xəta gözlənilirdi, lakin nil alındı")
                }
                if !errors.Is(err, tt.wantErr) {
                    t.Errorf("Xəta = %v; gözlənən %v", err, tt.wantErr)
                }
                return
            }

            if err != nil {
                t.Fatalf("Gözlənilməz xəta: %v", err)
            }
            if tt.wantUser && user == nil {
                t.Error("İstifadəçi nil olmamalıdır")
            }
        })
    }
}
```

### Nümunə 4: TestMain — setup və teardown

```go
// Fayl: integration_test.go
package service

import (
    "fmt"
    "os"
    "testing"
)

var testDB *sql.DB

func TestMain(m *testing.M) {
    // SETUP — bütün testlərdən əvvəl
    fmt.Println("Test DB qurulur...")
    var err error
    testDB, err = sql.Open("postgres", os.Getenv("TEST_DATABASE_URL"))
    if err != nil {
        fmt.Println("DB xətası:", err)
        os.Exit(1)
    }

    // Testləri işlət
    exitCode := m.Run()

    // TEARDOWN — bütün testlərdən sonra
    fmt.Println("Test DB bağlanır...")
    testDB.Close()

    os.Exit(exitCode)
}

func TestUserCreate(t *testing.T) {
    // testDB artıq hazırdır
    t.Cleanup(func() {
        // Bu test bitəndə — test data silinir
        testDB.Exec("DELETE FROM users WHERE email LIKE '%test%'")
    })

    // test məntiqi...
}
```

### Nümunə 5: Benchmark testi

```go
// Fayl: sort_test.go
package sort

import (
    "testing"
)

func BubbleSort(arr []int) []int { /* ... */ }
func QuickSort(arr []int) []int  { /* ... */ }

func BenchmarkBubbleSort(b *testing.B) {
    // b.N — go test özü müəyyən edir; stabil nəticə üçün kifayət qədər işlədir
    data := []int{5, 3, 8, 1, 9, 2, 7, 4, 6}

    b.ResetTimer() // setup zamanı benchmark-a daxil deyil
    for i := 0; i < b.N; i++ {
        tmp := make([]int, len(data))
        copy(tmp, data)
        BubbleSort(tmp)
    }
}

func BenchmarkQuickSort(b *testing.B) {
    data := []int{5, 3, 8, 1, 9, 2, 7, 4, 6}

    b.ResetTimer()
    for i := 0; i < b.N; i++ {
        tmp := make([]int, len(data))
        copy(tmp, data)
        QuickSort(tmp)
    }
}

// go test -bench=. -benchmem
// BenchmarkBubbleSort-8    5000000    300 ns/op    0 B/op    0 allocs/op
// BenchmarkQuickSort-8    10000000    150 ns/op    0 B/op    0 allocs/op
```

### Nümunə 6: Köməkçi funksiya + t.Helper()

```go
// Fayl: order_test.go
package order

import (
    "testing"
)

// Köməkçi funksiya — t.Helper() xəta satırını düzgün göstərir
func assertEqual(t *testing.T, got, want interface{}) {
    t.Helper() // Bu olmasa xəta bu funksiyada göstərilir, test funksiyasında deyil
    if got != want {
        t.Errorf("got %v; want %v", got, want)
    }
}

func assertNoError(t *testing.T, err error) {
    t.Helper()
    if err != nil {
        t.Fatalf("gözlənilməz xəta: %v", err)
    }
}

func assertError(t *testing.T, err error) {
    t.Helper()
    if err == nil {
        t.Fatal("xəta gözlənilirdi, lakin nil alındı")
    }
}

func TestOrderTotal(t *testing.T) {
    order := &Order{
        Items: []Item{
            {Price: 100, Qty: 2},
            {Price: 50, Qty: 3},
        },
    }

    total, err := order.Total()
    assertNoError(t, err)
    assertEqual(t, total, 350.0)
}
```

### Nümunə 7: Test əmrləri

```bash
# Bütün testlər
go test ./...

# Verbose çıxış — hər testin nəticəsi
go test -v ./...

# Yalnız konkret test
go test -run TestEmailYoxla ./...

# Subtest filteri
go test -run "TestEmailYoxla/düzgün" ./...

# Race condition yoxlaması (CI-da mütləq!)
go test -race ./...

# Coverage
go test -cover ./...
go test -coverprofile=coverage.out ./...
go tool cover -html=coverage.out  # brauzer-də görüntü

# Benchmark
go test -bench=. ./...
go test -bench=BenchmarkBubble -benchmem ./...

# Cache-siz işlət
go test -count=1 ./...

# Qısa testlər (-short flag ilə uzunları atla)
go test -short ./...

# Timeout
go test -timeout 30s ./...
```

## Praktik Tapşırıqlar

1. **Calculator table-driven test:** `Add`, `Sub`, `Mul`, `Div` funksiyaları üçün table-driven testlər yaz. `Div` sıfır bölmə xətasını test etsin. Hər funksiya üçün ən az 5 case.

2. **HTTP handler testi:** `httptest` paketi ilə GET `/users/:id` handler-ini test et. `200`, `404`, `400` status kodlarını ayrıca test case kimi yaz.

3. **Interface mock:** `EmailSender interface { Send(to, subject, body string) error }` yarat. `MockEmailSender` yazın — göndərilən email-ləri saxlasın. Test-də `mockSender.Calls` yoxla.

4. **Benchmark müqayisəsi:** String concatenation üçün `+` operatoru, `fmt.Sprintf`, `strings.Builder`, `bytes.Buffer` metodlarını benchmark et. Ən sürətlisini müəyyənləşdir.

5. **Integration test skippinq:** `TestMain`-də DB bağlantısı yoxdursa testləri `t.Skip("DB mövcud deyil")` ilə atla. `-short` flag ilə integration testlər atlanmalıdır.

## PHP ilə Müqayisə

| PHPUnit | Go testing |
|---------|------------|
| `class FooTest extends TestCase` | `func TestFoo(t *testing.T)` |
| `$this->assertEquals($expected, $actual)` | `if got != want { t.Errorf(...) }` |
| `@dataProvider` annotation | Table-driven: `[]struct{...}{}` + `t.Run` |
| `setUp()` / `tearDown()` | `TestMain` / `t.Cleanup` |
| `$this->getMockBuilder(...)` | Interface mock — manual və ya `testify/mock` |
| `vendor/bin/phpunit` | `go test ./...` (built-in) |
| PHPUnit-ü ayrıca install etmək lazımdır | Standart kitabxana |

## Əlaqəli Mövzular

- [18-error-handling.md](18-error-handling.md) — xəta hallarını test etmək, `errors.Is`
- [17-interfaces.md](17-interfaces.md) — mock yaratmaq üçün interface-lər
- [23-time-and-scope.md](23-time-and-scope.md) — `time.Now` inject edərək test yazmaq
- [04-httptest.md](../backend/04-httptest.md) — HTTP handler-lərin testinin ətraflı izahı
- [16-mocking-and-testify.md](../backend/16-mocking-and-testify.md) — `testify` paketi ilə daha güclü test
- [21-profiling-and-benchmarking.md](../advanced/21-profiling-and-benchmarking.md) — benchmark-ı daha dərin analiz
