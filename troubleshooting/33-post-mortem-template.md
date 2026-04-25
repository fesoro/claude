# Post-mortem Template

## Məqsəd
İncident-in yazılı qeydidir, həlldən sonra yayılır. Nə baş verdiyini, niyə, nə öyrəndiyimizi və nəyi dəyişəcəyimizi qeyd edir. Blameless. Konkret. Sahibli.

## Nə vaxt yazılmalı
- Hər SEV1 və SEV2
- Az qala eskalasiya olan istənilən SEV3
- Nəsə öyrədən istənilən near-miss
- Tələb ilə: müştəri RCA istədi, tənzimləyici tələb etdi, exec görünürlüyü

---

# Post-mortem: {INCIDENT NAME}

## Summary

**Bir abzas, exec-friendly.** Sadə dildə nə baş verdi, ümumi təsir, həll xülasəsi.

Nümunə:
> On 2026-04-17 between 14:32 and 14:58 UTC, approximately 10% of checkout requests returned HTTP 500. The cause was a null-pointer bug in the payments-service v2.5.0 deploy. The issue was mitigated by rolling back to v2.4.1. Approximately 1,200 users experienced a failed checkout; 450 retried successfully. Estimated revenue impact: $4,800. No data loss or security impact.

## Incident details

| Field | Value |
|-------|-------|
| Date | 2026-04-17 |
| SEV | SEV2 |
| Started | 14:32 UTC |
| Detected | 14:34 UTC (PagerDuty alert) |
| Acknowledged | 14:35 UTC |
| Mitigated | 14:58 UTC |
| Resolved | 15:10 UTC (monitoring clean) |
| Duration | 38 minutes (active) / 26 min (user-impact) |
| Incident commander | @orkhan |
| Scribe | @elena |
| Customers impacted | ~1,200 |
| Revenue impact | ~$4,800 |
| Data loss | None |
| Security impact | None |

## Impact

Rəqəmlərlə:
- ~1,200 user checkout POST-da 500 aldı
- 450 5 dəqiqə ərzində uğurla retry etdi
- ~750 səbəti tərk etdi
- Uğursuz charge yoxdur (fail payment processor-dan əvvəl baş verdi)
- Support ticket-ləri: 23
- Revenue: ~$4,800 təxmini itki

## Timeline (UTC)

Hər yerdə UTC istifadə et. Hər giriş: timestamp, kim, nə.

```
14:30  Deploy of payments-service v2.5.0 started (auto, merged PR #4521)
14:32  First 500 observed in logs (Datadog)
14:34  PagerDuty alert: HighErrorRate (threshold 2%)
14:35  @orkhan ack, opens #incident-20260417-checkout-500s
14:36  @orkhan posts first update, SEV2 declared
14:37  @elena joins, appointed scribe
14:38  @alice joins, appointed debug lead
14:39  Initial check of dashboards — confirms payments-service error spike
14:41  Correlates with deploy at 14:30
14:42  IC decides rollback; @alice runs kubectl rollout undo
14:44  Rollback in progress (rolling restart)
14:49  Rollback complete, error rate dropping
14:52  Error rate at 2% and falling
14:58  Error rate at 0.1%, mitigation confirmed
15:00  Status page: Monitoring
15:10  Error rate stable at 0%, IC declares resolved
15:15  Status page: Resolved
15:20  Post-mortem scheduled for 2026-04-19 14:00 UTC
```

## Root cause

Birbaşa səbəb və altda yatan amillər. Konkret ol.

**Direct cause**: In `PaymentsController::process()`, a new null-check was added for `$order->discount`, but the check assumed `$order->discount` was always either null or an object. In reality, legacy orders created before 2024-12-01 had `discount = []` (empty array). The null-check used `->amount` access on an array, throwing `TypeError`.

**Why it wasn't caught**:
- Unit tests covered null and object cases, not array
- Staging data had only post-2024 orders
- Canary ran 5% traffic for 15 min — enough to hit ~15 failures, but under the alert threshold

## Contributing factors

Əsas səbəb deyil, amma incident-i daha pis etdi və ya imkan verdi:
- Staging DB 3 ay əvvəl restore olundu; legacy data pattern-ləri yoxdur
- Canary alert threshold 5% idi, adi xəta dərəcəsi < 0.5% olduqda çox yüksəkdir
- Rollback manual idi (kubectl); mitigation-a ~2 dəq əlavə etdi
- Yeni null-check kod yolunda feature flag yox idi

## What went well

Bunu həmişə daxil et — həmişə. Moral və öyrənmə bundan asılıdır.

- Alert ilk xətadan 2 dəq sonra işə düşdü
- IC ack-dan 3 dəq sonra toplandı
- Doğru rollar dərhal təyin edildi
- Rollback qərarı tez verildi (ilk xətadan 10 dəq)
- Scribe-ın timeline-ı təmiz və tam idi
- Status page vaxtında yeniləndi
- Data itkisi və ya security təsiri yoxdur
- Support 5-ci dəqiqədə döngüyə qoşuldu

## What went poorly

- Canary threshold bunu tutmaq üçün çox yüksək idi
- Staging data təmsilçi deyildi
- Null-check fərziyyəsi review-da tutulmadı
- Rollback tam avtomatlaşdırılmamışdı
- Qeyri-mühəndis stakeholder-lərə daxili kommunikasiya daha aydın ola bilərdi

## Action items

SMART: Specific, Measurable, Assignable, Realistic, Time-bound.

| # | Action | Owner | Due | Priority |
|---|--------|-------|-----|----------|
| 1 | Add test fixtures with pre-2024 discount=[] orders | @alice | 2026-04-24 | High |
| 2 | Lower canary alert threshold from 5% to 1% | @bob | 2026-04-22 | High |
| 3 | Periodic refresh of staging DB from prod snapshot (monthly, anonymized) | @carlos | 2026-05-15 | Medium |
| 4 | Automate rollback in ArgoCD with metric-based abort | @david | 2026-05-30 | Medium |
| 5 | Add runbook entry for "payments-service 500s" | @orkhan | 2026-04-20 | Medium |
| 6 | Feature-flag all payment flow changes going forward | @team | 2026-04-30 | High |

Issue tracker-də (Jira / GitHub Issues / Linear) izlə. Bura link ver. Rüblük review-da tamamlanmanı yoxla.

## Lessons learned

Narrative düşüncə: bu incident bizə komanda olaraq nə öyrədir?

- Prod data-sına bənzəməyən staging data təkrarlanan risk mənbəyidir. Data parity-yə investisiya etməliyik.
- Canary threshold-ları xidmət başına tənzimlənməlidir; qlobal 5% çox kobuddur.
- Legacy data formaları (null gözlənilən yerdə boş array) fərz olunduğundan çox uzun yaşayır.
- Bizim rollback SLA-mız ~5 dəqiqədir; payment-kritik yollar üçün <1 dəqiqə olmalıdır.

## Related incidents

Oxşar mövzulu əvvəlki post-mortem-lərə link:
- [2025-11-12: Legacy field shape broke search](link)
- [2026-01-30: Canary missed auth regression](link)

## Appendix

- Datadog dashboard screenshot-ları
- Nümunə error trace
- Pis commit-in link-i
- Rollback commit-in link-i
- Slack kanal arxivi (saxlanıbsa)

---

## Meta: Post-mortem görüşünü necə aparmalı

**İştirakçılar**: IC, responder-lər, komanda lider-ləri, support/product-dan nümayəndə
**Vaxt**: həlldən 5 iş günü ərzində
**Müddət**: 60-90 dəq
**Facilitator**: IC və ya incident ilə əlaqəsi olmayan senior mühəndis

**Agenda**:
1. (5 dəq) Summary-ni səsli oxumaq
2. (15 dəq) Timeline-ı keçmək, qeyri-müəyyənlikləri aydınlaşdırmaq
3. (20 dəq) Root cause müzakirəsi — 5 Whys
4. (15 dəq) Nə yaxşı keçdi / nə pis keçdi
5. (15 dəq) Action items — commit, təyin, deadline
6. (5 dəq) 4 həftə sonra review check-in planlaşdır

**Əsas qaydalar**:
- Blameless: "niyə @X bunu etdi" yox — əvəzində "sistemimiz niyə X-ə icazə verdi"
- Konkret: "daha yaxşı test etməliyik" yox — əvəzində "Z tarixinə qədər Y fixture əlavə et"
- Səssiz fikir ayrılığı yoxdur: narahatçılıqları sonradan deyil, görüş zamanı gətir
- Qərarları aydın qeyd et

## Style guide

- Timeline üçün keçmiş zaman
- Həmişə UTC timestamp
- İzlənmə üçün Slack formatında ad (@handle)
- Metrikləri dəqiq (450 user, "çoxlu" deyil)
- Blame yoxdur, qəhrəman yoxdur — yalnız hərəkətlər və nəticələr
- İncident başına bir post-mortem; əlaqəli incident-lərə link
- Bütün mühəndislik üçün görünən yerdə publish et (Notion, Confluence) — basdırma

## Anti-pattern-lər

- Post-mortem-i 3 həftə gec yazmaq → detallar unudulur
- Owner və ya deadline olmadan action item-lər → heç vaxt edilmir
- Əvvəlki template-i yenidən yazmadan copy-paste etmək → təhqirdir
- Fərdləri günahlandırmaq → psixoloji təhlükəsizliyi öldürür
- "Nə yaxşı keçdi" yoxdur → moralı aşağı salır
- "Root cause: human error" → səbəb deyil, simptomdur

## Müsahibə bucağı

"Yazdığın bir post-mortem haqqında danış."

Güclü cavab:
- "Strukturlu: summary, UTC-də timeline, impact metrikləri, root cause, contributing factors, owner və deadline ilə action item-lər, lessons, və related incidents."
- "Hər yerdə blameless çərçivələmə. Sistem uğursuzluqları, insan uğursuzluqları deyil."
- "Həmişə 'what went well' bölməsi — morali saxlayır, güclü tərəfləri vurğulayır."
- "Action item-lər SMART-dır və həqiqətən baş versin deyə post-mortem sənədindən kənarda izlənir."
- "Nümunə: payment incident üçün post-mortem yazdım, root cause legacy data formasına qarşı null-check idi. Contributing factor staging data freshness idi. Action item-lərə aylıq prod snapshot staging-ə, canary threshold tənzimi, və gələcək payment dəyişikliklərinin feature-flag olunması daxil idi."

Bonus: action item-ləri 4 həftə sonra review etdiyini qeyd et ki, həqiqətən baş verdiyindən əmin olasan. Follow-through-suz post-mortem-lər teatrdır.
