# Kubernetes Troubleshooting

## Nədir? (What is it?)

Kubernetes troubleshooting — pod-ların, service-lərin və cluster komponentlərinin gözlənildiyi kimi işləməməsini tapıb həll etmək prosesidir. K8s kompleks distributed sistem olduğu üçün problemlərin səbəbini tapmaq sistemli yanaşma tələb edir.

## Əsas Konseptlər

### 1. Troubleshooting Ağacı

```
Pod işləmir
    │
    ├─ Status: Pending
    │   ├─ Image pull xətası?   → ImagePullBackOff
    │   ├─ Resursu yoxdur?      → FailedScheduling
    │   └─ Volume bağlanmır?    → FailedAttachVolume
    │
    ├─ Status: Running amma tələblər ödəmir
    │   ├─ App crash olur?       → CrashLoopBackOff
    │   ├─ OOM olur?             → OOMKilled
    │   ├─ Health check fail?    → Restart
    │   └─ Network problemi?     → DNS, NetworkPolicy
    │
    └─ Status: Completed
        └─ Job bitmiş
```

### 2. Debug Əmrləri Hiyerarxiyası

```bash
kubectl get              # General overview
kubectl describe         # Detailed events
kubectl logs             # Container logs
kubectl exec             # Container-in içinə gir
kubectl debug            # Ephemeral container
kubectl events           # Cluster events
```

## Praktiki Nümunələr

### 1. ImagePullBackOff / ErrImagePull

```bash
kubectl get pods
# NAME           STATUS              RESTARTS
# laravel-abc    ImagePullBackOff    0

kubectl describe pod laravel-abc
# Events:
#   Warning  Failed     pod/laravel-abc   Failed to pull image "myregistry/laravel:1.0.0":
#                                          pull access denied, authentication required
```

**Səbəblər və həllər:**

| Səbəb | Həll |
|-------|------|
| İmage adı səhv | `spec.containers.image` yoxla |
| İmage versiyası yoxdur | Registry-də tag-i yoxla |
| Private registry auth yox | imagePullSecret əlavə et |
| Network problem | Cluster registry-yə çata bilir? |
| Rate limit (Docker Hub) | Authenticated pull et |

```bash
# ImagePullSecret yarat
kubectl create secret docker-registry regcred \
    --docker-server=myregistry.com \
    --docker-username=admin \
    --docker-password=$REGISTRY_PASSWORD \
    --docker-email=admin@example.com

# Pod-a əlavə et
apiVersion: v1
kind: Pod
metadata:
  name: laravel
spec:
  imagePullSecrets:
    - name: regcred
  containers:
    - name: laravel
      image: myregistry.com/laravel:1.0.0
```

### 2. CrashLoopBackOff

Pod başlayır, crash olur, yenidən başlayır — cycle.

```bash
kubectl get pods
# laravel-xyz   CrashLoopBackOff   5

# Son logları yoxla
kubectl logs laravel-xyz --previous
# Və ya son container
kubectl logs laravel-xyz -c laravel

# Birdən çox container varsa
kubectl logs laravel-xyz --all-containers=true

# Describe ilə events
kubectl describe pod laravel-xyz
```

**Ən çox rast gələn səbəblər:**

1. **Entrypoint/Command səhv**:
```bash
kubectl logs laravel-xyz
# exec: "php artisane": executable file not found
# Həll: "php artisan" yaz
```

2. **Environment variable yoxdur**:
```bash
kubectl logs laravel-xyz
# ERROR: DB_HOST is not set

kubectl get configmap laravel-config -o yaml
# DB_HOST yoxdur → əlavə et
```

3. **Dependency yoxdur** (DB hələ hazır deyil):
```yaml
# Init container ilə gözlə
initContainers:
  - name: wait-for-db
    image: busybox
    command: ['sh', '-c', 'until nc -z mysql 3306; do sleep 2; done']
```

4. **Liveness probe çox aqressiv**:
```yaml
livenessProbe:
  httpGet:
    path: /health
    port: 8080
  initialDelaySeconds: 60   # Start-up time nəzərə al
  periodSeconds: 10
  failureThreshold: 3
```

### 3. OOMKilled

Pod yaddaş limitini aşdı, kernel öldürdü.

```bash
kubectl describe pod laravel-xyz
# Last State:     Terminated
#   Reason:       OOMKilled
#   Exit Code:    137
```

**Həllər:**

```yaml
# Memory limit-i artır
resources:
  requests:
    memory: "256Mi"
    cpu: "100m"
  limits:
    memory: "512Mi"  # Artırıldı
    cpu: "500m"
```

```bash
# Cari istifadəyə bax
kubectl top pods
# NAME           CPU(cores)   MEMORY(bytes)
# laravel-xyz    50m          490Mi   # 512 limit-ə çox yaxın
```

**PHP üçün xüsusi**: `memory_limit` php.ini-də container limit-dən aşağı olmalıdır:
```dockerfile
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory.ini
# Container limit 512M, PHP 256M — buffer var
```

### 4. Pending Status

Pod schedule oluna bilmir.

```bash
kubectl describe pod laravel-xyz
# Events:
#   Warning  FailedScheduling   0/3 nodes are available:
#                                3 Insufficient memory.
```

**Səbəblər:**

| Səbəb | Debug |
|-------|-------|
| Node resursu yoxdur | `kubectl top nodes` |
| NodeSelector uyğunsuz | `kubectl get nodes --show-labels` |
| Taint/Toleration | `kubectl describe node` → Taints |
| PVC bind olmur | `kubectl get pvc` |
| Affinity/Anti-affinity | Pod spec-də yoxla |

### 5. Service Unreachable

Pod işləyir amma Service vasitəsilə çatmır.

```bash
# 1. Service var?
kubectl get svc laravel

# 2. Endpoints var?
kubectl get endpoints laravel
# Əgər empty → selector səhvdir
kubectl get pods --show-labels

# 3. Network policy?
kubectl get networkpolicy

# 4. DNS resolution
kubectl run -it --rm debug --image=busybox -- nslookup laravel.default.svc.cluster.local
```

**Selector debug:**
```yaml
# Service
selector:
  app: laravel       # ← Bu label

# Pod labels
metadata:
  labels:
    app: laravel     # ← Uyğun olmalı
    version: "1.0"
```

### 6. DNS Problems

```bash
# Test pod
kubectl run -it --rm debug --image=nicolaka/netshoot -- bash

# DNS yoxla
nslookup kubernetes.default
nslookup google.com
nslookup mysql.default.svc.cluster.local

# CoreDNS logs
kubectl logs -n kube-system -l k8s-app=kube-dns
```

**ClusterDNS ConfigMap:**
```bash
kubectl get configmap coredns -n kube-system -o yaml
```

### 7. kubectl debug (Ephemeral Container)

Distroless image-də shell yoxdursa, debug container əlavə et:

```bash
kubectl debug -it laravel-xyz \
    --image=nicolaka/netshoot \
    --target=laravel

# Pod-a yeni container əlavə olunur, shell var
# Original container-i görə bilər (--target)
```

### 8. Node Problemləri

```bash
# Node status
kubectl get nodes
# NAME      STATUS     ROLES    AGE
# node-1    NotReady   worker   10d

kubectl describe node node-1
# Conditions:
#   MemoryPressure   True
#   DiskPressure     True
#   NetworkUnavail   False
#   Ready            False (KubeletNotReady)

# Node events
kubectl get events --field-selector source=kubelet,type=Warning

# Node-un pod-ları
kubectl get pods --all-namespaces --field-selector spec.nodeName=node-1
```

## PHP/Laravel ilə İstifadə

### Laravel Pod Troubleshooting Checklist

```bash
#!/bin/bash
# laravel-debug.sh

POD=$(kubectl get pods -l app=laravel -o jsonpath='{.items[0].metadata.name}')

echo "=== Pod Status ==="
kubectl get pod $POD

echo ""
echo "=== Pod Description ==="
kubectl describe pod $POD

echo ""
echo "=== Laravel Logs (last 50 lines) ==="
kubectl logs $POD --tail=50

echo ""
echo "=== Previous Container Logs ==="
kubectl logs $POD --previous --tail=50 2>/dev/null

echo ""
echo "=== Environment Variables ==="
kubectl exec $POD -- env | grep -E "(APP_|DB_|REDIS_)" | sort

echo ""
echo "=== Laravel Artisan Status ==="
kubectl exec $POD -- php artisan about 2>/dev/null
```

### DB Connection Problemi

```bash
# Pod-dan DB-yə çatmaq mümkündürmü?
kubectl exec -it laravel-xyz -- bash
php artisan tinker
>>> DB::connection()->getPdo();
# PDOException: SQLSTATE[HY000] [2002] Connection refused

# Network-dən yoxla
php -r "var_dump(fsockopen('mysql', 3306, \$errno, \$errstr, 5));"
```

**Ümumi səbəblər:**
1. DB servisinin adı yalnışdır (`mysql` vs `mysql-primary`)
2. Namespace fərqli (`mysql.db.svc.cluster.local`)
3. NetworkPolicy blok edir
4. DB hələ startup-dadır

### Laravel Queue Worker Crash

```bash
# Queue worker pod
kubectl logs -l role=queue-worker --tail=100

# Failed jobs
kubectl exec laravel-abc -- php artisan queue:failed
kubectl exec laravel-abc -- php artisan queue:retry all

# Worker restart
kubectl rollout restart deployment/laravel-queue-worker
```

### PHP-FPM "Upstream Timed Out"

```bash
# Laravel pod-da cluster resurs
kubectl top pod laravel-xyz
# CPU throttling?
# Memory yaxın limit-ə?

# PHP-FPM status (nginx ilə)
kubectl exec laravel-xyz -- curl http://localhost:9000/status
# active processes: 20
# idle processes: 0    ← idle 0 olması problem
# max children reached: true

# pm.max_children artır
```

```dockerfile
RUN echo "pm.max_children = 50" >> /usr/local/etc/php-fpm.d/www.conf
RUN echo "pm.start_servers = 10" >> /usr/local/etc/php-fpm.d/www.conf
```

## Interview Sualları

**1. CrashLoopBackOff-un ümumi səbəbləri?**
Entrypoint səhv, env var yox, dependency hazır deyil (DB), liveness probe aqressiv, config fayl yox, memory limit kiçik.

**2. Pod Pending statusda qalırsa nə yoxlayırıq?**
`kubectl describe pod` — events-ə bax. Node resource, NodeSelector, Taint/Toleration, PVC bind, Affinity rules yoxlanılır.

**3. OOMKilled (exit code 137) nədir?**
Container memory limit-i aşdı, kernel öldürdü. `resources.limits.memory` artırmaq və ya app-də memory leak axtarmaq lazımdır.

**4. ImagePullBackOff fərqi ErrImagePull-dan?**
ErrImagePull — ilk pull cəhdi uğursuz. ImagePullBackOff — Kubernetes retry-ları idarə edir, exponential backoff tətbiq edir.

**5. Service endpoint boşdur — niyə?**
Selector uyğun deyil. `kubectl describe svc` → selector, `kubectl get pods --show-labels` → pod-un label-larını müqayisə et.

**6. kubectl debug nə edir?**
Ephemeral container əlavə edir — mövcud pod-a. Distroless image-də shell olmadığında istifadə olunur. `--target` ilə eyni network namespace paylaşır.

**7. DNS problemlərini necə debug edirik?**
1. CoreDNS pod-u işləyir? `kubectl get pods -n kube-system`
2. netshoot container-dən nslookup
3. `/etc/resolv.conf` pod daxilində yoxla
4. NetworkPolicy CoreDNS-ə access bloklayır?

**8. Liveness və Readiness probe fərqi?**
- Liveness: Container işləyir? Fail olarsa, restart
- Readiness: Traffic qəbul etməyə hazırdır? Fail olarsa, Service-dən silinir (restart YOX)

**9. kubectl top niyə "metrics not available" verir?**
Metrics Server quraşdırılmayıb. `kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml`

**10. Node NotReady statusda — hansı addımlar?**
1. `kubectl describe node` — conditions (MemoryPressure, DiskPressure)
2. SSH ilə node-a gir, `systemctl status kubelet`
3. `journalctl -u kubelet -f`
4. Disk full? `df -h`
5. Docker/containerd sağlam? `crictl ps`

## Best Practices

1. **`describe` əvvəl, `logs` sonra** — events çox vaxt cavabı deyir
2. **`--previous` unutma** — crash olmuş container-in logunu göstərir
3. **Resource requests/limits** — həmişə təyin et
4. **Probe-ları test et** — production-a çıxmadan
5. **Centralized logging** (ELK, Loki) — pod silindikdən sonra logları saxlamaq
6. **`kubectl alias`** — `k describe pod`, `k logs -f` qısaltma
7. **Ephemeral container** — production debug üçün
8. **`kubectl events --watch`** — real-time cluster events
9. **Stern** istifadə et — çox pod-dan eyni anda log tail
10. **Runbook-lar yaz** — tez-tez qarşılaşılan problemlər üçün

### Faydalı Alətlər

| Alət | Funksiya |
|------|----------|
| k9s | Terminal-based K8s UI |
| stern | Multi-pod log tailing |
| kubectl-neat | YAML output-u təmizlə |
| kubectl-tree | Owner references tree |
| kube-score | Manifest validation |
| popeye | Cluster audit |
| kubectl-debug | Debug sidecar |
| netshoot | Network troubleshooting image |
