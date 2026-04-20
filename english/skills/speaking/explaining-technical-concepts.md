# Explaining Technical Concepts — Texniki Mövzular İzahı

## Səviyyə
B1-B2 (interview + stakeholder meetings vacib!)

---

## Niyə Vacibdir?

Senior engineer bacarığı:
- Technical concepts izah et
- Non-tech people üçün (PMs, CEOs)
- Junior developer üçün mentorship
- Interview system design
- Blog post writing

**Yaxşı izah = senior engineer imza**

---

## Qızıl Qayda

**Know your audience.** Auditoriya ilə başla.

- **Tech peer** → dərin technical
- **Junior dev** → ortalama + examples
- **PM / Designer** → high-level + impact
- **CEO / non-tech** → business value + analogy

---

## 1. Structure — Simple Framework

### Problem → Solution → Example

1. **What problem?** (kontekst)
2. **What's the solution?** (konsept)
3. **How does it work?** (izah)
4. **Real example** (nümunə)
5. **Why it matters** (business impact)

---

## 2. The Feynman Technique

Noble prize-winning scientist Richard Feynman metoduna görə:

### Steps

1. **Pick a concept**
2. **Explain to a 10-year-old**
3. **Find gaps** (where you stumble)
4. **Go back** to source, re-learn
5. **Simplify further**

### Test

"If you can't explain it simply, you don't understand
it well enough." — Einstein

---

## 3. Analogies (ƏSAS!)

Mürəkkəb konsepti sadə bir şeyə bənzət.

### Common tech analogies

#### Database

- **Database** = kitabxana (knowing where to find books)
- **Index** = kitabxanada fihrist
- **Query** = kitabxanaçıya sual

#### Cache

- **Cache** = bitki yanında su şüşəsi
- **Cache hit** = suyu şüşədən al
- **Cache miss** = mətbəxə gedib al

#### Load Balancer

- **Load balancer** = restoranın hostessi
- **Directs traffic** = müştəriləri masalara böl

#### API

- **API** = restoran menyusu
- You order, chef cooks, food served

#### CDN

- **CDN** = yaxın filial mağazası
- Fast local pickup vs. central warehouse

---

## 4. Technical Depth Levels

### Level 1: Metaphor only (CEO)

"Caching is like keeping important files on your desk
instead of in the filing cabinet."

### Level 2: Metaphor + brief technical (PM)

"Caching stores frequently accessed data in fast memory
(like RAM) so we don't have to query the slower database."

### Level 3: Technical (Engineer peer)

"We use Redis as a write-through cache with a 5-minute TTL.
Read operations hit the cache first, falling back to
PostgreSQL on cache misses."

### Level 4: Deep (Staff engineer)

"We're implementing a multi-tier cache with L1 (in-memory)
and L2 (Redis). Invalidation is handled via pub/sub events
to ensure consistency across the fleet..."

---

## 5. Useful Phrases

### Starting

- "Let me explain..."
- "Think of it like..."
- "Imagine if..."
- "The way it works is..."

### Building up

- "First, ..."
- "Then, ..."
- "Finally, ..."
- "The key insight is..."

### Analogies

- "It's like..."
- "It's similar to..."
- "Think of it as..."
- "Imagine..."

### Clarifying

- "In other words, ..."
- "To put it simply, ..."
- "What this means is..."
- "The bottom line is..."

### Checking understanding

- "Does that make sense?"
- "Any questions so far?"
- "Following me?"
- "Should I clarify anything?"

---

## 6. Visual Aids

### Whiteboard

- Draw architecture
- Show data flow
- Before/after diagrams

### Real tools

- Diagrams (draw.io)
- Flowcharts
- Sequence diagrams

### ASCII in chat

```
Client → API → DB
          ↓
        Cache
```

---

## 7. Common Mistakes

### ✗ Too much jargon

"We utilize gRPC-based microservices with event-driven
orchestration via Kafka and CQRS patterns..."

### ✓ Layered explanation

"We split our big application into small services that
talk to each other via messages. When one changes, others
react."

### ✗ Too fast

Rapid explanation → listener lost.

### ✓ Pause + check

"...with caching. Any questions before I continue?"

### ✗ Assuming knowledge

"Obviously, when the monad is composed with the functor..."

### ✓ Define terms

"A monad is a pattern for handling sequential operations
with side effects."

---

## 8. Concept Examples

### Explaining "Load Balancer"

**To CEO:**
"Imagine a restaurant with one waiter. When busy, customers
wait. A **load balancer** is like hiring more waiters and
a host who directs each customer to an available one.
Our app has multiple servers, and the load balancer
distributes users so nobody waits."

**To Engineer:**
"We use nginx as a reverse proxy with round-robin
distribution. It routes HTTP requests to one of our 5
backend pods based on health checks. Sticky sessions
are disabled since our API is stateless."

### Explaining "Microservices"

**To PM:**
"Instead of one big app that does everything, we split
it into smaller apps that each do one thing well. If
the login service breaks, the rest keeps working."

**To Engineer:**
"We migrated from a monolith to domain-driven
microservices. Each service owns its data, communicates
via REST/gRPC, and deploys independently."

### Explaining "Race Condition"

**To non-tech:**
"Imagine two people trying to edit the same Google Doc
at the exact same millisecond. They both save, but only
one wins — the other's change is lost."

**To Engineer:**
"A race condition occurs when two threads access shared
state without synchronization. In this case, two writes
read the same counter value, increment it, and write back —
losing one increment."

---

## 9. Interview System Design

### Pattern

1. **Clarify requirements** (ask questions)
2. **Start high-level** (boxes + arrows)
3. **Go deeper** (component by component)
4. **Address trade-offs** (CAP, scalability)
5. **Metrics** (latency, throughput)

### Structured response

```
"Before I dive in, I have a few questions:
- What's the expected scale? [10K users? 10M?]
- What's the read/write ratio?
- Any specific latency requirements?

Given that, here's my high-level design..."
```

---

## 10. Teaching Juniors

### Scaffolding

Build knowledge layer by layer:

1. **Why** (motivation)
2. **What** (definition)
3. **How** (mechanism)
4. **When** (use cases)
5. **Example** (real code)
6. **Pitfalls** (what to avoid)

### Example (teaching promises)

```
**Why**: We need to handle async operations (network, disk)
without blocking.

**What**: A Promise represents a future value.

**How**: It has 3 states: pending → fulfilled or rejected.

**When**: Anything async — API calls, timeouts, file reads.

**Example**: [code]

**Pitfalls**: Not returning promises (forgetting to chain),
unhandled rejections.
```

---

## 11. Handling Questions

### Simple questions

- "Great question. The answer is..."
- "Exactly — and here's why..."

### Complex questions

- "That's a deep question. Let me think..."
- "Multiple layers here. Let me start with..."

### "I don't know"

- "Honest answer: I don't know. But I'd find out by..."
- "I haven't looked at that, but my hypothesis is..."

**Never fake it** — interviewers notice.

---

## 12. Non-Tech Stakeholder Context

### Translate tech → business

- "500 errors" → "users can't log in"
- "Latency" → "site is slow"
- "Technical debt" → "slows future development"
- "Scaling" → "support more users without issues"
- "Downtime" → "customer trust + revenue impact"

### Focus on outcomes

Instead of implementation details, talk about:
- **User impact**
- **Business metrics**
- **Revenue / cost**
- **Risk**

---

## 13. Pacing

### Too fast

- Watch for glazed eyes
- "Am I going too fast?"

### Too slow

- Check engagement
- "Does this level of detail help?"

### Balance

- Pause every 2-3 minutes
- Invite questions
- Adapt to feedback

---

## 14. Socratic Method

Instead of telling, **ask**:

### Example teaching

You: "Why do you think we use caching?"
Junior: "To make things faster?"
You: "Right! What's slow about going to the database?"
Junior: "Network latency? Disk?"
You: "Exactly. Now, where do we store the cache?"

---

## 15. Story-Driven Explanation

### Humans love stories

Wrap explanation in narrative:

"Last month, our users started complaining about slow
dashboards. I ran profiling and found **500 database
queries** per page load. The root cause was N+1.
Let me show you what N+1 is..."

---

## Explaining to Different Roles

### CEO

- Focus: revenue, cost, users, risk
- Avoid: implementation detail
- Example: "This saves us $50K/year and improves
  user satisfaction."

### PM

- Focus: user impact, timeline, trade-offs
- Example: "Feature X takes 2 weeks because we need
  to handle edge cases A, B, C."

### Designer

- Focus: technical constraints, possibilities
- Example: "We can't do real-time updates without
  WebSockets, which adds complexity."

### Engineer

- Focus: architecture, trade-offs, implementation
- Full technical depth

---

## Key Phrases Cheat Sheet

### Analogy intros

- "Think of it like..."
- "It's similar to how..."
- "Imagine..."

### Simplification

- "Basically..."
- "At its core..."
- "In a nutshell..."

### Deepening

- "But there's more..."
- "Here's where it gets interesting..."
- "Going a level deeper..."

### Clarifying

- "To put it another way..."
- "The key point is..."
- "What this really means is..."

### Check understanding

- "Does that help?"
- "Any questions?"
- "Want me to dig deeper?"

---

## Azərbaycanlı Səhvləri

- ✗ Too much jargon
- ✓ **Explain** jargon

- ✗ No analogy
- ✓ **Use analogies** for complex topics

- ✗ Assume listener knows
- ✓ **Check** knowledge first

---

## Xatırlatma

**Good Explainer:**
1. ✓ **Know your audience**
2. ✓ **Use analogies**
3. ✓ **Layer complexity** (simple → deep)
4. ✓ **Check understanding** frequently
5. ✓ **Visual aids** when possible
6. ✓ **Real examples**
7. ✓ **Admit** when you don't know

**Qızıl qaydalar:**
- Simple > complex explanation
- Story > lecture
- Analogy > abstract definition
- "Does that make sense?" = your best tool

**Interview:** "I explained [concept] to [non-tech person] by [analogy]..."

→ Related: [system-design-discussion.md](system-design-discussion.md), [presentation-english-deep.md](presentation-english-deep.md), [technical-discussion-phrases.md](technical-discussion-phrases.md)
