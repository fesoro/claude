# General Patterns

Bu qovluq domain-agnostic, amma backend developer üçün vacib olan cross-cutting mövzuları əhatə edir. Bunlar nə yalnız GoF pattern-ləridir, nə yalnız DDD taktikası — bunlar real layihələrdə hər yerdə işlənən, düzgün tətbiq edilmədikdə gizli texniki borca çevrilən mövzulardır.

---

## Mövzular

| № | Fayl | Mövzu | Səviyyə |
|---|------|-------|---------|
| 01 | [01-dto.md](01-dto.md) | DTO (Data Transfer Object) | Middle ⭐⭐ |
| 02 | [02-code-smells-refactoring.md](02-code-smells-refactoring.md) | Code Smells & Refactoring | Middle ⭐⭐ |
| 03 | [03-concurrency-patterns.md](03-concurrency-patterns.md) | Concurrency Patterns | Senior ⭐⭐⭐ |
| 04 | [04-multi-tenancy.md](04-multi-tenancy.md) | Multi-Tenancy Arxitekturası | Senior ⭐⭐⭐ |
| 05 | [05-technical-debt.md](05-technical-debt.md) | Technical Debt & Fitness Functions | Lead ⭐⭐⭐⭐ |
| 06 | [06-architecture-decision-records.md](06-architecture-decision-records.md) | Architecture Decision Records (ADR) | Lead ⭐⭐⭐⭐ |
| 07 | [07-cache-aside.md](07-cache-aside.md) | Cache-Aside Pattern | Middle ⭐⭐ |
| 08 | [08-caching-strategies.md](08-caching-strategies.md) | Caching Strategiyaları | Senior ⭐⭐⭐ |
| 09 | [09-feature-flags.md](09-feature-flags.md) | Feature Flags / Feature Toggles | Middle ⭐⭐ |

---

## Bu Fayllar Hansı Problemi Həll Edir?

### DTO (01)
**Problem**: Controller-dan service-ə `array` göndərirsən — typo, tip uyğunsuzluğu, `$data['emial']` kimi gizli bug-lar.
**Həll**: Typed DTO class-ları — IDE avtomatik tamamlama, refactoring aləti dəstəyi, compile-time-daxil aşkarlanan xətalar.

### Code Smells (02)
**Problem**: Kod işləyir amma hər dəyişiklik risklidiir, yeni developer dolaşır, test yazmaq çətindir.
**Həll**: Fowler catalog-dan smell-ləri tanı, Rector/PHPStan-la avtomatlaşdır, kiçik addımlarla refactor et.

### Concurrency Patterns (03)
**Problem**: İki request eyni vaxtda eyni inventory-ni alır, hər ikisi "stok var" görür, stok mənfi olur.
**Həll**: Pessimistic locking (`lockForUpdate()`), optimistic locking (version column), Redis atomic əməliyyatlar.

### Multi-Tenancy (04)
**Problem**: SaaS məhsulda bir tenant digərinin datasını görür — ya kod xətasından, ya da yanlış model seçimindən.
**Həll**: İzolyasiya modeli seç (row-level / schema / DB-per-tenant), Global Eloquent Scope, proper index-lər.

### Technical Debt (05)
**Problem**: "Sonra düzəldərik" deyilir, heç vaxt düzəldilmir, sistemi dəyişdirmək getdikcə mümkünsüzləşir.
**Həll**: TD quadrant-ını bil (deliberate vs inadvertent), intentional debt-i track et, fitness function-larla ölç.

### ADR (06)
**Problem**: "Niyə Redis, Kafka deyil?" — 2 il sonra heç kim bilmir, qərara kor itaat ya da lazımsız yenidən müzakirə.
**Həll**: Architecture Decision Record-ları git repo-da saxla, "niyə"yi alternativlərlə birgə sənədləndir.

### Cache-Aside (07)
**Problem**: Hər request DB-ə gedir, latency yüksəkdir; ya da cache invalidation unudulub, user köhnə data görür.
**Həll**: `Cache::remember()` = Cache-Aside pattern; write əməliyyatlarında `Cache::forget()` ilə invalidation.

### Caching Strategiyaları (08)
**Problem**: Cache stampede-dən DB çökür; Redis down olduqda tətbiq tamamilə dayanır; HTTP cache başlıqları yanlış.
**Həll**: Lock-based stampede protection, circuit breaker, Redis tag-ları, ETag/Last-Modified HTTP cache.

### Feature Flags (09)
**Problem**: Yeni feature deploy etmək = hamı görür ya da heç kim. Production-da bug tapılsa hotfix deploy gözlənilir.
**Həll**: Feature flag arxasında dark launch; kill switch ilə anında söndürmə; canary rollout ilə %1 → %100 açılış.

---

## Oxuma Yolları

### Middle Səviyyəsinə Giriş
Layerlər arasında data ötürülməsini düzgün qurmaq:
```
01 (DTO) → 07 (Cache-Aside) → 09 (Feature Flags) → 02 (Code Smells)
```

### Senior Keçid
Sistemin mürəkkəb hissələrini idarə etmək:
```
03 (Concurrency) → 08 (Caching Strategies) → 04 (Multi-Tenancy)
```

### Lead / Architect Hazırlığı
Texniki liderlik qərarları:
```
05 (Technical Debt) → 06 (ADR) → bütün qovluq
```

### Tam Oxuma Yolu (Easy → Hard)
```
01 → 07 → 09 → 02 → 08 → 03 → 04 → 05 → 06
```

---

## Əlaqəli Qovluqlar

- [laravel/](../laravel/) — Repository, Service Layer, Form Object, Presenter — bu pattern-lərlə birlikdə istifadə olunur
- [ddd/](../ddd/) — Value Objects DTO-dan fərqlənir; Domain Service vs Application Service
- [architecture/](../architecture/) — SOLID, Hexagonal, Clean Architecture — bu qovluğun kontext-i
- [integration/](../integration/) — CQRS, Outbox — caching + concurrency pattern-ləri ilə kəsişir
