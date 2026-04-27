# Kubernetes Basics (Senior)

## İcmal

Kubernetes (K8s) — konteynerləşdirilmiş tətbiqlərin orkestrasiyası üçün open-source platformdur. Go ilə yazılmış bir API-ni production-a çıxardığında, K8s avtomatik scaling, self-healing, rolling update kimi əməliyyatları idarə edir. Sən yalnız "bu tətbiq 3 instansiya ilə işləməlidir" deyirsən — K8s qalanını həll edir.

## Niyə Vacibdir

Docker ilə containerize etmək lazımdır, lakin bu yetərli deyil:

- Konteyner çökdükdə kim onu yenidən başladır?
- Traffic artdıqda kim yeni instansiya əlavə edir?
- Yeni version deploy edəndə kim zero-downtime təmin edir?
- Load balancing kim həll edir?

K8s bütün bu sualların cavabını avtomatik verir. PHP/Laravel dəvründə `supervisor`, `nginx`, `capistrano` ilə manual idarə etdiklərin K8s-də deklarativ YAML ilə həll olunur.

## Əsas Anlayışlar

### Pod

Ən kiçik deployment vahididir. Bir və ya bir neçə konteyner saxlayır. Adətən bir Pod = bir tətbiq konteyneri.

```yaml
# go-api-pod.yaml
apiVersion: v1
kind: Pod
metadata:
  name: go-api
  labels:
    app: go-api
spec:
  containers:
    - name: go-api
      image: ghcr.io/myorg/go-api:latest
      ports:
        - containerPort: 8080
      resources:
        requests:
          memory: "64Mi"
          cpu: "100m"
        limits:
          memory: "128Mi"
          cpu: "500m"
```

Pod-lar birbaşa yaradılmır — onlar Deployment vasitəsilə idarə olunur.

### Deployment

Replica set-i idarə edir: neçə Pod olacağını, rolling update strategiyasını təyin edir.

```yaml
# go-api-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: go-api
spec:
  replicas: 3
  selector:
    matchLabels:
      app: go-api
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1        # eyni vaxtda +1 yeni Pod
      maxUnavailable: 0  # heç bir Pod offline olmasın
  template:
    metadata:
      labels:
        app: go-api
    spec:
      containers:
        - name: go-api
          image: ghcr.io/myorg/go-api:v1.2.0
          ports:
            - containerPort: 8080
          envFrom:
            - configMapRef:
                name: go-api-config
            - secretRef:
                name: go-api-secrets
          resources:
            requests:
              memory: "64Mi"
              cpu: "100m"
            limits:
              memory: "256Mi"
              cpu: "500m"
          livenessProbe:
            httpGet:
              path: /health
              port: 8080
            initialDelaySeconds: 10
            periodSeconds: 15
          readinessProbe:
            httpGet:
              path: /ready
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 10
      terminationGracePeriodSeconds: 30
```

### Service

Pod-lara sabit network endpoint verir. Pod-lar ölüb yenidən yarandıqda IP dəyişir — Service sabit qalır.

```yaml
# go-api-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: go-api-svc
spec:
  selector:
    app: go-api
  ports:
    - protocol: TCP
      port: 80        # Service portu (daxili)
      targetPort: 8080 # Pod portu
  type: ClusterIP     # Yalnız cluster daxilindən əlçatan
```

**Service tipleri:**
- `ClusterIP` — yalnız cluster daxili (default)
- `NodePort` — host maşının portuna yönləndirir (dev/test üçün)
- `LoadBalancer` — cloud load balancer yaradır (AWS ALB, GCP LB)

### ConfigMap

Həssas olmayan konfiqurasiyanı inject etmək üçün.

```yaml
# go-api-configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: go-api-config
data:
  APP_ENV: "production"
  LOG_LEVEL: "info"
  DB_HOST: "postgres-svc"
  DB_PORT: "5432"
  DB_NAME: "myapp"
```

Go tətbiqi `os.Getenv("DB_HOST")` ilə oxuyur — heç bir fərq yoxdur.

### Secret

Həssas məlumatları (şifrə, token) saxlamaq üçün. Base64 encode edilir — encrypted deyil (vault ilə integrate et production-da).

```yaml
# go-api-secret.yaml
apiVersion: v1
kind: Secret
metadata:
  name: go-api-secrets
type: Opaque
data:
  DB_PASSWORD: cGFzc3dvcmQxMjM=  # base64("password123")
  JWT_SECRET: c3VwZXJzZWNyZXQ=   # base64("supersecret")
```

```bash
# Base64 encode etmək üçün:
echo -n "password123" | base64
```

### Ingress

HTTP/HTTPS trafficini cluster daxilindəki Service-lərə yönləndirir. Nginx Ingress Controller lazımdır.

```yaml
# go-api-ingress.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: go-api-ingress
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
spec:
  ingressClassName: nginx
  rules:
    - host: api.myapp.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: go-api-svc
                port:
                  number: 80
  tls:
    - hosts:
        - api.myapp.com
      secretName: api-myapp-tls
```

### HorizontalPodAutoscaler

CPU və ya memory-ə görə Pod sayını avtomatik artırır/azaldır.

```yaml
# go-api-hpa.yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: go-api-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: go-api
  minReplicas: 2
  maxReplicas: 10
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
```

## Praktik Baxış

### Go tətbiqi üçün Health Check endpoint-ləri

K8s liveness və readiness probe-ları üçün endpoint-lər mütləq olmalıdır:

```go
package main

import (
    "database/sql"
    "encoding/json"
    "net/http"
    "sync/atomic"
)

type HealthHandler struct {
    db    *sql.DB
    ready atomic.Bool // graceful shutdown zamanı false olur
}

// /health — liveness probe: tətbiq işləyirmi?
// K8s bu endpoint fail olsa Podu restart edir
func (h *HealthHandler) LivenessHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(http.StatusOK)
    json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}

// /ready — readiness probe: tətbiq traffic qəbul etməyə hazırdırmı?
// K8s bu endpoint fail olsa Poda traffic göndərmir (amma restart etmir)
func (h *HealthHandler) ReadinessHandler(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")

    if !h.ready.Load() {
        w.WriteHeader(http.StatusServiceUnavailable)
        json.NewEncoder(w).Encode(map[string]string{"status": "not ready"})
        return
    }

    if err := h.db.PingContext(r.Context()); err != nil {
        w.WriteHeader(http.StatusServiceUnavailable)
        json.NewEncoder(w).Encode(map[string]string{
            "status": "not ready",
            "reason": "database unreachable",
        })
        return
    }

    w.WriteHeader(http.StatusOK)
    json.NewEncoder(w).Encode(map[string]string{"status": "ready"})
}
```

### Graceful Shutdown (SIGTERM handling)

K8s Pod silməzdən əvvəl `SIGTERM` göndərir. Tətbiq 30 saniyə (terminationGracePeriodSeconds) ərzində clean shutdown etməlidir:

```go
package main

import (
    "context"
    "log"
    "net/http"
    "os"
    "os/signal"
    "syscall"
    "time"
)

func main() {
    health := &HealthHandler{db: db}
    health.ready.Store(true)

    mux := http.NewServeMux()
    mux.HandleFunc("/health", health.LivenessHandler)
    mux.HandleFunc("/ready", health.ReadinessHandler)
    mux.HandleFunc("/api/v1/", apiHandler)

    server := &http.Server{
        Addr:    ":8080",
        Handler: mux,
    }

    // Server-i background-da başlat
    go func() {
        if err := server.ListenAndServe(); err != http.ErrServerClosed {
            log.Fatalf("server error: %v", err)
        }
    }()
    log.Println("server started on :8080")

    // SIGTERM və SIGINT gözlə
    quit := make(chan os.Signal, 1)
    signal.Notify(quit, syscall.SIGTERM, syscall.SIGINT)
    <-quit

    log.Println("shutdown signal received")

    // Əvvəlcə readiness probe-u false et — K8s yeni traffic göndərməsin
    health.ready.Store(false)

    // Mövcud requestlərin tamamlanmasını gözlə (max 25 saniyə)
    ctx, cancel := context.WithTimeout(context.Background(), 25*time.Second)
    defer cancel()

    if err := server.Shutdown(ctx); err != nil {
        log.Printf("shutdown error: %v", err)
    }
    log.Println("server stopped gracefully")
}
```

### Multi-stage Dockerfile (minimal Go image)

```dockerfile
# --- Build stage ---
FROM golang:1.23-alpine AS builder

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux go build \
    -ldflags="-w -s" \
    -o /app/server \
    ./cmd/api

# --- Final stage (distroless) ---
FROM gcr.io/distroless/static-debian12

COPY --from=builder /app/server /server

EXPOSE 8080

USER nonroot:nonroot

ENTRYPOINT ["/server"]
```

`distroless` image: shell yoxdur, package manager yoxdur — yalnız binary. Security surface minimaldır.

### Go üçün Resource limits

Go garbage collector memory istifadə edir — limits lazımdır:

```yaml
resources:
  requests:
    memory: "64Mi"   # scheduler bunu görür
    cpu: "100m"      # 0.1 CPU core
  limits:
    memory: "256Mi"  # Bu həddə OOMKilled olur
    cpu: "500m"      # 0.5 CPU core
```

**GOMAXPROCS qayğısı**: K8s CPU limit varsa, `uber-go/automaxprocs` paketini istifadə et — Go runtime CPU limit-i düzgün oxusun:

```go
import _ "go.uber.org/automaxprocs"
```

## Nümunələr

### Ümumi Nümunə

PHP/Laravel-də `php artisan serve` ilə tək instansiya işlədirdin. K8s-də isə:

1. `Deployment` yazırsan: "3 instansiya olsun, 256MB RAM, 0.5 CPU"
2. `Service` yazırsan: "bu 3 Pod-a traffic yönləndir"
3. `HPA` yazırsan: "CPU 70%-dən keçsə, 10-a qədər scale et"
4. `Ingress` yazırsan: "api.myapp.com buraya gəlsin"

K8s qalanını özü həll edir.

### Kod Nümunəsi

Bütün K8s resurslarını tək bir faylda toplamaq:

```yaml
# k8s/deployment.yaml — production deploy üçün tam manifest
---
apiVersion: v1
kind: ConfigMap
metadata:
  name: go-api-config
  namespace: production
data:
  APP_ENV: "production"
  LOG_LEVEL: "info"
  DB_HOST: "postgres-svc.production.svc.cluster.local"

---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: go-api
  namespace: production
spec:
  replicas: 3
  selector:
    matchLabels:
      app: go-api
  template:
    metadata:
      labels:
        app: go-api
    spec:
      containers:
        - name: go-api
          image: ghcr.io/myorg/go-api:v1.2.0
          ports:
            - containerPort: 8080
          envFrom:
            - configMapRef:
                name: go-api-config
            - secretRef:
                name: go-api-secrets
          livenessProbe:
            httpGet:
              path: /health
              port: 8080
            initialDelaySeconds: 10
            periodSeconds: 15
            failureThreshold: 3
          readinessProbe:
            httpGet:
              path: /ready
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 10
            failureThreshold: 2
          resources:
            requests:
              memory: "64Mi"
              cpu: "100m"
            limits:
              memory: "256Mi"
              cpu: "500m"
      terminationGracePeriodSeconds: 30

---
apiVersion: v1
kind: Service
metadata:
  name: go-api-svc
  namespace: production
spec:
  selector:
    app: go-api
  ports:
    - port: 80
      targetPort: 8080

---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: go-api-hpa
  namespace: production
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: go-api
  minReplicas: 3
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
```

```bash
# Deploy etmək
kubectl apply -f k8s/deployment.yaml

# Status yoxlamaq
kubectl get pods -n production
kubectl get deployment go-api -n production

# Logları görmək
kubectl logs -l app=go-api -n production --tail=100 -f

# Pod-a daxil olmaq (debug üçün — distroless-də işləməz)
kubectl exec -it go-api-xxx -n production -- sh
```

## Praktik Tapşırıqlar

**1. Local cluster qur:**
```bash
# minikube ilə
brew install minikube
minikube start --cpus=2 --memory=4096

# kind ilə (Docker-in-Docker)
brew install kind
kind create cluster --name dev
```

**2. Go API-ni deploy et:**
```bash
# Image build et
docker build -t go-api:local .

# minikube-ə yüklə
minikube image load go-api:local

# Deploy et
kubectl apply -f k8s/

# Service-ə çatmaq üçün
kubectl port-forward svc/go-api-svc 8080:80
curl localhost:8080/health
```

**3. Rolling update test et:**
```bash
# Image-i yenilə
kubectl set image deployment/go-api go-api=go-api:v2 -n production

# Update prosesini izlə
kubectl rollout status deployment/go-api -n production

# Əvvəlki versiyaya qayıt
kubectl rollout undo deployment/go-api -n production
```

**4. HPA test et:**
```bash
# Load generator ilə
kubectl run load-test --image=busybox --rm -it -- \
  /bin/sh -c "while true; do wget -q -O- http://go-api-svc/api/v1/ping; done"

# HPA status
kubectl get hpa go-api-hpa -w
```

**5. Günlük istifadə olunan kubectl əmrləri:**
```bash
kubectl get pods                        # Pod siyahısı
kubectl describe pod <pod-name>         # Pod detalları (events!)
kubectl logs <pod-name> -f              # Real-time log
kubectl get events --sort-by='.lastTimestamp'  # Cluster events
kubectl top pods                        # CPU/Memory istifadəsi
kubectl get ing                         # Ingress siyahısı
kubectl scale deployment/go-api --replicas=5   # Manual scale
```

## Əlaqəli Mövzular

- `23-docker-and-deploy.md` — Docker ilə konteynerləşdirmə
- `17-graceful-shutdown.md` (go/backend/) — SIGTERM handling detalları
- `24-monitoring-and-observability.md` — K8s-dən metrics toplamaq
- `39-github-actions-cicd.md` — K8s-ə automated deploy pipeline
