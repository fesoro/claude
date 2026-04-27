# Architecture Decision Records (Middle)

## İcmal

Architecture Decision Record (ADR) — bir komandanın vacib texniki qərar qəbul etməsi zamanı niyə həmin qərara gəldiyini qeyd etdiyi qısa sənədir. Nə seçildi yox, **niyə** seçildi — bu fərq 6 ay sonra çox vacib olur.

## Niyə Vacibdir

Komandalar hər gün qərarlar verir:
- "Niyə PostgreSQL seçdik, MongoDB deyil?"
- "Niyə gRPC, REST deyil?"
- "Niyə CQRS tətbiq etdik?"

6 ay sonra yeni developer gələndə heç kim xatırlamır. ADR bu xatirəni qoruyur.

**PHP/Laravel paraleli**: Laravel-də `php artisan make:migration` — hər dəyişiklik izlənir. ADR isə arxitektura dəyişiklikləri üçün eyni prinsipi tətbiq edir.

## Əsas Anlayışlar

### ADR formatı (Michael Nygard)

```
# ADR-NNN: Qısa başlıq

## Status
Proposed | Accepted | Deprecated | Superseded by ADR-XXX

## Context
Problemi izah et. Nə baş verir? Niyə qərar lazım idi?

## Decision
Nə seçildi? Aktiv cümlə ilə: "Biz X edəcəyik, çünki..."

## Consequences
### Müsbət
- ...
### Mənfi
- ...
### Neytral
- ...
```

### ADR lifecycle

```
Proposed → Accepted → (zaman keçir) → Deprecated
                                    → Superseded by ADR-042
```

- **Proposed**: qərar müzakirə olunur
- **Accepted**: qərar qəbul edilib, icra edilir
- **Deprecated**: qərar artıq aktuall deyil, amma silinmir — tarix qalır
- **Superseded**: yeni ADR bu qərarı əvəz edib

### Harada saxlanır

```
/docs/adr/
  ├── 001-use-postgresql.md
  ├── 002-use-cqrs-for-orders.md
  ├── 003-use-kafka-over-rabbitmq.md
  └── README.md  ← bütün ADR-lərin siyahısı
```

Repository-nin içi — kod ilə birlikdə version control altında.

### Nə vaxt ADR yazılır

**Yazılmalıdır:**
- Proqramlaşdırma dili seçimi
- Framework seçimi
- Verilənlər bazası seçimi
- Arxitektura patterninin tətbiqi (CQRS, Event Sourcing, Saga)
- İkinci variantın niyə rədd edildiyinin izahı lazım olduğu hallarda
- Team üçün standart müəyyən edərkən

**Yazılmamalıdır:**
- Gündəlik kod implementation qərarları
- "İndi bu funksiyaya dependency injection tətbiq edəcəyik" kimi kiçik qərarlar
- Artıq aydın olan seçimlər

## Praktik Baxış

### Real ADR nümunəsi 1: Go seçimi

```markdown
# ADR-001: Yeni microservice üçün Go seçimi

## Status
Accepted (2024-03-15)

## Context
Mövcud order-management servisi PHP/Laravel ilə yazılıb.
Yüksək yük altında (10k req/s) performance problemi yaşayırıq.
P99 latency 800ms-dir, hədəfimiz 100ms-dir.

Üç variant müzakirə edildi:
1. PHP-i optimize etmək (Laravel Octane + RoadRunner)
2. Go ilə yenidən yazmaq
3. Java/Spring Boot

## Decision
Biz Go ilə yenidən yazacağıq.

**Niyə Go:**
- Eyni benchmark-da Go PHP-dən 5-10x daha az memory istifadə edir
- Goroutine-lər C10K problemini asanlıqla həll edir
- Komandada Go biliyi artıq var (2 developer)
- Compile time binary — deployment sadədir
- Java ilə müqayisədə: daha az boilerplate, daha sürətli başlanğıc

**Niyə PHP deyil:**
- Octane ilə belə, PHP-nin single-threaded modeli məhduddur
- Memory footprint yüksəkdir

**Niyə Java deyil:**
- JVM startup time K8s-də scale-in/scale-out-u yavaşladır
- Komandada Java expertise yoxdur

## Consequences
### Müsbət
- P99 latency < 100ms hədəfinə çatmaq mümkün olacaq
- Memory istifadəsi ~3x azalacaq → infrastruktur xərci düşəcək
- Static binary — Docker image kiçilir

### Mənfi
- Komanda Go öyrənməlidir (2-3 ay transition period)
- Laravel ecosystem-in convenience-i (Eloquent, Artisan) olmayacaq
- Go-da daha çox boilerplate (error handling)

### Neytral
- CI/CD pipeline dəyişməlidir
- Monitoring/alerting yenidən qurulmalıdır
```

### Real ADR nümunəsi 2: CQRS

```markdown
# ADR-002: Order Management-də CQRS tətbiqi

## Status
Accepted (2024-04-10)

## Context
Order service-də oxuma və yazma əməliyyatları arasında konflikt var:
- Yazma: mürəkkəb business logic, transaction lazımdır
- Oxuma: dashboard üçün 15 cədvəl JOIN edilir, çox yavaşdır

Standart CRUD arxitekturası artıq işləmir:
- Dashboard sorğuları 2-3 saniyə gedir
- Yazma əməliyyatları oxuma ilə lock conflict yaradır

## Decision
Order Management-i CQRS pattern-inə köçürəcəyik:
- **Command side**: yazma (Create, Update, Cancel order) — PostgreSQL
- **Query side**: oxuma (dashboard, reports) — ayrı read model, denormalized

Read model Redis-də cached view kimi saxlanacaq.
Order yazılanda event publish olunacaq, read model update ediləcək.

## Consequences
### Müsbət
- Dashboard sorğuları < 50ms olacaq (Redis-dən)
- Yazma və oxuma ayrıldığı üçün hər biri ayrıca scale edilə bilər
- Read model fərqli sxemada ola bilər (oxumaq üçün optimallaşdırılmış)

### Mənfi
- Eventual consistency: yazılan data anında read model-də görünmür (~100ms delay)
- İki ayrı data store (Postgres + Redis) — operational complexity artır
- Code complexity artır: artıq sadə CRUD deyil

### Neytral
- Test yazması daha çətin olacaq — integration test lazımdır
```

### Real ADR nümunəsi 3: Kafka vs RabbitMQ

```markdown
# ADR-003: Event Streaming üçün Kafka seçimi

## Status
Accepted (2024-05-02)

## Context
Microservice-lər arasında event kommunikasiyası lazımdır:
- Order created → inventory service stock azaltmalıdır
- Payment processed → notification service email göndərməlidir
- Gündə ~500k event

İki variant müzakirə edildi:
1. RabbitMQ
2. Apache Kafka

## Decision
Apache Kafka istifadə edəcəyik.

**Niyə Kafka:**
- Event replay: köhnə event-ləri yenidən oxumaq mümkündür — audit, debugging, yeni service üçün
- Throughput: 500k/gün indi, gələcəkdə 10M/gün ola bilər — Kafka bunu rahat həll edir
- Log compaction: state reconstruction imkanı
- Confluent Cloud ilə managed — operational burden azalır

**Niyə RabbitMQ deyil:**
- Message broker kimi daha yaxşıdır, amma event store kimi Kafka daha üstündür
- RabbitMQ message-i işlənən kimi silir — replay mümkün deyil
- Throughput limiti daha aşağıdır

## Consequences
### Müsbət
- Audit log pulsuz gəlir (event-lər Kafka-da qalır)
- Yeni service əlavə olanda köhnə event-ləri replay edə bilər
- Confluent Cloud ilə operational overhead minimumdur

### Mənfi
- Confluent Cloud xərci: ~$200/ay (başlanğıcda)
- Kafka learning curve — RabbitMQ-dan daha mürəkkəbdir
- Ordering guarantee: yalnız eyni partition daxilində

### Neytral
- Consumer group idarəsi lazım olacaq
- Schema Registry istifadə etmək tövsiyə olunur (Avro/Protobuf)
```

## Nümunələr

### Ümumi Nümunə

Team meeting-də: "Redis-mi, Memcached-mi istifadə edəcəyik?" müzakirəsi. Hər kəs fikirini deyir, qərar verilir. 8 ay sonra yeni developer: "Niyə Redis seçdik? Memcached daha sürətlidir ki?" — cavab yoxdur. ADR olsaydı, müzakirənin konteksti, rədd edilən alternativlər, gözlənilən nəticələr yazılı olardı.

### Kod Nümunəsi

**ADR template — Go proyekti üçün:**

```markdown
# ADR-NNN: [Qısa başlıq]

## Status
Proposed / Accepted / Deprecated / Superseded by ADR-NNN

## Date
YYYY-MM-DD

## Deciders
- @github-handle1
- @github-handle2

## Context
[Problemin izahı. Nə baş verir? Nə lazımdır? Texniki məhdudiyyətlər?]

## Options considered
### Variant A: [Ad]
- Pro: ...
- Con: ...

### Variant B: [Ad]
- Pro: ...
- Con: ...

## Decision
Biz [Variant X] seçirik, çünki [əsas səbəb].

[Daha ətraflı izah...]

## Consequences
### Müsbət
- ...

### Mənfi
- ...

### Neytral / Diqqət tələb edən
- ...

## Related ADRs
- ADR-NNN: [əlaqəli qərar]

## References
- [Link to relevant docs/articles]
```

**Makefile ilə ADR yaratmaq:**

```makefile
# ADR sayını avtomatik müəyyən et və yeni fayl yarat
adr-new:
	@NEXT=$$(ls docs/adr/*.md 2>/dev/null | wc -l | xargs -I{} expr {} + 1); \
	NUM=$$(printf "%03d" $$NEXT); \
	FILE="docs/adr/$$NUM-$(title).md"; \
	cp docs/adr/template.md $$FILE; \
	echo "Created: $$FILE"; \
	$(EDITOR) $$FILE
```

```bash
make adr-new title="use-redis-for-session-storage"
# → docs/adr/004-use-redis-for-session-storage.md
```

**`adr-tools` CLI:**

```bash
# Install
brew install adr-tools

# Init
adr init docs/adr

# Yeni ADR yarat
adr new "Use PostgreSQL as primary database"
# → docs/adr/0001-use-postgresql-as-primary-database.md

# Supersede əvvəlki qərarı
adr new -s 1 "Switch to CockroachDB for global distribution"
# ADR-0001 "Superseded by ADR-0002" olaraq işarələnir

# Siyahı
adr list
```

**docs/adr/README.md — index:**

```markdown
# Architecture Decision Records

Bu qovluq Go API layihəsinin vacib arxitektura qərarlarını qeyd edir.

## Qərarlar

| # | Başlıq | Status | Tarix |
|---|--------|--------|-------|
| [ADR-001](001-use-postgresql.md) | PostgreSQL as primary DB | ✅ Accepted | 2024-03-15 |
| [ADR-002](002-cqrs-orders.md) | CQRS for order management | ✅ Accepted | 2024-04-10 |
| [ADR-003](003-kafka-streaming.md) | Kafka for event streaming | ✅ Accepted | 2024-05-02 |
| [ADR-004](004-redis-sessions.md) | Redis for session storage | 🔄 Proposed | 2024-06-01 |
```

## Praktik Tapşırıqlar

**1. Mövcud layihə üçün retrospektiv ADR yaz:**

Artıq qəbul edilmiş, amma yazılmamış qərarları sənəkləşdir. "Niyə X deyil Y?" sualını yaddaşından cavabla.

**2. Növbəti texniki müzakirənin əvvəlində:**

Qərar mövzusunu müəyyən et → ADR template aç → Variants section-ı doldur → Müzakirə et → Qərar verin → Decision section-ı yaz.

**3. PR-a ADR əlavə et:**

Böyük arxitektura dəyişikliyini etdiyin zaman PR-a yeni ADR faylını da qoş. Reviewer-lər baxışdan əvvəl konteksti başa düşsün.

**4. ADR review checklist:**

- [ ] Status doldurulub?
- [ ] Context section problemi aydın izah edir?
- [ ] Ən azı 2 alternativ müzakirə edilib?
- [ ] Mənfi nəticələr (cons) yazılıb? (Yalnız müsbət yazılan ADR etibarsızdır)
- [ ] Kimin qərar verdiyi bəlli?

**Common mistakes:**

- ADR-i qərardan sonra yazmaq — alternativlər unudulur, əsl debate itirilir
- Yalnız müsbət nəticələr yazmaq — dürüst deyil, lazımsızdır
- Həddən artıq kiçik qərarlar üçün ADR yazmaq — "handler-da logging əlavə etdik" ADR deyil
- ADR-i update etmək qərar dəyişdikdə — bunun əvəzinə yeni ADR yaz, köhnəni "Superseded" et

## Əlaqəli Mövzular

- `27-clean-architecture.md` — arxitektura qərarlarının izahı
- `26-microservices.md` — microservice-lər üçün ADR mövzuları
- `04-design-patterns.md` — pattern seçimi kimi ADR mövzuları
