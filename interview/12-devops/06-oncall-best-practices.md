# On-Call Best Practices (Lead ⭐⭐⭐⭐)

## İcmal
On-call — production sistemlərinin gündə 24 saat, həftədə 7 gün izlənməsi üçün növbə sistemidir. On-call keçidi developer-in texniki bacarığını deyil, sistemi tanıma, incident-ı tez həll etmə, team-ə kömək etmə kabiliyyətini sınayır. Lead səviyyəsindəki developer on-call prosesini yalnız şəxsi olaraq yerinə yetirmir — team üçün sistemin etibarlılığını artıran strukturu qurur.

## Niyə Vacibdir
On-call prosesini yaxşı qurmamaq developer burnout-una, yanlış alertlərə, uzun MTTR-ə (Mean Time To Recovery) və team moralinə ciddi zərər verir. "Gecə yarısı gözlənilməz alert, 2 saat mübarizə, root cause tapılmadı" — bu həm problemi həll etmir, həm sistemi pis qurmağın nəticəsidir. Lead developer kimi alert keyfiyyəti, runbook-lar, escalation policy, blameless post-mortem — bunları qurmaq sizin öhdəliyinizdir.

## Əsas Anlayışlar

- **On-Call Rotation növləri**: Follow-the-sun — müxtəlif timezone-larda team-lər, hər kəs iş vaxtında on-call. Weekly rotation — bir həftə bir nəfər. Primary + Backup — iki nəfər eyni anda, primary cavab verir. Sağlam rotation: iş saatı xaricindəki alert/həftə < 2.

- **Alert keyfiyyəti kriteriyaları**: Alert yalnız insan müdaxiləsi lazımdırsa göndərilir. Urgentdir (indi düzəltmək lazımdır). Actionable-dır (bəlirli bir addım var). Bu üç şərt yerinə yetirilmirsə — alert lazım deyil.

- **Alert Anti-pattern-lər**: Noise alert — hər 5 dəqiqə CPU 70% üçün alert, developer ignore edir. Non-actionable — "Disk 80%" — nə etmək lazım? Runbook yoxdur. Duplicate — eyni problem 5 alert göndərir.

- **Alert Tiering (Severity)**: P1 (Critical) — production tamamilə çökür, payment dayanıb, data corruption. PagerDuty + SMS + Phone. P2 (High) — feature degraded, error rate artıb. PagerDuty + Slack. P3 (Medium) — non-critical threshold. Slack only. P4 (Low) — trend narahat, urgentliyi yox. JIRA ticket.

- **MTTR (Mean Time To Recovery)**: Incident başlanğıcından həll edilənə qədər ortalama vaxt. Yaxşı on-call process MTTR-i azaldır. Runbook olmayan sistemdə MTTR yüksəkdir. MTTR < 30 dəqiqə — yaxşı. MTTR > 2 saat — prosesi araşdır.

- **MTTA (Mean Time To Acknowledge)**: Alert-dən acknowledge-a qədər vaxt. MTTA > 5 dəqiqə = severity yanlış qurulmuş (ya da developer uyuyur). P1 üçün MTTA < 2 dəqiqə hədəf.

- **Runbook (Əməliyyat Kitabçası)**: Incident zamanı izlənəcək addım-addım kəlavuz. Hər alert üçün bir runbook. Runbook olmayan alert yaratmaq — yanlış praktika. Yaxşı runbook: "Nə baş verdi", "İlk 5 dəqiqə", "Diagnoz", "Həll addımları", "Escalation", "Post-incident".

- **Blameless Post-Mortem**: "Kim etdi?" deyil, "sistem bu xətanın baş verməsinə niyə icazə verdi?" məqsədi. Sistemi günahlandır, insanı deyil. Action item-lar konkret, assignee-li, deadline-li olmalıdır. Google SRE-nin əsas praktikasıdır.

- **Incident Severity Classification**: SEV-1: Tam outage, bütün istifadəçilər. SEV-2: Major degradation, çoxlu istifadəçi. SEV-3: Minor degradation, az istifadəçi, workaround var. SEV-4: Monitoring anomaliyası, business impact yox.

- **Incident Response Flow**: Alert → Acknowledge → Severity müəyyənləşdir → SEV-1/2: Incident channel aç → Diagnose (Runbook izlə) → Mitigate → Root cause → Fix → Post-mortem.

- **Alert Quality Metrics**: Alert/həftə — total. Alert/həftə — actionable olanların payı. Yanlış alarm oranı. MTTA ortalama. Bu metrikalar izlənmədikdə alert noise artır.

- **On-Call Burnout Profilaktikası**: Alert quality review — hər ayın sonunda. Toil budget — on-call zamanı 50%-dən çox manual iş = automation lazımdır. Runbook güncəlləmə — hər incident sonrası. Handoff ritual — əvvəlki nöbəti brief etmək. Compensation — iş saatı xaricindəki on-call-a uyğun tənzimləmə.

- **PagerDuty / OpsGenie Escalation Policy**: Level 1 — Primary on-call (5 dəqiqə cavab). Level 2 — Backup on-call (10 dəqiqə). Level 3 — Tech Lead (15 dəqiqə). Level 4 — Engineering Manager (son çarə). Bu sıra izlənmədikdə manager-lər hər incident-ı idarə edir — burnout.

- **On-Call Handoff**: Nöbət keçərkən: cari incident-lar, gedən işlər, potensial risk olan değişiklikler, gözlənilən trafik spike-ları. Verbal briefing + yazılı handoff not. Bu olmadan on-call person "blind" başlayır.

- **Shadow On-Call**: Yeni developer-ların on-call öyrənmə prosesi. Əvvəlcə shadow — dinləyir, görür. Sonra primary — daha təcrübəli backup dəstəkləyir. Birbaşa P1-ə atmaq burnout yaradır.

- **Incident Communication**: SEV-1/2 zamanı status page güncəllənir. Stakeholder-lara 15 dəqiqədə update. "Araşdırıram" — ən azı 30 dəqiqə bir update. Incident özü həll edilməmişdən əvvəl kommunikasiya itirilməməlidir.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"On-call experience-iniz nədir?" sualına sadəcə "keçdi, hallandım" demə. Alert keyfiyyəti, runbook, post-mortem, burnout prevention haqda danış. "İlk on-call həftəmdə 20+ alert aldım, 15-i noise idi — alert review keçirib 5-ə endirdik" — əla nümunədir.

**Junior-dan fərqlənən senior cavabı:**
Junior: "Alert gelir, baxıram, həll edirəm."
Senior: "Alert aldıqda əvvəlcə acknowledge, sonra runbook-u açıb diagnoz addımlarını izləyirəm. Əgər 30 dəqiqədə həll edilmirsə escalate edirəm."
Lead: "Team-in on-call prosesini qurmuşam. Alert quality review ayda bir keçirik. Runbook olmayan alert sistemə əlavə edilmir. Blameless post-mortem culture yaratmışam."

**Follow-up suallar:**
- "Gece yarısı alert alırsınız. İlk 5 dəqiqədə nə edirsiniz?"
- "Yanlış alert yarandığında nə edirsiniz?"
- "Post-mortem necə aparırsınız?"
- "On-call burnout necə qarşısını alırsınız?"
- "Runbook-u kim yazır, kim güncəlləyir?"

**Ümumi səhvlər:**
- Alert-ları runbook-suz saxlamaq — developer hər incident-da həll yolunu sıfırdan axtarır
- "Kim etdi?" fokuslu post-mortem — culture-u poza bilər, problem həll edilmir
- Alert noise-u ignore etmək — "alert fatigue" → real incident ignore edilir
- On-call öyrənilənləri heç bir yerdə qeyd etməmək — eyni incident növbəti ay yenilənir
- Runbook olmayan alert yaratmaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Blameless post-mortem konseptini, alert quality metrics-ni, toil reduction anlayışını bir arada izah etmək. "Runbook olmayan alert sistemə əlavə etmirəm — əvvəlcə runbook yazılır, sonra alert aktiv olur" — bu maturite göstərir.

## Nümunələr

### Tipik Interview Sualı
"On-call zamanı gece yarısı alert alırsınız. İlk 5 dəqiqədə nə edirsiniz?"

### Güclü Cavab
"Əvvəlcə alert-ı acknowledge edirəm — PagerDuty-da '5 dəqiqə cavab' SLA var. Sonra severity-ni qiymətləndirirəm: tam outage mu, yoxsa partial degradasiya? Dashboard-u açıb current error rate-ə, response time-a baxıram. Əgər SEV-1 isə — dərhal `#incidents` Slack channel-ı açıram, 'Araşdırıram' mesajı atıram, stakeholder-ları xəbərdar edirəm. Runbook-u açıb diagnoz addımlarını izləyirəm: son deployment varmı, DB bağlantısı normal mı, external service status. Rollback mümkünsə — dərhal edirik, bərpa olduqdan sonra root cause araşdırıram. 30 dəqiqə içində həll edilməsə — escalate edirəm. Post-incident-də runbook-u yeniləyirəm."

### Runbook Nümunəsi

```markdown
# Runbook: HighErrorRate5xx
# Alert-ID: ALT-0042
# Son güncəllənmə: 2025-04-01 @backend-lead

## Alert Nə Deməkdir
Production API-nin 5xx error rate-i son 5 dəqiqədə 1%-dən yüksəkdir.
Threshold: `job:http_request_error_rate:5m > 0.01`
Severity: P2 (əgər payment endpoint-lər də təsirlənibsə → P1)

## İlk 5 Dəqiqə
1. **Acknowledge** et — PagerDuty-da
2. **Dashboard-u** aç: [SLO Dashboard](https://grafana/slo)
3. Error rate nə qədər? 1-5% mi, 50%+ mi?
4. Hansı endpoint-lər error verir?
   ```bash
   kubectl logs -l app=api --since=10m | grep "HTTP 5" | \
     awk '{print $7}' | sort | uniq -c | sort -rn | head -10
   ```
5. **Son deployment** varmı?
   ```bash
   kubectl rollout history deployment/api --namespace=production
   ```

## Diagnoz Ağacı

### Son 30 dəqiqədə deployment var idisə:
```bash
# Deployment log-larını yoxla
kubectl get events --namespace=production --sort-by='.lastTimestamp' | tail -20

# Rollback seçimi
kubectl rollout undo deployment/api --namespace=production
# Rollback effektini izlə: error rate azalırmı?
```

### DB bağlantısı problemi:
```bash
# DB ping
kubectl exec -it deployment/api -- php artisan db:ping

# Connection pool doluluğu
kubectl exec -it deployment/api -- php artisan tinker --execute \
  "echo DB::select('SHOW STATUS LIKE \"Threads_connected\"')[0]->Value;"

# PgBouncer pool status
psql -h pgbouncer -p 5432 pgbouncer -c "SHOW POOLS;"
```

### Memory/CPU limit:
```bash
kubectl top pods --namespace=production --sort-by=memory
# Pod memory limitə yaxındırsa → restart ya da scale out
kubectl scale deployment/api --replicas=5 --namespace=production
```

### External service status:
- Stripe: https://status.stripe.com
- SendGrid: https://status.sendgrid.com
- AWS: https://health.aws.amazon.com

## Həll Addımları

**Scenario: High DB Connection Count**
```bash
# Long-running transaction-ları tap
psql -h production-db -c "
  SELECT pid, state, query_start, query
  FROM pg_stat_activity
  WHERE state != 'idle'
    AND query_start < now() - interval '1 minute'
  ORDER BY query_start;"

# Blocking query-ni kill et (ehtiyatla!)
# SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE pid = <pid>;
```

**Scenario: Memory Leak (Soak issue)**
```bash
# PHP-FPM process-lərini restart et (graceful)
kubectl rollout restart deployment/api --namespace=production
```

## Eskalasiya
- 15 dəqiqə həll edilməsə → `@tech-lead` ping et
- 30 dəqiqə → P1-ə upgrade, Engineering Manager xəbərdar
- Payment endpoint-lər təsiriənibsə → anında P1

## Post-Incident
- [ ] Incident document dolduр: [Template](https://wiki/incident-template)
- [ ] Root cause 24 saat içindən yazılmalıdır
- [ ] Runbook update lazımdırmı? Yeni diagnoz addımı?
- [ ] Monitoring gap varmı? Alert güncəlləmə lazım?
```

```markdown
# Blameless Post-Mortem Template

## Incident: Payment Service Partial Outage
**Tarix:** 2025-04-22, 14:30–15:15 UTC (45 dəqiqə)
**Severity:** SEV-1 → SEV-2 (14:50-dən)
**Xülasə:** Payment API 45 dəqiqə yüksək error rate yaşadı.
~1,200 istifadəçi checkout xətası gördü. ~$45k revenue impact.

---

## Timeline
| Vaxt (UTC) | Hadisə |
|-----------|--------|
| 14:28     | DB migration başladı (production-da) |
| 14:30     | Payment API error rate 5%-ə qalxdı |
| 14:31     | PagerDuty alert (P1) |
| 14:33     | On-call acknowledge etdi |
| 14:38     | Root cause tapıldı: FK constraint migration → table lock |
| 14:50     | Severity P2-yə endirildi (partial mitigation) |
| 14:55     | Migration rollback qərarı |
| 15:00     | Payment service tam bərpa |
| 15:15     | Monitoring normal, all-clear |

---

## Root Cause
Production-da `orders` cədvəlinə FK constraint əlavə edən migration
canlı traffic altında table lock yaratdı. Lock 45 dəqiqə saxlandı.

---

## Contributing Factors
1. Migration staging-də test edildi, amma **peak traffic altında deyil**
2. Migration runbook-da **lock-free migration texnikası** göstərilməyib
3. **Migration başlanğıcı üçün monitoring yox idi** — alert 2 dəqiqə gec gəldi
4. Production-da migration **deploy pipeline-dan kənar** çalışdırıldı

---

## Action Items
| # | Tapşırıq | Sahibi | Deadline |
|---|---------|--------|---------|
| 1 | Migration runbook-a lock-free texnikası əlavə et (pt-online-schema-change) | @backend-a | 2025-04-29 |
| 2 | Staging-də peak load altında migration sınağı məcburi et | @platform | 2025-05-06 |
| 3 | DB migration monitor alert qur (migration başladıqda notify) | @infra | 2025-04-25 |
| 4 | Production migration → deploy pipeline-a inteqrasiya et | @platform | 2025-05-13 |

---

## Blameless Note
Bu incident heç bir şəxsin səhvi deyil. Prosesimiz migration-ların
production-da necə davrandığını tam test etmirdi. Bu boşluğu bağlayırıq.
```

```yaml
# PagerDuty — Escalation Policy konfiqurasiyası (Terraform)
resource "pagerduty_escalation_policy" "backend_team" {
  name = "Backend Team On-Call"

  rule {
    escalation_delay_in_minutes = 5  # 5 dəq cavab gözlə
    target {
      type = "schedule_reference"
      id   = pagerduty_schedule.primary_oncall.id  # Primary
    }
  }

  rule {
    escalation_delay_in_minutes = 10  # 10 dəq sonra Backup
    target {
      type = "schedule_reference"
      id   = pagerduty_schedule.backup_oncall.id   # Backup
    }
  }

  rule {
    escalation_delay_in_minutes = 15  # 15 dəq sonra Tech Lead
    target {
      type = "user_reference"
      id   = pagerduty_user.tech_lead.id
    }
  }

  rule {
    escalation_delay_in_minutes = 20  # Son çarə: EM
    target {
      type = "user_reference"
      id   = pagerduty_user.engineering_manager.id
    }
  }
}

# Alert routing — severity-ə görə
resource "pagerduty_service" "payment_api" {
  name                    = "Payment API"
  escalation_policy       = pagerduty_escalation_policy.backend_team.id
  alert_creation          = "create_alerts_and_incidents"

  incident_urgency_rule {
    type = "use_support_hours"
    during_support_hours {
      type    = "constant"
      urgency = "high"   # İş saatında P1/P2 high urgency
    }
    outside_support_hours {
      type    = "constant"
      urgency = "high"   # Gecə yalnız genuinely critical
    }
  }
}
```

```bash
# On-Call Metrics — Alert quality izlə
# weekly cron job

#!/bin/bash
# Həftəlik alert quality report

START=$(date -d '7 days ago' --iso-8601=seconds)
END=$(date --iso-8601=seconds)

echo "=== On-Call Alert Quality Report: $(date) ==="
echo ""

# PagerDuty API-dən alert-ları al (simplifikasiya)
ALERTS=$(curl -s -H "Authorization: Token token=$PAGERDUTY_TOKEN" \
  "https://api.pagerduty.com/incidents?since=$START&until=$END&limit=100")

TOTAL=$(echo $ALERTS | jq '.total')
ACTIONABLE=$(echo $ALERTS | jq '[.incidents[] | select(.urgency == "high")] | length')
NOISE=$(echo $ALERTS | jq '[.incidents[] | select(.status == "resolved" and (.last_status_change_at | . < (.created_at | . + 300)))] | length')
# 5 dəqiqə içindən auto-resolve = noise

echo "Total alerts:     $TOTAL"
echo "Actionable:       $ACTIONABLE"
echo "Noise (auto-res): $NOISE"
echo "Quality ratio:    $(echo "scale=1; $ACTIONABLE * 100 / $TOTAL" | bc)%"

# Threshold: quality < 80% ise Slack-a notify
if [ $(echo "$ACTIONABLE * 100 / $TOTAL" | bc) -lt 80 ]; then
    echo "WARNING: Alert quality below 80% — review needed!"
fi
```

### Müqayisə Cədvəli — On-Call Sağlamlıq Göstəriciləri

| Metrika | Sağlam | Narahat | Kritik |
|---------|--------|---------|--------|
| İş saatı xaricindəki alerts/həftə | < 2 | 2-10 | > 10 |
| Alert quality (actionable %) | > 80% | 50-80% | < 50% |
| MTTR (P1) | < 30 dəq | 30-60 dəq | > 1 saat |
| MTTA (P1) | < 2 dəq | 2-5 dəq | > 5 dəq |
| Runbook coverage | 100% | 80-100% | < 80% |
| Post-mortem completion | 100% P1, 90% P2 | 80% | < 80% |

## Praktik Tapşırıqlar

1. Mövcud bir alert üçün runbook yaz — "nə baş verdi", "ilk 5 dəqiqə", "diagnoz", "həll addımları", "escalation".
2. Son bir incident üçün blameless post-mortem sənədi hazırla. Action item-lar konkret, assignee-li, deadline-li.
3. Team-in alert noise ratio-sunu ölç: son 2 həftəki alert-lara bax, kaçı actionable idi?
4. Escalation policy PagerDuty/OpsGenie-də konfiqurasiya et — 4 səviyyəli.
5. On-call handoff template yaz: cari incident-lar, potensial risk, keçən həftənin öyrənciləri.
6. Shadow on-call prosesi qurun: yeni developer-lar əvvəlcə shadow, sonra backup, sonra primary.
7. Alert quality metrics-ni izlə: weekly cron job ilə hesabla, Slack-a göndər.
8. Runbook olmayan alert tapın — 30 dəqiqə içindən runbook yaz, sonra alert-ı aktiv et.

## Əlaqəli Mövzular

- [07-incident-response.md](07-incident-response.md) — On-call-ın əsas prosesi
- [04-observability-pillars.md](04-observability-pillars.md) — On-call zamanı istifadə olunan alətlər
- [05-sla-slo-sli.md](05-sla-slo-sli.md) — On-call SLO pozulmasına cavab verir
