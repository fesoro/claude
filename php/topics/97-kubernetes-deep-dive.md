# Kubernetes Deep Dive

## Mündəricat
1. [Əsas Konseptlər](#əsas-konseptlər)
2. [Pod, Deployment, Service, Ingress](#pod-deployment-service-ingress)
3. [HPA — Horizontal Pod Autoscaler](#hpa--horizontal-pod-autoscaler)
4. [Resource Requests vs Limits](#resource-requests-vs-limits)
5. [Probes (Liveness, Readiness, Startup)](#probes-liveness-readiness-startup)
6. [ConfigMap və Secret](#configmap-və-secret)
7. [PHP-FPM Kubernetes Konfiqurasiyası](#php-fpm-kubernetes-konfigurasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Əsas Konseptlər

```
Kubernetes — container orchestration platforması.
Container-ları deploy et, scale et, idarə et.

Cluster:
  ┌─────────────────────────────────────────────────┐
  │                   Cluster                       │
  │                                                 │
  │  ┌──────────────┐    ┌────────────────────────┐ │
  │  │Control Plane │    │      Worker Nodes      │ │
  │  │  API Server  │    │  ┌──────┐ ┌──────┐     │ │
  │  │  etcd        │    │  │ Pod  │ │ Pod  │     │ │
  │  │  Scheduler   │    │  └──────┘ └──────┘     │ │
  │  │  Controller  │    │  Node 1    Node 2       │ │
  │  └──────────────┘    └────────────────────────┘ │
  └─────────────────────────────────────────────────┘

Control Plane:
  API Server:  Bütün əməliyyatların giriş nöqtəsi
  etcd:        Cluster state-in key-value store-u
  Scheduler:   Pod-ları node-lara assign edir
  Controller:  Desired state-i real state-ə çatdırır

Worker Node:
  kubelet:     Node agenti, Pod-ları idarə edir
  kube-proxy:  Network rules
  Container runtime (containerd, Docker)
```

---

## Pod, Deployment, Service, Ingress

```
Pod — Ən kiçik deploy vahidi.
  Bir və ya daha çox container.
  Eyni IP, eyni storage.

Deployment — Pod-ların idarəsi.
  Neçə replica? Rolling update? Rollback?

Service — Pod-lara sabit şəbəkə endpointi.
  Pod-lar dəyişsə belə, Service IP sabit qalır.
  ClusterIP:  Cluster daxili
  NodePort:   Node portu ilə xaricə
  LoadBalancer: Cloud LB

Ingress — HTTP routing.
  Xarici traffic → Service yönləndirilməsi.
  Host-based, path-based routing.

┌─────────────────────────────────────────────────────────┐
│  Internet                                               │
│      │                                                  │
│   Ingress (nginx-ingress)                               │
│      │  /api → api-service                              │
│      │  /app → app-service                              │
│      ▼                                                  │
│   Service (ClusterIP)                                   │
│      │                                                  │
│  ┌───▼────┐ ┌────────┐ ┌────────┐                       │
│  │  Pod   │ │  Pod   │ │  Pod   │                       │
│  └────────┘ └────────┘ └────────┘                       │
└─────────────────────────────────────────────────────────┘
```

---

## HPA — Horizontal Pod Autoscaler

```
HPA — CPU/memory/custom metrics-ə görə Pod sayını avtomatik artırır/azaldır.

spec:
  minReplicas: 2
  maxReplicas: 20
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70  # CPU 70%-dən artıq olarsa scale out

HPA necə işləyir:
  metrics-server CPU-nu ölçür
  HPA: "70% threshold keçildi, +1 Pod lazımdır"
  Deployment replica sayını artırır
  Yeni Pod schedule → başlayır → ready

Scale-out: CPU > 70% → Pod əlavə et
Scale-in:  CPU < 70% (stabilization window bitdi) → Pod azalt

Cooldown:
  scaleDown.stabilizationWindowSeconds: 300
  (5 dəqiqə yüksək qalmazsa scale-in etmə)

VPA (Vertical Pod Autoscaler):
  CPU/Memory request-lərini avtomatik tənzimləyir.
  HPA ilə birlikdə istifadə çətin ola bilər.
```

---

## Resource Requests vs Limits

```
Requests: "Bu qədər resource ayır mənə"
  Scheduler bu dəyərə görə Pod-ı node-a yerləşdirir.
  Guaranteed amount.

Limits: "Bu qədərdən çox istifadə etməməliyəm"
  CPU: Throttle edilir (kill deyil)
  Memory: Limit keçərsə → OOMKilled!

resources:
  requests:
    memory: "128Mi"
    cpu: "250m"      # 0.25 core
  limits:
    memory: "256Mi"
    cpu: "500m"      # 0.5 core

QoS Classes (Quality of Service):
  Guaranteed: requests == limits
              → Eviction-da sonuncu sıra
  Burstable:  requests < limits
              → Orta sıra
  BestEffort: requests/limits yoxdur
              → İlk evict edilən

PHP-FPM üçün tövsiyə:
  Memory limit = pm.max_children * worker_memory
  CPU requests = reallist expectation
  Memory OOM → container restart (pm.max_children artır!)
```

---

## Probes (Liveness, Readiness, Startup)

```
Liveness Probe: "Container işləyirmi? Dead lock-dəmi?"
  Fail olarsa → Container restart edilir.
  
  livenessProbe:
    httpGet:
      path: /health/live
      port: 8080
    initialDelaySeconds: 30
    periodSeconds: 10
    failureThreshold: 3

Readiness Probe: "Container traffic qəbul etməyə hazırdırmı?"
  Fail olarsa → Service-dən çıxarılır (traffic gəlmir).
  Restart edilmir!
  
  readinessProbe:
    httpGet:
      path: /health/ready
      port: 8080
    initialDelaySeconds: 5
    periodSeconds: 5

Startup Probe: "Container hələ başlayır (yavaş başlayan app-lar üçün)"
  Startup tamamlanana kimi liveness check etmə.
  
  startupProbe:
    httpGet:
      path: /health/started
      port: 8080
    failureThreshold: 30     # 30 * 10s = 5 dəq gözlə
    periodSeconds: 10

PHP-FPM: OPcache warmup vaxt alır!
Startup probe olmadan liveness probe OPcache dolmadan öldürə bilər.
```

---

## ConfigMap və Secret

```
ConfigMap — non-sensitive konfiqurasiya
Secret     — sensitive data (şifrələnmiş etcd-də)

ConfigMap nümunəsi:
apiVersion: v1
kind: ConfigMap
metadata:
  name: app-config
data:
  APP_ENV: "production"
  LOG_LEVEL: "info"
  DB_HOST: "postgres-service"

Secret nümunəsi (base64 encoded):
apiVersion: v1
kind: Secret
metadata:
  name: app-secrets
type: Opaque
data:
  DB_PASSWORD: cGFzc3dvcmQxMjM=    # base64("password123")
  APP_KEY: ...

Pod-da istifadə:
env:
  - name: DB_PASSWORD
    valueFrom:
      secretKeyRef:
        name: app-secrets
        key: DB_PASSWORD
  - name: APP_ENV
    valueFrom:
      configMapKeyRef:
        name: app-config
        key: APP_ENV

Secret idarəsi:
  Kubernetes Secret-lər etcd-dədir (encryption at rest lazımdır).
  Daha yaxşı: HashiCorp Vault, AWS Secrets Manager
  External Secrets Operator ilə Vault → K8s Secret sync
```

---

## PHP-FPM Kubernetes Konfigurasiyası

```yaml
# PHP-FPM + Nginx sidecar pattern
apiVersion: apps/v1
kind: Deployment
metadata:
  name: php-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: php-app
  template:
    spec:
      containers:
      # PHP-FPM container
      - name: php-fpm
        image: php:8.3-fpm
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        lifecycle:
          preStop:
            exec:
              # Graceful shutdown: cari request-lər tamamlansın
              command: ["/bin/sh", "-c", "sleep 5 && kill -QUIT 1"]
        readinessProbe:
          exec:
            command: ["php-fpm-healthcheck"]
          initialDelaySeconds: 10
          periodSeconds: 5
        env:
          - name: PHP_PM_MAX_CHILDREN
            value: "10"

      # Nginx sidecar
      - name: nginx
        image: nginx:alpine
        ports:
          - containerPort: 80
        volumeMounts:
          - name: nginx-config
            mountPath: /etc/nginx/conf.d
      
      volumes:
        - name: nginx-config
          configMap:
            name: nginx-config
```

---

## İntervyu Sualları

- Pod vs Deployment — niyə birbaşa Pod deploy etmirik?
- HPA minimum 2 replica seçmənin əsas səbəbi nədir?
- Resource requests olmadan Scheduler necə işləyir?
- Memory limit aşıldıqda nə baş verir? CPU limit-dən fərqi?
- Readiness probe fail olduqda container restart olurmu?
- PHP-FPM üçün startup probe niyə xüsusilə vacibdir?
- `preStop` lifecycle hook graceful shutdown-da nə rolu oynayır?
