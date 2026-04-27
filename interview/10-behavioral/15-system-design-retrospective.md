# System Design Retrospective (Lead ⭐⭐⭐⭐)

## İcmal

System design retrospective sualları interviewerin arxitektura qərarlarını geriyə baxaraq necə dəyərləndirib öyrəndiyinizi anlamaq üçün verilir. "Əvvəlki layihənizdə hansı arxitektura qərarını dəyişdirərdiniz?", "Bir sistemin dizaynını build etdikdən sonra retrospektiv etdinizmi?" kimi suallar bu kateqoriyadır.

Bu sual Lead/Architect səviyyəsi üçün xüsusi vacibdir — çünki sistemlər həmişə ilkin dizayndan kənara çıxır, və bu prosesi conscious şəkildə idarə etmək bacarığı əhəmiyyətlidir.

Güclü retrospektiv "mən yanıldım" deyil, "bu kontekstdə optimal idi, amma context dəyişdi" deməkdir. Self-blame yox, system thinking.

---

## Niyə Vacibdir

Interviewerlər bu sualdan anlamaq istəyirlər: dizayn qərarlarınızda critical thinking var mı, "build → ship → forget" mentalitetindən fərqli olaraq sistemin evolution-ını izləyirsinizmi, öyrənilmiş dərslər gələcək qəraralara necə transfer olunur?

Lead engineer-lər sadəcə "bu arxitektura doğru idi" deyil, "bu kontekstdə optimal idi, amma X dəyişdikdə artıq optimal deyil" deyə bilirlər.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Trade-off awareness** — niyə bu qərar verildi, alternatifləri nə idi, o vaxt niyə doğru idi?
- **Hindsight analysis** — "əvvəlki özümə nə deyərdim" — kritik amma self-blaming yox
- **Context sensitivity** — başqa şirkətdə eyni problem = eyni həll deyil
- **Evolution thinking** — sistem böyüdükcə arxitektura necə dəyişir, bu proactive yönetilirmi?
- **Blameless reflection** — "biz yanlış etdik" yox, "bu kontekstdə ən yaxşı idi, sonra öyrəndik"
- **Knowledge transfer** — bu dərsi başqaları da öyrəndimi? ADR, postmortem, internal blog?
- **Cost of deferral** — delay edilən arxitektura dəyişikliyinin real dollar cost-u

### 2. Red flags — zəif cavabın əlamətləri

- "Hər şeyi düzgün etmişdik, heç nəyi dəyişməzdim" — inandırıcı deyil
- Bütün problemi "o vaxt daha az bilikli idim" ilə izah etmək — growth yoxdur
- Retrospektiv yox idi, passiv öyrənmə
- Arxitektura qərarlarını detallardan kənarda anlatmaq — superficial
- Texniki deyil, yalnız process-i blame etmək
- "Hamı belə edirdi" — agency yoxdur

### 3. Green signals — güclü cavabın əlamətləri

- Konkret architectural decision + kontekst + nə öyrənildi
- Trade-off-u o vaxt da, indi də aydın izah edə bilmək
- "Bu dərs gələcək layihədə konkret nəyi dəyişdirdi"
- ADR, postmortem, internal blog — başqaları da öyrəndi
- Dəyişdirilən qərarın dollar/time cost-unu hesablamış olmaq
- "Scale envelope" analizi — sistemin limitini əvvəlcədən düşünmək

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Fokus |
|-------------|-------|
| **AWS/GCP partner** | Architecture best practices, Well-Architected Framework |
| **Long-term ownership** | System ownership, evolutionary architecture |
| **Lead/Staff/Principal** | Scope: sistemin lifecycle-ı, architectural debt |
| **Enterprise** | Migration paths, legacy modernization |

### 5. Seniority səviyyəsinə görə gözlənti

| Səviyyə | Retrospective depth |
|---------|---------------------|
| **Senior** | Modul-səviyyəli qərar retrospektivi |
| **Lead** | Sistem-səviyyəli qərar, team-i nə öyrətdi |
| **Staff** | Platform-səviyyəli, multi-team impact, şirkət-geniş dərs |

### 6. "Scale envelope" konsepti

Hər arxitektura qərarı üçün əvvəlcədən soruşulmalı olan sual:
- "Bu sistem maksimum neçə user/tenant/request-ə qədər işləyəcək?"
- "Bu limitə nə vaxt çatacağıq?"
- "O limitdə nə dəyişməlidir?"

Bu sualları soruşub cavablamaq — retrospektiv-e ehtiyacı azaldır. Lead-lər bu sualları dizayn mərhələsində soruşur, not after.

### 7. ADR (Architecture Decision Record) formatı

```markdown
## Status: Accepted / Superseded by ADR-XXX
## Context
[Nə idi problem, hansı constraint-lər var idi]
## Options Considered
[Alternativlər, hər birinin trade-off-u]
## Decision
[Seçilən yanaşma, niyə]
## Consequences
[Positive consequences, negative consequences, risks]
```

ADR yazmaq — retrospektiv məlumatı sistematik saxlamaq deməkdir.

---

## Praktik Baxış

### Cavabı necə qurmaq

STAR formatı ilə gedin, lakin "Result" hissəsini **iki hissəyə** ayırın:
1. İlk nəticə — original design-ın çatışmazlığı nə zaman ortaya çıxdı?
2. İkinci nəticə — öyrənilmiş dərsin sonrakı layihəyə tətbiqi

Bu sizi "problem gördüm" yox, "öyrəndim və tətbiq etdim" yerə qoyur.

### Optimal cavab uzunluğu

4–5 dəqiqə. Bu sual dərinlik tələb edir — 2 dəqiqəlik cavab superficial görünür.

### Tez-tez soruşulan follow-up suallar

1. **"If you could go back, what would you design differently and why?"** — Spesifik: "Tenant isolation-ı əvvəlcədən schema-per-tenant edərdim. Bu 20 tenantda 60 saat çəkərdi, 120 tenantda 280 saat çəkdi."
2. **"How do you document architectural decisions so others can learn?"** — "ADR yazıram — context, options, decision, consequences. Repo-da saxlanılır, yeni developer-lər niyə belə olduğunu anlayır."
3. **"What's the most expensive architectural mistake you've seen?"** — Spesifik, dollar rəqəmi ilə. "Migration 280 developer-saatı çəkdi — əvvəlcədən düzgün etmək 60 olardı."
4. **"How do you balance 'just enough architecture' vs over-engineering?"** — "Scale envelope analizi. 'Bu sistem neçə user görəcək 1 ildə, 3 ildə?' Əgər cavab belirsizdirsə — simplest solution. Əgər growth forecast-ı varsa — ona uyğun design."
5. **"What triggers an architectural review in your mind?"** — "Yeni tier müştəri (enterprise), 10x traffic artımı, yeni compliance tələbi, ya 3 sprint ardıcıl velocity azalması — bunların hər biri arxitektura review siqnalı."
6. **"Have you ever prevented an architectural mistake before it happened?"** — Proactive thinking: "Scale envelope analizi sayəsində X-i əvvəlcədən dəyişdirdim."
7. **"How do you know when to refactor vs rewrite?"** — "Behavior-ı dəyişdirmədən yaxşılaşdırma = refactor. >60% kod tamam fərqli olacaq = rewrite risk. Strangler fig — hər ikisi arasındakı pragmatik yol."

### Nə deyilməsin

- "O vaxt hamı səhv etdi, mən də onlarla getdim"
- "Yenidən qurardım" — amma niyə qurardın, nə qurardın demədən
- "Bu arxitektura pis idi" — context olmadan judgement
- "Retrospektiv etmək üçün vaxt olmadı" — bu culture problemini göstərir

---

## Nümunələr

### Tipik Interview Sualı

"Tell me about a system you designed or worked on that didn't age well. What would you do differently now?"

---

### Güclü Cavab (STAR formatında)

**Situation:**
2021-ci ildə 8 nəfərlik backend teamda multi-tenant SaaS platform — HR management — layihəsinə başladım. İlk versiya 3 developer ilə başlamışdı, sürəti vacib idi. Arxitektura qərarı: Laravel monolith, shared PostgreSQL database, hər tenant üçün `tenant_id` column ilə row-level isolation. Başlamaq üçün ən sürətli yol bu idi — team bunu bilirdi, 2 həftəyə MVP çıxırdı. Şirkət seed stage idi, ilk 10 müştəriyə çatmaq critical idi — arxitektura perfectionism-ə vaxt yox idi.

**Task:**
Mən bu sistemin backend-ını lead edirdim. 2022-ci ilə keçəndə 40 tenant, 2023-cü ildə 120 tenant oldu. Hər ilin ortasından etibarən sistem artan ağrı ilə işlədi.

**Action (retrospektiv):**

**Problem 1 — Shared schema scaling ("Noisy neighbor"):**
120 tenant olduqda bəzi tenant-ların heavy query-ləri digərlərinə slow-down yaratdı. `tenant_id` filtration güvənirdik, amma PostgreSQL query planner paylaşılan index-lərdə bütün tenant data-sını scan etmək məcburiyyətindəydi. Production-da 3 dəfə slow query incident oldu — bir tenant-ın large export-u digər tenant-ların dashboard-unu yavaşlatdı. SLA violation baş verdi.

**Problem 2 — Migration hell:**
Hər schema migration bütün tenant-ların cədvəlini etkiləyirdi. 120 tenant × böyük cədvəl = migration vaxtı saatlarla uzandı. Zero-downtime migration-ları implement etmək çox çətin oldu — lock əvəzinə `gh-ost` istifadə etmək məcburiyyəti yarandı, amma bu da row-level isolation-da mürəkkəblik yaratdı.

**Problem 3 — Customization demand:**
Enterprise müştərilər "bizim xüsusi iş axını var" deməyə başladı. Shared schema-da hər müştəriyə custom field əlavə etmək — `custom_attributes JSONB` column — schema versioning problemini yaratdı, reporting isə çox çətinləşdi.

**Retrospektiv reflection:**
O vaxt edilən qərar `tenant_id`-based isolation — tamam yanlış deyildi. 3 developer, 10 tenant üçün optimal idi. Bizim xəta: şirkətin growth trajectory-sini arxitekturada nəzərə almamaq idi. Marketing 6 aydan bəri "enterprise tier" planlaşdırırdı — bu siqnalı texniki arxitektura qərarında əks etdirmədik. "Scale envelope" sualını soruşmamışdım: "Bu sistem maksimum neçə tenant görəcək? Enterprise müştərinin customization tələbatı nədir?"

**Nə dəyişirdim:**
2021-dəki özümə deyərdim: ilk 6 ayda schema-per-tenant arxitekturasını spike et — 1 sprint vaxt al, iki yanaşmanı compare et. Konkret hesab: 20 tenant-a migration 60 saat çəkərdi, 120 tenant-a migration 280 saat çəkdi. 220 saatlıq fərq = ~$33,000 opportunity cost.

**Sonrakı layihəyə tətbiq:**
2023-cü ildə yeni micro-SaaS feature üçün tenant isolation qərarı vermək lazım idi. Bu dəfə "scale envelope" etdim: "Bu sistem maksimum neçə tenant görəcək? 50-dən azdırsa, sadə row-level. 50+ tenant olarsa, schema-per-tenant." Bu sual indi arxitektura review-larımın standart hissəsidir.

**Result:**
Köhnə sistem eventually schema-per-tenant-a migrate edildi — 4 aylıq proses, 280 development saati. Əgər başdan schema-per-tenant qursaydıq: ~60 saat. "Technical debt-in real cost-u" artıq mənim üçün abstrakt deyil, real rəqəmdir. Bu dərs sonrakı arxitektura qərarlarımda explicit "scale checkpoint" sorusunu standart hala gətirdi. Şirkətin sonrakı 3 arxitektura qərarında bu sualı qaldırdım — ikisini tez dəyişdirdi. ADR formatında bu qərarı yazdım — yeni engineer-lər indi "niyə schema-per-tenant?" sualını cavabsız qoymur.

---

### Alternativ Ssenari — Sync notification sistemi

Notification sistemi üçün sync HTTP call seçmişdim — "push notification → HTTP call → user device." 10K notification/gün zamanı mükəmməl idi. 100K/gün olduqda timeout-lar başladı — notification service çox connection açdı, DB connection pool tükəndi.

Retrospektiv: async queue (Laravel Horizon + Redis) əvvəldən seçilməliydi. Mən "YAGNI" prinsipi ilə özümü inandırdım — bu halda yanlış idi. Dərs: I/O bound + uncertain growth = async default. Bu prinsip indi decision checklist-imdədir. Emergency refactor 3 gün çəkdi, 2 sprint roadmap gecikdi — əgər əvvəlcədən etseydim, 1 gün çəkərdi.

---

### Zəif Cavab Nümunəsi

"Biz o vaxt microservices seçdik çünki hamı microservices deyirdi. Sonra başa düşdük ki, yanlış idi, monolith daha yaxşı idi. İndi microservices-dən çıxmağa çalışırıq."

**Niyə zəifdər:** Heç bir architectural reasoning yoxdur. "Hamı deyirdi" — passive decision. "Yanlış idi" — context yoxdur. O vaxt niyə düzgün idi? Nə dəyişdi? Nə öyrəndiniz? Trade-off analizi yoxdur. Nə öyrəndiniz gələcək layihə üçün? Bu cavab arxitektura düşüncəsinin olmadığını göstərir.

---

## Praktik Tapşırıqlar

1. **ADR yazma məşq et:** Keçmişdəki bir əhəmiyyətli texniki qərar üçün ADR yaz. Context, alternatives, decision, consequences. Bu formatı interview-da mention et — "mən ADR yazıram, team-ə görünür saxlayıram."

2. **"Scale envelope" analizi:** Keçmiş layihəni götürün. "Bu sistem neçə user/tenant/request-ə qədər scale edə bilər? Bu limitə nə vaxt çatdıq? Əvvəlcədən bilsəydik nəyi dəyişərdik?" Bu analizi STAR hekayəsinə çevirin.

3. **Technical debt inventory:** Hazırki ya keçmiş sistemdə 3 arxitektura decision-ı sıralayın: "o vaxt doğru, indi suboptimal." Hər biri üçün: niyə dəyişmək lazımdır, nə vaxt dəyişdiriləcək, nə qədər vaxt lazımdır?

4. **Məşq sualı:** "You designed a caching layer that worked fine for 6 months, then started causing cache stampede issues at scale. Walk me through the original design and what you changed." — Konkret texniki retrospektiv hazırlayın.

5. **Post-mortem → retrospektiv körpüsü:** Keçmiş bir incident post-mortem-ini götürün. Root cause-unu bir arxitektura qərarı ilə bağlayın. Bu bağlantı interview-da güclü insight göstərir.

6. **"Cost of deferral" hesablaması:** Bir arxitektura dəyişikliyini defer etdiyinizdə real cost nə oldu? Developer-day × rate ilə hesablayın. Bu rəqəm stakeholder-lərə arxitektura investment-ini justify etmək üçün güclü argumentdir.

7. **"Scale checkpoint" sual bank:** Özünüz üçün "hər yeni sistem dizaynında soruşduğum 5 sual" siyahısı hazırlayın. Bunları interview-da "dizayn prosesim" kimi mention edin — sistematik düşünən engineer görünüşü verir.

8. **"Prevented mistake" hekayəsi:** Scale envelope analizi ya da başqa bir proactive check sayəsində arxitektura səhvini başdan önlədiyiniz bir nümunə hazırlayın. Proactive retrospective thinking — reactive-dən daha güclü signal.

---

## Ətraflı Qeydlər

### "Scale envelope" — arxitektura qərarlarında standart sual

Hər yeni sistem dizaynında bu suallar:

```
1. Neçə user/tenant/request?
   - Bu sprint: 10-50
   - 1 ildə: 100-500
   - 3 ildə: 1000+?

2. Data volume?
   - Bu gün: 1K row/gün
   - 1 ildə: 10K row/gün?
   - Bu tabloda total?

3. Latency requirement?
   - Real-time (<1s)?
   - Near-real-time (<5s)?
   - Batch (hourly/daily)?

4. Consistency requirement?
   - Strong consistency (financial)?
   - Eventual consistency (analytics)?

5. Availability requirement?
   - 99.9% (8.7 saat/il down)?
   - 99.99% (52 dəq/il down)?
```

Bu sualları cavablandırmaq arxitektura qərarını kontekstualizasiya edir.

### "Evolutionary architecture" prinsipi

Bir dəfəlik "mükəmməl" arxitektura deyil, dəyişən tələblərə uyğunlaşan arxitektura:

1. **Fitness functions** — arxitekturanın sağlamlığını ölçən metrikalar
2. **Incremental change** — böyük rewrite yox, kiçik addımlar
3. **Guided change** — ADR ilə qərarlar sənədlənir
4. **Coupling minimization** — modular design, loose coupling

Bu termin-ləri interview-da istifadə etmək — "evolutionary architecture düşüncəsinə sahib developer" siqnalı verir.

### Arxitektura pattern retrospektivi — ümumi dərslər

| Pattern | Nə vaxt yaxşı? | Nə vaxt pis? |
|---------|----------------|--------------|
| **Monolith** | Startup, small team, fast iteration | High scale, independent deployment |
| **Microservices** | Large team, independent scaling | Small team, distributed debugging |
| **Shared DB** | Simple, fast start | Multi-team, schema conflicts |
| **Event-driven** | High throughput, loose coupling | Consistency tələb olduqda |
| **Sync HTTP** | Simple, low volume | High I/O, timeouts |
| **Row-level tenant isolation** | <50 tenants | >100 tenants, customization |

Bu cədvəl retrospektiv hekayənizi daha strukturlu edir.

### ADR nümunəsi — multi-tenant isolation

```markdown
## ADR-004: Multi-tenant Data Isolation Strategy

**Status:** Superseded by ADR-012

**Context:**
2021-ci ildə 10-20 tenant gözlənirdi. Speed kritik idi.

**Options:**
1. Row-level (tenant_id column) — Simple, fast, team bilir
2. Schema-per-tenant — Isolation güclü, migration müstəqil
3. DB-per-tenant — Ən güclü isolation, ən baha

**Decision:**
Row-level isolation seçildi. Team capacity, timeline, initial scale.

**Consequences:**
+ 2 həftəyə MVP çıxdı
+ Team learning curve minimal idi
- 120 tenant olduqda noisy neighbor yarandı (ADR-012 ilə həll edildi)
- Migration sarğıları artdı (gh-ost tələb olundu)
```

Bu ADR retrospektiv hekayənizi konkretləşdirir — "o vaxt niyə belə qərar verdim" izahı verir.

### Retrospektiv hekayəsinin final cümləsi

STAR-ın Result hissəsini gücləndirmək üçün 3 element:
1. **Texniki nəticə:** "Schema-per-tenant migration 60 saat əvəzinə 280 saat çəkdi."
2. **Business nəticə:** "Bu gecikmə $33K opportunity cost yaratdı."
3. **Sistemik öyrənmə:** "İndi hər yeni feature üçün scale envelope sualları ilk sprint-in bir hissəsidir."

Bu üçlük — "nə baş verdi + nə itirdik + nə dəyişdi" — yetkin engineer portretini çəkir.

---

## Əlaqəli Mövzular

- `03-greatest-technical-challenge.md` — Çətin texniki problem + öyrənilmiş dərs
- `06-managing-technical-debt.md` — Yanlış arxitektura qərarının borclara çevrilməsi
- `10-incident-handling.md` — Incident-in arxitektura qarar retrospektivinə çevrilməsi
- `08-estimation-planning.md` — Retrospektiv məlumatın planlamaya tətbiqi
- `14-ambiguous-requirements.md` — Qeyri-müəyyən spec-in arxitekturada nəticəsi
