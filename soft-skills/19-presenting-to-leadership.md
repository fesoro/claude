# Presenting to Leadership (Senior)

## Niyə vacibdir? (Why it matters)
Senior mühəndislərin əksəriyyəti kod yazırlar, amma şirkətin real gücü olan insanlarla — director, VP, C-level — effektiv danışa bilmirlər. Rəhbərlik demo, all-hands, QBR (quarterly business review) və ya incident update kimi format-larda sənin işini qiymətləndirir. Bu anlarda yaxşı danışan mühəndis görünür, investisiya qazanır, career acceleration yaşayır. Pis danışan mühəndis isə "texniki insan" kimi qalır — yaxşı iş olsa belə.

Senior PHP/Laravel mühəndisi üçün bu bacarıq staff-track keçidi üçün çox vacibdir: staff mühəndislər rəhbərliyin dilinə bir körpü qurur.

## Yanaşma (Core approach)
1. **Exec audience-i üçün yenidən yaz.** Texniki detal onları maraqlandırmır. Onları maraqlandıran: biznes impact, risk, timeline, resurs. Hər texniki şeyi bu linzadan keçir.
2. **Birinci cümlə = cavab.** Prezentasiyanı analizlə açma. Cavabla aç, sonra dəstəkləyici detallar.
3. **Maksimum 3 əsas mesaj.** Exec-lər 10 şeyi xatırlamır. 3 şeyi xatırlayırlar. Hər şeyi oraya qoy.
4. **Hazırlıq = sual simulator.** Ən çətin 5 sualı yaz. Cavablarını hazırla. Adətən onlar soruşulur.
5. **Konfidans ton — amma "false confidence" yox.** Bilmədiyini bilmək cəsarətidir. "I don't know but I'll find out" dürüstdür. "Everything is great" isə şübhə yaradır.

## Konkret skript və template (Scripts & templates)

### Opening formula (hər prezentasiyada)
```
In [timeframe], we [what we did].
The result: [business impact — number or outcome].
Today I want to cover: [3 points].
```

Nümunə:
> "In Q1, we migrated the checkout service to a new architecture. We reduced checkout failures by 62% — that's roughly 3,000 fewer failed orders per week. Today I'll cover: what we built, what we learned, and what's next."

### Demo format (feature showcase)
```
Problem: [1 sentence — what users couldn't do before]
Solution: [what you built — 1-2 sentences]
Demo: [live or recording — show the happy path only]
Impact: [number or outcome]
Next: [1-2 things coming in the next sprint/quarter]
```

Exec-lərə demo edərkən:
- Yalnız happy path göstər. Edge case-ləri demo-dan çıxar.
- Həmişə staging-dən deyil, hazırlanmış script ilə demo et.
- "If something breaks, I have a backup" fikirlə başla.

### QBR / quarterly update format
```
## [Team name] — Q[X] Update

### What we delivered
- [Initiative 1]: [outcome with number]
- [Initiative 2]: [outcome with number]

### What didn't go as planned
- [Item]: [reason + what we learned]

### Q[X+1] priorities
1. [Priority 1] — why: [business reason]
2. [Priority 2] — why: [business reason]

### Ask (if any)
- [Resource / decision / escalation needed]
```

### Incident update (rəhbərlik üçün)
```
Status: [Resolved / Ongoing]
Impact: [X users affected, Y hours, Z revenue estimate if known]
What happened: [2-3 sentences, no jargon]
What we did: [bullet points]
Current state: [one clear sentence]
Next steps: [actions with owners]
What we're doing to prevent it: [1-2 items]
```

Nümunə:
> "Status: Resolved. For 47 minutes this morning, checkout was unavailable for users on mobile. Roughly 1,200 users were affected. Root cause: a deploy introduced a null pointer exception in the payment handler. We rolled back within 12 minutes of detection. We're adding a new test to prevent this class of bug — shipping by Friday."

### Sual idarəsi (Q&A)
**"When will this be done?"**
> "Current estimate: [date]. Risks that could affect that: [X]. Confidence: [high/medium]. I'll flag you if anything changes."

**"Why did this take so long?"**
> "Two reasons: [X] and [Y]. The biggest unexpected cost was [Z]. Here's what we'll do differently next time."

**"Can we do X instead?"**
> "Good question. The trade-off: [if we do X, then Y happens]. My recommendation: [your view]. But this is your call — I wanted you to see the trade-off."

**Bilmədiyini bilmək:**
> "I don't have that number here. I'll send it by end of day."

### Suala hazırlıq (before the meeting)
```
1. "What's the status?" — answer in 2 sentences
2. "What's the risk?" — answer with mitigation
3. "Why does this matter?" — business framing
4. "What do you need from me?" — clear ask
5. "What went wrong?" — honest, no blame
```

## Safe phrases for B1 English speakers
- "The short version:" — icraçı rejimi
- "Bottom line:" — nəticəyə keçmək
- "The impact was [number]." — rəqəmlə danışmaq
- "Here's what I'd recommend, and the trade-off." — qərar çərçivəsi
- "This is a judgment call — I want your input." — rəhbərliyi daxil etmək
- "I don't have that number, but I'll follow up." — dürüst cavab
- "Let me show you the before and after." — demo açılışı
- "The risk I see is..." — proaktiv
- "We learned [X] and we're applying it to [Y]." — retrospektiv
- "Any questions before I move on?" — nəzarət
- "I'll keep this brief." — exec vaxtına hörmət
- "The one thing I want you to remember is..." — əsas mesaj
- "Can I have 5 minutes on the next agenda?" — görünürlük fürsəti
- "Happy to go deeper if useful." — dərinliyə girmək üçün icazə almaq
- "I'll send the slides after the call." — follow-up

## Common mistakes / anti-patterns
- Exec-ə texniki dil danışmaq. "We refactored the ORM layer" — maraqlandırmır.
- Cavabı son slide-da gizlətmək. Cavabı birinci söylə.
- Demo-nu live environment-da etmək. Həmişə hazırlanmış script.
- 20 şey söyləmək. 3 şey söylə, yaxşı söylə.
- Sualları hazırlamamaq. Ən çətin 5 sualı əvvəlcədən cavabla.
- "Everything is going great" deyib problemi gizlətmək. Rəhbərlik hiss edir.
- Pis xəbəri son anda çatdırmaq. Erkən flag et.
- Tükənmiş, həddən artıq uzun slides. Exec audience: maksimum 5-7 slide.
- Bütün context-i vermək, sonra nöqtəni. Əvvəl nöqtə, sonra context.
- "I think maybe perhaps..." — güvənsiz dil. Qəti danış.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "How do you communicate technical decisions to non-technical leaders?"
- "Tell me about a time you had to present to senior leadership."
- "How do you handle a difficult question from an exec in a meeting?"

Cavab planı:
1. **Çərçivə:** "I adjust the level of detail to the audience. For leadership, I lead with business impact — not technical implementation."
2. **Nümunə:** "I presented a performance degradation incident to our VP of Engineering. I opened with: '47-minute outage, 1,200 users, root cause and prevention plan in 3 slides.' She appreciated the directness and asked two smart follow-up questions I'd already prepared for."
3. **Sual idarəsi:** "If I don't know the answer, I say so and commit to following up. I'd rather do that than give a wrong confident answer."
4. **Format:** "I use a max of 5 slides: context, what we did, impact, risks, ask."

## Further reading
- "The Pyramid Principle" by Barbara Minto (executive communication structure)
- "Presentation Zen" by Garr Reynolds (visual communication)
- "Resonate" by Nancy Duarte (storytelling for presentations)
- "Crucial Conversations" by Patterson, Grenny, McMillan, Switzler
- Will Larson's blog — posts on staff engineers communicating upward
