# Technical Writing (Senior)

## Niyə vacibdir? (Why it matters)
Yazı senior mühəndislər üçün scale-etmə alətidir. Sən hər iclasda, hər kanalda, hər komandada ola bilməzsən — amma sənin yazın ola bilər. Yaxşı ADR 5 il ərzində 50 mühəndis tərəfindən oxunur. Yaxşı yazılmış post-mortem eyni outage-ın üç dəfə təkrarının qarşısını alır. Yaxşı yazan mühəndis yalnız yaxşı danışan mühəndisdən daha çox təsir göndərir.

B1 ingilis dili danışanlar üçün texniki yazı əslində ünsiyyətin ən təhlükəsiz yeridir. Vaxtın var. Yenidən işləyə bilərsən. Qısa cümlələr uzunlardan yaxşıdır. Bullet point-lər paraqraflardan yaxşıdır. Diaqramlar isə hər ikisindən yaxşıdır.

## Yanaşma (Core approach)
1. **Ehtiyaca uyğun formatı bil.**
   - ADR: bir kiçik qərar, 1 səhifə
   - RFC: tam design, 3-10 səhifə
   - Post-mortem: incident-dən sonra, blameless
   - Design doc: layihə üçün detallı texniki plan
2. **Əvvəl auditoriya.** Exec bir cümləli cavab istəyir. Yeni mühəndis tam əsaslandırma istəyir. Fərqli yaz.
3. **Yuxarıda TL;DR.** Hər sənəddə. Məşğul oxucular yalnız bunu oxuyur.
4. **Qısa cümlələr. Bullet point-lər. Diaqramlar.** Xüsusilə B1 ingiliscə üçün — bunlar daha az risk ilə məna daşıyır.
5. **Uğursuzluq barədə olanda blameless.** Post-mortem-lər insanlar barədə deyil, proses barədədir.

## Konkret skript və template (Scripts & templates)

### ADR (Architecture Decision Record) — 1 səhifə
```
# ADR-[number]: [short title]

## Status
Proposed / Accepted / Deprecated / Superseded by ADR-XXX

## Context
Why are we facing this decision? What's the background? (1-3 paragraphs, no more)

## Decision
We will do [X]. (One paragraph, definitive)

## Consequences
- Good: [what gets better]
- Bad: [what gets worse]
- Neutral: [other effects]

## Alternatives considered
- Option B: rejected because [reason]
- Option C: rejected because [reason]
```

### RFC şablonu — design-review.md-də istinad edilib
Tam RFC şablonu üçün design-review.md-ə bax.

### Post-mortem (blameless)
```
# Post-mortem: [Incident title]

## Summary
[1 paragraph: what happened, impact, duration]

## Impact
- Affected users: [N]
- Duration: [time]
- Revenue / reputation impact: [if known]

## Timeline (in UTC)
- 10:03 — Deploy of version X to production
- 10:07 — Error rate climbs to 20%
- 10:12 — Alert fires
- 10:14 — On-call acks alert
- 10:22 — Rollback started
- 10:28 — Error rate back to normal
- 11:00 — All-clear

## Root cause
[What actually broke. Technical detail.]

## Contributing factors
- [Not enough test coverage on the new code path]
- [Deploy happened on Friday afternoon]
- [Alert threshold was 5 minutes — should be 2]

## What went well
- On-call response time
- Rollback was clean

## Action items
- [ ] [Owner] — Add test coverage for path X — due [date]
- [ ] [Owner] — Change alert threshold to 2 min — due [date]
- [ ] [Owner] — Document rollback playbook — due [date]

## Blameless statement
This document focuses on systems and process, not individuals. Engineers made reasonable decisions given what they knew at the time.
```

### Design doc (detallı layihə planı — RFC-dən uzun)
```
# Design doc: [Project name]

## Executive summary (3 paragraphs max)
For busy readers. Include: what, why, timeline, risks.

## Background and motivation
Longer context.

## Requirements
- Functional
- Non-functional (performance, security, cost)

## Proposed architecture
Include a diagram.

## Detailed design
- API contracts
- Data model changes
- Migration plan
- Error handling
- Observability (metrics, logs, alerts)

## Rollout
Phases and success criteria.

## Risks and mitigation

## Open questions

## Appendix
```

### İcraçı xülasə (hər uzun sənədin yuxarısı)
Qayda: **birinci cümlə = cavab.**

Pis: "We've been looking into the database performance issue over the last two weeks and considered many options including..."
Yaxşı: "We recommend migrating to Postgres 16 over the next quarter. It solves the index bloat issue and cuts p95 latency by ~40%."

### Texniki yazı üçün B1 ingiliscə məsləhətləri
- Qısa cümlələr. 20 sözdən az.
- Cümlədə bir ideya.
- Aktiv səs: "We decided" yox, "It was decided".
- Bullet point-lər həmişə təhlükəsizdir.
- Diaqramlar dil yükünü azaldır.
- Ardıcıl lüğət istifadə et. Eyni konsept üçün 3 söz istifadə etmə.
- "Mükəmməl" ingiliscə barədə narahat olma. Aydınlıq stildən üstündür.

## Safe phrases for B1 English speakers
- "TL;DR: ..." — açılış xülasəsi
- "Context: ..." — arxa plan vermək
- "Proposal: ..." — istiqaməti bəyan etmək
- "Alternatives considered: ..." — düşündüyünü göstərmək
- "Risks: ..." — qeyri-müəyyənliyi adlandırmaq
- "Action items: ..." — növbədə nə olacaq
- "In short: ..." — xülasə
- "To be clear: ..." — vurğulamaq
- "This doc assumes you know..." — auditoriyanı çərçivələmək
- "Out of scope: ..." — məhdudlaşdırmaq
- "Open question for [person]: ..." — yönləndirmə
- "Decision: ..." — nəticəni bəyan etmək
- "Rationale: ..." — əsas göstərmək
- "Next step: ..." — irəliləmək
- "Feedback welcome by [date]." — review istəmək

## Common mistakes / anti-patterns
- TL;DR yoxdur. Məşğul oxucular geri dönür.
- Qərarı 7-ci paraqrafda basdırmaq.
- Hər yerdə passiv səs. "It was decided" — kim tərəfindən?
- İnsanı günahlandıran post-mortem. Blameless mədəniyyəti öldürür.
- 5 səhifəlik ADR. Məqsədə ziddir.
- Sistem dizaynı üçün diaqram yoxdur.
- Ardıcıl olmayan terminologiya (bir yerdə "order", başqa yerdə "purchase").
- Tarix, sahib, status yoxdur.
- Exec-lərin də oxuduğu halda yalnız mühəndislər üçün yazmaq.
- Sənədi bir dəfə yazılan kimi qəbul etmək. Şeylər dəyişəndə yenilə.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "Walk me through a post-mortem you wrote."
- "How do you document architecture decisions?"
- "What makes a good design doc?"

Cavab planı:
1. **Formatlar:** "I use ADRs for small decisions — one page. RFCs for designs. Post-mortems for incidents."
2. **Prinsip:** "TL;DR at the top. Audience first. Short sentences."
3. **Nümunə — post-mortem:** "We had a Redis outage. I wrote the post-mortem focusing on contributing factors — not just 'the deploy broke' but also 'we didn't have a pre-deploy canary' and 'alerts were too slow'. Two action items prevented a repeat."
4. **Blameless:** "I explicitly open post-mortems with the blameless statement. The goal is learning, not punishment."

## Further reading
- "Docs for Developers" by Jared Bhatti et al.
- "Documenting Software Architectures" by Paul Clements et al.
- "The Pyramid Principle" by Barbara Minto
- "Writing for Busy Readers" by Todd Rogers and Jessica Lasky-Fink
- Google's SRE book — post-mortem chapter (free online)
- "Style: Toward Clarity and Grace" by Joseph M. Williams
