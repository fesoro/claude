# Time paket və Scope qaydaları (Middle)

## İcmal

Bu fəsil iki mühüm mövzunu əhatə edir. Birincisi, Go-nun `time` paketi: `time.Time`, `time.Duration`, Unix timestamp, timezone idarəetməsi, tarix formatlaması. İkincisi, Go-da dəyişən görünürlüyü (scope): paket, funksiya, blok səviyyəsi, shadowing tuzağı. Hər iki mövzu gündəlik backend development-da daima istifadə olunur.

## Niyə Vacibdir

Tarix-vaxt səhvləri — billing, scheduling, audit log, token expiry kimi kritik kontekstlərdə ciddi data xətalara səbəb olur. Timezone idarəetməsi düzgün aparılmasa, UTC əvəzinə lokal vaxt saxlanılsa, müştərilərin məlumatları qarışır. Scope isə daha fundamental: shadowing tuzağı (`:=` əvəzinə `=` unudulmaq) xəta idarəetməsini gizli şəkildə sındırır.

## Əsas Anlayışlar

### time paketi:
- **`time.Now()`** — cari vaxt (`time.Time` tipi)
- **`time.Time`** — tam tarix-vaxt (timezone-la birlikdə)
- **`time.Duration`** — vaxt aralığı; nanosaniyə əsasında (`time.Second = 1e9 ns`)
- **`Unix()`** — 1970-01-01 UTC-dən keçən saniyələr (Unix timestamp)
- **`UnixMilli()`, `UnixMicro()`, `UnixNano()`** — müxtəlif dəqiqlik
- **`time.Unix(sec, nano)`** — timestamp-dən `time.Time`-a
- **Go tarix formatı** — `"2006-01-02 15:04:05"` — reference time (yadda saxlayın: `01/02 03:04:05 2006 -0700`)
- **`t.Format(layout)`** — string-ə çevirmə
- **`time.Parse(layout, str)`** — string-dən `time.Time`-a
- **`t1.Sub(t2)`** — iki vaxt arası `Duration`
- **`t.Add(d)`** — `Duration` əlavə etmək
- **`time.LoadLocation("Asia/Baku")`** — timezone yükləmə
- **`t.UTC()`** — UTC-yə çevirmə

### Scope qaydaları:
- **Paket scope** — `var x = 1` (funksiya xaricindəki dəyişkən) — bütün fayllardan görünür
- **Funksiya scope** — funksiya daxilindəki dəyişkən — yalnız orada
- **Blok scope** — `{}` içindəki dəyişkən — blok bitəndə məhv olur
- **Shadowing** — daxili blokda eyni adlı yeni dəyişkən — xarici gizlənir
- **`:=` vs `=`** — `:=` yeni dəyişkən yaradır; `=` mövcud dəyişkənə mənimsədir
- **`if` scope** — `if x := val; x > 0 {}` — `x` yalnız `if` içindədir
- **`for` scope** — dövrə dəyişkəni yalnız dövrədə görünür

## Praktik Baxış

**Real istifadə ssenariləri:**
- JWT token expiry: `time.Now().Add(24 * time.Hour)` → Unix timestamp
- Created/updated timestamps: `time.Now().UTC()` — həmişə UTC-də saxla
- Rate limiting: `time.Since(lastRequest) < time.Minute`
- Cron jobs: `time.AfterFunc(interval, fn)` ilə gecikdirmə
- SLA hesablaması: `deadline.Sub(time.Now())`

**Trade-off-lar:**
- `time.Now()` test edilə bilmir — `time.Now` funksiyasını inject edin (dependency injection)
- Timezone məlumatı olmayan timestamp — daima UTC ilə saxlayın
- `time.Sleep` test əhatəsini çətinləşdirir — abstraksiya yazın
- String format əvəzinə `time.Time` saxlamaq daha type-safe

**Ümumi səhvlər:**
- Go tarix formatını başqa dillərəki kimi yazmaq (`YYYY-MM-DD` əvəzinə `2006-01-02`)
- Lokal timezone-da vaxt saxlamaq — UTC istifadə edin, display-da çevirin
- Shadowing: `if err, ok := ...; err != nil { err := ... }` — daxili `err` xarici-ni gizlədir
- `:=` əvəzinə `=` unudaraq loop dəyişkəninin paylaşılması (Go 1.22-dən əvvəl)

## Nümunələr

### Nümunə 1: Cari vaxt, timestamp, format

```go
package main

import (
    "fmt"
    "time"
)

func main() {
    indi := time.Now()
    fmt.Println("Cari vaxt:", indi)

    // Unix timestamp — müxtəlif dəqiqlik
    fmt.Println("Unix (saniyə):     ", indi.Unix())
    fmt.Println("Unix (millisaniyə):", indi.UnixMilli())
    fmt.Println("Unix (nanosaniyə): ", indi.UnixNano())

    // Formatlama — Go-nun özünəməxsus format: 2006-01-02 15:04:05
    // Mnemonik: Mon Jan 2 15:04:05 MST 2006 (1 2 3 4 5 6 7)
    fmt.Println("Tarix:   ", indi.Format("2006-01-02"))
    fmt.Println("Vaxt:    ", indi.Format("15:04:05"))
    fmt.Println("Tam:     ", indi.Format("2006-01-02 15:04:05"))
    fmt.Println("ISO 8601:", indi.Format(time.RFC3339))
    fmt.Println("HTTP:    ", indi.Format(time.RFC1123Z))

    // Timestamp-dən time.Time-a
    t1 := time.Unix(1700000000, 0)
    fmt.Println("1700000000 →", t1.Format("2006-01-02 15:04:05"))

    t2 := time.UnixMilli(1700000000000)
    fmt.Println("1700000000000 ms →", t2.Format("2006-01-02 15:04:05"))
}
```

### Nümunə 2: Duration — vaxt aralığı

```go
package main

import (
    "fmt"
    "time"
)

func main() {
    // Duration sabitləri
    fmt.Println("1 saniyə:  ", time.Second)       // 1s
    fmt.Println("1 dəqiqə:  ", time.Minute)       // 1m0s
    fmt.Println("1 saat:    ", time.Hour)          // 1h0m0s
    fmt.Println("24 saat:   ", 24*time.Hour)       // 24h0m0s

    // Vaxt əlavə etmək
    indi := time.Now()
    birSaatSonra := indi.Add(time.Hour)
    birGunEvvel := indi.Add(-24 * time.Hour)
    fmt.Println("Bir saat sonra:", birSaatSonra.Format("15:04:05"))
    fmt.Println("Dünən:", birGunEvvel.Format("2006-01-02"))

    // İki tarix arası fərq
    baslangic := time.Date(2024, 1, 1, 0, 0, 0, 0, time.UTC)
    son := time.Date(2024, 12, 31, 0, 0, 0, 0, time.UTC)
    ferq := son.Sub(baslangic)

    fmt.Printf("Fərq: %.0f gün\n", ferq.Hours()/24)
    fmt.Println("Fərq saniyə:", int(ferq.Seconds()))

    // time.Since / time.Until
    deadline := time.Now().Add(5 * time.Minute)
    fmt.Println("Deadline-ə qədər:", time.Until(deadline).Round(time.Second))

    start := time.Now()
    time.Sleep(10 * time.Millisecond)
    fmt.Printf("Keçdi: %v\n", time.Since(start))

    // Duration-dan komponentlər
    d := 2*time.Hour + 35*time.Minute + 42*time.Second
    saat := int(d.Hours())
    deqiqe := int(d.Minutes()) % 60
    saniye := int(d.Seconds()) % 60
    fmt.Printf("%d:%02d:%02d\n", saat, deqiqe, saniye) // 2:35:42
}
```

### Nümunə 3: Tarix yaratma, müqayisə, parsing

```go
package main

import (
    "fmt"
    "time"
)

func main() {
    // Konkret tarix yaratmaq
    dogumGunu := time.Date(1999, time.May, 15, 10, 30, 0, 0, time.UTC)
    fmt.Println("Doğum günü:", dogumGunu.Format("2006-01-02"))
    fmt.Println("Timestamp:", dogumGunu.Unix())

    // Komponentlər
    indi := time.Now()
    fmt.Printf("İl: %d, Ay: %s (%d), Gün: %d\n",
        indi.Year(), indi.Month(), int(indi.Month()), indi.Day())
    fmt.Printf("Saat: %d, Dəqiqə: %d, Saniyə: %d\n",
        indi.Hour(), indi.Minute(), indi.Second())
    fmt.Println("Həftənin günü:", indi.Weekday())

    // String-dən parsing
    layout := "2006-01-02"
    tarix, err := time.Parse(layout, "2024-03-15")
    if err != nil {
        fmt.Println("Xəta:", err)
    } else {
        fmt.Println("Parse edildi:", tarix.Format("02 January 2006"))
    }

    // RFC3339 parsing (API-lərdən gəlir)
    iso, _ := time.Parse(time.RFC3339, "2024-03-15T10:30:00Z")
    fmt.Println("ISO:", iso.Format("2006-01-02 15:04:05"))

    // Müqayisə
    t1 := time.Date(2024, 1, 1, 0, 0, 0, 0, time.UTC)
    t2 := time.Date(2024, 6, 1, 0, 0, 0, 0, time.UTC)
    fmt.Println("t1 əvvəl?", t1.Before(t2))  // true
    fmt.Println("t2 sonra?", t2.After(t1))   // true
    fmt.Println("Bərabər?", t1.Equal(t2))    // false
}
```

### Nümunə 4: Timezone idarəetməsi

```go
package main

import (
    "fmt"
    "time"
)

func main() {
    // Həmişə UTC-də saxla — display-da çevir
    utc := time.Now().UTC()
    fmt.Println("UTC:", utc.Format("2006-01-02 15:04:05 MST"))

    // Timezone yükləmə
    baku, err := time.LoadLocation("Asia/Baku")
    if err != nil {
        fmt.Println("Timezone xətası:", err)
        return
    }
    bakuVaxt := utc.In(baku)
    fmt.Println("Bakı:", bakuVaxt.Format("2006-01-02 15:04:05 MST"))

    istanbul, _ := time.LoadLocation("Europe/Istanbul")
    fmt.Println("İstanbul:", utc.In(istanbul).Format("2006-01-02 15:04:05 MST"))

    london, _ := time.LoadLocation("Europe/London")
    fmt.Println("London:", utc.In(london).Format("2006-01-02 15:04:05 MST"))

    // Sabit offset timezone
    baku4 := time.FixedZone("UTC+4", 4*60*60)
    fmt.Println("UTC+4:", utc.In(baku4).Format("2006-01-02 15:04:05 MST"))

    // Token expiry nümunəsi
    tokenExpiry := time.Now().UTC().Add(24 * time.Hour)
    fmt.Println("\nToken bitəcək (UTC):", tokenExpiry.Format(time.RFC3339))
    fmt.Println("Token bitdi?", time.Now().After(tokenExpiry)) // false
}
```

### Nümunə 5: Scope qaydaları — bütün növlər

```go
package main

import "fmt"

// Paket scope — bütün fayllardan görünür
var paketDeyer = "paketi hərkəs görür"

func main() {
    // =========================================
    // FUNKSIYA SCOPE
    // =========================================
    lokal := "yalnız bu funksiyada"
    fmt.Println("Lokal:", lokal)

    // =========================================
    // BLOK SCOPE
    // =========================================
    {
        blokDeyer := "yalnız bu blokda"
        fmt.Println("Blok içi:", blokDeyer)
    }
    // fmt.Println(blokDeyer) // COMPILE XƏTASI — blok xaricindədir

    // =========================================
    // IF SCOPE
    // =========================================
    // İnitializer — x yalnız if bloku içindədir
    if x := hesabla(); x > 10 {
        fmt.Println("if içi x:", x)
    }
    // fmt.Println(x) // COMPILE XƏTASI

    // =========================================
    // FOR SCOPE
    // =========================================
    for i := 0; i < 3; i++ {
        // i yalnız dövrə içindədir
        _ = i
    }
    // fmt.Println(i) // COMPILE XƏTASI

    // =========================================
    // SHADOWING — ən çox tuzaq
    // =========================================
    err := "xarici xəta"
    fmt.Println("Xarici:", err)

    {
        err := "daxili xəta" // YENİ dəyişkən — xaricini gizlədir
        fmt.Println("Daxili:", err)
    }
    fmt.Println("Xarici yenə:", err) // "xarici xəta" — dəyişmədi!

    // XƏTALI pattern — error idarəetməsini gizlə
    var xeta error
    if true {
        xeta := fmt.Errorf("yeni xəta") // := ilə yeni dəyişkən!
        _ = xeta                         // xarici xeta-ya dəymir
    }
    fmt.Println("xeta nil mi?", xeta == nil) // true — gözlənilməz!

    // DUZGUN yol — = ilə mənimsət
    var xeta2 error
    if true {
        xeta2 = fmt.Errorf("yeni xəta") // = ilə xarici dəyişkənə
    }
    fmt.Println("xeta2:", xeta2) // yeni xəta — düzgün!
}

func hesabla() int { return 42 }
```

### Nümunə 6: Testable time — dependency injection

```go
package main

import (
    "fmt"
    "time"
)

// time.Now() test edilə bilmir — funksiya kimi inject et
type Clock func() time.Time

type TokenService struct {
    now      Clock
    duration time.Duration
}

func NewTokenService(now Clock, duration time.Duration) *TokenService {
    return &TokenService{now: now, duration: duration}
}

func (ts *TokenService) IsExpired(createdAt time.Time) bool {
    return ts.now().After(createdAt.Add(ts.duration))
}

func (ts *TokenService) GenerateExpiry() time.Time {
    return ts.now().UTC().Add(ts.duration)
}

func main() {
    // Production
    svc := NewTokenService(time.Now, 24*time.Hour)
    expiry := svc.GenerateExpiry()
    fmt.Println("Token bitəcək:", expiry.Format(time.RFC3339))

    // Test üçün — sabit vaxt
    fixedTime := time.Date(2024, 1, 1, 12, 0, 0, 0, time.UTC)
    testSvc := NewTokenService(func() time.Time { return fixedTime }, time.Hour)

    oldToken := fixedTime.Add(-2 * time.Hour) // 2 saat əvvəl
    fmt.Println("Köhnə token bitdi?", testSvc.IsExpired(oldToken)) // true

    newToken := fixedTime.Add(-30 * time.Minute) // 30 dəqiqə əvvəl
    fmt.Println("Yeni token bitdi?", testSvc.IsExpired(newToken)) // false
}
```

## Praktik Tapşırıqlar

1. **Age calculator:** Doğum tarixini string formatda (`"1999-05-15"`) qəbul edən, yaşı tam il kimi hesablayan funksiya yaz. Edge case: doğum günü hələ keçməyibsə `n-1` yaş.

2. **Business hours checker:** `IsBusinessHours(t time.Time) bool` funksiyası yaz. Bakı timezone-da həftəiçi 09:00-18:00 arası `true` qaytarsın.

3. **Rate limiter:** `RateLimiter` struct-ı yarat — müştəri başına son request vaxtını saxlasın. `Allow(clientID string) bool` metodu: son 1 dəqiqədə 10-dan çox request varsa `false`.

4. **Scope bug tapma:** Aşağıdakı kod niyə düzgün işləmir? Düzəldin:
   ```go
   var result string
   var err error
   if condition {
       result, err := fetchData()
       log.Println(result)
   }
   return result, err
   ```

5. **Audit log timestamp:** `AuditLog` struct-ı yarat (action, userID, createdAt). `createdAt` həmişə UTC olsun. 30 gündən köhnə olan log-ları filter edən funksiya yaz.

## PHP ilə Müqayisə

| PHP | Go |
|-----|-----|
| `date('Y-m-d')` | `time.Now().Format("2006-01-02")` |
| `time()` — Unix timestamp | `time.Now().Unix()` |
| `new DateTime('+1 hour')` | `time.Now().Add(time.Hour)` |
| `$dt->diff($dt2)` | `t1.Sub(t2)` → `Duration` |
| `DateTimeZone('Asia/Baku')` | `time.LoadLocation("Asia/Baku")` |
| PHP-də scope daha geniş (`global` keyword) | Go-da strict block scope |
| `date('Y-m-d H:i:s')` formatı | `"2006-01-02 15:04:05"` reference time formatı |

## Əlaqəli Mövzular

- [18-error-handling.md](18-error-handling.md) — shadowing xətası error idarəetməsini pozur
- [24-testing.md](24-testing.md) — `time.Now()` inject edərək test yazmaq
- [27-goroutines-and-channels.md](27-goroutines-and-channels.md) — `time.After`, `time.Tick` goroutine-larda
- [28-context.md](28-context.md) — `context.WithDeadline`, `context.WithTimeout`
- [01-http-server.md](../backend/01-http-server.md) — JWT token expiry, request timeout
- [17-graceful-shutdown.md](../backend/17-graceful-shutdown.md) — shutdown timeout idarəetməsi
