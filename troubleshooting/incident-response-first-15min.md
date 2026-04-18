# Incident Response: First 15 Minutes

## Problem (nə görürsən)
Page işə düşdü. PagerDuty/Opsgenie səni oyatdı. Dashboard qırmızıdır. Slack "sayt işləmir?" mesajları ilə dolur. Səhnədə ilk insan sənsən. Növbəti 15 dəqiqə bunun qısa hekayə, yoxsa uzun hekayə olacağını müəyyən edir.

Ümumi ilk siqnallar:
- PagerDuty alert: `HighErrorRate`, `APILatencyP95`, `QueueBacklog`
- #support kanalında müştəri şikayətləri
- Exec Slack DM: "nə isə problem var?"
- Synthetic monitoring səhvi (Pingdom, Checkly, Datadog synthetics)

## Sürətli triage (ilk 5 dəqiqə)

### Dəqiqə 0-1: Page-i acknowledge et
Paging zəncirini dayandır. Escalation-u susdur. Alert-i hələ RESOLVE etmə — bu problem həll olunmamış incident-i bağlayır.

```bash
# PagerDuty CLI
pd incident ack --ids P1AB2CD

# Or click "Acknowledge" in the mobile app
```

### Dəqiqə 1-2: Incident kanalını aç
Hamının tapa biləcəyi adlandırma qaydası:

```
#incident-20260417-api-5xx-spike
#incident-YYYYMMDD-short-description
```

Alert linkini, dashboard linkini və öz adını ilk cavab verən kimi pin et.

### Dəqiqə 2-3: Dashboard-ları yoxla
Bu üçünü tab-larda aç:
- Service RED dashboard (Rate, Errors, Duration)
- Four Golden Signals (latency, traffic, errors, saturation)
- Deploy timeline (son 2 saat)

Əgər səhvlər məhz deploy baş verəndə başlayıbsa — artıq hipotezin var.

### Dəqiqə 3-5: SEV səviyyəsini qiymətləndir
Bax [severity-levels.md](severity-levels.md). Yüksək SEV seç — aşağı salmaq ucuzdur, gec qaldırmaq bahadır.

## Diaqnoz

### Son deploy-ları yoxla
```bash
# ArgoCD
argocd app history my-app --limit 5

# Kubernetes
kubectl rollout history deployment/api-service

# Laravel Forge / Envoyer / Deployer
# Check deploy history in dashboard

# Git
git log --oneline --since="2 hours ago"
```

Əgər deploy səhv pəncərəsi daxilində olubsa: **rollback kandidatı**. Hələ debug etmə — əvvəlcə rollback, sonra debug.

### Cavab verənləri topla
- Incident Commander (IC) — koordinasiya edir, debug ETMİR
- Scribe — kanalda timeline yazır
- Subject Matter Experts — IC tərəfindən öz sahələri üçün çağırılır

Əgər təksənsə, hər üçü sənsən. Dərhal #eng-oncall kanalında dəstək istə.

### Statusu çatdır
İlk mesaj şablonu:

```
INCIDENT DECLARED — SEV2
Summary: API returning 5xx for ~8% of requests since 14:32 UTC
IC: @orkhan
Scribe: @orkhan (temporary)
Channel: #incident-20260417-api-5xx
Dashboard: [link]
Next update: 15 min
```

## Fix (qanaxmanı dayandır)

Prioritetə görə sıralanıb:

1. **Son deploy-u rollback et** — deploy səbəbdirsə ən sürətlidir
2. **Feature flag çevir** — feature flag dəyişibsə
3. **Scale up** — saturation olanda (CPU, workers, connections)
4. **Pod/servisləri restart et** — memory leak üçün son variant
5. **Replica-ya failover** — DB və ya Redis primary problemli olduqda
6. **Shed load** — rate limit, aşağı prioritetli trafikə 503 qaytar

```bash
# Rollback Kubernetes
kubectl rollout undo deployment/api-service

# Rollback Laravel Forge deploy
# Click "Redeploy" on previous release in UI

# Scale PHP-FPM pods
kubectl scale deployment/php-fpm --replicas=20

# Restart all Horizon workers
php artisan horizon:terminate
# Supervisor will restart them
```

## Əsas səbəbin analizi

Sayt sabit olandan sonra, incident zamanı yox. Aktiv incident zamanı debug etmə — əvvəlcə mitigation.

İncident kanalında nə etdiyini və nə vaxt etdiyini hər şeyi logla. Scribe-nin timeline-ı post-mortem skeletinə çevrilir.

## Qarşısının alınması

- Ümumi ssenarilər üçün əvvəlcədən yazılmış runbook-lar (bax [writing-runbooks.md](writing-runbooks.md))
- Deploy rollback düyməsi < 60 saniyəyə işləməlidir
- Incident kanal şablonunda dashboard-lar bookmark-lanmış olmalıdır
- On-call rotasiyası test edilməlidir (tək insana asılılıq olmasın)
- Müntəzəm game day / chaos engineering məşqləri

## PHP/Laravel üçün qeydlər

Laravel-də incident zamanı ilk yoxlanacaq yerlər:
- `storage/logs/laravel.log` — son 200 sətir
- Horizon dashboard — failed job spike var?
- `php artisan queue:failed` — yeni failure axını?
- Redis `INFO clients` — connection spike?
- OPcache statusu — pis deploy zamanı cache reset olundu?

```bash
# Laravel tail with filter
tail -f storage/logs/laravel.log | grep -i "error\|exception\|fatal"

# Horizon status
php artisan horizon:status

# Failed jobs count
php artisan queue:failed | wc -l
```

## Yadda saxlanacaq komandalar

```bash
# PagerDuty
pd incident ack
pd incident resolve

# Kubernetes
kubectl get pods -n production | grep -v Running
kubectl logs -n production -l app=api --tail=100
kubectl rollout undo deployment/api
kubectl top pods -n production --sort-by=cpu

# Laravel
php artisan horizon:status
php artisan queue:failed
tail -f storage/logs/laravel.log

# MySQL
mysql -e "SHOW FULL PROCESSLIST" | grep -v Sleep

# Redis
redis-cli INFO clients
redis-cli INFO stats
```

## Interview sualı

"Production incident-i necə idarə etdiyin bir haldan danış."

Cavabını belə qur:
1. **Situation** — "14:32 UTC-də SEV2 vardı, API trafikin 8%-inə 5xx qaytarırdı"
2. **Task** — "On-call idim. İlk cavab verən. Tez mitigation lazım idi."
3. **Action** — "Page-i ack etdim, #incident kanalı açdım, deploy timeline-ı yoxladım, 14:30-da release gördüm. 4 dəqiqəyə rollback etdim."
4. **Result** — "14:40-a qədər səhvlər sıfıra düşdü. Ümumi təsir pəncərəsi: 8 dəqiqə."
5. **Learning** — "Post-mortem edge case üçün testin olmadığını göstərdi. Regression test əlavə etdim, plus canary deploy mərhələsi."

Müsahibə verənlər nə eşitmək istəyir: sakit, metodik, mitigation-first, kommunikasiya və sonradan strukturlu öyrənmə.
