# Feature Flags (Senior)

## İcmal

**Feature flag** (feature toggle) — kod deploy-undan müstəqil olaraq feature-ları açıb-bağlamağa imkan verən mexanizmdir. Go-da sadə boolean config-dən başlayaraq, GrowthBook, LaunchDarkly, Unleash kimi tam platforma inteqrasiyasına qədər dəyişir.

## Niyə Vacibdir

- **Trunk-based development**: hər feature flag arxasındadır, yarımçıq kod production-da heç vaxt aktiv olmur
- **Canary release**: 5% istifadəçi üçün yeni feature açılır, problem yoxdursa artırılır
- **A/B test**: fərqli implementation-ları ölçmək
- **Incident response**: tez-tez bug olan feature-ı deploy olmadan bağlamaq

## Əsas Anlayışlar

- **Static flag** — konfiq faylında ya env-dən; restart lazımdır; sadə, predictable
- **Dynamic flag** — real-time dəyişir; Unleash/LaunchDarkly; evaluation SDK tərəfindən
- **Targeting** — user ID, coğrafi bölgə, account plan əsasında flag qiyməti
- **Variant** — A/B test üçün çox dəyər (string, number, JSON)
- **Context** — flag evaluation konteksti (user, request, environment)
- **Stale flag** — artıq lazım olmayan flag; texniki borc; mütəmadi təmizlənməlidir

## Praktik Baxış

**Flag növlərinin müqayisəsi:**

| Növ | İstifadə | Dəyişiklik |
|-----|---------|-----------|
| Env var | Simple on/off | Restart lazım |
| Config file | Az dəyişən | Reload + restart |
| Database | Runtime toggle | Dərhal |
| Unleash/GrowthBook | Targeting, rollout | Dərhal + analytics |

**Trade-off-lar:**
- Çox flag texniki borc yaradır: "flag graveyard" — köhnə flag-ları sil
- Kombinatorial explosion: 10 flag = 1024 potensial vəziyyət — test çətinləşir
- Dynamic flag latency əlavə edir (SDK poll); local cache ilə azalır
- Remote service unavailable olduqda default dəyər vacibdir

## Nümunələr

### Nümunə 1: Sadə statik flags

```go
package flags

import (
    "os"
    "sync"
)

type Flags struct {
    mu    sync.RWMutex
    flags map[string]bool
}

var global = &Flags{
    flags: map[string]bool{
        "new_checkout_flow": false,
        "ai_recommendations": false,
        "bulk_import":        true,
    },
}

func IsEnabled(name string) bool {
    global.mu.RLock()
    defer global.mu.RUnlock()
    return global.flags[name]
}

func Set(name string, value bool) {
    global.mu.Lock()
    defer global.mu.Unlock()
    global.flags[name] = value
}

// Env override: FF_NEW_CHECKOUT_FLOW=true
func LoadFromEnv() {
    global.mu.Lock()
    defer global.mu.Unlock()
    for name := range global.flags {
        envKey := "FF_" + strings.ToUpper(strings.ReplaceAll(name, "-", "_"))
        if val := os.Getenv(envKey); val != "" {
            global.flags[name] = val == "true" || val == "1"
        }
    }
}

// İstifadə:
// if flags.IsEnabled("new_checkout_flow") {
//     return newCheckout(ctx, cart)
// }
// return legacyCheckout(ctx, cart)
```

### Nümunə 2: User targeting ilə flag

```go
type EvaluationContext struct {
    UserID  int
    Email   string
    Plan    string // "free", "pro", "enterprise"
    Country string
}

type FeatureFlag struct {
    Name        string
    DefaultValue bool
    Rules        []Rule
}

type Rule struct {
    Attribute string // "plan", "country", "user_id"
    Operator  string // "in", "not_in", "equals", "lt", "gt"
    Values    []string
    Value     bool // bu rule uyğun gəlsə nə qaytarsın
}

func (f *FeatureFlag) Evaluate(ctx EvaluationContext) bool {
    for _, rule := range f.Rules {
        if rule.matches(ctx) {
            return rule.Value
        }
    }
    return f.DefaultValue
}

// Canary: 10% user üçün açıq
type PercentageFlag struct {
    Name       string
    Percentage int // 0-100
}

func (f *PercentageFlag) Evaluate(userID int) bool {
    // Deterministik: eyni user həmişə eyni nəticəni alır
    hash := fnv32a(fmt.Sprintf("%s:%d", f.Name, userID))
    return int(hash%100) < f.Percentage
}

func fnv32a(s string) uint32 {
    h := fnv.New32a()
    h.Write([]byte(s))
    return h.Sum32()
}
```

### Nümunə 3: HTTP middleware ilə flag injection

```go
type contextKey string

const flagsKey contextKey = "feature_flags"

// Flags-ı context-ə yüklə (middleware)
func FlagMiddleware(fm *FlagManager) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            userCtx := extractUserContext(r) // token-dən user info
            evalCtx := EvaluationContext{
                UserID:  userCtx.UserID,
                Plan:    userCtx.Plan,
                Country: r.Header.Get("CF-IPCountry"),
            }
            ctx := context.WithValue(r.Context(), flagsKey, fm.EvaluateAll(evalCtx))
            next.ServeHTTP(w, r.WithContext(ctx))
        })
    }
}

func FlagEnabled(ctx context.Context, name string) bool {
    flags, ok := ctx.Value(flagsKey).(map[string]bool)
    if !ok {
        return false
    }
    return flags[name]
}

// Handler-də:
func checkoutHandler(w http.ResponseWriter, r *http.Request) {
    if FlagEnabled(r.Context(), "new_checkout_flow") {
        newCheckout(w, r)
        return
    }
    legacyCheckout(w, r)
}
```

### Nümunə 4: GrowthBook inteqrasiyası

```go
import (
    "github.com/growthbook/growthbook-golang"
)

func initGrowthBook(apiKey string) *growthbook.GrowthBook {
    gb := growthbook.New(context.Background(), growthbook.Options{
        APIHost: "https://cdn.growthbook.io",
        ClientKey: apiKey,
    })

    // Feature dəyərini al
    // ctx ilə user attributes ötür
    attrs := growthbook.Attributes{
        "id":   "user-123",
        "plan": "pro",
    }

    gb.WithAttributes(attrs)
    return gb
}

func isFeatureEnabled(gb *growthbook.GrowthBook, featureKey string) bool {
    result := gb.Feature(featureKey)
    return result.On
}
```

### Nümunə 5: Flag-lar üçün test yardımçısı

```go
// Test-lərdə flag-ları override etmək
func WithFlags(t *testing.T, flags map[string]bool) {
    t.Helper()
    original := copyFlags()
    for name, val := range flags {
        Set(name, val)
    }
    t.Cleanup(func() { restoreFlags(original) })
}

// Test:
func TestCheckout_NewFlow(t *testing.T) {
    WithFlags(t, map[string]bool{"new_checkout_flow": true})
    // test kodu...
}

func TestCheckout_LegacyFlow(t *testing.T) {
    WithFlags(t, map[string]bool{"new_checkout_flow": false})
    // test kodu...
}
```

## Praktik Tapşırıqlar

1. **Env-based flags:** `os.Getenv` ilə `FF_*` env var-larından flag-ları yüklə; type-safe wrapper yaz
2. **Percentage rollout:** 20% istifadəçi üçün yeni endpoint activate et; user ID-yə görə deterministik
3. **Database flags:** `feature_flags` cədvəlindən flag-ları yüklə; 30 saniyəlik TTL cache əlavə et
4. **A/B test logging:** Flag variation-ı `variant` attribute ilə structured log-a yaz; analiz et

## PHP ilə Müqayisə

```
PHP/Laravel                      →  Go
────────────────────────────────────────────
pennant (Laravel 11+)            →  custom FlagManager
Feature::active('flag')          →  flags.IsEnabled("flag")
Feature::for($user)->active()    →  flag.Evaluate(userCtx)
```

Laravel Pennant feature flags üçün convenient-dir. Go-da ağırlıqlı olaraq manual implementation ya da Unleash/GrowthBook SDK istifadə olunur.

## Əlaqəli Mövzular

- [../backend/07-environment-and-config](../backend/07-environment-and-config.md) — env-based flag konfiqurasiyası
- [../core/28-context](../core/28-context.md) — flag context propagation
- [../backend/03-middleware-and-routing](../backend/03-middleware-and-routing.md) — flag middleware
- [43-load-testing](43-load-testing.md) — flag altında performance testi
- [41-adr-architecture-decision-records](41-adr-architecture-decision-records.md) — flag strategy qərarları
