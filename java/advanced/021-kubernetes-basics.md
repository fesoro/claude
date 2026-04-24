# 021 — Kubernetes Java Basics — Geniş İzah
**Səviyyə:** Ekspert


## Mündəricat
1. [Kubernetes nədir?](#kubernetes-nədir)
2. [Əsas Kubernetes resursları](#əsas-kubernetes-resursları)
3. [Spring Boot Deployment](#spring-boot-deployment)
4. [Service & Ingress](#service--ingress)
5. [ConfigMap & Secret](#configmap--secret)
6. [Health Checks & Resource Limits](#health-checks--resource-limits)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Kubernetes nədir?

```
Kubernetes (K8s) — container orkestrasiya platformu
  → Google-un Borg sistemindən ilham aldı (2014)

Docker ilə problem (production-da):
  → Çox container idarə etmək çətin
  → Container çökdükdə kim restart edəcək?
  → Yük artanda kim scale edəcək?
  → Yeni versiya deploy edəndə downtime?
  → Çox host arasında load balancing?

Kubernetes həll edir:
  ✅ Auto-healing: container çöksə → yenidən başlat
  ✅ Auto-scaling: yük artsa → yeni pod aç
  ✅ Rolling deploy: zero-downtime deployment
  ✅ Service discovery: pod-lar bir-birini tapır
  ✅ Load balancing: traffic pod-lar arasında bölünür
  ✅ Secret management: DB şifrəsi güvənli saxlanılır
  ✅ Self-healing: node çöksə → pod başqa node-a köçür

Əsas komponentlər:
  Control Plane (Master):
    → API Server: kubectl bütün əmrləri bura gedir
    → etcd: cluster state-i saxlayır (key-value)
    → Scheduler: yeni pod-ları hansı node-a yerləşdirir
    → Controller Manager: istəkli vəziyyəti qoruyur

  Worker Nodes:
    → kubelet: node-dakı pod-ları idarə edir
    → kube-proxy: network proxy
    → Container runtime: containerd, CRI-O

Resurs iyerarxiyası:
  Cluster → Namespace → Pod → Container
```

---

## Əsas Kubernetes resursları

```yaml
# ─── Pod — ən kiçik deploy vahidi ────────────────────────
# Bir ya da çox container qrupu
# Eyni network namespace paylaşır
apiVersion: v1
kind: Pod
metadata:
  name: myapp-pod
  labels:
    app: myapp
spec:
  containers:
    - name: myapp
      image: mycompany/myapp:1.0.0
      ports:
        - containerPort: 8080

# Pod birbaşa istifadə edilmir — Deployment istifadə edilir!

---
# ─── Deployment — Pod-ları idarə edir ────────────────────
apiVersion: apps/v1
kind: Deployment
metadata:
  name: myapp-deployment
  namespace: production
spec:
  replicas: 3                    # 3 pod həmişə çalışmalı
  selector:
    matchLabels:
      app: myapp
  template:                      # Pod şablonu
    metadata:
      labels:
        app: myapp
        version: "1.0.0"
    spec:
      containers:
        - name: myapp
          image: mycompany/myapp:1.0.0
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1                # Eyni anda +1 yeni pod
      maxUnavailable: 0          # Heç bir pod unavailable olmasın

---
# ─── ReplicaSet — Deployment yaradır avtomatik ───────────
# Müəyyən sayda pod-un çalışmasını zəmanət edir
# Birbaşa istifadə edilmir, Deployment idarə edir

---
# ─── StatefulSet — Stateful app-lar üçün (DB, Kafka) ────
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: postgres
spec:
  replicas: 1
  selector:
    matchLabels:
      app: postgres
  serviceName: postgres
  template:
    spec:
      containers:
        - name: postgres
          image: postgres:15
          volumeMounts:
            - name: data
              mountPath: /var/lib/postgresql/data
  volumeClaimTemplates:          # Hər pod üçün ayrı PVC
    - metadata:
        name: data
      spec:
        accessModes: [ReadWriteOnce]
        resources:
          requests:
            storage: 10Gi

---
# ─── Job & CronJob ───────────────────────────────────────
apiVersion: batch/v1
kind: CronJob
metadata:
  name: cleanup-job
spec:
  schedule: "0 2 * * *"        # Hər gecə 02:00
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: OnFailure
          containers:
            - name: cleanup
              image: mycompany/cleanup:1.0
              command: ["java", "-jar", "cleanup.jar"]
```

---

## Spring Boot Deployment

```yaml
# ─── Spring Boot Deployment YAML ─────────────────────────
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service
  namespace: production
  labels:
    app: order-service
    version: "2.1.0"
spec:
  replicas: 3
  selector:
    matchLabels:
      app: order-service
  template:
    metadata:
      labels:
        app: order-service
        version: "2.1.0"
      annotations:
        prometheus.io/scrape: "true"
        prometheus.io/path: "/actuator/prometheus"
        prometheus.io/port: "8080"
    spec:
      serviceAccountName: order-service-sa

      # Init container — DB hazır olana qədər gözlə
      initContainers:
        - name: wait-for-db
          image: busybox:1.36
          command:
            - sh
            - -c
            - |
              until nc -z postgres-svc 5432; do
                echo "PostgreSQL gözlənilir..."
                sleep 2
              done
              echo "PostgreSQL hazırdır!"

      containers:
        - name: order-service
          image: mycompany/order-service:2.1.0
          imagePullPolicy: IfNotPresent

          ports:
            - containerPort: 8080
              name: http

          # Environment variables
          env:
            - name: SPRING_PROFILES_ACTIVE
              value: "kubernetes"
            - name: POD_NAME
              valueFrom:
                fieldRef:
                  fieldPath: metadata.name
            - name: POD_NAMESPACE
              valueFrom:
                fieldRef:
                  fieldPath: metadata.namespace
            - name: SPRING_DATASOURCE_URL
              valueFrom:
                configMapKeyRef:
                  name: order-service-config
                  key: db.url
            - name: SPRING_DATASOURCE_USERNAME
              valueFrom:
                secretKeyRef:
                  name: order-service-secrets
                  key: db.username
            - name: SPRING_DATASOURCE_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: order-service-secrets
                  key: db.password

          # Resource limits — mütləq lazım!
          resources:
            requests:
              memory: "256Mi"
              cpu: "250m"    # 0.25 CPU core
            limits:
              memory: "512Mi"
              cpu: "500m"    # 0.5 CPU core

          # Health checks
          startupProbe:
            httpGet:
              path: /actuator/health
              port: 8080
            failureThreshold: 30   # 30 × 10s = 5dəq start üçün
            periodSeconds: 10

          livenessProbe:
            httpGet:
              path: /actuator/health/liveness
              port: 8080
            initialDelaySeconds: 0
            periodSeconds: 10
            failureThreshold: 3

          readinessProbe:
            httpGet:
              path: /actuator/health/readiness
              port: 8080
            initialDelaySeconds: 0
            periodSeconds: 5
            failureThreshold: 3

          # Volume mount
          volumeMounts:
            - name: config-volume
              mountPath: /app/config

      volumes:
        - name: config-volume
          configMap:
            name: order-service-config

      # Rolling update zamanı graceful shutdown
      terminationGracePeriodSeconds: 60

      # Pod dağılımı — eyni node-da çox pod olmasın
      topologySpreadConstraints:
        - maxSkew: 1
          topologyKey: kubernetes.io/hostname
          whenUnsatisfiable: DoNotSchedule
          labelSelector:
            matchLabels:
              app: order-service

---
# HorizontalPodAutoscaler — yük artanda auto-scale
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: order-service-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: order-service
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

---

## Service & Ingress

```yaml
# ─── Service — Pod-lara sabit endpoint ────────────────────
apiVersion: v1
kind: Service
metadata:
  name: order-service-svc
  namespace: production
spec:
  selector:
    app: order-service       # Bu label-lı pod-lara yönləndir
  ports:
    - protocol: TCP
      port: 80               # Service port
      targetPort: 8080       # Container port
  type: ClusterIP            # Yalnız cluster daxili

---
# ─── Service tipləri ─────────────────────────────────────
# ClusterIP  → Yalnız cluster daxili (default)
# NodePort   → Node IP:Port-dan xaricə açıq (test üçün)
# LoadBalancer → Cloud provider external LB (AWS ELB, GCP LB)
# ExternalName → External service-ə alias

---
# ─── Ingress — HTTP routing ──────────────────────────────
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: api-ingress
  namespace: production
  annotations:
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
    nginx.ingress.kubernetes.io/proxy-body-size: "10m"
    nginx.ingress.kubernetes.io/rate-limit: "100"
spec:
  ingressClassName: nginx
  tls:
    - hosts:
        - api.mycompany.com
      secretName: tls-secret
  rules:
    - host: api.mycompany.com
      http:
        paths:
          - path: /api/orders
            pathType: Prefix
            backend:
              service:
                name: order-service-svc
                port:
                  number: 80
          - path: /api/payments
            pathType: Prefix
            backend:
              service:
                name: payment-service-svc
                port:
                  number: 80
```

---

## ConfigMap & Secret

```yaml
# ─── ConfigMap — konfiqurasiya data ──────────────────────
apiVersion: v1
kind: ConfigMap
metadata:
  name: order-service-config
  namespace: production
data:
  db.url: "jdbc:postgresql://postgres-svc:5432/orders_db"
  redis.host: "redis-svc"
  kafka.bootstrap: "kafka-svc:9092"
  # Spring Boot application.yml
  application.yml: |
    spring:
      datasource:
        url: ${SPRING_DATASOURCE_URL}
      jpa:
        hibernate:
          ddl-auto: validate
    logging:
      level:
        com.mycompany: INFO

---
# ─── Secret — həssas data ─────────────────────────────────
# Base64 encoded (şifrəli deyil!)
# Production: Vault, AWS Secrets Manager, Sealed Secrets
apiVersion: v1
kind: Secret
metadata:
  name: order-service-secrets
  namespace: production
type: Opaque
data:
  db.username: bXl1c2Vy           # echo -n "myuser" | base64
  db.password: bXlwYXNzd29yZA==  # echo -n "mypassword" | base64
  jwt.secret: c3VwZXItc2VjcmV0   # echo -n "super-secret" | base64
```

```java
// ─── Spring Boot Kubernetes konfigurasiyası ───────────────
// application-kubernetes.yml
/*
spring:
  config:
    import: "optional:configtree:/app/config/"

management:
  endpoint:
    health:
      probes:
        enabled: true      # liveness + readiness probe-ları aktiv
      show-details: always
  health:
    livenessstate:
      enabled: true
    readinessstate:
      enabled: true

server:
  shutdown: graceful       # SIGTERM gəldikdə graceful shutdown
spring:
  lifecycle:
    timeout-per-shutdown-phase: 30s
*/

// ─── Graceful Shutdown ────────────────────────────────────
@SpringBootApplication
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}

// application.yml:
// server.shutdown: graceful
// spring.lifecycle.timeout-per-shutdown-phase: 30s

// K8s SIGTERM → Spring graceful shutdown:
//   1. Readiness probe → "OUT_OF_SERVICE" → traffic kəsilir
//   2. Cari sorğular tamamlanır (30s)
//   3. Bean-lər destroy olunur
//   4. Pod dayandırılır
```

---

## Health Checks & Resource Limits

```yaml
# ─── 3 tip health probe ───────────────────────────────────
# startupProbe: İlk dəfə başlama yoxlaması (slow start)
# livenessProbe: Canlıdır? (false → restart)
# readinessProbe: Traffic qəbul edə bilir? (false → service-dən çıxar)

containers:
  - name: order-service
    # Startup: 5 dəqiqəyə qədər start zamanı
    startupProbe:
      httpGet:
        path: /actuator/health
        port: 8080
      failureThreshold: 30
      periodSeconds: 10

    # Liveness: 30s-dən bir yoxla, 3 uğursuz → restart
    livenessProbe:
      httpGet:
        path: /actuator/health/liveness
        port: 8080
      initialDelaySeconds: 0
      periodSeconds: 10
      timeoutSeconds: 3
      failureThreshold: 3

    # Readiness: 5s-dən bir yoxla, uğursuz → traffic yönləndirmə
    readinessProbe:
      httpGet:
        path: /actuator/health/readiness
        port: 8080
      initialDelaySeconds: 0
      periodSeconds: 5
      failureThreshold: 3

    # Resource Requests & Limits
    resources:
      requests:              # Scheduling üçün minimum lazım
        memory: "256Mi"      # 256 MiB RAM ayrılır
        cpu: "250m"          # 0.25 CPU core (250 millicores)
      limits:                # Maksimum istifadə edə bilər
        memory: "512Mi"      # Aşsa → OOMKilled!
        cpu: "1000m"         # Aşsa → throttled (deyil restart)
```

```bash
# ─── kubectl əsas əmrlər ─────────────────────────────────
kubectl get pods -n production
kubectl describe pod order-service-xxx -n production
kubectl logs order-service-xxx -n production
kubectl logs order-service-xxx -n production --previous  # Crash sonrası
kubectl exec -it order-service-xxx -n production -- sh
kubectl port-forward svc/order-service-svc 8080:80  # Local test
kubectl apply -f deployment.yaml
kubectl delete -f deployment.yaml
kubectl rollout status deployment/order-service -n production
kubectl rollout undo deployment/order-service -n production  # Rollback
kubectl scale deployment order-service --replicas=5
kubectl top pods -n production    # Resource istifadəsi
kubectl get events -n production  # Cluster event-ləri
```

---

## İntervyu Sualları

### 1. Kubernetes niyə lazımdır, Docker Compose kifayət deyilmi?
**Cavab:** Docker Compose single host üçündür — bir machine çökdükdə hamı çökür. Kubernetes multi-node cluster: bir node çöksə pod-lar avtomatik başqa node-a köçürülür (self-healing). K8s əlavə imkanlar: HPA (auto-scaling), rolling deployment (zero-downtime), service discovery, ingress controller, secret management, RBAC. Production multi-node tələb olarsa Kubernetes; kiçik single-server deployment üçün Docker Compose da yetərlidir.

### 2. Liveness vs Readiness probe fərqi nədir?
**Cavab:** **Liveness** — application sağlamdırmı? Uğursuz olsa container restart edilir. Deadlock, infinite loop kimi bərpa edilməz vəziyyətlər üçün. **Readiness** — application traffic qəbul edə bilir? Uğursuz olsa Service bu pod-a traffic göndərmir, amma restart edilmir. Start zamanı, DB connection yoxlanır, migration işlənir — hazır olmadan traffic qəbul etmə. **Startup** — yavaş başlayan app-lar üçün (Spring Boot ~30s): ilk başlamada liveness-i blok edir, start tamamlandıqda normal liveness başlayır.

### 3. Request vs Limit fərqi nədir?
**Cavab:** **Request** — Kubernetes Scheduler bu qədər resurs ayrılmış node-a yerləşdirir. Minimum zəmanəti. **Limit** — container bu qədərdən çox istifadə edə bilməz. CPU limitini aşarsa throttled (yavaşlayır, deyil restart). Memory limitini aşarsa OOMKilled (restart). Best practice: memory request = limit (QoS: Guaranteed). CPU request < limit (burst mümkün). Request olmayan pod hər noda gedə bilər — unpredictable performance.

### 4. Rolling Update necə işləyir?
**Cavab:** `maxSurge: 1, maxUnavailable: 0` ilə: yeni pod başlayır (readiness probe keçənə qədər gözlər) → köhnə pod birini dayandır → tekrar. Nəticə: həmişə ən az `replicas` sayda pod çalışır, downtime olmur. Rollback: `kubectl rollout undo deployment/myapp` — əvvəlki ReplicaSet-ə qayıdır. K8s son N ReplicaSet-i saxlayır (default 10). Canary deployment: iki Deployment + Service weights ilə.

### 5. ConfigMap vs Secret fərqi?
**Cavab:** **ConfigMap** — həssas olmayan konfiqurasiya: DB URL, feature flag, app settings. Plain text saxlanılır, log-larda görünə bilər. **Secret** — həssas data: şifrə, API key, TLS sertifikat. Base64 encoded (şifrəli deyil! — yalnız encoded). Etcd-də şifrəli saxlamaq üçün encryption at rest aktivləşdirilməlidir. Production best practice: Kubernetes Secret yetərli deyil, **HashiCorp Vault**, **AWS Secrets Manager**, ya da **Sealed Secrets** (GitOps üçün) istifadə edin. Secret-lər env variable ya da mounted file kimi container-ə ötürülür.

*Son yenilənmə: 2026-04-10*
