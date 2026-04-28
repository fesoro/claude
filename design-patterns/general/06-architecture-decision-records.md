# Architecture Decision Records — ADR (Lead ⭐⭐⭐⭐)

## İcmal
Architecture Decision Record (ADR) — bir arxitektura qərarını, niyə verildiyi konteksti, alternativləri və nəticələri qeyd edən qısa sənəddir. "Niyə bunu Redis-lə etdik, Kafka ilə yox?" sualına 6 ay sonra cavab vermək üçün.

## Niyə Vacibdir
Böyük layihələrdə qərarlar veriliir: hangi message broker, monolith vs microservices, event sourcing tətbiq edilsin mi? Bu qərarları verən mühəndis işdən çıxır, kontekst itir. Yeni developer ya eyni müzakirəni yenidən başladır, ya da qərara kor itaat edir. ADR-lər "niyə"ni kodun yanında saxlayır, qərarı deyil.

## Əsas Anlayışlar

### Nygard Formatı (Klassik)
```markdown
# ADR-001: Message Broker olaraq RabbitMQ Seçimi

## Status
Accepted

## Context
Sipariş sistemi event-driven arxitekturaya keçir. Servislər arasında asenkron kommunikasiya lazımdır. 
Komandanın Kafka təcrübəsi yoxdur. Gündəlik 50K sifariş proqnozlanır.

## Decision
Kafka əvəzinə RabbitMQ istifadə edirik.

## Consequences
**Müsbət:**
- Komanda RabbitMQ bilir, onboarding sürətli olacaq
- Gündəlik 50K mesaj üçün kifayət edir
- Laravel Horizon inteqrasiyası hazırdır

**Mənfi:**
- Event replay nativas deyil (retention yoxdur)
- 10M+ mesaj/gün keçəndə Kafka-ya miqrasiya lazım olacaq
- Dead letter queue əl ilə idarə edilməlidir
```

### MADR Formatı (Daha Strukturlu)
```markdown
# ADR-007: Multi-tenancy üçün Row-Level Security

## Status
Accepted (2026-03-15) | Superseded by ADR-012

## Context and Problem Statement
SaaS platforması çox müştəriyə xidmət edir. Tenant data isolation lazımdır.
Shared database istifadə edirik. Silinmə xətası kritikdir.

## Decision Drivers
* Tenant data leak-i mütləq qarşısı alınmalıdır
* Query complexity artmamalıdır
* Mövcud Eloquent scope-ları ilə uyğun olmalıdır

## Considered Options
* Option A: Ayrı database hər tenant üçün
* Option B: Ayrı schema (PostgreSQL schema)
* Option C: Shared table + Row-Level Security (PostgreSQL RLS)
* Option D: Global Eloquent scope ilə `tenant_id` filter

## Decision Outcome
Option D seçildi: Global scope + `tenant_id` FK.

**Səbəb:** PostgreSQL RLS (C) miqrasiya tooling-i mürəkkəbləşdirir; 
ayrı database (A) devops yükünü artırır; global scope mövcud Laravel 
infrastrukturumuza uyğundur.

## Pros and Cons of the Options

### Option A — Ayrı Database
✓ Tam izolasiya, asan backup
✗ 100+ tenant üçün 100+ migration, yüksək devops yükü

### Option D — Global Scope  
✓ Sadə, Eloquent-native, sürətli inkişaf
✗ Scope unutulduqda (Model::withoutGlobalScopes()) leak riski
```

### Status Dövranı
```
Proposed → Accepted → Deprecated → Superseded by ADR-XXX
                   → Rejected
```

## ADR Saxlama Yeri

```
docs/
└── decisions/
    ├── 001-message-broker.md
    ├── 002-caching-strategy.md
    ├── 003-deployment-platform.md
    └── README.md  ← indeks

# Alternativ — kodun yanında
app/
├── Services/
│   └── Payment/
│       └── decisions/
│           └── 001-payment-gateway-choice.md
```

### adr-tools CLI
```bash
npm install -g adr-tools
# ya da
pip install adr-tools

adr init docs/decisions
adr new "PostgreSQL seçimi"          # 0001-postgresql-secimi.md yaradır
adr new -s 3 "Redis Cluster keçidi" # 3-cü ADR-i supersede edir
adr list
adr generate toc                    # README-ə cədvəl yarat
```

## Nə Vaxt ADR Yazılmalı?

### Yaz:
- Texnologiya seçimi (database, message broker, framework)
- Arxitektura pattern dəyişikliyi (monolith → modular, sync → async)
- Geri dönüşü çətin olan qərar (data model, API contract)
- Uzun müzakirə nəticəsindəki razılaşma
- Alternativlərin rədd edilməsi

### Yazma:
- CRUD əməliyyatları
- Routine implementasiya detalları
- Kod stil qaydaları (bunlar linter-dədir)
- Hər PR — çox dərəceli olur

## Code Annotation

ADR-i kod ilə əlaqələndirmək:
```php
/**
 * @see docs/decisions/007-multi-tenancy-row-level-security.md
 * 
 * Global scope tenant_id-ni avtomatik filtrlər.
 * withoutGlobalScopes() yalnız admin kontekstdə istifadə olunmalıdır.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('tenant_id', tenant()->id);
    }
}
```

```php
// ADR-003: Event sourcing sadəcə Order aggregate-i üçün — 2026-01-10
// Reasoning: docs/decisions/003-partial-event-sourcing.md
class OrderAggregate extends AggregateRoot
{
    // ...
}
```

## ADR Review Prosesi

```markdown
## ADR Lifecycle

1. **Draft**: Mühəndis kontekst + seçimlər yazır
2. **Review**: Tech lead + əlaqəli mühəndislər şərh edir (PR kimi)  
3. **Decision**: Team meeting-də qərar verilir
4. **Accepted/Rejected**: Status yenilənir
5. **Superseded**: Yeni qərar köhnəni əvəz edəndə link verilir
```

## Anti-Pattern Nə Zaman Olur?

**1. Heç oxunmayan ADR-lər yazmaq**
ADR-ləri kimsə oxumursa, onlar sadəcə bürokratiyadır. Əlamətlər: ADR yenilənmir, köhnə qərar hələ "Accepted" durur amma sistem çoxdan dəyişib, yeni developer ADR-lərin varlığını bilmir. ADR-lər kodun yanında olmalıdır (git repo-da), PR template-ə çekilib, review prosesinin bir hissəsidir. Bürokratiya → heç oxunmaz. Integration → team-in düşüncəsinin bir hissəsi olur.

**2. "Bu qərara gəldik" — niyə yox**
Yalnız qərarı yazıb konteksti, alternativləri, "niyə"ni yazmamaq — bu sadəcə JIRA ticket-dir, ADR deyil. 2 il sonra kim baxacaq: "PostgreSQL seçdik" — OK, amma niyə MySQL deyil? niyə MongoDB deyil? o vaxt hansı constraint-lər var idi? Alternativləri niyə rədd etdiniz? Qərar deyil, **qərar verən düşüncə prosesi** dəyərlidir.

```markdown
# YANLIŞ ADR
## Decision
Redis istifadə edirik.

# DOĞRU ADR
## Context
Session storage üçün həll lazımdır. 50K concurrent user.
Mövcud DB-yə əlavə yük verməmək lazımdır.

## Considered Options
- Database sessions: mövcud MySQL-ə yük qoşulur, horizontal scale çətin
- File sessions: multi-server mühitdə sticky session lazımdır
- Redis: dedicated, fast, TTL native, horizontal scale asan

## Decision
Redis seçildi.

## Consequences
Negative: Əlavə infrastructure (Redis cluster), ops yükü artır.
Positive: Sub-millisecond read, native TTL, session izolasiyası.
```

---

## Praktik Baxış

### ADR Anti-pattern-lər
- **Post-hoc yazılmış ADR**: Qərar artıq həyata keçirilmiş, ADR "justification" kimi yazılır → context itir, seçimlər saxta görünür
- **Çox dəqiq**: Kod səviyyəsindəki qərarlar ADR-ə girər → yüzlərlə ADR, hamısı oxunmaz olur
- **Status yenilənmir**: ADR "Accepted" qalır, amma 2 il sonra supersede olunub → ADR-lərə güvən azalır
- **Yalnız "nə" yazılır, "niyə" yox**: "PostgreSQL istifadə edirik" → kontekstsiz, dəyərsiz

### PHP Layihə Nümunələri
```markdown
# ADR-012: Laravel Octane Tətbiqi

## Status
Proposed

## Context
High-traffic endpoint-lər (search, feed) hər request-də FPM prosesi başladır.
P95 latency 800ms-dir. Target: 200ms.

## Options
* Option A: PHP-FPM + OPcache optimize
* Option B: Laravel Octane + Swoole
* Option C: Laravel Octane + RoadRunner
* Option D: Go microservice bu endpoint-lər üçün

## Decision Outcome
Option C — RoadRunner seçildi.

Səbəb: Swoole extension-ı production-da debug çətin; 
RoadRunner Go binary-dir, PHP extension lazım deyil.
Octane tətbiqi üçün kod dəyişiklikləri (singleton resetting) sənədlənib.
```

### Trade-off-lar
- **ADR vs Wiki**: Wiki dəyişir, tarixçə itirir. ADR — git history ilə versioned, PR-larla review olunur.
- **ADR vs JIRA/Linear**: Ticket "niyə"yi saxlayır amma axtarmaq çətin, linki qurur.
- **Lightweight vs Heavyweight**: MADR (5 bölmə) vs Nygard (4 bölmə) vs RFC (10+ bölmə). Komandanın ölçüsünə görə seç.

## Praktik Tapşırıqlar

1. Mövcud layihəndə 3 arxitektura qərarını identifikasiya et, Nygard formatında ADR yaz
2. `docs/decisions/README.md` faylı yarat — bütün ADR-lərin indeksi ilə
3. PR template-ə ADR checkbox əlavə et: "Bu dəyişiklik arxitektura qərarı tələb edir mi?"
4. Supersede nümunəsi: RabbitMQ → Kafka miqrasiyası üçün ADR yaz, köhnəni link et
5. CI-da ADR lint: status field-inin doldurulub-doldurilmadığını yoxlayan bash script

## Əlaqəli Mövzular
- [Technical Debt](05-technical-debt.md) — ADR qərarları izlər, TD isə nəticəsini izlər
- [Modular Monolith](../architecture/08-modular-monolith.md) — modul sərhədlərini ADR ilə sənədləşdirmək
- [Microservices vs Modular Monolith](../architecture/09-microservices-vs-modular-monolith.md) — ən kritik qərar, mütləq ADR tələb edir
- [Hexagonal Architecture](../architecture/05-hexagonal-architecture.md) — port/adapter qərarları ADR-ə uyğundur
- [Clean Architecture](../architecture/12-clean-architecture.md) — layer dependency qərarları üçün ADR
