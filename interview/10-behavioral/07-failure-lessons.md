# Failure and Lessons Learned (Senior ⭐⭐⭐)

## İcmal

"Tell me about a failure" — behavioral interview-un ən çox qorxulan, eyni zamanda ən aydınladıcı suallarından biridir. Bu sualın məqsədi sizi utandırmaq deyil — self-awareness, accountability, growth mindset-i ölçməkdir. Mükəmməl cavab: real bir uğursuzluq, şəxsi məsuliyyət, öyrənmə, sistem dəyişikliyi.

Heç bir senior developer uğursuzluq yaşamamışdır — bu mümkün deyil. Həm production bug-ları, həm yanlış arxitektura qərarları, həm deadline-ı qaçırmaq — hamısı baş verir. Sual — bu hadisələri nə etdiyinizdədir.

Bu sualda iki tuzaq var: uğursuzluğu minimize etmək ("hər şey yaxşı bitti") və ya başqasını blame etmək ("PM səhv spec yazdı"). İkisi də red flag-dir.

---

## Niyə Vacibdir

Interviewer bu sualda gözləyir: siz öyrənən bir insanmısınız? Blame etmirsinizmi? Sistemik düzəltmə etdinizmi? "Mən heç uğursuz olmamışam" deyən candidate-i interviewer ciddi götürmür — ya yalan deyir, ya da self-awareness-i yoxdur.

Blameless postmortem culture-u ilə tanış şirkətlər (Google, Netflix, Stripe) bu sualda xüsusilə "sistem düzəltmək" aspektini axtarır. Uğursuzluq fərdin günahı deyil — sistem problemidir; düzgün cavab sistemik dəyişikliyi göstərməlidir.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Accountability** — "mən etdim" deyə bilmək, başqasını günahlandırmamaq
- **Self-awareness** — niyə baş verdiyini anlamaq — texniki, process, ya insan faktoru
- **Growth mindset** — uğursuzluq "sonunun sonu" deyil, öyrənmə fürsəti
- **Systemic fix** — sadəcə özünü düzəltmək deyil, sistemi də düzəltmək
- **Proportionality** — nə qədər böyük idi? Hekayəni həddən artıq dramatik etmədən realist göstərmək
- **Transparency** — o vaxt başqalarını xəbərdar etdinizmi? Eskalasiya etdinizmi?
- **Impact honesty** — nəticəni minimize etmədən, əsl impact-ı söyləmək

### 2. Red flags — zəif cavabın əlamətləri

- "Heç vaxt ciddi uğursuzluğum olmayıb" — inandırıcı deyil
- "Xarici faktor idi — müştəri aydın izah etmirdi" — accountability yoxdur
- Trivial uğursuzluq — "bir dəfə commit-i unutdum" — ciddi deyil
- Hekayəni "amma nəticədə hamı razı qaldı" ilə bitirmək — uğursuzluğu gizlətmək
- Çox dramatik uğursuzluq — şirkət böyük zərər gördü, lakin hekayə proportional deyil
- "Sonra hər şey yaxşı oldu" — öyrənmə yoxdur, real impact minimize edilir
- "Bu team-in problemi idi" — personal accountability yoxdur

### 3. Green signals — güclü cavabın əlamətləri

- Real, concrete uğursuzluq — nədir, nə vaxt, hansı sistem
- Şəxsi məsuliyyəti aydın qəbul etmək — "bu mənim xətamdı, çünki..."
- Nəticə pis idi — minimize etmək yox, dürüst izah
- Öyrənmə spesifik — "indi X qaydası var", "checklist yaratdım"
- Systemic dəyişiklik — "komanda üçün process dəyişdi"
- Bunu başqaları ilə paylaşdım — knowledge transfer
- Emotionally honest — "bu məni çox narahat etdi, amma..."

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Fokus |
|-------------|-------|
| **Amazon** | Frugality + ownership — "nəyi nəzərə almadınız?" |
| **Google** | Blameless culture — "sistem nə öyrəndi?" |
| **Startup** | "Tez səhv et, tez öyrən" — frekans deyil, recovery speed |
| **Enterprise/Bank** | Risk management, process compliance, eskalasiya |
| **Stripe, Shopify** | Postmortem quality, prevention mindset |

### 5. "Failure" seçim kriteriyaları

| Seçim | Uyğunluq |
|-------|---------|
| Trivial (commit unutmaq) | Çox kiçik — interviewer-i məmnun etmir |
| Production downtime | İdeal — real impact, real learning |
| Yanlış arxitektura qərarı | Güclü — long-term thinking göstərir |
| Deadline qaçırmaq (düzgün framing ilə) | Ola bilər — estimation + communication |
| Şirkət böyük maliyyə zərəri | Çox böyük — proportionality problemi ola bilər |

### 6. "Bizim uğursuzluğumuz" vs "mənim uğursuzluğum"

Team uğursuzluğunu seçə bilərsiniz, amma sizin spesifik rol — nə etdiniz, nə etmirdiniz — aydın olmalıdır. "Biz hamımız səhv etdik" hekayəsi accountability-ni diffuse edir. "Mən bu hissəsinə sahib idim, bu addımı atladım" — daha güclüdür.

### 7. Sistemik dəyişiklik — ən vacib hissə

"Nə öyrəndim" ifadəsi personal hekayədir. "Sistem necə dəyişdi" daha yüksək impact göstərir. Hər ikisi olsun:
- Şəxsi öyrənmə: "indi həmişə X edirəm"
- Sistemik dəyişiklik: "team üçün Y prosesi qoydum, Z sənəd yaratdım"

---

## Praktik Baxış

### Cavabı qurmaq

1. **Nə idi uğursuzluq?** — konkret, real, texniki — 30 saniyə
2. **Niyə baş verdi?** — sizin rolu aydın, başqasını blame etmə — 30 saniyə
3. **Nəticə nə oldu?** — minimize etmə, dürüst impact — 20 saniyə
4. **Nə etdiniz?** — həll, mitiqasiya, kommunikasiya — 30 saniyə
5. **Nə öyrəndiniz?** — spesifik, genuine — 30 saniyə
6. **Sistem necə dəyişdi?** — process improvement, bunu prevent etmək — 20 saniyə

### Optimal cavab uzunluğu

3–4 dəqiqə. "Failure" hekayəsini dramatize etmədən, amma minimize etmədən izah etmək balansı lazımdır.

### Tez-tez soruşulan follow-up suallar

1. **"What would you do differently if you were in that situation again?"** — Konkret, spesifik: "daha əvvəl staging-i production volume ilə test edərdim", "peak hour-da deploy etməzdim"
2. **"How did you communicate the failure to your manager/stakeholders?"** — Şəffaflıq: "dərhal manager-ə xəbər verdim, incident timeline hazırladım"
3. **"Did this failure affect your confidence? How did you recover?"** — Emotionally honest: "ilk 2 gün çətin idi, amma postmortem-dən sonra daha əminliklə getdim"
4. **"What did your team learn from this?"** — Sistemik öyrənmə: "bu incident bir process change yaratdı — indi migration checklist-imiz var"
5. **"Can you give me another example of a failure — a different kind?"** — İkinci hekayə hazır olsun: texniki + process ya texniki + interpersonal
6. **"How do you balance moving fast with avoiding failures like this?"** — Trade-off thinking: "staging-in production data-sı ilə doldurulması əlavə vaxt alır, amma bu 18 dəqiqəlik outage-dən baha deyil"
7. **"What's the most important lesson you took from this?"** — Bir cümlə: "Production migration-da 'works in staging' = 'works in production' deyil — data volume həmişə test edilməlidir"

### Nə deyilməsin

- "Hər şeyi düzgün etdim, amma şərait pis idi" — accountability yoxdur
- "O failure-ın böyük dərsi olmadı" — growth mindset yoxdur
- "Sonra həmişə yaxşı idi" — real impact minimize edilir
- "Bu team-in problemi idi" — personal accountability yoxdur

### "Pass" cavabından "Strong Hire" cavabına nə fərq edir

**Pass:** "Bir dəfə migration səhv etdim, downtime oldu, fix etdik."

**Strong Hire:** "Migration 18 dəqiqə table lock saxladı — staging-də data volume fərqi görmüşdüm amma nəticəsini düşünməmişdim. Bu mənim gözdən qaçırdığım bir detalydı. Dərhal manager-ə xəbər verdim. Nəticədə müştəriyə görünən impact oldu. Bu hadisədən üç dəyişiklik etdim: migration checklist, staging-ə production data subset, şirkət-geniş 'peak hours deploy ban'. İki ildə oxşar incident sıfır oldu."

---

## Nümunələr

### Tipik Interview Sualı

"Tell me about a time you failed. What happened and what did you learn?" / "Describe a mistake you made on a project. How did you handle it?"

---

### Güclü Cavab (STAR formatında)

**Situation:**
2021-ci ilin sentyabrında production PostgreSQL database-ə schema migration apardım. Yeni `order_items` cədvəlinə `unit_weight` sütunu əlavə etmək lazım idi — shipping cost calculation üçün. 15M satırlı cədvəl idi. Mən senior backend developer idim, bu migration-ın planlaşdırılması, testi və deploy-u mənim məsuliyyətimdə idi. Şirkət e-commerce platform idi, gündəlik 800+ order işlənirdi.

**Task:**
Migration-u staging-də sınadım, approve aldım, production-a deploy etdim. İlk baxışdan sadə görünən bir əməliyyat idi. Heç kimə məsləhət soruşmadım — "standart column əlavəsidir" düşünürdüm.

**Action (nə etdim/etmirdim):**
Staging-də cəmi 10K row var idi — migration 0.3 saniyə çəkdi. Production-da 15M row üçün `ALTER TABLE ... ADD COLUMN` 18 dəqiqə table lock saxladı. Bu müddətdə bütün write əməliyyatları bloklandı — sifarişlər qəbul edilə bilmirdi.

Nəyə görə baş verdi? `ALTER TABLE ... ADD COLUMN NOT NULL DEFAULT` PostgreSQL-də table rewrite tələb edir. `CONCURRENTLY` flag-ini bilirdim, amma bu addımı atladım. Staging-i production-a uyğun data volume ilə test etmədim. Dəyişikliyin business impact-ını düşünmədim — peak saat (cümə axşamı saat 20:00) seçilmişdi.

Birinci 5 dəqiqədə nə etdim: monitoring-də alert gəldi, incident channel-da xəbər verdim, manager-ı çağırdım. Rollback mümkün deyildi — migration tamamlanmışdı. Əl ilə monitoring etdim, lock-ın qaldırılmasını gözlədim. 18 dəqiqə sonra sistem normal rejiminə qayıtdı.

**Result:**
18 dəqiqə downtime. Müştərilərdən şikayətlər gəldi. Biznesdə gözlənilən maliyyə zərəri kiçik idi (peak saatda idi, amma şimdiki traffic-də ~$2,400), amma şirkətin etibarlılığına toxundu. CTO ilə 1:1 keçirdim, tam accountability götürdüm. Heç kimi blame etmədim.

**Öyrənmə + Sistem dəyişikliyi:**
Bu hadisədən üç sistemik dəyişiklik etdim:

1. **Migration checklist yaratdım** — hər migration üçün məcburi: row count, estimated lock time (`pg_class` ilə), peak hours yoxlaması, rollback plan, zero-downtime alternative (`CONCURRENTLY`, `gh-ost`).

2. **Staging data policy** — staging-ə aylıq anonymized production subset (10% sample) sync etmək proseduru qoydum. Bu, production volume-a yaxın test imkanı yaratdı.

3. **Company-wide migration policy** — şirkətin runbook-una "zero-downtime migration requirement" əlavə edildi: `CONCURRENTLY`, `gh-ost`, ya `pt-online-schema-change` nə zaman istifadə olunur — sənədləndi. Həmin sənədi özüm yazdım.

Bu uğursuzluğun peşmanlığını hala hiss edirəm. Amma qazandığım şey daha böyük: sonrakı 2 ildə oxşar əməliyyatlar üçün sıfır incident. O checklist komandanın standart proseduru oldu — özüm yaratdım, amma artıq "ours" idi.

---

### Alternativ Ssenari — Yanlış arxitektura qərarı

**Situation:** 2020-ci ildə notification sistemini "real-time" etmək üçün sync HTTP push seçdim — "sadə, tez işləyir." 10K notification/gün zamanı ideal idi.

**Accountability:** 100K/gün olduqda timeout-lar başladı, DB connection pool tükəndi. Async queue seçməli idim, amma over-engineered görünür deyə atladım. "YAGNI" prinsipi ilə özümü inandırdım — amma bu halda yanlış tətbiq idi. Growth trajectory-ni nəzərə almamışdım — marketing həmin dövrdə "agresif growth" planlaşdırırdı.

**Result:** 3 gün downtime riski, emergency refactor, async job queue-ya keçid. Team 2 sprint daha gec roadmap-ə davam etdi.

**Öyrənmə:** "I/O bound + high volume = async default." İndi hər yeni servis üçün "bu 10x böyüsə nə olar?" sualını öncədən soruşuram. Həmçinin "YAGNI" prinsipini daha diqqətli tətbiq edirəm — YAGNI code complexity üçün keçərlidir, amma scale tələbatı üçün əvvəlcədən düşünmək lazımdır.

---

### Zəif Cavab Nümunəsi

"Bir dəfə deadline-ı qaçırdım. Çox işim var idi, PM də çox şey istəyirdi. Nəhayət işi bitirdim, amma bir gün gec. Manager razı qalmadı, amma başa düşdü. Bir daha deadline-ı qaçırmamağa çalışıram."

**Niyə zəifdər:** "PM çox şey istəyirdi" — blame. "Manager başa düşdü" — impact minimize edildi. Heç bir texniki detail yoxdur. Öyrənmə spesifik deyil. Sistem dəyişikliyi yoxdur. "Çalışıram" — hərəkətsiz söz, konkret deyil. Bu cavab uğursuzluğu "amma hər şey yaxşı bitti" ilə bitirməyə cəhd edir — bu interviewer üçün red flag-dir.

---

## Praktik Tapşırıqlar

1. **Real uğursuzluq seç:** Karyeranızda 3 real uğursuzluq tapın. Hər biri üçün: niyə baş verdi, sizin rolu nə idi, nəticə nə oldu? Ən güclüsünü seçin — məsuliyyət aydın, öyrənmə konkret, sistem dəyişdi.

2. **Accountability check:** Hekayənizdə "biz" əvəzinə "mən" işlədilən yerləri yoxlayın. "Mən nə etmirdim" aydındırmı?

3. **Systemic fix əlavə edin:** Hekayənizdə "artıq bu cür etmirəm" ifadəsindən sistemi dəyişdirdiyiniz şey varmı? Əgər yoxdursa — əlavə edin. "Checklist yaratdım", "policy dəyişdirdim", "monitoring əlavə etdim."

4. **"Tone" tarazlığı:** Hekayəni danışın. "Özünüzü çox vuran" hissə balanslandırılmışmı? Nəticəni minimize etmirsinizmi, dramaya da getmirsinizmi?

5. **"Second failure" hazırla:** Interviewer "başqa nümunə verin" soruşa bilər. Fərqli tipli uğursuzluq: texniki (production incident) + process (yanlış qiymətləndirmə) + interpersonal (kommunikasiya boşluğu).

6. **"What did your team learn?" cavabı:** Öz uğursuzluğunuzdan başqa komandanın nəyi dəyişdirdiyi barədə hazır olun. Sistemik impactı göstərmək fərdi öyrənmədən daha güclüdür.

7. **"Confidence recovery" hekayəsi:** Uğursuzluqdan sonra necə bərpa oldunuz? "İlk həftə çətin idi, amma postmortem-dən sonra daha güclü hiss etdim" kimi authentic emotional arc — interviewer empathy göstərir.

8. **Dollar impact hesabla:** Uğursuzluğunuzun real cost-unu hesablamışmısınız? Downtime minuetlə nə qədər? Müştəri kaçışı? Bu rəqəm hekayənizi daha concrete edir — interviewer-ə "o developer real-world impact anlayır" siqnalı verir.

---

## Ətraflı Qeydlər

### "Failure" hekayəsinin 3 arxetipi

**Arxetip 1 — Technical failure:**
Production incident, yanlış migration, deployment səhvi. Bu hekayə texniki learning + systemic fix göstərir. İdeal: postmortem var, action items tamamlandı.

**Arxetip 2 — Process failure:**
Yanlış estimation, kommunikasiya boşluğu, requirements missunderstanding. Bu hekayə process improvement göstərir. İdeal: yeni checklist, template ya workflow yaratdınız.

**Arxetip 3 — Decision failure:**
Yanlış arxitektura seçimi, wrong technology choice, premature optimization. Bu hekayə long-term thinking göstərir. İdeal: o qərarın niyə o vaxt doğru göründüyünü, indi nəyin dəyişdiyini izah edirsiniz.

### Blameless postmortem — 5 Why analizi nümunəsi

Production database migration downtime incident üçün:

```
Problem: 18 dəqiqə production downtime

Why 1: ALTER TABLE table lock saxladı
Why 2: NOT NULL DEFAULT column əlavəsi table rewrite tələb edir
Why 3: Bu davranışı bilmirdim
Why 4: Migration-ın PostgreSQL davranışını test etmədim
Why 5: Staging data volume production-a uyğun deyildi

Root cause: Staging məlumat həcmi production-a uyğun deyil
Action: Staging-ə production subset sync proseduru quruldu
```

5 Why — "individual blame" yox, "systemic gap" tapır.

### Migration checklist nümunəsi (PHP/PostgreSQL)

Bu checklist hekayənizin "sistemik dəyişiklik" hissəsini konkretləşdirir:

```markdown
## Production Migration Checklist

Əvvəl:
- [ ] Row count: SELECT COUNT(*) FROM table_name;
- [ ] Estimated lock time: online calculator + pg_class
- [ ] Peak hours qiymətləndirildi (deploy 09-17 arası?)
- [ ] Rollback plan yazıldı
- [ ] Zero-downtime alternativ nəzərə alındı (CONCURRENTLY, gh-ost)
- [ ] Staging-də test edildi (data volume uyğun?)

Sonra:
- [ ] Monitoring normal göstərir?
- [ ] Error rate baseline-da?
- [ ] Performance metrics stable?
- [ ] Rollback trigger condition meeting edilmdi?
```

### "Two-failure" hazırlığı

Müsahibəçi tez-tez 2-ci failure soruşur: "Başqa bir nümunə?" Hazır olun:
- **Tip 1:** Technical failure (migration, deployment, race condition)
- **Tip 2:** Process/communication failure (yanlış estimation, gecikən escalation)
- **Tip 3:** Decision failure (yanlış arxitektura, wrong tool choice)

Bu üç tipdən 2-si hazır olsun — müsahibəçi "fərqli bir tip" istəyə bilər.

### Failure hekayəsini bitirmək — ən güclü final cümlə

Cavabın sonunda "indi nə fərqli edir?" sualına hazırlıq:

- "Bu hadisədən sonra hər migration-a staging data volume testi olaraq daxil etdim. 2 il ərzində production-da heç bir benzer hadisə baş vermədi."
- "Bu uğursuzluğun ən böyük dərsi: mən texniki detalı bilirdim, amma onun impact-ını düşünməmişdim. İndi hər dəyişiklikdə 'worst case nədir?' sualını birinci verirəm."
- "Bu incident sistematik dəyişiklik yaratdı — komanda-daxili policy yazdıq. Bu policy hazırda bütün engineering team tərəfindən istifadə edilir."

Result hissəsini "personal change + systemic change" ilə bitirmək — yetkinlik siqnalı.

### "Blame vs accountability" — fərq

Müsahibəçi diqqət edir:

**Blame mindset (red flag):**
- "Testlər bunu tutmalıydı."
- "DevOps mühiti düzgün qurmamışdı."
- "PM tez qərara gəldi."

**Accountability mindset (green flag):**
- "Mən staging-də yeterli data hacmi olmadığını yoxlamamışdım."
- "Bu deployment risk siqnalını görüb escalate etməliyidim."
- "Checklist-i mən hazırlamalıydım — etmədim."

"Mənim rolum nə idi?" — bu sualın dürüst cavabı hekayəni gücləndirir.

---

## Əlaqəli Mövzular

- `01-star-method.md` — STAR çərçivəsi ilə failure hekayəsi
- `10-incident-handling.md` — Production failure kimi incident
- `06-managing-technical-debt.md` — Debt yaradan uğursuzluq qərarları
- `15-system-design-retrospective.md` — Architecture failure retrospective
- `03-greatest-technical-challenge.md` — Challenge vs failure fərqi
