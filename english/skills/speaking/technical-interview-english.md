# Technical Interview English (Senior Backend)

English for backend technical interviews — system design, architecture questions, technical deep dives.

Not covered here: general job-interview phrases → see `job-interview.md`. Live code narration → see `live-coding-narration.md`.

---

## 1. System Design Soruları — Strukturlu Cavab

"Design a URL shortener / payment system / notification service" kimi suallar üçün.

### Açılış — Tələbləri Aydınlaşdırmaq

```
Before I dive in, let me clarify the requirements.
```
```
A few quick questions before I start — is this a read-heavy or write-heavy system?
```
```
Are we designing for a global user base, or one region?
```
```
What's the expected scale — roughly how many users / requests per second?
```
```
Should I focus on the core flow first, or cover the full system?
```

**Niyə vacibdir:** Interviewer görmək istəyir ki, başlamadan öncə düşünürsən.

---

### Scope Müəyyənləşdirmək

```
Let me start by defining the scope.
```
```
I'll focus on the core use case first: [X]. We can expand to edge cases after.
```
```
For now, I'll assume [X] — we can revisit that assumption if needed.
```
```
Out of scope for this design: [Y]. Happy to cover it if you'd like.
```

---

### Estimation

```
At a rough estimate —
```
```
Back of the envelope: if we have 10 million users, and each sends one request per day,
that's roughly 115 requests per second.
```
```
Storage-wise, if each record is about 1KB and we store 100 million records,
that's about 100GB — manageable on a single DB to start.
```
```
I'll assume [X] for now to simplify — does that seem reasonable?
```

---

### Komponenti Tanıtmaq

```
I'd break this into three main components:
```
```
The system has two main parts: [X] and [Y].
```
```
Let me walk through the data flow from the user's perspective.
```
```
The critical path here is: [step 1] → [step 2] → [step 3].
```

---

### Deep Dive

```
Let me zoom in on the [X] component — this is where it gets interesting.
```
```
The tricky part here is [X]. Here's how I'd handle it:
```
```
For the database layer, I'd go with [X] because [reason].
```
```
At this scale, we'd need to think about [X].
```

---

### Bağlamaq

```
Let me summarize what we've covered:
```
```
To recap: [component 1], [component 2], [component 3].
```
```
The main trade-offs in this design are: [X] vs [Y].
```
```
If I were building this in phases, I'd start with [X] and add [Y] when we hit [scale].
```

---

## 2. "Niyə?" Sualları (Defending Decisions)

Interviewer sənin seçimini sorgulayır. Bu normal bir şeydir — cavabın olması lazımdır.

### Qərarı İzah Etmək

```
I chose [X] because [specific reason].
```
```
The main reason I went with [X] over [Y] is [reason].
```
```
[X] made sense here because [context] — in a different scenario, I'd reconsider.
```

---

### Alternativ Tanımaq

```
I considered [Y] as well. The reason I ruled it out is [reason].
```
```
[Y] would also work — the difference is [trade-off].
```
```
In hindsight, I might consider [Y] if the requirements changed to [condition].
```

---

### Şəraitə Görə Cavab

```
It depends on the use case — for [scenario A], I'd use [X]; for [scenario B], [Y] makes more sense.
```
```
At this scale, [X] is fine. Once we hit [threshold], I'd look at [Y].
```
```
This was the pragmatic choice — there's a more elegant solution, but it adds complexity
that isn't worth it at this stage.
```

---

### Nümunə: DB Seçimi

Sual: "Why did you choose PostgreSQL over MongoDB here?"

```
I went with PostgreSQL because the data has a clear relational structure — orders, users,
and line items — and we need transactional guarantees across multiple tables. MongoDB
would work too, but we'd lose ACID compliance without extra work. If the schema were
highly variable or document-centric, I'd reconsider.
```

---

## 3. Bilmədiyini Demək (Saying You Don't Know — Professionally)

Bu bölmə çox vacibdir. "I don't know" demək zəiflik deyil — amma necə deməyin önəmi var.

### Pis Cavablar

❌ Tamamilə susmaq
❌ "I don't know." (qısa, heç bir davam yoxdur)
❌ Bilmirəm kimi yalan danışmaq — mütəxəssis bu hiss edir

---

### Yaxşı Cavablar

```
I haven't worked with [X] directly, but I understand the concept.
My approach would be to [apply known principles].
```

```
That's outside my current expertise, but I'd approach it by [reasoning from first principles].
```

```
I'm not deeply familiar with [X], but based on what I know about [related area],
I'd expect it to work like [explanation].
```

```
I'd have to look that up to give you a precise answer, but my intuition is [X].
Is that in the right direction?
```

---

### Nümunə: Raft Algorithm haqqında sual

Sual: "Can you explain how Raft consensus works?"

```
I know Raft at a conceptual level — it's a consensus algorithm for distributed systems,
similar to Paxos but designed to be more understandable. It uses leader election and
log replication. I haven't implemented it directly, but my understanding is that nodes
elect a leader, and all writes go through the leader, which then replicates to followers.
Is there a specific part you'd like me to go deeper on?
```

---

### Nümunə: Naməlum Tool

Sual: "Have you used Apache Flink?"

```
I haven't used Flink specifically, but I've worked with stream processing concepts —
I've used Kafka Streams and have experience with event-driven architectures. I
understand that Flink handles stateful stream processing with exactly-once guarantees.
I'd need some ramp-up time to work with it directly, but the underlying concepts
aren't new to me.
```

---

## 4. Vaxtı İdarə Etmək (Managing Time in Interviews)

### Düşünmək Üçün Vaxt İstəmək

```
Give me a moment to think through this.
```
```
Let me think out loud here —
```
```
That's a good question — one second.
```
```
I want to make sure I give you a good answer — can I have a moment?
```

---

### Sualı Anladığını Yoxlamaq

```
Just to confirm I understand the question: you're asking about [restate]?
```
```
Let me make sure I've got this right — you want me to design [X], correct?
```
```
Is the focus on [A] or [B]?
```

---

### Yönlənmə (Redirecting)

```
I'd like to come back to that point — first let me finish [current topic].
```
```
Great question — let me park that and finish this part, then I'll address it.
```
```
I'll get to that — let me set the foundation first.
```

---

### Vaxt Azlığını Hiss Etdikdə

```
I realize we're running short on time — should I focus on [X] or [Y]?
```
```
I can go deeper on this, or move on — your call.
```
```
I've covered the high level — happy to drill into any specific part.
```

---

### Əlavə Sual Vermək

```
Can I ask — is there a particular constraint I should optimize for?
```
```
Is the priority latency, consistency, or cost here?
```
```
Are there any specific requirements I should be designing around?
```

---

## 5. Rəqəmləri və Miqyası İfadə Etmək (Expressing Scale and Numbers)

Backend interviews often involve estimates. Dəqiq rəqəm yox, reasoning lazımdır.

### Estimation Phrases

```
On the order of [X] — so roughly [number].
```
```
In the ballpark of [X].
```
```
Roughly [number], give or take.
```
```
Let's say approximately [X] — the exact number matters less than the order of magnitude.
```

---

### Nümunə Hesablamalar

```
About 10 million requests per day — that's roughly 115 requests per second
(10M / 86,400 seconds ≈ 115 rps).
```
```
If the average user has 500 followers and we have 50 million users,
that's 25 billion fan-out operations per day — which immediately tells me
we need an async approach.
```
```
At 1KB per record and 100 million records, that's 100GB — fits on one machine today,
but we should plan for sharding at 10x growth.
```

---

### Scale Haqqında Danışmaq

```
At this scale, we'd need to consider [X].
```
```
This works fine up to [threshold] — beyond that, we'd hit [bottleneck].
```
```
The bottleneck here is [X] — at [scale], that becomes a problem.
```
```
We can start simple and scale out when we hit [metric].
```

---

### Order of Magnitude

```
We're talking millions, not billions — so [simpler approach] is fine.
```
```
At billions of records, [X] becomes impractical.
```
```
This is a low-traffic service — [simple solution] will handle it comfortably.
```

---

## 6. Trade-off Dili (Trade-off Language)

Bu, senior-level texniki müsahibənin əsasıdır. Hər seçimin qiyməti var.

### Əsas Trade-off Çərçivəsi

```
The benefit of [X] is [benefit], however, the downside is [downside].
```
```
This approach trades [X] for [Y].
```
```
We gain [benefit] but at the cost of [cost].
```

---

### Şərtlərə Görə Seçim

```
It depends on the use case:
- If [condition A], then [choice A] — because [reason].
- If [condition B], then [choice B] — because [reason].
```
```
If read performance is the priority, [X]. If write performance matters more, [Y].
```
```
In a startup context, I'd start with [X]. At enterprise scale, [Y] makes more sense.
```

---

### Consistency vs Availability

```
Here we're trading consistency for availability — this is acceptable because [reason].
```
```
For financial data, I'd always choose consistency over availability.
```
```
This is an eventually consistent model — the user might see stale data for [duration],
but that's acceptable for [this use case].
```

---

### Complexity vs Simplicity

```
The simpler solution has [limitation], but it's much easier to maintain and debug.
```
```
We could add [complex feature] for [benefit], but I'd only do that if we actually hit
that scale — premature optimization and all.
```
```
This adds operational complexity — you'd need [X] to manage it properly.
```

---

### Nümunə: Cache Haqqında Trade-off

```
Adding a Redis cache in front of the DB will dramatically reduce read latency —
we're talking sub-millisecond vs 5-10ms from the DB. The trade-off is cache
invalidation complexity and the risk of serving stale data. For a product catalog,
that's acceptable. For a bank balance, it's not.
```

---

## 7. Useful Phrases Table

| Vəziyyət | Phrase | Azərbaycanca |
|----------|--------|-------------|
| Sualı aydınlaşdırmaq | "Before I dive in, let me clarify the requirements." | "Başlamadan öncə tələbləri aydınlaşdırım." |
| Scope bildirmək | "I'll focus on the core use case first." | "Əvvəl əsas use case-ə fokuslanacağam." |
| Estimation | "Back of the envelope: roughly [X]." | "Təxmini hesabla: təqribən [X]." |
| Komponent | "I'd break this into three main parts:" | "Bunu üç əsas hissəyə bölərdim:" |
| Qərar izah etmək | "I chose [X] because [reason]." | "[X]-i seçdim, çünki [səbəb]." |
| Alternativ | "I considered [Y] but ruled it out because [reason]." | "[Y]-i nəzərdən keçirdim amma [səbəb]-dən rədd etdim." |
| Bilmirəm | "I haven't worked with [X] directly, but my approach would be..." | "[X] ilə birbaşa işləməmişəm, amma yanaşmam belə olardı..." |
| Düşünmək | "Give me a moment to think through this." | "Bir anlıq düşünüm." |
| Sualı yoxlamaq | "Just to confirm — you're asking about [X]?" | "Təsdiqləmək üçün — [X] haqqında soruşursunuz?" |
| Geri qayıtmaq | "Let me come back to that — first let me finish [X]." | "Buna qayıdacağam — əvvəl [X]-i bitirim." |
| Miqyas | "At this scale, we'd need to consider [X]." | "Bu miqyasda [X]-i nəzərə almaq lazımdır." |
| Trade-off | "The benefit of [X] is..., however, the downside is..." | "[X]-in üstünlüyü... lakin çatışmazlığı..." |
| Şərtli seçim | "It depends — if [A], then [X]; if [B], then [Y]." | "Şərtə görə — əgər [A], onda [X]; əgər [B], onda [Y]." |
| Xülasə | "Let me summarize what we've covered." | "Əhatə etdiklərimizi ümumiləşdirəm." |
| Vaxt azlığı | "Should I focus on [X] or move on to [Y]?" | "[X]-ə fokuslanım, yoxsa [Y]-ə keçim?" |
| Davam etmək | "Happy to go deeper on any part." | "İstənilən hissədə daha dərindən danışmağa hazıram." |
