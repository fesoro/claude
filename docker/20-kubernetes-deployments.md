# Kubernetes Deployments (Kubernetes Deployment-lər)

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

Kubernetes Deployment — Pod-ların deklarativ idarə olunmasını təmin edən resursdur. Deployment-lər rolling update, rollback, scaling və versiya idarəetməsini avtomatikləşdirir. Produksiya mühitlərində tətbiqlərin deploy olunmasının əsas yoludur.

Deployment → ReplicaSet → Pod zənciri ilə işləyir. Siz Deployment-ə istədiyiniz vəziyyəti (desired state) təsvir edirsiniz, Kubernetes onu gerçəkləşdirir.

## Əsas Konseptlər

### 1. Rolling Update

Default deployment strategiyasıdır. Köhnə Pod-ları tədricən yeniləri ilə əvəz edir — downtime olmur.

```yaml
# rolling-update-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 4
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1          # Əlavə olaraq neçə Pod yaradıla bilər (25% və ya say)
      maxUnavailable: 1    # Eyni anda neçə Pod unavailable ola bilər
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          ports:
            - containerPort: 9000
```

```
Rolling Update prosesi (replicas=4, maxSurge=1, maxUnavailable=1):

Əvvəl:  [v1] [v1] [v1] [v1]
Step 1:  [v1] [v1] [v1] [--] [v2]    ← 1 köhnə silinir, 1 yeni əlavə olur
Step 2:  [v1] [v1] [--] [v2] [v2]
Step 3:  [v1] [--] [v2] [v2] [v2]
Step 4:  [--] [v2] [v2] [v2] [v2]
Sonra:   [v2] [v2] [v2] [v2]
```

```bash
# Rolling update başlatmaq (image dəyişmək)
kubectl set image deployment/laravel-app \
    php-fpm=mycompany/laravel:1.1.0

# və ya YAML-ı edit edib apply etmək
kubectl apply -f rolling-update-deployment.yaml

# Update statusunu izləmək
kubectl rollout status deployment/laravel-app

# Rollout tarixçəsi
kubectl rollout history deployment/laravel-app
kubectl rollout history deployment/laravel-app --revision=2
```

### 2. Rollback

```bash
# Son deployment-ə rollback
kubectl rollout undo deployment/laravel-app

# Müəyyən revision-a rollback
kubectl rollout undo deployment/laravel-app --to-revision=3

# Rollout-u dayandırmaq (canary testing üçün)
kubectl rollout pause deployment/laravel-app

# Davam etdirmək
kubectl rollout resume deployment/laravel-app
```

### 3. Recreate Strategy

Bütün köhnə Pod-ları öldürüb, sonra yenilərini yaradır. Downtime olur, amma version conflict olmur.

```yaml
# recreate-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  strategy:
    type: Recreate    # Bütün köhnələri sil, sonra yenilərini yarat
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
```

```
Recreate prosesi:
Əvvəl:  [v1] [v1] [v1]
Step 1:  [--] [--] [--]    ← Hamısı silinir (DOWNTIME!)
Step 2:  [v2] [v2] [v2]    ← Hamısı yaradılır
```

**Nə vaxt Recreate istifadə etmək?**
- Database migration zamanı (köhnə və yeni versiya eyni anda işləməməlidir)
- Shared volume ilə version conflict olduqda
- Development mühitlərində

### 4. Scaling

#### Manual Scaling

```bash
# Replica sayını dəyişmək
kubectl scale deployment/laravel-app --replicas=10

# YAML ilə
kubectl patch deployment/laravel-app -p '{"spec":{"replicas":10}}'

# Conditional scaling (yalnız cari replicas düzgündürsə)
kubectl scale deployment/laravel-app --current-replicas=3 --replicas=5
```

#### Horizontal Pod Autoscaler (HPA)

```yaml
# hpa.yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel-app
  minReplicas: 3
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70     # CPU 70%-dən çox olanda scale up
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 60
      policies:
        - type: Pods
          value: 4                   # Maksimum 4 Pod əlavə et
          periodSeconds: 60
    scaleDown:
      stabilizationWindowSeconds: 300  # 5 dəqiqə gözlə
      policies:
        - type: Percent
          value: 25                  # Hər dəfə 25% azalt
          periodSeconds: 60
```

```bash
# HPA yaratmaq (sadə yol)
kubectl autoscale deployment/laravel-app \
    --min=3 --max=20 --cpu-percent=70

# HPA statusu
kubectl get hpa
kubectl describe hpa laravel-hpa

# Nümunə çıxış:
# NAME          REFERENCE          TARGETS   MINPODS   MAXPODS   REPLICAS
# laravel-hpa   Deployment/laravel  45%/70%   3         20        5
```

**HPA üçün metrics-server lazımdır:**

```bash
# metrics-server quraşdırma
kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml

# Yoxlamaq
kubectl top nodes
kubectl top pods
```

### 5. Resource Requests və Limits

```yaml
# resources.yaml
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
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          resources:
            requests:
              memory: "128Mi"    # Scheduler buna əsasən node seçir
              cpu: "250m"        # 250 millicpu = 0.25 CPU
            limits:
              memory: "512Mi"    # Bu limitdən çox istifadə edərsə OOMKill
              cpu: "1000m"       # 1 CPU — throttle olur (öldürülmür)
```

```
Resource anlayışları:

CPU:
  1000m = 1 CPU core
  500m  = 0.5 CPU core
  250m  = 0.25 CPU core

Memory:
  128Mi = 128 Mebibytes (~134 MB)
  256Mi = 256 Mebibytes
  1Gi   = 1 Gibibyte (~1.07 GB)

Requests vs Limits:
┌─────────────────────────────────────────────────────┐
│ Requests  │ Scheduler üçün — Pod-u harada            │
│           │ yerləşdirəcəyini qərar verir             │
├───────────┼──────────────────────────────────────────┤
│ Limits    │ Runtime üçün — Pod bu limitdən çox        │
│           │ istifadə edə bilməz                       │
│           │ Memory limit → OOMKill                    │
│           │ CPU limit → Throttle                      │
└───────────┴──────────────────────────────────────────┘
```

**LimitRange (namespace-level default):**

```yaml
apiVersion: v1
kind: LimitRange
metadata:
  name: default-limits
  namespace: production
spec:
  limits:
    - default:
        memory: "256Mi"
        cpu: "500m"
      defaultRequest:
        memory: "128Mi"
        cpu: "250m"
      type: Container
```

**ResourceQuota (namespace-level total):**

```yaml
apiVersion: v1
kind: ResourceQuota
metadata:
  name: production-quota
  namespace: production
spec:
  hard:
    requests.cpu: "10"
    requests.memory: "20Gi"
    limits.cpu: "20"
    limits.memory: "40Gi"
    pods: "50"
```

### 6. Liveness, Readiness və Startup Probes

```yaml
# probes.yaml
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
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          ports:
            - containerPort: 9000

          # Startup Probe — başlanğıcda yavaş tətbiqlər üçün
          startupProbe:
            httpGet:
              path: /health
              port: 9000
            failureThreshold: 30      # 30 * 10s = 5 dəq gözlə
            periodSeconds: 10

          # Liveness Probe — konteyner sağdırmı?
          livenessProbe:
            httpGet:
              path: /health
              port: 9000
            initialDelaySeconds: 10
            periodSeconds: 15
            timeoutSeconds: 5
            failureThreshold: 3       # 3 ardıcıl fail → restart

          # Readiness Probe — trafik qəbul edə bilərmi?
          readinessProbe:
            httpGet:
              path: /ready
              port: 9000
            initialDelaySeconds: 5
            periodSeconds: 5
            timeoutSeconds: 3
            failureThreshold: 3       # 3 fail → endpoint-dən çıxar
```

**Probe tipləri:**

```yaml
# HTTP GET
livenessProbe:
  httpGet:
    path: /health
    port: 9000
    httpHeaders:
      - name: Host
        value: localhost

# TCP Socket
livenessProbe:
  tcpSocket:
    port: 9000

# Exec command
livenessProbe:
  exec:
    command:
      - php
      - artisan
      - health:check

# gRPC (K8s 1.27+)
livenessProbe:
  grpc:
    port: 50051
```

### 7. Deployment Strategiyaları (Advanced)

#### Blue-Green Deployment

```yaml
# blue-deployment.yaml (cari versiya)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-blue
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
      version: blue
  template:
    metadata:
      labels:
        app: laravel
        version: blue
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0

---
# green-deployment.yaml (yeni versiya)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-green
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
      version: green
  template:
    metadata:
      labels:
        app: laravel
        version: green
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.1.0

---
# Service — selector ilə switch
apiVersion: v1
kind: Service
metadata:
  name: laravel-service
spec:
  selector:
    app: laravel
    version: blue    # blue → green dəyişməklə keçid edirik
  ports:
    - port: 80
      targetPort: 9000
```

```bash
# Blue-dən Green-ə keçid
kubectl patch service laravel-service \
    -p '{"spec":{"selector":{"version":"green"}}}'

# Problem olarsa rollback (Green-dən Blue-a)
kubectl patch service laravel-service \
    -p '{"spec":{"selector":{"version":"blue"}}}'
```

#### Canary Deployment

```yaml
# Stable deployment (90% traffic)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-stable
spec:
  replicas: 9
  selector:
    matchLabels:
      app: laravel
      track: stable
  template:
    metadata:
      labels:
        app: laravel
        track: stable
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0

---
# Canary deployment (10% traffic)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-canary
spec:
  replicas: 1
  selector:
    matchLabels:
      app: laravel
      track: canary
  template:
    metadata:
      labels:
        app: laravel
        track: canary
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.1.0

---
# Service — hər iki deployment-ə point edir (app label ilə)
apiVersion: v1
kind: Service
metadata:
  name: laravel-service
spec:
  selector:
    app: laravel    # Həm stable, həm canary-ə point edir
  ports:
    - port: 80
      targetPort: 9000
# 9/10 Pod v1.0.0, 1/10 Pod v1.1.0 → ~10% canary traffic
```

## Praktiki Nümunələr

### Laravel Production Deployment

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
  namespace: production
  annotations:
    kubernetes.io/change-cause: "Deploy v1.2.0 - add user notifications"
spec:
  replicas: 5
  revisionHistoryLimit: 10
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 2
      maxUnavailable: 0    # 0 = heç bir Pod unavailable olmamalı
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
        version: v1.2.0
    spec:
      terminationGracePeriodSeconds: 60
      initContainers:
        - name: migrate
          image: mycompany/laravel:1.2.0
          command: ["php", "artisan", "migrate", "--force"]
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.2.0
          ports:
            - containerPort: 9000
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret
          resources:
            requests:
              memory: "256Mi"
              cpu: "500m"
            limits:
              memory: "512Mi"
              cpu: "1000m"
          startupProbe:
            tcpSocket:
              port: 9000
            failureThreshold: 30
            periodSeconds: 5
          livenessProbe:
            tcpSocket:
              port: 9000
            periodSeconds: 15
            failureThreshold: 3
          readinessProbe:
            httpGet:
              path: /api/health
              port: 9000
            periodSeconds: 5
            failureThreshold: 3
          lifecycle:
            preStop:
              exec:
                command: ["/bin/sh", "-c", "sleep 10"]
```

### Queue Worker Deployment

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-queue
  namespace: production
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
      component: queue-worker
  template:
    metadata:
      labels:
        app: laravel
        component: queue-worker
    spec:
      containers:
        - name: queue-worker
          image: mycompany/laravel:1.2.0
          command: ["php", "artisan", "queue:work", "--tries=3", "--timeout=90"]
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secret
          resources:
            requests:
              memory: "128Mi"
              cpu: "250m"
            limits:
              memory: "256Mi"
              cpu: "500m"
          livenessProbe:
            exec:
              command:
                - php
                - artisan
                - queue:monitor
                - redis:default
            periodSeconds: 30
            failureThreshold: 3
```

## PHP/Laravel ilə İstifadə

### Graceful Shutdown

```php
// Laravel queue worker graceful shutdown üçün
// SIGTERM signal-ını handle edir
// php artisan queue:work --timeout=90

// preStop hook ilə — PHP-FPM-in mövcud request-ləri bitirməsi üçün
// lifecycle.preStop.exec.command: ["/bin/sh", "-c", "sleep 10"]
// Bu, readiness probe fail olandan sonra mövcud request-lərin bitməsi üçün vaxt verir
```

### Health Check Controller

```php
// app/Http/Controllers/HealthController.php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    // Liveness — tətbiq sağdırmı?
    public function health()
    {
        return response()->json(['status' => 'alive'], 200);
    }

    // Readiness — trafik qəbul edə bilərmi?
    public function ready()
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'failed';
            return response()->json($checks, 503);
        }

        try {
            Redis::ping();
            $checks['redis'] = 'ok';
        } catch (\Exception $e) {
            $checks['redis'] = 'failed';
            return response()->json($checks, 503);
        }

        $checks['status'] = 'ready';
        return response()->json($checks, 200);
    }
}
```

## İntervyu Sualları

### S1: Rolling update necə işləyir?
**C:** Köhnə Pod-ları tədricən yeniləri ilə əvəz edir. `maxSurge` eyni anda neçə əlavə Pod yaradılacağını, `maxUnavailable` neçə Pod-un eyni anda unavailable olacağını müəyyən edir. Hər yeni Pod ready olduqda köhnə Pod silinir. Bu strategiya zero-downtime deployment təmin edir.

### S2: Rollback necə edilir?
**C:** `kubectl rollout undo deployment/<name>` ilə son işlək versiyaya qayıdılır. `--to-revision=N` ilə konkret versiyaya qayıtmaq olar. Kubernetes Deployment tarixçəsini (ReplicaSet-lər) saxlayır — `revisionHistoryLimit` ilə neçə revision saxlanacağı təyin olunur. `kubectl rollout history` ilə tarixçəyə baxmaq olar.

### S3: HPA necə işləyir və nə tələb edir?
**C:** HPA Pod-ların resurs istifadəsini (CPU, memory) izləyir və hədəf dəyərdən çox olduqda replica sayını artırır, az olduqda azaldır. metrics-server quraşdırılmalıdır. Pod-ların resource requests təyin olunmalıdır. `minReplicas` və `maxReplicas` ilə limit qoyulur. Scale-down üçün stabilization window var (default 5 dəq).

### S4: Resource requests ilə limits arasında fərq nədir?
**C:** Requests — scheduler-in Pod-u yerləşdirmək üçün node-da axtardığı minimum resursdur. Limits — Pod-un istifadə edə biləcəyi maksimum resursdur. Memory limit aşıldıqda Pod OOMKill olur. CPU limit aşıldıqda Pod throttle olur (öldürülmür). Requests olmadan HPA işləmir, limits olmadan Pod bütün node resursunu istifadə edə bilər.

### S5: Blue-green və canary deployment arasında fərq nədir?
**C:** Blue-green-də iki tam mühit var (blue=cari, green=yeni). Keçid ani olur — Service selector-u dəyişdirilir. Risk: hamısı birbaşa yeni versiyaya keçir. Canary-də yeni versiyanın kiçik hissəsi (məsələn 10%) deploy olunur, monitorinq edilir, problem yoxdursa tədricən artırılır. Risk daha azdır.

### S6: Liveness, readiness və startup probe arasında fərq nədir?
**C:** Liveness: konteyner sağdırmı? Fail → restart. Readiness: trafik qəbul edə bilərmi? Fail → Service endpoint-dən çıxar (restart yox). Startup: başlanğıcda uzun sürən tətbiqlər üçün — keçməyincə liveness/readiness başlamır. Startup olmadan yavaş başlayan tətbiqlər liveness fail-ə görə restart loop-a düşə bilər.

### S7: maxSurge=0 və maxUnavailable=0 qoymaq olarmı?
**C:** Xeyr, hər ikisi eyni anda 0 ola bilməz — heç bir dəyişiklik mümkün olmazdı. Ən azı biri 0-dan böyük olmalıdır. `maxSurge=1, maxUnavailable=0` ən conservative seçimdir — əvvəl yeni Pod yaradılır, ready olduqda köhnə silinir. Heç vaxt capacity azalmır.

### S8: terminationGracePeriodSeconds nədir?
**C:** Pod-a SIGTERM göndərildikdən sonra SIGKILL göndərilənə qədər gözləmə müddətidir (default 30 saniyə). Bu müddətdə tətbiq mövcud request-ləri bitirib graceful shutdown edə bilər. PHP-FPM və queue worker-lər üçün bu parametr vacibdir.

## Best Practices

1. **maxUnavailable=0 istifadə edin** — zero-downtime üçün
2. **Readiness probe MÜTLƏQDİR** — olmasa, hazır olmayan Pod-lara traffic gedər
3. **Resource requests/limits qoyun** — HPA üçün və cluster stabillliyi üçün
4. **preStop hook əlavə edin** — graceful shutdown üçün `sleep 10`
5. **revisionHistoryLimit qoyun** — rollback üçün tarixçə saxlayın
6. **change-cause annotation yazın** — `kubectl rollout history`-də görünsün
7. **PodDisruptionBudget istifadə edin** — node maintenance zamanı minimum Pod sayı
8. **Canary deployment istifadə edin** — risk azaltmaq üçün
9. **Init container-da migration işlədin** — app container-dən əvvəl
10. **terminationGracePeriodSeconds artırın** — uzun request-lər üçün


## Əlaqəli Mövzular

- [kubernetes-basics.md](18-kubernetes-basics.md) — K8s əsasları
- [kubernetes-autoscaling.md](31-kubernetes-autoscaling.md) — HPA, KEDA
- [resource-limits-sizing-php.md](48-resource-limits-sizing-php.md) — Resource requests/limits
- [migrations-in-containers.md](41-migrations-in-containers.md) — Init container migration
