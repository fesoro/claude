# Kubernetes Əsasları

## Nədir? (What is it?)

Kubernetes (K8s) — konteynerləşdirilmiş tətbiqlərin avtomatik deployment, scaling və idarə olunması üçün açıq mənbəli orkestrasiya platformasıdır. Google tərəfindən yaradılıb və 2014-cü ildə açıq mənbəyə çevrilib. Hazırda CNCF (Cloud Native Computing Foundation) tərəfindən idarə olunur.

Kubernetes konteynerlər klasterini idarə edir — hansı konteynerin harada işləyəcəyini, neçə nüsxə olacağını, birinin düşdükdə nə edəcəyini avtomatik həll edir.

### Niyə Kubernetes?

Docker Compose bir maşında konteynerləri idarə edir. Bəs yüzlərlə konteyner, onlarla server olduqda?

- **Avtomatik scheduling** — konteynerləri uyğun node-lara yerləşdirir
- **Self-healing** — düşən konteynerləri avtomatik yenidən başladır
- **Horizontal scaling** — yük artdıqda avtomatik scale edir
- **Service discovery** — konteynerlər bir-birini DNS ilə tapır
- **Rolling updates** — downtime olmadan yeniləmə
- **Load balancing** — trafiği konteynerlər arasında bölür

## Əsas Konseptlər

### 1. Kubernetes Arxitekturası

```
┌─────────────────────────────────────────────────────────────┐
│                    Kubernetes Cluster                         │
│                                                              │
│  ┌──────────────── Control Plane (Master) ────────────────┐ │
│  │                                                         │ │
│  │  ┌────────────┐  ┌─────────────┐  ┌─────────────────┐ │ │
│  │  │ API Server │  │  Scheduler  │  │ Controller Mgr  │ │ │
│  │  └────────────┘  └─────────────┘  └─────────────────┘ │ │
│  │  ┌──────────┐                                          │ │
│  │  │   etcd   │  (Cluster state database)                │ │
│  │  └──────────┘                                          │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌──── Worker Node 1 ─────┐  ┌──── Worker Node 2 ─────┐   │
│  │  ┌───────┐  ┌───────┐  │  │  ┌───────┐  ┌───────┐  │   │
│  │  │ Pod A │  │ Pod B │  │  │  │ Pod C │  │ Pod D │  │   │
│  │  └───────┘  └───────┘  │  │  └───────┘  └───────┘  │   │
│  │  ┌────────┐ ┌────────┐ │  │  ┌────────┐ ┌────────┐ │   │
│  │  │kubelet │ │kube-   │ │  │  │kubelet │ │kube-   │ │   │
│  │  │        │ │proxy   │ │  │  │        │ │proxy   │ │   │
│  │  └────────┘ └────────┘ │  │  └────────┘ └────────┘ │   │
│  └─────────────────────────┘  └─────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

**Control Plane komponentləri:**
- **API Server (kube-apiserver)** — bütün əlaqələrin giriş nöqtəsi, REST API təqdim edir
- **etcd** — cluster-in bütün state-ini saxlayan distributed key-value store
- **Scheduler** — yeni Pod-ları hansı node-a yerləşdirəcəyinə qərar verir
- **Controller Manager** — cluster-in istənilən vəziyyətini qoruyur (ReplicaSet, Deployment controller-lər)

**Worker Node komponentləri:**
- **kubelet** — hər node-da işləyir, Pod-ların düzgün işləməsini təmin edir
- **kube-proxy** — şəbəkə qaydalarını idarə edir, service-ləri dəstəkləyir
- **Container Runtime** — konteynerləri işlədir (containerd, CRI-O)

### 2. Pod

Pod — Kubernetes-in ən kiçik deploy vahididir. Bir və ya daha çox konteyner ehtiva edir.

```yaml
# pod.yaml
apiVersion: v1
kind: Pod
metadata:
  name: laravel-app
  labels:
    app: laravel
    tier: backend
spec:
  containers:
    - name: php-fpm
      image: mycompany/laravel:1.0.0
      ports:
        - containerPort: 9000
      resources:
        requests:
          memory: "128Mi"
          cpu: "250m"
        limits:
          memory: "256Mi"
          cpu: "500m"
      env:
        - name: APP_ENV
          value: "production"
        - name: DB_HOST
          valueFrom:
            configMapKeyRef:
              name: app-config
              key: db-host
```

```bash
# Pod yaratmaq
kubectl apply -f pod.yaml

# Pod-ları siyahılamaq
kubectl get pods
kubectl get pods -o wide    # node və IP ilə
kubectl get pods -w         # real-time izləmə

# Pod haqqında ətraflı məlumat
kubectl describe pod laravel-app

# Pod log-ları
kubectl logs laravel-app
kubectl logs -f laravel-app              # real-time
kubectl logs laravel-app -c php-fpm      # multi-container pod-da

# Pod-da shell açmaq
kubectl exec -it laravel-app -- bash

# Pod-u silmək
kubectl delete pod laravel-app
```

### 3. Deployment

Deployment — Pod-ların deklarativ idarə olunmasını təmin edir: neçə replica olmalı, necə yenilənməli.

```yaml
# deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-deployment
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
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          ports:
            - containerPort: 9000
          resources:
            requests:
              memory: "128Mi"
              cpu: "250m"
            limits:
              memory: "512Mi"
              cpu: "1000m"
          livenessProbe:
            httpGet:
              path: /health
              port: 9000
            initialDelaySeconds: 30
            periodSeconds: 10
          readinessProbe:
            httpGet:
              path: /ready
              port: 9000
            initialDelaySeconds: 5
            periodSeconds: 5
```

```bash
# Deployment yaratmaq
kubectl apply -f deployment.yaml

# Deployment-ları görmək
kubectl get deployments

# Scaling
kubectl scale deployment laravel-deployment --replicas=5

# Yeniləmə
kubectl set image deployment/laravel-deployment \
    php-fpm=mycompany/laravel:1.1.0

# Rollback
kubectl rollout undo deployment/laravel-deployment
kubectl rollout history deployment/laravel-deployment

# Status
kubectl rollout status deployment/laravel-deployment
```

### 4. Service

Service — Pod-lara stabil şəbəkə endpointi təqdim edir. Pod-lar gəlib-gedə bilər, amma Service ünvanı sabitdir.

```yaml
# service.yaml
apiVersion: v1
kind: Service
metadata:
  name: laravel-service
spec:
  selector:
    app: laravel
  type: ClusterIP
  ports:
    - port: 80
      targetPort: 9000
      protocol: TCP
```

```bash
# Service yaratmaq
kubectl apply -f service.yaml

# Service-ləri görmək
kubectl get services
kubectl get svc

# Service endpoints
kubectl get endpoints laravel-service
```

### 5. ReplicaSet

ReplicaSet müəyyən sayda Pod-un həmişə işləməsini təmin edir. Deployment avtomatik ReplicaSet yaradır — birbaşa ReplicaSet yaratmaq tövsiyə olunmur.

```bash
# ReplicaSet-ləri görmək
kubectl get replicasets
kubectl get rs
```

### 6. Namespace

Namespace — cluster-i virtual hissələrə ayırır. Team-lər, mühitlər üçün izolasiya təmin edir.

```bash
# Namespace-ləri görmək
kubectl get namespaces

# Namespace yaratmaq
kubectl create namespace staging
kubectl create namespace production

# Namespace-də resource yaratmaq
kubectl apply -f deployment.yaml -n staging

# Default namespace dəyişmək
kubectl config set-context --current --namespace=staging
```

```yaml
# namespace ilə deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel
  namespace: production
spec:
  replicas: 5
  # ...
```

### 7. ConfigMap

```yaml
# configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-config
data:
  APP_NAME: "My Laravel App"
  APP_ENV: "production"
  DB_HOST: "mysql-service"
  DB_PORT: "3306"
  CACHE_DRIVER: "redis"
  QUEUE_CONNECTION: "redis"
  php.ini: |
    memory_limit = 256M
    upload_max_filesize = 50M
    post_max_size = 50M
```

### 8. Secret

```yaml
# secret.yaml
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secret
type: Opaque
data:
  APP_KEY: YmFzZTY0LWVuY29kZWQta2V5    # base64 encoded
  DB_PASSWORD: c2VjcmV0MTIz              # base64 encoded
  REDIS_PASSWORD: cmVkaXNwYXNz            # base64 encoded
```

```bash
# Secret yaratmaq (CLI)
kubectl create secret generic laravel-secret \
    --from-literal=APP_KEY=base64-key \
    --from-literal=DB_PASSWORD=secret123

# Base64 encode/decode
echo -n "secret123" | base64         # encode
echo "c2VjcmV0MTIz" | base64 -d     # decode
```

## Praktiki Nümunələr

### Laravel-i Kubernetes-ə Deploy Etmək

```yaml
# laravel-full-deployment.yaml

---
# ConfigMap
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-config
  namespace: production
data:
  APP_NAME: "Laravel K8s"
  APP_ENV: "production"
  APP_DEBUG: "false"
  DB_CONNECTION: "mysql"
  DB_HOST: "mysql-service"
  DB_PORT: "3306"
  DB_DATABASE: "laravel"
  CACHE_DRIVER: "redis"
  SESSION_DRIVER: "redis"
  QUEUE_CONNECTION: "redis"
  REDIS_HOST: "redis-service"

---
# Secret
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secret
  namespace: production
type: Opaque
data:
  APP_KEY: YmFzZTY0OnlvdXIta2V5LWhlcmU=
  DB_PASSWORD: cHJvZHVjdGlvbi1wYXNz
  DB_USERNAME: bGFyYXZlbA==

---
# Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
  namespace: production
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
      component: php-fpm
  template:
    metadata:
      labels:
        app: laravel
        component: php-fpm
    spec:
      initContainers:
        - name: migrate
          image: mycompany/laravel:1.0.0
          command: ["php", "artisan", "migrate", "--force"]
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
          resources:
            requests:
              memory: "128Mi"
              cpu: "250m"
            limits:
              memory: "512Mi"
              cpu: "1000m"
          livenessProbe:
            tcpSocket:
              port: 9000
            initialDelaySeconds: 30
            periodSeconds: 10
          readinessProbe:
            exec:
              command:
                - php
                - artisan
                - health:check
            initialDelaySeconds: 10
            periodSeconds: 5

---
# Service
apiVersion: v1
kind: Service
metadata:
  name: laravel-service
  namespace: production
spec:
  selector:
    app: laravel
    component: php-fpm
  ports:
    - port: 9000
      targetPort: 9000

---
# Nginx Deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: nginx
  namespace: production
spec:
  replicas: 2
  selector:
    matchLabels:
      app: laravel
      component: nginx
  template:
    metadata:
      labels:
        app: laravel
        component: nginx
    spec:
      containers:
        - name: nginx
          image: mycompany/laravel-nginx:1.0.0
          ports:
            - containerPort: 80
          resources:
            requests:
              memory: "64Mi"
              cpu: "100m"

---
# Nginx Service (LoadBalancer)
apiVersion: v1
kind: Service
metadata:
  name: nginx-service
  namespace: production
spec:
  type: LoadBalancer
  selector:
    app: laravel
    component: nginx
  ports:
    - port: 80
      targetPort: 80
```

### kubectl Əsas Əmrləri

```bash
# Cluster məlumatı
kubectl cluster-info
kubectl get nodes

# Resource-ları görmək
kubectl get all                    # Bütün resource-lar
kubectl get all -n production      # Namespace ilə
kubectl get pods,svc,deploy        # Müəyyən tip-lər

# Ətraflı məlumat
kubectl describe pod <pod-name>
kubectl describe deployment <name>

# YAML çıxışı
kubectl get pod <name> -o yaml
kubectl get deployment <name> -o json

# Resource silmək
kubectl delete -f deployment.yaml
kubectl delete pod <name>
kubectl delete deployment <name>

# Apply vs Create
kubectl apply -f file.yaml    # Yaradır və ya yeniləyir (deklarativ)
kubectl create -f file.yaml   # Yalnız yaradır (imperativ)
```

## İntervyu Sualları

### S1: Kubernetes nədir və niyə lazımdır?
**C:** Kubernetes konteyner orkestrasiya platformasıdır. Konteynerlərin avtomatik deployment, scaling, self-healing, load balancing və rolling update-lərini idarə edir. Docker tək maşında konteynerləri idarə edir, Kubernetes isə klaster (çoxlu maşın) səviyyəsində. Mikroservis arxitekturasında yüzlərlə konteyner olduqda manual idarəetmə mümkün deyil.

### S2: Pod nədir və niyə birbaşa konteyner deyil?
**C:** Pod — K8s-in ən kiçik deploy vahididir. Bir və ya çox konteyner ehtiva edə bilər. Pod-dakı konteynerlər eyni network namespace, storage və lifecycle paylaşır. Tək konteyner əvəzinə Pod abstraksiyası sidecar pattern, shared volume, init container kimi pattern-ləri mümkün edir.

### S3: Deployment və ReplicaSet arasında fərq nədir?
**C:** ReplicaSet müəyyən sayda Pod-un işləməsini təmin edir. Deployment ReplicaSet üzərində qurulub və əlavə olaraq rolling update, rollback, versiya tarixçəsi təqdim edir. Birbaşa ReplicaSet yaratmaq tövsiyə olunmur — Deployment istifadə edin.

### S4: etcd nədir və niyə vacibdir?
**C:** etcd — distributed, consistent key-value store-dur. Kubernetes cluster-in bütün state-ini saxlayır: Pod-lar, Service-lər, ConfigMap-lər, Secret-lər. etcd olmadan cluster işləyə bilməz. Produksiyada etcd HA (High Availability) konfiqurasiyada olmalıdır (ən azı 3 node).

### S5: kubectl apply ilə kubectl create arasında fərq nədir?
**C:** `kubectl create` imperativdir — resource yoxdursa yaradır, varsa xəta verir. `kubectl apply` deklarativdir — resource yoxdursa yaradır, varsa yeniləyir. Produksiyada və CI/CD-də `apply` istifadə olunur çünki idempotent-dir (neçə dəfə çağırsan eyni nəticəni verir).

### S6: Namespace nə üçün istifadə olunur?
**C:** Namespace cluster-i virtual hissələrə ayırır. İstifadə halları: mühitlər (staging, production), team-lər (frontend, backend), resource quota (hər team-ə limit), access control (RBAC ilə), resource izolasiyası. Default namespace-lər: `default`, `kube-system`, `kube-public`, `kube-node-lease`.

### S7: liveness probe ilə readiness probe arasında fərq nədir?
**C:** Liveness probe konteynerın sağ olub-olmadığını yoxlayır — fail edərsə konteyner restart olunur. Readiness probe konteynerın trafik qəbul etməyə hazır olub-olmadığını yoxlayır — fail edərsə Service endpoint-dən çıxarılır (restart olunmur). Startup probe isə başlanğıcda yavaş tətbiqlər üçündür.

### S8: Kubernetes-i local necə işlədə bilərəm?
**C:** Minikube (VM və ya container-based local cluster), Kind (Kubernetes in Docker), k3s (lightweight K8s), Docker Desktop (built-in K8s). Development üçün Minikube və ya Kind ən populyardır.

## Best Practices

1. **Həmişə Deployment istifadə edin** — birbaşa Pod yaratmayın
2. **Resource requests/limits qoyun** — hər konteyner üçün
3. **Liveness və readiness probe əlavə edin**
4. **Namespace istifadə edin** — mühitlər üçün ayrı namespace
5. **Labels istifadə edin** — resource-ları qruplaşdırmaq və seçmək üçün
6. **Secret-ləri düzgün idarə edin** — plain text YAML-da saxlamayın
7. **Image tag-lərdə `latest` istifadə etməyin** — versiyalı tag qoyun
8. **`kubectl apply` istifadə edin** — `create` əvəzinə
9. **RBAC konfiqurasiya edin** — minimum privilege prinsipi
10. **Monitoring qurun** — Prometheus + Grafana
