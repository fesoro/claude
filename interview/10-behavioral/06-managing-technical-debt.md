# Managing Technical Debt (Lead ⭐⭐⭐⭐)

## İcmal

Technical debt — gələcəkdə əlavə iş tələb edən, şüurlu ya şüursuz verilmiş qısa yol qərarlarının yığılmasıdır. "Bu kodu indi sürətlə yaz, sonra düzəldirik" — debt. Bu sual ilə interviewer görmək istəyir: siz debt-i inkar edir, ya tanıyır; idarə edir, ya idarə olunursunuz?

Martin Fowler-in debt quadrant-ı göstərir ki, hər debt pis deyil — bəziləri şüurlu strategidir. Problematik olan: inadvertent (bilmədən yaranan) və reckless (bilə-bilə risk götürmə) debt-dir. Yaxşı lead-lər fərqi bilir.

Real dünyada sıfır debt mövcud deyil. Sual debt olub-olmaması yox, onun necə idarə edilməsidir.

---

## Niyə Vacibdir

Lead/Principal developer üçün technical debt idarəetməsi strateji məsuliyyətdir. Hər sprint-ə "sadəcə yeni feature" sıxışdıran komanda debt altında batır — velocity azalır, bug artır, yeni developer-lər anlamaqda çətinlik çəkir.

Digər tərəfdən, "hamısını yenidən yaz" fəlakəti — Netscape Navigator, Healthcare.gov — debt-i nəzərə almadan tam rewrite-ın nəticəsidir. Yaxşı lead bu iki ekstrem arasında balans tapır.

Debt-i yalnız texniki problem kimi göstərən namizəd "güclü texniki adam" görünür. Debt-i business risk kimi çerçeveleyən namizəd "lead" görünür.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Debt-i business terms-ə çevirmək** — sadəcə "kod pis" deyil, "hər feature bu moduldan keçsə 2x uzun çəkir"
- **Prioritizasiya** — hamısını bir anda görmək olmur, impact × effort matrix
- **Incremental approach** — "yenidən yaz" yox, "strangler fig", "expand-contract", "boy scout rule"
- **Stakeholder alignment** — debt-in business impact-ını non-technical dilə çevirmək
- **Data toplama** — retrospective analysis, sprint velocity trends, bug source tracking
- **Risk framing** — "indi etməsək nə baş verir?" sualını cavablandırmaq
- **Prevention** — yeni debt girmənin qarşısını almaq — review culture, standards

### 2. Red flags — zəif cavabın əlamətləri

- "Hər şeyi yenidən yazdıq" — bu çox risklidir, soruşa bilərlər "nə getdi yanlış?"
- "Debt olmadı, həmişə clean code yazdıq" — inandırıcı deyil, naif görünür
- "Manager icazə vermədi" — initiative yox idi, advocacy bacarığı yox
- Yalnız texniki perspektiv, business impact izah edilmir
- Debt-i bir anda "sprint-ə doldurub" həll etmək — plansız
- "Debt çox idi, nə edəcəyimi bilmirdim" — passiv davranış
- Hər sprintin başında debt üçün dava etmək — sistematik deyil

### 3. Green signals — güclü cavabın əlamətləri

- Debt map: nə qədər debt var, harada, indi nə qədər bahalı?
- Business case: "bu debt-i silməsək, Q3-də yeni feature 3x uzun çəkəcək"
- Incremental strategy: büyük rewrite yox, kiçik addımlar
- Team alignment: hamı debt-in nə olduğunu bilir
- Sprint-ə % ayırmaq — "hər sprint-in 20%-i debt reduction" sistemi
- Ölçülə bilən nəticə: "velocity 30% artdı", "regression bug 68%-dən 23%-ə düşdü"

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Prioritet |
|-------------|-----------|
| **Startup** | Speed vs quality balansı — debt qəbul edilə bilər, amma track olunmalı |
| **Enterprise** | Risk management, compliance, audit trail |
| **FAANG** | Engineering excellence culture, TechDebt OKR-lar |
| **Scaleup** | Velocity artırmaq — debt developer productivity-ni öldürür |

### 5. Seniority səviyyəsinə görə gözlənti

| Səviyyə | Debt management scope |
|---------|----------------------|
| **Senior** | Öz modulunda debt azaltmaq, "boy scout rule" |
| **Lead** | Team-in debt backlog-unu idarə etmək, stakeholder-i inandırmaq |
| **Staff** | Şirkət-geniş debt strategy, architectural debt OKR-lar |

### 6. Martin Fowler-in debt quadrant-ı

| | Reckless | Prudent |
|---|---------|---------|
| **Deliberate** | "Design olmadan yazırıq" | "Ship etməliyik, sonra düzəldirik" |
| **Inadvertent** | "Layering nədir ki?" | "İndi daha yaxşı yolu bildim" |

Yalnız "Prudent + Deliberate" — şüurlu strategik güzəşt — acceptable debt-dir.

### 7. Debt-in real cost-u

Soyut "debt pis" deyil, konkret rəqəm lazımdır:
- Feature X bu modul-a toxunduqda: +3 gün (normal: 1 gün)
- Bu rübdə belə feature 4 ədəd: 4 × 2 gün = 8 developer-day artıq
- Developer daily rate $300 ise: 8 × $300 = $2,400 opportunity cost
- Bu rəqəmi stakeholder-ə göstərmək = debt-in business reality-si

---

## Praktik Baxış

### Debt idarəetmə çərçivəsi

1. **Audit** — "Debt map" yarat: hansı modul, nə qədər problematik, niyə yarandı
2. **Classify** — Critical (blocker), High (velocity azaldır), Low (inconvenient)
3. **Business case** — Hər debt item üçün: indi düzəltməsək nəyə başa gəlir?
4. **Prioritize** — Impact × effort matrix: yüksək impact, az effort → əvvəl
5. **Incremental** — "Strangler fig", "boy scout rule" (hər dəfə girdiyiniz yeri biraz yaxşılaşdır)
6. **Track** — Debt item-ları backlog-da görünür saxlamaq, sprint-ə daxil etmək

### Debt-i stakeholder-lərə izah etmək

**Texniki:** "Bu modul spaghetti kod, test coverage 0%, her dəyişiklik 2-3 gün regression testing tələb edir."

**Business:** "Bu moduldan keçən hər feature 2.3x daha uzun çəkir — digər module-lərə nisbətən."

**Rəqəm:** "Bu rübdə 4 feature × 3 gün əlavə = 12 developer-day itkisi. Team rate-ə görə bu ≈ $18,000 opportunity cost."

### Rewrite vs Refactor qərarı

- **Rewrite:** >80% kod tamamilə fərqli olacaq, business logic aydın sənədlənib, risk qəbul olunub
- **Refactor:** incremental, test coverage var, davranış dəyişmir
- **Strangler fig:** yeni kod köhnəni hissə-hissə əvəz edir, paralel çalışır

### Tez-tez soruşulan follow-up suallar

1. **"How do you convince a product manager to prioritize tech debt over features?"** — Business case ilə: "Bu debt-i düzəltməsək, roadmap-dəki X feature 3 həftə uzanacaq. İndi 1 sprint versək, sonrakı 3 sprinti qurtararıq." Dollar rəqəmi əlavə etmək daha güclüdür.
2. **"What's the difference between technical debt and a bug?"** — Debt: sistematik problem, hər dəyişikliyi yavaşladır. Bug: spesifik səhv davranış. Bəzən overlap olur.
3. **"Have you ever made the wrong call on when to address debt?"** — Hekayə hazır olsun: ya çox tez, ya çox gec; öyrənmə nə idi?
4. **"How do you prevent new debt from being introduced?"** — PR review standards, debt-awareness 1:1-lər, "boy scout rule", ADR yazmaq
5. **"How do you measure whether debt reduction was successful?"** — Velocity artımı, bug rəqəminin azalması, feature cycle time-ın azalması, yeni developer onboarding vaxtının azalması

### Nə deyilməsin

- "Hamısını yenidən yazdıq — heç problem olmadı" — bu çox nadir, soruşulacaq
- "Debt-i müzakirə etdim, heç kim qulaq asmadı" — advocacy bacarığı yox idi
- Yalnız texniki dillə danışmaq — business value bilmirsiniz kimi görünür
- "Indi vaxt yoxdur" — debt özü-özünə böyüyür

---

## Nümunələr

### Tipik Interview Sualı

"How do you manage technical debt in a product team?" / "Tell me about a time you had to advocate for addressing technical debt."

---

### Güclü Cavab (STAR formatında)

**Situation:**
2021-ci ildə çalışdığım şirkətin payment module-u 2015-ci ildə yazılmışdı — test coverage 12%, global variable-lar, mixed concerns, heç bir separation of layers. Hər yeni ödəniş üsulu əlavəsi ortalama 2 həftə çəkirdi. Rəqibimiz eyni işi 3 günə edirdi — bu competitive disadvantage-ə çevrilmişdi. Product manager isə sprint-ə yalnız yeni feature-lar salmaq istəyirdi. 12 nəfərlik teamda mən payment module-un de-facto owner-i idim — ən uzun müddətdir bu kodu touch edən developer.

**Task:**
Debt-i sprint-ə daxil etmək üçün management-i inandırmaq mənim formal vəzifəm deyildi. Amma velocity problem həll olunmadan şirkətin roadmap-i gecikdirəcəkdi. Initiative götürmək qərarı verdim.

**Action:**
İlk addım — data toplamaq: son 6 ayın sprint retrospective-lərini analiz etdim. Payment module-a toxunan hər feature ortalama 2.3x daha uzun çəkirdi digər module-lərlə müqayisədə. Regression bug-larının 68%-i payment module-dan gəlirdi. Bunu spreadsheet-ə çevirdim — vizual olaraq aydın idi. Bu məlumatı PM-ə "texniki problem" deyil "feature delivery gecikməsinin mənbəyi" kimi təqdim etdim.

İkinci addım — business case hazırlamaq: "payment-a toxunmayan işlər daha sürətli bitir" söyləmək əvəzinə, konkret maliyyə rəqəmi hesabladım. 6 aylıq delay + QA overhead = təxminən 8 developer-day artıq xərc hər major feature üçün. Hesab etdim: bu rübdə 4 feature × 3 gün = 12 developer-day, şirkətin developer rate-inə görə bu ≈ $18,000 opportunity cost. Product manager-ə bu rəqəmləri göstərdim. Eyni zamanda rəqibin 3 günlük feature delivery-ni mention etdim — bu competitive angle PM üçün daha aydın idi.

Üçüncü addım — incremental plan: "Hamısını yenidən yazmayaq" dedim. Strangler fig pattern-ni təklif etdim: hər sprint-in 20%-i debt reduction üçün ayrılsın, yeni payment method-lar yeni, clean interface-dən yazılsın, köhnə modul hissə-hissə deprecate olsun. Risk göstərdim: "indi etməsək, Q3-da PayPal integration 4 həftə çəkəcək" — sonra doğru çıxdı, 3.5 həftə oldu.

**Result:**
Management sprint kapasitesinin 20%-ni debt-ə ayırmağa razı oldu. 4 sprint sonra yeni payment method əlavəsi 2 həftə → 4 günə düşdü. Regression bug-ları 68% → 23%-ə düşdü. Team velocity 30% artdı. Product manager özü 6 ay sonra sprint review-da dedi: "Artıq debt budget üçün mənə gəlmirsiniz, özünüz bunu idarə edirsiniz." — Bu, debt management-in team culture-una çevrilməsinin əlaməti idi.

---

### Alternativ Ssenari — "Boy Scout Rule" mədəniyyəti yaratmaq

**Situation:** Monolith-in authentication module-u 2 ildir yenilənməmişdi — test yoxdur, deprecated function-lar var idi. Tam refactor üçün sprint tapmaq çətin idi. Hər sprint "bunu elə gələn sprinte atacağıq" deyilirdi.

**Action:** Formal sprint almadan "boy scout rule" — "hər dəfə bu module-a toxunanlar 1 test əlavə etsin" — informal qaydası qoydum. Code review-da "bu module-ə toxunduqda bu funksiya üçün test əlavə et" comment-ini sistematik yazmağa başladım. PR template-ə "bu modul-u touch etdinizmi? Test əlavə etdinizmi?" sualını əlavə etdim.

**Result:** 3 ayda heç bir scheduled sprint olmadan test coverage 0% → 45%-ə çıxdı. Auth bug-ları 80% azaldı. Bu "incremental improvement" şirkətin engineering culture-ının hissəsinə çevrildi — indi yeni developer-lər onboarding zamanı bu qaydanı öyrənirlər. Formal sprint olmadan, budget müzakirəsi olmadan, 45% coverage artımı.

---

### Zəif Cavab Nümunəsi

"Bizim kodumuzda çox debt var idi. Bir gün manager razı oldu ki, bir sprint debt-ə ayıraq. O sprint-də çox çalışdıq, bir çox şeyi düzəltdik. Sonra yenə feature-lara davam etdik."

**Niyə zəifdər:** Bir dəfəlik "debt sprint" işləmir — debt yenidən toplanır. Business case yoxdur, rəqəm yoxdur, sistematik yanaşma yoxdur. "Manager razı oldu" — siz necə inandırdınız? Nəticə ölçülmür. Bu cavab heç bir strategic thinking göstərmir. "Sonra yenə feature-lara davam etdik" — debt management tamamlanmadı, sadəcə pause edildi.

---

## Praktik Tapşırıqlar

1. **Debt map hazırla:** Öz layihənizdə 3 böyük debt sahəsini tapın. Hər biri üçün: niyə yarandı, indi nə qədər baha başa gəlir (developer-day), düzəltmək nə qədər vaxt alır. Bu xəritəni interview-da mention edə bilərsiniz.

2. **Business case yaz:** Bir debt item üçün 1 səhifəlik business case hazırlayın: problem description (texniki), cost model (developer-day × rate), risk əgər düzəlmədisə (konkret scenario), tövsiyə (strangler fig plan).

3. **Strangler fig plan:** Mövcud bir "problematik modul" üçün strangler fig migration planı yaz — hansı hissə əvvəl, paralel running period nə qədər, rollback strategy, success metrics.

4. **"20% rule" simulyasiya:** Növbəti sprinti planlaşdırın, 20% debt-ə ayırın. Nə seçərdiniz? Impact × effort matrix hazırlayın. Bu exercise debt triage bacarığını göstərir.

5. **Debt tracking sistemini dizayn et:** Debt item-larını backlog-da necə saxlarsınız? Label-lar, priority markers, "cost if deferred" field-ı. Sistematik tracking interviewer-ə strategic thinking göstərir.

6. **"Wrong debt call" hekayəsi:** Debt-ə aid etdiyiniz yanlış bir qərar tapın — ya çox tez etdiniz, ya da çox gec. Bu honest reflection interviewer üçün dəyərlidir.

7. **"Boy scout rule" tətbiq et:** Növbəti həftə işlədiyiniz kod üçün: hər touch etdiyiniz funksiyaya 1 test əlavə edin. Nə qədər vaxt çəkdi? Bu metric-i "incremental improvement" nümunəsi kimi hazırlayın.

8. **Rəqiblə müqayisə etmək:** "Rəqibimiz eyni feature-ı 3 günə edir, biz 2 həftə — bu fərqin 60%-i debt-dən gəlir" kimi competitive angle-ı olan bir hekayə hazırlayın. Bu PM üçün ən aydın business case-dir.

---

## Ətraflı Qeydlər

### Technical debt-in 4 kateqoriyası (Ward Cunningham + Fowler)

```
                  DELIBERATE (bilərəkdən)
                        |
RECKLESS ————————————————————————— PRUDENT
(ehtiyatsız)              |            (ehtiyatlı)
                          |
                  INADVERTENT (bilmədən)

Kvadrant 1 — Reckless + Deliberate:
"Dizayn olmadan yazırıq" — bu pis debt-dir.

Kvadrant 2 — Prudent + Deliberate:
"Şimdi ship etməliyik, sonra düzəldirik" — qəbul edilə bilər.

Kvadrant 3 — Reckless + Inadvertent:
"Layering nədir ki?" — bilgisizlik debt-i.

Kvadrant 4 — Prudent + Inadvertent:
"İndi daha yaxşı yol bilirəm" — öyrənmədən gələn debt.
```

### "Boy Scout Rule" implementation

```php
// Keçmiş kod (toxunmadan əvvəl — test yox):
public function calculateTotal($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item->price * $item->quantity;
    }
    return $total;
}

// Boy Scout Rule ilə — test əlavə edildi, docblock əlavə edildi:
/**
 * Calculate total price for given items.
 * @param Collection $items
 * @return float
 */
public function calculateTotal(Collection $items): float {
    return $items->sum(fn($item) => $item->price * $item->quantity);
}
// + test əlavə edildi
```

Hər touch edildikdə kiçik yaxşılaşdırma — böyük sprint-siz incremental improvement.

### Debt tracking üçün JIRA label sistemi

Debt-i backlog-da visible saxlamaq üçün:
- Label: `tech-debt`
- Custom field: `debt-type` (performance, security, test, architecture)
- Custom field: `cost-if-deferred` (developer-day)
- Priority: High/Medium/Low
- Link: ilgili feature ticket-ləri ilə

Bu sistem debt-i "görünən" edir — sprint planlamasında data ilə müzakirə mümkün olur.

### Debt prevention — yeni debt girişini önləmək

Debt management = repair + prevention. Prevention üçün:
- **Code review standards** — debt-yaradacaq pattern-ları PR-da tut
- **Architecture Decision Records (ADR)** — "niyə bu qərar" yazılı saxla
- **"Definition of Done"** — test coverage, documentation, linting
- **Tech debt budget** — hər sprint-in X%-i debt-ə ayrılmış
- **"No new debt" policy** — yeni module-lər üçün debt-free standard

### Management-i inandırmaq — iqtisadi dil

Engineer-lər debt-i texniki ağırlıqla izah edir. Management isə business impact ilə qərar verir.

**Texniki dil (zəif):**
"Bu kod çox köhnədir, refactor etməliyik."

**Business dil (güclü):**
"Bu module-u dəyişmək hər dəfə 3 gün alır, çünki test yoxdur. Əgər refactor etsək, gələcəkdə eyni iş 4 saata düşər. Bu rüb 6 belə iş var — 12 developer-day saving deməkdir."

### Tech debt cavabında şirkət konteksti

**Startup:** "Move fast" mühitdə debt qaçılmazdır. Hekayənizi "strategic debt" kimi frame edin — "biz sürətli getdik, amma debt-i izlədik və vaxtında ödədik."

**Enterprise:** Böyük sistemlərdə debt tez-tez legacy-dir. "Incremental modernizasiya" — strangler fig pattern — müzakirə edin.

**Scale-up:** Product-market fit tapılıb, indi "engineering excellence" dövrü. "Debt-i performance bottleneck-lər kimi müəyyən etdim, priority sırasında yerləşdirdim."

### Tech debt cavabını bitirmək — ən güclü final cümlə

Result hissəsini konkret nəticə ilə bağlayın:

- "20% sprint qaydası 6 ay sonra velocity-ni 30% artırdı — team daha çox feature çatdırdı, daha az bug ilə."
- "Debt-i görünən etdikdən sonra management özü prioritet vermə mövzusunu sprint planlamasına daxil etdi."
- "Bu approach-un əsas dərsi: debt invisible olduqda heç vaxt prioritet almır — onu ticket kimi tracklamaq hər şeyi dəyişir."

Cavabı "mən nə etdim" ilə deyil, "nə dəyişdi" ilə bitirin.

---

## Əlaqəli Mövzular

- `04-technical-disagreements.md` — Debt prioritizasiyası haqqında anlaşmazlıq
- `08-estimation-planning.md` — Debt-i sprint planlamasına daxil etmək
- `13-leadership-without-authority.md` — Management-i inandırmaq
- `15-system-design-retrospective.md` — Debt yaradan arxitektura qərarları
- `03-greatest-technical-challenge.md` — Debt-i texniki challenge kimi göstərmək
