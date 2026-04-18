# Technical Reading Practice — Texniki Oxu Məşqləri

## Bu Fayl Haqqında

Software engineer olaraq hər gün **texniki mətnlər oxuyursan**: RFC-lər, API documentation, blog postları, Stack Overflow cavabları, pull request description-ları, incident report-lar. Texniki mətnlər adi mətn deyil — öz strukturu, jargonu, və oxu strategiyası var.

Bu faylda **5 fərqli texniki mətn növü** + anlama sualları + açar söz təhlili var.

**Məqsəd:** Müsahibəyə hazırlıq, iş mühitində daha tez oxu, texniki sənədləri başa düşmə.

---

## Necə İstifadə Etmək

### 1. İlk oxuma (5-7 dəq):
- **Skim** et: başlıqlar, birinci cümlələr
- Əsas fikiri (TL;DR) tap

### 2. İkinci oxuma (10-15 dəq):
- Tam oxu
- Bilmədiyin texniki sözləri işarələ
- Suallara cavab ver

### 3. Üçüncü oxuma (5 dəq):
- Bilmədiyin sözləri lüğətdə tap
- Yenidən oxu, indi rahat başa düşürsən?

### 4. Təhlil:
- Açar ifadələri yaz
- Özün analogiya qur

---

## Text 1: API Documentation Nümunəsi

### Başlıq
**POST /api/v2/orders — Create Order**

### Mətn

> Creates a new order on behalf of an authenticated user. The request must include at least one line item. Each line item specifies a product and a quantity. The order is created in `pending` status and remains in this state until payment is confirmed via the `/api/v2/payments/confirm` endpoint.
>
> **Idempotency:** This endpoint is idempotent. Repeated requests with the same `Idempotency-Key` header return the same response without creating duplicate orders. Idempotency keys are stored for 24 hours.
>
> **Rate limiting:** Maximum 100 orders per minute per API key. Exceeding this limit returns HTTP 429 with a `Retry-After` header indicating when the client may retry.
>
> **Request body:**
> ```json
> {
>   "customer_id": "cus_12345",
>   "line_items": [
>     {"product_id": "prod_abc", "quantity": 2},
>     {"product_id": "prod_def", "quantity": 1}
>   ],
>   "shipping_address_id": "addr_678"
> }
> ```
>
> **Response (201 Created):**
> ```json
> {
>   "order_id": "ord_90ab",
>   "status": "pending",
>   "total_amount": 45.99,
>   "created_at": "2026-04-17T12:00:00Z"
> }
> ```
>
> **Error responses:**
> - `400 Bad Request` — invalid request body
> - `401 Unauthorized` — missing or invalid API key
> - `404 Not Found` — customer, product, or address not found
> - `409 Conflict` — insufficient inventory
> - `429 Too Many Requests` — rate limit exceeded

### Questions

1. What does this endpoint do?
2. What status is the order in after creation?
3. What does "idempotent" mean in this context?
4. How long are idempotency keys stored?
5. What happens if you exceed 100 orders per minute?
6. What status code indicates insufficient inventory?
7. What HTTP method does this endpoint use?

### Answers

1. Creates a new order for an authenticated user with specified line items.
2. `pending` status until payment is confirmed.
3. Repeated requests with the same `Idempotency-Key` won't create duplicate orders — they return the same response.
4. 24 hours.
5. You get HTTP 429 with a `Retry-After` header telling you when to retry.
6. 409 Conflict.
7. POST.

### Key Vocabulary

- **idempotent** — təkrar çağırışda eyni nəticə verir
- **rate limiting** — sürət məhdudiyyəti
- **authenticated** — kimliyi təsdiq edilmiş
- **line item** — sifariş sətri (bir məhsul + miqdar)
- **endpoint** — API nöqtəsi
- **retry** — yenidən cəhd
- **inventory** — anbarda məhsul sayı
- **HTTP status code** — HTTP cavab kodu (200, 201, 400, 500 və s.)

---

## Text 2: Pull Request Description

### Başlıq
**feat(payments): add retry logic for Stripe API calls**

### Mətn

> ## What
>
> This PR adds retry logic to our Stripe API client. When a Stripe API call fails with a transient error (network timeout or 5xx response), the client now automatically retries up to 3 times with exponential backoff.
>
> ## Why
>
> Over the past month, we've seen ~0.3% of Stripe calls fail with timeouts or 502/503 errors. Most of these are transient — a retry 500ms later usually succeeds. Currently, a single failed call causes a customer payment to fail, which means they have to manually retry the checkout.
>
> ## How
>
> I wrapped the existing `StripeClient` in a `RetryableStripeClient` that implements the retry logic:
>
> 1. **Retry count:** up to 3 attempts (initial + 2 retries)
> 2. **Backoff:** 100ms, 500ms, 2500ms (exponential with jitter)
> 3. **Retry conditions:** 
>    - Network errors (timeout, connection refused)
>    - HTTP 5xx responses
>    - HTTP 429 (with `Retry-After` respected)
> 4. **Do not retry:**
>    - HTTP 4xx (client errors — retry won't help)
>    - Any response containing a `decline` field
>
> ## Testing
>
> - [x] Unit tests for retry logic (covered all branches)
> - [x] Integration tests with mocked failure injection
> - [x] Manually tested in staging against a Stripe test account
> - [x] Observed retries in logs during chaos testing
>
> ## Risks
>
> - **Increased latency for failing calls:** a call that would fail immediately now takes up to ~3s before giving up. This is an acceptable trade-off.
> - **Idempotency:** Stripe requires idempotency keys to avoid duplicate charges on retry. I've confirmed our code already generates unique keys per payment attempt.
>
> ## Rollout
>
> Behind feature flag `STRIPE_RETRY_ENABLED`. Will enable for 10% of traffic for 24 hours, then 100% if metrics look good.

### Questions

1. What problem does this PR solve?
2. How often were Stripe calls failing before?
3. How many retry attempts does the new logic make?
4. What's the delay between retries?
5. In which cases does the code NOT retry?
6. What's the main risk mentioned?
7. How will this be rolled out?

### Answers

1. Transient Stripe API failures (timeouts, 5xx errors) were causing customer payments to fail.
2. About 0.3% of calls.
3. Up to 3 attempts (initial + 2 retries).
4. 100ms, 500ms, 2500ms (exponential backoff with jitter).
5. HTTP 4xx responses and responses containing a `decline` field.
6. Increased latency for calls that ultimately fail — up to ~3 seconds before giving up.
7. Behind a feature flag, 10% traffic for 24 hours, then 100%.

### Key Vocabulary

- **transient error** — keçici xəta
- **exponential backoff** — üstəl gecikmə (100ms → 500ms → 2500ms)
- **jitter** — təsadüfi gecikmə (thundering herd qarşısı)
- **feature flag** — xüsusiyyət açarı
- **chaos testing** — qəsdən xəta inyeksiyası
- **idempotency key** — təkrarı önləyən açar
- **rollout** — yayım

---

## Text 3: Incident Postmortem

### Başlıq
**Postmortem: Checkout Service Outage — 2026-03-15**

### Mətn

> **Severity:** SEV1  
> **Duration:** 14:32 – 15:48 UTC (76 minutes)  
> **Impact:** ~3,000 users unable to complete checkout; estimated $45K in lost revenue.
>
> ## Summary
>
> A deploy on 2026-03-15 introduced a null-pointer bug in the checkout payment validation code. The bug affected approximately 1% of users whose billing address had a null country field. Users received 500 errors and could not complete purchases. We identified the root cause 23 minutes after detection and rolled back the deploy, restoring service.
>
> ## Timeline
>
> - 14:30 — Deploy #4581 released to production
> - 14:32 — Error rate on /checkout begins climbing
> - 14:37 — PagerDuty alert fires (threshold: error rate > 1%)
> - 14:39 — On-call engineer acknowledges alert
> - 14:45 — Initial investigation begins; error logs reviewed
> - 14:55 — Root cause identified: null-pointer in `validatePayment` function
> - 15:02 — Team decides to roll back rather than fix forward
> - 15:12 — Rollback initiated
> - 15:40 — Rollback complete; error rate drops
> - 15:48 — Metrics confirm full recovery
>
> ## Root Cause
>
> The `validatePayment` function was updated in PR #892 to support a new payment method. The new code accessed `user.billingAddress.country` without checking for null values. Approximately 1% of users have a null `country` field (legacy data), causing the function to throw a `NullPointerException`.
>
> The bug was not caught in review because reviewers focused on the happy path. It was not caught in testing because our test database does not contain users with null country fields.
>
> ## Detection
>
> PagerDuty alert fired 5 minutes after the first error. The alert threshold was correctly configured, and the on-call engineer responded within 2 minutes. Detection was timely.
>
> ## What Went Well
>
> - Alert detected the issue quickly
> - On-call response was fast
> - Rollback procedure worked smoothly
> - Customer communication via status page was prompt
>
> ## What Went Wrong
>
> - Bug not caught in code review
> - Test data did not cover legacy users with null country fields
> - Decision to roll back took 17 minutes (too long)
> - We did not have a defined "rollback or fix forward" decision framework
>
> ## Action Items
>
> - [ ] **Add integration tests for null country case.** Owner: @orkhan. Due: 2026-04-01.
> - [ ] **Improve staging DB to include edge-case user data.** Owner: @sara. Due: 2026-04-15.
> - [ ] **Update incident runbook with rollback decision criteria.** Owner: @mike. Due: 2026-04-10.
> - [ ] **Add null-safety linting rule to PR checks.** Owner: @alex. Due: 2026-04-20.
>
> ## Lessons Learned
>
> This incident reminded us that production data often contains edge cases our test environments don't. We need to invest in making staging more representative of production. Additionally, we should default to rolling back first and investigating after, especially when customer-facing features are affected.

### Questions

1. What severity was this incident?
2. How many users were affected?
3. How long did the outage last?
4. What was the root cause?
5. Why wasn't the bug caught in testing?
6. How long after detection was the root cause identified?
7. What is the main lesson learned?

### Answers

1. SEV1 (highest severity).
2. About 3,000 users.
3. 76 minutes.
4. A null-pointer bug in `validatePayment` — the code accessed `billingAddress.country` without checking for null values.
5. The staging/test database did not contain users with null country fields.
6. 23 minutes (from 14:32 detection to 14:55 identification).
7. Production data often contains edge cases that test environments miss; staging should better match production.

### Key Vocabulary

- **postmortem** — hadisədən sonrakı analiz
- **severity** — ciddilik (SEV1 > SEV2 > SEV3)
- **root cause** — əsl səbəb
- **null-pointer** — null referans xətası
- **edge case** — qeyri-adi hal
- **roll back** — geri qaytarmaq
- **fix forward** — irəliyə düzəltmək (yeni deploy ilə)
- **on-call** — növbətçi
- **runbook** — addım-addım təlimat
- **staging** — sınaq mühiti
- **happy path** — normal istifadə yolu

---

## Text 4: Technical Blog Post

### Başlıq
**Why We Moved From Kubernetes to Nomad**

### Mətn

> Six months ago, our team migrated from Kubernetes to HashiCorp Nomad for our workload orchestration. This was a contentious decision internally, and the blog posts about "why we chose Kubernetes" vastly outnumber those about leaving. Here's our story.
>
> ## Context
>
> We're a 30-person engineering team running a SaaS platform with about 50 services. We've used Kubernetes for four years. By most measures, our Kubernetes deployment was successful: reliable, auto-scaling, well-understood.
>
> So why change?
>
> ## The Problems
>
> **1. Operational complexity.** Kubernetes has enormous surface area. We had two engineers who were "Kubernetes experts" — when they took vacation, the rest of us were afraid to debug certain issues. Our runbooks were long, and onboarding new engineers on Kubernetes took weeks.
>
> **2. Upgrade pain.** Major Kubernetes upgrades (1.22 → 1.23, etc.) required careful planning, API deprecation reviews, and usually a sprint of work. We upgraded twice in three years — both times, something broke in ways we didn't expect.
>
> **3. Overkill for our scale.** Most of our services run 2-10 replicas. Kubernetes was designed for Google-scale, and many of its features (like complex networking primitives) we simply didn't use.
>
> ## Why Nomad?
>
> Nomad is a simpler orchestrator. It handles the core job of "run this container here" without the surrounding ecosystem complexity. Its feature set is narrower, but for our needs, that narrowness is a feature, not a bug.
>
> Specifically:
>
> - **Simpler mental model.** One binary, one language (HCL) for config.
> - **Better multi-region support.** Nomad handles federation of clusters more gracefully.
> - **Lower operational burden.** Our on-call pages related to orchestration dropped by 70% post-migration.
>
> ## What We Gave Up
>
> - **Ecosystem.** Kubernetes has more tools (Helm, Prometheus Operator, cert-manager). Nomad requires more DIY.
> - **Community size.** Stack Overflow answers are 10x more common for Kubernetes.
> - **Future flexibility.** If we grow 10x, Kubernetes might be the right choice again.
>
> ## The Migration
>
> We migrated over 4 months. Key steps:
>
> 1. Ran Nomad and Kubernetes in parallel for a quarter
> 2. Migrated non-critical services first
> 3. Built internal tooling to ease the transition
> 4. Cut over critical services in a series of one-week sprints
> 5. Decommissioned Kubernetes after 2 weeks of stable Nomad operation
>
> ## Would We Do It Again?
>
> Yes — but with reservations.
>
> If we were 200 engineers instead of 30, Kubernetes probably makes sense again. If our workloads were highly dynamic or multi-tenant, we'd reconsider.
>
> For teams like ours — mid-size, stable workloads, small ops team — Nomad has been the right call.

### Questions

1. How big is the engineering team?
2. For how long did they use Kubernetes?
3. What are the three main problems they had with Kubernetes?
4. What's the main advantage of Nomad they highlight?
5. What did they give up by moving to Nomad?
6. How long did the migration take?
7. In what scenarios would they choose Kubernetes again?

### Answers

1. 30 engineers.
2. Four years.
3. (a) Operational complexity, (b) upgrade pain, (c) overkill for their scale.
4. Simpler mental model and 70% reduction in orchestration-related on-call pages.
5. Ecosystem tools, community size, and future flexibility for extreme scale.
6. 4 months.
7. If they had 200 engineers, highly dynamic workloads, or multi-tenant needs.

### Key Vocabulary

- **orchestration** — orkestrasyon (konteyner idarəetmə)
- **SaaS (Software as a Service)** — xidmət olaraq proqram
- **operational complexity** — əməliyyat mürəkkəbliyi
- **runbook** — təlimat sənədi
- **upgrade** — təkmilləşdirmək
- **deprecation** — istifadədən çıxarılma
- **federation** — federasiya (çoxlu klasterin birləşməsi)
- **HCL (HashiCorp Config Language)** — HashiCorp konfiqurasiya dili
- **multi-tenant** — çoxistifadəçili
- **cut over** — keçid etmək
- **decommission** — istifadədən çıxarmaq

---

## Text 5: RFC (Request for Comments)

### Başlıq
**RFC-0042: Standardize Error Handling Across Services**

### Mətn

> **Author:** Orkhan Aliyev  
> **Status:** Draft  
> **Created:** 2026-04-15  
> **Target:** Q3 2026
>
> ## Motivation
>
> Our services currently handle errors inconsistently. Service A returns JSON error responses with a `{"error": "message"}` structure. Service B returns `{"code": 400, "message": "..."}`. Service C sometimes returns plain text. This inconsistency causes several problems:
>
> - **Client integration:** Mobile and web clients must implement different error handling for each service.
> - **Debugging:** On-call engineers struggle to parse errors across service boundaries.
> - **Tooling:** Our logging and monitoring cannot reliably extract error information.
>
> ## Proposal
>
> This RFC proposes adopting RFC 7807 (Problem Details for HTTP APIs) as our standard error format across all services.
>
> ### Error Response Schema
>
> All error responses MUST include:
>
> ```json
> {
>   "type": "https://example.com/errors/insufficient-funds",
>   "title": "Insufficient Funds",
>   "status": 402,
>   "detail": "Your account balance is insufficient to complete this transaction.",
>   "instance": "/transactions/ord_abc123"
> }
> ```
>
> Fields:
> - `type` (required): URI identifying the problem type
> - `title` (required): short human-readable summary
> - `status` (required): HTTP status code
> - `detail` (optional): specific description of this occurrence
> - `instance` (optional): URI identifying the specific occurrence
>
> Services MAY include additional fields for domain-specific error information.
>
> ### Content-Type
>
> Error responses MUST use `Content-Type: application/problem+json`.
>
> ## Migration Plan
>
> 1. **Phase 1 (Q2):** New services implement the standard from day one.
> 2. **Phase 2 (Q3):** Existing services add the new format as a supplementary response, alongside their current format.
> 3. **Phase 3 (Q4):** Clients update to consume the new format. Old formats are deprecated but still supported.
> 4. **Phase 4 (Q1 2027):** Old formats are removed.
>
> ## Alternatives Considered
>
> ### Alternative A: Custom internal schema
> Design our own error format. Rejected because it provides no advantage over an existing standard and introduces learning burden.
>
> ### Alternative B: GraphQL-style errors
> Return errors as part of a successful response body. Rejected because it requires major client rewrites.
>
> ## Backwards Compatibility
>
> During migration, services will return both old and new formats based on the `Accept` header. This allows clients to migrate at their own pace.
>
> ## Stakeholders
>
> Approval required from:
> - Backend Architecture Team
> - Mobile Team
> - Platform Team
>
> ## Open Questions
>
> - [ ] Should we standardize machine-readable error codes (e.g., `INSUFFICIENT_FUNDS`)?
> - [ ] How do we handle multi-error responses (validation errors)?
> - [ ] Do we need versioning on the `type` URIs?

### Questions

1. What is the main problem this RFC addresses?
2. What standard is being proposed?
3. What are the two required fields in the proposed schema?
4. How long is the full migration expected to take?
5. What was rejected alternative A and why?
6. How will backwards compatibility be handled?
7. What are the open questions?

### Answers

1. Inconsistent error handling across services (each service returns different formats).
2. RFC 7807 (Problem Details for HTTP APIs).
3. Required: `type`, `title`, `status`. (detail and instance are optional.)
4. About 3 quarters (Q2 2026 start, Q1 2027 old formats removed) — roughly 9 months.
5. Custom internal schema — rejected because it provides no advantage over an existing standard.
6. Services return both old and new formats based on the `Accept` header during migration.
7. Three: machine-readable codes, multi-error responses, and versioning of `type` URIs.

### Key Vocabulary

- **RFC (Request for Comments)** — texniki təklif sənədi
- **stakeholder** — maraqlı tərəf
- **migration plan** — keçid planı
- **backwards compatibility** — köhnə sistemlərlə uyğunluq
- **supplementary** — əlavə
- **schema** — sxem
- **URI (Uniform Resource Identifier)** — vahid resurs identifikatoru
- **deprecated** — köhnəldilmiş, sonra çıxarılacaq
- **domain-specific** — sahəyə xas

---

## Ümumi Strategiyalar — Texniki Oxu

### 1. Struktur Tanı
Texniki mətnlər proqnozlaşdırıla bilər. Adətən:
- Məqsəd
- Problem
- Həll
- Trade-off
- Next steps

Əvvəl strukturu başa düş, sonra detallara keç.

### 2. Akronimləri Axtar
Texniki mətnlər akronim dolu olur:
- İlk dəfə işlədilibsə, açılışı yazılır: "HTTP (HyperText Transfer Protocol)"
- Sonra yalnız "HTTP"
- Bilmədiklərini işarələ, sonra ara

### 3. Kod Blokları
Kod nümunələri mətnin **illüstrasiyasıdır**:
- Ümumi fikir mətndədir
- Detal koddadır
- İkisini birlikdə oxu

### 4. Lists və Bullet Points
Texniki mətnlər çox bullet istifadə edir:
- Tez tarama üçün yaxşı
- Sıralı nöqtələr üçün (1, 2, 3)
- Paralellik axtar

### 5. Link və Referanslar
- Bağlantıları "sonra oxu" üçün qeyd et
- Referansları izləmə cəhdi etmə (mətnin axarını pozar)

---

## Tez-tez Olan Texniki Oxu Növləri

### API Documentation
- Strukturlu (endpoint, params, response)
- Copy-paste edə biləcək nümunə
- Error codes

### Pull Request Description
- Context: nə, niyə, necə
- Testing
- Screenshots (UI dəyişiklikləri üçün)

### Postmortem
- Timeline
- Root cause
- Action items

### Blog Post
- Hekayə formalı
- Şəxsi təcrübə
- Opinion + fakt qarışıq

### RFC
- Formal struktur
- Alternativlər müzakirəsi
- Stakeholder yoxlama

### Stack Overflow
- Sual → cavablar
- Voted cavablar ən yaxşıdır (adətən)
- Şərhlər faydalı ola bilər

---

## Oxu Sürətini Artırmaq

### Texnika 1: Skim First, Deep Second
- Başlıqları + birinci cümlələri oxu (2 dəq)
- Strukturu başa düş
- Sonra tam oxu

### Texnika 2: Finger Pointing
- Gözünü sözdən-sözə sıçramaqdan saxla
- Barmağını mətn boyunca aparır
- Geri qayıtma vərdişini buraxır

### Texnika 3: Word Chunks
- Söz-söz oxuma
- 3-4 sözdən ibarət bloklar oxu
- Natural söz qruplarını tut

### Texnika 4: Predict
- Növbəti cümlə nə deyəcək? Təxmin et
- Düzgünsənsə → daha tez oxuyursan
- Səhvsənsə → diqqətli oxu

---

## Söz Ehtiyatı Genişləndirmək

### Hər gün 5 yeni texniki söz:
- Oxuduqca yeni sözü yaz
- Cümlə kontekstini də yaz
- Özün istifadə etməyə cəhd et

### Anki / Quizlet
- Texniki söz kartları
- Kontekst cümlə ilə birlikdə
- Gündə 10-15 təkrar

### Kontekstdə öyrən
- Tək söz unudulur
- "Idempotent" yerinə: "Idempotent means a repeated call returns the same result"

---

## İlk 20 Texniki Söz (A2-B1 üçün)

Bu 20 sözü hər texniki mətndə görəcəksən:

| # | Söz | Azərbaycanca | Nümunə |
|---|-----|--------------|--------|
| 1 | **endpoint** | API nöqtəsi | "The `/users` endpoint returns user data." |
| 2 | **payload** | yük, məzmun | "Send the payload as JSON." |
| 3 | **latency** | gecikmə | "Response latency is under 100ms." |
| 4 | **throughput** | ötürücülük | "Throughput is 1000 requests per second." |
| 5 | **cache** | ön yaddaş | "We cache user profiles for 10 minutes." |
| 6 | **deploy** | yaymaq | "We deploy twice a day." |
| 7 | **rollback** | geri qaytarma | "We rolled back the bad deploy." |
| 8 | **bug** | xəta | "There's a bug in the login flow." |
| 9 | **patch** | yamaq | "We shipped a patch to fix it." |
| 10 | **bottleneck** | darboğaz | "The database was the bottleneck." |
| 11 | **threshold** | həd | "Alert fires when error rate exceeds 1%." |
| 12 | **metric** | göstərici | "We track 5 key metrics." |
| 13 | **baseline** | əsas səviyyə | "Current baseline is 50ms." |
| 14 | **spike** | ani artım | "There was a traffic spike at noon." |
| 15 | **edge case** | qeyri-adi hal | "Handle edge cases in tests." |
| 16 | **load balancer** | yük paylayıcı | "The load balancer distributes traffic." |
| 17 | **replica** | replika | "We have 3 replicas for redundancy." |
| 18 | **rate limit** | sürət məhdudiyyəti | "Rate limit is 100 req/min." |
| 19 | **retry** | yenidən cəhd | "Retry with exponential backoff." |
| 20 | **stale** | köhnəlmiş | "Cache data is stale after 10 min." |

---

## Həftəlik Plan

### Həftə 1:
- Gündə 1 API documentation oxu
- 5 yeni söz/gün

### Həftə 2:
- Gündə 1 PR description
- Open-source repo-larda baxa bilərsən

### Həftə 3:
- Gündə 1 blog post (Medium, engineering blogs)
- Daha uzun mətnlər

### Həftə 4:
- 1 RFC / design doc
- 1 postmortem
- Daha texniki, dərin

---

## Real Resurslar

### API Docs (oxumaq üçün):
- Stripe (stripe.com/docs) — mükəmməl yazılmış
- Twilio (twilio.com/docs)
- GitHub API (docs.github.com)

### Engineering Blogs:
- Stripe — stripe.com/blog
- Shopify Engineering
- Airbnb Engineering
- Netflix Tech Blog
- Uber Engineering

### Postmortems:
- danluu.com/postmortem-lessons (ictimai postmortems kolleksiyası)
- github.com/danluu/post-mortems

### RFCs:
- IETF RFC index (rfc-editor.org)
- Python PEPs (peps.python.org)

---

## Əlaqəli Fayllar

- [A2 Reading Comprehension](a2-reading-comprehension.md)
- [B1 Reading Comprehension](b1-reading-comprehension.md)
- [Reading Skills](../../skills/reading/)
- [Tech Deep Dive Vocabulary](../../vocabulary/by-topic/technology/tech-deep-dive.md)
- [Technical Writing](../../skills/writing/technical-writing.md)
- [Reading Texts — Tech Documentation](../../reading-texts/b1-tech-documentation.md)
