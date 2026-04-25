# On-Call (Middle)

## Problem (nəyə baxırsan)
On-call rotation mühəndislik reliability-sinin real olduğu yerdir. Yaxşı edildikdə, mühəndislik mədəniyyətinin məhsuldar hissəsidir. Pis edildikdə burnout, alert fatique və heç kim cavab vermədiyi üçün uzanan incident-lərdir. Bu playbook shift handoff-ları, alert fatique, kompensasiya, alətlər və eskalasiyanı əhatə edir.

## Sürətli triage (ilk 5 dəqiqə)

### İndi on-call-san?

Qrafiki yoxla:
```bash
# PagerDuty CLI
pd schedule list
pd oncall list --schedule-id PXXXXX

# Opsgenie
curl -X GET "https://api.opsgenie.com/v2/schedules/SCHED_ID/on-calls/current" \
  -H "Authorization: GenieKey $API_KEY"
```

Telefon/Slack PagerDuty app-inin aktiv olduğunu və notification-ların icazəli olduğunu təsdiqlə. On-call olub pager-in DND ilə bloklanmasından pis şey yoxdur.

### Shift-ə başlayarkən

1. Açıq incident-ləri yoxla: hələ aktiv olanları miras al
2. Son post-mortem-ləri oxu: bu həftə nə uğursuz olub bil
3. Alət girişini təsdiqlə: PagerDuty, dashboard-lar, Slack kanalları, VPN, production SSH
4. Alerting-in işlədiyini təsdiqlə: self-test page
5. Runbook indeksini gözdən keçir

## Diaqnoz

### Shift handoff template-i

Gedən tərəfdən gələn tərəfə shift sonu mesajı:

```
ON-CALL HANDOFF — 2026-04-17 09:00 UTC

Active issues:
  - None (or describe open items)

Recent pages (last 24h):
  - 03:15 UTC: HighDBConnections — self-resolved, root cause in investigation
  - 08:42 UTC: QueueBacklog — scaled Horizon, watching

Context for the week:
  - New payments-service deploy on Tue 14:00 UTC
  - Known issue: Redis upgrade scheduled Thursday
  - Customer impact note: large customer X onboarding, expect traffic spike

Handing off to: @next-oncall
```

Daimi on-call kanalında (#oncall-handoff və ya #ops) post et. Keçmiş mesajlar bir incident gündəliyi olur.

### Runbook əlçatanlığı

Hər alert runbook-a link olmalıdır. Bunu test et:
- PagerDuty-də alert link-ə klik et
- Nə etməli olduğunu izah edən runbook-a gedir?
- Runbook güncəldir (son yeniləmə 2 il əvvəl deyil)?
- İşlədəcək dəqiq komandalar varmı?

Runbook yoxdursa: bu on-call üçün bir action item-dir, saat 3-də başına çarə tapmaq üçün səbəb deyil.

### Alert fatique

Simptomlar:
- Mühəndislər notification-ları səssiz edir
- Page-lərin > 30%-i "no-action-needed" həlli ilə bitir
- İnsanlar page gələndə deyinirlər
- Heç kəsin düzəltmədiyi eyni şey üçün xroniki alert-lər

Fix (bax: [alert-quality.md](alert-quality.md)):
- Rüblük alert review-u, səs-küylü alert-ləri sil / tənzimlə
- Hər page actionable olmalıdır
- Səbəb-əsaslı deyil, simptom-əsaslı alerting
- Ümumi hallar üçün auto-remediation

### On-call üçün yuxu gigiyenası

- Telefonu silent-except-pager saxla (PagerDuty üçün DND allowlist)
- Hər rotation başlanğıcında pager-i test et — real test page tətiklə
- Saat 3-də oyandırsalar: ack et, stabilləşdir, SƏNƏDLƏŞDİR, yat. SEV1 olmasa saat 3-də 4 saatlıq debug başlama.
- İncident sonrası yorğunluq: növbəti günü daha yavaş keçir, sərt itələmə

### Kompensasiya vaxtı

Siyasət gözləntiləri (yazılı olmalıdır):
- Yuxu saatlarında (adətən yerli saat 22:00 - 07:00) page gəldi: qeyd et
- 24 saatlıq dövrdə N page: növbəti gün time-in-lieu
- Həftəsonu page-lənmə: standart yarım günlük off kompensasiyası
- Bayramlar: ikiqat kompensasiya

Şirkətin siyasəti yoxdursa, onu müdafiə et. Kompensasiyasız on-call = retention problemi.

## Fix (bleeding-i dayandır)

### Shift zamanı

1. Ack SLA-sı ərzində cavab ver (adətən SEV2+ üçün 5-15 dəq, SEV3 üçün 30 dəq)
2. Runbook-u izlə
3. Lazım gələrsə siyasətə uyğun eskalasiya et
4. Nə etdiyini sənədləşdir

### İncident ortasında oyandırsan

İlk 3 dəqiqə:
- Page-i ack et
- Laptop-u aç, incident kanalına qoşul
- "@here buradayam, qavrayıram" yaz
- Son güncəlləmələri oxu
- Kömək etməyə başla (təkrarlama — hərəkət etməzdən əvvəl oxu)

## Əsas səbəbin analizi

İncident başına: standart post-mortem prosesi.

Shift başına: qısa shift sonu review-u:
- Nəsə aydın olmayan idi?
- Hər hansı runbook çatışmırdı / səhv idi?
- Hər hansı alert səs-küylü / actionable deyildi?

Bunları komanda metriki kimi zaman ərzində izlə.

## Qarşısının alınması

- Rotation ölçüsü: minimum 5-8 mühəndis (shift başına primary + secondary)
- Shift uzunluğu: tipik 1 həftə, maksimum 2 həftə
- İnsanları yandırmadan 24/7 üçün follow-the-sun
- Primary + secondary: secondary backup-dır, primary qaçırarsa miras alır
- Eskalasiya siyasəti: açıq, test olunmuş, adlı rollarla, fərdlər deyil

## PagerDuty / Opsgenie əsasları

### PagerDuty
- **Service**: monitor olunan şey (bir app, bir database)
- **Integration**: alert-lər PagerDuty-yə necə çatır (email, API, Datadog, Prometheus Alertmanager)
- **Escalation policy**: kim page olunur, hansı qaydada, hansı timeout-dan sonra
- **Schedule**: kim nə vaxt on-call-dır
- **Incident**: cavab tələb edən konkret alert

### Opsgenie
- Oxşar konseptlər: komandalar, qrafiklər, eskalasiya siyasətləri, inteqrasiyalar

### Eskalasiya siyasəti nümunəsi
```
Level 1: primary on-call — ack within 5 min
Level 2: secondary on-call — paged after 5 min if not acked
Level 3: team lead — paged after 15 min
Level 4: manager — paged after 30 min
```

Eskalasiyanı test et. Primary oyanmayanda saat 3-də qırıq olduğunu kəşf etmə.

## Follow-the-sun coverage

Qlobal komandalar üçün handoff-u zaman zonaları arasında rotate et:

```
EU shift: 08:00-16:00 UTC
US east: 13:00-21:00 UTC  (overlap for handoff)
US west: 16:00-00:00 UTC
APAC: 00:00-08:00 UTC
```

Hər region öz gündüz saatlarını idarə edir. Heç kim müntəzəm olaraq saat 3-də page-lənmir.

Tradeoff: bir neçə regionda komanda tələb edir. ~50-dən çox mühəndisi olan təşkilatlar üçün mümkündür.

## PHP/Laravel xüsusi on-call toolkit

Bookmark-da / əlində olmasını istədiyin şeylər:
- Laravel app health URL (versiya məlumatı ilə)
- Horizon dashboard
- Log aggregation (Kibana / Loki / Datadog)
- App, DB, Redis üçün Grafana dashboard-ları
- Kubernetes dashboard
- Runbook indeksi (`docs/runbooks/` repo-sunda markdown fayllar)
- Fövqəladə kontakt siyahısı
- Deploy pipeline (rollback düyməsi)

Sürətli Laravel diaqnostika script-i:
```bash
#!/bin/bash
# oncall-status.sh
echo "=== App Health ==="
curl -sf https://myapp.com/healthz || echo "FAIL"

echo "=== Horizon ==="
php artisan horizon:status

echo "=== Failed Jobs ==="
php artisan queue:failed | tail

echo "=== DB Connections ==="
mysql -e "SHOW GLOBAL STATUS LIKE 'Threads_connected';"

echo "=== Redis ==="
redis-cli PING
redis-cli INFO clients | grep connected_clients
```

## Yadda saxlanmalı real komandalar

```bash
# PagerDuty CLI
pd incident list --status triggered,acknowledged
pd incident ack --ids P1AB2CD
pd incident resolve --ids P1AB2CD
pd oncall list

# Opsgenie CLI (lamp)
lamp alert get --id ALERT_ID
lamp alert ack --id ALERT_ID

# Join incident by URL (quick mobile action)
# PagerDuty mobile → swipe to ack

# Quick log check from phone
ssh prod-api "tail -f /var/log/app/laravel.log | grep -i error"
```

### Handoff kanal konvensiyaları

```
#oncall-handoff — standing channel for shift reports
#incident-YYYYMMDD-description — per-incident
#eng-oncall — general chatter
```

## Müsahibə bucağı

"Sənin on-call təcrübən necədir?"

Güclü cavab:
- "[X il] ərzində 6 mühəndislik rotation-dayam. Primary + secondary, həftəlik shift-lər."
- "Düzgün handoff edirik — daimi kanalda yazılı xülasə, açıq məsələləri, son page-ləri və həftə konteksini əhatə edir."
- "Aldığım hər alert actionable olmalıdır. Boş yerə page-lənirəmsə, bu alert-də bug-dır, növbəti review üçün qeyd olunur."
- "Runbook-lar first-class-dır: hər alert birinə link verir, runbook-lar git-dədir, köhnələndə review olunur."
- "Alert fatique azalmasını idarə etmişəm: iki rüb ərzində silmə və tənzimləmə ilə həftəlik ~40 page-dən ~6-ya keçdik."
- "Kompensasiya vaxtı: yazılı siyasətimiz var — gecə page-ləndin = növbəti gün flex saatlar."

Bonus: "On-call rotation keyfiyyətini komanda sağlamlıq metriki kimi qiymətləndirirəm. İnsanlar yanırsa və ya shift-lərdən qorxursa, mühəndislik sistemi reliability-ni düzəltmək üçün signal göndərir, rotation üçün daha çox mühəndis işə götürmək üçün deyil."
