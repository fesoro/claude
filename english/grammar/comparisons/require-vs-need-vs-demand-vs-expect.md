# Need / Require / Demand / Expect — Tələb Etmək Fellər

## Səviyyə
B1-B2

---

## Əsas Cədvəl

| Söz | Nədir? | Güc? |
|-----|--------|------|
| **need** | lazımdır (ümumi) | neytral |
| **require** | mütləq lazımdır (formal) | rəsmi / məcburi |
| **demand** | güclü tələb et (urgent/authority) | güclü, bəzən aqressiv |
| **expect** | gözləyirsən (olacaq gümanı) | gözlənti, zəmanə yox |

> **Qısa qayda:**
> - **need** = lazımdır (gündəlik)
> - **require** = məcburidir (rəsmi, API, doc)
> - **demand** = güclü tələb (urgent, authority)
> - **expect** = gözləyirəm ki olacaq (zəmanə yox)

---

## 1. Need — Ümumi Lazımdır

Ən çox işlənən. Gündəlik, neytral. Hər kontekstdə.

### Nümunələr

- I **need** more time.
- The code **needs** refactoring.
- We **need** an API key.
- I **need** help with the deployment.
- The server **needs** a restart.

### Kontekst

- Casual dev söhbəti: "We **need** to fix this before the demo." ✓
- Özünü ifadə: "I **need** a second pair of eyes on this PR." ✓
- Neytral — nə çox rəsmi, nə çox güclü

### Struktur

- **need** + N
- **need** + to + V
- **need** + V-ing (= need to be V-ed)

- I **need** a database connection.
- I **need** **to** fix this.
- The code **needs** **refactoring**. (= needs to be refactored)

### "Need" passive məna (important!)

- The bug **needs** fixing. = The bug needs to be fixed.
- This **needs** reviewing. ✓

---

## 2. Require — Rəsmi Məcburilik

Formal. API sənədləşməsində, sistem tələblərində, rəsmi kontekstdə.
"Olmasa olmaz" hissi var.

### Nümunələr

- This endpoint **requires** an API key.
- Authentication is **required**.
- The function **requires** two parameters.
- Visa **requires** these documents.
- A valid token is **required** to access this resource.

### Əsas fərq: Need vs Require

- **need** = casual "lazımdır"
- **require** = formal "məcburidir / tələb olunur"

- "We **need** an API key." (casual söhbət)
- "This endpoint **requires** an API key." (API sənədi)

### Tech / API kontekstdə — standart dil

- "**Required** field." ✓ (form validation)
- "**Required** parameter." ✓ (API doc)
- "Authentication **required**." ✓ (403 error message)
- "**Requires** PHP 8.2+" ✓ (package doc)

### Struktur

- **require** + N
- **require** + sb + to + V
- **required** = adj kimi çox işlənir

- **Require** authentication.
- We **require** all developers **to** write tests.
- **Required** fields: name, email.

---

## 3. Demand — Güclü Tələb Et

Urgent, gücə əsaslı tələb. Authority var — ya mövqe, ya vəziyyətin ciddilik dərəcəsi.
Bəzən aqressiv səslənə bilər — diqqət!

### Nümunələr

- The client **demanded** an immediate fix.
- This issue **demands** urgent attention.
- The SLA **demands** 99.9% uptime.
- The situation **demands** a decision now.
- He **demanded** an explanation.

### Əsas fərq: Need/Require vs Demand

- **need** / **require** = lazım / məcburidir
- **demand** = güclü tələb, urgent və ya güc hissi var

- "We **need** a response." (neytral)
- "We **require** a response by EOD." (rəsmi)
- "We **demand** an immediate response." (güclü — müştəri, ya çox ciddi)

### Kontekst: Demand nə vaxt?

- Müştəri problemi: "The client **demanded** a hotfix." ✓
- SLA/sistem: "This **demands** a 99.9% SLA." ✓
- Ciddi vəziyyət: "The outage **demands** all hands on deck." ✓
- İsim kimi: "supply and **demand**" ✓

### ⚠ Diqqət: Demand informal kontekstdə

Gündəlik söhbətdə "demand" çox güclü, bəzən rudely.
- "I **demand** you fix this." = very aggressive
- Colleagues ilə: **need** / **require** daha yaxşı

### Struktur

- **demand** + N
- **demand** + that + clause (subjunctive: formal)
- **demand** + to + V

- He **demanded** a refund.
- They **demanded that** we fix it immediately.
- She **demanded to** know the reason.

---

## 4. Expect — Gözləmək (Zəmanəsiz)

Bir şeyin olacağını güman etmək — amma zəmanə yox.
Gözlənti, anticipation.

### Nümunələr

- I **expect** this to be done by Friday.
- The API **expects** a JSON string.
- We **expect** high traffic after the launch.
- I **expected** you to know this.
- Don't **expect** perfection on the first try.

### Əsas fərq: Require vs Expect

- **require** = mütləq lazımdır (olmasa error)
- **expect** = gözləyirik ki olacaq (olmasa surprise)

- "The function **requires** an integer." (integer vermesən crash)
- "The function **expects** an integer." (integer gözlənilir — doc context)

### ⚠ Testing — çox vacib!

`expect` testing framework-lərdə literal keyword-dür:

```javascript
expect(result).toBe(true);
expect(user.name).toEqual('Alice');
```

```php
expect($result)->toBeTrue();  // Pest
```

Bu "gözləyirəm ki" = assertion.

### Tech kontekstdə

- "The API **expects** a Bearer token in the header." ✓ (API doc)
- "We **expect** the service to handle 10k RPS." ✓ (load)
- "**Expected** behavior vs actual behavior." ✓ (bug report)
- "**Expected** response: 200 OK." ✓ (test case)

### İş konteksti

- "I **expect** the PR to be reviewed by EOD." (gözlənti)
- "What do you **expect** from this role?" (job interview)

### Struktur

- **expect** + N
- **expect** + sb + to + V
- **expect** + (that) + clause

- I **expect** good results.
- I **expect** you **to** be on time.
- I **expect** (that) we'll finish by Friday.

---

## Müqayisə Cədvəli

| | Need | Require | Demand | Expect |
|-|------|---------|--------|--------|
| Güc | neytral | rəsmi | güclü | gözlənti |
| Formal | az | çox | çox | orta |
| Zəmanə | yox | yox | bəzən | yox |
| API/Doc | az | çox | az | bəli |
| Testing | az | az | yox | çox (keyword!) |
| Aqressiv? | yox | yox | bəzən | yox |

---

## Tez-tez Yanlış İşlənənlər

| ❌ Yanlış | ✅ Düzgün |
|-----------|-----------|
| The endpoint needs an API key. (API doc-da) | The endpoint **requires** an API key. |
| I demand more time. (colleagues ilə) | I **need** more time. |
| The client needed an immediate fix. (urgent!) | The client **demanded** an immediate fix. |
| I require your help. (casual) | I **need** your help. |
| The function demands two parameters. | The function **requires** two parameters. |
| I need the PR done by Friday. (strong expectation) | I **expect** the PR done by Friday. |
| The API needs a JSON body. (doc context) | The API **expects** / **requires** a JSON body. |
| We expected all fields to be present. (məcburi) | We **required** all fields to be present. |

---

## Test

Hansı söz uyğundur?

1. We ______ an API key to access this endpoint. (məcburi, doc dili)
2. I ______ more time to debug this issue. (casual, neytral)
3. The client ______ a fix within 2 hours. (urgent, güclü)
4. The test ______ the result to be 42. (testing assertion)
5. This feature ______ refactoring before we can add more. (gündəlik)
6. The SLA ______ 99.9% availability. (güclü, rəsmi tələb)
7. The function ______ a valid integer — passing null will throw. (məcburi)
8. I ______ the deployment to be complete by Monday. (gözlənti)

**Cavablar:** 1. require, 2. need, 3. demanded, 4. expects, 5. needs, 6. demands, 7. requires, 8. expect

---

## Cümləni tamamlayın

1. The ______ fields are marked with an asterisk. (required)
2. I ______ you to write tests for every PR. (strong expectation)
3. This outage ______ our immediate attention. (güclü, urgent)
4. Our stack ______ PHP 8.2 or higher. (rəsmi tələb)
5. What do you ______ from your next job? (interview sualı)
6. We ______ to improve our test coverage before release. (casual)

**Cavablar:** 1. required, 2. expect, 3. demands, 4. requires, 5. expect, 6. need

---

## Tech / İş Kontekstində

### Need

- "We **need** a cache layer." ✓
- "The service **needs** a health check endpoint." ✓

### Require

- "Authentication is **required**." ✓ (HTTP 401/403)
- "**Required** fields: email, password." ✓ (form/API)
- "This package **requires** Laravel 11." ✓ (composer)

### Demand

- "The SLA **demands** sub-100ms response time." ✓
- "The situation **demanded** a rollback." ✓

### Expect

```javascript
expect(response.status).toBe(200);   // Jest
expect($user->email)->toBe($email);  // Pest
```

- "The API **expects** a Unix timestamp." ✓ (doc)
- "We **expect** 5x traffic on Black Friday." ✓ (capacity planning)

---

## Azərbaycanlı Səhvləri

- ✗ I demand help. (colleagues ilə — çox aqressiv)
- ✓ I **need** help.

- ✗ The API needs authorization. (API doc-da — require daha professional)
- ✓ The API **requires** authorization.

- ✗ I expect two parameters. (məcburi → require)
- ✓ The function **requires** two parameters.

---

## Xatırlatma

| Söz | Bir sözdə |
|-----|-----------|
| **need** | lazımdır (casual) |
| **require** | məcburidir (rəsmi) |
| **demand** | güclü tələb (urgent/authority) |
| **expect** | gözləyirəm ki olacaq |

**API doc qaydası:** `require` — "This endpoint **requires** an API key."
**Testing qaydası:** `expect` — `expect(result).toBe(true)`

→ Related: [must-have-to-should-need-to.md](must-have-to-should-need-to.md), [ask-vs-request-vs-demand.md](ask-vs-request-vs-demand.md)
