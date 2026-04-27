# Think / Consider / Believe / Assume — Düşünmək Fellər

## Səviyyə
B1-B2

---

## Əsas Cədvəl

| Söz | Nədir? | Əminlik? |
|-----|--------|---------|
| **think** | ümumi fikir | aşağı — rəy bildirmək |
| **consider** | diqqətlə düşün | orta — analiz |
| **believe** | inandığın həqiqət | yüksək — conviction |
| **assume** | sübut olmadan qəbul et | xəyali — təhlükəli! |

> **Qısa qayda:**
> - **think** = fikirimdə (casual rəy)
> - **consider** = diqqətlə düşün (analiz)
> - **believe** = inanıram (güclü, dəlilə əsaslı)
> - **assume** = sübut olmadan güman et (tez-tez yanlış olur!)

---

## 1. Think — Ümumi Rəy (Casual)

Ən çox işlənən, ən az öhdəlik. Sadə fikir bildirmək.

### Nümunələr

- I **think** the issue is in the auth layer.
- What do you **think**?
- I **think** we should cache this.
- I **thought** it was a simple fix, but it wasn't.
- I don't **think** this approach scales.

### Kontekst

- Code review-da: "I **think** we could simplify this." ✓
- Müzakirədə: "What do you **think** about using Redis?" ✓
- Rəy bildirirsən, təsdiqlənməmiş

### Struktur

- **think** + (that) + clause
- **think about** + N / V-ing (düşünmək, araşdırmaq)
- **think of** + N (yadına düşmək, baxış)

- I **think that** we need more tests.
- **Think about** the trade-offs.
- I can't **think of** a better solution.

---

## 2. Consider — Diqqətlə Analiz Et

"Düşünmək" — amma xüsusi: müxtəlif faktorları nəzərə alaraq.

### Nümunələr

- **Consider** using Redis for caching.
- We need to **consider** all the trade-offs.
- Have you **considered** the edge cases?
- **Consider** switching to a different approach.
- We should **consider** the impact on performance.

### Əsas fərq: Think vs Consider

- **think** = sadə rəy (casual)
- **consider** = çox tərəfli analiz (deliberate)

- "I **think** we should use Redis." (rəy)
- "**Consider** Redis — it handles this use case well." (analiz)

### Kod review-da — professional səslənir

- "**Consider** extracting this into a service." ✓
- "**Consider** adding a retry mechanism." ✓
- "**Consider** the memory overhead here." ✓

### Struktur

- **consider** + N
- **consider** + V-ing (important!)
- **consider** + (wh-word) + clause

- **Consider** caching. ✓
- **Consider using** a queue. ✓
- **Consider whether** this scales. ✓

⚠ "Consider to use" — YANLIŞ! Consider + V-ing, "to" yox.

---

## 3. Believe — Dərin İnam (Conviction)

Think-dən güclü. Dəlil, təcrübə və ya güclü fikir əsasında.

### Nümunələr

- I **believe** the bottleneck is the database.
- We **believe** this is the right architecture.
- I **believe** our users expect real-time updates.
- I **believe** in clean code.
- The logs **suggest** what I **believe** to be true.

### Əsas fərq: Think vs Believe

- **think** = "mənim rəyimcə" (casual)
- **believe** = "daha çox əminəm" (conviction)

- "I **think** this is slow." (casual müşahidə)
- "I **believe** the N+1 query is causing this." (araşdırmaya əsaslı)

### Professional kontekst

- **Believe** in prensiplər: "I **believe** in test-first development." ✓
- Üst-qat kommunikasiyada: "We **believe** this approach will reduce costs." ✓
- İnterviewda: "I **believe** microservices add unnecessary complexity here." ✓

### Struktur

- **believe** + (that) + clause
- **believe in** + N (prinsip, dəyər)

- I **believe that** caching is necessary.
- I **believe in** keeping systems simple.

---

## 4. Assume — Sübut Olmadan Qəbul Et (Xəyali!)

Bir şeyi doğru kimi qəbul etmək — lakin sübut olmadan. Tez-tez problemə səbəb olur!

### Nümunələr

- I **assumed** the migration had run — it hadn't.
- Don't **assume** — verify.
- We **assumed** the API was backward-compatible.
- I **assumed** you knew about the deadline.
- Never **assume** input is sanitized.

### ⚠ ƏSAS MƏQAM

Assume istifadə edəndə çox vaxt nəticə pisdir:
- "I **assumed** it was working..." (bug tapıldı)
- "We **assumed** the server was up..." (downtime oldu)

**Famous quote:** *"Never assume. Always verify."*

### Tech kontekstdə "assume" — fəqət neutral istifadəsi

Bəzən "fərz edirik ki" mənasında neytral işlənir:

- "**Assuming** the request is valid, the handler returns 200." (doc context)
- "**Assume** we have 1M users." (hypothetical)

### Believe vs Assume

- **believe** = dəlilə əsaslı (yüksək əminlik)
- **assume** = dəlilsiz (güman, risk)

- "I **believe** Redis is the right choice." (düşünüb əminəm)
- "I **assumed** Redis was already configured." (yoxlamadım — xəta!)

### Struktur

- **assume** + (that) + clause
- **assume** + N

- I **assumed that** caching was enabled.
- Never **assume** correctness without tests.

---

## Müqayisə Cədvəli

| | Think | Consider | Believe | Assume |
|-|-------|----------|---------|--------|
| Əminlik | aşağı | orta | yüksək | yanlış ola bilər |
| Dəlil lazım? | yox | bəlkə | bəli | yox (problem!) |
| Rəsmilik | casual | professional | professional | neytral |
| Code review | bəli | çox | bəli | xeyr |
| Risk | aşağı | aşağı | aşağı | yüksək |

---

## Tez-tez Yanlış İşlənənlər

| ❌ Yanlış | ✅ Düzgün |
|-----------|-----------|
| Consider to use Redis. | **Consider using** Redis. |
| I assumed it's the right approach after research. | I **believe** it's the right approach after research. |
| I think in test-driven development. | I **believe in** test-driven development. |
| I'm assuming we should cache this. | I **think** / **believe** we should cache this. |
| Consider that the server might be slow. | **Consider** that the server might be slow. ✓ (bu düzgündür) |
| Don't think — just do it. | Don't **assume** — verify. |
| I believe we should try it. (casual) | I **think** we should try it. (casual üçün think daha yaxşı) |
| I considered he was wrong. | I **thought** he was wrong. |

---

## Test

Hansı söz uyğundur?

1. I ______ the issue is in the database layer — let me check. (casual fikir)
2. ______ adding a circuit breaker to handle downstream failures. (analiz)
3. I ______ that clean code is non-negotiable in a team. (güclü prinsip)
4. I ______ the cache was warm — it wasn't. (sübut olmadan qəbul etdi)
5. What do you ______ about the new architecture? (rəy soruşur)
6. We should ______ the trade-offs before switching to microservices. (analiz)
7. Never ______ the input is sanitized. (təhlükəli qəbul)
8. I ______ this approach is better — the benchmarks prove it. (əminlik)

**Cavablar:** 1. think, 2. Consider, 3. believe, 4. assumed, 5. think, 6. consider, 7. assume, 8. believe

---

## Cümləni tamamlayın

1. ______ switching to a message queue — it will decouple the services.
2. I ______ that JWT is the right choice after reading the RFC.
3. I ______ the migration was complete, but no one had run it.
4. I ______ the API is broken — let me investigate.
5. ______ the memory usage before deploying to production.
6. Don't ______ the third-party service is always available — handle failures.

**Cavablar:** 1. Consider, 2. believe, 3. assumed, 4. think, 5. Consider, 6. assume

---

## Code Review Dili

Bu üç ifadə code review-da çox işlənir:

- "I **think** we could simplify this." (yumşaq rəy)
- "**Consider** extracting this into a repository class." (tövsiyə)
- "I **believe** this will cause a race condition." (əminlik)

`assume` code review-da çox nadir — yalnız hypothetical context-də.

---

## Azərbaycanlı Səhvləri

- ✗ I consider he is wrong. (consider = analiz, rəy üçün "think")
- ✓ I **think** he is wrong.

- ✗ Consider to add tests. ("to" yox!)
- ✓ **Consider adding** tests.

- ✗ I assumed after checking it's the right approach. (assume = sübut olmadan; sübut sonra believe)
- ✓ I **believe** it's the right approach after checking.

---

## Xatırlatma

| Söz | Bir sözdə |
|-----|-----------|
| **think** | casual rəy |
| **consider** | analiz et |
| **believe** | güclü inam |
| **assume** | sübut olmadan (risk!) |

**Altın qayda:** code-da "assume" etmə — **verify** et.

→ Related: [know-vs-understand-vs-realize.md](know-vs-understand-vs-realize.md), [suggest-recommend-advise-propose.md](suggest-recommend-advise-propose.md)
