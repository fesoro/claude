# Container Orchestration Patterns (Konteyner Orkestrasiya Pattern-ləri)

## Nədir? (What is it?)

Container orchestration patterns — konteynerləşdirilmiş tətbiqlərin dizaynı və idarə olunması üçün sübut olunmuş arxitektura pattern-ləridir. Bu pattern-lər konteynerlərin necə birlikdə işləyəcəyini, necə kommunikasiya edəcəyini və lifecycle-ın necə idarə olunacağını təyin edir.

Bu pattern-lər Kubernetes-in Pod abstraksiyası üzərində qurulub — bir Pod daxilində çoxlu konteyner, Pod-lar arası əlaqə və iş axını pattern-ləri.

## Əsas Konseptlər (Key Concepts)

### 1. Sidecar Pattern

Sidecar — əsas konteynerə əlavə funksionallıq verən köməkçi konteynerdir. Eyni Pod-da işləyir, eyni network və storage-i paylaşır.

```
┌─────────────────────────────────┐
│            Pod                   │
│  ┌───────────┐ ┌──────────────┐ │
│  │   Main    │ │   Sidecar    │ │
│  │ Container │ │  Container   │ │
│  │           │ │              │ │
│  │ Laravel   │ │ Log shipper  │ │
│  │ PHP-FPM   │ │ (Fluentbit)  │ │
│  └───────────┘ └──────────────┘ │
│       │              │          │
│       └──── shared ──┘          │
│         volume/network          │
└─────────────────────────────────┘
```

**İstifadə halları:**
- Log collection (Fluentbit, Filebeat)
- Monitoring (Prometheus exporter)
- Proxy (Envoy, Istio sidecar)
- Sync (Git sync, config reload)
- TLS termination

```yaml
# Sidecar pattern — Log shipper
apiVersion: v1
kind: Pod
metadata:
  name: laravel-with-sidecar
spec:
  containers:
    # Əsas konteyner — Laravel
    - name: php-fpm
      image: mycompany/laravel:1.0.0
      volumeMounts:
        - name: log-volume
          mountPath: /var/www/html/storage/logs

    # Sidecar — Log shipper
    - name: log-shipper
      image: fluent/fluent-bit:latest
      volumeMounts:
        - name: log-volume
          mountPath: /var/log/laravel
          readOnly: true
        - name: fluentbit-config
          mountPath: /fluent-bit/etc/
      resources:
        requests:
          cpu: 50m
          memory: 64Mi

  volumes:
    - name: log-volume
      emptyDir: {}
    - name: fluentbit-config
      configMap:
        name: fluentbit-config
```

**Envoy Sidecar (Service Mesh):**

```yaml
# Istio sidecar injection — avtomatik əlavə olunur
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
  labels:
    app: laravel
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
      annotations:
        sidecar.istio.io/inject: "true"    # Istio sidecar inject
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          ports:
            - containerPort: 9000
# Istio avtomatik olaraq Envoy proxy sidecar əlavə edəcək
```

### 2. Ambassador Pattern

Ambassador — əsas konteyner üçün xarici servislərlə əlaqəni idarə edən proxy konteynerdir. Connection pooling, retry, circuit breaking kimi funksiyaları təmin edir.

```
┌─────────────────────────────────┐
│            Pod                   │
│  ┌───────────┐ ┌──────────────┐ │        ┌──────────┐
│  │   Main    │ │  Ambassador  │ │───────→│ External │
│  │ Container │→│  (proxy)     │ │        │ Service  │
│  │           │ │              │ │        └──────────┘
│  │ Laravel   │ │ localhost:   │ │
│  │ connects  │ │ 6379 → Redis│ │
│  │ to local  │ │ Cluster     │ │
│  └───────────┘ └──────────────┘ │
└─────────────────────────────────┘
```

```yaml
# Ambassador pattern — Redis proxy
apiVersion: v1
kind: Pod
metadata:
  name: laravel-with-ambassador
spec:
  containers:
    # Əsas konteyner — localhost:6379-a qoşulur
    - name: php-fpm
      image: mycompany/laravel:1.0.0
      env:
        - name: REDIS_HOST
          value: "localhost"    # Ambassador-a qoşulur
        - name: REDIS_PORT
          value: "6379"

    # Ambassador — Redis Cluster proxy
    - name: redis-proxy
      image: haproxy:alpine
      ports:
        - containerPort: 6379
      volumeMounts:
        - name: haproxy-config
          mountPath: /usr/local/etc/haproxy/
      resources:
        requests:
          cpu: 50m
          memory: 32Mi

  volumes:
    - name: haproxy-config
      configMap:
        name: redis-proxy-config
```

**İstifadə halları:**
- Database connection pooling (PgBouncer)
- Redis Cluster proxy
- API gateway (rate limiting, auth)
- Service mesh (Envoy)

### 3. Adapter Pattern

Adapter — əsas konteynerin çıxışını standart formata çevirən konteynerdir. Monitoring və logging standartlaşdırılması üçün istifadə olunur.

```
┌─────────────────────────────────┐
│            Pod                   │
│  ┌───────────┐ ┌──────────────┐ │        ┌──────────┐
│  │   Main    │ │   Adapter    │ │───────→│Prometheus│
│  │ Container │→│              │ │        │ Scrape   │
│  │           │ │ Custom format│ │        └──────────┘
│  │ PHP-FPM   │ │→ Prometheus  │ │
│  │ status    │ │  metrics     │ │
│  └───────────┘ └──────────────┘ │
└─────────────────────────────────┘
```

```yaml
# Adapter pattern — PHP-FPM metrics exporter
apiVersion: v1
kind: Pod
metadata:
  name: laravel-with-adapter
  annotations:
    prometheus.io/scrape: "true"
    prometheus.io/port: "9253"
spec:
  containers:
    # Əsas konteyner
    - name: php-fpm
      image: mycompany/laravel:1.0.0
      ports:
        - containerPort: 9000

    # Adapter — PHP-FPM metrics → Prometheus format
    - name: php-fpm-exporter
      image: hipages/php-fpm_exporter:latest
      ports:
        - containerPort: 9253
      env:
        - name: PHP_FPM_SCRAPE_URI
          value: "tcp://localhost:9000/status"
        - name: PHP_FPM_FIX_PROCESS_COUNT
          value: "true"
      resources:
        requests:
          cpu: 50m
          memory: 32Mi

    # Adapter — Nginx metrics → Prometheus format
    - name: nginx-exporter
      image: nginx/nginx-prometheus-exporter:latest
      ports:
        - containerPort: 9113
      args:
        - -nginx.scrape-uri=http://localhost:8080/stub_status
```

**İstifadə halları:**
- Metrics format çevirməsi (custom → Prometheus)
- Log format standartlaşdırılması
- Protocol çevirməsi (gRPC → REST)
- Data transformation

### 4. Init Container Pattern

Init container — əsas konteynerlərdən əvvəl işləyən və tamamlanan konteynerdir. Hazırlıq işləri üçün istifadə olunur.

```
┌──────────────────────────────────────────────────────┐
│ Pod Lifecycle                                         │
│                                                       │
│ Init 1    Init 2         Main Container              │
│ [migrate] [wait-db]      [php-fpm]                   │
│ ────────→────────→────────────────────────→           │
│ Sıralı    Sıralı   Init-lər bitdikdən sonra başlayır│
└──────────────────────────────────────────────────────┘
```

```yaml
# Init Container — Database migration və dependency wait
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      initContainers:
        # 1. Database-in hazır olmasını gözlə
        - name: wait-for-db
          image: busybox:1.36
          command: ['sh', '-c',
            'until nc -z mysql-service 3306; do echo "Waiting for MySQL..."; sleep 2; done']

        # 2. Redis-in hazır olmasını gözlə
        - name: wait-for-redis
          image: busybox:1.36
          command: ['sh', '-c',
            'until nc -z redis-service 6379; do echo "Waiting for Redis..."; sleep 2; done']

        # 3. Database migration
        - name: migrate
          image: mycompany/laravel:1.0.0
          command: ['php', 'artisan', 'migrate', '--force']
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret

        # 4. Cache warm-up
        - name: cache-warmup
          image: mycompany/laravel:1.0.0
          command: ['sh', '-c',
            'php artisan config:cache && php artisan route:cache && php artisan view:cache']
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret

      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          ports:
            - containerPort: 9000
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret
```

**İstifadə halları:**
- Database-in hazır olmasını gözləmək
- Migration işlətmək
- Config faylları yükləmək
- Permission-ları düzəltmək
- Secret-ləri xarici mənbədən çəkmək

### 5. Job və CronJob Patterns

#### Job — Bir dəfəlik tapşırıqlar

```yaml
# Database seeding job
apiVersion: batch/v1
kind: Job
metadata:
  name: laravel-seed
spec:
  ttlSecondsAfterFinished: 3600    # 1 saat sonra Job silinsin
  backoffLimit: 3                   # 3 dəfə cəhd et
  template:
    spec:
      containers:
        - name: seeder
          image: mycompany/laravel:1.0.0
          command: ["php", "artisan", "db:seed", "--force"]
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret
      restartPolicy: Never

---
# Parallel Job — data processing
apiVersion: batch/v1
kind: Job
metadata:
  name: process-reports
spec:
  parallelism: 5           # 5 Pod paralel işləsin
  completions: 20          # Cəmi 20 tapşırıq tamamlansın
  template:
    spec:
      containers:
        - name: processor
          image: mycompany/laravel:1.0.0
          command: ["php", "artisan", "reports:process"]
      restartPolicy: Never
```

#### CronJob — Dövri tapşırıqlar

```yaml
# Laravel Scheduler
apiVersion: batch/v1
kind: CronJob
metadata:
  name: laravel-scheduler
spec:
  schedule: "* * * * *"          # Hər dəqiqə
  concurrencyPolicy: Forbid       # Əvvəlki bitməyibsə, yenisini başlatma
  successfulJobsHistoryLimit: 3
  failedJobsHistoryLimit: 3
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: scheduler
              image: mycompany/laravel:1.0.0
              command: ["php", "artisan", "schedule:run"]
              envFrom:
                - configMapRef:
                    name: laravel-config
                - secretRef:
                    name: laravel-secret
              resources:
                requests:
                  cpu: 100m
                  memory: 128Mi
          restartPolicy: OnFailure

---
# Database backup
apiVersion: batch/v1
kind: CronJob
metadata:
  name: db-backup
spec:
  schedule: "0 2 * * *"           # Hər gecə saat 2:00
  concurrencyPolicy: Forbid
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: backup
              image: mysql:8.0
              command:
                - /bin/sh
                - -c
                - |
                  mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS \
                    --all-databases | gzip > /backup/db-$(date +%Y%m%d-%H%M).sql.gz
              envFrom:
                - secretRef:
                    name: db-backup-secret
              volumeMounts:
                - name: backup
                  mountPath: /backup
          volumes:
            - name: backup
              persistentVolumeClaim:
                claimName: backup-pvc
          restartPolicy: OnFailure

---
# Cache cleanup
apiVersion: batch/v1
kind: CronJob
metadata:
  name: cache-cleanup
spec:
  schedule: "0 */6 * * *"        # Hər 6 saatda
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: cleanup
              image: mycompany/laravel:1.0.0
              command: ["php", "artisan", "cache:prune-stale-tags"]
              envFrom:
                - configMapRef:
                    name: laravel-config
          restartPolicy: OnFailure
```

**ConcurrencyPolicy:**

| Policy | Təsvir |
|--------|--------|
| Allow | Eyni anda çoxlu Job işləyə bilər (default) |
| Forbid | Əvvəlki bitməyibsə yenisini başlatma |
| Replace | Əvvəlkini dayandır, yenisini başlat |

### 6. DaemonSet Pattern

DaemonSet — hər node-da (və ya seçilmiş node-larda) tam bir Pod işlədilməsini təmin edir. Node əlavə olunduqda avtomatik Pod yaradılır.

```yaml
# Monitoring agent — hər node-da
apiVersion: apps/v1
kind: DaemonSet
metadata:
  name: node-exporter
  namespace: monitoring
spec:
  selector:
    matchLabels:
      app: node-exporter
  template:
    metadata:
      labels:
        app: node-exporter
    spec:
      hostNetwork: true
      hostPID: true
      containers:
        - name: node-exporter
          image: prom/node-exporter:latest
          ports:
            - containerPort: 9100
              hostPort: 9100
          resources:
            requests:
              cpu: 50m
              memory: 64Mi
          volumeMounts:
            - name: proc
              mountPath: /host/proc
              readOnly: true
            - name: sys
              mountPath: /host/sys
              readOnly: true
      volumes:
        - name: proc
          hostPath:
            path: /proc
        - name: sys
          hostPath:
            path: /sys
      tolerations:
        - operator: Exists    # Bütün node-larda, taint olsa belə

---
# Log collector — hər node-da
apiVersion: apps/v1
kind: DaemonSet
metadata:
  name: fluentbit
  namespace: logging
spec:
  selector:
    matchLabels:
      app: fluentbit
  template:
    metadata:
      labels:
        app: fluentbit
    spec:
      containers:
        - name: fluentbit
          image: fluent/fluent-bit:latest
          volumeMounts:
            - name: varlog
              mountPath: /var/log
              readOnly: true
            - name: containers
              mountPath: /var/lib/docker/containers
              readOnly: true
            - name: config
              mountPath: /fluent-bit/etc/
          resources:
            requests:
              cpu: 100m
              memory: 128Mi
      volumes:
        - name: varlog
          hostPath:
            path: /var/log
        - name: containers
          hostPath:
            path: /var/lib/docker/containers
        - name: config
          configMap:
            name: fluentbit-config
```

**DaemonSet istifadə halları:**
- Monitoring agent (node-exporter, datadog-agent)
- Log collector (fluentbit, filebeat)
- Storage daemon (ceph, glusterd)
- Network plugin (calico, flannel)
- Security agent (falco)

### 7. Multi-Container Communication Patterns

```yaml
# Shared volume ilə kommunikasiya
apiVersion: v1
kind: Pod
metadata:
  name: laravel-full
spec:
  containers:
    # PHP-FPM
    - name: php-fpm
      image: mycompany/laravel:1.0.0
      volumeMounts:
        - name: app-code
          mountPath: /var/www/html

    # Nginx — PHP-FPM-ə fastcgi ilə qoşulur (localhost:9000)
    - name: nginx
      image: nginx:alpine
      ports:
        - containerPort: 80
      volumeMounts:
        - name: app-code
          mountPath: /var/www/html
          readOnly: true
        - name: nginx-config
          mountPath: /etc/nginx/conf.d/

  volumes:
    - name: app-code
      emptyDir: {}
    - name: nginx-config
      configMap:
        name: nginx-config
```

## Praktiki Nümunələr (Practical Examples)

### Tam Laravel Production Pattern-ləri

```yaml
# Bütün pattern-lərin birlikdə istifadəsi
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-production
spec:
  replicas: 5
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
      annotations:
        prometheus.io/scrape: "true"
        prometheus.io/port: "9253"
    spec:
      # Init Containers — hazırlıq
      initContainers:
        - name: wait-for-deps
          image: busybox:1.36
          command: ['sh', '-c',
            'until nc -z mysql-service 3306 && nc -z redis-service 6379; do sleep 2; done']

        - name: migrate
          image: mycompany/laravel:1.0.0
          command: ['php', 'artisan', 'migrate', '--force']
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret

      # Main + Sidecar + Adapter Containers
      containers:
        # Main — PHP-FPM
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          ports:
            - containerPort: 9000
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret
          volumeMounts:
            - name: logs
              mountPath: /var/www/html/storage/logs
          resources:
            requests:
              cpu: 250m
              memory: 256Mi
            limits:
              cpu: 1000m
              memory: 512Mi

        # Sidecar — Log shipper
        - name: log-shipper
          image: fluent/fluent-bit:latest
          volumeMounts:
            - name: logs
              mountPath: /var/log/laravel
              readOnly: true
          resources:
            requests:
              cpu: 50m
              memory: 64Mi

        # Adapter — Metrics exporter
        - name: metrics
          image: hipages/php-fpm_exporter:latest
          ports:
            - containerPort: 9253
          env:
            - name: PHP_FPM_SCRAPE_URI
              value: "tcp://localhost:9000/status"
          resources:
            requests:
              cpu: 30m
              memory: 32Mi

      volumes:
        - name: logs
          emptyDir: {}
```

## PHP/Laravel ilə İstifadə (Usage with PHP/Laravel)

### Laravel Horizon Deployment Pattern

```yaml
# Horizon — dedicated deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-horizon
spec:
  replicas: 1              # Horizon tək instance işləyir
  strategy:
    type: Recreate         # Rolling update deyil
  selector:
    matchLabels:
      app: laravel
      component: horizon
  template:
    metadata:
      labels:
        app: laravel
        component: horizon
    spec:
      terminationGracePeriodSeconds: 120
      containers:
        - name: horizon
          image: mycompany/laravel:1.0.0
          command: ["php", "artisan", "horizon"]
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret
          livenessProbe:
            exec:
              command: ["php", "artisan", "horizon:status"]
            periodSeconds: 30
          lifecycle:
            preStop:
              exec:
                command: ["php", "artisan", "horizon:terminate"]
```

### Websocket Server Pattern

```yaml
# Laravel Reverb / Soketi
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-websocket
spec:
  replicas: 2
  selector:
    matchLabels:
      app: laravel
      component: websocket
  template:
    metadata:
      labels:
        app: laravel
        component: websocket
    spec:
      containers:
        - name: reverb
          image: mycompany/laravel:1.0.0
          command: ["php", "artisan", "reverb:start", "--host=0.0.0.0"]
          ports:
            - containerPort: 8080
          readinessProbe:
            tcpSocket:
              port: 8080
            periodSeconds: 5

---
apiVersion: v1
kind: Service
metadata:
  name: websocket-service
spec:
  selector:
    app: laravel
    component: websocket
  ports:
    - port: 8080
      targetPort: 8080
```

## Interview Sualları (Interview Questions)

### S1: Sidecar pattern nədir və nə vaxt istifadə olunur?
**C:** Sidecar — əsas konteynerə əlavə funksionallıq verən köməkçi konteynerdir. Eyni Pod-da olduğu üçün network və storage paylaşır. İstifadə halları: log collection (Fluentbit), monitoring (metrics exporter), proxy (Envoy/Istio), TLS termination. Əsas prinsipi: əsas tətbiqin kodunu dəyişmədən funksionallıq əlavə etmək.

### S2: Init container ilə sidecar container arasında fərq nədir?
**C:** Init container başlanğıcda işləyir və tamamlanır — əsas container başlamadan əvvəl. Sidecar isə əsas container ilə paralel işləyir və Pod-un bütün ömrü boyunca aktiv qalır. Init container: migration, dependency wait. Sidecar: log shipping, monitoring, proxy.

### S3: Ambassador pattern nə üçün istifadə olunur?
**C:** Əsas konteyner üçün xarici servislərlə əlaqəni abstrakt edən proxy konteynerdir. Əsas konteyner `localhost`-a qoşulur, ambassador isə xarici servisə yönləndirir. İstifadə halları: connection pooling (PgBouncer), Redis Cluster proxy, service discovery. Əsas konteynerin xarici servis detaylarını bilməsi lazım olmur.

### S4: CronJob concurrencyPolicy seçimləri nədir?
**C:** Allow — eyni anda çoxlu Job işləyə bilər (default). Forbid — əvvəlki Job bitməyibsə yenisini başlatmır (idempotent olmayan tapşırıqlar üçün, məsələn billing). Replace — əvvəlki Job-u dayandırıb yenisini başladır. Laravel scheduler üçün Forbid ən uyğundur.

### S5: DaemonSet nə vaxt istifadə olunur?
**C:** Hər node-da bir nüsxə lazım olanda: monitoring agent (Prometheus node-exporter), log collector (Fluentbit), storage daemon, network plugin. DaemonSet node əlavə/silinəndə avtomatik Pod yaradır/silir. `tolerations` ilə tainted node-larda da işləyə bilər.

### S6: Adapter pattern sidecar-dan necə fərqlənir?
**C:** Texniki olaraq eyni mexanizmdir (Pod daxilində əlavə konteyner). Fərq məqsəddədir: Sidecar əsas konteynerə funksionallıq əlavə edir (log shipping, proxy). Adapter isə əsas konteynerin çıxışını standart formata çevirir (PHP-FPM metrics → Prometheus format). Adapter "translator" rolunu oynayır.

### S7: Kubernetes Job-un restartPolicy seçimləri nədir?
**C:** `Never` — fail edərsə yeni Pod yaradılır (debug üçün yaxşı, köhnə Pod qalır). `OnFailure` — eyni Pod-da yenidən başladır (disk space friendly). Job-larda `Always` qəbul olunmur. `backoffLimit` ilə maksimum retry sayı təyin olunur. `ttlSecondsAfterFinished` ilə bitmiş Job avtomatik silinir.

### S8: Multi-container Pod-da konteyerlər necə kommunikasiya edir?
**C:** Üç yolla: 1) localhost — eyni Pod-dakı konteynerlər eyni network namespace paylaşır, `localhost:port` ilə əlaqə qurur. 2) Shared volume — `emptyDir` ilə fayl paylaşımı. 3) Process namespace sharing — `shareProcessNamespace: true` ilə prosesləri görə bilər. Ən çox istifadə olunan: localhost və shared volume.

## Best Practices

1. **Sidecar-ları kiçik və yüngül saxlayın** — resource limits qoyun
2. **Init container-da dependency wait edin** — əsas konteyner hazır dependency-lərlə başlasın
3. **CronJob-larda Forbid concurrency istifadə edin** — overlap olmasm
4. **DaemonSet-lərdə tolerations doğru qoyun** — lazım olan node-larda işləsin
5. **Job-larda backoffLimit qoyun** — sonsuz retry olmasın
6. **ttlSecondsAfterFinished istifadə edin** — bitmiş Job-lar yığılmasın
7. **Adapter pattern-i monitoring üçün istifadə edin** — standart Prometheus metrics
8. **Shared volume üçün emptyDir istifadə edin** — Pod restart-da itirilməsi problem deyilsə
9. **Init container-ları sıralı düşünün** — birincisi bitməsə ikincisi başlamır
10. **Sidecar resource-larını hesaba alın** — Pod-un total resource tələbi artır
