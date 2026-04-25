# Incident Response (Senior)

## Nədir? (What is it?)

Incident Response – production sistemdə baş verən kritik problemin (downtime, data loss, performance degradation) aşkarlanması, qiymətləndirilməsi, həll edilməsi və təkrarlanmaması üçün dərs çıxarılması prosesidir. Müasir SRE mədəniyyətində bu prosess **formalizə** olunub: severity səviyyələri (SEV1-SEV4), on-call rotation, incident commander rolu, status page kommunikasiyası, blameless postmortem. Məqsəd iki ölçülü: (1) **MTTR** (Mean Time To Recovery) azaltmaq – müştəriyə təsiri qısaltmaq, (2) **MTBF** (Mean Time Between Failures) artırmaq – təkrar baş verməsinin qarşısını almaq. Alətlər: **PagerDuty**, **Opsgenie** (Atlassian), **VictorOps** (Splunk), **Grafana OnCall**. Status page: Statuspage.io, Instatus, Better Stack. Incident management platforms: Rootly, Incident.io, FireHydrant. Good incident response mədəniyyəti SLO və error budget yanaşması ilə sıx bağlıdır.

## Əsas Konseptlər (Key Concepts)

### Severity Levels

```
SEV1 (Critical)   – Total outage, məlumat itkisi, security breach
                    Response: <15 dəq, bütün mövcud əl, CEO/CTO-a bildir
                    Example: bütün production API 500, DB down, data breach
                    SLA: dərhal

SEV2 (High)       – Major feature pozuldu, böyük user segment təsirli
                    Response: <30 dəq, on-call + escalate
                    Example: ödəniş işləmir, 30% user-də 500, DB read replica down
                    SLA: bir neçə saat

SEV3 (Medium)     – Kiçik feature pozuldu, workaround var
                    Response: iş saatları, normal ticket
                    Example: kiçik səhifə slow, non-critical cron failed
                    SLA: 1-2 gün

SEV4 (Low)        – Cosmetic, low impact
                    Example: UI mismatch, typo, kiçik log xətası
                    SLA: backlog
```

### Incident Lifecycle

```
1) Detect       – Monitoring/alert və ya user report
2) Triage       – Severity qiymətləndir, IC təyin et
3) Communicate  – Status page, Slack channel, stakeholder-lər
4) Mitigate     – Təsiri azalt (rollback, scale, failover)
5) Resolve      – Köklü həll yoxsa workaround
6) Review       – Postmortem, action item-lər
7) Follow-up    – Action item-lər icra olunsun
```

### On-Call Rotation

```
Follow-the-sun   – Fərqli coğrafiyada komanda, 24/7 iş saatlarında
Primary/Secondary – Primary birinci cavab verir, timeout olsa secondary
Weekly rotation   – Həftəlik növbə dəyişikliyi
Escalation chain  – Primary → Secondary → Manager → Director

Healthy on-call:
- <2 gecə alarm həftədə
- Alarm actionable olsun (yalnız real problem)
- Bounty / compensation (on-call pay)
- Fair rotation
```

### Incident Commander (IC) rolu

```
İC məsuliyyətləri:
- Incident-i qoordinasiya edir (texniki işi yox)
- Severity təyin edir
- Rolları paylayır (Communications Lead, SME, Scribe)
- Status update-lər verir (hər 30 dəq)
- Qərar qəbul etmək səlahiyyəti var (rollback, failover)
- Incident-dən sonra postmortem başladır

IC olmaq üçün texniki expert olmaq şərt deyil, prosessi idarə edə bilmək vacibdir.
```

### Communication Channels

```
Internal:
- #incident-XXX Slack channel (hər incident üçün ayrı)
- War room (video call – Zoom/Meet)
- Status dashboard (Grafana, Datadog)

External:
- Status Page (statuspage.io) – müştəri görür
- Twitter, support email
- Proaktiv bildiriş (enterprise müştərilər)

Template:
"12:05 UTC: API latency artışını araşdırırıq. Təsirli region: eu-central-1.
Update 30 dəqiqədə."
```

### Runbook

```
Hər known-issue üçün yazılı addım-addım procedure:
- Symptom təsviri
- İlk yoxlama addımları (grep, metric, dashboard)
- Mitigation (rollback komandaları, failover)
- Escalation (kim ilə əlaqə)
- Postmortem link-ləri

Runbook nümunəsi:
"DB connection pool exhausted":
1. Grafana-da MySQL connection count yoxla
2. slow_query_log aktiv et
3. Uzun sorğular varsa: KILL QUERY <id>
4. Horizon pause et – `php artisan horizon:pause`
5. RDS-də max_connections artır (dynamic parameter)
6. Escalation: @dba-team
```

### Blameless Postmortem

```
Qayda: İnsan günah axtarma, sistemi düzəlt

5 Whys – kök səbəbə enmək üçün
Timeline – nə zaman nə baş verdi
What went well – uğurlu tərəflər
What went wrong – problemlər
Action items – konkret, sahibli, tarixli

Psychological safety – mühəndis "yanılmaq olar" hissi
"Novice mistake" deyilməsin – "system allowed this mistake"
```

## Praktiki Nümunələr

### PagerDuty Terraform Setup

```hcl
# pagerduty.tf
resource "pagerduty_service" "laravel_api" {
  name              = "Laravel API"
  auto_resolve_timeout = 14400  # 4 saat
  acknowledgement_timeout = 600 # 10 dəq
  escalation_policy = pagerduty_escalation_policy.sre.id

  alert_creation = "create_alerts_and_incidents"

  incident_urgency_rule {
    type    = "constant"
    urgency = "high"
  }
}

resource "pagerduty_escalation_policy" "sre" {
  name = "SRE Escalation"
  rule {
    escalation_delay_in_minutes = 10
    target {
      type = "user_reference"
      id   = pagerduty_user.primary_oncall.id
    }
  }
  rule {
    escalation_delay_in_minutes = 15
    target {
      type = "schedule_reference"
      id   = pagerduty_schedule.secondary.id
    }
  }
  rule {
    escalation_delay_in_minutes = 30
    target {
      type = "user_reference"
      id   = pagerduty_user.sre_manager.id
    }
  }
}

resource "pagerduty_schedule" "primary" {
  name      = "Primary On-Call"
  time_zone = "Asia/Baku"

  layer {
    name                         = "Weekly"
    start                        = "2026-04-21T09:00:00+04:00"
    rotation_virtual_start       = "2026-04-21T09:00:00+04:00"
    rotation_turn_length_seconds = 604800  # 1 həftə
    users                        = [
      pagerduty_user.engineer1.id,
      pagerduty_user.engineer2.id,
      pagerduty_user.engineer3.id,
      pagerduty_user.engineer4.id,
    ]
  }
}
```

### Prometheus Alertmanager + PagerDuty Integration

```yaml
# alertmanager.yml
global:
  resolve_timeout: 5m

route:
  receiver: default
  group_by: ['alertname', 'severity']
  group_wait: 10s
  group_interval: 5m
  repeat_interval: 4h
  routes:
    - match:
        severity: critical
      receiver: pagerduty-sev1
      continue: true
    - match:
        severity: warning
      receiver: slack-warnings

receivers:
  - name: pagerduty-sev1
    pagerduty_configs:
      - service_key: "<PD_INTEGRATION_KEY>"
        severity: critical
        description: "{{ .CommonAnnotations.summary }}"
        details:
          runbook: "{{ .CommonAnnotations.runbook_url }}"
          dashboard: "{{ .CommonAnnotations.dashboard_url }}"

  - name: slack-warnings
    slack_configs:
      - api_url: "<SLACK_WEBHOOK>"
        channel: "#alerts-warnings"
        title: "{{ .GroupLabels.alertname }}"
        text: "{{ range .Alerts }}{{ .Annotations.description }}\n{{ end }}"
```

### Prometheus Alert Rules (actionable alerts)

```yaml
# alert-rules.yml
groups:
  - name: laravel.critical
    rules:
      - alert: LaravelAPIDown
        expr: up{job="laravel-api"} == 0
        for: 2m
        labels:
          severity: critical
          team: backend
        annotations:
          summary: "Laravel API tam çökdü"
          description: "Job {{ $labels.instance }} 2 dəqiqədir cavab vermir."
          runbook_url: "https://runbooks.example.com/laravel-api-down"
          dashboard_url: "https://grafana.example.com/d/laravel-api"

      - alert: HighErrorRate
        expr: |
          (
            sum(rate(http_requests_total{job="laravel-api",status=~"5.."}[5m]))
            /
            sum(rate(http_requests_total{job="laravel-api"}[5m]))
          ) > 0.05
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Error rate > 5% ({{ $value | humanizePercentage }})"
          runbook_url: "https://runbooks.example.com/high-error-rate"

      - alert: DatabaseConnectionPoolExhausted
        expr: mysql_global_status_threads_connected / mysql_global_variables_max_connections > 0.9
        for: 3m
        labels:
          severity: warning
        annotations:
          summary: "MySQL connection pool 90% doludur"
          runbook_url: "https://runbooks.example.com/db-connections"
```

### Status Page Update Script

```bash
#!/bin/bash
# update-statuspage.sh

STATUSPAGE_ID="abc123"
API_KEY="${STATUSPAGE_API_KEY}"
COMPONENT_ID="def456"  # Laravel API komponenti
SEVERITY=$1             # investigating, identified, monitoring, resolved
MESSAGE=$2

curl -X POST "https://api.statuspage.io/v1/pages/$STATUSPAGE_ID/incidents" \
  -H "Authorization: OAuth $API_KEY" \
  -H "Content-Type: application/json" \
  -d "{
    \"incident\": {
      \"name\": \"Laravel API degraded performance\",
      \"status\": \"$SEVERITY\",
      \"impact_override\": \"major\",
      \"body\": \"$MESSAGE\",
      \"component_ids\": [\"$COMPONENT_ID\"],
      \"deliver_notifications\": true
    }
  }"

# İstifadə:
# ./update-statuspage.sh investigating "API gecikmə araşdırılır"
```

### Incident Bot (Slack + Workflow)

```python
# incident_bot.py (Slack slash command /incident start)
from slack_bolt import App
import uuid, requests

app = App(token=os.environ["SLACK_BOT_TOKEN"])

@app.command("/incident")
def start_incident(ack, respond, command, client):
    ack()
    args = command["text"].split(" ", 2)
    severity = args[0] if args else "SEV3"
    title = args[1] if len(args) > 1 else "Untitled"

    incident_id = f"INC-{uuid.uuid4().hex[:6].upper()}"
    channel_name = f"incident-{incident_id.lower()}"

    # 1) Channel yarat
    ch = client.conversations_create(name=channel_name, is_private=False)
    channel_id = ch["channel"]["id"]

    # 2) Initial mesaj
    client.chat_postMessage(
        channel=channel_id,
        text=f"*{incident_id}* ({severity}): {title}\n"
             f"*Incident Commander*: <@{command['user_id']}>\n"
             f"Runbook: https://runbooks.example.com/{incident_id}"
    )

    # 3) PagerDuty açmaq (SEV1-2 üçün)
    if severity in ("SEV1", "SEV2"):
        requests.post("https://events.pagerduty.com/v2/enqueue", json={
            "routing_key": os.environ["PD_KEY"],
            "event_action": "trigger",
            "payload": {
                "summary": f"{incident_id}: {title}",
                "severity": "critical" if severity == "SEV1" else "error",
                "source": "incident-bot"
            }
        })

    # 4) Status page update
    # ... statuspage API call

    respond(f"Incident {incident_id} açıldı: <#{channel_id}>")

app.start(3000)
```

### Postmortem Template

```markdown
# Postmortem: INC-A1B2C3 – Laravel API 90 min downtime
**Date**: 2026-04-15
**Authors**: @alice (IC), @bob (SRE)
**Status**: Finalized
**Severity**: SEV1
**Duration**: 90 minutes (12:05 – 13:35 UTC)

## Summary
Deploy zamanı yeni miqrasiya böyük `orders` cədvəlində lock yaratdı.
API request-lər timeout oldu, LB bütün instance-ları unhealthy kimi işarələdi.

## Impact
- 100% API downtime 90 dəqiqə
- Təxminən 120,000 failed request
- 45 müştəridən dəstək ticket
- Gəlir itkisi: təxminən $15,000

## Timeline (UTC)
- 12:03 – Deploy başladı (v2.34.0)
- 12:05 – İlk 500 xətaları (user raportları)
- 12:08 – Monitoring alert (PagerDuty – high error rate)
- 12:10 – On-call @alice cavab verdi
- 12:15 – IC təyin edildi, #incident-a1b2c3 channel açıldı
- 12:25 – Root cause identifikasiyası: `ALTER TABLE orders` lock
- 12:35 – Rollback deploy başladı
- 12:50 – Rollback tamamlandı, amma sessiyalar hələ slow idi
- 13:10 – Horizon worker-ləri yenidən başladı
- 13:35 – Error rate baseline-a qayıtdı
- 13:40 – Status page "resolved"

## Root Cause
`ALTER TABLE orders ADD COLUMN shipment_tracking VARCHAR(100)` əmri
MySQL 5.7-də copy-table lock etdi (online DDL deyildi).
Cədvəl 80M row idi, lock 25 dəqiqə çəkdi.

## What went well
- Monitoring alarm dəqiq işlədi (5 dəqiqə içində)
- IC rolu incident zamanı yaxşı idarə etdi
- Status page tez yeniləndi

## What went wrong
- Migration staging-də test edilmədi (cədvəl kiçik idi)
- Rollback procedure-də gap var idi – Horizon restart əl ilə
- DBA təsdiqi PR-da tələb olunmurdu

## Action Items
| ID | Action | Owner | Due |
|----|--------|-------|-----|
| 1 | Staging-də production-size cədvəl istifadə et | @platform | 2026-05-01 |
| 2 | `pt-online-schema-change` / gh-ost tool-u install və məcburi | @dba | 2026-04-30 |
| 3 | DBA approval PR template-ə əlavə et | @alice | 2026-04-22 |
| 4 | Rollback runbook-da Horizon restart-ı avtomatlaşdır | @bob | 2026-05-01 |
| 5 | Migration playbook işçi trenninqi | @sre | 2026-05-15 |
```

## PHP/Laravel ilə İstifadə

### Laravel Exception Handler → Sentry/PagerDuty

```php
// app/Exceptions/Handler.php
use Sentry\Laravel\Integration;
use Illuminate\Support\Facades\Http;

public function register(): void
{
    $this->reportable(function (\Throwable $e) {
        // Sentry
        Integration::captureUnhandledException($e);

        // Critical error-lar PagerDuty-yə
        if ($e instanceof \App\Exceptions\CriticalException) {
            $this->triggerPagerDuty($e);
        }
    });
}

protected function triggerPagerDuty(\Throwable $e): void
{
    Http::post('https://events.pagerduty.com/v2/enqueue', [
        'routing_key' => config('services.pagerduty.routing_key'),
        'event_action' => 'trigger',
        'dedup_key' => md5(get_class($e) . $e->getMessage()),
        'payload' => [
            'summary' => 'Critical: ' . $e->getMessage(),
            'severity' => 'critical',
            'source' => app()->environment(),
            'component' => 'laravel-api',
            'custom_details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ],
        ],
    ]);
}
```

### Health Check Endpoint (load balancer üçün)

```php
// routes/web.php
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

Route::get('/healthz', function () {
    $checks = [
        'database' => fn() => DB::connection()->getPdo() && User::limit(1)->count() >= 0,
        'cache'    => fn() => Cache::put('health', 'ok', 10) && Cache::get('health') === 'ok',
        'redis'    => fn() => Redis::ping() === 'PONG',
        'storage'  => fn() => is_writable(storage_path('app')),
    ];

    $results = [];
    $allHealthy = true;

    foreach ($checks as $name => $check) {
        try {
            $ok = $check();
            $results[$name] = $ok ? 'ok' : 'failed';
            $allHealthy = $allHealthy && $ok;
        } catch (\Throwable $e) {
            $results[$name] = 'error: ' . $e->getMessage();
            $allHealthy = false;
        }
    }

    return response()->json(
        ['status' => $allHealthy ? 'healthy' : 'unhealthy', 'checks' => $results],
        $allHealthy ? 200 : 503
    );
});
```

### Feature Flag-lərlə Graceful Degradation

```php
// composer require laravel/pennant

use Laravel\Pennant\Feature;

// Circuit breaker üçün feature flag
Feature::define('payment-service-enabled', fn() => true);

class CheckoutController
{
    public function process(Request $request)
    {
        if (!Feature::active('payment-service-enabled')) {
            // Incident zamanı tez söndür
            return response()->json([
                'message' => 'Ödəniş xidməti müvəqqəti əlçatmazdır. Dəqiqəyə qədər yenidən cəhd edin.',
                'retry_after' => 60,
            ], 503);
        }

        // Normal flow
        return $this->paymentService->charge($request);
    }
}

// Incident zamanı tez söndürmə:
// php artisan pennant:deactivate payment-service-enabled
```

## Interview Sualları (Q&A)

**S1: SEV1 ilə SEV2 arasında fərq necə təyin olunur?**
C: SEV1 – **total outage** və ya **data loss / security breach**: bütün müştərilər təsirlidir, iş tamamilə dayanıb. SEV2 – **böyük feature pozulub** amma sistem tam işləyir: məs. ödəniş işləmir amma catalog açılır; 30% user-də xəta var. Praktik fərq: SEV1 gecə-gündüz on-call çağırır, IC məcburi, status page dərhal yenilənir. SEV2 – on-call çağırır amma bütün komandanı səfərbər etmir. Hər şirkətin öz definition-u olmalıdır və yazılı olmalıdır, yoxsa "mənə görə SEV1" qarışıqlığı yaranır.

**S2: Blameless postmortem nədir və niyə vacibdir?**
C: Postmortem-də "kim günahkardır" sualı verilmir. Əvəzinə "**sistem necə buna imkan verdi?**" soruşulur. Əsas fikir – insan səhv edəcək (human factor), prosess onu tuta bilməlidir. Blame culture mühəndislərin problemi gizlətməsinə səbəb olur (qorxudan). Blameless yanaşma açıqlığı təşviq edir – mühəndis detalları verir, sistem güclənir. Etsy, Google, Netflix bu mədəniyyətin populyarlaşdırıcılarıdır. Praktik rule: "I think X team messed up" → "I notice X process allowed this".

**S3: Incident Commander (IC) nə ilə məşğul olur?**
C: IC **texniki işi GÖRMÜR** – koordinasiya edir. Rolları: (1) Severity qərarı ver, (2) Rolları paylaş (Communications Lead, SME, Scribe), (3) Qərar qəbul et (rollback, failover), (4) Status update-lər (internal və external), (5) Meetings yönləndir, (6) Postmortem yaranışını başlad. IC texniki ekspert olmağa ehtiyac duymur – incident-i idarə etməyi öyrənməlidir. Bəzi şirkətlərdə "IC on-call" ayrıca rotasiyadır.

**S4: On-call mühəndis incident-də nə etməlidir?**
C: (1) **5 dəqiqə içində alarm-ı ack et** (PagerDuty bildirir), (2) Severity qiymətləndir, (3) **IC-ə çağır** (SEV1-2 üçün), (4) Incident channel yarat, (5) İlk triage – runbook izlə, metric yoxla, (6) Mitigate et – rollback, scale, failover (həll etməkdən tez), (7) Eskalasiya – bilmirsənsə secondary/manager çağır, (8) Timeline yaz (scribe yoxdursa sən). Səhv: təkbaşına çox vaxt itirmək – kömək istəməkdə utanmaq. Healthy on-call mədəniyyəti tez çağırmağı təşviq edir.

**S5: "Actionable alert" nə deməkdir?**
C: Alert **mütləq insan müdaxiləsi** tələb etsin. "CPU 80%" actionable deyil – çox vaxt normaldır. "P95 latency > 2s 5 dəqiqədir və error rate > 1%" actionable-dir. Non-actionable alert-lər "alert fatigue" yaradır – mühəndis alert-i görməyi dayandırır. Qayda: hər alert-in **runbook-u olmalıdır** – "bu alert gəldikdə bunu et". Runbook yoxdursa alert silinməlidir. On-call healthy olması alert kalite-sindən birbaşa asılıdır.

**S6: MTTR və MTBF nədir?**
C: **MTTR** (Mean Time To Recovery) – incident detect olunduqdan bərpaya qədər orta vaxt. **MTBF** (Mean Time Between Failures) – incident-lər arasında orta vaxt. İdeal: MTTR kiçik (tez düzəlt), MTBF böyük (nadir düzəlt). MTTR komponentlər: MTTD (detect), MTTA (acknowledge), MTTR (recover). Yaxşı monitoring MTTD, yaxşı alerting MTTA, yaxşı runbook/automation MTTR azaldır. Trend aylıq izlənir, hər postmortem-də bu metric-lər yeniləndirilir.

**S7: Status page nə üçündür və necə istifadə olunur?**
C: Status page – müştəriyə **transparent kommunikasiya** kanalıdır. Məqsəd: (1) Müştəri bilməsə, support bombardman edir, (2) Enterprise müştərilər real-time status istəyir. Best practice: (1) Problem aşkarlanandan 10-15 dəq ərzində update, (2) Hər 30 dəq yeni update (hətta "hələ araşdırırıq"), (3) Plain English, technical jargon yox, (4) ETA söyləməmə əgər bilinmirsə. Statuspage.io standartdır, Instatus və Better Stack ucuzdur, Atlassian Statuspage enterprise üçün.

**S8: Runbook və playbook fərqi nədir?**
C: Oxşar istifadə olunur, amma **runbook** daha konkretdir: "**bu spesifik alert/problem** üçün addım-addım nə etmək". **Playbook** daha ümumi: "database failover üçün ümumi prosedur". Praktik fərq azdır, bir çox təşkilat terminləri dəyişdirir. Vacib olan: hər kritik servisin, hər alert-in runbook-u olsun, Git-də saxlansın, aktual saxlansın, incident zamanı onu izləmək mümkün olsun (URL alert-də olmalıdır).

**S9: Error budget və incident arasında əlaqə nədir?**
C: SLO 99.9% uptime deməkdir aylıq 43 dəqiqə budgeteded downtime. İncident-lər bu budget-dən yeyir. Budget bitəndə: (1) Feature deploy-lar **dayandırılır**, (2) Komanda reliability işinə keçir, (3) Postmortem-lərin action item-ləri prioritet. Bu "reliability vs feature" tension-u avtomatlaşdırır. Google SRE kitabı bu konsepti populyarlaşdırdı. Error budget incident-lərin biznes dilinə tərcüməsidir – CFO anlayır.

**S10: Incident-lərdən necə öyrənmək olar?**
C: (1) **Hər SEV1/SEV2-dən sonra postmortem məcburi** – 5 iş günü içində, (2) **Action item-lər sahibli və tarixli** – follow-through olmasa postmortem faydasız, (3) **Quarterly pattern analysis** – hansı komponent tez-tez düşür, hansı root cause təkrarlanır, (4) **Incident library** – bütün postmortem-lər Wiki-də, yeni mühəndis oxusun, (5) **Game day** – istifadə olunmayan senariləri məşq et (Chaos Engineering), (6) **Cross-team share** – qonşu komandalar da öyrənsin. Learn-don't-blame mədəniyyəti vacibdir.

## Best Practices

1. **Runbook** hər kritik servisə – Git-də, aktual, alert-də link.
2. **Severity matrisini** yazılı saxla – SEV1-SEV4 meyarları hamıya aydın.
3. **On-call rotation ədalətli** – maksimum həftədə bir, gecə alarmı nadirdə.
4. **On-call compensation** – əlavə pay, off-time, recognition.
5. **Alert hygiene** – aktiv olmayan alert-ləri sil, noise-ı azalt.
6. **Blameless postmortem** – insanın adı yaz, amma onu günahlandırma.
7. **Action item tracking** – Jira/Linear-da, həftəlik review.
8. **Status page** proaktiv yenilə – müştəri soruşmadan danış.
9. **IC training** – incident commander kurs/rotasiyası, texniki olmayanlar da edə bilər.
10. **Game days** – kvartalda 1 dəfə süni incident, komanda məşq etsin.
11. **Chaos Engineering** – kiçik failure-ları production-da sına.
12. **Feature flags** ilə tez söndürmə qabiliyyəti – deploy-a ehtiyac olmadan.
13. **Rollback-ı asan et** – 1 əmr/klik ilə əvvəlki versiyaya.
14. **Error budget** – SLO-ya görə incident impact-i əsaslandır.
15. **Postmortem publishing** – komanda daxili, sonra şirkət daxili paylaş.
