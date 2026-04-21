# Kubernetes Autoscaling (HPA, VPA, Cluster Autoscaler, KEDA)

## Nədir? (What is it?)

**Autoscaling** — yükə uyğun olaraq resursları avtomatik artırıb azaltmaqdır. Kubernetes-də üç səviyyə var:

- **HPA (Horizontal Pod Autoscaler)** — pod replica sayını artırıb azaldır (scale out/in)
- **VPA (Vertical Pod Autoscaler)** — pod-un CPU/memory request/limit dəyişir (scale up/down)
- **Cluster Autoscaler (CA)** — cluster-də node sayını dəyişir
- **KEDA** — event-driven scaling (Kafka lag, SQS queue, cron və s.)

## Əsas Konseptlər

### 1. Autoscaler Matrisi

```
                 Pod                   Node
            ┌──────────┐          ┌──────────┐
Horizontal  │   HPA    │          │    CA    │
            │ replicas │          │  node++  │
            └──────────┘          └──────────┘
            ┌──────────┐
Vertical    │   VPA    │
            │ resources│
            └──────────┘
            ┌──────────┐
Event       │   KEDA   │
            │ ext src  │
            └──────────┘
```

### 2. Control Loop

Hər autoscaler periodic olaraq metric toplayır və hədəfə çatdırmağa çalışır:

```
1. Metric topla (CPU, memory, custom)
2. desired = current × (currentMetric / targetMetric)
3. Tolerance içindədirsə dəyişmə (default ±10%)
4. Scale velocity və stabilization window tətbiq et
5. replicas/resources yenilə
```

## HPA (Horizontal Pod Autoscaler)

### 1. metrics-server Quraşdırmaq

```bash
kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml

kubectl top nodes
kubectl top pods -n production
```

### 2. Sadə CPU-əsaslı HPA

```yaml
# hpa-laravel.yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel
  namespace: production
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel
  minReplicas: 3
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70   # 70% CPU
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
```

```bash
kubectl apply -f hpa-laravel.yaml
kubectl get hpa -w
# NAME      REFERENCE            TARGETS   MINPODS   MAXPODS   REPLICAS
# laravel   Deployment/laravel   45%/70%   3         20        3
# laravel   Deployment/laravel   82%/70%   3         20        5
```

### 3. Custom Metrics (Prometheus Adapter)

```bash
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm install prometheus-adapter prometheus-community/prometheus-adapter \
    --namespace monitoring \
    --set prometheus.url=http://prometheus.monitoring.svc
```

```yaml
# Prometheus Adapter config
rules:
  - seriesQuery: 'http_requests_total{namespace!="",pod!=""}'
    resources:
      overrides:
        namespace: {resource: "namespace"}
        pod: {resource: "pod"}
    name:
      matches: "^(.*)_total"
      as: "${1}_per_second"
    metricsQuery: 'sum(rate(<<.Series>>{<<.LabelMatchers>>}[1m])) by (<<.GroupBy>>)'
```

HPA Pod metric ilə:

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel
  minReplicas: 3
  maxReplicas: 50
  metrics:
    - type: Pods
      pods:
        metric:
          name: http_requests_per_second
        target:
          type: AverageValue
          averageValue: "100"  # hər pod 100 rps
```

### 4. External Metrics (SQS, RabbitMQ)

```yaml
metrics:
  - type: External
    external:
      metric:
        name: sqs_approximate_number_of_messages_visible
        selector:
          matchLabels:
            queue: laravel-jobs
      target:
        type: Value
        value: "30"  # 30-dan çox message olduqda scale out
```

### 5. Behavior Policies (v2)

Scale velocity və stabilization window:

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel
spec:
  scaleTargetRef:
    kind: Deployment
    name: laravel
  minReplicas: 3
  maxReplicas: 50
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
  behavior:
    scaleDown:
      stabilizationWindowSeconds: 300  # 5 dəqiqə gözlə
      policies:
        - type: Percent
          value: 10         # max 10% azalt
          periodSeconds: 60
        - type: Pods
          value: 2          # və ya max 2 pod
          periodSeconds: 60
      selectPolicy: Min     # ikisindən azı
    scaleUp:
      stabilizationWindowSeconds: 0    # dərhal scale up
      policies:
        - type: Percent
          value: 100        # 2x artır
          periodSeconds: 30
        - type: Pods
          value: 4          # və ya 4 pod əlavə
          periodSeconds: 30
      selectPolicy: Max     # ikisindən çoxu
```

Niyə vacibdir:
- **Flapping** qarşısı — tez scale up/down etməmək
- **Gradual scale down** — connection drain üçün vaxt
- **Aggressive scale up** — traffic spike-a tez cavab

## VPA (Vertical Pod Autoscaler)

### 1. VPA Quraşdırmaq

```bash
git clone https://github.com/kubernetes/autoscaler.git
cd autoscaler/vertical-pod-autoscaler
./hack/vpa-up.sh
```

Üç komponent:
- **Recommender** — hesablayır
- **Updater** — köhnə pod-ları kill edir
- **Admission Controller** — yeni pod-a tövsiyə tətbiq edir

### 2. VPA Resource

```yaml
apiVersion: autoscaling.k8s.io/v1
kind: VerticalPodAutoscaler
metadata:
  name: laravel-vpa
  namespace: production
spec:
  targetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel
  updatePolicy:
    updateMode: "Auto"    # Off | Initial | Recreate | Auto
  resourcePolicy:
    containerPolicies:
      - containerName: laravel
        minAllowed:
          cpu: 100m
          memory: 128Mi
        maxAllowed:
          cpu: 2
          memory: 2Gi
        controlledResources: ["cpu", "memory"]
        controlledValues: RequestsAndLimits
```

### 3. Update Modes

| Mode | İzah |
|------|------|
| `Off` | Yalnız tövsiyə yazır, tətbiq etmir |
| `Initial` | Yeni pod yaranarkən tətbiq edir, mövcudlara toxunmur |
| `Recreate` | Köhnə pod-u öldürür, yenisi düzgün resource ilə yaranır |
| `Auto` | Recreate ilə eyni (gələcəkdə in-place update olacaq) |

### 4. Tövsiyəyə Baxmaq

```bash
kubectl describe vpa laravel-vpa
# Recommendation:
#   Container Recommendations:
#     Container Name: laravel
#     Lower Bound:
#       Cpu:     250m
#       Memory:  262Mi
#     Target:
#       Cpu:     500m
#       Memory:  524Mi
#     Upper Bound:
#       Cpu:     1
#       Memory:  1Gi
```

**DİQQƏT**: HPA CPU/memory metric istifadə edirsə, VPA ilə birlikdə istifadə ETMƏ — conflict olur. Custom/external metric olsa olar.

## Cluster Autoscaler

### 1. AWS EKS üçün

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: cluster-autoscaler
  namespace: kube-system
spec:
  template:
    spec:
      serviceAccountName: cluster-autoscaler
      containers:
        - name: cluster-autoscaler
          image: registry.k8s.io/autoscaling/cluster-autoscaler:v1.28.0
          command:
            - ./cluster-autoscaler
            - --cloud-provider=aws
            - --node-group-auto-discovery=asg:tag=k8s.io/cluster-autoscaler/enabled,k8s.io/cluster-autoscaler/my-cluster
            - --balance-similar-node-groups
            - --skip-nodes-with-system-pods=false
            - --scale-down-delay-after-add=10m
            - --scale-down-unneeded-time=10m
            - --max-node-provision-time=15m
```

### 2. Node Scale Up Triggerləri

CA node əlavə edir o zaman ki:
- Pending pod var (Insufficient cpu/memory)
- Mövcud node-larda yer yoxdur
- Node group `maxSize`-a çatmayıb

### 3. Node Scale Down

CA node silir o zaman ki:
- Node utilization < `--scale-down-utilization-threshold` (default 0.5)
- `--scale-down-unneeded-time` (default 10m) müddət ərzində boş qalıb
- Pod-ları başqa node-a köçürmək mümkündür

### 4. Annotations

Node silməkdən qorumaq:
```bash
kubectl annotate node my-node cluster-autoscaler.kubernetes.io/scale-down-disabled=true
```

Pod köçürməkdən qorumaq:
```yaml
metadata:
  annotations:
    cluster-autoscaler.kubernetes.io/safe-to-evict: "false"
```

### 5. Karpenter (AWS Alternativi)

AWS-in yeni autoscaler-i — node group (ASG) yerinə birbaşa EC2 yaradır:

```yaml
apiVersion: karpenter.sh/v1beta1
kind: NodePool
metadata:
  name: default
spec:
  template:
    spec:
      requirements:
        - key: kubernetes.io/arch
          operator: In
          values: ["amd64", "arm64"]
        - key: karpenter.sh/capacity-type
          operator: In
          values: ["spot", "on-demand"]
        - key: node.kubernetes.io/instance-type
          operator: In
          values: ["t3.medium", "t3.large", "m5.large"]
      nodeClassRef:
        name: default
  limits:
    cpu: 1000
    memory: 1000Gi
  disruption:
    consolidationPolicy: WhenUnderutilized
    expireAfter: 720h
```

Karpenter üstünlükləri:
- Dərhal provision (ASG-dən sürətli)
- Bin packing — ən uyğun instance tipi
- Spot interruption handling
- Consolidation — boş node-ları birləşdirir

## KEDA (Kubernetes Event-Driven Autoscaling)

### 1. Quraşdırmaq

```bash
helm repo add kedacore https://kedacore.github.io/charts
helm install keda kedacore/keda --namespace keda --create-namespace
```

### 2. ScaledObject

```yaml
apiVersion: keda.sh/v1alpha1
kind: ScaledObject
metadata:
  name: laravel-queue-worker
  namespace: production
spec:
  scaleTargetRef:
    name: laravel-queue-worker  # Deployment adı
  minReplicaCount: 0            # scale to zero!
  maxReplicaCount: 50
  pollingInterval: 15           # saniyə
  cooldownPeriod: 300           # idle olduqda nə qədər gözləsin
  triggers:
    - type: redis
      metadata:
        address: redis.production.svc.cluster.local:6379
        listName: laravel_queues:default
        listLength: "10"        # hər worker 10 job
```

### 3. Populyar Scaler-lar

| Scaler | Use case |
|--------|----------|
| `kafka` | Consumer lag |
| `rabbitmq` | Queue length |
| `redis` | List length, streams |
| `aws-sqs-queue` | SQS depth |
| `aws-cloudwatch` | CloudWatch metric |
| `prometheus` | PromQL query |
| `cron` | Vaxta görə |
| `postgresql` | Query nəticəsi |
| `http-add-on` | HTTP RPS |

### 4. Scale to Zero

KEDA-nın ən güclü xüsusiyyəti: `minReplicaCount: 0`. İş olmadıqda Deployment 0 replica-ya enir, yalnız KEDA agent işləyir.

```yaml
apiVersion: keda.sh/v1alpha1
kind: ScaledObject
metadata:
  name: image-processor
spec:
  scaleTargetRef:
    name: image-processor
  minReplicaCount: 0
  maxReplicaCount: 30
  idleReplicaCount: 0          # idle zaman 0-a en
  triggers:
    - type: aws-sqs-queue
      metadata:
        queueURL: https://sqs.eu-central-1.amazonaws.com/123/images
        queueLength: "5"
        awsRegion: eu-central-1
      authenticationRef:
        name: aws-credentials
```

### 5. Cron Scaler (Vaxta Görə Scale)

```yaml
apiVersion: keda.sh/v1alpha1
kind: ScaledObject
metadata:
  name: batch-processor
spec:
  scaleTargetRef:
    name: batch-processor
  minReplicaCount: 0
  maxReplicaCount: 10
  triggers:
    - type: cron
      metadata:
        timezone: Europe/Berlin
        start: "0 9 * * 1-5"    # işçi gündə 9:00-da
        end: "0 18 * * 1-5"     # 18:00-da
        desiredReplicas: "5"
```

### 6. ScaledJob (Hər Event üçün Job)

```yaml
apiVersion: keda.sh/v1alpha1
kind: ScaledJob
metadata:
  name: image-processing-job
spec:
  jobTargetRef:
    template:
      spec:
        containers:
          - name: processor
            image: laravel:latest
            command: ["php", "artisan", "image:process"]
        restartPolicy: Never
  pollingInterval: 10
  maxReplicaCount: 100
  triggers:
    - type: aws-sqs-queue
      metadata:
        queueURL: https://sqs.../image-jobs
        queueLength: "1"
```

Hər message üçün yeni Job yaranır — worker pool deyil.

## PHP/Laravel ilə İstifadə

### Laravel Web HPA

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel-web
  namespace: production
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel-web
  minReplicas: 3
  maxReplicas: 30
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 65
    - type: Pods
      pods:
        metric:
          name: php_fpm_active_processes_ratio
        target:
          type: AverageValue
          averageValue: "0.7"
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 0
      policies:
        - type: Percent
          value: 100
          periodSeconds: 15
    scaleDown:
      stabilizationWindowSeconds: 600  # 10 dəq, uzun drain
      policies:
        - type: Pods
          value: 1
          periodSeconds: 120           # 2 dəqiqədə 1 pod
```

### Laravel Queue Worker KEDA

```yaml
apiVersion: keda.sh/v1alpha1
kind: ScaledObject
metadata:
  name: laravel-queue
  namespace: production
spec:
  scaleTargetRef:
    name: laravel-queue-worker
  minReplicaCount: 1              # 1 idle saxla — cold start qaç
  maxReplicaCount: 50
  pollingInterval: 10
  cooldownPeriod: 180
  triggers:
    # Redis queue default
    - type: redis
      metadata:
        address: redis-master.production.svc.cluster.local:6379
        listName: laravel_queues:default
        listLength: "20"
    # Emails queue
    - type: redis
      metadata:
        address: redis-master.production.svc.cluster.local:6379
        listName: laravel_queues:emails
        listLength: "50"
```

Laravel Deployment:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-queue-worker
spec:
  replicas: 1
  template:
    spec:
      containers:
        - name: worker
          image: myregistry/laravel:1.0.0
          command: ["php", "artisan", "queue:work", "--tries=3", "--max-time=3600"]
          resources:
            requests:
              cpu: 100m
              memory: 128Mi
            limits:
              cpu: 500m
              memory: 512Mi
```

### PHP-FPM üçün VPA

PHP-FPM `pm.max_children` CPU/memory-dən asılıdır — VPA ilə right-size edilə bilər:

```yaml
apiVersion: autoscaling.k8s.io/v1
kind: VerticalPodAutoscaler
metadata:
  name: laravel-vpa
spec:
  targetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel
  updatePolicy:
    updateMode: "Off"    # Yalnız tövsiyə, manual tətbiq
  resourcePolicy:
    containerPolicies:
      - containerName: laravel
        minAllowed:
          cpu: 250m
          memory: 256Mi
        maxAllowed:
          cpu: 2
          memory: 2Gi
```

Tövsiyəyə görə `pm.max_children` tune edilir, sonra VPA `Off` saxlanılır (HPA istifadə olunur).

## Interview Sualları

**1. HPA və VPA-nı eyni vaxtda CPU metricə görə istifadə etmək olar?**
YOX — conflict olur. HPA replica artırır (CPU azalır), VPA bu sinal əsasında resource azaltır, sonra CPU yenə artır. Yalnız custom/external metric ilə HPA + memory/CPU ilə VPA olar.

**2. Cluster Autoscaler node-u nə vaxt silir?**
Node utilization `--scale-down-utilization-threshold` (default 50%) altında, `--scale-down-unneeded-time` (default 10m) ərzində, və üstündəki pod-lar başqa node-a köçürülə bilərsə. Local storage, DaemonSet-olmayan pod, safe-to-evict: false olan pod olmamalıdır.

**3. KEDA-nın HPA-dan üstünlüyü?**
1. Event-driven (Kafka lag, SQS depth, cron)
2. Scale to zero
3. 60+ built-in scaler
4. ScaledJob — hər event üçün job
5. Multiple triggers bir ScaledObject-də

Texniki olaraq KEDA HPA yaradır, amma external metric-ləri təmin edir.

**4. `behavior.scaleDown.stabilizationWindowSeconds` nə işə yarayır?**
Replica sayını azaltmadan əvvəl gözlənilən müddət. Flapping qarşısını alır — traffic anlıq düşəndə dərhal scale down edib sonra yenə scale up etmə. Default 5 dəqiqə.

**5. Karpenter Cluster Autoscaler-dən niyə sürətlidir?**
Cluster Autoscaler ASG (Auto Scaling Group) vasitəsilə işləyir — yeni node 2-5 dəqiqəyə gəlir. Karpenter birbaşa EC2 API-yə çağırış edir, pending pod-a ən uyğun instance tipini seçir, 30-60 saniyəyə hazır olur. Həm də spot instance və bin packing optimize edir.

**6. metrics-server production-ready-dir?**
Evet, amma yalnız HPA üçün. Uzun müddətli history saxlamır (yalnız son 15s). Production monitoring üçün Prometheus + kube-state-metrics lazımdır. HPA custom metric üçün prometheus-adapter istifadə olunur.

**7. VPA-nın Auto modunun problemi?**
Pod restart olunmalıdır — yeni resource tətbiq etmək üçün. Bu kısa downtime yaradır. In-place resize (K8s 1.27+) gəlir, amma hələ də beta-dadır. Production-da adətən `Off` mode istifadə olunur, tövsiyə manual baxılır.

**8. HPA target utilization 70% niyə yaxşıdır?**
- 50%: çox aqressiv scale up, resource boş qalır
- 70%: balance — spike üçün buffer var, tez scale up
- 90%: cavab verməmədən əvvəl throttling, pod stress altında
Application xüsusiyyətlərinə görə tune edilməlidir (burst vs steady).

**9. Cluster Autoscaler və Karpenter fərqi?**
| CA | Karpenter |
|----|-----------|
| ASG/NodePool ilə işləyir | Birbaşa EC2 API |
| Fixed instance tipləri | Dynamic selection |
| Slow provision (2-5 dəq) | Fast (30-60 s) |
| Multi-cloud | AWS-only (primarily) |
| Mature | Newer |

**10. ScaledObject-də `minReplicaCount: 0` niyə risk ola bilər?**
Cold start — ilk event gələndə pod yaratmaq, image pull, warm up vaxtı tələb edir. Latency-sensitive workload-lar üçün uygun deyil. `idleReplicaCount: 1` ilə 1 warm pod saxlamaq kompromis olur.

## Best Practices

1. **Resource requests/limits mütləq təyin et** — HPA CPU%-i onların üstündə hesablayır
2. **Readiness probe düzgün** — pod hazır olmadan traffic almasın
3. **PodDisruptionBudget** — scale down zamanı minimum mövcud replica
4. **Stabilization window** — flapping qarşısı
5. **Custom metric > CPU** — RPS, queue length kimi biznes metric-ləri
6. **HPA max 100 replica** — bundan çoxu Deployment-i split et
7. **VPA mode `Off`** — production-da tövsiyə al, manual tətbiq
8. **CA + HPA birgə** — pod scale out olduqda node scale out
9. **Karpenter `consolidation`** — under-utilized node-ları birləşdir
10. **KEDA ScaledJob** — long-running task-lər üçün
11. **Multi-metric HPA** — CPU + RPS birlikdə
12. **Load test** — scaling davranışını öncədən yoxla
13. **`kubectl top`** — baseline resource istifadəsi
14. **Alert on max replicas** — cap-ə çatıbsa incident
