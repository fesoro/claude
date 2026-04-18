# Performance Reviews

## Niyə vacibdir? (Why it matters)
Performance review ilin ən böyük karyera hadisəsidir. Promotion-lar, maaş və sənin növbəti rolun buna bağlıdır. Zəif self-review yazan mühəndis etdiyi iddianı qədər — yəni çox az — qazanır. Kəskin, sübuta əsaslanan self-review yazan mühəndis isə çox vaxt iddiasını qazanır. Əlbəttə, əsl iş vacibdir, amma paketləmə əksər mühəndislərin qəbul etmək istədiyindən daha vacibdir.

Senior mühəndislər üçün review-lar təşkilatın əsl dəyərlərinin nə olduğunu öyrəndiyin yerdir. Promotion kriteriyalarını və kalibrasiya rubriklərini diqqətlə oxu — onlar sənə açıq nərdivan haqqında deyir.

## Yanaşma (Core approach)
1. **İl boyu sübut topla (brag doc).** Hər həftə etdiyin şeyləri yazdığın sadə sənəd. Review mövsümündə qəbz var.
2. **Fəaliyyət yox, təsir.** "I reviewed 120 PRs" sadalama. "I reduced PR review turnaround from 3 days to 1 day, which unblocked the team by X" de.
3. **Saat yox, scope + leverage.** Senior+ review-lar saat ilə deyil, scope (problem nə qədər böyük idi) və leverage (başqalarını nə qədər gücləndirdin) ilə qiymətləndirilir.
4. **Boşluqlar barədə dürüst ol.** Sıfır zəiflik olan self-review ya təkəbbürlü, ya da qeyri-dürüst oxunur. Yaxşılaşdırmaq üçün bir real sahəni ad.
5. **Promotion üçün: soruşmadan əvvəl iddianı yaz.** Promo iddiası biznes təsiri + scope + leverage + sübut tələb edir, bu sırada.

## Konkret skript və template (Scripts & templates)

### Brag doc şablon (il boyu açıq saxla)
```
# Brag doc — [Year]

## Q1
### Projects
- [Project name] — [your role] — [impact/outcome with numbers]
### Leverage (what you did to lift others)
- Mentored X who was promoted
- Wrote the RFC template used by 3 teams
### Recognition
- [Slack quotes, PR comments, manager comments]

## Q2, Q3, Q4...
```

### Self-review strukturu
```
## Summary (3 sentences)
What was my year about. What I'm proud of. What I want to grow in.

## Impact
- [Project 1]: [outcome with numbers]. My role: [what I specifically did].
- [Project 2]: ...

## Leverage (how I helped the team/org)
- Mentoring, hiring, cross-team influence, docs, talks.

## Growth
What I learned, what I leveled up in.

## Areas for improvement
One or two real things, with a concrete plan.

## Goals for next period
Specific, measurable.
```

### Təsir ifadələri yazmaq (bu pattern-ı istifadə et)
- Zəif: "Worked on the payments refactor."
- Orta: "Led the payments refactor, reducing bug rate."
- Güclü: "Led the payments refactor. Reduced payment failures by 40% (from 800/week to 480/week). Enabled the subscription feature which generated $X in Q3."

### Promotion iddiası şablonu
```
# Promotion case: [Name] to [Level]

## Current level expectations (from ladder)
- [Criterion 1]: evidence — [link]
- [Criterion 2]: evidence — [link]
- ...

## Next level expectations (from ladder)
- [Criterion 1]: evidence from last 6 months — [link]
- [Criterion 2]: evidence — [link]
- ...

## Business impact
- [Project 1]: $X revenue / Y hours saved / Z bugs prevented
- [Project 2]: ...

## Scope
- Worked across [N] teams
- Influenced [decision X] at org level

## Leverage
- Mentored [N] engineers, [N] promoted
- Wrote [doc/RFC] used by [audience]
```

### Peer review şablonu (başqası üçün yazmaq)
```
## Strengths
- [Specific behavior + impact]. Example: [concrete example].
- [Specific behavior + impact]. Example: [concrete example].

## Areas to grow
- [Specific behavior]. Suggested focus: [concrete].
- Note: I'm flagging this because I want X to reach [level].

## Overall
[1-2 sentence summary]
```

### Menecerindən kalibrasiya dəstəyi istəmək
> "I'd like to submit a promotion case this cycle. Can you review my draft before calibration? I want to know if the scope and impact are at the right level, and what evidence you'd add or remove."

## Safe phrases for B1 English speakers
- "My biggest impact this period was..." — self-review açmaq
- "The outcome was..." — işi nəticə ilə bağlamaq
- "I led / I owned / I contributed to..." — sahiblik səviyyələri
- "I enabled the team to..." — leverage çərçivəsi
- "I learned that..." — fikirləşmə
- "Looking forward, I want to grow in..." — gələcək çərçivəsi
- "I want to flag an area I'm working on..." — dürüst zəiflik
- "Evidence: [link]." — iddianı sübutla dəstəkləmək
- "Compared to last period, I improved in..." — trayektoriya göstərmək
- "The most valuable thing I did was..." — vurğulamaq
- "Where I fell short: ..." — dürüstlük
- "My plan to improve: ..." — hərəkətə yönəlik
- "I'd appreciate feedback on this draft." — kalibrasiya istəmək
- "I'd like to discuss promotion this cycle." — birbaşa tələb
- "Can you tell me what's missing for next level?" — böyümə sualı

## Common mistakes / anti-patterns
- Review mövsümünü gözləyib fikirləşmək. Brag doc-u həftəlik yaz.
- Təsir yox, fəaliyyətləri sadalamaq. Neçə PR olduğuna heç kimə maraq deyil.
- Rəqəm yoxdur. "Improved performance" vs "reduced p95 from 800ms to 200ms".
- Komanda işini tək iş kimi iddia etmək. Kalibratorlar bunu tez görür.
- Heç bir zəiflik sadalamamaq. Təkəbbürlü oxunur.
- "I work too hard" kimi zəiflik. Qeyri-dürüst və ya junior oxunur.
- İddianı yazmadan promotion istəmək.
- Nərdivanı oxumamaq. Açıq kriteriyaları bilməlisən.
- Peer review-ları atlamaq və ya ümumi yazmaq. Həmkarlar xatırlayır.
- Səhvləri gizlətmək. Sahiblə + öyrəndiyini de.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "What was your biggest impact in the last year?"
- "Tell me about a project you're proud of."
- "Have you pushed for a promotion? How did you build the case?"

Cavab planı:
1. **Əvvəl təsir:** "The project I'm most proud of was [X]. It reduced [metric] by [number] and enabled [business outcome]."
2. **Scope:** "I worked across 3 teams — engineering, product, and data."
3. **Leverage:** "I wrote the design doc that became our team's template, and I mentored the junior who later owned the v2."
4. **Böyümə:** "What I learned: [one specific lesson]."
5. **Promo sualı üçün:** "I wrote the case before asking. I mapped each ladder criterion to evidence. My manager added calibration context, and it passed in the first cycle."

## Further reading
- "The Staff Engineer's Path" by Tanya Reilly
- "Staff Engineer" by Will Larson (promotion case sections)
- "How to write a promotion packet" — various engineering blogs (Gergely Orosz, Will Larson)
- "Measure What Matters" by John Doerr (OKRs, impact framing)
- "The Effective Engineer" by Edmond Lau
