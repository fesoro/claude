# Kubernetes Jobs və CronJobs

## Nədir? (What is it?)

**Job** — bir və ya bir neçə pod-u icra edib, uğurla bitməsini təmin edən workload resource-dur. Pod crash olarsa, retry edir. Batch processing, data migration, one-off task-lar üçündür.

**CronJob** — vaxta əsaslanan Job yaradan resurs-dur (Linux cron-a bənzər). Database backup, report generation, cache cleanup kimi periodic task-lar üçün.

## Əsas Konseptlər

### 1. Job vs Deployment

| Xüsusiyyət | Deployment | Job |
|------------|------------|-----|
| Məqsəd | Long-running app | One-time task |
| Restart policy | Always | OnFailure / Never |
| Replica idarə | ReplicaSet | `completions` / `parallelism` |
| Finish state | Heç bitmir | Completed |

### 2. Job Tipləri

```
┌─────────────────────────────────────┐
│ 1. Non-parallel (default)            │
│    completions: 1, parallelism: 1    │
│    Bir pod, bir dəfə                 │
├─────────────────────────────────────┤
│ 2. Fixed completion count            │
│    completions: 5, parallelism: 2    │
│    5 uğurlu, eyni vaxtda max 2 pod   │
├─────────────────────────────────────┤
│ 3. Parallel with work queue          │
│    completions: null, parallelism: 3 │
│    Hər pod özü bilir nə vaxt bitəcək│
├─────────────────────────────────────┤
│ 4. Indexed Job (1.24+)               │
│    completionMode: Indexed           │
│    Hər pod öz index-ini bilir        │
└─────────────────────────────────────┘
```

## Job

### 1. Sadə Job

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: laravel-migration
  namespace: production
spec:
  template:
    spec:
      restartPolicy: OnFailure
      containers:
        - name: migrate
          image: myregistry/laravel:1.0.0
          command: ["php", "artisan", "migrate", "--force"]
          env:
            - name: DB_HOST
              value: mysql.production.svc.cluster.local
          envFrom:
            - secretRef:
                name: laravel-db
  backoffLimit: 4             # max 4 retry
  activeDeadlineSeconds: 600  # max 10 dəqiqə
  ttlSecondsAfterFinished: 86400  # 1 gün sonra sil
```

```bash
kubectl apply -f migration-job.yaml
kubectl get jobs
# NAME                 COMPLETIONS   DURATION   AGE
# laravel-migration    1/1           25s        1m

kubectl logs -l job-name=laravel-migration
```

### 2. Parallel Job (Fixed Completions)

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: image-processor
spec:
  completions: 100          # 100 uğurlu pod lazım
  parallelism: 5            # eyni anda 5 pod
  backoffLimit: 10
  template:
    spec:
      restartPolicy: OnFailure
      containers:
        - name: processor
          image: image-processor:1.0
          command: ["/app/process-one"]
          env:
            - name: TOTAL_ITEMS
              value: "100"
```

### 3. Work Queue Pattern

Hər pod queue-dan özünü yükləyir:

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: email-batch
spec:
  parallelism: 10          # 10 worker
  # completions yoxdur — queue boşaldıqda worker özü çıxır
  template:
    spec:
      restartPolicy: Never
      containers:
        - name: worker
          image: myregistry/laravel:1.0.0
          command: ["php", "artisan", "queue:work", "--stop-when-empty", "--queue=emails"]
          env:
            - name: REDIS_HOST
              value: redis-master
```

Worker code pattern:
```php
// artisan queue:work --stop-when-empty
// Queue boşaldıqda exit code 0 ilə çıxır
```

### 4. Indexed Job (K8s 1.24+)

Hər pod-a unikal index-i environment variable kimi verir:

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: sharded-etl
spec:
  completions: 10
  parallelism: 10
  completionMode: Indexed
  template:
    spec:
      restartPolicy: Never
      containers:
        - name: etl
          image: myregistry/etl:1.0
          command:
            - sh
            - -c
            - |
              echo "Processing shard $JOB_COMPLETION_INDEX of 10"
              php artisan etl:process --shard=$JOB_COMPLETION_INDEX --total=10
```

Hər pod `JOB_COMPLETION_INDEX` ilə öz shard-ını bilir (0-9).

### 5. backoffLimit və restartPolicy

```yaml
spec:
  backoffLimit: 6            # Pod-level retry limit
  activeDeadlineSeconds: 3600 # Total time limit
  template:
    spec:
      restartPolicy: OnFailure # Container restart (node-dan köçmür)
      # və ya:
      restartPolicy: Never     # Yeni pod yaradılır, köhnə saxlanır
```

**Fərq**:
- `OnFailure` — eyni pod restart, fərqli container exit
- `Never` — yeni pod yaranır, köhnə log-lar qalır (debug üçün yaxşı)

### 6. Pod Failure Policy (K8s 1.26+)

Spesifik exit code-da özünü aparmaq:

```yaml
spec:
  backoffLimit: 6
  podFailurePolicy:
    rules:
      # Exit 42 olarsa dərhal fail (retry etmə)
      - action: FailJob
        onExitCodes:
          containerName: main
          operator: In
          values: [42]
      # OOMKilled və ya SIGKILL — retry etmə
      - action: Ignore
        onPodConditions:
          - type: DisruptionTarget
  template:
    spec:
      restartPolicy: Never
      containers:
        - name: main
          image: myapp
```

## CronJob

### 1. Sadə CronJob

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: laravel-schedule
  namespace: production
spec:
  schedule: "* * * * *"      # hər dəqiqə
  timeZone: "Europe/Berlin"  # K8s 1.27+
  concurrencyPolicy: Forbid
  successfulJobsHistoryLimit: 3
  failedJobsHistoryLimit: 1
  startingDeadlineSeconds: 100
  jobTemplate:
    spec:
      backoffLimit: 2
      activeDeadlineSeconds: 300
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: schedule
              image: myregistry/laravel:1.0.0
              command: ["php", "artisan", "schedule:run"]
```

### 2. Schedule Syntax

```
┌───────────── minute (0 - 59)
│ ┌─────────── hour (0 - 23)
│ │ ┌───────── day of month (1 - 31)
│ │ │ ┌─────── month (1 - 12)
│ │ │ │ ┌───── day of week (0 - 6) (Sunday = 0)
│ │ │ │ │
* * * * *

Nümunələr:
0 2 * * *           # Hər gün 02:00
*/15 * * * *        # Hər 15 dəqiqə
0 */6 * * *         # Hər 6 saatdan bir
0 9 * * 1-5         # İş günlərində 09:00
0 0 1 * *           # Ayın 1-i gecə
@hourly             # Hər saat başı
@daily              # Hər gün
@weekly             # Hər həftə bazar günü
@monthly            # Hər ayın 1-i
```

### 3. concurrencyPolicy

| Policy | Davranış |
|--------|----------|
| `Allow` (default) | Paralel run-lara icazə — overlap ola bilər |
| `Forbid` | Əvvəlki bitməyibsə, yenisi skip olunur |
| `Replace` | Əvvəlkini öldürüb yenisini başlat |

Backup üçün `Forbid`, real-time sync üçün `Replace`.

### 4. startingDeadlineSeconds

Cluster qısa müddət offline olarsa, missed scheduling necə idarə olunur:

```yaml
startingDeadlineSeconds: 200
# Əgər scheduled time-dan 200s keçibsə, start etmə
```

Bu olmadan K8s 100+ missed run-ı bir anda start edə bilər ("thundering herd").

### 5. successfulJobsHistoryLimit

```yaml
successfulJobsHistoryLimit: 3   # son 3 uğurlu Job saxla
failedJobsHistoryLimit: 5       # son 5 fail Job saxla
```

Həddindən artıq Job cluster-i boğur.

### 6. suspend

Müvəqqəti dayandırmaq:

```yaml
spec:
  suspend: true
```

```bash
kubectl patch cronjob laravel-schedule -p '{"spec":{"suspend":true}}'
# İş saxlanır, yeni Job yaranmır
```

## Patterns

### 1. One-Off Migration Pattern

Deployment-dən əvvəl migration-ı Job kimi çalıştır:

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: migrate-{{ .Values.image.tag }}   # hər versiya üçün unikal
  annotations:
    "helm.sh/hook": pre-upgrade,pre-install
    "helm.sh/hook-weight": "-5"
    "helm.sh/hook-delete-policy": before-hook-creation,hook-succeeded
spec:
  backoffLimit: 2
  template:
    spec:
      restartPolicy: Never
      initContainers:
        - name: wait-db
          image: busybox
          command: ['sh', '-c', 'until nc -z mysql 3306; do sleep 2; done']
      containers:
        - name: migrate
          image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
          command: ["php", "artisan", "migrate", "--force"]
```

### 2. Fan-out Pattern (Work Queue)

Controller pod mesaj generate edir, worker-lar qəbul edir:

```yaml
---
# 1. Producer Job
apiVersion: batch/v1
kind: Job
metadata:
  name: producer
spec:
  template:
    spec:
      restartPolicy: OnFailure
      containers:
        - name: producer
          image: myapp
          command: ["php", "artisan", "queue:fill", "--count=1000"]
---
# 2. Consumer Job
apiVersion: batch/v1
kind: Job
metadata:
  name: consumer
spec:
  parallelism: 10
  template:
    spec:
      restartPolicy: OnFailure
      containers:
        - name: consumer
          image: myapp
          command: ["php", "artisan", "queue:work", "--stop-when-empty"]
```

### 3. Indexed Parallel Processing

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: user-export
spec:
  completions: 10
  parallelism: 10
  completionMode: Indexed
  template:
    spec:
      restartPolicy: Never
      containers:
        - name: exporter
          image: myregistry/laravel:1.0.0
          command:
            - php
            - artisan
            - users:export
            - --shard-index=$(JOB_COMPLETION_INDEX)
            - --shard-count=10
            - --output=s3://exports/users-$(JOB_COMPLETION_INDEX).csv
```

### 4. Chained Jobs

ArgoWorkflow və ya Argo CD ilə, yaxud manual:

```yaml
# Job 1: Extract
apiVersion: batch/v1
kind: Job
metadata:
  name: extract
spec:
  template:
    spec:
      restartPolicy: Never
      containers:
        - name: extract
          image: etl:1.0
          command: ["php", "artisan", "etl:extract"]
---
# Job 2: Transform (manual start sonra)
apiVersion: batch/v1
kind: Job
metadata:
  name: transform
```

Production-da Argo Workflows, Tekton istifadə olunur.

## Laravel Scheduler Müqayisəsi

### Laravel Schedule Klassik

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('backup:run')->daily()->at('02:00');
    $schedule->command('emails:send-digest')->weekly()->sundays()->at('09:00');
    $schedule->command('cache:prune-stale')->everyFifteenMinutes();
    $schedule->command('queue:monitor')->everyMinute();
}
```

Server-də cron:
```bash
* * * * * cd /path/to/laravel && php artisan schedule:run >> /dev/null 2>&1
```

### K8s CronJob ilə

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: laravel-scheduler
spec:
  schedule: "* * * * *"
  concurrencyPolicy: Forbid   # Artisan schedule özü overlap check edir
  jobTemplate:
    spec:
      backoffLimit: 1
      activeDeadlineSeconds: 300
      template:
        spec:
          restartPolicy: Never
          containers:
            - name: schedule
              image: myregistry/laravel:1.0.0
              command: ["php", "artisan", "schedule:run"]
```

### Hər Task Ayrı CronJob (Alternativ)

```yaml
---
apiVersion: batch/v1
kind: CronJob
metadata:
  name: laravel-backup
spec:
  schedule: "0 2 * * *"
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: backup
              image: myregistry/laravel:1.0.0
              command: ["php", "artisan", "backup:run"]
---
apiVersion: batch/v1
kind: CronJob
metadata:
  name: laravel-digest
spec:
  schedule: "0 9 * * 0"
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: digest
              image: myregistry/laravel:1.0.0
              command: ["php", "artisan", "emails:send-digest"]
```

### Müqayisə

| Yanaşma | Üstünlük | Dezavantaj |
|---------|----------|------------|
| Laravel `schedule:run` + K8s CronJob | Bütün schedule kod-da, bir yerdə | Bir pod hər dəqiqə start olur (overhead) |
| Hər task ayrı CronJob | K8s native, observable per-job | Kod dublikasiyası, Kernel.php-dən ayrı |
| K8s CronJob + Laravel artisan | Kombinasiya — kritik task-lər ayrı | Maintain çətindir |

**Tövsiyə**: Kiçik layihələrdə `schedule:run`. Kritik backup/report üçün ayrı CronJob (avto retry, observability).

## PHP/Laravel ilə İstifadə

### Laravel Database Backup CronJob

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: mysql-backup
  namespace: production
spec:
  schedule: "0 2 * * *"
  timeZone: "Europe/Berlin"
  concurrencyPolicy: Forbid
  successfulJobsHistoryLimit: 3
  failedJobsHistoryLimit: 5
  startingDeadlineSeconds: 300
  jobTemplate:
    spec:
      backoffLimit: 2
      activeDeadlineSeconds: 7200  # 2 saat
      template:
        spec:
          restartPolicy: OnFailure
          serviceAccountName: backup-sa
          containers:
            - name: backup
              image: myregistry/laravel:1.0.0
              command:
                - sh
                - -c
                - |
                  DATE=$(date +%Y-%m-%d_%H-%M)
                  mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME | \
                      gzip > /tmp/backup-$DATE.sql.gz
                  aws s3 cp /tmp/backup-$DATE.sql.gz s3://backups/mysql/
                  echo "Backup completed: $DATE"
              env:
                - name: DB_HOST
                  value: mysql.production.svc.cluster.local
                - name: DB_NAME
                  value: laravel
              envFrom:
                - secretRef:
                    name: mysql-credentials
              resources:
                requests:
                  memory: 256Mi
                  cpu: 200m
                limits:
                  memory: 1Gi
                  cpu: 1
```

### Laravel Queue Worker Job (Batch)

Gündəlik 10,000 email göndərmək:

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: daily-emails-{{ now | date '%Y%m%d' }}
spec:
  parallelism: 20
  backoffLimit: 3
  ttlSecondsAfterFinished: 86400
  template:
    spec:
      restartPolicy: OnFailure
      containers:
        - name: worker
          image: myregistry/laravel:1.0.0
          command:
            - php
            - artisan
            - queue:work
            - --queue=emails
            - --stop-when-empty
            - --max-jobs=500
            - --max-time=1800
          envFrom:
            - secretRef:
                name: laravel-env
          resources:
            requests:
              memory: 256Mi
              cpu: 250m
            limits:
              memory: 512Mi
              cpu: 1
```

### Laravel Migration (Helm Hook)

```yaml
{{- if .Values.migration.enabled }}
apiVersion: batch/v1
kind: Job
metadata:
  name: {{ .Release.Name }}-migrate-{{ .Values.image.tag }}
  annotations:
    "helm.sh/hook": pre-upgrade,pre-install
    "helm.sh/hook-weight": "-5"
    "helm.sh/hook-delete-policy": before-hook-creation
spec:
  backoffLimit: 2
  activeDeadlineSeconds: 600
  template:
    spec:
      restartPolicy: Never
      initContainers:
        - name: wait-db
          image: busybox:1.36
          command: ['sh', '-c', 'until nc -z {{ .Values.mysql.host }} 3306; do echo waiting; sleep 3; done']
      containers:
        - name: migrate
          image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
          command: ["php", "artisan", "migrate", "--force", "--seed={{ .Values.migration.seed }}"]
          envFrom:
            - secretRef:
                name: {{ .Release.Name }}-env
{{- end }}
```

## Interview Sualları

**1. Job və CronJob fərqi?**
Job — bir dəfə icra olunan task (migration, batch process). CronJob — vaxta əsasən periodic Job yaradan resurs (backup, report).

**2. `restartPolicy: OnFailure` və `Never` fərqi?**
`OnFailure` — eyni pod-da container restart (node dəyişmir, log silinir). `Never` — yeni pod yaranır, köhnə pod (və log) saxlanır — debug üçün yaxşıdır.

**3. `backoffLimit` nə edir?**
Pod neçə dəfə fail olarsa Job-u fail elan etmək. Default 6. Exponential backoff: 10s, 20s, 40s, 80s... arasında retry.

**4. `activeDeadlineSeconds` və `backoffLimit` arasında fərq?**
`backoffLimit` — retry sayı. `activeDeadlineSeconds` — toplam vaxt limiti (bütün retry-lər + running vaxtı daxil). Hansısa keçsə Job fail olur.

**5. `concurrencyPolicy` nə işə yarayır?**
CronJob-da əvvəlki Job hələ işləyirsə yenisi necə idarə olunacaq:
- `Allow`: paralel işlə
- `Forbid`: skip et
- `Replace`: köhnəni öldür, yenisini başlat

**6. Indexed Job nədir?**
K8s 1.24+. `completionMode: Indexed` — hər pod unikal `JOB_COMPLETION_INDEX` environment variable alır (0, 1, 2, ...). Shard-based processing üçün ideal — hər pod öz shard-ını bilir.

**7. `startingDeadlineSeconds` niyə vacibdir?**
Cluster downtime-dan sonra K8s bütün missed run-ları bir anda start edə bilər. `startingDeadlineSeconds` — scheduled time-dan nə qədər gec start-a icazə var. Bu olmadan 100 run birdən başlayar — thundering herd.

**8. `ttlSecondsAfterFinished` nə edir?**
Job bitdikdən N saniyə sonra Job və pod-lar avto silinir. cluster-i zibilsiz saxlayır. Default yoxdur — manual silmək lazımdır.

**9. CronJob-da timezone necə təyin edilir?**
K8s 1.27+ `spec.timeZone: "Europe/Berlin"` dəstəkləyir. Əvvəllər UTC default idi, app-də timezone math etmək lazım idi.

**10. Laravel `schedule:run` vs K8s CronJob-lar — hansı daha yaxşı?**
Tradeoff: `schedule:run` hər dəqiqə pod start — overhead. K8s CronJob per-task observability var amma kod dublikasiyası. Kiçik layihə → `schedule:run`. Mission-critical task (backup) → ayrı CronJob.

## Best Practices

1. **`ttlSecondsAfterFinished`** — həmişə təyin et (cluster təmiz qalsın)
2. **`activeDeadlineSeconds`** — infinite loop qarşısı
3. **`backoffLimit` kiçik** (2-3) — fail-fast
4. **`concurrencyPolicy: Forbid`** — backup/ETL üçün overlap qarşısı
5. **`startingDeadlineSeconds`** — thundering herd qarşısı
6. **`successfulJobsHistoryLimit: 3`** — çox history cluster-i boğur
7. **`restartPolicy: Never`** — debug üçün pod qalsın
8. **`initContainers`** — DB hazır olmayana qədər gözlə
9. **Resource limits** — node-un boş resursunu yeyir yoxsa
10. **ServiceAccount dedicated** — least privilege
11. **Indexed Job** — parallel batch processing üçün
12. **Observability** — Prometheus metric (kube_job_status_failed, kube_job_status_succeeded)
13. **Idempotent task-lar** — retry təhlükəsiz olsun
14. **Helm hook** — migration üçün pre-upgrade
15. **`--stop-when-empty`** — queue worker-lər üçün graceful exit
