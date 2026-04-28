# Atlassian (Lead)

## Ümumi baxış
- **Nə edir:** Komanda əməkdaşlıq alətləri — Jira (issue tracking), Confluence (wiki), Bitbucket (Git hosting), Trello (kanban), Bamboo (CI/CD), Statuspage, Opsgenie. B2B SaaS; əsasən developer tooling bazarı.
- **Yaradılıb:** 2002-ci ildə Sidney-də Mike Cannon-Brookes və Scott Farquhar tərəfindən. Heç VC pulu almadan IPO-ya çatdılar (2015).
- **Miqyas:**
  - 300.000+ müştəri (Fortune 500-ün 80%+).
  - 10M+ aktiv istifadəçi.
  - Jira dünyanın ən geniş yayılmış issue tracker-ıdır.
- **Əsas tarixi anlar:**
  - 2002: Jira 1.0 — Java-da yazılmış, server-installed.
  - 2010: Atlassian Marketplace — plugin ekosistemi.
  - 2012: Atlassian Cloud — SaaS versiyası (server yanında).
  - 2017: Server satışları yavaşlayır; cloud-a fokus artır.
  - 2021: Server lisenziyanın sonu elan edildi (2024-cü ilə qədər).
  - 2022-2024: Bütün müştəriləri Data Center (özündə host) və ya Cloud-a köçürülməsi.
  - 2024: Atlassian artıq demək olar ki, tam cloud SaaS şirkətidir.

Atlassian **on milyonlarla fayl attach-ı, on milyardlarla issue, on illik audit log-ların** üzərindən data migration-ının necə aparılacağını göstərən klassik **"server product → cloud SaaS" miqrasiya** case study-dir.

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Main backend | **Java** (Spring + öz framework-ləri) | 2002-dən bəri; enterprise yetkinlik |
| Jira frontend | **React** (köhnəsi: Velocity template-ləri) | 2017+ inkremental köçürmə |
| Confluence frontend | **React** (köhnəsi: server-render Velocity) | Eyni köçürmə |
| Primary DB | **PostgreSQL** (Cloud), **Oracle/MSSQL/PostgreSQL** (Data Center) | Multi-tenant Cloud; müştəri seçimi DC-də |
| Blob storage | **AWS S3** | Fayl attach-lar, export-lar |
| Cache | **Redis** | Session, hot data |
| Message queue | **Apache Kafka** | Event streaming, auditlog, notification |
| Search | **Elasticsearch** | Jira axtarışı, Confluence full-text |
| Infrastructure | **AWS** (us-east-1, ap-southeast-2, eu-west-1, ...) | Global cloud, multi-region |
| CDN | **CloudFront + Fastly** | Static assets, global edge |
| Multi-tenancy | **Schema-based** (hər tenant öz PostgreSQL schema-sı) | Tenant isolation, simpler backup/restore |

## Dillər — Nə və niyə

### Java — Əsas dil
- Jira, Confluence, Bitbucket, Bamboo — hamısı Java.
- 2002-ci ildə enterprise server software üçün Java ən yaxşı seçimdi: strong typing, JVM portability, geniş ekosistem.
- OSGi (plugin sistemi) — Jira plugin-ləri Java-da yazılıb, run-time-da yüklənir. Bu arxitektura 2000-lərin əvvəlindən gəlir.
- JVM-in performance-ı server deployment üçün kifayət idi; cloud-da scale-out daha asan oldu.

### Kotlin (artan)
- Yeni servislərdə tədricən Kotlin. Java ilə tam interop.
- Atlassian-ın engineering blogunda Kotlin adoption haqqında post-lar var (2019+).

### Go
- Bir neçə infrastruktur servisi (proxy, agent). Go-nun kiçik binary footprint-i məcburi quraşdırmalarda (Jira Data Center node agent-ləri) üstünlük verir.

### Python
- ML, analytics, internal tooling.

### JavaScript / TypeScript
- Frontend React köçürməsi. Köhnə UI Velocity template-ləri (Java server-side rendering) idi; indi React SPA-lara keçirlər.

## Framework seçimləri — Nə və niyə

### Jira — Spring + OSGi (Atlassian Plugins 2)
- **OSGi** (Open Services Gateway initiative) — Jira-nın plug-in arxitekturasının əsasıdır. Plugin-lər JVM-də hot-deploy olunur, izolasiya olunmuş classloader-larla.
- **Spring** — DI, transaction management, standart enterprise Java.
- Plugin marketplace (3500+ plugin) bu arxitektura sayəsindədir. Müştəri öz plugin-ini yazıb Jira-ya install edə bilir.

### Confluence — Eyni OSGi yanaşması
- Makro sistemi (custom Wiki markup-lar) plugin-lər vasitəsilə genişləndirilir.

### Atlassian Forge (yeni)
- 2021-dən: **Forge** — serverless function-based extension platform. V8 isolate-lər (Cloudflare Workers bənzəri).
- Cloud-da müştərilərin öz serverini idarə etmədən extension yazmasına imkan verir.
- Connect (webhook-based) → Forge (hosted functions) köçürməsi davam edir.

## Verilənlər bazası seçimləri — Nə və niyə

### Multi-tenant PostgreSQL (Cloud)
- Hər tenant (şirkət) öz PostgreSQL schema-sını alır.
- **Schema-based isolation**: `CREATE SCHEMA tenant_abc_123; SET search_path TO tenant_abc_123;`
- Üstünlük: müştəri data-sı ayrıdır, backup/restore sadədir, query izolasiyası var.
- Çatışmazlıq: çox tenant olduqda schema count artır, migration-lar mürəkkəbləşir.
- Atlassian Cloud-da on minlərlə PostgreSQL schema var; hər biri bir tenant.

### Data Center — Multi-database support
- Müştəri öz DB-sini seçir: PostgreSQL, MySQL, Oracle, MSSQL.
- Jira-nın JDBC abstraction layer-i bütün DB-ləri dəstəkləyir.
- Bu çoxlu cross-DB compatibility testi tələb edir — Atlassian-ın ən böyük test yükü bu idi.
- Cloud-da hamı PostgreSQL-ə köçdü — test yükü azaldı.

### Elasticsearch
- Jira axtarışı: issue-lar, şərhlər, attachment məzmunu.
- Confluence full-text axtarışı: page-lər, attachments.
- Böyük tenantlarda Elasticsearch cluster-ları ayrılır.

## Proqram arxitekturası

```
        [Browser / Mobile Client]
               |
        [CloudFront CDN]
               |
        [API Gateway / Load Balancer]
               |
    +----------+----------+----------+
    |          |          |          |
  Jira     Confluence  Bitbucket  Trello
  (Java)    (Java)      (Java)    (Node)
    |          |          |
 [PostgreSQL schema-per-tenant]
    |
 [Redis Cache] [Elasticsearch] [S3]
    |
  [Kafka event bus]
    |
 [Notification service] [Audit log] [Analytics]
```

### Forge (Cloud Extensions)
```
  Developer kodu → Forge deploy → V8 isolate-lər (per-tenant) → Jira/Confluence API-si
```

### Data migration pipeline (Server → Cloud)
```
  On-prem Jira → Export (ZIP/XML) → S3 → Migration service → Atlassian Cloud tenant
```
- Böyük müştərilər üçün (100k+ issue): incremental sync, delta migration.
- Migration service Java-da yazılıb; transformasiya, validation, idempotency.

## İnfrastruktur və deploy

- AWS multi-region: US, EU, AP.
- **Micros** — Atlassian-ın daxili deployment platforması (Kubernetes üzərindəki abstraction).
- Hər servis öz K8s deployment-ı; canary deploy, feature flag-lar (LaunchDarkly + öz FLAG sistemi).
- **Tenancy isolation**: VIP (tenant-group) sistemi — böyük tenantlar ayrı cluster-lara alınır.
- **Rate limiting**: per-tenant API rate limits; büyük müştəri kiçik müştərinin performansına təsir etmir.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|-----|-----------|
| 2002 | Jira 1.0 — Java servlet, Oracle DB |
| 2008 | Atlassian Plugins 2 (OSGi) — marketplace əsası |
| 2012 | Atlassian Cloud ilk versiyası — server yanında SaaS |
| 2015 | IPO; cloud-a investisiya sürətləndi |
| 2017 | Server satışı yavaşladı; cloud-first strategiya |
| 2019 | Kotlin adoption başladı |
| 2021 | Server EOL elan edildi (2024-cü il son tarix) |
| 2021 | Forge launch — yeni extension model |
| 2022 | Böyük Data Center → Cloud migration dalğası |
| 2024 | Server versiyası sona çatdı; Atlassian tam cloud |

## Əsas texniki qərarlar

1. **Schema-per-tenant PostgreSQL.** Row-level multi-tenancy yerinə schema-per-tenant seçildi. Bu tenant isolation-ı gücləndirir amma migration mürəkkəbliyini artırır. On minlərlə schemada `ALTER TABLE` migration-ları daxili automation tələb etdi.
2. **OSGi plugin sistemi.** 2008-dən bəri extension point-lər OSGi vasitəsilə. 3500+ marketplace plugin bu qərarın məhsuludur. Əks tərəfi: classloader isolation-ı debug çətin, security boundary zəif. Forge bu problemi həll edir.
3. **Server EOL qərarı.** Müştəriləri force etmək riskli idi, amma on illik dual-mode (server + cloud) saxlamağın cost-u daha böyük idi. Atlassian 2021-də qəti addım atdı — bu cəsarətli amma düzgün qərar idi.
4. **Multi-database support (Data Center).** PostgreSQL + MySQL + Oracle + MSSQL dəstəyi Atlassian-ın ən böyük test yükü idi. Cloud-da hamı PostgreSQL-ə keçdikdən sonra bu yük dramatik azaldı — monokultura üstünlükləri var.
5. **Forge V8 isolates.** Serverless extension model — Lambda deyil, V8 isolate-lər. Hər tenant extension-ı izolasiyada işləyir, başlanma vaxtı sıfıra yaxın. Cloudflare Workers arxitekturasının B2B extension platformasına tətbiqi.

## Müsahibədə necə istinad etmək

1. **"Multi-tenant SaaS DB schema necə dizayn edərsiniz?"** → "Atlassian schema-per-tenant yanaşmasını istifadə edir — hər müştəri öz PostgreSQL schema-sına sahib. Bu row-level isolation-dan daha güclü tenant izolasiyası verir, amma migration-ları mürəkkəbləşdirir. Alternativ: shared tables + tenant_id FK hər yerdə — sadədir amma data isolation zəifdir."
2. **"Legacy server produktunu cloud SaaS-a necə köçürərsiniz?"** → "Atlassian 10 illik dual-mode-dan sonra hard cutoff seçdi. Key insight: iki versiya paralel saxlamaq özü risk idi — feature parity divergence, security patch dual burden. Migration service + incremental sync + hard deadline birlikdə işlər."
3. **"Plugin/extension sistemi necə qurulur?"** → "Atlassian OSGi (JVM classloaders) ilə başladı — güclü amma debug çətin. 2021-dən Forge: V8 isolate-lər, per-tenant, serverless. Sandbox model hər extension-ı izolasiya edir. Extension sistemi quranda execution environment seçimi (process, VM, isolate) security/performance trade-off-u müəyyən edir."
4. **"Böyük müştəriləri kiçiklərdən performans baxımından necə ayırırsınız?"** → "Atlassian VIP tenancy sistemi — böyük müştərilər (Fortune 500) ayrı klasterlara alınır. Bu 'noisy neighbor' problemini həll edir. Laravel-də bu rate limiting + dedicated queue worker-lar + separate DB replica ilə başlaya bilər."
5. **"B2B SaaS-da data migration necə işləyir?"** → "Atlassian export (ZIP/XML) → S3 → idempotent migration service pipeline-ı. Böyük tenantlar üçün: incremental sync, son delta migration ilə cutover. Idempotency kritikdir — migration yarım qalıb yenidən başlaya bilər."

## Əlavə oxu üçün
- Atlassian Engineering Blog: *How Atlassian migrated to cloud*
- Atlassian Engineering Blog: *Forge architecture — V8 isolates at scale*
- Atlassian Engineering Blog: *Schema-per-tenant at scale: lessons learned*
- InfoQ: Atlassian server EOL decision retrospective
- Atlassian Marketplace developer docs: OSGi plugin system deep-dive
