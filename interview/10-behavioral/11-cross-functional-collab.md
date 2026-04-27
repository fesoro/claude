# Cross-Functional Collaboration (Lead ⭐⭐⭐⭐)

## İcmal

Cross-functional collaboration sualları interviewerin sizi engineering sferası xaricindəki insanlarla — product managers, designers, sales, legal, data — necə işlədiyinizi anlamaq üçün soruşulur. Lead səviyyəsindəki developer yalnız kodla deyil, müxtəlif background-dan olan stakeholder-lərlə alignment yaratmaqla məşğul olur.

"Product manager-lə razılaşmadığınız bir vəziyyəti danışın", "Technical requirements-ı non-technical audience-a necə çatdırırsınız?" tipli suallar bu kateqoriyadandır.

Əsas prinsip: siz engineer deyil, translator-sunuz. Texniki həqiqəti business dilinə, business məhdudiyyətlərini texniki qərara çevirə bilmək — bu Lead-in əsas dəyəridir.

---

## Niyə Vacibdir

Lead engineer-in performansı artıq yalnız kod keyfiyyəti ilə ölçülmür — həm də team alignment, cross-team dependency management, business goal-larla texniki qərarların uyğunluğu ilə ölçülür. Engineering vacuum-da qalan lead-lər yaxşı texniki qərarlar alır, lakin şirkətin istiqamətindən kənara düşür. Cross-functional skill olmadan lead-lər bottleneck yaradır.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Müxtəlif "dil"lərdə danışmaq** — engineer-ə technical depth, PM-ə business impact, designer-a UX feasibility, legal-a risk
- **Conflict navigation** — "xeyir" demək yerinə tradeoff izah etmək, alternativ təklif etmək
- **Alignment yaratmaq** — güc deyil, anlayış vasitəsilə
- **Non-technical constraint-ləri texniki kontekstə çevirmək** — "legal GDPR deyir" = "biz PII-ni encrypt etməliyik"
- **Business context** — şirkətin strategiyasını başa düşmək, texniki qərarı bunla align etmək
- **Uzunmüddətli relationship** — bir-dəfəlik "qazan" deyil, trusted partner
- **Empathy** — PM-in də öz boss-u var; şirkətin timeline-ı var; market pressure var

### 2. Red flags — zəif cavabın əlamətləri

- "PM-lər işimizi başa düşmürlər, onlarla çətin işləmək olur" — empathy yoxdur
- "Texniki qərarları mən verirəm, onların fikri lazım deyil" — silo mentality
- Hər xahişə "xeyir" demək — şəxsi texniki agenda
- Business deadline-ları tamam ignore etmək
- Conflict-i eskalasiya etmədən birbaşa CTO-ya aparmaq — process bypass
- "Onlar nə istədiyini bilmirlər" — arrogance

### 3. Green signals — güclü cavabın əlamətləri

- Technical tradeoff-ları biznes dili ilə izah etmək — "bu 3 gün deyil, 3 sprint çəkər, çünki..."
- PM/Designer-ı şagird kimi deyil, partner kimi görmək
- "Xeyir, amma..." əvəzinə "əgər bu lazımdırsa, bu 3 variant var"
- Özünü stakeholder-in yerinə qoymaq — "PM niyə bunu istəyir?"
- Data ilə razılaşma — "A/B test etsək nə öyrənərik?"
- Long-term trust qurmaq — "PM mənim dediyimə güvənir, çünki..."

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Fokus |
|-------------|-------|
| **Notion, Figma, Shopify** | Product-engineering-design üçlüsünün uyumu |
| **Startup growth stage** | PM + engineering alignment — tez qərar, tez ship |
| **Enterprise** | IT + business alignment, governance |
| **Fintech** | Legal + compliance + engineering balansı |

### 5. Seniority səviyyəsinə görə gözlənti

| Səviyyə | Cross-functional scope |
|---------|----------------------|
| **Senior** | PM ilə feature clarification, designer ilə implementation |
| **Lead** | Multi-team alignment, dependency management, roadmap influence |
| **Staff** | Executive-level communication, company strategy alignment |

### 6. "Non-technical audience" üçün danışmaq texnikası

Engineer-lərin ən çox etdiyi səhv: texniki dəqiqlik güdərək başqasını itirmək. PM "real-time" deyəndə o "2 saniyə" deməyir, "daha tez" deməkdir. Bu fərqi anlamaq alignment-ın açarıdır.

Üç sual soruşun:
- "Bu feature niyə lazımdır?" — business driver
- "Bu olmasaydı nə baş verərdi?" — problem statement
- "Nə vaxt 'uğur' deyəcəksiniz?" — success metric

Bu suallar əsl tələbi üzə çıxarır.

### 7. "Xeyir" deməyin alternativləri

Birbaşa "xeyir" demək əvəzinə:
- "Bu iş x həftə çəkər. Prioritet verin ki hansı featuredən kəsim?"
- "Bu variant var: A (tam feature, 4 sprint), B (MVP, 1 sprint). Hansını seçirsiz?"
- "Texniki constraint-i izah edim, sonra birlikdə alternativ axtaraq"

---

## Praktik Baxış

### Cavabı necə qurmaq

STAR formatı yaxşı işləyir, amma "Action" hissəsini **communication strategy**-yə fokusla. Texniki qərar necə verdindən artıq — fərqli perspektivləri necə eşitdin, consensus necə yaratdın. Conflict qısa olsun, solution uzun olsun.

### Texniki constraint-ləri non-technical dildə izah etmək

**Pis:** "Real-time demək event-streaming pipeline lazımdır, mövcud RDBMS bu throughput-u dəstəkləmir."

**Yaxşı:** "2 saniyə dedikdə mənə bu demək olur: hər transaction baş verdikdən dərhal sonra hesablama lazımdır. Mövcud sistemimiz hesablamanı hər 10 saniyədə bir edir. 2 saniyəyə endirmək üçün yeni bir mexanizm qurmalıyıq — bu 3 sprint-lik iş. 10 saniyə ilə gedə bilərik bu sprint-də, 2 saniyəyə sonra keçərik."

### "3 variant" frameworku

Hər cross-functional konflikti üç variant cədvəli ilə həll edin:

```
| Variant | Nə verir? | Nə tələb edir? | Timeline |
|---------|-----------|----------------|---------|
| A       | ...       | ...            | ...     |
| B       | ...       | ...            | ...     |
| C       | ...       | ...            | ...     |
```

Non-technical stakeholder seçim edir — siz seçimi etmirsiniz. Bu empowerment PM ilə trust yaradır.

### Tez-tez soruşulan follow-up suallar

1. **"A PM gives you an unrealistic timeline. What do you do?"** — "Əvvəlcə anlamağa çalışıram niyə bu timeline. PM-in external constraint-i ola bilər. Sonra feature-u decompose edirəm, MVP-ni identify edirəm. 'Bu 3 variant var — deadline-ı tutmaq üçün scope kəsmək lazımdır. Hansını kəsərik?'"
2. **"How do you communicate technical debt to non-technical stakeholders?"** — "Dollar cost-u ilə: 'Bu module-a toxunan hər feature 2x uzun çəkir — bu rübdə 12 developer-day = $18K opportunity cost.'"
3. **"Designer wants something technically very expensive — how do you handle it?"** — "Əvvəlcə UX məqsədini anlayıram. Sonra 'bu visual effekti 3 fərqli yolla əldə edə bilərik' deyirəm. Dizaynerin məqsədi: UX. Mənim məqsədim: implementasiya. İkisi arasında bridge taparıq."
4. **"Two departments want the same engineering resource — how do you prioritize?"** — "Hər department-in business priority-sini yazılı alıram. Impact × urgency matrix qururum. Qərarı mən vermirəm — manager-lər birlikdə verir, mən məlumat verirəm."
5. **"How do you build trust with PM/designer over time?"** — "1) Söz verirəm, tuturam. 2) Problemləri erkən bildirirəm. 3) 'Xeyir' əvəzinə alternativ verirəm. 4) Onların domain-indən bir şey öyrənirəm."
6. **"Have you ever pushed back on a business decision that you thought was wrong?"** — "Bəli — data ilə. 'Bu feature build etməkdən əvvəl A/B test etsək nə öyrənərik?' Bu framing daha yaxşı işləyir."
7. **"How do you handle scope creep from the PM side?"** — "Sprint goal-ı yazılı saxlayıram. Mid-sprint request = yeni ticket, scope tradeoff conversation. 'Bu əlavə edilsə, X kəsilməlidir.'"

### Nə deyilməsin

- "Mən haqlı idim, onlar başa düşmürdü"
- "Güzəştə getdim ki, PM razı olsun, amma texniki cəhətdən pis qərar idi"
- "Hər dəfə konflikti manager-ə eskalasiya edirəm"
- "PM-in biznes qərarına qarışmıram, o da texniki qərara qarışmasın"

---

## Nümunələr

### Tipik Interview Sualı

"Tell me about a time you had to work closely with non-engineering stakeholders on a complex decision. How did you navigate disagreements?"

---

### Güclü Cavab (STAR formatında)

**Situation:**
FinTech startupda lead backend developer idim. Product Manager yeni bir feature istədi: "Real-time spend analytics dashboard — hər transaction-dan sonra 2 saniyə ərzində user-in spending category breakdown-ı yenilənsin." Bu Q3-ün prioritet feature-u idi, marketing artıq teaser göndərmişdi, müştərilər gözləyirdi. PM-in boss-u bu feature-ı conference-da mention etmişdi — external commitment yaranmışdı.

**Task:**
Feature-un texniki tərəfini lead etməliydim. İlk analiz etdikdə ciddi problem gördüm: mövcud DB schema kategoriyalar üçün real-time aggregation-ı dəstəkləmirdi. Əvvəlki arxitektura cold query-lərlə işləyirdi — hər dashboard refresh-ında DB scan edirdi. Real-time = event-driven architecture, Redis stream, ya incremental aggregation. Bu 2–3 sprint-lik iş idi, PM 1 sprint hesablayırdı.

**Task çatışmazlığı:** PM ilə birbaşa "bu olmaz" deyə danışsam, PM-in boss-u qarşısında problem yarada bilərdi. Diplomatik amma dürüst olmaq lazım idi.

**Action:**
PM-i rədd etmək yerinə, **"technical feasibility" meeting** çağırdım — PM, designer, data analyst, mən. Məqsəd: seçim etmək, mübahisə etmək deyil.

Əvvəlcə problemi biznes dilindəki consequence-larla izah etdim: "Real-time 2 saniyə dedikdə hər transaction baş verdikdən 2 saniyə sonra dashboard-u yeniləməliyik. Mövcud sistemimiz bunu 8–12 saniyəyə edir. 2 saniyəyə endirmək üçün event-streaming pipeline qurmalıyıq."

Sonra 3 variant təklif etdim — texniki jargon olmadan:

| Variant | User experience | Engineering effort | Launch timeline |
|---------|-----------------|-------------------|----------------|
| A — Polling (hazır) | 10–15 sn delay | Sıfır | Bu sprint |
| B — Incremental cache | 3–5 sn delay | Orta | 1 sprint |
| C — Event streaming | <2 sn delay | Böyük | 3 sprint |

PM dedi: "Marketing '2 saniyə' demişdir. Dəyişə bilmərik." Mən: "Anladım. Onda sual budur: launch-ı 2 sprint gecikdirib C seçirik, ya B ilə launch edirik, sonra C-yə upgrade edirik? B-ni 'Fast Analytics' kimi promote edə bilərik — rəqiblərimiz hələ real-time yoxdur."

Data analyst əlavə etdi: "İstifadəçilər dashboard-da anyway scroll edirlər — 3-5 saniyə fərqi real behavior-da hiss olunmaz." Bu obyektiv input-u qeyd etdim — PM-in qərar vermək üçün daha çox məlumatı var idi.

PM bir gün düşündü, marketing ilə danışdı. Qərar: Variant B launch, Variant C roadmap-a Q4-da əlavə.

**Result:**
Feature 1 sprint-də launch oldu. 3–4 saniyə delay istifadəçilərdən heç bir şikayət yaratmadı (A/B test etdik — engagement metrics eyni idi). 3 ay sonra Variant C implement edildi, latency 1.2 saniyəyə düşdü. Amma daha əhəmiyyətlisi: PM ilə trust quruldu. O sonra dedi: "Siz problemi pərdə arxasında həll etmirsiniz, bizə real seçim verirsiniz." Sonrakı feature planlamalarına texniki feasibility review mərhələsi əlavə edildi.

---

### Alternativ Ssenari — Legal ilə anlaşmazlıq

Legal team GDPR compliance üçün bütün user data-nı 30 gündən sonra silmək tələb etdi. Bu analytics pipeline-ı tamamilə sırırdı — historical report-lar yox olacaqdı. Mən legal team-ə texniki alternativ göstərdim: data anonymization — PII sil, amma aggregated stats saxla. Legal counsel ilə meeting keçirdim, GDPR Article 89-u birlikdə oxuduq (research + statistical use-case). Razılaşma: anonymized aggregated data saxlamaq legal idi. Həm compliance, həm analytics saxlandı. Nəticə: legal conflict texniki alternativ ilə həll edildi, heç bir tərəf "qazan-uduz" yaşamadı. Bu hadisə məni GDPR-ın texniki implementasiyasını daha dərindən öyrənməyə vadar etdi.

---

### Zəif Cavab Nümunəsi

"Product manager həmişə qeyri-realist şeylər istəyir. Mən texniki olmayan adamlara qərar verməyin nə demək olduğunu izah edirəm. Çox vaxt manager-i çağırıb müdaxilə etdiririk."

**Niyə zəifdər:** "Qeyri-realist" — PM-in perspektivini başa düşmürsünüz. "Onlara qərar verməyin nə demək olduğunu izah edirəm" — arrogance. Manager-ə eskalasiya = birbaşa conflict resolution bacarığı yoxdur. Heç bir empathy, heç bir collaborative approach yoxdur. "Çox vaxt" — bu recurring problem, siz həll etməmisiniz.

---

## Praktik Tapşırıqlar

1. **"Texniki qərarı non-technical izah et" tapşırığı:** Ən son mürəkkəb texniki qərarınızı götürün (cache invalidation, DB schema migration). Bunu PM-ə izah edəcəkmiş kimi 5 cümləylə yazın. Technical jargon sıfır.

2. **Cross-functional conflict hekayəsi yaz:** PM, designer, ya business ilə texniki razılaşmazlıq yaşadığınız bir situation-ı STAR formatında yazın. Action hissəsini detallıca: nə dedinsə, onlar nə dedilər, necə razılaşdınız.

3. **"3 variant matrix" məşq et:** Növbəti texniki qərar vermədən əvvəl 3 variant cədvəli qurun: benefit, cost, timeline. Non-technical stakeholder-ə present edin. Reaksiyalarına diqqət edin.

4. **PM-in gününü anla:** PM-in bir gün nə ilə məşğul olduğunu anlamağa çalışın. "Sprint review-da PM nəyi report edir?", "PM-in OKR-ları nədir?" — bu anlayış cross-functional empathy-ni inkişaf etdirir.

5. **"Xeyir" əvəzinə "əgər" məşq et:** Növbəti "bu mümkün deyil" anınızda durub "amma mümkün olmasının şərtləri nələrdir?" soruşun. Bu reframing cross-functional alignment-ın açarıdır.

6. **"Alignment failure" hekayəsi:** Cross-functional uyumsuzluğun problem yaratdığı bir vəziyyəti tapın. Nə öyrəndiniz? Bu interview-da önleme fokusunu göstərir.

7. **"Trust building" hekayəsi:** PM, designer ya başqa non-eng ilə uzunmüddətli əməkdaşlıq nəticəsində "trusted partner" olduğunuz bir keçmiş tapın. Bu relationship-in necə qurulduğunu izah edin — bu Lead-in əsas keyfiyyətidir.

8. **Business context öyrənmə:** Şirkətinizin son quarter-in OKR-larını, PM-in roadmap-ini, marketing-in campaign calendar-ini bilirsinizmi? Bu məlumatı texniki qərarlarda istifadə etdiyinizi göstərmək — "sistemik düşünən engineer" görünüşü verir.

---

## Ətraflı Qeydlər

### "Technical translation" çərçivəsi

Hər texniki konsepti non-technical audience-a izah etmək üçün:
1. **Analogy tapın** — "Cache = kitabxana" (hər kitabı anbardan gətirməyin, tez-tez istifadə edilənlər rafda hazır)
2. **Business consequence göstərin** — "Bu slow query dashboard-u 8 saniyə edir — user 3 saniyədən çox gözləmirsə"
3. **Cost-in** — "Bu debt-i düzəltməsək, Q3 roadmap 2 sprint gec çıxar"
4. **Seçim verin** — "Biz A ya B seçə bilərik — hər birinin business implication-ı budur"

### PM ilə trust qurmaq — 5 davranış

1. **Söylədiyini etmək** — "Sprint-də deliver edəcəm" = sprint-də deliver et
2. **Gözlənilməzlikdən əvvəl bildirmək** — problem gördükdə dərhal, sprint sonunda deyil
3. **Alternativ verməyi vərdiş etmək** — "xeyir" deyil, "bu variantlar var"
4. **Business language-i öyrənmək** — PM-in OKR-ları, KPI-ları, müştəri segment-ləri
5. **PM-in uğurunu alqışlamaq** — "bu feature istifadəçilərdən çox yaxşı rəy aldı"

### "Technical feasibility" meeting agendası

```
Məqsəd: Qərar vermək, mübahisə deyil

Agenda (60 dəqiqə):
1. Problem statement (5 dəq) — PM təqdim edir
2. Technical constraint-lər (10 dəq) — engineer izah edir, non-technical dildə
3. Variant analizi (20 dəq) — cədvəl: variant/benefit/cost/timeline
4. Q&A (15 dəq) — clarifying questions, hücum deyil
5. Decision (10 dəq) — PM seçir, engineer "nəticəsi nə olacaq" deyir

Output: Yazılı qərar, action items, owner, deadline
```

### GDPR/Legal ilə işləmək — texniki angle

Compliance tələblərini texniki həllə çevirmək:

| Legal tələb | Texniki həll |
|------------|-------------|
| "PII silinsin" | Anonymization vs deletion |
| "Data export" | GDPR Article 20 — data portability endpoint |
| "Audit log" | Immutable audit trail, encrypted |
| "Consent tracking" | Consent events, timestamps, versioned |
| "Right to erasure" | Soft delete + scheduled purge |

Bu mapping-i bilmək legal ilə işdə çox dəyərlidir.

### Cross-functional collaboration — uğursuzluq siqnalları

Mövcud işbirliyini interview-da necə frame edirsiniz:

| Siqnal | Zəif cavab | Güclü cavab |
|--------|-----------|-------------|
| PM ilə münaqişə | "PM həmişə qeyri-realist tələb edir" | "Biz variant cədvəli hazırladıq, birlikdə seçdik" |
| Design feedback | "Design işləmir, mən fix etdim" | "Designer ilə oturub texniki constraint-i izah etdim" |
| Legal/Compliance | "Legal hər şeyi ləngidir" | "Tələbi texniki requirement-ə çevirdim, hamı anladı" |
| QA ilə | "QA test etmir düzgün" | "QA ilə acceptance criteria birlikdə yazdıq" |

"Hamının günahı var" yox — "mənim rolum nə idi?" perspektivi.

### Collaboration-ı ölçmək

Müsahibədə "işbirliyi necə etdiniz?" sualına data ilə cavab:
- "Sprint planning-dən əvvəl PM ilə 30 dəq sync — bu sinxronizasiya sayəsində mid-sprint requirement change 70% azaldı."
- "Design review-lara developer kimi iştirak etdim — 3 UX qərarı texniki reallaşdırılmadan kənarda idi, onları əvvəlcədən düzəltdik."
- "Legal review üçün 1-page texniki summary hazırladım — approval vaxtı 3 həftədən 4 günə düşdü."

Metric olmadan "yaxşı işlədik" iddiası yetərsizdir.

### Şirkət tipinə görə cross-functional dinamika

**Startup:** PM = CEO-nun istəyi. Engineering çox vaxt geridə qalır. "Biz 2 gün içərisində scope-u razılaşdırdıq — PM-in flexibility-si sayəsində." Sürət ön plandadır.

**Enterprise:** Legal, compliance, security hər qərarın tərəfinə baxır. "Sign-off prosesi 4 departament əhatə edirdi — hər birini ayrıca brief etdim." Proses ön plandadır.

**Scale-up:** Data-driven qərarlara keçid. "PM A/B test istədi, mən feature flag infrastructure qurmaq üçün 1 gün xərclədim." Experiment culture ön plandadır.

Bu kontekst fərqini hekayənizdə açıqlamaq şirkətin mühitini başa düşdüyünüzü göstərir.

### Cross-functional hekayəsinin güclü final cümləsi

Cavabı "nə etdim" ilə deyil, "nə dəyişdi" ilə bitirin:

- "Bu işbirliyi sayəsində layihə 2 həftə əvvəl çatdırıldı — PM-in initial deadline-ı tam tutuldu."
- "Legal-in erken loop-a alınması sayəsində 2 compliance issue deployment-dan əvvəl tapıldı."
- "Bu prosesdən sonra team-daxilində 'feasibility first' qaydası yarandı — indi hər böyük feature PM + Engineering + Design birlikdə kickoff edir."

Sistemik təsir göstərmək — individual contribution-dan güclüdür.

---

## Əlaqəli Mövzular

- `04-technical-disagreements.md` — Engineering daxilindəki texniki mübahisələr
- `13-leadership-without-authority.md` — Formal authority olmadan alignment yaratmaq
- `14-ambiguous-requirements.md` — Requirements-ı PM ilə birlikdə clarify etmək
- `08-estimation-planning.md` — Texniki estimation-ı stakeholder-lərə kommunikasiya etmək
- `05-mentoring-juniors.md` — Junior-ları cross-functional işə hazırlamaq
