# Architecture Decision Records (ADR) (Lead)

## İcmal

ADR (Architecture Decision Record) — arxitektura qərarını, onun kontekstini və nəticələrini qeyd edən qısa sənəddir. Hər ADR bir qərara cavab verir: **"Niyə bu cür etdik?"**

ADR-lər kod ilə birgə git repository-sində yaşayır. 6 ay sonra komandalara keçmiş qərarları anlamağa imkan verir — developer artıq komandada olmasa belə.

---

## Niyə Vacibdir

**ADR-siz olan real ssenari:**

```
Yeni developer: "Niyə PostgreSQL istifadə edirik? MySQL daha tanışdır."
Senior developer: "Bilmirəm, mən bu qərara qatılmamışdım."
Həmin ay: eyni debat yenidən başlayır, vaxt itirilir.
```

ADR olmadan:
- **Institutional knowledge loss** — qərar verən developer gedəndə səbəb də gedir
- **Eyni debatların təkrarlanması** — "biz bunu bir dəfə müzakirə etdik" amma sənəd yoxdur
- **Rejected alternative-lərin unudulması** — "niyə Kafka yox RabbitMQ seçmədik?" — cavab yoxdur
- **Onboarding çətinliyi** — yeni developer "niyə" suallarını komandaya verməli olur

ADR ilə:
- Hər qərarın konteksti saxlanır
- Rejected alternativlər qeyd olunur
- Komanda dəyişsə belə qərar tarixi qalır
- PR-larda istinad verilə bilər: "Bax ADR-0007"

---

## Əsas Anlayışlar

### Michael Nygard Formatı (klassik)

2011-ci ildə Michael Nygard tərəfindən popularlaşdırılmış minimal format:

| Hissə | Məzmun |
|-------|--------|
| **Title** | Qısa imperativ cümlə: "Use PostgreSQL as primary database" |
| **Status** | `Proposed` / `Accepted` / `Deprecated` / `Superseded by ADR-0023` |
| **Context** | Hansı şərtlər bu qərarı zəruri etdi (technical, team, business) |
| **Decision** | Nə etdik — aktiv voice ilə: "We will use..." |
| **Consequences** | Müsbət VƏ mənfi nəticələr, trade-off-lar açıq qeyd olunur |

### MADR Formatı (Markdown Architectural Decision Records)

Nygard formatının genişləndirilmiş versiyası — alternativləri müqayisəli cədvəldə göstərir:

```
# ADR-0012: Use Kafka for inter-service messaging

## Status
Accepted

## Context and Problem Statement
3 mikroservis arasında asinxron kommunikasiya lazımdır...

## Decision Drivers
- Durability tələbi: mesaj itməməlidir
- Throughput: 10k msg/sec peak
- Replay imkanı lazımdır

## Considered Options
- Apache Kafka
- RabbitMQ
- AWS SQS
- Direct HTTP calls

## Decision Outcome
Chosen option: Apache Kafka with Spring Kafka

## Pros and Cons of the Options

### Kafka
- ✅ Durability, replay, high throughput
- ❌ Operational complexity, ZooKeeper/KRaft

### RabbitMQ
- ✅ Simpler ops, AMQP standard
- ❌ No native replay, lower throughput
```

### ADR Nömrələmə Qaydaları

- Sequential: `0001`, `0002`, ..., `0099`, `0100`
- Nömrə **heç vaxt yenidən istifadə edilmir**
- ADR **heç vaxt silinmir** — köhnəlibsə `Superseded` statusu verilir
- Git history-də hər ADR-in kim tərəfindən, nə vaxt qəbul edildiyi görünür

### ADR Lifecycle

```
Proposed ──→ Accepted ──→ Deprecated
                 │              ↑
                 └──→ Superseded (by ADR-XXXX)
```

| Status | Mənası |
|--------|--------|
| `Proposed` | Komanda review edir, hələ qəbul edilməyib |
| `Accepted` | Qərar qəbul edildi, tətbiq olunur |
| `Deprecated` | Artıq aktualdeyil, amma "supersede" edən yoxdur |
| `Superseded by ADR-0023` | Yeni qərar köhnəni əvəz edir; köhnə ADR silinmir |

---

## ADR Formatı

### Fayl Strukturu

```
docs/
  adr/
    README.md          ← ADR siyahısı (adr-tools ilə auto-generate)
    0001-use-postgresql.md
    0002-use-flyway-migrations.md
    0003-use-kafka-messaging.md
    0023-replace-kafka-with-sqs.md   ← ADR-0003-i supersede edir
```

**Niyə git-də:**
- Version control — kim, nə vaxt qərar verdi
- PR review prosesi — ADR review implementation-dan əvvəl gəlir
- Searchable — `git log --grep="database"` ilə tapmaq olar
- Kod ilə eyni yerdə — ayrı wiki-də itmir

### Fayl Adlandırma

```
NNNN-imperativ-qisal-bashliq.md
```

Nümunələr:
- `0001-use-postgresql-as-primary-db.md`
- `0007-adopt-jwt-for-api-auth.md`
- `0015-migrate-monolith-to-microservices.md`

---

## ADR Lifecycle

### Prosess

```
1. Developer/Lead → ADR yazar (Proposed)
2. PR açır: docs/adr/XXXX-title.md
3. Tech lead + affected team members review edir
4. Razılaşma → Status: Accepted → merge
5. Implementation PR-da istinad: "Implements ADR-0012"
6. Qərar dəyişsə → YENİ ADR yazılır → köhnənin status-u: "Superseded by ADR-0XXX"
```

**Qayda:** ADR implementation-dan **əvvəl** merge olunmalıdır. Kod artıq yazılandan sonra "retrospective ADR" yazmaq anti-patterndir — kontekst unudulur, alternativlər qeyd olunmur.

### Referencing

**Kod şərhləri:**
```java
// See ADR-0007: JWT was chosen over sessions for stateless scaling
// Changing this affects mobile clients — review ADR-0007 first
@Bean
public JwtDecoder jwtDecoder() { ... }
```

**PR description:**
```
## Context
Implements ADR-0012 (Kafka for inter-service messaging).

## Changes
- Added spring-kafka dependency
- Implemented OrderEventPublisher
```

**README:**
```markdown
## Architecture Decisions
Key decisions are documented in [docs/adr/](docs/adr/).
Notable: [ADR-0001 (PostgreSQL)](docs/adr/0001-use-postgresql.md),
[ADR-0012 (Kafka)](docs/adr/0012-use-kafka-messaging.md)
```

---

## Nümunələr

### Sadə ADR: PostgreSQL vs MySQL

```markdown
# ADR-0001: Use PostgreSQL as Primary Database

## Status
Accepted

## Context
E-commerce platformu üçün relational database seçmək lazımdır.
Tələblər:
- ACID transactions (order processing)
- JSON data (product attributes fərqlidir)
- AWS-də deploy olunacaq
- Komandanın 3 nəfəri PostgreSQL, 1 nəfəri MySQL bilir

## Decision
PostgreSQL 15-i AWS RDS-də primary database kimi istifadə edəcəyik.
Spring Boot tərəfindən Hibernate/JPA ilə əlaqəli olacaq.

## Consequences

### Müsbət
- JSONB tipi product attributes üçün schema-less saxlama imkanı verir
- Advanced indexing: partial index, expression index, GIN
- ACID tam dəstəklənir — order processing üçün kritikdir
- Komandanın çoxu tanışdır → onboarding daha sürətli
- AWS RDS-də automated backup, failover dəstəklənir

### Mənfi
- AWS RDS-də MySQL-dən ~15% baha
- MySQL-ə nisbətən daha az DBA-resource online-da mövcuddur
- Vendor lock-in: AWS RDS-ə bağlıyıq (migration mümkün, amma xərc tələb edir)

## Rejected Alternatives

### MySQL 8.0
- ❌ JSON dəstəyi var, amma JSONB kimi performanslı deyil
- ❌ Window functions dəstəyi 8.0-dan gəldi, hələ yetişməmiş

### MongoDB
- ❌ Transaksiyalar limitlidir — order processing üçün riskli
- ❌ Komandada heç kim bilmir
```

### Mürəkkəb ADR: Mikroservis üçün Messaging

```markdown
# ADR-0012: Use Apache Kafka for Inter-Service Messaging

## Status
Accepted

## Context
3 mikroservis (order-service, inventory-service, notification-service)
arasında asinxron kommunikasiya lazımdır.

Tələblər:
- **Durability**: mesaj göndərildikdən sonra itirilməməlidir
- **Replay**: notification service aşağı düşsə, mesajları yenidən
  emal etmək lazım ola bilər
- **Throughput**: peak-də 10,000 msg/sec (Black Friday ssenarisi)
- **Ordering**: eyni order-a aid hadisələr sıralı gəlməlidir

Mövcud vəziyyət:
- Komandada Kafka təcrübəsi var (2 nəfər)
- AWS-də deploy edirik (MSK mövcuddur)
- Spring Boot ekosistemi

## Decision
Apache Kafka-nı Spring Kafka ilə istifadə edəcəyik.
AWS MSK (Managed Streaming for Kafka) üzərindən deploy olunacaq.

Topic adlandırma: `{domain}.{entity}.{event}` formatı
(məs: `orders.order.created`, `inventory.stock.updated`)

## Considered Options

| Kriteriya | Kafka | RabbitMQ | AWS SQS | HTTP Direct |
|-----------|-------|----------|---------|-------------|
| Durability | ✅ | ✅ | ✅ | ❌ |
| Replay | ✅ | ❌ | ❌ | ❌ |
| Throughput 10k/s | ✅ | ⚠️ | ✅ | ❌ |
| Ordering | ✅ partition | ❌ | ⚠️ FIFO | ❌ |
| Ops complexity | ❌ High | ✅ Low | ✅ Managed | ✅ None |
| Team familiarity | ✅ | ❌ | ⚠️ | ✅ |

## Consequences

### Müsbət
- Replay: notification service dayandıqda mesajlar itirilmir,
  yenidən emal edilə bilər
- Throughput: 10k msg/sec rahat ödəyir, scale mümkündür
- Stream processing: gələcəkdə Kafka Streams ilə analitika əlavə etmək olar
- AWS MSK: ops yükü azdır, managed service

### Mənfi
- **Operational complexity**: RabbitMQ-dan daha mürəkkəb
  (partition, consumer group, offset management)
- **Team knowledge gap**: 2 nəfər bilir, qalan 3 öyrənməli olacaq
  → 2 həftəlik onboarding planlaşdırılır
- **Minimum latency**: Kafka ~ms latency, HTTP-dən yavaşdır —
  real-time response tələb edən hallarda uyğun deyil

## Notes
Əgər gələcəkdə throughput tələbi azalarsa və replay lazım olmazsa,
AWS SQS-ə keçid nəzərdən keçirilə bilər (daha az ops complexity).
Bu halda yeni ADR yazılacaq.
```

---

## Praktik Baxış

### Java/Spring Layihələri üçün Tipik ADR Mövzuları

**Authentication:**
```markdown
# ADR-0007: Use JWT for API Authentication

Context: Mobile app + web frontend, stateless scaling tələbi var,
         session server-side state olmadan dəstəklənməlidir.

Decision: JWT (RS256) — Stateless, Spring Security ilə inteqrasiya.

Rejected: Session-based — stateful, horizontal scaling-də problem;
          OAuth2 — overkill, external IdP hazır deyil hələlik.
```

**ORM seçimi:**
```markdown
# ADR-0003: Use Spring Data JPA / Hibernate as ORM

Context: CRUD-heavy application, developer-lər SQL-dən daha çox
         ORM istifadə edir. Performance critical sorğular az.

Decision: Spring Data JPA + Hibernate.

Rejected: jOOQ — type-safe SQL yaxşıdır, amma learning curve yüksəkdir,
          komanda tanış deyil. JDBC Template — boilerplate çoxdur.

Note: Performance-critical reports üçün native query istifadə oluna bilər.
```

**Database migration:**
```markdown
# ADR-0004: Use Flyway for Database Migrations

Context: Multiple environment (dev/staging/prod), CI/CD pipeline,
         migration history vacibdir.

Decision: Flyway — Spring Boot auto-configuration, versioned migrations.

Rejected: Liquibase — XML/YAML format daha verbose, Flyway SQL-first
          daha asan oxunur. Manual SQL — version control yoxdur.
```

**Monolith → Mikroservis:**
```markdown
# ADR-0015: Migrate Monolith to Microservices (Strangler Fig Pattern)

Context: Laravel monolith 5 ildir böyüyür. Deploy riski artıb.
         Hər deploy bütün sistemi dayandırır. Team 8 nəfərə çatıb.

Decision: Strangler Fig pattern ilə inkremental miqrasiya.
          Yeni features mikroservis kimi yazılır, köhnə kod
          tədricən miqrasiya edilir.

Rejected: Big bang rewrite — çox riskli, business continuity pozulur.
          Monolith qalmaq — deploy risk artmaqda davam edir.
```

### PHP/Laravel-dən Java-ya Keçiddə ADR-lər

Laravel monolith-dən Java mikroservisə keçid edəndə ADR-lər xüsusilə vacibdir:

```markdown
# ADR-0020: Adopt Java/Spring Boot for New Microservices

Context: PHP/Laravel monolith performance bottleneck-lərə çatıb.
         Yeni payment service yüksək throughput tələb edir.
         Java/Spring Boot ekosistemi bu tələblərə uyğundur.

Decision: Yeni mikroservislər Java 21 + Spring Boot 3 ilə yazılacaq.
          Mövcud Laravel monolith qalır, tədricən miqrasiya ediləcək.

Consequences:
  (+) Virtual threads (Project Loom) ilə yüksək concurrency
  (+) Spring ekosistemi — Cloud, Security, Data
  (-) İki dil — Java + PHP. DevOps, CI/CD daha mürəkkəb
  (-) Komanda Java öyrənməlidir — 3 aylıq plan hazırlanıb
```

### adr-tools CLI

```bash
# Qurulum (macOS)
brew install adr-tools

# Layihədə ADR qovluğunu inisializasiya et
adr init docs/adr

# Yeni ADR yarat (avtomatik nömrələnir)
adr new "Use PostgreSQL as primary database"
# → docs/adr/0001-use-postgresql-as-primary-database.md yaradılır

adr new "Use Kafka for inter-service messaging"
# → docs/adr/0002-use-kafka-for-inter-service-messaging.md

# ADR-0002-ni supersede edən yeni ADR yarat
adr new -s 2 "Replace Kafka with AWS SQS"
# → 0002-nin statusu "Superseded" olur, 0003 yaradılır

# Bütün ADR-ləri siyahıla
adr list

# README üçün table of contents yarat
adr generate toc > docs/adr/README.md
```

**Manuel istifadə (adr-tools olmadan):**

```bash
# Sadəcə fayl yarat
touch docs/adr/0001-use-postgresql.md
# Template-i kopyala, doldur, PR aç
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: İlk ADR-inizi yazın

Mövcud bir layihənizi götürün. Bu sualları cavablayın:

1. Layihədə hansı database istifadə olunur? Bu seçimin **niyə** edildiyi harada yazılıb?
2. Authentication hansı üsulla həyata keçirilir? Alternativlər nəydi?
3. `docs/adr/` qovluğu yoxdursa, yaradın

Sonra bu ADR-ləri yazın:
- `0001-{database-name}-as-primary-db.md`
- `0002-{auth-method}-for-authentication.md`

### Tapşırıq 2: ADR Review Prosesini Simulyasiya Edin

Komandanızda bu ssenari üçün ADR yazın:

> **Ssenari:** Mövcud REST API-nə əlavə olaraq real-time notification lazımdır.
> Seçimlər: WebSocket, Server-Sent Events (SSE), polling, Webhook.

ADR-də:
- Context-i doldurun (tətbiqinizin real şəraitinə görə)
- Hər seçimi bir cədvəldə müqayisə edin
- Bir qərar verin və niyəsini izah edin
- Rejected alternativlər üçün bir cümlə yazın

### Tapşırıq 3: Supersede Ssenarisi

1. `0003-use-flyway-for-migrations.md` yazın (Accepted)
2. 3 ay sonra Liquibase-ə keçmək qərarı verildi (imagine et)
3. `0008-replace-flyway-with-liquibase.md` yazın
4. ADR-0003-ün status-unu `Superseded by ADR-0008` edin
5. ADR-0003-i **silməyin** — bu anti-patterndir

---

## Ətraflı Qeydlər

### ADR-in Olmadığı Hal: Real Cost

```
Şirkətdə: 2 il əvvəl Redis cache əlavə edilib.
Yeni Lead Developer: "Cache invalidation mürəkkəbdir, niyə
                      database-i direkt oxumayaq?"
3 həftəlik debat: perf test, DB load analizi, müqayisə.
Nəticə: Redis lazımdır, 3 il əvvəl eyni nəticəyə gəlinib.
Cost: 3 developer × 3 həftə = ~360 iş saatı itirildi
```

ADR-0005: "Use Redis for Session Caching" olsaydı:
- Kontekst: "DB connection pool limitini aşırdıq, session-lar üçün"
- Nəticə: Yeni developer 10 dəqiqəyə anlar, debat olmur

### ADR nə deyil

- **Design doc deyil** — implementasiya detalları lazım deyil
- **RFC deyil** — uzun analysis raportu deyil
- **Meeting notes deyil** — yalnız final qərar və konteksti

**Uzunluq:** 1-2 səhifə kifayətdir. Sadə qərar üçün 0.5 səhifə.

### Onboarding-də ADR-in Dəyəri

```
Yeni Java developer (PHP backgroundlu):
"Niyə Hibernate istifadə edirik? jOOQ daha type-safe deyilmi?"

Cavab: "Bax ADR-0003 — biz bunu müzakirə etdik, jOOQ-un
        learning curve-ü komanda üçün çox idi o vaxt.
        Əgər bu qərarı dəyişmək istəyirsənsə, yeni ADR yaz."

Nəticə:
- Sual cavablandı (5 dəqiqə)
- Köhnə debat təkrarlanmadı
- Yeni developer prosesi başa düşdü
- Əgər jOOQ həqiqətən yaxşıdırsa → ADR prosesi ilə irəliləyə bilər
```

---

## Əlaqəli Mövzular

- [19-strangler-fig-pattern.md](19-strangler-fig-pattern.md) — ADR-lər monolith miqrasiyasını sənədləşdirmək üçün əsas vasitədir
- [10-clean-architecture.md](10-clean-architecture.md) — Clean arch qərarları ADR-ə əla mövzudur
- [12-ddd-tactical.md](12-ddd-tactical.md) — Bounded context qərarları ADR-lə sənədləşdirilir
- [25-multi-tenancy-patterns.md](25-multi-tenancy-patterns.md) — Multi-tenancy strategiyası seçimi tipik ADR mövzusudur
- [23-github-actions-cicd.md](23-github-actions-cicd.md) — CI/CD pipeline-da ADR PR review mərhələsi
