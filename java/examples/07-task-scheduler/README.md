# Task Scheduler (⭐⭐⭐⭐ Lead)

Spring `@Scheduled` ilə background job sistemi. Cron ifadələri, job tracking, paralel execution qarşısının alınması, metrics dashboard.

## Öyrənilən Konseptlər

- `@Scheduled` — fixed rate, fixed delay, cron ifadəsi
- `@EnableScheduling` — scheduling aktiv etmək
- `@Async` — job-u ayrı thread-də işlət
- Job record tracking — hər job execution DB-də qeyd olunur
- Idempotency — eyni job iki dəfə işləməsin (`@SchedulerLock` pattern)
- `@Transactional` ilə job status yeniləmə
- Manual job trigger — endpoint vasitəsilə əl ilə işlət

## İşə Salma

```bash
cd java/examples/07-task-scheduler
./mvnw spring-boot:run
# → http://localhost:8080
# Schedulerlər avtomatik başlayır
```

## Endpoints

| Method | Path | Təsvir |
|--------|------|--------|
| GET | /api/jobs | Bütün job execution tarixçəsi |
| GET | /api/jobs?type=REPORT | Tip üzrə filtr |
| GET | /api/jobs/stats | Job statistikası |
| POST | /api/jobs/report/trigger | Report job-u əl ilə işlət |
| POST | /api/jobs/cleanup/trigger | Cleanup job-u əl ilə işlət |

## İstifadə Nümunəsi

```bash
# Job tarixçəsini gör
curl http://localhost:8080/api/jobs

# Report job-u əl ilə işlət
curl -X POST http://localhost:8080/api/jobs/report/trigger

# Statistika
curl http://localhost:8080/api/jobs/stats

# Tip üzrə filtr
curl "http://localhost:8080/api/jobs?type=CLEANUP"
```

## Cron Cədvəli

| Job | Cron | İzah |
|-----|------|------|
| DailyReport | `0 0 8 * * *` | Hər gün səhər 8-də |
| Cleanup | `0 */5 * * * *` | Hər 5 dəqiqədən bir |

> Dev mühitdə hər 30 saniyədə bir işlədilir (application.yml-dən konfiqurasiya olunur).

## Fixed Rate vs Cron

```java
@Scheduled(fixedRate = 5000)          // hər 5 saniyə
@Scheduled(fixedDelay = 5000)         // əvvəlki bitmişdən 5 saniyə sonra
@Scheduled(cron = "0 0 8 * * MON-FRI") // iş günlərinin sabahı
```

## İrəli Getmək Üçün

- ShedLock ilə distributed lock (cluster-də bir instance-da işlət)
- Spring Batch ilə böyük dataset-ləri emal et → `java/spring/81-spring-batch.md`
- Quartz Scheduler — daha güclü job management
