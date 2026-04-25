# Conducting Technical Interviews (Lead)

## Niyə vacibdir? (Why it matters)
Senior+ mühəndislərin işinin böyük bir hissəsi texniki müsahibə keçirməkdir. Yaxşı müsahibəçi şirkətə 2-3 il işləyəcək mühəndis gətirir. Pis müsahibəçi ya güclü namizədi rədd edir, ya da zəif namizədi qəbul edir — hər ikisi şirkətə böyük zərər vurur. Bu bacarıq həm karyera üçün vacibdir (hiring bar-ı formalaşdırmaq senior influence-in bir hissəsidir), həm də mühəndislik mədəniyyətini doğrudan müəyyən edir.

Lead-track üçün: hiring decision-larda iştirak edən mühəndislər şirkətin texniki istiqamətini real olaraq formalaşdırır.

## Yanaşma (Core approach)
1. **İşi test et, bilik testini yox.** "Binary tree balanslanır?" sualı yox. "Caching layer-i niyə əlavə edərdiniz?" sualı. Real mühəndislik düşüncəsini tap.
2. **Namizədi rahat et.** Gərgin namizəd öz real bacarığını göstərə bilmir. Rahat namizəd isə güclü olduğunda çox aydın görünür.
3. **Cavabı deyil, prosesi qiymətləndir.** Namizəd necə düşünür? Soruşur mu? Sınayır mı? Nəticəyə necə çatır?
4. **Vahid signal yox, çoxlu signal topla.** Bir sualdan yanlış nəticəyə gəlmə. Bütün müsahibənin pattern-ını qiymətləndir.
5. **Structured rubric istifadə et.** Şəxsi intuisiyaya istinad etmə. Rubric olmadan bias qaçılmazdır.

## Konkret skript və template (Scripts & templates)

### Müsahibə strukturu (60 dəq)
```
0-5   min: Giriş, rahatlatmaq
5-10  min: Background — nə etdilər, necə düşünürlər
10-40 min: Texniki sual (1-2 sual, dərindən)
40-50 min: Behavioral sual (1-2 sual, STAR)
50-60 min: Namizədin sualları (vacibdir — skip etmə)
```

### Giriş / rahatlatmaq skripti
> "Hi [name], thanks for coming in. I'm [name], I'm a [role] on the [team] team. Today I'll walk you through a technical problem and chat about your experience. There are no trick questions — I want to understand how you think, not just what the answer is. Feel free to think out loud. Ready?"

### Texniki sual — system design (Senior+ namizəd üçün)
**Açılış (geniş başla, daralt):**
> "Let's say we're building a URL shortener — something like bit.ly. Walk me through how you'd design it."

**Follow-up suallar (kəşf etmək üçün):**
- "What's your estimated scale? How many redirects per second?"
- "How would you handle the same URL being submitted twice?"
- "Where would bottlenecks emerge as we scale?"
- "How would you handle the database running out of short codes?"
- "What would you monitor in production?"

**Deep-dive açmaq:**
> "You mentioned Redis for caching. Walk me through the cache invalidation strategy."

### Texniki sual — code review (praktik senaryolar üçün)
> "Here's a pull request. [Show code snippet with 2-3 issues.] Take a minute to read it and tell me what you'd comment on — and how you'd phrase the comment."

Axtardıqlarını yoxla:
- N+1 query, security issue, missing test, naming, edge case
- Komment tonu: konstruktiv mu, qeyri-müəyyənmi?

### Behavioral sual (STAR format)
- "Tell me about a time you disagreed with a technical decision. What happened?"
- "Describe a situation where you had to deliver bad news to your manager."
- "Tell me about a time you mentored someone. What was your approach?"
- "Describe a project that didn't go as planned. What did you learn?"

**Follow-up:**
> "What would you do differently now?"

### Rubric (scoring template)
```
## [Candidate name] — [Position] — [Date]

### Technical depth (1-4)
[ ] 1 — surface level only
[ ] 2 — understands basics, misses edge cases
[ ] 3 — solid, handles tradeoffs
[ ] 4 — deep, proactively raises complexity

### Problem-solving approach (1-4)
[ ] 1 — jumps to solution, doesn't clarify
[ ] 2 — some structure, loses thread
[ ] 3 — structured, recovers from mistakes
[ ] 4 — methodical, asks right questions, adapts

### Communication (1-4)
[ ] 1 — hard to follow
[ ] 2 — unclear in places
[ ] 3 — clear, concise
[ ] 4 — excellent, adapts to audience

### Behavioral signals (1-4)
[ ] 1 — vague, no concrete examples
[ ] 2 — some examples, weak impact
[ ] 3 — clear examples with context
[ ] 4 — strong ownership, learning, impact

### Overall recommendation
[ ] Strong hire
[ ] Hire
[ ] No hire
[ ] Strong no hire

### Key strengths (2-3 bullets)

### Key concerns (2-3 bullets)

### Evidence (specific moments from the interview)
```

### Zəif namizədi yönləndir (yox, cavabı vermə)
> "You're on the right track. Let me add some context: we're handling 10,000 requests per second. How does that change your thinking?"

> "What about if the database goes down — what does the user experience look like?"

### Güclü namizədi daha dərindən yoxla
> "That's a solid approach. Let's push a bit further — what happens if the cache layer is unavailable?"

> "How would you design the monitoring for this? What would you alert on?"

### Namizədin sualları bölməsi
> "Now your turn — what questions do you have for me about the team, the work, or the company? I'll be as honest as I can."

Bu bölmə vacibdir: yaxşı namizəd həmişə yaxşı suallar verir.

### Post-interview debriefing
Dərhal rubric-i doldurun. 1 saat sonra memory fade olur.

Debrief formatı:
> "I'll share my notes first, then let's hear from [other interviewer]. I want raw reactions before we influence each other."

Hire/no-hire-ı soruşmazdan əvvəl hər kəs öz məlumatını paylaşsın.

## Safe phrases for B1 English speakers (müsahibəçi üçün)
- "There's no trick here — I want to see your thinking." — namizədi rahatlatmaq
- "Think out loud — the process matters more than the answer." — istiqamət
- "Take a moment — no rush." — vaxt vermək
- "Let me add some context." — hint vermək
- "You mentioned X — can you go deeper?" — kəşf etmək
- "What trade-offs do you see?" — depth yoxlamaq
- "How would you test this?" — testability yoxlamaq
- "What would you monitor?" — ops awareness yoxlamaq
- "What would you do differently with more time?" — prioritization
- "I'll pass it to [next interviewer] now — thank you." — transition

## Common mistakes / anti-patterns
- Cavabı bilmirəmsə "pass" demək. Sən müsahibəçisən — sualı daha yaxşı yaz.
- Bütün vaxtı bir sualda keçirmək. Çoxlu signal toplamaq lazımdır.
- Namizədi susdurmaq. Ən güclü interviewlar dialog-dur, sınaq deyil.
- Rubric doldurmamaq. "Gut feel" bias-lıdır.
- Hint vermədən namizədi sıxışdırmaq. Sınaqdan keçmişlər görür ki, hint-lər nə qədər lazım oldu.
- Şəxsi hekayəyə çox vaxt vermək. Namizədin vaxtıdır, sənin yox.
- Behavioral sualı atlamaq. Texniki güclü amma işləmək çətin olan namizəd mövcuddur.
- Namizədin suallarını atlamaq. Bu da bir siqnaldır.
- Hər kəslə eyni müsahibə etmək. Seniora senior sual ver, juniora junior.
- Debrief-i gecikdirmək. 30 dəqiqə ərzində rubric-i doldur.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "How do you assess a candidate's technical depth?"
- "Tell me about a time you had to make a difficult hiring decision."
- "What's your approach to conducting interviews?"

Cavab planı:
1. **Struktur:** "I use a rubric and score before the debrief. I want raw signal, not social consensus."
2. **Sual fəlsəfəsi:** "I test thinking, not knowledge. 'Design a rate limiter' tells me more than 'name all HTTP status codes'."
3. **Çətin qərar nümunəsi:** "A candidate had great system design skills but gave very vague behavioral answers. We passed. Six months later I heard they were let go from another company for culture reasons. Pattern matching matters."
4. **Bias qarşı:** "I write feedback immediately after. Memory warps. And I never share my opinion before others speak in debrief."

## Further reading
- "The Google Resume" by Gayle Laakmann McDowell (interviewer perspective)
- "Cracking the Coding Interview" by Gayle Laakmann McDowell (for interviewers: see what candidates expect)
- "Who: The A Method for Hiring" by Geoff Smart and Randy Street
- "Work Rules!" by Laszlo Bock (Google's hiring research)
- "Structured interviewing" research — many HR/org psych papers on reducing bias
