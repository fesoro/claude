# Dealing with Ambiguous Requirements (Lead ⭐⭐⭐⭐)

## İcmal

Ambiguous requirements sualları interviewerin incomplete, contradictory, ya da belirsiz tələblərlə qarşılaşdığınızda necə davrandığınızı anlamaq üçün verilir. "Tələblər aydın olmadığında nə edirsiniz?", "Bir dəfə tələbsiz layihə başladınızmı?" kimi suallar bu kateqoriyadandır.

Real dünyada mükəmməl spec mövcud deyil — həmişə boşluqlar, ziddiyyətlər, ya bilinməyənlər var. Lead engineer-lər bu boşluqları həll etməkdə key rol oynayırlar.

Ambiguity-ni necə idarə etmək sizi "need to be told what to do" kategoriyasından "drive clarity" kategoriyasına aparır. Bu fərq senior ilə lead arasındakı əsas ayrımdır.

---

## Niyə Vacibdir

Interviewerlər bu sualı soruşarkən bir neçə keyfiyyəti ölçürlər: analysis paralysis-mi yoxsun, wrong assumptions-ı erkən tutub tuta bilirsənmi, stakeholder-ləri düzgün suallarla requirements-ı müəyyən etməyə yönəldə bilirsənmi?

Senior-lardan gözlənilən şey — müəllim gözlənilmədiyi halda, özü gedib öyrənməsidir. Ambiguity tolerance yüksək olmayan engineer-lər hər addımda bloklaya bilər.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Proactive clarification** — düşünmədən start etmədən əvvəl sual vermək
- **Assumption logging** — nəyi fərz etdiyini şəffaf saxlamaq — gizli assumption = ticking bomb
- **Iteration mindset** — ilk dəfə "mükəmməl" deyil, "öyrənən" versiya çıxarmaq
- **Stakeholder-i guide etmək** — açıq suallar deyil, multiple choice suallar — "A, B ya C?"
- **"Enough to start" qərarı** — nə vaxt başlamaq lazımdır, nə vaxt daha çox soruşmaq
- **Show, don't ask** — mockup ilə sualları surface etmək
- **Business context-i anlamaq** — "nə istəyir" deyil, "niyə istəyir" — real need-ə çatmaq

### 2. Red flags — zəif cavabın əlamətləri

- Heç sual vermədən başlamaq — assumptions silently götürmək
- Hər şeyi soruşmaq — analysis paralysis (50 sual = müştəri yorulur)
- "Tələblər aydın olmadı, gözlədim" — passive behaviour, blocker
- Yanılan assumption-ı gizlətmək — accountability yoxdur
- PM-i blame etmək — "çünki o spec yazmadı"
- "Agile-dır, dəyişəcərik" — rework-ü normalize etmək

### 3. Green signals — güclü cavabın əlamətləri

- Suallarını prioritize etmək — blocker vs nice-to-know
- "Bu məntiqlə başlayıram, X aydın olanda dəyişəcəm" kommunikasiyası
- Prototype / spike ilə qeyri-müəyyənliyi azaltmaq
- Assumption-ları doc-da yazılı saxlamaq
- Ambiguity xəritəsi: "bu məlum, bu naməlum, bu deferrable"
- "Show, don't ask" — mockup ilə cavablar özü gəlir

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Fokus |
|-------------|-------|
| **Startup** | Spec çox vaxt yoxdur — özü kəşf etmək |
| **Consulting/Agency** | Müştəri requirements həmişə incomplete |
| **Enterprise** | Change management — spec dəyişikliyi prosesi |
| **Product şirkət** | Discovery process — user research ilə validate etmək |

### 5. Seniority səviyyəsinə görə gözlənti

| Səviyyə | Ambiguity handling |
|---------|-------------------|
| **Senior** | Öz feature-ının tələblərini clarify etmək |
| **Lead** | Team-in requirements-larını clarify etmək, discovery process qurmaq |
| **Staff** | Şirkət-geniş requirement gathering process-i yaxşılaşdırmaq |

### 6. "User story" vs "real need"

"User istəyir ki dashboard olsun" — user story.
"User hər gün Excel-ə data kopyalayır, sonra PM-ə göndərir" — real need.

Real need-i tapmaq üçün: "Bu feature olmasaydı nə edərdiniz?" — bu sual workaround-ı üzə çıxarır. Workaround real problemi göstərir. Real problem-ə düzgün texniki həll tapılır.

### 7. "Assumption bomb" nədir?

Silent assumption-lar — gizli götürülmüş fərziyyələr — projeni mid-way-də partladan bomblardır. Hər assumption yazılı olmalı, stakeholder-ə göstərilməlidir: "Mən fərz edirəm ki X — bu doğrudurmu?"

Yazılı assumption → stakeholder confirms/denies → yanlışdırsa erkən aşkar olunur → az rework.

---

## Praktik Baxış

### Cavabı necə qurmaq

STAR formatı ilə gedin. Situation-da ambiguity-nin nə qədər ciddi olduğunu göstərin. Action-da hansı sualları nəyə görə verdiyinizi, nə vaxt "enough to start" qərarını verdiyinizi izah edin. Result-da nə qədər yaxşı iterasiya etdiyinizi göstərin.

### Ambiguity idarəetmə framework-u

1. **Assumption map** — "What I know / What I assume / What I must clarify"
2. **Blocker sualları** — bunlar olmadan bir sətir kod yaza bilmərəm
3. **High priority suallar** — Sprint 1-2-də lazım
4. **Deferrable** — mockup gördükdən sonra müştəri özü qərar verəcək
5. **"Show don't ask"** — prototype ilə surface etmək

### Stakeholder-ə sual vermək texnikası

**Açıq sual (pisdir):** "Dashboard necə görünməlidir?"

**Multiple choice (yaxşıdır):** "Dashboard-da filtr olaraq: tarix aralığı, müştəri seqmenti, ya ikisi — ya da başqa bir şey düşünürsünüz?"

**Consequence-based (ən yaxşı):** "Əgər user öz şəhərinin data-sını görürsə, o zaman X baş verəcək. Əgər hər region-ı görürsə, o zaman Y. Hansı biznes proseduruna uyğun gəlir?"

### Tez-tez soruşulan follow-up suallar

1. **"What do you do when a key assumption turns out to be wrong mid-sprint?"** — "Dərhal PM-ə bildirirəm — 'Assumption X yanlış çıxdı, nəticəsi: Y əlavə iş lazımdır. Trade-off: A ya B.' Gizlətmirəm."
2. **"How do you handle competing requirements from different stakeholders?"** — "Hər stakeholder-in prioriteti yazılı alıram. Conflict-i visible etmirəm — onların önündə matrix hazırlayıb 'siz bu tradeoff-u necə həll edirsiniz?' soruşuram."
3. **"Deadline is tomorrow and requirements are still unclear — what do you do?"** — "MVP scope müəyyənləşdirirəm: 'Bu 3 şey aydındır. Qalanı sonra. Bunu deliver edirəm.' Stakeholder-i xəbərdar edirəm."
4. **"How do you distinguish between what clients say they want and what they actually need?"** — "5 Why analizi. 'Niyə bu feature istəyirsiz?' soruşuram. Axıra kimi 'niyə' soruşduqda real problem çıxır."
5. **"Have you ever started building the wrong thing? What happened?"** — Honest cavab hazır olsun; öyrənmə nə idi?
6. **"How do you know when you have 'enough' requirements to start?"** — "'Bu 3 suala cavab bilsəm başlayıram' threshold-umu müəyyənləşdirirəm. Hamısını gözləmirəm."
7. **"How do you handle stakeholders who keep changing requirements?"** — "Change-ın cost-unu visible edirəm: 'Bu dəyişiklik 2 gün əlavə iş. Mevcut sprint-ə daxil etməyə yer varmı?' Sonra onlar qərar verir."

### Nə deyilməsin

- "Heç sual verməmişəm, öz başıma qərar vermişəm" — assumption bomb
- "PM düzgün spec yazmadı, problem PM-dədir" — blame, empathy yox
- "Çox qeyri-müəyyən idi, gözlədim" — passive, blocker
- "Müştəri istədiyi kimi qurdum" — iteration mindset yoxdur

---

## Nümunələr

### Tipik Interview Sualı

"Describe a situation where you had to work with very ambiguous or incomplete requirements. How did you handle it?"

---

### Güclü Cavab (STAR formatında)

**Situation:**
Şirkətimizin böyük müştərisi — enterprise client — "custom reporting dashboard" istədi. Üst-üstə email yazışmasında tələb belə idi: "Bizim sales data-sını görmək istəyirik, filter var olsun, export mümkün olsun." Daha heç nə yox idi. PM-imiz yeni işə başlamışdı, client-lə dərin discovery prosesi keçirə bilmirdi. Deadline 6 həftə idi. Komandada 2 developer, 1 designer var idi — hər kəs fərqli fikirlə başlamaq istəyirdi. Mən bu layihəni texniki lead kimi idarə edirdim.

**Task:**
Mən bu layihəni lead etməliydim. İlk görüşdən sonra texniki tərəfdə 15-dən çox qeyri-müəyyənlik gördüm — hansı data source-lar, hansı aggregation-lar, filter logic, export format, permission model, date range, real-time vs batch. Hamısını soruşsam client "biz proqramçı deyilik" deyər. Heç soruşmasam yanlış şey quraram.

**Action:**

**Addım 1 — Assumption map:**
Əvvəlcə "assumption log" yaratdım — 3 sütun: "What I know", "What I assume", "What I must clarify before start." Bu log 23 item idi. Sonra 3 kateqoriyaya ayırdım:
- **Blocker (6 item):** bunlar olmadan kod yaza bilmərəm — data source, permission model, date range logic
- **High priority (9 item):** Sprint 1–2-də lazım — export format, filter types
- **Deferrable (8 item):** mockup gördükdən sonra müştəri özü qərar verəcək

**Addım 2 — Discovery session (60 dəqiqəlik):**
PM ilə birlikdə client-lə meeting keçirdim. Yalnız blocker sualları hazırladım — 6 sual, hər sualın 2–3 əvvəlcədən müəyyən edilmiş cavab variantı var idi. Məsələn:
- "Sales data dedikdə hansı sistemdən gəlir — CRM-dən mi, manual Excel-dən mi, ya ikisi birlikdə?"
- "Export üçün PDF, Excel, ya CSV — ya da fərqli düşünürsünüz?"

Multiple choice suallar non-technical client üçün rahat idi. 45 dəqiqədə 5 blocker-i həll etdik.

**Addım 3 — Assumption-u yazılı saxlamaq:**
6-cı blocker aydın olmadı — permission model: client-in 3 fərqli rəyi var idi. Assumption log-a yazdım: "Assumption: hər user yalnız öz region-unun data-sını görür." PM-ə email göndərdim: "Bu fərziyyə ilə başlayırıq. Əgər yanlışdırsa sprint 2-də düzəldə bilərik — amma o zaman 3 günlük əlavə iş olar." Yazılı assumption = görünür risk.

**Addım 4 — "Show, don't ask":**
2 həftədə mockup dashboard hazırladım — fake data ilə, real data yox. Client-ə göstərdim. Onlar gördülər və dərhal: "Bu filter lazım deyil, amma bu başqa filter lazımdır", "Bu chart-ı bar chart deyil, table kimi istəyirik." Real tələblər mockup görülüncə çox daha aydın oldu — əvvəl soruşmaqdan çox məlumat verdi.

**Addım 5 — Iterative sprint:**
6 həftəni 3 sprint-ə böldüm: Sprint 1 — data pipeline + basic display, Sprint 2 — filters + permissions, Sprint 3 — export + polish. Hər sprint sonunda client demo-ya dəvət edildi. Hər sprintdə kiçik "requirement discovery" baş verdi — amma biz işləyən sistem üzərindəydik, sıfırdan deyil.

**Result:**
6 həftədə dashboard deliver edildi. Permission model assumption-ımız 80% doğru çıxdı — yalnız "admin sees all regions" edge case əlavə edildi — bu 4 saatlıq iş idi. Müştəri NPS 9/10 verdi. PM dedi: "Onlar 'developer team bizi çox yaxşı başa düşdü' deyir — halbuki siz çox sual verdiniz." Real sübut: düzgün sual = müştəri "başa düşüldüm" hiss edir. Sonrakı layihə üçün client özü "suallarınız faydalı olur" deyərək bu discovery prosesi metodunu request etdi.

---

### Alternativ Ssenari — Internal tool, requirements yox

Internal tool development-da CTO "bir şeylər qurun" demişdi — dashboard-u kimin istifadə edəcəyi, nə üçün lazım olduğu aydın deyildi. Mən 3 potential user ilə 20 dəqiqəlik interview etdim. "Hər gün ən çox vaxt itirdiyin iş hansıdır?" — bu açıq sual real pain point-ləri üzə çıxardı. "Dashboard" deyil, "manual Excel export + Slack-ə göndərmə" problemi idi. Yəni tələb "dashboard" deyil, "automated report delivery" idi. Interview olmadan sıfır sual ilə başlasaydım yanlış şey qurardım. Bu discovery 2 saata başa gəldi — potensial 3 sprint-lik yanlış işi önlədi.

---

### Zəif Cavab Nümunəsi

"Tələblər gəlməsə özüm qərara gəlirəm. Developer olaraq öz judgment-ımıza güvənməliyik. Əgər müştəri dəyişdirmək istəsə, dəyişdiririk — agile-dır axı."

**Niyə zəifdər:** "Agile-dır" rework-ü normallaşdırmaq üçün bahane deyil. Stakeholder alignment tamam ignore edilir. Rework cost-u nəzərə alınmır. "Judgment-ımıza güvən" — bu ba'zan düzgündür, amma həmişə yox. Bu cavab "mən bilirəm siz bilmirsiniz" mentalitetini göstərir. "Agile" düzgün tətbiq edilmirsə — bu waterfall-dan daha baha başa gəlir.

---

## Praktik Tapşırıqlar

1. **Assumption log məşq et:** Gələcək sprint-in bir feature-unu götürün. "What I know / What I assume / What I must clarify" cədvəlini doldurun. Hər assumption üçün: blocker mu, deferrable mu?

2. **"Discovery sual" bank yarat:** 10 ümumi discovery sualı hazırlayın — multiple choice formatında. Bunları interview-da "mənim prosesim" kimi present edin.

3. **Mockup-first workflow məşq et:** Növbəti feature üçün kod yazmadan əvvəl sadə mockup qurun (Figma, kağız, ya Excalidraw). Stakeholder-ə göstərin. Neçə yeni tələb ortaya çıxır? Bu iteration bahasını ölçün.

4. **Məşq sualı:** "You're given a task: 'Add search to our app.' Nothing else. What do you do first?" — 5 addımlı process cavabı hazırlayın.

5. **"Yanılmış assumption" hekayəsi:** Bir assumption yanıldı, nə etdiniz? Problemi necə erkən kəşf etdiniz, nə dəyişdiniz? Bu story interview-da çox güclüdür.

6. **"Enough to start" threshold:** Hər yeni feature üçün "bu 3 soruya cavab bilsəm başlaya bilərəm" suallarını müəyyənləşdirin. Bu threshold-u aydın etmək həm blockerları azaldır, həm analysis paralysis-i önlər.

7. **"5 Why" analizi:** Bir mövcud feature üçün "müştəri niyə bunu istədi?" sualını 5 dəfə soruşun. Real need ilə stated need fərqlimi? Bu analizi interview-da "real need-ə çatma" nümunəsi kimi istifadə edin.

8. **"Rework cost" hekayəsi:** Ambiguity-dən yaranan rework-ün bir nümunəsini tapın. Nə qədər vaxt itdi? Bu hekayə "assumption logging" nin dəyərini göstərir — "bu 3 saat discovery 3 sprint-lik rework-ü önlədi."

---

## Ətraflı Qeydlər

### "Assumption log" — real nümunə

Dashboard feature üçün:

```markdown
## Assumption Log — Sales Dashboard Feature

### What I Know (verified)
- Data source: CRM database (confirmed by PM)
- Export format: Excel + CSV (meeting-də qərara gəlindi)
- User roles: 3 (admin, manager, rep) — schema-da var

### What I Assume (unverified)
- Her user yalnız öz region-unun data-sını görür [HIGH RISK]
- Dashboard real-time deyil, daily refresh yetər [MEDIUM RISK]
- Date range default: current quarter [LOW RISK]

### What I Must Clarify
- [ ] Permission model: region-based ya user-based? (BLOCKER)
- [ ] Refresh frequency: real-time, hourly, daily? (BLOCKER)
- [ ] Historical data: neçə il? (HIGH PRIORITY)

### Deferrable (mockup-dan sonra)
- Chart types (bar vs line vs pie)
- Color scheme
- Mobile responsive requirement
```

### Discovery sual tipolojisi

| Sual tipi | Nümunə | İstifadə vaxtı |
|-----------|--------|---------------|
| **Blocker** | "Data source hansı sistemdir?" | Başlamadan əvvəl |
| **Consequence** | "X olarsa Y baş verər, bu doğrumu?" | High-risk assumption |
| **Multiple choice** | "A, B, ya C?" | Non-technical stakeholder |
| **Show don't ask** | Mockup göstər, "bu doğrumu?" | UI/UX requirements |
| **5 Why** | "Niyə bunu istəyirsiniz?" (5 dəfə) | Real need tapmaq |

### "Spike" sprint — ambiguity azaltmaq üçün

Böyük belirsizlik olduqda: əvvəlcə spike sprint:
- Müddət: 1-3 gün
- Məqsəd: texniki feasibility test etmək, unknown-ları müəyyənləşdirmək
- Output: "Bu mümkündür/deyil + nə qədər vaxt alır" estimation
- Rəsmilik: "bu production code deyil, throw-away proof of concept"

Spike-dan sonra estimation çox daha dəqiq olur — bu üsul hekayənizə daxil etmək "mature engineering judgment" göstərir.

### Ambiguity tolerance — psixoloji tərəf

Ambiguity-dən narahat olmaq normaldır. Amma:
- "Hər şey aydın olmayınca başlaya bilmərəm" = analysis paralysis
- "Başlayıram, sonra düzəldirik" = reckless (yanlış assumption cost-u yüksəkdir)
- "3 blocker aydın olsun, qalanı iterate edərik" = professional balance

Bu balansı interview-da "mən belə qərara gəlirəm" kimi ifadə etmək güclüdür.

### Requirements müsahibəsi — sual texnikası

Non-technical stakeholder-dən requirement alanda:

```
❌ "Sistem real-time işləməlidirmi?"   (texniki cavabı bilinmir)
✓  "İstifadəçi button-a basanda nə qədər gözləyə bilər?"

❌ "Multi-tenant lazımdırmı?"          (texniki anlayış yox)
✓  "Bu sistemi eyni anda neçə şirkət istifadə edəcək?"

❌ "High availability tələb olunurmu?" (jargon)
✓  "Sistem gündə neçə saat dayanırsa problem sayılır?"
```

Bu sual transformasiyası "technical → business language" — ambiguity-ni azaldır.

### Ambiguity hekayəsinin güclü final cümləsi

STAR-ın Result hissəsini güclü bitirmək üçün:

- "Proqnozlaşdırılan 12 həftəlik işi 8 həftəyə tamamladıq — çünki yanlış assumption-lardan vaxtında xilas olduq."
- "Layihə scope dəyişikliklərinin sayı 0-a düşdü — əvvəldən düzgün başa düşmüşdük."
- "PM sonra bildirdi ki, bu kəşf sessiyası ən effektiv requirement gathering idi."

Nəticəni rəqəm + stakeholder feedback ilə bitirmək — hekayəni tam tamamlayır.

### Ambiguity management — şirkət tipinə görə

**FAANG:** "Product spec yazılmış olur, amma texniki ambiguity qalır. Scale qərarları müzakirə tələb edir." Texniki assumption-ları sənədləşdirmək vacibdir.

**Startup:** "Requirements tez-tez dəyişir. Assumption log saxlamaq özlüyündə value-dur — dəyişikliyi izlemek asanlaşır."

**Enterprise:** "Legal, compliance, legacy system constraint-ları requirements-ı mürəkkəbləşdirir. Formal discovery session — Change Request prosesinin bir hissəsidir."

Bu kontekst fərqini hekayənizdə göstərmək — müsahibəçinin şirkətinə uyğun cavab verdiyinizi aydın edir.

---

## Əlaqəli Mövzular

- `08-estimation-planning.md` — Qeyri-müəyyənlik olduqda estimation necə verilir
- `11-cross-functional-collab.md` — Stakeholder-lərlə requirements clarification
- `15-system-design-retrospective.md` — Qeyri-müəyyən dizayn qərarlarının post-analizi
- `07-failure-lessons.md` — Yanlış assumption-dan öyrənmək
- `13-leadership-without-authority.md` — Qeyri-müəyyən situation-da komandanı guide etmək
