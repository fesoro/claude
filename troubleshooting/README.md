# Production Troubleshooting Playbooks

Senior-lər junior-lardan ən çox prod incident-lərdə fərqlənir. Bu folder:

- **Incident anında** nə etməli (panic yoxdur, adım-adım)
- **Tez-tez rast gələn fail pattern-ləri** və onların həlli
- **PHP/Laravel-ə spesifik** production debug texnikaları
- **Post-mortem və post-incident** proses

## Fayllar

### Incident anında
1. [Incident response — ilk 15 dəqiqə](incident-response-first-15min.md) — Səhifəyə gələndə nə etməli
2. [Severity səviyyələri (SEV1–SEV4)](severity-levels.md) — Nə zaman CEO-nu oyatmalı
3. [Incident elan etmək](declaring-incident.md) — Kimə xəbər, necə kommunikasiya
4. [Incident zamanı kommunikasiya](incident-communication.md) — Müştəri, rəhbərlik, komanda update-ləri
5. [Incident commander rolu](incident-commander.md) — Koordinatorluq rolu

### Diaqnoz
6. [Is it my service? — Triage axını](is-it-my-service.md) — "Mənimdir, yoxsa upstream-də?" təyin etmək
7. [Log analizi pattern-ləri](log-analysis-patterns.md) — grep, jq, strukturlaşmış log-lar
8. [Metric oxumaq (Grafana/Prometheus)](reading-metrics.md) — RED, USE, Four Golden Signals
9. [Distributed tracing debug](tracing-debug.md) — Hansı servis günahkar?
10. [Binary search debug](binary-search-debugging.md) — Hansı commit, hansı deploy, hansı feature flag?

### PHP/Laravel production
11. [PHP memory leak diaqnozu](php-memory-leak.md) — Uzun işləyən worker-lər, daemon
12. [PHP yüksək CPU](php-high-cpu.md) — XHProf, Blackfire production-da
13. [Gecə saat 3-də slow query](slow-query-diagnosis.md) — EXPLAIN, pt-query-digest, index-siz query
14. [Queue ilişib / backlog](queue-backlog.md) — Horizon, Supervisor, ilişmiş worker-lər
15. [Cache stampede / thundering herd](cache-stampede.md) — Lock, probabilistic early expiration
16. [OPcache fəlakəti](opcache-disaster.md) — Restart, file-based, preloading tələləri
17. [PHP-FPM pool təcili tuning](php-fpm-emergency.md) — pm.max_children, timeout
18. [Database connection tükəndi](db-connection-exhaustion.md) — PgBouncer, connection pooling həlləri

### Database
19. [Database təcili halları](database-emergencies.md) — Read replica lag, lock contention, replication qırılması
20. [Miqrasiya səhv getdi](migration-gone-wrong.md) — Lock-lar, uzun ALTER, rollback
21. [Redis SPOF kimi](redis-spof.md) — Redis düşdü, failover, data itkisi
22. [MySQL deadlock fırtınası](mysql-deadlocks.md) — Diaqnoz et, azalt, retry

### Deploy və rollback
23. [Pis deploy rollback](rollback-strategies.md) — Blue-green, canary, feature flag kill
24. [Migration rollback](migration-rollback.md) — Forward-only fəlsəfəsi vs real həyat
25. [Config dəyişikliyi fəlakəti](config-change-disaster.md) — Env var dəyişikliyi prod-u yıxdı

### Post-incident
26. [Post-mortem template](post-mortem-template.md) — Günahsız (blameless) struktur
27. [5 Whys texnikası](5-whys.md) — Kök səbəb analizi
28. [Action item-lərin davamı](action-items.md) — Gecə saat 3-də verilən sözlər
29. [Ümumi war stories](war-stories.md) — Sənayedəki real incident-lər və dərslər

### On-call
30. [On-call ən yaxşı təcrübələr](on-call.md) — Shift handoff, runbook, alert fatigue
31. [Yaxşı runbook yazmaq](writing-runbooks.md) — Gecə 3-ə uyğun struktur
32. [Alert keyfiyyəti](alert-quality.md) — Səs-küylü, action-lı, simptom vs səbəb

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
