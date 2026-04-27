# Container Orchestration Concepts (Senior ⭐⭐⭐)

## İcmal
Container orchestration — çoxlu sayda container-ı avtomatik idarə etmək, deploy etmək, scale etmək və monitorinq etmək üçün platformdur. Kubernetes bu sahənin standart həllidir. Backend developer kimi Kubernetes-i dərinləməsinə bilmək məcburi deyil, amma əsas konseptləri başa düşmək production sistemlərini idarə etmək üçün vacibdir.

## Niyə Vacibdir
Müasir backend sistemlər containerize olunur, Kubernetes-də deploy edilir. "Bizim app Kubernetes-də çalışır" dedikdə interviewer sizin Pod, Deployment, Service, Ingress, HPA kimi konseptlərə aşina olub-olmadığınızı yoxlayır. Microservice arxitekturasında orchestration olmadan scale etmək, zero-downtime deploy etmək, failure recovery etmək mümkün deyil.

## Əsas Anlayışlar

### Kubernetes Əsas Obyektləri:

**Pod:**
- Kubernetes-in ən kiçik deploy vahidi
- Bir ya da bir neçə container (adətən bir)
- Eyni Pod-dakı container-lər localhost ilə danışır
- Ephemeral — Pod silinərsə IP dəyişir
- Birbaşa istifadə edilmir — Deployment idarə edir

**Deployment:**
- Pod-ların declarative idarəsi
- "3 replika olsun" → Kubernetes 3-ü saxlayır
- Rolling update, rollback dəstəği
- ReplicaSet-in üzərindəki abstraction

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel-app
  template:
    metadata:
      labels:
        app: laravel-app
    spec:
      containers:
        - name: app
          image: ghcr.io/mycompany/laravel-app:1.2.3
          ports:
            - containerPort: 80
          resources:
            requests:
              cpu: "100m"
              memory: "128Mi"
            limits:
              cpu: "500m"
              memory: "512Mi"
          readinessProbe:
            httpGet:
              path: /health
              port: 80
            initialDelaySeconds: 10
            periodSeconds: 5
          livenessProbe:
            httpGet:
              path: /health
              port: 80
            initialDelaySeconds: 30
            periodSeconds: 10
```

**Service:**
- Pod-lara stabil network endpoint verir
- Pod-lar yenidən başladıqda IP dəyişir — Service sabit qalır
- ClusterIP: Cluster daxilindən əlçatan
- NodePort: Node-un portunu expose edir
- LoadBalancer: Cloud provider LB yaradır

**Ingress:**
- HTTP/HTTPS traffic-i cluster-ə giriş nöqtəsi
- Path-based routing (`/api` → api-service, `/` → frontend-service)
- SSL termination
- Nginx Ingress Controller ən geniş yayılmışdır

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: app-ingress
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
    cert-manager.io/cluster-issuer: letsencrypt-prod
spec:
  tls:
    - hosts:
        - api.example.com
      secretName: api-tls
  rules:
    - host: api.example.com
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: laravel-service
                port:
                  number: 80
```

---

### ConfigMap və Secret:

**ConfigMap:**
- Non-sensitive konfiqurasiya məlumatları
- Environment variable ya da volume kimi mount edilir

**Secret:**
- Sensitive məlumatlar: passwords, API keys
- Base64-encoded (şifrələnmir — encryption at rest lazımdır)
- External Secret Operator (AWS Secrets Manager, Vault ilə inteqrasiya)

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: app-secrets
type: Opaque
stringData:
  DB_PASSWORD: "supersecret"
  APP_KEY: "base64:..."
```

---

### Horizontal Pod Autoscaler (HPA):

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel-app
  minReplicas: 2
  maxReplicas: 20
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

### Liveness vs Readiness Probe:

| | Liveness Probe | Readiness Probe |
|--|---------------|-----------------|
| Məqsəd | Container sağ mı? | Traffic ala bilir? |
| Uğursuz olduqda | Container restart | Service traffic dayandırır |
| İstifadə | Deadlock aşkarlama | Startup, rolling update |

```yaml
# Readiness: App startup-ı tamamlandıqdan sonra traffic al
readinessProbe:
  httpGet:
    path: /ready
    port: 8080
  initialDelaySeconds: 15  # App başlayana qədər gözlə
  periodSeconds: 5

# Liveness: App deadlock-a girərsə restart et
livenessProbe:
  httpGet:
    path: /health
    port: 8080
  initialDelaySeconds: 30
  periodSeconds: 10
  failureThreshold: 3
```

---

### Namespace:

- Cluster-i məntiqi olaraq bölmək üçün
- Nümunə: `production`, `staging`, `development`
- Resource quota namespace üzərindən qoyula bilir
- Network policy ilə namespace-lər arasında traffic məhdudlaşdırılır

---

### PersistentVolume və PersistentVolumeClaim:

- Container-lər ephemeral — Pod silinəndə data itirir
- PV: Real storage (AWS EBS, GCP Persistent Disk, NFS)
- PVC: Pod-un storage tələbi
- Laravel storage üçün: ReadWriteMany (RWX) lazımdır (çox replica üçün)

---

### Kubernetes Resource Limits:

```yaml
resources:
  requests:          # Scheduling üçün minimum qeyd olunur
    cpu: "100m"      # 100 millicores = 0.1 CPU
    memory: "128Mi"  # 128 mebibytes
  limits:            # Bu limitdən çox istifadə edilərsə throttle/kill
    cpu: "500m"
    memory: "512Mi"
```

**OOMKilled:** Memory limit keçildikdə Kubernetes container-ı kill edir.

---

### Helm:

Kubernetes üçün package manager. Çoxlu YAML faylını template-ə çevirib `values.yaml` ilə konfiqurasiya etməyi asanlaşdırır.

```bash
# Laravel app deploy etmək
helm install my-laravel ./charts/laravel-app \
  --set image.tag=1.2.3 \
  --set replicaCount=3 \
  -f values.production.yaml
```

---

### Docker vs Kubernetes Münasibəti:

```
Docker: "Bu container-ı necə build və run edim?"
Kubernetes: "Bu container-ı cluster-də necə idarə edim?"

Docker Compose → Development
Kubernetes → Production orchestration
```

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Kubernetes-i nə dərəcədə bilirsiz?" sualına dürüst olun: "Gündəlik istifadə edirəm / kubectl ilə işləyirəm / manifests yazıram / team-in DevOps-u idarə edir, amma konseptlərə aşinayam." Sonra bildiklərini əmin şəkildə izah et: Pod/Deployment fərqi, probe-lar, HPA, rolling update.

**Follow-up suallar:**
- "Zero-downtime deployment Kubernetes-də necə edilir?"
- "Liveness probe uğursuz olduqda nə baş verir?"
- "HPA CPU metric-dən başqa nə istifadə edə bilər?"

**Ümumi səhvlər:**
- Pod-ları birbaşa yaratmaq (Deployment olmadan)
- Readiness probe olmadan rolling update — köhnə Pod silinər, yeni hazır olmaz, downtime!
- Resource limits qoymamaq — bir Pod bütün node-u sıxışdıra bilər
- Secrets-i environment variable kimi açıq saxlamaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Pod, Service, Deployment bilirəm" vs "Rolling update zamanı readiness probe olmasa nə baş verər, onu niyə həmişə qoyuram" — ikincisi real experience göstərir.

## Nümunələr

### Tipik Interview Sualı
"Laravel app-ınızı Kubernetes-ə deploy edirsiz. Zero-downtime necə təmin edirsiniz?"

### Güclü Cavab
"Bir neçə mexanizm birlikdə işləyir. Birincisi, readinessProbe: yeni Pod hazır olmadan traffic almır — köhnə Pod-lar aktiv qalır. İkincisi, rolling update strategiyası: `maxSurge: 1, maxUnavailable: 0` — köhnə Pod silinmədən yeni Pod hazır olmalıdır. Üçüncüsü, preStop hook: Kubernetes Pod-a 'kill' siqnalı göndərməzdən əvvəl 5 saniyə gözlənilir — in-flight request-lər tamamlanır. Dördüncüsü, terminationGracePeriodSeconds: PHP-FPM-in işləyən request-ləri bitirməsi üçün vaxt verilir."

## Praktik Tapşırıqlar
- `minikube` ya da `kind` ilə lokal Kubernetes qur
- Laravel app üçün Deployment + Service + Ingress manifest-lər yaz
- HPA qur: CPU 70% keçəndə auto-scale
- Rolling update sına: readiness probe olmadan nə baş verir?

## Əlaqəli Mövzular
- [01-cicd-pipeline-design.md](01-cicd-pipeline-design.md) — CD pipeline Kubernetes-ə deploy edir
- [03-infrastructure-as-code.md](03-infrastructure-as-code.md) — Kubernetes config IaC-da idarə edilir
- [04-observability-pillars.md](04-observability-pillars.md) — Kubernetes cluster monitoring
- [08-capacity-planning.md](08-capacity-planning.md) — Resource planning HPA ilə
