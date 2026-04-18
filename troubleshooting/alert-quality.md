# Alert Quality

## Məqsəd
Alert-lər bahadır. Hər page diqqəti kəsir, kimisə oyadır, diqqətə xərclənir. Səs-küylü alert-lər susdurulur və sonra real incident-ləri qaçırır. Yaxşı alert keyfiyyəti davamlı on-call-ın təməlidir. Bu playbook alert dizayn prinsiplərini, review cadence-ini və ümumi tələləri əhatə edir.

## Əsas test

Hər alert üçün soruş: **"Bunu ignor edə bilərəmmi?"**

- Bəli → sil və ya ticket/notification-a endir
- Yox → təsdiqlə ki, hərəkət tələb edən real page-dir

Page-lər = indi hərəkət etməliyəm deməkdir. Sonra hərəkət edə bilərsən və ya heç etmə bilərsən, deməli page deyil.

## Simptom vs səbəb alert-ləri

### Simptom-əsaslı (yaxşı)
İstifadəçi tərəfindən görünən problemlər üçün alert:
- "Checkout error rate > 1%"
- "API p95 latency > 2s"
- "Homepage 5xx count > 10/min"

Bunlar istifadəçilər ağrı hiss etdikdə işə düşür. Actionable: ağrını yüngülləşdir.

### Səbəb-əsaslı (adətən pis)
Problemə səbəb olabilecek daxili metriklər üçün alert:
- "CPU > 80%" — yaxşı da ola bilər, pis də
- "Memory > 70%" — çoxlu app-də zərərsizdir
- "Connection count > 500" — sağlam ola bilər

Bunlar müştəri təsiri olmadan tez-tez işə düşür. Səs-küy.

### Səbəb-əsaslı OK olanda
Proqnozlaşdırıcı / qabaqlayıcı alert kimi, page olmayan:
- Slack / ticket-ə göndər, pager-ə yox
- Mühəndislər iş saatlarında review etsin
- Tutum planlaması üçün faydalıdır

**Qayda**: page-lər simptom-əsaslıdır. Proqnozlar ticket-əsaslıdır.

## Actionable vs informativ

Hər page aydın növbəti hərəkətə sahib olmalıdır:
- "DB connections > 95%" → X playbook-unu işlət, scale et, idle-ləri kill et
- "Horizon queue > 10k" → Y playbook-unu işlət, worker-ləri scale et
- "5xx spike" → Z playbook-unu işlət, deploy-ları yoxla, rollback-ı nəzərdən keçir

Əgər page-də aydın hərəkət yoxdursa: bu məlumatdır. Slack-a yönəlt, PagerDuty-yə yox.

### "Nə etməli" testi

Hər alert üçün özünü cavab verməyə məcbur et: "On-call bunu gördükdə nə etməli?"

Cavab verə bilmirsənsə, alert yaratma.

## Səs-küylü alert-lər ignor olunur

İnsan alarm fatique-i realdır. Mexanizmlər:
- Təkrar-təkrar işə düşəni susdur
- Bütün alert sisteminə güvəni itir
- Real alert-i başqa false positive kimi görür və ignor edir

### Alert fatique simptomları
- Mühəndislər notification-ları susdurur
- "Ack and ignore" pattern-i
- Page-lərin > 30%-i "no action needed" ilə həll olunur
- Eyni alert heç kimin baxmadığı üçün həftəlik onlarla dəfə işə düşür

## Threshold-lar: error rate vs mütləq say

### Mütləq say
```
alert: errors > 100 per minute
```

Pis scale olur. Az trafikdə 100 error = hər şey qırılıb. Yüksək trafikdə 100 error = 0.01% error rate = OK.

### Error rate
```
alert: errors / total_requests > 1% over 5 min
```

Trafiklə scale olur. Eyni alert saat 2-də və 2-də işləyir.

### Rate + minimum trafik
```
alert: (errors / total_requests > 1%) AND (total_requests > 100 per 5 min)
```

Qaçınır: 2 request-dən tək error → 50% error rate alert tətiklənməsi.

Nümunə Prometheus alert:
```yaml
- alert: HighErrorRate
  expr: |
    sum(rate(http_requests_total{status=~"5.."}[5m])) 
    / 
    sum(rate(http_requests_total[5m])) > 0.01
    and
    sum(rate(http_requests_total[5m])) > 1
  for: 2m
  labels:
    severity: page
  annotations:
    runbook: https://runbooks.example.com/high-error-rate
```

`for: 2m` → işə düşməzdən əvvəl 2 dəq davam etməlidir. Keçici spike-lərdən qaçınır.

## SLI/SLO-əsaslı alerting

### Service Level Indicator (SLI)
Xidmət davranışının ölçümü:
- Availability: successful_requests / total_requests
- Latency: request müddətinin p95-i
- Durability: data itirilmir

### Service Level Objective (SLO)
SLI üçün hədəf:
- 28 gün ərzində 99.9% availability
- p95 < 500ms
- 100% durability

### Error budget
1 - SLO. 99.9% SLO üçün error budget = 0.1% = 43 dəq/ay.

### Burn-rate alerting
"Hər downtime dəqiqəsi" üçün alert etmə. Budget istehlak dərəcəsi üçün alert:

- **Fast burn** (1 saatda budget-in 2%-i): dərhal page. Böyük bir şey səhvdir.
- **Slow burn** (6 saatda budget-in 10%-i): iş saatlarında page. Degradasiya yığılır.
- **Drift**: gündəlik budget statusu xülasəsi.

Google SRE handbook bunu yaxşı müəyyən edir. Alert-ləri təsirə mütənasib edir.

## Alert review cadence-i

**Rüblük review** minimum:
1. 3 aylıq page-ləri export et
2. Hər birini təsnif et: real / false / səs-küy
3. > 30% səs-küylü alert-lər: tənzimlə və ya sil
4. Heç vaxt işə düşməyən alert-lər: sil (və ya hələ də aktual olduğunu təsdiqlə)
5. Alert-siz incident-lər: birini əlavə et

İzləmək üçün metriklər:
- Həftəlik şəxs başına page-lər
- Actionable page-lərin %-i
- Rollback tələb edən page-lərin %-i
- Page-dən həllə qədər MTTR

Yaxşı benchmark:
- On-call başına həftədə < 5 page
- Page-lərin > 80%-i actionable
- Page-lərin > 90%-i üçün məlum playbook var

## Alert config ən yaxşı praktikalar

### 1. Hər alert-in runbook link-i var

```yaml
annotations:
  runbook: https://runbooks.example.com/alert-name
```

### 2. Hər alert-in owner-i var

Alert-ləri komanda üzrə tag et:
```yaml
labels:
  team: payments
```

PagerDuty-də yönəltmə: payments alert-ləri → payments on-call. Cross-contamination yox.

### 3. Hər alert-in severity-si var

```yaml
labels:
  severity: page      # pages
  severity: ticket    # goes to Jira / issue
  severity: info      # goes to Slack
```

### 4. Hər alert-in description-u var

On-call dərhal nə haqqında olduğunu bilsin:
```yaml
annotations:
  summary: "Error rate > 1% on API service for 5 min"
  description: "Service {{ $labels.service }} has error rate {{ $value | humanizePercentage }}"
```

### 5. Alert-lər test olunur

Göndərmədən əvvəl:
- Şərti sintetik yarat, alert-in işə düşdüyünü təsdiqlə
- Düzgün kanala / on-call-a getdiyini təsdiqlə
- Runbook link-in işlədiyini təsdiqlə

### 6. Baxım zamanı susdurula bilən

Planlaşdırılmış baxım pəncərələri alert-ləri susdurmalıdır. PagerDuty baxım pəncərələrini dəstəkləyir; Prometheus silence API-yə malikdir.

## Ümumi tələlər

### "CPU > 80%" üçün alert
Adətən səs-küydür. Müasir sistemlər yüksək CPU-nu yaxşı idarə edir. Yalnız latency də pis olsa önəmlidir.

### Disk dolu üçün alert
Bəli, amma mütləq deyil, % istifadə et:
```
disk_usage > 85% AND trending upward
```

Proqnozlaşdırıcı versiya (24 saat əvvəl xəbərdarlıq):
```
predict_linear(disk_usage[6h], 24*3600) > 0.95
```

### Hər error üçün alert
```
any 5xx → page
```

İstifadəçilər nadir error-lara dözəcəklər. Tək hadisə deyil, rate və ya pattern üçün page.

### İstifadəçilərin hiss etmədiyi infra metriklər üçün alert
Kubernetes pod restart ≠ istifadəçi təsiri. Yalnız simptoma çevrilsə alert et.

### Hər məsələ üçün bir alert
Əgər eyni əsas səbəb üçün 10 alert işə düşürsə, konsolidə et və ya inhibit qaydaları istifadə et.

Prometheus inhibit:
```yaml
inhibit_rules:
- source_match:
    alertname: 'APIDown'
  target_match_re:
    alertname: 'HighErrorRate|HighLatency'
```

Əgər API aşağıdırsa, simptomları haqqında da page etmə.

## Auto-remediation

Ən yaxşı alert heç bir alert-dir — əgər sistem özünü sağalda bilirsə.

Nümunələr:
- Horizon worker OOM → supervisor restart edir (page lazım deyil)
- Pod crash → Kubernetes restart edir (flapping olmasa page yox)
- Queue backlog → worker-lər autoscale olur (scaling uğursuz olmasa page yox)

Yalnız auto-remediation uğursuz olanda və ya tətbiq edilə bilməyəndə page et.

## PHP/Laravel alert nümunələri

### Yaxşı
```
HighErrorRate: 5xx rate > 1% for 5 min (with min traffic)
APILatencyP95: p95 > 2s for 5 min
HorizonQueueBacklog: pending > 10000 AND growing
FPMSaturation: active_processes/max_children > 90% for 5 min
MySQLConnectionsNearLimit: threads_connected / max_connections > 80%
RedisMemoryNearLimit: used_memory / maxmemory > 90%
DiskFull: disk_usage > 85% (predictive)
CertExpiring: cert_expires_in_days < 14
```

### Pis / səs-küylü
```
AnyPHPError: any error log line
CPUHigh: cpu > 60%
PodRestarted: any restart
DBQuerySlowOnce: any query > 1s
```

### Ticket-səviyyəli, page deyil
```
SlowQueryLogGrowth: slow_query_count increasing
OPcacheHitRateLow: hit_rate < 95% (investigate capacity)
HorizonMemoryTrend: worker RSS trending up
```

## Yadda saxlanmalı real komandalar

```bash
# Prometheus query to test alert
curl -G 'http://prometheus:9090/api/v1/query' \
  --data-urlencode 'query=sum(rate(http_errors[5m])) / sum(rate(http_requests[5m]))'

# Alertmanager silence
amtool silence add alertname=HighErrorRate --comment="maintenance" --duration=2h

# PagerDuty via API
curl -X POST https://api.pagerduty.com/incidents \
  -H "Authorization: Token token=$PD_TOKEN" \
  -d '{"incident":{"type":"incident","title":"Test","service":{"id":"PXXXXX"}}}'

# Datadog monitor query
dogshell-monitor show MONITOR_ID
```

## Müsahibə bucağı

"Yaxşı alert-ləri necə dizayn edirsən?"

Güclü cavab:
- "Hər alert cavab verməlidir: 'On-call nə edəcək?' Aydın hərəkət yoxdursa, bu page deyil — məlumatdır."
- "Səbəb-əsaslı deyil, simptom-əsaslı. İstifadəçi ağrısı üçün alert: error rate, latency, uğursuz tranzaksiyalar. Daxili CPU/memory ticket olur, page deyil."
- "Minimum trafik threshold-ları ilə error rate. '2 request-də bir error = 50% error rate' səs-küyündən qaçınır."
- "Yetkin xidmətlər üçün SLO-əsaslı burn-rate alerting. Fast burn page-lər, slow burn ticket-lər."
- "Hər alert-in runbook-u var. Runbook yoxdur = natamam alert."
- "Rüblük review. > 30% səs-küylü alert-lər tənzimlənir və ya silinir. Heç vaxt işə düşməyən alert-lər silinir."

Bonus: "Bir şirkətdə 'page diet' etdik. 140 fərqli alert-lə başladıq, 35 ilə bitirdik. Həftəlik page-lər 50+-dan 8-ə düşdü. On-call davamlı oldu. Reliability əslində yüksəldi çünki real page-lər tez diqqət aldı."
