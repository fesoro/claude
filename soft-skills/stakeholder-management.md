# Stakeholder Management

## Niyə vacibdir? (Why it matters)
Stakeholder — uğuru sənin işindən asılı olan və ya sənin işinə təsir edən hər kəsdir — PM, dizayner, rəhbərlik, müştərilər, digər eng komandaları. Stakeholderləri yaxşı idarə edən senior mühəndislər komandasını xaosdan qoruyur: scope creep, qeyri-real deadline-lar, bir-birinə zidd prioritetlər. Stakeholderləri idarə etməyən senior mühəndislər reaktiv olur, həddən artıq öhdəlik götürür və tükənir.

Senior PHP/Laravel mühəndisi üçün stakeholder bacarıqları çox vaxt "senior mühəndis"-i "staff mühəndis"-dən ayıran şeydir. Bu "feature göndərir" ilə "nə qurulacağını formalaşdırır" arasındakı fərqdir.

## Yanaşma (Core approach)
1. **Stakeholderlərini xəritələ.** Sənin işinə kim əhəmiyyət verir? Resursları kim qərar verir? Səni kim bloklayır? Siyahını yaz.
2. **Müxtəlif auditoriyalar üçün müxtəlif dillər.** Exec-lər biznes təsiri və risk istəyir. PM-lər scope və timeline istəyir. Mühəndislər design trade-off istəyir. Tərcümə et.
3. **Proaktiv update-lər, sürpriz yox.** Stakeholderlərə onlar soruşmamışdan əvvəl məlumat ver. 2 dəqiqəlik həftəlik update 2 saatlıq fövqəladə iclasın qarşısını alır.
4. **Alternativlərlə "yox".** Stakeholder-ə yol təklif etmədən heç vaxt "yox" demə.
5. **Əvvəl icraçı xülasələr.** Liderlər birinci paraqrafı oxuyur. Cavabı ora qoy.

## Konkret skript və template (Scripts & templates)

### Stakeholder xəritə şablonu
```
## Project: [X]
| Name | Role | What they care about | Update cadence |
|------|------|----------------------|----------------|
| [PM] | Product | Timeline, scope, user impact | Weekly |
| [Director] | Eng leadership | Risk, progress, dependencies | Biweekly |
| [Designer] | Design | UX constraints, handoff | As needed |
```

### PM-ə həftəlik update (qısa saxla)
```
Week of [date] — [project name]

Status: Green / Yellow / Red
This week:
- Completed: [1-2 items]
- In progress: [1-2 items]
Next week:
- [1-2 items]
Risks:
- [risk] — mitigation: [X]
Asks:
- [blocker needing PM help, if any]
```

### İcraçı xülasə (rəhbərlik üçün)
Qayda: birinci cümlə = cavab. Növbəti 2 = niyə vacibdir. Dəstəkləyici detallar sonra.

```
[Project] will ship 2 weeks late due to an unplanned database migration.

New timeline: April 30. Confidence: high.
Impact: delays the marketing launch by 1 week; PM has confirmed this is acceptable.
Reason: staging revealed data volume that requires an online migration pattern. Detail below.
```

### Scope danışıqları (PM-ə)
> "Looking at the sprint, we can't do A + B + C in two weeks. Here are three options:
> 1. A + B only — ships on time.
> 2. All three with cut scope on C (no admin UI) — ships on time.
> 3. All three as specified — one week late.
> Which fits your goals best?"

### Alternativlərlə "yox" demək
> "I can't take on this project in Q2 without dropping something. Options:
> - Drop [current project] — ships 6 weeks later.
> - Take it on in Q3 — 3 months from now.
> - Scope down to a smaller v1 — 2 weeks, 60% of the value.
> Which works?"

### Yuxarı idarə — rəhbərlik iclasından əvvəl menecerinə brifinq
> "Before the review with [director], here's where I am: [1-line status]. Risks: [1-2]. What I need: [nothing / air cover on X / decision on Y]. Anything you want me to emphasize?"

### Rəhbərlikdən gələn sürpriz istəyi idarə etmək
> "Let me think about the impact before committing. Can I come back to you by end of day with a clear answer and trade-offs?"

### Müştəri demo-su reallığa uyğun olmayanda
> "What you saw in the demo was the happy path. In production we still have [known issues]. Before you commit to a customer timeline, let me walk you through what's ready vs what's roadmap."

## Safe phrases for B1 English speakers
- "What does success look like for you?" — nəticə üzərində uyğunlaşma
- "What's your priority between A and B?" — seçimə məcbur etmək
- "Let me give you the short version." — icraçı rejimi göstərmək
- "Bottom line: [1 sentence]." — güclü açılış
- "I want to set expectations." — reallığı çərçivələmək
- "Here are three options with trade-offs." — strukturlu seçim
- "I'll keep you posted." — gələcək update-lər
- "No surprises — flagging early." — proaktiv
- "I want to align before we proceed." — buy-in yoxlamaq
- "This is a judgment call, it's your decision." — səlahiyyəti geri vermək
- "Help me understand the business goal." — yuxarı çəkmək
- "I hear the urgency." — təzyiqi təsdiqləmək
- "Let me check the impact on the team and come back." — vaxt qazanmaq
- "The risk I see is..." — narahatlıqları aydın qaldırmaq
- "I'd recommend [X], for these reasons..." — yol göstərmək

## Common mistakes / anti-patterns
- Stakeholderləri pis xəbərlə sürpriz etmək. Həmişə erkən xəbər ver.
- Qeyri-texniki insanlarla texniki dil istifadə etmək.
- Rəhbərliyi razı salmaq üçün həddən artıq söz vermək, sonra deadline-ı qaçırmaq.
- Həftələrlə update yoxdur, sonra böhran email-i.
- Hər şeyə "bəli" demək. Stakeholderlərə sonsuz capacity-n olduğunu öyrədirsən.
- Alternativsiz "yox" demək.
- Uzun update yazmaq. İcraçılar bir paraqraf oxuyur.
- Bütün stakeholderlərə eyni cür yanaşmaq. Müxtəlif auditoriyalar, müxtəlif mesajlar.
- Stakeholderlərdən öz komandana şikayət etmək. Moral vergisi.
- Qeyd saxlamamaq. Nə söz verdiyini unudacaqsan.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "How do you handle a difficult stakeholder?"
- "Tell me about a time you had to say no to a leader."
- "How do you communicate with non-technical stakeholders?"

Cavab planı:
1. **Çərçivə:** "I map my stakeholders and adjust the message per audience. Execs get one-paragraph summaries. PMs get weekly updates."
2. **Nümunə — yox demək:** "Our VP asked for a feature in 3 weeks. I analyzed it: 7 weeks minimum. I went back with three options at different scopes. He picked the 3-week scope-down. Trust built, and we shipped on time."
3. **Nümunə — pis xəbər:** "I once knew a deadline was slipping 2 weeks. I told leadership the moment I knew, not at the deadline. The reaction was fine because I gave them time to adjust communications. Late bad news is the real problem."
4. **Prinsip:** "The goal is no surprises. Stakeholders should never learn something about my project from someone else."

## Further reading
- "Managing Up" by Mary Abbajay
- "Crucial Conversations" by Patterson, Grenny, McMillan, Switzler
- "Writing for Busy Readers" by Todd Rogers and Jessica Lasky-Fink
- "The Pyramid Principle" by Barbara Minto
- "Staff Engineer" by Will Larson
