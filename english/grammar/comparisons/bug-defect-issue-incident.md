# Bug / Defect / Issue / Incident / Outage — Problem Tipləri

## Səviyyə
B1-B2 (tech interview vacib)

---

## Əsas Cədvəl

| Söz | Nə deməkdir? | Ciddiyyət |
|-----|--------------|-----------|
| **bug** | kod səhvi | kiçik-orta |
| **defect** | rəsmi ada bug | rəsmi QA termin |
| **issue** | ümumi problem | neytral |
| **incident** | qəzavari hadisə | production-da |
| **outage** | xidmət düşüb | kritik |

> **Qısa qayda:**
> - **bug** = developer söhbəti
> - **defect** = QA / formal testing
> - **issue** = GitHub issue / ümumi
> - **incident** = production qəzası
> - **outage** = sistem düşüb

→ Related: [mistake-error-fault-bug.md](mistake-error-fault-bug.md), [problem-issue-trouble-matter.md](problem-issue-trouble-matter.md)

---

## 1. Bug — Kod Səhvi

Ən çox işlədilir. Kodun gözləniləndən fərqli davranışı.

### Nümunələr

- I fixed a **bug** in the login flow.
- Report this **bug** on GitHub.
- There's a **bug** in the payment logic.
- Critical **bug** blocking deployment.
- Nasty **bug** — took 3 hours to find.

### Types

- **bug** = ümumi
- **critical bug** = kritik
- **minor bug** = kiçik
- **edge case bug** = nadir hal
- **race condition bug** = paralellik səhvi
- **regression bug** = əvvəl işləyirdi, indi işləmir

---

## 2. Defect — Rəsmi / QA Termini

QA/test mühəndisləri tərəfindən işlədilir. Bug-un rəsmi adı.

### Nümunələr

- The QA team logged 5 **defects**.
- **Defect** severity level 2.
- **Defect** tracking system.
- **Defect** density per 1000 lines of code.

### Bug vs Defect

- **bug** → gündəlik developer termin
- **defect** → rəsmi QA / sertifikasiya kontekst

- "Found a **bug**" — developer
- "Logged a **defect** in Jira" — QA engineer

---

## 3. Issue — Ümumi Problem / Ticket

GitHub issue, Jira ticket. Bəzən bug, bəzən feature request.

### Nümumlər

- Open an **issue** on GitHub.
- This **issue** needs investigation.
- The **issue** was resolved in v2.1.
- Assign this **issue** to me.
- Triaging **issues**.

### Issue != Bug

- **issue** daha geniş = bug + feature request + question
- **bug** = yalnız səhv

- "I opened an **issue** to ask about the API."  — sual da issue ola bilər
- "This **issue** is actually a **bug**."

---

## 4. Incident — Production Hadisəsi

Production-da qeyri-normal hadisə. Adətən users təsirlənir.

### Nümunələr

- We had a major **incident** last night.
- **Incident** response team activated.
- **Incident** report.
- Post-**incident** review.
- **Incident** severity SEV-1.

### İfadələr

- **incident** response
- **incident** commander
- **incident** management
- **post-incident review (PIR)** = sonrakı analiz
- **SEV-1 / SEV-2** = severity levels

### Bug vs Incident

- **bug** = kod səhvi
- **incident** = hadisənin özü (bug → incident → outage ola bilər)

---

## 5. Outage — Xidmət Düşüb

Ən ağır forma — sistem düşüb, users xidmətdən istifadə edə bilmir.

### Nümunələr

- There's a major **outage**.
- API **outage** affected 30% of users.
- Planned **outage** for maintenance.
- **Outage** lasted 2 hours.

### Types

- **full outage** = hər şey düşüb
- **partial outage** = bəzi servislər düşüb
- **planned outage** = planlaşdırılmış
- **unplanned outage** = gözlənilməz

---

## 6. Error vs Fault vs Failure (qonşu anlayışlar)

| Söz | Nə deməkdir? |
|-----|--------------|
| **error** | message / log output |
| **fault** | hansısa komponentin qüsuru |
| **failure** | sistem və ya xidmətin fail olması |

- Check the **error** logs.
- Hardware **fault**.
- Service **failure**.

→ Related: [mistake-error-fault-bug.md](mistake-error-fault-bug.md)

---

## Real Kontekst Nümunələri

### Developer daily

- "I found a **bug** in the payment service." ✓
- "Let me open an **issue** in Jira." ✓

### QA testing

- "The test suite found 3 **defects**." ✓

### Production postmortem

- "The **incident** started at 3 AM." ✓
- "This **outage** impacted 50k users." ✓

### GitHub / Open Source

- "Please file an **issue** if you find a **bug**." ✓

---

## Test

Hansı söz daha uyğun?

1. We had a 2-hour API ______ last Friday. (xidmət düşüb)
2. The QA team logged 10 ______ in testing. (formal)
3. Open a GitHub ______ for this feature request.
4. This ______ in the login code is blocking us. (kod)
5. Post-______ review scheduled for tomorrow. (production hadisə)

**Cavablar:** 1. outage, 2. defects, 3. issue, 4. bug, 5. incident

---

## İnterview Söhbətləri

- "I handled a production **incident** where our API was down for an hour."
- "We use Jira to track **bugs** and **issues**."
- "After the **outage**, we wrote a detailed post-mortem."
- "QA found 5 **defects** in the final testing round."

---

## Azərbaycanlı Səhvləri

- ✗ We had a bug in production for 2 hours. (səhv tip!)
- ✓ We had an **outage** in production for 2 hours. (xidmət düşdü)

- ✗ Major bug last night — team on the call.
- ✓ Major **incident** last night — team on the call.

---

## Xatırlatma

- **bug** = kod səhvi (daily speak)
- **defect** = QA-formal bug
- **issue** = GitHub/Jira ticket (bug + feature + sual)
- **incident** = production hadisəsi
- **outage** = xidmət düşüb
