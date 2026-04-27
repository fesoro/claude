# Handling Technical Disagreements (Senior ⭐⭐⭐)

## İcmal

Texniki anlaşmazlıqlar hər komandada qaçılmazdır — fərqli təcrübə, fərqli kontekst, fərqli prioritetlər. Senior developer-in bu sualı yaxşı cavablandırması: "Mən həmişə razıyam" yox, "Mən arqumentlə müdafiə edirəm, lakin komanda qərarını hörmətlə qəbul edirəm" mesajını verir. Yanlış cavab — ya heç vaxt anlaşmazlıq olmayıb (inandırıcı deyil), ya da hər dəfə "mən haqlı idim" (egoist görünür).

Bu sual xüsusilə senior-dan yuxarı səviyyə üçün vacibdir, çünki lead-lər çox vaxt texniki qərar müzakirəsinin mərkəzindədir. Eyni zamanda bu sual "disagree and commit" bacarığını — Amazon-un ən məşhur leadership principle-larından birini — birbaşa yoxlayır.

---

## Niyə Vacibdir

Interviewer bu sual ilə iki şeyi ölçür: texniki əminlik (siz öz qərarınızı data ilə müdafiə edə bilirsinizmi?) və sosial yetkinlik (anlaşmazlığı destructive yox, constructive şəkildə idarə edirsinizmi?).

"Disagree and commit" — Amazon leadership principle-ı — çox şirkətin bu məsələyə baxışını ifadə edir. Siz qərarla razı olmaya bilərsiniz, amma komanda qərarını verdikdən sonra tam dəstəklə həyata keçirirsiniz.

Senior developer-lər fikir ayrılığını personal konfliktə çevirmir. Data ilə müzakirə edir, arqument qururlar, amma qərar verildikdən sonra komandanın istiqamətini tam dəstəkləyirlər.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Data-driven standpoint** — "mən fikirliyəm" yox, "benchmark göstərir ki..." yaxud "EXPLAIN ANALYZE göstərdi ki..."
- **Respectful pushback** — qəzəblənmədən, personal etmədən, faktlarla mübahisə etmək
- **Komanda qərarına uyğunlaşmaq** — "disagree and commit" bacarığı
- **Reflection** — o anlaşmazlıqdan nə öyrəndiniz? Öyrənmə hər iki tərəfdə ola bilər
- **Empathy** — qarşı tərəfin arqumentini dürüst anlamaq, "onlar niyə belə düşünür?"
- **Constructive alternatives** — "yox" demək deyil, "əvvəl A, sonra B" kompromis
- **Long-term thinking** — "şu an doğru yol" ilə "uzunmüddətli doğru yol" arasında balans

### 2. Red flags — zəif cavabın əlamətləri

- "Heç vaxt anlaşmazlığım olmayıb" — inandırıcı deyil, conflict-avoidance göstərir
- "Mən haqlı idim, onlar yanılırdı" — teamwork problemini aşkar edir
- "Manager dedi, etdim" — ownership yox, agency yox
- Şəxsi anlaşmazlığı texniki kimi göstərmək — "o bəyənmir məni"
- Hər dəfə öz mövqeyini qoruduğunu söyləmək — kompromi bilmir
- Qarşı tərəfin arqumentini heç vaxt anlamaq üçün dinləməmək
- "Bu topik-i bağlayaq, mövzuya keçək" — conflict-avoidance

### 3. Green signals — güclü cavabın əlamətləri

- Spesifik texniki mövzu: "microservice vs monolith", "cache strategy", "ORM vs raw SQL", "sync vs async"
- Arqumentasiyanı data ilə qurmaq — benchmark, profiler, EXPLAIN, cost hesablaması
- "Disagree but commit" məğzini genuine göstərmək — sadəcə sözlə deyil, hərəkətlə
- Nəticədə onların seçimi doğru çıxdıqda öyrənmə, ya öz seçimi doğru çıxdıqda skromluq
- RFC, ADR, data-backed document ilə mübahisə etmək
- Kompromi tapmaq — "əvvəl A, sonra B" hybrid approach

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Nə axtarır |
|-------------|------------|
| **Amazon** | Disagree and commit + data-driven + vocally self-critical |
| **Google** | Structured argumentation, psychological safety culture |
| **Startup** | Speed + pragmatism — nə qədər tez qərara gəlmək |
| **Enterprise** | Process-based escalation, documentation, risk awareness |
| **Atlassian** | Team autonomy — squad-ın qərarını hörmət etmək |

### 5. Seniority səviyyəsinə görə gözlənti

| Səviyyə | Anlaşmazlıq növü |
|---------|-----------------|
| **Senior** | Peer ilə, ya da junior-senior kəsişməsindəki texniki qərar |
| **Lead** | Team lead-lə, ya da stakeholder ilə arxitektura qərarı |
| **Staff** | Sistemik texniki istiqamət — şirkət-geniş qərar |

### 6. "Haqlı çıxmağa" fokuslanmağın təhlükəsi

Ən güclü cavablar "mən haqlı idim" yox, "komanda birlikdə daha yaxşı qərara gəldi" mesajı verir. İnterviwer komanda oyunçusu axtarır, ən "ağıllı" adamı deyil. Hətta anlaşmazlıqda "siz haqlı çıxdınız" dediyinizdə bu güclü self-awareness göstərir.

---

## Praktik Baxış

### Cavabı necə qurmaq

1. **Kontekst:** nə haqqında anlaşmazlıq idi? Texniki, arxitektura, yoxsa process?
2. **Sizin mövqeyiniz:** niyə belə düşünürdünüz, hansı məlumat əsasında?
3. **Müzakirə:** necə konfrontasiya etdiniz — nə dedini, nə eşitdiniz? Onların arqumenti nə idi?
4. **Nəticə:** komanda nə qərara gəldi? Siz razılaşdınız, yoxsa commit etdiniz?
5. **Reflection:** nə öyrəndiniz? Onlar haqlı çıxdı mı, ya siz?

### "Disagree and commit" düzgün formulu

- "Mən başqa yanaşmanı daha doğru hesab edirdim, çünki [data/benchmark/reasoning]..."
- "Öz mövqeyimi [RFC doc / EXPLAIN output / benchmark result] ilə izah etdim..."
- "Komanda X qərarını verdi — mən o qərarın arxasında durub tam dəstəklədim..."
- "Nəticədə [öyrənmə — ya onlar haqlı çıxdı, ya siz]"

### Optimal cavab uzunluğu

3–4 dəqiqə. Qısa (1 dəq): texniki dərinlik yoxdur. Uzun (>5 dəq): anlaşmazlığı dramatize edirsiz.

### "Disagree and commit"-dən sonra nə etdiniz?

Bu kritik detalı çox namizəd unudur. Commit etdikdən sonra aktiv olaraq nə etdiniz?
- "Onların qərarının uğurlu olmasına kömək etdim"
- "Potensial risk materiallaşdıqda əvvəlcədən migration plan hazırlamışdım"
- "Komandaya doğru seçim etdiklərini retrospektivdə deyin"

### Tez-tez soruşulan follow-up suallar

1. **"What would you have done if the team still chose the approach you disagreed with?"** — "Disagree but commit, sonra risk monitor etmək. Əgər risk materiallaşırdısa, əvvəlcədən hazırladığım alternativ plan devreye girerdi"
2. **"Have you ever been wrong in a technical disagreement? What happened?"** — MÜTLƏQ hazır olsun; "onlar haqlı çıxdı" hekayəsi self-awareness göstərir
3. **"How do you handle disagreements with your manager vs with a peer?"** — Manager-lə: daha formal, data-driven, yazılı. Peer-lərlə: daha çevik, real-time, whiteboard
4. **"Have you ever changed your technical opinion after hearing counter-arguments?"** — "Bəli, mənim Redis cache planım var idi, amma senior PostgreSQL materialized view ilə benchmark göstərdi, onun yanaşması bizim use case üçün daha uyğun idi"
5. **"How do you ensure disagreements don't affect working relationships?"** — "Anlaşmazlığı personal etmirəm. 'Sənin fikrin yanlış' deyil, 'bu approach bu şərtdə bu riski yaradır' deyirəm"
6. **"When is it worth escalating a disagreement?"** — "Yalnız safety, compliance, ya da çox yüksək risk söhbəti olanda. Style ya approach fərqi eskalasiyaya dəyməz"
7. **"Tell me about a time the other side turned out to be right."** — bu sual gözlənir; honest, spesifik hekayə hazır olsun

### Nə deyilməsin

- "O developer bilmirdi" — personal attack, professional deyil
- "Manager yanlış etdi" — authority-yə hücum, red flag
- Hekayəni çox dramatik göstərmək — "böyük dava idi"
- Nəticəni söyləməmək — anlaşmazlıq necə bitdi?
- Həmişə öz fikrindən dönmədiyini söyləmək — adaptable deyil

### "Pass" cavabından "Strong Hire" cavabına nə fərq edir

**Pass:** "Manager ilə razılaşmadım, amma o qərar verdi, etdim."

**Strong Hire:** "Mən RFC hazırladım — hər iki yanaşmanın trade-off-unu data ilə yazdım. Onların arqumentini dürüst əks etdirdim. Komanda X seçdi, mən 'disagree but commit' etdim və hətta onların qərarının uğuruna kömək etdim. 6 ay sonra Y limitinə çatdıqda, əvvəlcədən yazdığım migration plan blueprint oldu."

---

## Nümunələr

### Tipik Interview Sualı

"Tell me about a time when you disagreed with a technical decision. How did you handle it?" / "Have you ever pushed back on a senior engineer's approach? What happened?"

---

### Güclü Cavab (STAR formatında)

**Situation:**
2023-cü ilin əvvəlində 6-nəfərlik backend teamımız yeni analytics pipeline-ının arxitekturasını müzakirə edirdi. Şirkət FinTech idi, gündəlik 300K+ transaction event işlənirdi. Tech lead real-time processing üçün tam PostgreSQL + custom materialized views yanaşmasını tövsiyə etdi — sadə, team-in bildiyı stack, 2 həftəyə production-a çıxmaq olar. Mən isə bu use case üçün Kafka + stream processing-in daha uyğun olacağını düşünürdüm. Anlaşmazlıq ciddi idi — hər iki yanaşma işləyirdi texniki cəhətdən, amma uzunmüddətli nəticə fərqli idi.

**Task:**
Mövqeyimi texniki arqumentlə izah etmək, münaqişəni destructive etmədən müzakirəni konstruktiv saxlamaq, komanda qərarına riayət etmək lazım idi. Eyni zamanda tech lead ilə working relationship-i qorumaq vacib idi — uzunmüddətli əməkdaşlıq edirdik.

**Action:**
Birbaşa "sən yanılırsın" demək əvəzinə, RFC (Request for Comments) document hazırladım — Google Doc-da, hamının əlavə edə bildiyi. Hər iki yanaşmanın müqayisəsini yazdım: latency, throughput, operational complexity, cost, team learning curve.

Konkret rəqəm əlavə etdim: 10K event/sec yükdə materialized view refresh hər 5 saniyədə bir bütün aggregate cədvəlini rebuild etməli idi — bu DB-ə çox yük demək idi (benchmark: ~800ms refresh time, yüksək traffic-də 12% DB CPU artımı). Kafka ilə hər event-ə incremental prosessing mümkündür.

Lakin tech lead-in arqumentini RFC-yə dürüst yazdım: Kafka operational overhead — team-in heç bir Kafka təcrübəsi yox idi, 3 ay onboarding lazım olardı. PostgreSQL ilə team 2 həftəyə production-a çıxa bilərdi. "Time to market" vacib idi — rəqib artıq oxşar feature buraxmışdı.

Mənim təklif etdiyim kompromis: PostgreSQL ilə başla, performance limitlərə çatdıqda (6-12 ay) Kafka-ya miqrasiya et. Migration path-ı RFC-yə yazdım — bunu indi etmək şərti ilə, o zaman migration daha asan olacaqdı. Konkret olaraq: `event_stream` abstract interface, yeni schema-lar migration-a hazır.

**Result:**
Komanda PostgreSQL seçdi — tam məntiqliydi. Mən "disagree but commit" etdim: migration path-ı yaxşı sənədlədim, yeni schema-ları migration-a hazır yazdım. 7 aydan sonra Kafka-ya miqrasiya zamanı o sənəd əsas blueprint oldu — migration 1 sprint çəkdi (əvvəlcə 3 sprint estimate edilirdi). Bu hadisə özüm üçün bir şey öyrətdi: texniki üstünlük həmişə "ən yaxşı texnologiya" üzərindəki qərar deyil — team capacity, time-to-market, operational cost da vacibdir. İndi hər texniki arqumentə bu 3 faktoru daxil edirəm.

---

### Alternativ Ssenari — Junior ilə anlaşmazlıq

**Situation:** Junior developer ciddi performance problemi yaradan pattern olan PR açdı: 400+ query (N+1) + no pagination + eager load olmadan. O özünə çox confident idi — "bu feature-ı bitirdim, merge edə bilərik" dedi.

**Task:** PR-ı rədd etmədən, həm kodu düzəltmək, həm developer-i öyrətmək, həm də onu defensive etməmək.

**Action:** Review comment-ləri "bu yanlış"-dan başlamadım. Əvvəlcə PR-ın nəyə nail olmağa çalışdığını sual verdim. Sonra Laravel Debugbar screenshot paylaşdım — 412 query göstərirdi. "Bu rəqəmin nə demək olduğunu birlikdə baxaq" dedim. EXPLAIN ANALYZE output-u göstərdim, N+1-i visualize etdim. Düzəltmə öz əlləri ilə etsin deyə hint verdim, cavab vermədim. "Niyə belə yazıldığını anlamaq istəyirəm" — bu cümləni başlanğıc olaraq seçdim, "bu yanlışdır" əvəzinə.

**Result:** O developer PR-ı özü 24 saat sonra yenidən yazdı — `with()`, pagination, index. Bir ay sonra başqa PR review-da özü başqa junior-a N+1-i izah etdi. Personal deyil, texniki yanaşma onu defensive etmirdi. Bu anlaşmazlıqdan öyrəndim: junior-larla conflict-i "mənim standartım" yox "birlikdə kəşf" çərçivəsindən qurmaq daha effektivdir.

---

### Zəif Cavab Nümunəsi

"Bir dəfə team lead-ə dedim ki, bu kod pis yazılıb, lazımsız şəkildə mürəkkəbdir. O qulaq asmadı. Məni demotiv etdi. Amma axırda mən haqlı çıxdım — kod debug zamanı problem oldu."

**Niyə zəifdər:** "Pis yazılıb" — subyektiv, data yox. "Haqlı çıxdım" focus-u — team-in uğuru deyil, şəxsi haqlılıq. "Demotiv etdi" — professional deyil. Nə öyrəndiniz? "Disagree and commit" yoxdur. "Axırda problem oldu" — bu "sən yanılırdın" deməkdir, team-work deyil. Bu cavab şəxsi ego-nu komanda mədəniyyətindən yuxarı qoyur.

---

## Praktik Tapşırıqlar

1. **Hekayə tap:** Karyeranızda texniki anlaşmazlıq yaşadığınız 2 vəziyyəti tapın. Biri "siz haqlı çıxdınız", digəri "onlar haqlı çıxdı" variantları ideal — hər ikisini hazırlayın. İkinci hekayə — "onlar haqlı çıxdı" — self-awareness siqnalı verir.

2. **RFC format məşq et:** Mövcud bir texniki qərara (istənilən) "counter-proposal" şəklindəki 1 səhifəlik sənəd yazın: müqayisə cədvəli, trade-off-lar, recommendation, risks. Bu format interview-da "mən bunu belə etdim" kimi göstərilə bilər.

3. **"Disagree and commit" cümlə formulu:** Hekayənizdə bu anı izah edən 3 cümlə yazın — "commit" etmək dedikdə konkret nə etdiniz? Bunu hekayə danışarkən natural daxil etməyi məşq edin.

4. **"Onlar haqlı çıxdı" versiyası hazırla:** Anlaşmazlıqda digər tərəf haqlı çıxdığı hekayəni danışın. Bu genuinliyi göstərir — hər zaman haqlı olduğunu iddia etmirsiniz.

5. **Follow-up hazırlığı:** "Əgər yenidən olsaydı, fərqli nə edərdiniz?" sualına cavab hazırlayın — bu reflection-ı göstərir. "Daha tez RFC yazardım", "benchmark-i əvvəlcədən hazırlardım" kimi konkret.

6. **Peer interview məşqi:** Bir həmkarınıza "anlaşmazlıq" hekayənizdə onların rolunu oynamasını istəyin — conflict-i simulate edin. Stres altında professional qalmağı məşq edin.

7. **"Kompromis" tapıq hekayəsi:** Heç biriniz tam qalib çıxmadığınız, amma ikisinin də bir hissəsini götürdüyünüz bir qərarı tapın. Bu "win-win" hekayə ən güclü cross-functional mindset göstərir.

8. **Ölçülə bilən nəticə əlavə edin:** Anlaşmazlığın nəticəsi — onların yanaşması seçildi — nə qədər yaxşı işlədi? "Kafka əvəzinə PostgreSQL seçdik, 7 ay sonra scale limitinə çatdıq, 1 sprint-də migration etdik." Bu məlumat "onların yanaşması işlədi, amma migration planımı hazırlamağım onu daha asan etdi" kimi güclü mesaj verir.

---

## Ətraflı Qeydlər

### Texniki anlaşmazlığın 4 tipi

**Tip 1 — Implementation approach:**
"Sync yox, async olmalıdır." "ORM yox, raw SQL." "REST yox, GraphQL." Bu anlaşmazlıqlar data ilə həll edilir — benchmark, profiler, load test.

**Tip 2 — Architecture direction:**
"Monolith yox, microservices." "Shared DB yox, schema-per-tenant." Bu anlaşmazlıqlar trade-off analizi ilə həll edilir — RFC, ADR, şirkətin growth stage-i.

**Tip 3 — Process/standards:**
"Code style", "test coverage threshold", "deployment process." Bu anlaşmazlıqlar komanda konsensüsü ilə həll edilir — retrospective, RFC vote.

**Tip 4 — Priority conflict:**
"Feature yox, debt." "Deadline yox, quality." Bu anlaşmazlıqlar business context ilə həll edilir — stakeholder input, risk framing.

### "Disagree and commit" — real həyatda necə görünür?

Commit etmək = verbal razılıq vermək deyil. Real commit:
- Onların seçiminin uğuru üçün aktiv çalışmaq
- Qərarı başqalarına sabotaj etmədən izah etmək ("mən razı deyildim, amma komanda seçdi")
- Əvvəlcədən risk bildirdiyinizdə — o risk materiallaşdıqda "mən demişdim" deyil, "budur həll planı" demək
- Retrospektivdə nəyin işlədiyini/işləmədiyini honest qiymətləndirmək

### RFC (Request for Comments) şablonu

Texniki anlaşmazlıqda RFC yazmaq — ən professional yanaşmadır:

```markdown
## Başlıq: [Mövzu]
## Tarix: [Tarix]
## Müəllif: [Ad]
## Status: Draft / Under Review / Accepted / Rejected

## Problem
[Nə problemi həll etməyə çalışırıq?]

## Alternativlər
### Variant A: [Ad]
Pros: ...
Cons: ...

### Variant B: [Ad]
Pros: ...
Cons: ...

## Tövsiyə
[Niyə bu variant seçilməlidir?]

## Açıq suallar
[Hələ cavabsız qalan hissələr]
```

Bu format — emotiondan əvvəl data — anlaşmazlığı professional müstəviyə çəkir.

### Amazon "Vocally self-critical" prinsipi

Amazon-un müsahibəsindəki xüsusi tələb: öz yanlışlığınızı açıq qəbul etmək. "Mən yanılırdım, çünki X" — bu cümlə Amazon-da güclü siqnaldır. Həm anlaşmazlıq hekayənizə, həm "onlar haqlı çıxdı" versiyasına daxil edin.

### Anlaşmazlığın bitişi — 4 mümkün final

Hər technical disagreement eyni şəkildə bitmir. Interview-da fərqli finallar hazır saxlayın:

1. **Mənim variant qəbul edildi** — niyə? Data, POC, ya business impact qazandı.
2. **Onların variant qəbul edildi** — "disagree and commit" — öz etirazımı qeyd etdim, komanda qərarını dəstəklədim.
3. **Kompromis tapıldı** — iki variantın ən yaxşı hissəsi birləşdirildi.
4. **Qərar ertələndi** — spike sprint ilə əvvəlcə bilinməyən şeylər öyrənildi, sonra qərar verildi.

**Güclü hekayədə:** final outcome-un niyə o şəkildə olduğunu və sizin rolunuzu aydın izah edin.

### Disagreement-da emosional yetkinlik siqnalları

Müsahibəçi izləyir:
- "Mən haqlıydım, onlar yanlış idi" tonu → **red flag** (empathy yoxluğu)
- "Biz oturub hər iki tərəfin data-sına baxdıq" → **green flag**
- "Sonradan anladım ki, onların nəzər nöqtəsi də haqlıydı" → **strong green flag**
- "Bu mübahisədən team-in daha yaxşı qərar vermə prosesi yarandı" → **exceptional signal**

### Şirkət tipinə görə disagreement frame-i

**FAANG:** "Disagree and commit" — Amazon Leadership Principle-dır. Hekayənizdə "mən etirazımı yazdım, komanda qərarını qəbul etdim, tam dəstəklədim" — bu cümlə çox dəyərlidir.

**Startup:** Sürət vacibdir. "30 dəqiqəlik müzakirə, sonra qərar" — uzun RFC prosesi startup-da yersiz ola bilər.

**Enterprise:** Formal approval prosesləri var. "Change Advisory Board-a təqdim etdik" — bu context-i bilin.

### Güclü hekayənin son cümləsi

Disagreement hekayəsini bitirmək üçün 2 element lazımdır:

1. **Qərarın nəticəsi:** "Variant B seçildi. 3 ay sonra production-da validation etdi — latency 40% azaldı."
2. **Münasibətin nəticəsi:** "Bu prosesdən sonra komandamızda RFC mədəniyyəti yarandı. İndi hər böyük texniki qərar yazılı müzakirə ilə başlayır."

Sadəcə "mən haqlı idim" ya da "onlar haqlı idi" ilə bitirmək yetərsizdir — sistemik təsir göstərin.

---

## Əlaqəli Mövzular

- `01-star-method.md` — STAR çərçivəsi
- `05-mentoring-juniors.md` — Junior ilə anlaşmazlıq
- `11-cross-functional-collab.md` — Başqa team ilə anlaşmazlıq
- `06-managing-technical-debt.md` — Debt prioritizasiyası haqqında anlaşmazlıq
- `13-leadership-without-authority.md` — Influence olmadan persuade etmək
