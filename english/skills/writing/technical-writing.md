# Technical Writing — Texniki Yazı

## Bu Fayl Haqqında

Müasir software engineer üçün **yazmaq** çox vacibdir. Design docs, RFC-lər, postmortems, pull request description-lar, API documentation — bunlar gündəlik işdir. Yaxşı texniki yazı:
- Komandanı uzlaşdırır
- Qərarları sənədləşdirir
- Yeni komanda üzvlərini tez-tez öyrədir
- Səni "senior" kimi göstərir

Bu fayl **beş əsas texniki yazı formatını** əhatə edir: design docs, RFCs, postmortems, PR description-ları, və documentation.

---

## 1. Design Doc — Layihə Sənədi

**Nə:** Yeni xüsusiyyət və ya sistem qurmazdan əvvəl yazdığın sənəd. Məqsəd: komandanın razılığını almaq, qərarları sənədləşdirmək.

**Uzunluq:** 2-5 səhifə adətən. 1 səhifədən az = çox az. 10+ səhifə = çox uzun.

### Standart struktur:

```markdown
# [Feature Name]

**Author:** [Your Name]  
**Status:** Draft / Review / Approved  
**Reviewers:** [Names]  
**Last Updated:** YYYY-MM-DD

## Summary
[2-3 sentence TL;DR]

## Background / Context
[Why are we doing this? What's the problem?]

## Goals
- [Specific, measurable]

## Non-Goals
- [What this is NOT doing — to set expectations]

## Proposed Solution
[The actual design]

## Alternatives Considered
### Alternative A
[Description, pros, cons]

### Alternative B
[Description, pros, cons]

## Trade-offs
[What are we giving up?]

## Risks & Mitigations
- Risk: [Description]
  Mitigation: [Plan]

## Rollout Plan
[How will we deploy this? Feature flags? Migration?]

## Success Metrics
[How will we know this worked?]

## Open Questions
- [ ] [Question 1]
- [ ] [Question 2]

## Appendix
[Diagrams, references, detailed calculations]
```

### Hər bölmə üçün açar ifadələr:

**Summary:**
- "This document proposes [X]."
- "We're planning to build [Y] because [reason]."
- "This doc describes the design for [feature]."

**Background:**
- "Currently, [existing state]."
- "The problem is [issue]."
- "This is causing [impact]."
- "Users have reported [feedback]."

**Goals:**
- "Reduce X by Y%."
- "Enable [capability]."
- "Support [use case]."

**Non-Goals:**
- "This is NOT a replacement for [X]."
- "We're explicitly NOT addressing [Y] in this iteration."
- "Out of scope: [Z]."

**Proposed Solution:**
- "The approach I'm proposing is [X]."
- "At a high level, we'll [Y]."
- "The main components are [A, B, C]."

**Alternatives:**
- "I considered three approaches."
- "The main alternative was [X], which [pros/cons]."
- "I ruled out [Y] because [reason]."

**Trade-offs:**
- "This approach is simpler but less flexible than [X]."
- "We're optimizing for [A] at the cost of [B]."
- "The main compromise is [Z]."

**Risks:**
- "The biggest risk is [X]. To mitigate, we'll [Y]."
- "If [bad thing] happens, the impact would be [scope]."

**Rollout:**
- "We'll roll this out in phases: phase 1 will [X], phase 2 will [Y]."
- "We'll deploy behind a feature flag, gradually ramping up."
- "First rollout to [group], then [group]."

### Tam nümunə (qısa):

```markdown
# Add Rate Limiting to Public API

**Author:** Orkhan Aliyev
**Status:** Draft
**Reviewers:** @sara (backend), @mike (security)
**Last Updated:** 2026-04-17

## Summary
We propose adding rate limiting to our public API to prevent abuse
and protect downstream services. Limits will be per-API-key.

## Background
Our public API has been growing in usage. Over the past month,
we've had 3 incidents where a single client generated 100x their
normal traffic, causing degradation for everyone. Currently, we
have no mechanism to throttle abusive clients.

## Goals
- Prevent any single client from consuming more than 1000 requests/minute
- Return clear 429 errors with Retry-After headers
- Support different limits for free/paid tiers

## Non-Goals
- This is NOT a DDoS protection solution (that requires Cloudflare)
- We're NOT implementing user-level rate limits in this iteration
- IP-based rate limiting is explicitly out of scope

## Proposed Solution
Implement sliding-window rate limiting using Redis. The middleware
will check each request, increment the counter, and return 429 if
the limit is exceeded.

Key components:
1. Redis-based sliding window counter
2. Express middleware to check limits
3. Admin dashboard to configure per-key limits

## Alternatives Considered

### Alternative A: Token Bucket
Pros: Smoother rate limiting, allows bursts.
Cons: More complex to implement correctly.

### Alternative B: Fixed Window
Pros: Simplest.
Cons: "Thundering herd" at window boundaries.

I chose sliding window because it balances simplicity and
correctness for our use case.

## Risks & Mitigations

- **Risk:** Redis outage takes down the API.
  **Mitigation:** Fail open — if Redis is down, don't rate limit.
  Alert on this scenario.

- **Risk:** Legitimate bursts get throttled.
  **Mitigation:** Start with generous limits; tune based on data.

## Rollout Plan
1. Deploy behind feature flag, off.
2. Enable for 10% of traffic, monitor.
3. Ramp to 100% over 1 week.

## Success Metrics
- Zero "abusive client" incidents in the 30 days post-rollout
- 99.9% of legitimate traffic unaffected (measured by false-positive rate)
- p99 latency of middleware < 5ms

## Open Questions
- [ ] Do we need separate limits for read vs write endpoints?
- [ ] Should we expose current rate limit status in response headers?
```

---

## 2. RFC (Request for Comments)

**Nə:** Design doc-un daha formal, daha böyük variantı. Adətən arxitektur səviyyəli qərarlar üçün.

### Əlavə bölmələr (Design Doc-dan):
- **Motivation** (Background-dan daha geniş)
- **Detailed Design** (addım-addım implementasiya)
- **Backwards Compatibility** (köhnə sistemlər necə təsirlənir)
- **Stakeholders** (kim razılaşmalıdır)
- **Timeline** (aylar, ya illər)

### Açar ifadələr:
- "This RFC proposes [fundamental change]."
- "The current system has the following limitations: [list]."
- "We propose to [major change] over the next [timeframe]."
- "This will require buy-in from [team A, team B]."
- "Backwards compatibility: existing clients [impact]."

### Qəbul / rədd prosesi:
- "This RFC is currently in [Draft / Review / Accepted / Rejected] state."
- "Approvers: @name1, @name2"
- "Deadline for feedback: [date]"

---

## 3. Postmortem / Incident Report

**Nə:** İncident-dən sonra yazılan sənəd. Məqsəd: nə oldu, niyə oldu, necə qarşını alacağıq.

**Qayda: BLAMELESS** — adlar çəkmə, sistem və proses üzərinə yaz. Məqsəd öyrənməkdir, kimsə günahlandırmaq yox.

### Struktur:

```markdown
# Postmortem: [Brief Description] — [YYYY-MM-DD]

**Severity:** SEV1 / SEV2 / SEV3
**Duration:** [Start time] – [End time]
**Impact:** [What users experienced]
**Author:** [Name]
**Review Date:** [When team discusses]

## TL;DR
[1-2 sentences: what happened, how it was fixed]

## Impact
- [Specific user impact]
- [Business impact — revenue, SLA breach]

## Timeline
- HH:MM — [Event 1]
- HH:MM — [Event 2]
- ...

## Root Cause
[Technical explanation]

## Resolution
[How it was fixed]

## Detection
[How we noticed — alert, user report, etc.]
[Could we have detected earlier?]

## What Went Well
- [Thing 1]
- [Thing 2]

## What Went Wrong
- [Thing 1]
- [Thing 2]

## Action Items
- [ ] [Specific action] — Owner: @name — Due: [date]
- [ ] ...

## Lessons Learned
[Broader takeaways]
```

### Açar ifadələr:

**Tonu neutral saxla:**
- "The system did X" (not "John did X")
- "The deploy introduced a bug" (not "John introduced a bug")
- "This was missed during review" (not "You missed this")

**Cause-analysis:**
- "The root cause was [X]."
- "A contributing factor was [Y]."
- "The issue was triggered by [Z]."
- "This had been latent in the code for [duration]."

**What went well:**
- "Our monitoring detected the issue within [time]."
- "The on-call engineer responded within [time]."
- "The rollback procedure worked as designed."

**What went wrong:**
- "We took [time] to identify the root cause, which is too long."
- "The alert fired but did not wake up the right person."
- "We don't have documentation for this procedure."

**Action items (specific, assigned):**
- "Add integration test for [specific case]. Owner: @name. Due: 2026-04-30."
- "Update runbook for [process]. Owner: @name. Due: 2026-05-15."

### Nümunə:

```markdown
# Postmortem: Checkout Service Outage — 2026-03-15

**Severity:** SEV1
**Duration:** 14:32 – 15:48 UTC (76 minutes)
**Impact:** ~3,000 users unable to complete checkout; estimated $45K in lost revenue
**Author:** Orkhan Aliyev

## TL;DR
A deploy introduced a null pointer bug in checkout payment validation.
We rolled back after 76 minutes, restoring service.

## Impact
- 3,000 users unable to complete checkout during the 76-minute window
- $45,000 estimated revenue loss
- SLA breach for checkout service (uptime fell below 99.9%)
- 47 support tickets filed

## Timeline
- 14:30 UTC — Deploy #4581 released to production
- 14:32 — Error rate on /checkout starts rising
- 14:37 — PagerDuty alert fires
- 14:39 — On-call engineer acknowledges
- 14:55 — Root cause identified (null pointer in validation)
- 15:12 — Decision to roll back
- 15:40 — Rollback completed
- 15:48 — Metrics confirm full recovery

## Root Cause
Deploy #4581 added a new payment validation function. The function
did not handle the case where `user.billingAddress` is `null`. This
field is null for ~1% of users. When affected users tried to check
out, they got a 500 error.

## Resolution
Rolled back deploy #4581 via deploy system. Service recovered within
8 minutes of rollback.

## Detection
PagerDuty alert fired at 14:37 based on error rate threshold. This
is working as designed. However, we did not catch this in staging
because our staging dataset doesn't include users with null
billing addresses.

## What Went Well
- Monitoring detected the issue quickly (5 minutes)
- On-call engineer responded within 2 minutes of alert
- Rollback procedure worked smoothly

## What Went Wrong
- Bug made it past code review
- Staging data didn't match production (missing null cases)
- Took 16 minutes to decide on rollback (too long)

## Action Items
- [ ] Add integration tests covering `billingAddress = null` case.
      Owner: @orkhan. Due: 2026-04-01.
- [ ] Improve staging data to include edge cases from production.
      Owner: @sara. Due: 2026-04-15.
- [ ] Update incident runbook with rollback decision criteria.
      Owner: @mike. Due: 2026-04-10.

## Lessons Learned
- Our staging environment is less representative of production
  than we thought. We should invest in making it closer.
- "When in doubt, roll back" should be explicit in the runbook.
- Payment code touches critical paths — deserves more testing rigor.
```

---

## 4. Pull Request Description

**Nə:** Hər PR üçün yaxşı description — reviewer-lərin vaxtını qoruyur.

### Standart şablon:

```markdown
## What
[One-sentence description of the change]

## Why
[Why we're making this change]

## How
[Brief technical approach]

## Testing
- [ ] Unit tests added
- [ ] Tested locally
- [ ] Tested in staging
- [ ] Screenshots attached (for UI changes)

## Screenshots / Demos
[If applicable]

## Related
- Closes #123
- Related to #456
```

### Nümunələr:

**Qısa (trivial PR):**
```markdown
## What
Fix typo in error message.

## Why
Currently says "Recieved" instead of "Received".
```

**Orta (feature):**
```markdown
## What
Add retry logic to Stripe API calls.

## Why
We've seen intermittent Stripe timeouts causing failed payments.
Retrying with exponential backoff should recover from transient errors.

## How
- Wrap Stripe calls in a retry helper with configurable max retries
- Default: 3 retries with 100ms, 500ms, 2500ms delays
- Only retry on network errors and 5xx responses (not 4xx)

## Testing
- [x] Unit tests for retry logic
- [x] Tested locally by injecting failures
- [x] Tested in staging with chaos testing

## Related
- Closes #523
- Follow-up to incident #IR-2026-12
```

**Ətraflı (böyük dəyişiklik):**
```markdown
## What
Migrate payment processing from monolith to new `payments` microservice.

## Why
See design doc: [link]
Summary: the monolithic payment code is tightly coupled with user
management, making changes risky. Extracting to a dedicated service
improves isolation and enables independent deployment.

## How
This PR contains:
1. New `payments` service skeleton (Flask + PostgreSQL)
2. HTTP API matching the existing internal payment interface
3. Data migration script (one-time) to copy existing transactions
4. Feature flag `USE_NEW_PAYMENTS_SERVICE` to toggle between old/new
5. Monitoring dashboards

### Rollout plan
1. Merge PR (feature flag off — no behavior change)
2. Enable flag for 1% of traffic, monitor for 48 hours
3. Ramp to 100% over 1 week
4. Remove old code after 2 weeks of stable operation

## Testing
- [x] Unit tests (86% coverage on new code)
- [x] Integration tests (happy path + failure modes)
- [x] Load tested (handled 10x current peak)
- [x] Dark-launched against production traffic (read-only)

## Screenshots
N/A (backend-only)

## Related
- Design doc: [link]
- Closes #100 (payment service epic)
- Related to #101 (consolidation of billing logic)

## Reviewer Notes
- High-risk change. Please focus on error handling and rollback safety.
- I recommend reviewing in 2 sittings — it's a lot of code.
```

### Açar ifadələr:
- "This PR [does X]."
- "The motivation is [Y]."
- "The approach is [Z]."
- "I've tested by [method]."
- "Closes #123."
- "Breaking change: [yes/no]. If yes: [impact]."
- "Please pay special attention to [X]."

---

## 5. Documentation (README, API Docs)

### README.md — Layihə üçün

Nümunə şablonu:

```markdown
# Project Name

[Brief tagline describing what it does]

## Overview
[2-3 sentences about the project]

## Features
- Feature 1
- Feature 2

## Quick Start

### Prerequisites
- Node.js 18+
- PostgreSQL 14+

### Installation
```bash
git clone [repo]
cd project
npm install
cp .env.example .env
```

### Running
```bash
npm run dev
```

Visit http://localhost:3000

## API Documentation
See [docs/api.md](docs/api.md).

## Development
[Development setup, testing, contributing]

## Architecture
See [docs/architecture.md](docs/architecture.md).

## License
MIT
```

### API Documentation — Endpoint Nümunəsi

```markdown
### POST /api/orders

Create a new order.

#### Request

Headers:
- `Authorization: Bearer <token>` (required)
- `Content-Type: application/json`

Body:
```json
{
  "items": [
    { "product_id": 123, "quantity": 2 }
  ],
  "shipping_address_id": 456
}
```

#### Response

**Success (201 Created)**
```json
{
  "order_id": "ord_abc123",
  "total": 45.99,
  "status": "pending"
}
```

**Errors**
| Status | Code | Description |
|--------|------|-------------|
| 400 | `INVALID_ITEMS` | No items in order |
| 400 | `INVALID_ADDRESS` | Address ID not found |
| 401 | `UNAUTHORIZED` | Missing or invalid token |
| 409 | `OUT_OF_STOCK` | One or more items are out of stock |

#### Example

```bash
curl -X POST https://api.example.com/orders \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"product_id":123,"quantity":2}],"shipping_address_id":456}'
```

#### Notes
- Orders are processed asynchronously. Use GET /orders/:id to check status.
- Rate limit: 10 requests/minute per API key.
```

### Açar ifadələr documentation üçün:
- "This endpoint [action]."
- "Required/Optional: [field]."
- "Returns [type] with fields: [list]."
- "Example: [code]."
- "Note: [important detail]."

---

## 6. Commit Messages

### Yaxşı commit message strukturu:

```
<type>(<scope>): <subject>

<body — optional>

<footer — optional>
```

### Types:
- **feat:** new feature
- **fix:** bug fix
- **docs:** documentation
- **style:** formatting (no code change)
- **refactor:** code restructuring (no behavior change)
- **test:** adding tests
- **chore:** tooling, dependencies

### Nümunələr:

**Qısa:**
```
fix(auth): handle expired refresh tokens
```

**Orta:**
```
feat(payments): add retry logic for Stripe API

Wraps Stripe calls in retry-with-backoff helper.
Retries on network errors and 5xx responses, not on 4xx.
```

**Ətraflı (breaking change):**
```
refactor(users): rename email field to primary_email

BREAKING CHANGE: The `email` field on User model has been
renamed to `primary_email`. Update any consumers.

Migration plan:
1. Old field kept as alias for 30 days
2. Deprecation warnings added
3. Field removed in v3.0

Closes #890
```

### ⚠️ Pis commit message-lər:
- ❌ "fix" (nə fix?)
- ❌ "WIP" (bitməmiş)
- ❌ "asdf"
- ❌ "update code"
- ❌ "it works now"

---

## 7. Tech Spec / Design Questions

Design doc yazmazdan əvvəl bu sualları özünə ver:

### Problem:
- What problem are we solving?
- Whose problem is it?
- What happens if we don't solve it?
- Is there evidence this is the right problem?

### Solution:
- What's the simplest thing that could work?
- What are the alternatives?
- Why is this one best?
- What are the trade-offs?

### Technical:
- How will this scale?
- What can fail, and what happens when it does?
- How will we test it?
- How will we monitor it?
- How will we roll it out?
- How will we roll it back?

### People:
- Who needs to agree?
- Who will maintain this long-term?
- Who will be affected downstream?

---

## 8. Effective Writing — Ümumi Prinsiplər

### 1. TL;DR əvvəldə
Hər sənədin başında 1-2 cümlə: əsas fikir. Oxucu gərək bəzən yalnız TL;DR oxusun.

### 2. Aydın struktur
Header-lər, bullet-lər, qısa abzaslar. Solid wall of text → heç kəs oxumaz.

### 3. Konkret
❌ "This will improve performance."  
✅ "This will reduce p99 latency from 500ms to 100ms."

### 4. Passive voice qaçın
❌ "The bug was discovered."  
✅ "I discovered the bug."

### 5. "We" vs "I"
Design doc-da "we propose" (komanda). PR description-da "I added" (fərdi).

### 6. Qısa cümlə
Uzun cümlələr başa düşmək çətindir. **15-20 söz** maksimum ideal.

### 7. Biznes dilini qarışdırma
❌ "Leveraging synergies to drive impact."  
✅ "Adding feature X to help users do Y."

---

## 9. Azərbaycan Danışanlar — Tipik Səhvlər

### ❌ Həddindən artıq formal
"It is my pleasure to inform you that we have decided to refactor the user authentication module in order to enhance..."  
→ "We're refactoring the auth module to improve security."

### ❌ Passive voice
"The system was designed by the team to be scalable."  
→ "The team designed the system to scale."

### ❌ Çox uzun giriş
Şirkətin tarixi, prosesin izahı, əvvəlki cəhdlər... 3 abzas sonra nə istədiyin bəlli olur.  
→ Əsas fikri birinci abzasda ver.

### ❌ "It" referansı qarışıq
"We fixed the bug. It was important."  
→ "It" nə? Bug, ya fix? "This fix was important."

### ❌ Articles həmişə səhv
"the system" vs "a system" vs heç nə — tech writing-də dəqiq olmalıdır.

### ❌ Tenses qarışıq
Past-da yazdığın bir qərarı indi "we are doing" ilə qarışdırma.

---

## 10. Nəzərdən Keçirmə Checklist

Göndərmədən əvvəl özünə bu sualları ver:

**Struktur:**
- [ ] TL;DR var?
- [ ] Headers logikaya uyğundur?
- [ ] Hər bölmə lazımdırmı?

**Məzmun:**
- [ ] Niyə, nə, necə — hər üçü var?
- [ ] Alternativlər göstərilib?
- [ ] Trade-off-lar aydınlaşdırılıb?
- [ ] Rəqəm, misal var?

**Dil:**
- [ ] Yazı səhvi yoxdur?
- [ ] Qrammar düzgün? (Grammarly)
- [ ] Aktiv voice-da yazıram?
- [ ] Cümlələr 20 sözdən qısa?

**Audiensiya:**
- [ ] Kimlər oxuyacaq?
- [ ] Onlar hər sözü anlayırmı?
- [ ] Kontekst aydındır?

**Fəaliyyət:**
- [ ] Oxucu nə etməlidir? (review, approve, feedback)
- [ ] Deadline var?
- [ ] Kim sualı cavablandıracaq?

---

## 11. Mükəmməl TL;DR Yazmaq

TL;DR = oxucu yalnız bunu oxusa, **ən vacib** məlumatı almalıdır.

### Formula:
> "[What] + [Why it matters] + [Impact/Action]"

### Nümunələr:

**Design doc TL;DR:**
> "We propose rate-limiting our public API to prevent abuse incidents. This will protect downstream services without affecting 99% of legitimate users."

**Postmortem TL;DR:**
> "A deploy on 2026-03-15 introduced a null-pointer bug, causing checkout failures for 3,000 users over 76 minutes. We rolled back and are adding tests + better staging data to prevent recurrence."

**RFC TL;DR:**
> "This RFC proposes migrating from REST to GraphQL for our public API over the next 6 months. This will reduce client/server coupling and improve mobile app performance."

---

## 12. Collaborative Editing — Başqalarının Rəyi

### Şərh soruşan tərəf
- "Please focus on [specific area]."
- "I'm most unsure about [X]. Would love your thoughts."
- "High-level feedback is welcome; don't worry about typos yet."

### Şərh verən tərəf
- "Nit: [small suggestion]."
- "Suggestion: [improvement]."
- "Blocker: [must-fix]."
- "Question: [clarifying question]."

---

## 13. Yazı Formatı — Best Practices

### Markdown istifadə et:
- Header-lər için `#`, `##`, `###`
- Bold üçün `**text**`
- Kod üçün `` `code` `` və `` ```code blocks``` ``
- Link üçün `[text](url)`

### Diagrams:
- Mermaid, PlantUML, Excalidraw — text-based, git-də izlənir
- Kağız üzərində çəkib fotoşəkil çək və əlavə et
- Complex sistemlər üçün — həmişə diagram vacibdir

### Code snippets:
Həmişə language tag ver:
````markdown
```python
def hello():
    print("Hello!")
```
````

---

## 14. Azərbaycan Danışanlar Üçün Yazı İpuçları

### 1. İlk draft-ı Azərbaycan dilində yaz
Sonra İngiliscəyə çevir. Fikir aydın olar.

### 2. Grammarly işlət
Həmişə. Ödənişsiz versiya kifayətdir.

### 3. Güclü fel işlət
"I was responsible for" → "I led"  
"The system can perform" → "The system handles"

### 4. Articles (a/an/the) dəqiq
Texniki yazıda articles həddən artıq vacibdir. Bir neçə dəfə oxu.

### 5. Tenses ardıcıl
Bir cümlədə "we built" və "we are building" qarışdırma.

### 6. Native speaker-dən review soruş
Mümkünsə, bir kolleqadan "does this read well?" soruş.

---

## 15. Nümunə İstinadlar — Yaxşı Texniki Yazı

Bu şirkətlərin engineering bloglarından oxu:
- **Stripe Engineering Blog** — clean, detailed
- **Shopify Engineering** — pragmatic
- **Airbnb Engineering** — cultural + technical
- **GitHub Blog** — short, informative
- **Amazon 6-page memos** — (daxili format)

### Öyrənmə yolu:
1. Gözlə seçdiyin blogun 3-5 məqaləsini oxu
2. Struktura diqqət et (hansı başlıqlar, hansı ardıcıllıq)
3. Öz yazını analoji strukturda yaz

---

## 16. Praktik Məşq

### Məşq 1: 1-Paragraph Summary
Hazırki layihən haqqında 3-4 cümlədə TL;DR yaz.

### Məşq 2: PR Description
Son yazdığın kodu təsəvvür et. Kitaba görə PR description yaz.

### Məşq 3: Postmortem (uydurma)
"Production database crashed for 2 hours" üçün postmortem yaz. Təmizayılığa diqqət et.

### Məşq 4: Design Doc
Hazırki problemə bir design doc yaz (2 səhifədən az).

---

## Əlaqəli Fayllar

- [Email Writing](email-writing.md)
- [Interview Follow-up Emails](interview-follow-up-emails.md)
- [Paragraph Structure](paragraph-structure.md)
- [Linking Words](linking-words.md)
- [Tech Deep Dive Vocabulary](../../vocabulary/by-topic/technology/tech-deep-dive.md)
- [Slack/Teams Messaging](slack-teams-messaging.md)
