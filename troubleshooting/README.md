# Production Troubleshooting Playbooks

Senior-lər junior-lardan ən çox prod incident-lərdə fərqlənir. Bu folder:

- **Incident anında** nə etməli (panic yoxdur, addım-addım)
- **Tez-tez rast gələn fail pattern-ləri** və onların həlli
- **PHP/Laravel-ə spesifik** production debug texnikaları
- **Post-mortem və post-incident** proses

---

## Junior ⭐

Hər developer bilməlidir.

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-severity-levels.md](01-severity-levels.md) | Severity Levels (SEV1–SEV4) — nə zaman kim oyandırılır |
| 02 | [02-reading-metrics.md](02-reading-metrics.md) | Metric Oxumaq — RED, USE, Four Golden Signals |
| 03 | [03-is-it-my-service.md](03-is-it-my-service.md) | Is It My Service? — triage axını |
| 04 | [04-incident-communication.md](04-incident-communication.md) | Incident Communication — müştəri, komanda update-ləri |
| 05 | [05-alert-quality.md](05-alert-quality.md) | Alert Quality — simptom vs səbəb, action-lı alert |

---

## Middle ⭐⭐

Prod-da işləyən developer-lər üçün.

| # | Fayl | Mövzu |
|---|------|-------|
| 06 | [06-on-call.md](06-on-call.md) | On-Call — shift handoff, runbook, alert fatigue |
| 07 | [07-incident-response-first-15min.md](07-incident-response-first-15min.md) | Incident Response: İlk 15 Dəqiqə — səhifə gələndə nə etməli |
| 08 | [08-declaring-incident.md](08-declaring-incident.md) | Incident Elan Etmək — kimə xəbər, kommunikasiya |
| 09 | [09-log-analysis-patterns.md](09-log-analysis-patterns.md) | Log Analysis Patterns — grep, jq, strukturlaşmış log-lar |
| 10 | [10-slow-query-diagnosis.md](10-slow-query-diagnosis.md) | Slow Query Diagnosis — EXPLAIN, pt-query-digest |
| 11 | [11-ssl-certificate-issues.md](11-ssl-certificate-issues.md) | SSL Certificate Issues — expire, renewal, Let's Encrypt |
| 12 | [12-disk-space-full.md](12-disk-space-full.md) | Disk Space Full — log accumulation, inode tükənməsi |
| 13 | [13-php-memory-leak.md](13-php-memory-leak.md) | PHP Memory Leak — uzun işləyən worker-lər, daemon |
| 14 | [14-queue-backlog.md](14-queue-backlog.md) | Queue Backlog — Horizon, Supervisor, ilişmiş worker-lər |
| 15 | [15-opcache-disaster.md](15-opcache-disaster.md) | OPcache Disaster — köhnə kod, deploy reset, preload tələləri |
| 16 | [16-php-fpm-emergency.md](16-php-fpm-emergency.md) | PHP-FPM Emergency — pm.max_children, timeout tuning |
| 17 | [17-db-connection-exhaustion.md](17-db-connection-exhaustion.md) | DB Connection Exhaustion — PgBouncer, pooling həlləri |
| 18 | [18-rollback-strategies.md](18-rollback-strategies.md) | Rollback Strategies — blue-green, canary, feature flag kill |

---

## Senior ⭐⭐⭐

Mürəkkəb diaqnoz və sistem-level düşüncə.

| # | Fayl | Mövzu |
|---|------|-------|
| 19 | [19-migration-gone-wrong.md](19-migration-gone-wrong.md) | Migration Gone Wrong — lock contention, uzun ALTER, rollback |
| 20 | [20-config-change-disaster.md](20-config-change-disaster.md) | Config Change Disaster — env var dəyişikliyi prod-u yıxdı |
| 21 | [21-binary-search-debugging.md](21-binary-search-debugging.md) | Binary Search Debugging — hansı commit, deploy, feature flag? |
| 22 | [22-tracing-debug.md](22-tracing-debug.md) | Distributed Tracing Debug — hansı servis günahkar? |
| 23 | [23-php-high-cpu.md](23-php-high-cpu.md) | PHP High CPU — XHProf, Blackfire production-da |
| 24 | [24-cache-stampede.md](24-cache-stampede.md) | Cache Stampede — lock, probabilistic early expiration |
| 25 | [25-database-emergencies.md](25-database-emergencies.md) | Database Emergencies — replica lag, lock contention, replication |
| 26 | [26-mysql-deadlocks.md](26-mysql-deadlocks.md) | MySQL Deadlocks — diaqnoz, azaltma, retry |
| 27 | [27-redis-spof.md](27-redis-spof.md) | Redis SPOF — Redis düşdü, failover, data itkisi |
| 28 | [28-migration-rollback.md](28-migration-rollback.md) | Migration Rollback — forward-only fəlsəfəsi vs real həyat |
| 29 | [29-third-party-service-failure.md](29-third-party-service-failure.md) | Third-Party Service Failure — Stripe/S3/email provider düşdü |
| 30 | [30-oom-kills.md](30-oom-kills.md) | OOM Kills — Linux OOM killer, container limits, memory leak |
| 31 | [31-network-timeout-storms.md](31-network-timeout-storms.md) | Network Timeout Storms — kaskad timeout, circuit breaker |

---

## Lead ⭐⭐⭐⭐

Incident idarəetməsi, proses, komandalararası koordinasiya.

| # | Fayl | Mövzu |
|---|------|-------|
| 32 | [32-incident-commander.md](32-incident-commander.md) | Incident Commander — koordinator rolu, qərar vermə |
| 33 | [33-post-mortem-template.md](33-post-mortem-template.md) | Post-mortem Template — blameless struktur |
| 34 | [34-5-whys.md](34-5-whys.md) | 5 Whys — kök səbəb analizi texnikası |
| 35 | [35-action-items.md](35-action-items.md) | Action Items — gecə saat 3-də verilən sözlər |

---

## Architect ⭐⭐⭐⭐⭐

Sistemi incident-dən öncə düzgün qurmaq.

| # | Fayl | Mövzu |
|---|------|-------|
| 36 | [36-writing-runbooks.md](36-writing-runbooks.md) | Writing Runbooks — gecə 3-ə uyğun runbook strukturu |
| 37 | [37-war-stories.md](37-war-stories.md) | War Stories — sənayedəki real incident-lər və dərslər |

---

## Reading Paths

### Yeni başlayan üçün (Junior → solid Middle)
01 → 02 → 03 → 04 → 07 → 09 → 10 → 13 → 14 → 18

### On-call keşik üçün hazırlıq
01 → 02 → 03 → 04 → 05 → 06 → 07 → 08 → 18 → 33 → 34

### PHP/Laravel prod incident-ləri
10 → 11 → 12 → 13 → 14 → 15 → 16 → 17 → 23 → 27

### Database emergency triage
10 → 17 → 19 → 25 → 26 → 27 → 28

### Sistemli düşünmə (Senior yolu)
21 → 22 → 24 → 29 → 30 → 31 → 32 → 33 → 34 → 36

### Müsahibə hazırlığı
02 → 07 → 21 → 33 → 34 → 37

---

## Əsas düşüncə tərzi

1. **Əvvəlcə qanaxmanı dayandır, kök səbəbi sonra başa düş.** Production-da rollback > debug.
2. **İşləri pisləşdirmə.** Nə etdiyini bilmirsənsə, bunu de və kömək istə.
3. **Erkən və tez-tez kommunikasiya et.** Sükut = qorxu. Hər 15 dəqiqədə bir update, "hələ araşdırırıq" olsa belə.
4. **Günahsız (blameless).** İnsanlar səhv edir; sistemlər səhv etməyi çətinləşdirməlidir. Sistem səviyyəsində fix tap.
5. **Runbook-u sonradan yaz.** Əgər sənin səhifəni çaldı, başqasının da çalacaq. Yaz ki, qalsın.

## Sürətli triage üçün "Golden Signals"

Google SRE kitabının terminologiyası:

1. **Latency** — cavab müddəti (p50, p95, p99)
2. **Traffic** — req/sec
3. **Errors** — 5xx, exception rate
4. **Saturation** — CPU%, memory%, disk IO, connection pool %

Grafana dashboard-da bu 4-ü gör — incident-lərin 90%-ində cavab buradadır.

## Səhifə gələndə sürətli qərar ağacı

```
Alert aldın
  ↓
Mənim servis-mi? → Yoxsa → (aid olan komandaya ötür, sən qoşul amma lead deyilsən)
  ↓ Hə
Severity nədir? → SEV1/2? → Incident elan et, commander təyin et
  ↓
İndi pisləşir? → Hə → Rollback variantları yoxla (feature flag, deploy, config)
  ↓
Stabilləşdi? → Hə → Observation mode; post-mortem planlaşdır
  ↓ Yox
Root cause axtarılır → log, metrik, trace → hipotez yoxla → fix → təsdiqlə
```

## Müsahibələr üçün

"Tell me about a production incident you handled" sualına bu folder hazırlayır. Nümunə cavab strukturu:

1. **Context**: Nə idi (servis, miqyas, vaxt).
2. **Detection**: Necə bildik (alert, müştəri report-u).
3. **Diagnosis**: Hansı hipotezləri yoxladıq, nə tapdıq.
4. **Mitigation**: Qanaxmanı dayandırma (rollback/flag).
5. **Root cause**: Sistem səviyyəsində səbəb.
6. **Action items**: Nə dəyişdirdik ki, təkrarlanmasın.

Hər hekayə bu 6 hissəyə bölünür. Yaxşı senior-un STAR method-un texniki versiyası.
