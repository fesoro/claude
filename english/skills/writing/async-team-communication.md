# Async Team Communication (Remote Work English)

## 1. Niyə Async Writing Vacibdir?

Remote iş mühitində **yazılı kommunikasiya = əsas işdir**. Office-də olan şeyləri remote-da edə bilmirsən:
- Stolunun yanından keçən birini çağıra bilmirsən
- "Bir dəqiqə soruşum" deyib edə bilmirsən
- Körpə dil, ton, mimika yoxdur

Bu o deməkdir ki:
- Bir mesajın **aydın olmaması** işi saatlarla gecikdirə bilər
- **Context verməmək** = digər developer həmin anı yaşamır
- **Vague suallar** = vague cavablar, ya da sual qarşı sualı doğurur

Remote teamdə yaxşı yazı = daha sürətli iş, daha az friction, daha çox trust.

---

## 2. Slack Mesajları — Effektiv Yazma

### Formula: Context + Problem + Nə Yoxladın + Nə Lazımdır

### Nümunə 1: Bug Report

❌ Bad:
```
Hey, something is broken
```

✅ Good:
```
The payment webhook is returning 500 since 14:30 UTC.
Affecting: production (EU region only)
Already checked: Stripe logs, our app logs — issue seems to be in signature
validation (STRIPE_WEBHOOK_SECRET mismatch after last deploy).
Investigating now. Will update in 30 min.
```

Fərq: hər kəs dərhal vəziyyəti başa düşür, əlavə sual yoxdur.

---

### Nümunə 2: Review Sorğusu

❌ Bad:
```
Can you review my PR?
```

✅ Good:
```
Could someone review PR #248 (auth refactor)?
It touches the JWT middleware and session handling.
~200 lines, should take 15–20 min.
Wanted: feedback on the token refresh logic (line 87–120) — I'm not sure
my approach is idiomatic for our stack.
```

---

### Nümunə 3: Blocker

❌ Bad:
```
I'm stuck on the deployment
```

✅ Good:
```
Blocked on deploying the new queue worker to staging.
Error: "Connection refused" when worker tries to reach Redis.
Redis is running (confirmed via `redis-cli ping`).
Config looks correct in .env.staging.
Been on this for 1.5 hours — anyone familiar with our staging setup?
```

---

### Nümunə 4: Qısa Update

❌ Bad:
```
Done with the task
```

✅ Good:
```
Done with the rate limiting implementation (ticket BE-441).
PR is up: #251. Covered unit tests + one integration test.
Note: I used the token bucket algorithm — let me know if you'd prefer sliding window instead.
```

---

### Nümunə 5: Help Request

❌ Bad:
```
How do I use Kafka here?
```

✅ Good:
```
Quick question about our Kafka setup:
I'm producing events from the OrderService, but messages aren't appearing
in the consumer. Producer is confirmed working (checked via Kafka UI).
Consumer group ID matches the topic config.
Is there anything specific about our consumer config I should check?
Relevant code: [link to file, line 45]
```

---

### Nümunə 6: Timezone-Aware Request

❌ Bad:
```
Can we meet tomorrow?
```

✅ Good:
```
Could we sync briefly tomorrow about the API design?
I'm UTC+4, free from 10:00–13:00 UTC.
If async works better, I can write up my questions in a doc — just let me know.
```

---

### Qısa Qaydalar

- **Thread-dən istifadə et** — uzun müzakirəni `#general`-da etmə, thread aç
- **@mention et** — kimi lazımdırsa onu tag et, hamını yox
- **Emoji reactions istifadə et** — "👀" = baxıram, "✅" = bitdi, "🔄" = işlənir
- **Uzun mesajı formatla** — bullet, bold, code block

---

## 3. Async Status Updates

Bir çox remote team video standup əvəzinə yazılı update istifadə edir.

### Standart Template

```
**Yesterday**
- Completed the OAuth2 integration (PR #244 — ready for review)
- Investigated slow queries on the orders table; found missing index

**Today**
- Add the missing index + monitor query times
- Start on the notification service (ticket BE-448)

**Blockers**
- None currently
```

---

### Qısa Update (Heç bir problem yoxdursa)

```
Yesterday: finished token refresh logic + tests.
Today: PR review + starting on the email worker.
Blockers: none.
```

---

### Detailed Update (Böyük bir şeyin ortasındaysan)

```
**Yesterday**
- Deep dive into the payment service bottleneck. Found the issue: N+1 query
  in the invoice generation (was firing ~40 queries per request). Fixed with
  eager loading — query count now 3 per request.
- Added regression test to cover this case.

**Today**
- Monitor production metrics after deploy (watching p95 latency)
- Sync with @sara about the upcoming billing cycle changes (she's got context I need)

**Blockers**
- Waiting on DB team to confirm the index strategy for the invoices table.
  If no response by EOD, I'll proceed with my current approach.
```

---

### Nə Vaxt Qısa, Nə Vaxt Uzun?

| Vəziyyət | Uzunluq |
|----------|---------|
| Rutin gün, heç bir problem yox | Qısa (3 line) |
| Mürəkkəb iş, başqaları təsirlənə bilər | Detailed |
| Blocker varsa | Həmişə aydın izah et |
| Release/deploy günü | Detailed |

---

## 4. Escalation Messages

Nəsə urgent olarsa — text ilə necə escalate etmək olar?

### Formula: Impact + Timeline + Nə Yoxladın + Nə Lazımdır

### Production Down

```
🔴 Production incident — payment service is down.
Impact: all checkout flows failing since 15:47 UTC (approx 12 min ago).
Investigated: last deploy was 15:30 UTC (PR #239). Rolled back — still failing.
Logs show DB connection pool exhausted.
Need: someone with DB access to check connection counts.
Pinging @devops @tech-lead
```

---

### Deadline at Risk

```
⚠️ Heads up: the API integration for the Stripe migration is at risk for Friday.
Reason: their sandbox environment has been down since yesterday — can't test the
webhook flows. Already contacted their support (ticket #SU-88821, no ETA).
Options I see:
1. Stub the integration for now, complete after sandbox is back
2. Push delivery to Monday

@pm @tech-lead — which direction do you prefer?
```

---

### Blocker (Uzun müddətdir)

```
Flagging a blocker: I've been stuck on the CI pipeline failure for 3+ hours.
The Docker build fails only in CI (works locally).
Tried: clearing cache, checking env vars, comparing base images — no luck.
This is blocking the release of BE-412.
Could anyone with CI experience take a look?
Relevant logs: [link]
```

---

### Urgency Siqnalları

| Signal | İstifadə Halı |
|--------|--------------|
| 🔴 | Production down / data loss risk |
| ⚠️ | Deadline at risk / major blocker |
| Emoji olmadan | Normal sual, aşağı urgency |
| `@channel` | Bütün team lazımdır |
| `@person` | Spesifik birinə |

**Qayda:** dramatik olma — faktları ver, nə lazım olduğunu de.

---

## 5. Asking Questions in Text

Remote teamdə vague sual = vaxt itkisi.

### Formula: Context + Spesifik Sual + Nə Cəhd Etdin + Nə Lazımdır

### Nümunə 1: Technical Question

❌ Bad:
```
Can you help me with the auth?
```

✅ Good:
```
I'm implementing OAuth2 with Laravel Passport.
The token refresh is failing with a 401 — error message:
"Token has been revoked" (even though it's a fresh token, not revoked).

Tried:
- Checked token is stored correctly in DB ✓
- Verified client credentials ✓
- Confirmed the `personal_access_client` record exists ✓

Relevant code: [link to AuthController, line 88]

Should I check the token expiry config? Or is there something else I'm missing?
```

---

### Nümunə 2: Architecture Question

❌ Bad:
```
Should we use Redis or a DB for this?
```

✅ Good:
```
Quick architecture question:
For the session token blacklist (after logout), I'm deciding between:
- Redis with TTL matching the token expiry
- DB table with a cleanup job

Our current setup: Redis is already running for caching.
Scale: ~50k active sessions at peak.

My lean: Redis (no extra cleanup job needed, TTL is automatic).
Any concerns with this approach, or does it seem reasonable?
```

---

### Nümunə 3: Process Question

❌ Bad:
```
How do releases work here?
```

✅ Good:
```
Quick process question: what's our release flow for hotfixes?
I have a critical fix ready (BE-501) and want to make sure I follow
the right steps. Is it: hotfix branch → PR → direct to main, or
does it still go through staging?
```

---

## 6. Cavab Verdiyin Zaman (Responding)

### Qəbul Etmək

```
Got it — I'll look into this now.
```
```
Thanks for the heads up. I'll check the logs and get back to you.
```
```
On it. Give me ~30 minutes.
```

---

### Gözlənti Bildirmək

```
I'll get back to you within 2 hours.
```
```
I'm in a meeting until 15:00 UTC — will respond after that.
```
```
This needs some digging — I'll have an answer by EOD.
```

---

### Bilmədiyini Demək

```
I'm not sure about this one — let me check with @backend-team.
```
```
Not my area, but @lisa would know. Tagging her in.
```
```
I don't have full context here. Could you share the relevant ticket?
Then I can give a proper answer.
```

---

### Cavab Verməyə Vaxtın Yoxdursa

```
Saw this — can't dig in right now, but I'll circle back by tomorrow morning.
```
```
Flagging this for myself — I'll have a proper response by EOD.
```

---

## 7. Useful Phrases

| Vəziyyət | Phrase | Azərbaycanca |
|----------|--------|-------------|
| Mesaj göndərərkən | "Just a heads up —" | "Bildirmək istəyirdim ki —" |
| Diqqəti cəlb etmək | "Flagging this for visibility" | "Hamının görməsi üçün qeyd edirəm" |
| Yenilik vermək | "Quick update on [X]" | "[X] haqqında qısa yenilik" |
| Sual vermək | "One quick question:" | "Bir qısa sual:" |
| Rəy istəmək | "Would love a second pair of eyes on this." | "Buna bir baxış atmağınızı istərdim." |
| Blokeri bildirmək | "Blocked on X — need Y to proceed." | "X-də blokdum — davam etmək üçün Y lazımdır." |
| Tamamlandı | "Wrapped up / Done with [X]." | "[X] tamamlandı." |
| Gözlənti | "ETA: [time]" | "Gözlənilən vaxt: [vaxt]" |
| Razılıq | "Makes sense — will do." | "Başa düşdüm — edəcəm." |
| Güzəşt | "Happy to jump on a call if easier." | "Zəng daha rahat olarsa, hazıram." |
| Cavab gecikirsə | "Sorry for the delayed response —" | "Gec cavab verdiyim üçün üzr istəyirəm —" |
| Prioriteti aydınlaşdırmaq | "Is this urgent, or can it wait until tomorrow?" | "Bu urgentdir, yoxsa sabaha qala bilər?" |
