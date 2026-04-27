# Estimation and Planning (Senior ⭐⭐⭐)

## İcmal

Estimation and planning sualları interviewerin sizin mürəkkəb işi necə parçaladığınızı, qeyri-müəyyənliyə necə yanaşdığınızı və realistic deadline-lar necə qoyduğunuzu anlamaq üçün verilir. "Bu feature-u nə vaxt bitirərsiniz?", "Sprint planlamasını necə edirsiniz?" kimi suallar bilavasitə bu kateqoriyaya aiddir.

Senior developer-lər üçün bu bacarıq xüsusilə vacibdir, çünki pis estimation bütün komandanı, product roadmap-i və müştəri gözləntiləsini mənfi təsir edir. "2 günə bitirərəm" deyib 2 həftə işləmək — professional repurasiya üçün çox zərərlidir.

Estimation mükəmməl olmayacaq — bu qəbul edilə bilər. Lakin "estimation yoxdur" ya da "gecə qalıb tamamladım" — qəbul edilmir.

---

## Niyə Vacibdir

Interviewerlər bu sualı soruşarkən texniki bilikdən artıq **engineering judgment** ölçürlər. Bir developer nə qədər yaxşı kod yazsa da, estimation-ları daima yanılırsısa, stakeholder-lər ona güvənmir. Bu sual həmçinin sizin unknown-ları necə idarə etdiyinizi, risk-ləri necə müəyyən etdiyinizi, "buffer niyə lazımdır" sualını izah edə bildiyinizi yoxlayır.

Lead developer-lər üçün bu daha kritikdir: siz yalnız öz estimation-ınızı deyil, komandanın estimation prosesini də idarə edirsiniz.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Decomposition** — epic → story → task; monolith estimation yox, parçalanmış iş
- **Known vs unknown fərqi** — "bu API-nin dokumentasiyasını görməmişəm, +2 gün buffer"
- **Buffer əsaslandırması** — niyə buffer lazımdır, "just in case" deyil
- **Stakeholder communication** — estimate-i necə bildirirsiniz, nə vaxt revize edirsiniz
- **Range vs point estimation** — "3–5 gün, X olarsa 7 günə çıxa bilər"
- **Historical data** — velocity, past sprint data-sından istifadə
- **Risk identification** — nə "gedə bilər yanlış", nə qədər ehtimal, nə qədər impact
- **Early warning** — sprint ortasında blocker görünsə, dərhal bildirmək

### 2. Red flags — zəif cavabın əlamətləri

- "Baxıb söyləyərəm" — heç düşünmədən cavab vermək
- Buffer-siz "dəqiq" rəqəmlər — "4 gün, dəqiq" deyən developer
- Tək-başına estimate etmək, komanda input-unu almamaq
- Technical debt, review, testing vaxtını unutmaq
- "Manager deyir ki bu vaxtda bitsin, bitirərik" mentaliteti — passive, ownership yoxdur
- Yanıldıqda manager-ə deyilmədən işin lap sonuna qədər gizlətmək
- "Gecə qalıb bitirdim" — burnout culture-u normallaşdırır

### 3. Green signals — güclü cavabın əlamətləri

- Top-down + bottom-up estimation kombinasiyası
- Explicit assumptions sıralamaq: "X var deyə götürürəm, əgər yox olarsa..."
- Risk-ə görə range vermək: "3–5 gün, Y blocker olarsa 7 günə çıxa bilər"
- Tradeoff-ları açıq danışmaq: "scope cut vs deadline uzatmaq, hansını seçirik?"
- Historical velocity data-sından istifadə
- Estimation-ı iteration üzrə refine etmək — "sprint 2-dən sonra daha dəqiq söyləyəcəm"
- "Early warning" — blocker gördükdə dərhal bildirmək

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Prioritet |
|-------------|-----------|
| **Startup** | Timeline kritikdir — tez estimate, tez feedback |
| **Enterprise** | Formal WBS, JIRA story points, PRINCE2/Agile mix |
| **FAANG** | Engineering rigor — T-shirt sizing, confidence interval |
| **Booking.com, Atlassian** | Historical velocity, planning poker, sprint velocity |

### 5. Seniority səviyyəsinə görə gözlənti

| Səviyyə | Estimation scope |
|---------|-----------------|
| **Senior** | Öz feature-larının estimation-ı, risk-ləri aşkarlama |
| **Lead** | Team-in estimation prosesini idarə etmək, capacity planning |
| **Staff** | Quarter-level planning, OKR-lara texniki estimate |

### 6. Estimation-ın psixologiyası

- **Optimism bias:** developer-lər həmişə underestimate edir — "best case" scenario üzərindən düşünür
- **Planning fallacy:** "bu dəfə fərqli olacaq" inanışı — amma olmur
- **Buffer-i gizlətmək:** "manager buffer görüb kəsəcək" qorxusu — əksinə şəffaf buffer daha güvənilir göstərir
- **Integration time:** kod yazmaq + test + review + deploy + hotfix = real time

---

## Praktik Baxış

### Cavabı necə qurmaq

STAR formatını istifadə et, amma **"Action"** hissəsini genişləndir — konkret estimation texnikasından danış (story points, T-shirt sizing, 3-point estimation). Sadəcə "estimate etdim, düz çıxdı" kifayət deyil.

### Estimation prosesi — 5 addım

1. **Decompose** — epic → story → task. Hər task 1–4 saatdan çox olmamalıdır
2. **3-point estimate** — Optimistic / Most Likely / Pessimistic hər task üçün
3. **Risk identification** — unknown-ları müəyyən et, hər birinə buffer qoy
4. **Communicate** — range ilə ver, assumptions-ları açıq söylə
5. **Track and revise** — sprint ərzində yenilə, early warning ver

### Estimation texnikaları

| Texnika | İstifadə vaxtı |
|---------|----------------|
| **Story points + Planning Poker** | Team estimation, relative complexity |
| **T-shirt sizing** | Rough estimate, early stage planning |
| **3-point estimation** | Risk var, variance yüksəkdir |
| **Historical velocity** | Recurring task-lar, sprint planning |
| **Decomposition-based** | Complex feature, detailed planning |

### Yanıldıqda nə etmək

1. Erkən xəbər ver — sprint-in ortasında görürsənsə, dərhal söylə
2. Root cause izah et — niyə yanıldı?
3. Yeni estimate ver — revize edilmiş plan
4. Trade-off təklif et — "scope azaltmaq" ya "deadline uzatmaq"

### Tez-tez soruşulan follow-up suallar

1. **"When your estimate was wrong, how did you communicate it to your manager?"** — "Sprint-in ortasında gördüm — dərhal manager-ə dedim, yeni estimate verdim, trade-off seçimini mən verməyin onlara yönləndirdim"
2. **"Story points vs hours — which do you prefer and why?"** — Story point: relative complexity ölçür, team velocity-yə uyğunlaşır. Hours: stakeholder-ə daha aydın. Kontekstə görə seçilir.
3. **"How do you handle team planning poker when estimates are very different?"** — "Fərqi aydınlaşdırıram — 'sən 2 point dedinsə, mən 8 dedim, niyə fərq var?' — bu discussion-dan hidden assumption-lar çıxır"
4. **"One week left of a two-week sprint and you're 50% done. What do you do?"** — "Dərhal manager-ə deyirəm. Trade-off seçim verirəm: scope kəsmək vs deadline uzatmaq. Özüm qərar vermirəm."
5. **"How do you estimate work you've never done before?"** — "Spike sprint edirəm — 1-2 günlük araşdırma. Sonra estimation. Alternativ: oxşar işin historical velocity-sindən analogy."
6. **"What's your buffer policy for unknowns?"** — "Məlum unknown-lar üçün explicit buffer. 3-point estimation: (O + 4M + P) / 6 = expected. Variance görə buffer qoy."
7. **"How do you prevent scope creep from killing your estimates?"** — "Requirement clarification öncədən. Sprint goal aydın. Mid-sprint change request = yeni ticket, yeni sprint."

### Nə deyilməsin

- "Manager-in dediyini qəbul edirəm" — ownership yoxdur
- "Heç yanılmıram" — inandırıcı deyil
- "Estimate-i test olmadan verdim" — professional deyil
- "Gecə qalıb bitirdim" — burnout culture-u normallaşdırır, estimation skill-ini gizlədır

---

## Nümunələr

### Tipik Interview Sualı

"Tell me about a time when your estimate was significantly off. What happened and what did you learn?"

---

### Güclü Cavab (STAR formatında)

**Situation:**
B2B SaaS şirkətdə payment integration layihəsi üzərində işləyirdim. Mövcud Stripe inteqrasiyamıza əlavə olaraq PayPal və lokal ödəniş provider-i (AzərGold) qoşmaq lazım idi. Product manager sprint planning-dən əvvəl sordu: "Bu feature üçün nə qədər vaxt lazımdır?" — sprint 2 həftədir. Komandada daha bir mid developer var idi, lakin critical payment logic mənim məsuliyyətimdə idi. Marketing artıq "multi-payment" feature-ı Q3 release-ə daxil etmişdi.

**Task:**
Mən həm estimation-ı hazırlamalı, həm də feature-u implement etməliydim. Estimation-ın dəqiqliyi PM-in sprint planning-inə, müştəri demo schedule-ına, marketing announce-una birbaşa təsir edirdi.

**Action:**
Əvvəlcə feature-u decompose etdim — task list yaratdım:
1. PaymentGateway abstract interface — 1 gün
2. PayPal SDK integration + webhook + idempotency keys — 2 gün
3. AzərGold API dokumentasiyasını oxumaq + integration — ?, çünki yeni API, heç kim istifadə etməmişdi
4. Database schema: `payment_methods`, `payment_attempts` cədvəlləri — 0.5 gün
5. Retry logic + failure handling + dead-letter queue — 1 gün
6. Unit + integration testlər — 1.5 gün
7. Staging manual QA — 0.5 gün
8. Code review + fixes — 1 gün

Bu task list-dən 3-point estimation etdim: AzərGold üçün "most likely" 3 gün, amma "pessimistic" 6 gün — çünki API dokumentasiyasını görmədim, integration-da surprise ola bilərdi. Ümumi estimate: 8–12 gün (2 həftə sprint üçün mümkün, amma AzərGold riski var).

Product manager-ə range ilə bildirdim: "2 sprint-ə plan edək. Birinci sprint-də Stripe abstraction + PayPal, ikinci sprint-də AzərGold + QA. Əgər hər şey düz getsə 1 sprint-də tamamlaya bilərik, amma AzərGold risk faktorudur. Documentation-u görməmişəm." PM bu framing-ə razı oldu.

Nəticədə AzərGold API-da undocumented rate limiting var idi — 429 error-ları test-də gördüm. Retry logic əlavə 2 gün çəkdi. Amma mən əvvəlcədən riski bildirdiyim üçün stakeholder-lər hazırlıqlı idi. Sprint ortasında PM-ə yazdım: "AzərGold rate limiting materiallaşdı — variant B (2 sprint) aktivdir."

**Result:**
Layihə 12 gündə tamamlandı — pessimistic estimate-in içindəydi. AzərGold rate limiting bug-ı production-da deyil, staging-də tapıldı. PM "gözlənilməz gecikmə" kimi deyil, "planlaşdırılmış risk materiallaşdı" kimi aldı — bu fərq böyükdür. Bundan sonra komandada "unknown API = +30% buffer" qaydası qoyuldu. Həmin sprint-dən sonra PM özü estimation meeting-ə risk discussion əlavə etdi.

---

### Alternativ Ssenari — Junior-un estimation workshop-u

Sprint planning-da junior developer "3 saata bitirəm" dedi, halbuki feature Redux state management refactor + 15 component update tələb edirdi — bu 2 günlük iş idi.

Mən araları girərək 3-point estimation workshop keçirdim: hər task üçün best/worst case yazdıq, planning poker oynadıq. Junior "3 saat" dedi, mən "3–5 gün" dedim. Fərqi aydınlaşdırdım: "Refactor + 15 component + test = hər component 30 dəqiqə × 15 = 7.5 saat yalnız refactor, testlər daxil deyil."

Final estimate: 2 gün. Faktiki: 1.5 gün. Junior-un özünə olan confidence-i artdı, planning daha realist oldu. Bu işi sprint planning prosesinin hissəsinə çevirdim — indi hər feature üçün brief decomposition etmədən story point qoymuruq.

---

### Zəif Cavab Nümunəsi

"Mən həmişə manager-in verdiyi deadline-a uyğun işləyirəm. Əgər çox iş var, gecə qalıb işləyirəm. Estimate yanılıbsa gecə qalıb bitirmişəm. Deadline tutmaq menim üçün şərəf məsələsidir."

**Niyə zəifdər:** Bu cavab burnout culture-u normallaşdırır. Estimation skill-i sıfırdır. "Deadline tutmaq" şərəf kimi göstərmək həqiqətə uyğun planning-i gizlədır. Manager bu developer-i gözləntiləri idarə edən biri kimi deyil, "gün-gündən həll edən" biri kimi görür. Uzunmüddətli bu pattern sustainable deyil. "Gecə qalıb bitirdim" cümləsi həm sağlamlıq problemi, həm estimation problemi, həm communication problemi göstərir.

---

## Praktik Tapşırıqlar

1. **Story decomposition exercise:** Növbəti feature-u götür. 30 dəqiqəyə task-lara parçala, hər task üçün 3-point estimate yaz. Sonra faktiki vaxtla müqayisə et. Fərqin nə qədər olduğunu ölçün.

2. **Yanıldığın bir estimation-ı xatırla:** STAR formatında yazıb məşq et. Hansı unknown-ları qaçırdın? Əgər yenidən olsaydı nə edərdin?

3. **"What if" ssenarisi hazırla:** Interview-da "estimation-ı necə verirsiniz?" sualı üçün 5 addımlı prosesi yadda saxla: decompose → 3-point → risks → communicate as range → track.

4. **Məşq sualı:** "You need to estimate building a real-time notification system with push, email, and SMS. Walk me through your process." — Bu suala 3 dəqiqəlik cavab hazırla. Hər komponenti parçala, unknown-ları aşkarda tut.

5. **"Wrong estimate" recovery plan:** "Estimate yanıldı, 1 həftə qaldı, 40% qalıb" — nə edirsən? 3 seçim: scope cut, deadline uzat, overtime. Hər birinin trade-off-unu hazırla, interview-da göstər.

6. **Velocity tracking:** Son 3–4 sprint-inizin actual vs estimate rəqəmlərini tutun. Sistematik pattern varmı? (Həmişə underestimate? Həmişə overestimate?) Bu pattern-i interview-da mention etmək self-awareness göstərir.

7. **Buffer policy yaz:** Özünüz üçün explicit buffer policy hazırlayın: "Known unknown: +20%. Unknown unknown: +30%. Third-party API: +40%." Bu policy-i interview-da "mən belə approach edirəm" kimi mention edin.

8. **"Communication template" hazırla:** Estimation yanıldığında manager-ə nə yazarsınız? Template hazırlayın: "Sprint X-in ortasındayıq. Y task Z gündə bitmir, çünki [reason]. Yeni estimate: [date]. Seçimlər: A (scope kəs), B (deadline uzat). Tövsiyə: A, çünki [reason]." Bu template professional communication göstərir.

---

## Ətraflı Qeydlər

### 3-Point Estimation formula

PERT (Program Evaluation and Review Technique):
```
Expected = (Optimistic + 4 × Most Likely + Pessimistic) / 6
Standard Deviation = (Pessimistic - Optimistic) / 6
```

Nümunə:
- Optimistic: 2 gün
- Most Likely: 4 gün
- Pessimistic: 9 gün
- Expected: (2 + 16 + 9) / 6 = **4.5 gün**
- Std Dev: (9 - 2) / 6 = **1.2 gün**

"4.5 ± 1.2 gün" — range daha dürüst, point estimate-dən daha güvənilir.

### Story point calibration

Komandanın velocity-sini calibrate etmək üçün:
1. Anchor story tapın — "login feature = 3 point" (komanda razılaşır)
2. Yeni story-ləri bu anchor-a görə qiymətləndirin (relative complexity)
3. 3 sprint sonra velocity hesablayın: delivered points / sprint
4. Bu velocity-dən gəcəcək sprinti planlaşdırın

Velocity = capacity planlama üçün əsas metrik. "Neçə feature bu sprintdə olacaq?" deyil, "neçə point capacity var?" soruşun.

### Estimation anti-pattern-lər

| Anti-pattern | Problem | Həll |
|-------------|---------|------|
| "1 gün" (çox qısa) | Confidence illusion | 3-point estimate |
| "2-3 həftə" (çox uzun, belirsiz) | Parkinson's Law | Task decomposition |
| "Manager istədiyi qədər" | Ownership yoxdur | Data-driven pushback |
| "Əvvəlki kimi" | Context fərqlidir | Historical + adjustment |
| Buffer yox | Riskli | Explicit buffer |
| Testing vaxtı yox | Underestimate | Test = estimate-in hissəsi |

### Sprint capacity formula

Real capacity planlaması:
```
Sprint capacity = Team size × Sprint days × Focus factor
Focus factor = 0.6-0.8 (meetings, interruptions, sick days)

Nümunə: 3 developer × 10 gün × 0.7 = 21 developer-day
Story points-ə çevirmək üçün: 21 developer-day × (points/day) = total points
```

Bu formula "hamı tam 10 gün iş edir" yanılgısını aradan qaldırır.

### Estimation-ı iterate etmək — rolling wave

Layihə başında bütün feature-ları dəqiq estimate etmək mümkün deyil. Rolling wave planlaması:

```
Sprint 1-2:  Tam dəqiq estimate (task-level decomposition)
Sprint 3-4:  Orta dəqiqlik (story-level estimate)
Sprint 5+:   Epik-level rəqəm (±50% tolerance ilə)
```

"İndi bilmirəm, amma öyrəndikdə yeniləyəcəm" — bu cümlə senior signal verir. Junior "hər şeyi indi estimate edirəm" deyir.

### Estimation kommunikasiyası — stakeholder-ə necə çatdırılır?

Texniki estimate-i non-technical stakeholder-ə çatdırmaq üçün:

1. **Rəqəm + əminlik faizi:** "3 həftə — 70% əminliyimlə"
2. **Risk toggle:** "Əgər X riskləşsə +1 həftə"
3. **Dependency list:** "Bu estimate Y team-in API-si hazır olduğunu fərz edir"
4. **Milestone structure:** "Həftə 1-də MVP, həftə 2-3-də production-ready"

"2 həftə" deyib susmaqdansa bu çərçivə müsahibəçiyə planning maturity göstərir.

### Yanlış estimate — retrospektiv analiz

Post-mortem: estimate 2x yanlış çıxdıqda nə öyrənilir?
- **Root cause tipi:** scope creep / unknown dependency / technical debt / team capacity error
- **Corrective action:** buffer artırıldı / spike sprint əlavə edildi / better decomposition
- **Process improvement:** Estimation meeting strukturu yeniləndi, dependency map əlavə edildi

Yanlış estimate-i interview-da "failure" deyil — "öyrənmə" kimi frame edin.

### Estimation hekayəsini şirkət tipinə uyğunlaşdırmaq

**FAANG:** "3-point estimation + spike sprint" — process maturity göstərir. Data-driven qərar mühiti.

**Startup:** "2 həftəlik estimate 2 gündə çatdırmaq — MVP first, polish sonra." Sürət > dəqiqlik.

**Enterprise:** "Formal SOW (Statement of Work) — hüquqi öhdəliklər var." Conservative estimate, formal risk register.

Müsahibə şirkətinin tipini araşdırın — estimation filosofiyası kontekstdən asılıdır.

---

## Əlaqəli Mövzular

- `07-failure-lessons.md` — Yanılmış estimate-dən öyrənmə
- `14-ambiguous-requirements.md` — Tələblər aydın olmadıqda estimate necə verilir
- `06-managing-technical-debt.md` — Tech debt estimation-a necə daxil edilir
- `15-system-design-retrospective.md` — Planlaşdırma retrospektivi
- `11-cross-functional-collab.md` — Stakeholder-lərlə expectation management
