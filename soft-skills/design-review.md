# Design Review

## Niyə vacibdir? (Why it matters)
Design review (bəzən RFC review və ya technical review adlanır) bahalı səhvlərin qarşısının alındığı yerdir. Bir saatlıq design review həftələrlə implementasiyanı xilas edə bilər. Senior+ mühəndislər üçün əla design review apara bilmək — və yaxşı review dəvət edən sənəd yaza bilmək — ən yüksək effekt verən bacarıqlardan biridir.

Staff-track-ə doğru irəliləyən senior PHP/Laravel mühəndisi üçün komandalar arası design review-lar aparmaq, öz birbaşa komandandan kənarda görünürlük və təsir qurduğun yerdir.

## Yanaşma (Core approach)
1. **Async sənəd + sinxron iclas, yalnız iclas yox.** İnsanların oxumağa və düşünməyə vaxt lazımdır. İclas təqdimat üçün deyil, müzakirə üçündür.
2. **Aydın goal-lar və non-goal-lar.** Pis review-ların yarısı insanların müxtəlif məqsədlər üçün optimallaşdırmasından gəlir.
3. **Bir cavab yox, seçimlər.** Birini üstün tutsan belə, nəzərdən keçirdiyin alternativləri göstər.
4. **Risklər və açıq suallar sənəddə.** Əmin olmadığın şeyləri adlandır. Review edənlər onsuz da tapacaqlar.
5. **Qərarı yaz.** 6 ay sonra sənəd həqiqət mənbəyi olacaq.

## Konkret skript və template (Scripts & templates)

### RFC / Design doc şablonu
```
# RFC: [Title]

## Author, date, status
- Author: [name]
- Date: [YYYY-MM-DD]
- Status: Draft / In Review / Approved / Rejected / Superseded

## Context
Why are we doing this? What problem are we solving? (2-4 paragraphs)

## Goals
- Goal 1
- Goal 2

## Non-goals
- What this does NOT cover. Important for scope control.

## Options considered
### Option A: [name]
- Description
- Pros
- Cons

### Option B: [name]
- Description
- Pros
- Cons

### Option C: [name]
- Description
- Pros
- Cons

## Proposal
We recommend Option [X] because [reasoning].

## Risks
- Risk 1 — mitigation
- Risk 2 — mitigation

## Open questions
- Question 1 — need input from [team/person]
- Question 2

## Rollout plan
- Phase 1
- Phase 2
- Success metrics

## Appendix
Links, benchmarks, diagrams
```

### İclas formatı (1 saat)
```
0-5 min: Author frames the problem and goals
5-15 min: Silent reading of the doc (in the meeting — yes)
15-50 min: Open discussion, going through open questions
50-55 min: Decision (approved / needs revision / rejected)
55-60 min: Action items and next steps
```

### Skript: review-ı açmaq
> "Thanks everyone for making time. Goal of today: decide whether to move forward with [option]. I'll give 5 minutes of context, then we'll spend 10 minutes reading in silence, then open discussion. Please add inline comments as you read."

### Skript: müzakirəni aparmaq
> "Let's go through the open questions in the doc. Question 1: [X]. Who wants to start?"

### Skript: review-ı bağlamaq
> "To summarize where we landed: approved with these changes — [list]. [Name] will update the doc by Friday. Next step: [person] starts implementation Monday. Any disagreements to put on the record?"

### Skript: review edənlər nit-ə dərindən girəndə
> "Good point — let's take that offline. It's important but it's not blocking approval. Add it as a comment, we'll resolve async."

### Skript: review edənlər sakit olanda
> "I want to push a bit — do we have real support here or just silence? If you have concerns, this is the time. Better to disagree now than during implementation."

### Təsdiq siyahısı (kim təsdiqləməlidir)
- **Tələb olunan təsdiqlər:**
  - Sahib olan komandanın tech lead-i
  - Staff/principal mühəndis reviewer (komandalar arası təsir üçün)
- **FYI (xəbər verilməlidir, amma bloklaşdırmır):**
  - Təsirə məruz qalan komandaların tech lead-ləri
  - Security komandası (həssas məlumatla işlənirsə)
  - DBA (schema dəyişiklikləri olarsa)

### Qərar qeydi (sənədə əlavə et)
```
## Decision
Approved on [date]. Chose Option [X].

Approvers:
- [Name] — tech lead
- [Name] — staff reviewer

Disagreements:
- [Name] preferred Option Y because [brief]. Committed to executing Option X.

Changes required before start:
- [item]
```

## Safe phrases for B1 English speakers
- "The goal of today is to decide..." — açılış
- "Let's take 10 minutes to read in silence." — struktur
- "Add comments as you go." — async-dostu
- "Let's go through the open questions." — strukturlu müzakirə
- "What's the concern here?" — etiraza dəvət
- "Can we push this to an async comment?" — nit-ləri parka qoymaq
- "I want to hear from people who haven't spoken." — daxil etmə
- "Is anyone blocking approval?" — real razılaşmamağı yoxlamaq
- "Let's not rehash — that's decided." — irəli getmək
- "To summarize: ..." — bağlama
- "Approvals needed from..." — səlahiyyəti aydınlaşdırmaq
- "Risks I'm holding:" — qeyri-müəyyənliyi adlandırmaq
- "Open to changing if we hear better options." — açıq qalmaq
- "Let's record that as a disagree-and-commit." — müxalifətə hörmət
- "I'll update the doc by Friday." — hərəkət

## Common mistakes / anti-patterns
- Sənəd olmadan review etmək. Lövbərsiz müzakirə.
- İnsanların oxuduğunu güman etmək. Oxumayıblar.
- Yalnız bir seçim. "Stamp of approval" tələb edir, real review yox.
- Goal/non-goal yoxdur. Sonsuz scope debatı.
- Author hər kommentə müdafiə edir. Feedback-i öldürür.
- İclasda 15 nəfər. Teatra çevrilir.
- Sonunda qərar yoxdur. "We'll discuss next week" — momentum ölür.
- Risklər bölməsinə məhəl qoymamaq. Review edənlər hələ də tapacaq.
- Sakit oxuma mərhələsini atlamaq. İnsanlar artıq sənəddə olan şeyləri soruşacaq.
- Bir ucadan səsin hakim olmasına icazə vermək. Növbə ilə danışmağı strukturla.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "Describe how you run a design review."
- "Walk me through a design doc you wrote."
- "What do you do when reviewers disagree?"

Cavab planı:
1. **Struktur:** "I use an async-doc + sync-meeting format. Goals and non-goals up top, options considered, risks, open questions."
2. **İclas formatı:** "I dedicate the first 10 minutes to silent reading. People always claim they read, but they didn't."
3. **Nümunə:** "For the payments refactor, I wrote three options. The one I favored was cheapest. The team pushed back — the more expensive option was more maintainable. I updated the doc and committed to the team's direction. Glad I did — we shipped cleaner code."
4. **Qərar:** "I never end a review without a decision or an action owner. Otherwise momentum dies."

## Further reading
- "Fundamentals of Software Architecture" by Mark Richards and Neal Ford
- "Design It!" by Michael Keeling
- "Philosophy of Software Design" by John Ousterhout
- Google's Design Docs template (various public sources)
- "The Architecture of Open Source Applications" (aosabook.org, free)
