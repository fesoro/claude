# B1 → B2 Keçid Planı (4 Ay)

A2/B1 səviyyəsindən B2-yə keçid üçün strukturlaşdırılmış plan. Backend developer, remote iş, relocation hədəfi.

---

## Fərq: B1 vs B2

| Skill | B1 (İndi) | B2 (Hədəf) |
|-------|-----------|------------|
| **Speaking** | Tanış mövzularda özünü ifadə edə bilirsən, lakin duruxmalar olur | Texniki mövzularda da axıcı danışırsan, mürəkkəb fikirləri dəqiq çatdırırsan |
| **Writing** | Sadə email və mesajlar yaza bilirsən, bəzən qrammatik səhvlər olur | Formal email, PR description, design doc professional səviyyədə yaza bilirsən |
| **Listening** | Yavaş, aydın nitqi başa düşürsən, native sürət çətindir | Native sürətli söhbəti, texniki podcast-ı əsasən anlayırsan |
| **Reading** | Sadə texniki mətnləri başa düşürsən, mürəkkəb sintaks yavaşladır | Rəsmi sənədlər, RFC-lər, arxitektura yazıları oxuyursan — axıcı |
| **Grammar** | Hazırkı zaman, sadə keçmiş, Future — səhvsiz; mürəkkəb strukturlarda çəkinkinlik | Past Perfect, Passive bütün zamanlarda, Conditional 3/mixed, Inversion — aktiv istifadə |

---

## B1 → B2 üçün Açar Qrammatika Mövzuları

### 1. Past Perfect — "I had finished before he arrived"
> "By the time the deploy ran, I had already fixed the bug."

Niyə vacibdir: Hadisələrin ardıcıllığını dəqiq ifadə edir. Interview-da "tell me about a time when..." suallarında Past Perfect olmadan cavab qeyri-dəqiq olur.

---

### 2. Passive Voice — Bütün Zamanlarda
> "The API was designed by the core team." / "The cache will be invalidated automatically." / "The data has been migrated."

Niyə vacibdir: Texniki yazıda — PR description, postmortem, design doc — passive voice standarddır. "The system handles X" yox, "X is handled by the system."

---

### 3. Reported Speech — Tam Versiya
> Direct: "The deadline is Friday." → Reported: "He said that the deadline **was** Friday."
> Direct: "I will fix it." → Reported: "She said she **would** fix it."

Niyə vacibdir: Meeting nəticələrini async şəkildə başqasına çatdıranda — Slack-də, emaildə — reported speech istifadə olunur. "He said he would..." professional səslənir.

---

### 4. 3rd və Mixed Conditionals
> 3rd: "If I had reviewed the PR earlier, the bug wouldn't have reached production."
> Mixed: "If I had studied caching earlier, I would be more confident now."

Niyə vacibdir: Postmortem yazanda, keçmiş qərarları analiz edəndə, retrospektivdə — bu strukturlar professional düşüncəni göstərir.

---

### 5. Relative Clauses — whose / whom
> "The engineer **whose** PR caused the incident is investigating it."
> "The colleague **with whom** I pair-programmed left the company."

Niyə vacibdir: Mürəkkəb texniki cümlələri bir cümlədə ifadə etmək üçün. Formal yazıda "with whom" standartdır.

---

### 6. Discourse Markers
> "The monolith is easier to deploy. **However**, it doesn't scale horizontally."
> "**Nevertheless**, we decided to proceed with microservices."
> "The first approach uses more memory. **In contrast**, the second approach is CPU-heavy."

Niyə vacibdir: Design doc, RFC, texniki bloq — discourse markers olmadan mətn parçalı görünür. B2 markeri mürəkkəb arqumentasiyanı bağlayır.

---

### 7. Modal Perfects — should have / could have / must have
> "We **should have** added rate limiting from the start."
> "The server **must have** crashed during the migration."
> "We **could have** used a queue instead of a direct call."

Niyə vacibdir: Postmortem yazanda, code review-da, incident analysis-də — modal perfects dəqiq texniki mühakimə üçün vacibdir.

---

### 8. Inversion — Not only did I... / Had I known...
> "**Not only did** the service crash, but it also corrupted the data."
> "**Had I known** about the memory leak, I would have fixed it sooner."
> "**Rarely do** we encounter such a complex race condition."

Niyə vacibdir: Senior engineer yazdığı şey formal inversion ilə nüanslı, peşəkar görünür. Interview-da, texniki yazıda güclü təsir buraxır.

---

## Aylıq Plan (4 Ay)

### Ay 1 — Qrammatika Özəyi

**Hədəflər:** Past Perfect, Passive Voice (bütün zamanlarda), Reported Speech

| Həftə | Mövzu | Gündəlik Məşq | Ölçüm |
|-------|-------|--------------|-------|
| 1 | Past Perfect | 5 cümlə yaz (real iş situasiyası) | Sənin yazdıqlarının 4/5-i düzgün olsun |
| 2 | Passive — Present/Past | PR description-ı passive ilə yenidən yaz | 1 əsl PR description passivlərlə |
| 3 | Passive — Future/Perfect | Email şablonları passive ilə | Passive səhvsiz 5 müxtəlif zaman |
| 4 | Reported Speech | Meeting qeydini reported speech ilə Slack-ə yaz | Bir tam paragraf reported speech |

**Gündəlik vaxt:** 20 dəqiqə (10 qrammatika + 10 yazma məşqi)

---

### Ay 2 — Qrammatika + Listening

**Hədəflər:** 3rd/Mixed Conditionals, Modal Perfects + gündəlik dictation

| Həftə | Mövzu | Gündəlik Məşq | Ölçüm |
|-------|-------|--------------|-------|
| 1 | 3rd Conditional | Keçmiş bug-lar haqqında "If I had..." cümlələr | 5 düzgün 3rd conditional |
| 2 | Mixed Conditional | Postmortem-dən 3 mixed conditional cümləsi | Mənalı mixed conditional istifadəsi |
| 3 | Modal Perfects | Code review comment-ləri modal perfect ilə | 5 fərqli modal perfect istifadəsi |
| 4 | Konsolidasiya | 1-3-cü həftəni qarışıq test et | 80%+ düzgünlük nisbəti |

**Gündəlik vaxt:** 30 dəqiqə (15 qrammatika + 15 dictation — BBC 6 Minute English)

**Dictation məşqi:**
1. BBC 6 Minute English epizodunun bir parçasını dinlə
2. Eşitdiklərini yaz
3. Transcript ilə müqayisə et
4. Fərqləri analiz et — anlamamışdın ya yazmamışdın?

---

### Ay 3 — Speaking Mürəkkəbliyi

**Hədəflər:** Discourse Markers, mürəkkəb cümlələr, system design müzakirəsi

| Həftə | Fəaliyyət | Hədəf |
|-------|-----------|-------|
| 1 | Discourse markers: however/nevertheless/in contrast | Texniki yazıda hər birini istifadə et |
| 2 | Relative clauses: whose/whom | 3 mürəkkəb cümləni bir cümlə kimi yaz |
| 3 | System design mövzusunu izah et (ingilis dilində, 2 dəqiqə) | Record et → dinlə → təkmilləşdir |
| 4 | Mock interview (özünlə və ya ChatGPT ilə) | STAR metodu + B2 grammar strukturları |

**Gündəlik vaxt:** 30 dəqiqə (15 speaking məşqi + 15 listening — All Ears English / B1 podcast)

---

### Ay 4 — İnteqrasiya + Yazma

**Hədəflər:** Formal writing, uzun mətnlər, B2 reading, inversion

| Həftə | Fəaliyyət | Hədəf |
|-------|-----------|-------|
| 1 | Inversion (Not only did I / Had I known) | Design doc-da 2 inversion cümləsi |
| 2 | Formal email (200 söz) — heç bir qrammatika səhvi yox | Grammarly + manual yoxlama |
| 3 | B2 oxuma: RFC, texniki blog, arxitektura sənədi | 15 dəqiqədə 500 söz anlayaraq oxu |
| 4 | Full integration test | Aşağıdakı B2 checklist |

**Gündəlik vaxt:** 35 dəqiqə (20 yazma + 15 oxuma/listening)

---

## Ölçüm Meyarları (B2-yə Çatdığını Necə Biləcəksən)

### Listening
- [ ] Software Engineering Daily epizodunu (texniki mövzu) 70%+ anlayırsan
- [ ] Native speed söhbəti (Stuff You Should Know) 65%+ anlayırsan
- [ ] BBC News podcast-ını subtitrsiz anlayırsan

### Speaking
- [ ] Texniki konsepti (məs: caching strategiyaları) 2 dəqiqə ərzində aydın izah edə bilirsən
- [ ] System design interview sualına 5 dəqiqə strukturlu cavab verə bilirsən
- [ ] "Tell me about a time when..." sualına STAR + Past Perfect ilə cavab verə bilirsən

### Writing
- [ ] 200 söz formal email — Grammarly 0 critical error
- [ ] PR description — passive voice, discourse markers, professional
- [ ] Postmortem şablonu — modal perfects, 3rd conditional — natural görünür

### Reading
- [ ] AWS/GCP arxitektura sənədini oxuyub əsas məqamları çıxara bilirsən
- [ ] GitHub RFC və ya ADR (Architecture Decision Record) anlayırsan

### Grammar
- [ ] 8 açar qrammatika mövzusunu istifadə edə bilirsən — səhvsiz
- [ ] Inversion cümlələri qurmaq üçün fikirləşmirsən

---

## Hər Ay Sonu Yoxlama

Aşağıdakı testi özün üçün et:

1. BBC 6 Minute English epizodunu subtitrsiz dinlə → anlama faizini qeyd et
2. Real situasiyadan (meeting, PR, email) bir parçanı seç → 8 qrammatika mövzusundan istifadə et
3. Texniki mövzunu ingilis dilində 2 dəqiqə izah et → record et → dinlə

**Hədəf faiz:**
- Ay 1 sonu: Grammar testdə 70%+
- Ay 2 sonu: Dictation 75%+
- Ay 3 sonu: Speaking 2 dəqiqə — rahat, duruxmasız
- Ay 4 sonu: Yuxarıdakı B2 checklist-in 80%+
