# Writing Runbooks

## Məqsəd
Runbook — bilinən ssenari üçün əvvəlcədən yazılmış cavabdır. Yaxşı runbook-lar saat 3 panikasını 10 dəqiqəlik prosedura çevirir. Pis runbook-lar köhnədir və ya yoxdur, on-call-ı sıfırdan cavab icad etməyə məcbur edir.

## Nə vaxt runbook yazmalı
- Təkrarlanması ehtimalı olan hər incident-dən sonra
- Yüksək həcmli hər alert üçün
- Hər mürəkkəb prosedur üçün (deploy, failover, restore)
- Yeni komanda üzvü onboarding zamanı — "bunu saat 3-də ona necə izah edərdim?"

## Saat 3 testi

Hər runbook bundan keçməlidir: bu alert-i heç görməmiş yorğun mühəndis onu uğurla izləyə bilər?

Əgər runbook tələb edirsə:
- Söhbətdən konteksti bilmək
- Hansı mühit / cluster olduğunu anlamaq
- Hansı komandanı işlədəcəyini təxmin etmək
- 10 səhifəlik sənəd oxumaq
...saat 3 testindən keçmir.

## Tələb olunan struktur

```markdown
# Runbook: {Scenario Title}

## When you get this alert
Exact alert name, what dashboard shows, what Slack message indicates this scenario.

## First 5 minutes
1. Copy-paste commands
2. Expected output
3. Decision: mitigated or escalate

## If mitigated
Short-term fix applied. What to do next.

## If not mitigated
Escalation path: who to page, when.

## Deeper investigation
Once stable, investigate root cause with these commands.

## Related
Links to related runbooks, post-mortems, dashboards.

## Updated
Last reviewed: YYYY-MM-DD by @name
```

## Nümunə: Horizon queue backlog

```markdown
# Runbook: Horizon Queue Backlog

## When you get this alert
- PagerDuty: `HorizonQueueBacklog`
- Dashboard: Grafana → Horizon → "Pending jobs" panel > 5000
- Symptom: emails delayed, notifications delayed

## First 5 minutes

1. Check Horizon is running:
   ```bash
   ssh api-prod-1
   php /var/www/app/artisan horizon:status
   # Expected output: "Horizon is running."
   ```

2. Check queue depth:
   ```bash
   redis-cli -h redis-queue.prod.internal LLEN queues:default
   # If > 10000, we're significantly behind
   ```

3. Check worker count:
   ```bash
   ps aux | grep "horizon: worker" | wc -l
   # Should match maxProcesses in config/horizon.php
   ```

## Decision tree

### If Horizon is NOT running:
```bash
php /var/www/app/artisan horizon
# or via supervisor
sudo supervisorctl start horizon
```
Wait 30s, recheck queue depth.

### If workers < expected:
Scale supervisors:
```bash
sudo systemctl restart supervisor
```
If still not scaling, check `/var/log/supervisor/horizon-stderr.log`.

### If workers are running but queue still growing:
One job class is slow. Check Horizon dashboard → Metrics → Runtime.
Common slow: `GenerateInvoicePdfJob`, `SendBulkEmailJob`.

Pause that queue:
```bash
php artisan horizon:pause-supervisor supervisor-low
```

## If not mitigated after 10 min
Page @platform-lead via PagerDuty. Include:
- Queue depth at alert + current
- Worker count
- Job classes dominating runtime
- Any recent deploys

## Deeper investigation (after stable)
- Horizon dashboard → Failed Jobs
- Check `storage/logs/horizon.log` for errors
- Cross-ref with Datadog traces for slow job spans

## Related
- Runbook: [Redis down](redis-down.md)
- Runbook: [DB slow](db-slow.md)
- Post-mortem: [2026-03-12 Queue backlog](pm-20260312.md)

## Updated
Last reviewed: 2026-04-10 by @orkhan
```

## Runbook yazımı qaydaları

### 1. Dəqiq komandalar, kritik dəyərlər üçün placeholder yox

Pis:
```
Restart Horizon on the server.
```

Yaxşı:
```bash
ssh api-prod-1
sudo systemctl restart horizon
```

Pis:
```
Check the queue depth with redis-cli.
```

Yaxşı:
```bash
redis-cli -h redis-queue.prod.internal -p 6379 LLEN queues:default
```

### 2. Gözlənən output-u göstər

On-call düzgün vəziyyətdə olduğunu təsdiqləyə bilsin:

```bash
php artisan horizon:status
# Expected: "Horizon is running."
# If you see: "Horizon is not running." → continue to next step
```

### 3. Konteksti fərz etmə

Pis:
```
Roll it back.
```

Yaxşı:
```
Roll back the payments-service deployment:
kubectl rollout undo deployment/payments-service -n production

Verify rollback:
kubectl rollout status deployment/payments-service -n production
```

### 4. Qərar nöqtələri, mətn yox

Aydın budaqlanma istifadə et:
```
If X: → do A
If Y: → do B
If neither: → escalate to @team-lead
```

Belə yox:
"Asılı olaraq variantları nəzərdən keçirə bilərsən..."

### 5. Eskalasiya yolu açıq

Kim page-lənir, hansı metodla, hansı məlumatla.

```
If issue persists after 15 minutes, page @platform-lead via PagerDuty.
Include in your message:
  - Current queue depth
  - Any recent deploys
  - Failed job count
  - Dashboards with time range
```

### 6. Təzəlik tarixi

Hər runbook bununla bitir:
```
Updated: 2026-04-10 by @orkhan
```

Rüblük review et. Saat 3-də köhnə runbook-lar = pis sürprizlər.

### 7. Əlaqəli runbook-lara link ver

Bir şey başqa bir şey ola bildikdə, yan-yana link ver:
```
## Also check
- If DB also slow: [DB runbook](db-slow.md)
- If Redis down: [Redis runbook](redis-down.md)
```

## Runbook qovluğu üçün template strukturu

```
docs/runbooks/
├── README.md                      # index with links
├── playbooks/
│   ├── laravel/
│   │   ├── horizon-backlog.md
│   │   ├── php-fpm-saturation.md
│   │   └── opcache-stale.md
│   ├── database/
│   │   ├── mysql-deadlock.md
│   │   ├── mysql-replication-lag.md
│   │   └── pg-connection-exhaustion.md
│   ├── redis/
│   │   ├── redis-down.md
│   │   ├── redis-memory-full.md
│   │   └── redis-slowlog.md
│   └── infra/
│       ├── ingress-5xx.md
│       ├── cert-expired.md
│       └── dns-issue.md
├── scenarios/                     # non-alert-specific playbooks
│   ├── deploy-rollback.md
│   ├── db-failover.md
│   └── incident-response.md
└── reference/                     # lookup tables
    ├── contacts.md
    ├── escalation-policies.md
    └── critical-services.md
```

## Anti-pattern-lər

### Mətn runbook
Mətn abzasları. Scan etmək çətindir. Aydın qərar nöqtələri yoxdur. Saat 3-də faydasızdır.

### Copy-paste runbook
Bir neçə runbook-da dublikat mətn. Biri dəyişəndə, digərləri köhnəlir. Link istifadə et.

### Köhnə runbook
"Son yeniləmə 2023." Mühit dəyişib, komandalar işləmir. Runbook tələyə çevrilir.

### "Daha ətraflı oxu" runbook
Xarici sənədlərə işarə edir. Mühəndis indi saat 3-də 5 səhifə naviqasiya edir. Əsasları inline yaz.

### "Vəziyyətdən asılıdır" runbook
Aydın qərar yoxdur. Mühəndis şərh etməyə məcburdur. Qərar ağacını yaz.

## Runbook review prosesi

- Hər runbook öz owner-i tərəfindən rüblük review olunur
- Runbook istifadə edən hər incident-dən sonra: işləməyən şeyə görə yenilə
- Yeni işçi təsadüfi runbook-u keçir: ilişibsə, düzəlt
- Rüblük "runbook məşqi" keçir — runbook-u staging-də uçdan-uca icra et

## Avto-generasiya runbook-lar (mümkün olduqda)

Bəzi şeylər avto-generasiya ola bilər:
- Komanda istinadları (production-u query et, siyahı istehsal et)
- Kontakt siyahıları (PagerDuty API-dan)
- Dashboard link-ləri (Grafana provisioning-dən)

Avto-gen köhnəlməni azaldır, amma mətn qərar məntiqini əvəz edə bilməz.

## Laravel-xüsusi runbook nümunələri

Hər Laravel app-in olmalı başlıqlar:
- Horizon queue backlog
- PHP-FPM pool saturated
- OPcache serving stale code
- DB connection exhausted
- MySQL deadlock spike
- Redis unavailable (cache vs queue vs session)
- Migration stuck
- Disk full (çox vaxt: log-lar, Horizon, Telescope)
- Tək worker-də yüksək CPU

Hər biri ~80-150 sətir, scan edilə bilən, əvvəl komanda.

## Müsahibə bucağı

"Runbook-ları necə yazırsan?"

Güclü cavab:
- "Yaratdığım hər alert-ə link olunmuş runbook olmalıdır. Runbook yoxdur = natamam alert."
- "Template-im: bu alert-i aldıqda, ilk 5 dəqiqə, qərar ağacı, eskalasiya, dərin araşdırma, yeniləmə tarixi."
- "Saat 3 testi üçün yazıram: bu ssenarinı heç görməmiş yorğun mühəndis uğur qazanmalıdır."
- "Dəqiq komandalar, gözlənən output, aydın budaqlanma. Mətn istinad üçündür, hərəkət üçün deyil."
- "Runbook-lar git-də yaşayır, rüblük review olunur, incident sonrası yenilənir."
- "Runbook keyfiyyətini MTTR ilə ölçürəm. Əgər alert-in runbook-u keçən dəfə 2 dəqiqə çəkdi və bu dəfə 8 dəqiqə, runbook yenilənməlidir."

Bonus: "Horizon crash-ləri üçün runbook-umuz var idi. İlk versiya qeyri-müəyyən idi. Onu istifadə edən 3 incident-dən sonra 5 addımlıq checklist-ə sıxlaşdırdım. Növbəti incident: alert-dən mitigation-a 3 dəqiqə çəkdi. Real runbook-un dəyəri budur."
