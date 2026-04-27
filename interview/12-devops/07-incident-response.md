# Incident Response (Lead ⭐⭐⭐⭐)

## İcmal
Incident response — production sistemlərindəki xərabalanmaları strukturlaşdırılmış şəkildə aşkar etmək, həll etmək, kommunikasiya etmək və öyrənmək prosesidir. Yaxşı incident response texniki həlldən daha çox: sakit qalmaq, düzgün prioritizasiya etmək, team-i koordinasiya etmək, stakeholder-ları məlumatlandırmaq kabiliyyətidir. Lead engineer kimi bu prosesi qurmaq və inkişaf etdirmək sizin öhdəliyinizdir.

## Niyə Vacibdir
Production incident-ı qaçınılmazdır. Sual "incident olacaqmı?" deyil, "incident olduqda nə qədər sürətli həll edəcəksiniz?" Lead developer incident zamanı texnik xilaskar olmaqdan öncə prosesi idarə edəndir. "Incident zamanı team-i necə idarə etdiniz?" sualı Senior-Lead müsahibələrinin ən çox verilən suallarından biridir.

## Əsas Anlayışlar

### Incident Lifecycle:

```
1. Detection (Aşkarlama)
   ↓
2. Declaration (Elan)
   ↓
3. Response (Cavab)
   ↓
4. Mitigation (Zərəri Dayandır)
   ↓
5. Resolution (Tam Həll)
   ↓
6. Post-mortem (Öyrənmə)
```

---

### Incident Severity Levels:

**SEV-0 / P0 — Critical:**
- Tam production outage
- Data loss ya da corruption riski
- Security breach
- Bütün team dərhal devreye girer

**SEV-1 / P1 — High:**
- Əsas funksionallıq işləmir (payment, login)
- Çoxlu istifadəçi təsirlənir
- On-call + backup devreye girer

**SEV-2 / P2 — Medium:**
- Qismən degradasiya, workaround var
- Az istifadəçi təsirlənir
- On-call idarə edir

**SEV-3 / P3 — Low:**
- Minor anomaliya, business impact yox
- Ticket yaradılır, prioritizasiya edilir

---

### Incident Response Proseduru:

**Phase 1: Detection & Declaration (ilk 5 dəqiqə)**

```
Alert gəldi
  ↓
Acknowledge (1-2 dəqiqə)
  ↓
Qiymətləndir: Bu real incident mi?
  ├── Bəli → Declare incident
  └── Xeyr → False positive → Alert fix

Incident elan edildi:
- Slack-da #incident-2025-04-20-payment channel aç
- Incident commander təyin et (adətən on-call)
- SEV müəyyənləşdir
- Stakeholder-ları ilkin xəbərdar et
```

**Phase 2: Response (diagnoz)**

```
Incident Commander:
  - Team-i koordinasiya edir
  - Kanalda what/who/when izlər
  - Stakeholder update-lərini idarə edir
  - Özü əlləri ilə fikslemez (idarə edir!)

Technical Lead:
  - Root cause araşdırır
  - Runbook-u izləyir
  - Fix/mitigation hazırlayır

Communications Lead:
  - İstifadəçilərə status update (status page)
  - C-level üçün executive summary
```

**Phase 3: Mitigation vs Fix:**

```
Mitigation (sürətli, risk var):
  - Rollback deployment
  - Feature flag ilə feature-ı söndür
  - Traffic-i sağlıklı instance-a yönləndir
  - Temporary rate limiting

Fix (düzgün, zaman alır):
  - Root cause-u düzəlt
  - Test et
  - Deploy et

QAYDA: Əvvəlcə mitigate et — istifadəçi zərərini dayan;
sonra düzgün fix.
```

---

### Incident Communication:

**Status page update (hər 15-30 dəqiqə):**
```
[14:30] Investigating: Payment service performance issues. We are aware and investigating.
[14:45] Identified: Root cause identified — database migration caused connection bottleneck.
[15:00] Monitoring: Fix deployed, monitoring for stability.
[15:30] Resolved: Payment service fully operational. Postmortem in progress.
```

**Executive communication (SEV-0/1 üçün):**
```
Subject: [INCIDENT P1] Payment Service — Update 14:45

Status: INVESTIGATING
Impact: ~500 users unable to complete checkout
Duration: ~15 minutes
Action: Team investigating DB connection issue
ETA: 30 minutes to resolution
Next update: 15:15
```

**Internal Slack template:**
```
🚨 INCIDENT DECLARED — SEV-1
System: Payment Service
Impact: 5xx errors, ~30% requests failing
Commander: @john
On-call: @jane
Bridge: [Zoom link]
Status page: [Link]

Timeline:
14:30 - Alert received
14:33 - Incident declared
```

---

### Incident Command Structure:

**Küçük team (< 5 kişi):**
- Incident Commander = on-call
- Hər kəs problem üzərindədir

**Orta team (5-15 kişi):**
- Incident Commander: koordinasiya, stakeholder
- Technical Responder(s): həll
- Communications: external/internal update

**Böyük team / Major outage:**
- Incident Commander
- Technical Lead (ayrıca)
- Communications Lead
- Operations (infra/deploy)
- SME (Subject Matter Expert) — lazım olduqda çağırılır

---

### MTTR Azaltmaq (Mean Time To Recovery):

```
MTTD (Detection): Alert nə qədər gec gəlir?
MTTA (Acknowledge): On-call nə qədər gec cavab verir?
MTTI (Identify): Root cause-u tapmaq nə qədər vaxt alır?
MTTF (Fix): Fix deploy etmək nə qədər vaxt alır?

MTTR = MTTD + MTTA + MTTI + MTTF
```

**MTTR azaltmaq üçün:**
- MTTD: Alert threshold-larını kalibrə et
- MTTA: Escalation policy-ni yaxşılaşdır
- MTTI: Runbook + Observability (trace, log, metric)
- MTTF: Rollback mexanizmi sürətli olsun

---

### Post-Mortem Best Practices:

**Nə vaxt:** Incident-dən 24-72 saat sonra (detallar unudulmadan)

**Format:**
```markdown
# Post-Mortem: [Incident Title] — [Date]

## Summary (2-3 cümlə)

## Impact
- Duration:
- Users affected:
- Revenue impact (əgər məlumdursa):

## Timeline (UTC)

## Root Cause Analysis
5 Whys metodologiyası:
- Why 1: Payment API 5xx verdi → DB connection pool tükəndi
- Why 2: Connection pool tükəndi → Migration lock table-ı tutdu
- Why 3: Migration lock tutdu → pt-online-schema-change istifadə edilmədi
- Why 4: pt-osc istifadə edilmədi → runbook-da mövcud deyildi
- Why 5: Runbook-da yox idi → Previous incident-larda bu pattern görülməmişdi

Root Cause: Schema migration proseduru yetərince documented deyildi.

## Contributing Factors

## What Went Well

## What Could Have Gone Better

## Action Items
| Action | Owner | Due Date | Priority |
```

**Blameless culture:**
- "Kim dəyişiklik etdi?" deyil, "sistem bu dəyişikliyi necə keçirdi?"
- Engineer-lər qınaqlı olmasa dürüst danışır
- Öyrənmə fərsəti kimi baxılır

---

### Chaos Engineering Əlaqəsi:

Proaktiv incident simulation — failure-ı production-da gözləmədən əvvəl test et:
- Netflix Chaos Monkey: random instance termination
- Gremlin: controlled failure injection
- Məqsəd: MTTI azaltmaq — runbook-ları real scenario ilə yoxlamaq

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Böyük bir incident yaşadınızmı?" sualı gözlənilir. Hazır bir nümunə ilə gedin: problem nə idi, siz nə etdiniz, nəyi öyrəndiniz. "Incident commander olaraq team-i koordinasiya etdim, texniki detallara girməkdənsə stakeholder kommunikasiyasını idarə etdim" — Lead mindset-ini göstərir.

**Follow-up suallar:**
- "MTTD, MTTA, MTTF nədir?"
- "Blameless post-mortem niyə vacibdir?"
- "Incident commander-in texniki olmaq zorundamı?"

**Ümumi səhvlər:**
- Incident commander-ın özü fix-lə məşğul olması (coordination itir)
- Stakeholder kommunikasiyasını unutmaq
- Post-mortem etməmək — öyrənmə fürsəti qaçırılır
- Mitigation ilə fix-i qarışdırmaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Incident zamanı hər şeyi özüm etdim" vs "Incident commander olaraq team-i koordinasiya etdim — texniki analizi həmkarlara verdim, özüm stakeholder kommunikasiyasını idarə etdim." İkincisi liderlik göstərir.

## Nümunələr

### Tipik Interview Sualı
"Bir Production incident nümunəsi danışın. Siz nə etdiniz?"

### Güclü Cavab
"Bir nahar saatında payment service-in error rate-i qəfil 15%-ə çıxdı. Alert gəldi, mən on-call idim. Dərhal acknowledge etdim, Slack-da #incident channel açdım, SEV-1 elan etdim. Backend lead-i, DB adminı çağırdım — hamını bridge-ə topladım. Mən koordinasiyaya baxdım — texniki araşdırmanı backend lead apardı. 10 dəqiqədə root cause: yeni migration table lock yaratmışdı. Rollback etdik — 5 dəqiqədə bərpa. Stakeholderlara hər 10 dəqiqədə update verdim. Həll sonrası post-mortem keçirdik: migration runbook yeniləndi, pt-osc proseduru əlavə edildi, staging-də performance test tələbi gəldi."

## Praktik Tapşırıqlar
- Team üçün incident severity matrix yaz
- Mövcud bir runbook yaz ya da mövcudu update et
- Post-mortem template hazırla
- Bir "game day" — simulated incident keçir, MTTD/MTTA/MTTI ölç

## Əlaqəli Mövzular
- [06-oncall-best-practices.md](06-oncall-best-practices.md) — On-call incident-ı detect edir
- [04-observability-pillars.md](04-observability-pillars.md) — Incident-ı araşdırmaq üçün alətlər
- [05-sla-slo-sli.md](05-sla-slo-sli.md) — Incident SLO-ya necə təsir edir
