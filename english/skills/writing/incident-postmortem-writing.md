# Incident Postmortem Writing — İncident Təhlili Yazmaq

## Səviyyə
B1-B2 (SRE / senior vacib!)

---

## Niyə Vacibdir?

Postmortem (post-mortem):
- Production hadisəsindən sonra yazılır
- "Nə baş verdi, niyə, nə öyrəndik"
- Blameless culture göstərir
- Interview-da çox vacib sual!

**Yaxşı postmortem = senior engineer imza**

---

## Postmortem Strukturu

```markdown
# Incident Postmortem: [Title]

## Summary
[2-3 cümlə]

## Impact
[Kimlər təsirləndi, nə qədər?]

## Timeline
[Event-lər xronoloji]

## Root Cause
[Əsl səbəb]

## Resolution
[Nə etdik fix etmək üçün]

## Lessons Learned
[Öyrəndiklərimiz]

## Action Items
[Gələcək üçün addımlar]
```

---

## 1. Title

Spesifik, aydın.

### ✓ Good

- "Database Outage — April 15, 2024"
- "API 500 Errors Due to Cache Failure"
- "Login Service Down for 2 Hours"

### ✗ Bad

- "Outage"
- "Something went wrong"

---

## 2. Summary

2-3 cümlə. Hamı bunu oxuyacaq.

### Template

```
On [date] at [time], [service] experienced [issue],
affecting [who/how many] for [duration]. The root
cause was [brief reason]. We [main action taken].
```

### Example

```
On April 15, 2024, at 14:32 UTC, our API experienced
a 45-minute outage affecting 60% of users. The root
cause was a database connection pool exhaustion
triggered by a slow query. We restarted the DB and
added monitoring.
```

---

## 3. Impact

Kimi, nə qədər təsirlədi.

### Metrics

- Duration: 45 minutes
- Affected users: 600,000 (60% of DAU)
- Failed requests: 3.2M
- Revenue impact: $15,000
- SLA breach: Yes (99.9% target missed)

### Users

- Geographic scope (US, EU, global?)
- User tiers (free, paid, enterprise?)
- Specific features affected

---

## 4. Timeline (ÇOX VACİB!)

Xronoloji hadisələr. **UTC vaxt istifadə et.**

### Format

```
## Timeline (All times UTC)

- 14:00 — Deploy of v2.3.1 completed successfully
- 14:15 — Database CPU starts climbing (monitoring alert)
- 14:32 — First user reports login issues
- 14:33 — On-call engineer paged
- 14:40 — Investigation begins; slow queries identified
- 14:50 — Incident declared SEV-1
- 15:10 — Rollback initiated
- 15:17 — Service restored
- 15:20 — Monitoring confirms recovery
- 15:30 — Incident closed
- 16:00 — Status page updated
```

### Key events

- First detection
- Escalation
- Mitigation attempts
- Resolution
- Confirmation of recovery

---

## 5. Root Cause (5 Whys!)

Əsl səbəbi tap. "5 Whys" texnikası.

### 5 Whys Example

**Problem:** Database connection pool exhausted.

1. **Why?** Too many concurrent connections.
2. **Why?** Slow query held connections for 30+ seconds.
3. **Why?** Missing index on `orders.user_id`.
4. **Why?** Migration added new column but didn't update indexes.
5. **Why?** No database migration review process.

**Root cause:** Lack of DB migration review process.

### Format

```markdown
## Root Cause

The incident was caused by a **missing database index**
on the `orders.user_id` column. This caused queries to
run in O(n) instead of O(log n), taking 30+ seconds.

The missing index was introduced in PR #456 ("Add user
preferences") which added a new column but didn't update
the index strategy.

### Contributing Factors

- No DB migration review process
- No query performance regression tests
- Monitoring didn't alert on slow queries earlier
```

---

## 6. Resolution

Nə etdik fix etmək üçün.

### Immediate mitigation

- Rolled back the deploy
- Restarted the database
- Cleared cache
- Scaled up resources

### Permanent fix

- Added the missing index
- Deployed improved query

### Format

```markdown
## Resolution

### Immediate Mitigation (15:10 UTC)
- Rolled back PR #456 to revert the column addition.
- Restarted database pods to clear hanging connections.

### Permanent Fix (April 16)
- Added index on `orders.user_id` in PR #457.
- Re-deployed original feature with proper indexing.
```

---

## 7. Lessons Learned

Nə öyrəndik? **Blameless!**

### ✓ Good (blameless)

- "We didn't have visibility into slow queries."
- "Our migration process lacks index review."
- "Monitoring thresholds were too high."

### ✗ Bad (blame)

- "Alice made a mistake."
- "Bob didn't test properly."
- "The team failed."

### Template

```markdown
## Lessons Learned

### What Went Well
- On-call response was fast (within 1 minute)
- Rollback process worked smoothly
- Team communicated well in Slack

### What Went Wrong
- Monitoring didn't catch the issue until users complained
- Migration review didn't cover performance impact
- Rollback took longer than expected (10+ minutes)

### Where We Got Lucky
- The issue was caught during business hours
- Rollback was possible (could have been irreversible)
```

---

## 8. Action Items

Konkret addımlar. **Owner + Deadline!**

### Format

```markdown
## Action Items

| Action | Owner | Priority | Due |
|--------|-------|----------|-----|
| Add DB migration review checklist | @alice | High | 2024-04-30 |
| Set up slow query monitoring | @bob | High | 2024-05-05 |
| Add query performance CI checks | @team | Medium | 2024-05-20 |
| Update runbook for DB incidents | @carol | Low | 2024-06-01 |
```

### Qaydalar

- **Specific** (konkret addım)
- **Owner** (kim edəcək)
- **Due date** (nə vaxtadək)
- **Priority** (sıra)

---

## Blameless Culture

**Kritik:** Postmortem **adam suçlamaq** üçün deyil.

### ✓ Blameless

- "The process failed."
- "Our system allowed this to happen."
- "We didn't have safeguards."

### ✗ Blame

- "Person X caused this."
- "If they had tested..."
- "They should have known."

### Niyə blameless?

- Psychological safety
- Honest reporting
- Better learning
- Team trust

---

## Tone və Tərz

### ✓ Professional

- "The system experienced..."
- "We identified..."
- "The root cause was..."

### ✗ Emotional

- "It was a disaster."
- "We failed miserably."
- "This was catastrophic."

### ✓ Objective facts

- "Response time increased from 200ms to 8 seconds."
- "60% of requests failed."

### ✗ Subjective

- "Everything was broken."
- "It was really bad."

---

## Common Phrases

### Describing incidents

- "The service experienced..."
- "We observed an anomaly..."
- "The incident began at..."
- "Users reported..."

### Root cause

- "The root cause was..."
- "The issue was triggered by..."
- "This was caused by..."

### Actions

- "We mitigated the issue by..."
- "To prevent recurrence, we will..."
- "The team responded by..."

### Learning

- "We learned that..."
- "This highlighted the need for..."
- "Going forward, we will..."

---

## Severity Levels

### SEV-1 (Critical)

- Production down
- Revenue impact
- Customer-facing
- All-hands response

### SEV-2 (High)

- Major feature broken
- Partial outage
- Performance degradation

### SEV-3 (Medium)

- Minor feature issue
- Workaround available
- Not customer-facing

### SEV-4 (Low)

- Minor UI issue
- Internal only
- No user impact

---

## Timeline Vocabulary

### Detection

- "First alerted at..."
- "User reports started at..."
- "Monitoring triggered at..."

### Investigation

- "Investigation began..."
- "Initial hypothesis was..."
- "We ruled out..."

### Mitigation

- "We rolled back..."
- "We scaled up..."
- "We disabled the feature..."

### Recovery

- "Service restored at..."
- "Monitoring confirmed recovery..."
- "All systems green at..."

---

## Example (Real Format)

```markdown
# Incident Postmortem: API Outage — 2024-04-15

## Summary
On April 15, 2024, from 14:32 to 15:17 UTC (45 min), our
main API returned 500 errors for 60% of requests. Root cause:
database connection pool exhaustion due to slow queries.

## Impact
- **Duration:** 45 minutes
- **Affected users:** ~600K (60% of DAU)
- **Failed requests:** 3.2M
- **Revenue impact:** ~$15K
- **SLA:** 99.9% target missed (actual: 99.85%)

## Timeline (All times UTC)
- 14:00 — Deploy of v2.3.1 successful
- 14:15 — DB CPU alert (warning level)
- 14:32 — First 500 errors; users affected
- 14:33 — On-call paged
- 14:40 — Slow queries identified
- 14:50 — SEV-1 declared
- 15:10 — Rollback initiated
- 15:17 — Service restored
- 15:30 — Incident closed

## Root Cause
Missing database index on `orders.user_id` column caused
queries to run in O(n). Introduced in PR #456 which added
a new column but didn't update indexes.

## Resolution
- 15:10: Rolled back PR #456
- Apr 16: Added index and re-deployed

## Lessons Learned

### What Went Well
- Fast on-call response (1 min)
- Smooth rollback

### What Went Wrong
- No slow query alerts
- Migration review missed indexing

## Action Items
| Action | Owner | Due |
|--------|-------|-----|
| Add migration review checklist | @alice | Apr 30 |
| Set up slow query alerts | @bob | May 5 |
| CI perf regression tests | @team | May 20 |
```

---

## Interview Kontekstində

### "Walk me through an incident you handled"

Use postmortem structure:

1. **What happened** (summary)
2. **How you detected** (timeline)
3. **How you mitigated** (resolution)
4. **Root cause** (5 whys)
5. **What you learned** (lessons)
6. **What you changed** (action items)

### Example answer

"Last year, we had a **database outage** affecting 60% of users. I was on-call and **identified** slow queries within 10 minutes. We **rolled back** the latest deploy which mitigated the issue. The **root cause** was a missing index. Our **action items** included adding migration review checklists. I led the postmortem writing."

---

## Tools / Platforms

### Postmortem tools

- **Jira Service Management**
- **PagerDuty**
- **Blameless**
- **GitHub Issues** (simple)
- **Notion / Confluence** (docs)

---

## Azərbaycanlı Səhvləri

- ✗ "X person made mistake."
- ✓ **Blameless**: "The process lacked safeguards."

- ✗ Times without timezone.
- ✓ Always **UTC** (or specify timezone).

- ✗ "We don't know why."
- ✓ "**Investigation ongoing**. Updated by [date]."

---

## Xatırlatma

**Postmortem çekliss:**
1. ✓ Clear summary
2. ✓ Impact metrics
3. ✓ UTC timeline
4. ✓ Root cause (5 whys)
5. ✓ Resolution steps
6. ✓ Lessons learned
7. ✓ Action items (with owners!)
8. ✓ **Blameless tone**

**Interview qızıl:**
- "I wrote a postmortem for the incident..."
- Shows ownership + professionalism

→ Related: [bug-report-writing.md](bug-report-writing.md), [design-doc-writing.md](design-doc-writing.md), [devops-vocabulary.md](../../vocabulary/topics/devops-vocabulary.md)
