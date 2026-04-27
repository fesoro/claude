# On-Call and Incident Handling (Senior ⭐⭐⭐)

## İcmal

On-call və incident handling sualları interviewerin production sistemlərini necə idarə etdiyinizi, yüksək təzyiq altında necə düşündüyünüzü, və post-incident prosesini necə apardığınızı başa düşmək üçün verilir. "Production-da incident olduqda nə edirsiniz?", "Ən ciddi outage-i danışın" kimi suallar bu kateqoriyaya aiddir.

Senior developer-lər üçün bu bacarıq texniki biliklə yanaşı communication, prioritization, blameless culture, və sistemli düşüncə tərzi deməkdir.

Incident handling-in iki faydası var: birincisi — sistemi bərpa etmək. İkincisi — eyni incident-in bir daha baş verməməsini təmin etmək. İkinci hissəni unudan namizəd "reaktiv" görünür.

---

## Niyə Vacibdir

Production sistemlər düşər, bu qaçılmazdır. İnterviewerlər bilmək istəyir ki, siz panic etmədən, sistematik olaraq problemi lokallaşdıra bilirsiniz, düzgün insanları cəlb edirsiniz, və nəticədə həm incident-i həll edir, həm də recurrence-ı önləyirsiniz.

Pis incident handling şirkətə milyonlarla dollar ziyan vura bilər, müştəri inamını sarsıda bilər. On-call culture-u yaxşı idarə edən engineer-lər şirkətlər üçün çox dəyərlidir.

---

## Əsas Anlayışlar

### 1. İnterviewerin həqiqətən nəyi ölçdüyü

- **Calm under pressure** — panic etmədən düşünmək, sistematik addımlar
- **Systematic troubleshooting** — gut feeling deyil, data-driven: logs, metrics, traces
- **Communication** — stakeholder-ləri real-time update etmək, düzgün kanal seçmək
- **Escalation judgment** — nə vaxt tək getmək, nə vaxt help çağırmaq
- **Rollback vs fix-forward qərarı** — nə zaman rollback, nə zaman fix?
- **Blameless post-mortem** — insanı deyil, prosesi düzəltmək
- **Prevention mindset** — bu incident bir daha baş verməsin
- **Runbook culture** — documented response, ad-hoc yox

### 2. Red flags — zəif cavabın əlamətləri

- "Mən tək başıma həll etdim, heç kimi çağırmadım" — hero mentality, transparency yoxdur
- "O developer-in günahı idi" — blame culture
- Post-mortem-siz incident bağlamaq — learn etmə yoxdur
- Alert-ləri ignore etmək, "özü keçər" düşünmək
- Metrics/logs olmadan "hər şey qayıdacaq" demək — intuition-la troubleshoot
- "Heç büyük incident olmamışdır" — ya hekayəsizlik, ya monitoring yoxluğu

### 3. Green signals — güclü cavabın əlamətləri

- İlk 5 dəqiqədə nə etdiniz — konkret, artıcıllıqlı addımlar
- Runbook/playbook istifadəsi — documented response
- Rollback vs fix-forward qərarını niyə verdiniz?
- Stakeholder communication template — kim, nə vaxt, hansı kanal
- Post-mortem action items-ın follow-up-u
- Dollar impact-ı hesablamaq — "hər dəqiqə $X"
- MTTR (Mean Time to Resolve) bilmək

### 4. Şirkət tipinə görə gözlənti

| Şirkət tipi | Fokus |
|-------------|-------|
| **Revolut, Wolt, Booking** | High-availability, SLA/SLO awareness, on-call rotation |
| **Stripe, Shopify** | Blameless culture, post-mortem quality, prevention |
| **Startup** | Resource-limited recovery, creative mitigation |
| **Enterprise/Bank** | Escalation protocol, compliance, audit trail |

### 5. Seniority səviyyəsinə görə gözlənti

| Səviyyə | Incident role |
|---------|--------------|
| **Senior** | First responder, troubleshoot, escalate if needed |
| **Lead** | Incident commander, coordinate team, stakeholder comm |
| **Staff** | Sistemik prevention, SLO design, chaos engineering |

### 6. MTTR-nin önəmi

Mean Time to Resolve (MTTR) — industry standard metrikdir:
- World-class: <10 dəqiqə
- Good: 10-30 dəqiqə
- Acceptable: 30-60 dəqiqə
- Poor: >60 dəqiqə

Hekayənizin MTTR-ni bilmək professional görünüş verir.

### 7. Blameless postmortem culture

Google, Netflix, Stripe bunu standard edib. Əsas prinsip: heç kim qəsdən sistemi çökdürmür. Hər incident sistem problemidir — insanın deyil. Bu mindset-i interview-da göstərmək çox güclü signal-dır.

---

## Praktik Baxış

### Cavabı necə qurmaq

Incident hekayəsi "dramadan" başlasın: "Cümə axşamı gecə saat 23:47-də alert gəldi." Sonra sistematik troubleshooting-i göstər. Final-da blameless post-mortem — bu sizi digər namizədlərdən fərqləndirir.

### Incident response framework (5 mərhələ)

```
1. ASSESS (0–5 dəq)   — impact anla, komandanı xəbərdar et
2. INVESTIGATE (5–15 dəq) — logs, metrics, recent changes
3. MITIGATE (15–20 dəq)  — rollback ya quick fix, user impact azalt
4. RESOLVE (20–60 dəq)   — kök səbəbi düzəlt ya confirm et
5. FOLLOW-UP (24 saat)   — post-mortem, action items
```

### Rollback vs Fix-forward qərarı

| Rollback | Fix-forward |
|----------|-------------|
| Root cause aydın deyil | Root cause çox aydındır, fix 5 dəqiqədir |
| Risk yüksəkdir | Risk azdır |
| Recent deploy olub | Deploy yoxdur, infra problemi |
| Data corruption riski var | Sadə config dəyişikliyi |

### Stakeholder communication template

```
🔴 INCIDENT STARTED: [saat]
Service: [service adı]
Impact: [% users affected, konkret nə olur]
On-call: [ad]
Status: investigating
Next update: 15 minutes
```

### Tez-tez soruşulan follow-up suallar

1. **"How do you prepare for on-call rotation?"** — "Runbook-ları oxuyuram, son 2 həftənin alert-lərini gözden keçirirəm, service dependency map bilirem"
2. **"What's your escalation decision process during an incident?"** — "İlk 15 dəqiqədə tək troubleshoot. Root cause görünmürsə — ikinci nəfər cəlb. SLA risk varsa — manager xəbərdar"
3. **"How do you write a blameless post-mortem?"** — "5 Why analizi. Timeline: nə baş verdi. Root cause: sistem problemi. Action items: müddəti olan, owner-i olan tasks"
4. **"What's the difference between an incident and a bug?"** — "Incident: aktiv production impact, real-time response lazım. Bug: gələcəkdə fix ediləcək problem"
5. **"How do you prioritize fixes after an incident — immediate vs long-term?"** — "İmmediately: mitigation. Sprint-ə: root cause fix. Sonra: systemic prevention (monitoring, runbook, alert tuning)"
6. **"Have you ever triggered an incident yourself?"** — Honest cavab: "Bəli — [nümunə]. Dərhal bildirdim, rollback etdim. Blameless postmortem yazdım."
7. **"How do you manage on-call fatigue?"** — "Runbook-lar false positive alert-ləri azaldır. Rotation ədalətlidir. Post-mortems noise-i azaldır"

### Nə deyilməsin

- "Kiminsə günahı idi" — blameless culture-u bilmirsiniz
- "Biz heç vaxt outage olmaz sistemi qurmuşuq" — hər sistem düşər
- "Post-mortem etmədik, vaxt yox idi" — öyrənmə yoxdur
- "Tək həll etdim, kimə deyib narahat edim" — transparency yoxdur

---

## Nümunələr

### Tipik Interview Sualı

"Tell me about a production incident you handled. Walk me through your response from alert to resolution."

---

### Güclü Cavab (STAR formatında)

**Situation:**
Cümə axşamı gecə saat 23:47-də PagerDuty alert-i gəldi: "Payment service — error rate 45%, P99 latency 8,000ms (normal: 200ms)." Mən on-call engineer idim. E-commerce platformumuzda aylıq 2M$ payment prosessing olurdu — hər dəqiqəlik outage ~$4,000 itkisi demək idi. Həmin gün saat 23:15-də yeni deploy olmuşdu. Şirkət FinTech startup idi — reputasiya kritik idi, hər outage müştərilərin sosial mediada yazmasına çevrilirdi.

**Task:**
Mənim vəzifəm: incident-i qısa müddətdə resolve etmək, müştəri impact-ı minimuma endirmək, stakeholder-ləri informed saxlamaq. Rollback lazım ola bilərdi — bu birbaşa mənim qərarım idi.

**Action:**

**Dəqiqə 0–5 (Assess & Communicate):**
Slack-də `#incidents` channel açdım:
```
🔴 INCIDENT STARTED: 23:47
Service: payment-service
Impact: ~45% payment requests failing
On-call: Orkhan
Status: investigating
Next update: 10 minutes
```
"Hero silently fixes" deyil, "team knows" yanaşması ilə başladım. CTO-nu da mention etdim — gecə olsa da, aylıq $4,000/dəq impact olan incident senior leadership-i xəbərdar etməyi tələb edirdi.

**Dəqiqə 5–15 (Investigate):**
Datadog-a girdim. Əsas müşahidələr:
- 500 Internal Server Error-lar — database timeout
- DB metrics: connection pool 100%, query queue artıb
- Deploy timeline: incident saat 23:15 deploy-dan 32 dəqiqə sonra başlamışdı
- Changelog: "add eager loading to order queries" — `with(['items', 'customer', 'shipping'])` əlavəsi

Correlation çox aydın idi: deploy ilə incident eyni timeframe-də. Şübhəli kod: bütün order query-lərini eager load etmişdi, yüksək concurrent request-də hər query 4 JOIN yaradırdı, connection pool tükəndi.

**Dəqiqə 15–20 (Mitigation decision):**
Rollback vs fix-forward: root cause şübhəli idi amma confirm olunmamışdı. Fix 5 dəqiqəyə olmurdu — `with()` selective olmalıydı amma hansı endpoint-lər etkilənir bilmirdim. Rollback qərarı verdim:
```bash
kubectl rollout undo deployment/payment-service
# 90 saniyə sonra pod-lar ready
```

**Dəqiqə 22:**
Error rate 0%-ə düşdü. Slack update:
```
✅ MITIGATED: 00:09
Rollback applied. Error rate normalized.
Root cause: suspected eager loading in v2.3.1
RCA to follow. Monitoring 30 minutes.
```

**Dəqiqə 22–50 (Monitor & RCA):**
30 dəqiqə monitoring etdim — stable. Log-lardan root cause-u confirm etdim: `with(['items', 'customer', 'shipping'])` hər query-də 4 JOIN, yüksək concurrency-də pool bitdi. Eager loading selective olmalıydı, qlobal deyil.

**Sabah (Post-mortem):**
Blameless post-mortem document yazdım: timeline, root cause, impact hesabı (~$12,000 — 3 dəqiqə outage), action items:
1. Staging-də load test əlavə et — connection pool spike-larını yaxala
2. DB connection pool alerting threshold aşağı sal
3. Eager loading review — selective loading pattern establish et
4. "5 o'clock rule": production deploy-lar yalnız iş saatlarında (9–17)

**Result:**
Incident 22 dəqiqəyə həll olundu (SLA: 30 dəqiqə). Post-mortem 3 gündə tamamlandı, bütün action items 2 sprint-də implement edildi. Staging-ə load test əlavə edildi — oxşar incident 4 ay sonra staging-də tutuldu, production-a çıxmadı. "5 o'clock rule" şirkətin deployment policy-sinə daxil edildi. MTTR 22 dəqiqə — team average 45 dəqiqə idi, yəni bu incident best-in-class response idi.

---

### Alternativ Ssenari — Redis cluster failure

Redis cluster failover zamanı caching layer tamam çökdü. Bütün request-lər birbaşa DB-ə düşdü, DB CPU 100%-ə çatdı. Mitigation sırası: circuit breaker açdım → non-critical endpoint-ləri temporary 503 qaytar → Redis-i manual restart → DB connection pool-u temporarily artır → Redis sentinel config-i yenidən enable et. 8 dəqiqə partial outage, 35 dəqiqə tam recovery.

Post-mortem: Redis sentinel config bir əvvəlki deploy zamanı comment olunmuşdu (debug üçün) — unkomment olunmamışdı. Action item: sentinel config-in test-i deployment pipeline-a daxil edildi. Bu incident-dən "sentinel health check" CI/CD-yə əlavə edildi.

---

### Zəif Cavab Nümunəsi

"Production-da heç böyük incident olmamışıq, sistemimiz çox sabitdir. Kiçik problemlər olurdu, özüm həll edirdim, heç kimə demirdim ki, panikamasınlar."

**Niyə zəifdər:** "Heç incident olmamışıq" — ya hekayəsizlik, ya da monitoring yoxluğunu göstərir. "Kimə isə demirdim" — transparency yoxluğu, hero mentality. İkisi də red flag. Bu cavab on-call culture-u bilmədiyini göstərir. "Panikamasınlar" — stakeholder communication-ı yüklülük kimi görürsünüz.

---

## Praktik Tapşırıqlar

1. **Incident hekayəsi hazırla:** Keçmişdəki ən böyük production problemini STAR formatında yaz. Mütləq: timeline, mitigation addımları, root cause, post-mortem action items. Rəqəmlər: outage duration, user impact, dollar impact.

2. **Runbook yaz:** Öz servisin üçün minimal runbook hazırla: "alert X gəldikdə — ilk 3 addım." Interview-da "runbook culture-unu maintain edirəm" mention etmək güclü signal-dır.

3. **Məşq sualı:** "Database CPU is at 100%, users can't log in, it's Friday 6pm. You are on-call. What do you do in the first 10 minutes?" — 10 addımlı konkret cavab hazırla.

4. **Post-mortem template öyrən:** Google SRE handbook-dakı blameless postmortem template-ini oxu. Bir previous incident-ə tətbiq et. 5 Why analizi əlavə et.

5. **Rollback decision tree:** "Rollback vs fix-forward" qərarı üçün özünüzün decision tree-sini çəkin. Hansı şərtlərdə rollback, hansı şərtlərdə fix-forward seçərsiniz? Bu structured thinking göstərir.

6. **Alert triage:** Şirkətinizdə alerting varsa, son 3 alert-in triage-ini yazın: "alert gəldi → nə baxdım → nə qərara gəldim." Bu reallığı göstərir.

7. **MTTR hesabla:** Son 3-4 incident-inizin MTTR-ni hesablayın. Bu metrik interview-da "bizim average MTTR X idi, mənim resolve etdiyim incident-lərdə Y idi" kimi mention edilə bilər.

8. **"Blame" anını düzəlt:** Bir keçmiş incident-i götürün. "X-in günahı idi" deyə başlayan post-mortem-i blameless versiyaya çevirin. Bu mindset dəyişikliyi interview-da görünür.

---

## Ətraflı Qeydlər

### Incident severity classification

```
SEV-1 (Critical): Production tamam down, ya da core function işləmir
- Response: Dərhal, bütün hands-on
- Communication: CTO + CEO xəbərdar
- SLA: 15 dəq response, 1 saat resolution

SEV-2 (Major): Core function degraded, significant user impact
- Response: On-call engineer + team lead
- Communication: Engineering leadership
- SLA: 30 dəq response, 4 saat resolution

SEV-3 (Minor): Non-core feature affected, workaround mövcud
- Response: On-call engineer
- Communication: Team Slack channel
- SLA: 4 saat response, next business day resolution
```

### Blameless Postmortem şablonu

```markdown
## Incident Postmortem: [Başlıq]
**Tarix:** [Tarix]
**Müddət:** [Başlanğıc] — [Son]
**Severity:** SEV-X
**Author:** [Ad]

## Xülasə
[2-3 cümlə — nə baş verdi]

## Timeline
- HH:MM — [Hadisə]
- HH:MM — [Həll addımı]
...

## Root Cause
[5 Why analizi nəticəsi]

## Impact
- Users affected: X%
- Revenue impact: $Y
- Duration: Z dəq

## Action Items
| Task | Owner | Deadline |
|------|-------|----------|
| ... | ... | ... |

## Nə yaxşı getdi?
[Blameless culture — sistemi güclü edən şeylər]

## Nə yaxşı getmədi?
[Sistem yox, process boşluqları]
```

### On-call hazırlığı checklist

Hər on-call rotation-dan əvvəl:
```
□ Runbook-ları oxu — ən son versiyası?
□ Son 2 həftənin alert-lərini nəzərdən keçir
□ Service dependency map-i bil
□ Escalation contacts-ı yoxla
□ On-call telefon doluymu?
□ Local dev environment işləyirmi?
□ Access: production logs, metrics, DB read access
```

### Incident communication templates

**Initial alert:**
```
🔴 [INCIDENT STARTED] [HH:MM]
Service: payment-api
Impact: ~30% requests failing, checkout unavailable
On-call: @username
Investigating. Next update: 10 min.
```

**Mitigation update:**
```
🟡 [MITIGATED] [HH:MM]
Rollback applied. Error rate normalizing.
Root cause: suspected. RCA in progress.
Next update: 30 min.
```

**Resolution:**
```
✅ [RESOLVED] [HH:MM]
Duration: 22 min | Impact: ~$8K revenue
Root cause: eager loading connection pool exhaustion
Postmortem: [link] | Action items: 4 items, owners assigned
```

---

## Əlaqəli Mövzular

- `03-greatest-technical-challenge.md` — Texniki crisis-i həll etmək
- `07-failure-lessons.md` — Incident-dən öyrənmək
- `15-system-design-retrospective.md` — Sistem dizaynında reliability
- `08-estimation-planning.md` — Incident recovery planlaması
- `14-ambiguous-requirements.md` — Qeyri-müəyyən situasiyada qərar vermək
