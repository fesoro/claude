# Kubernetes Operators

## Nədir? (What is it?)

Kubernetes Operator – konkret bir tətbiqi (database, message queue, monitoring system) Kubernetes klasteri daxilində avtomatlaşdırılmış şəkildə idarə edən xüsusi controller növüdür. 2016-cı ildə CoreOS şirkəti tərəfindən təklif olunmuş **Operator Pattern** fikirini irəli sürdü: "bir sysadmin-in manual gördüyü işi (DB yaratmaq, backup, upgrade, failover) Kubernetes-native formada kodlaşdır və cluster özü həmin işi avtomatik etsin". Operator iki komponentdən ibarətdir: (1) **CRD (Custom Resource Definition)** – yeni K8s resursu tipi təyin edir (məs. `PostgresCluster`, `Prometheus`, `Certificate`), (2) **Controller** – bu custom resource-ları izləyir və desired state-ə çatdırır (reconcile loop). Prometheus Operator, cert-manager, Strimzi (Kafka), CloudNativePG (PostgreSQL), Elastic Cloud on Kubernetes, PostgresOperator (Zalando), RabbitMQ Cluster Operator – mövcud operator-lar çox populyardır. Operator Hub (operatorhub.io) mərkəzi kataloqdur. Yazmaq üçün **kubebuilder** (Kubernetes SIG), **Operator SDK** (Red Hat) Go-da; **KubeBuilder for Rust (kube-rs)**, **Metacontroller** (YAML + webhook), **kopf** (Python) alternativlər var.

## Əsas Konseptlər (Key Concepts)

### Operator Pattern

```
Kubernetes native resource:    Custom resource:
- Pod, Deployment, Service     - PostgresCluster
- ConfigMap, Secret            - Certificate
- Job, CronJob                 - PrometheusRule
                               - KafkaTopic
                                
User applies:                 Controller watches:
kubectl apply -f my-db.yaml  → reconcile loop
                               → create StatefulSet
                               → create Service
                               → create Secret
                               → periodic backup
                               → health check
```

### Reconcile Loop (idarəetmənin ürəyi)

```go
// Pseudo-code
for {
    currentResources := observe()      // cluster-dən oxu
    desiredResources := userSpec       // CRD-dən desired state
    
    diff := desired - current
    for _, change := range diff {
        apply(change)
    }
    
    updateStatus(result)
}
```

### Operator Maturity Levels (Red Hat)

```
Level 1 – Basic Install
         CRD + Deployment avtomatlaşdırılır

Level 2 – Seamless Upgrades
         Operator versiya yüksəldir, downtime yox

Level 3 – Full Lifecycle
         Backup, restore, scale, failover

Level 4 – Deep Insights
         Metrics, alerts, logging entegrasiyası

Level 5 – Auto Pilot
         Auto-healing, auto-tuning, horizontal scaling
```

### Controller Runtime

```
K8s API → Informer (local cache, watch event)
           ↓
       Work queue
           ↓
       Reconcile() - idempotent
           ↓
       Update status
```

### CRD və Scheme

```
CustomResourceDefinition – K8s API-yə yeni resurs tipi qeyd olur
   ↓
OpenAPI v3 schema – validation (required fields, enum, regex)
   ↓
Subresources – status (ayrı update), scale (HPA dəstəyi)
   ↓
Conversion webhook – multi-version CRD (v1alpha1 → v1)
   ↓
Validating/Mutating admission webhook – request-lər üçün validation/mutation
```

### Mövcud məşhur operator-lar

```
PROMETHEUS OPERATOR:
- Prometheus, Alertmanager, ServiceMonitor CRD-ləri
- ServiceMonitor label selector – avtomatik scrape

CERT-MANAGER:
- Certificate, Issuer, ClusterIssuer CRD-ləri
- Let's Encrypt ACME, self-signed, Vault, private CA
- Avtomatik yeniləmə (30 gün qalmış)

STRIMZI (Kafka):
- Kafka, KafkaTopic, KafkaUser, KafkaConnect CRD-ləri
- Zookeeper və ya KRaft

POSTGRES OPERATORS:
- CloudNativePG (recommended 2024+)
- Zalando Postgres Operator
- Crunchy Data PGO

DATABASE:
- MongoDB Community Operator
- MariaDB Operator
- Percona XtraDB Cluster Operator
- Vitess (MySQL sharding)

OBSERVABILITY:
- Grafana Operator
- Jaeger Operator
- OpenTelemetry Operator
- Loki Operator

SECURITY:
- External Secrets Operator
- Sealed Secrets
- Gatekeeper (OPA)

OTHER:
- Argo CD, Argo Workflows
- Velero (backup)
- MetalLB (bare metal LB)
- Istio Operator
```

## Praktiki Nümunələr

### Prometheus Operator (ServiceMonitor)

```yaml
# prometheus.yaml
apiVersion: monitoring.coreos.com/v1
kind: Prometheus
metadata:
  name: laravel-prometheus
  namespace: monitoring
spec:
  replicas: 2
  serviceAccountName: prometheus
  serviceMonitorSelector:
    matchLabels:
      team: backend
  retention: 30d
  resources:
    requests: { memory: 2Gi, cpu: 500m }
    limits:   { memory: 4Gi, cpu: 2 }
  storage:
    volumeClaimTemplate:
      spec:
        storageClassName: gp3
        resources:
          requests: { storage: 100Gi }

---
# Laravel API metric-lərini avtomatik scrape etmək üçün:
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: laravel-api
  namespace: production
  labels:
    team: backend       # Prometheus selector match edir
spec:
  selector:
    matchLabels:
      app: laravel-api
  endpoints:
    - port: metrics
      interval: 30s
      path: /metrics
      scrapeTimeout: 10s
```

### cert-manager (avtomatik SSL)

```yaml
# issuer.yaml - Let's Encrypt ClusterIssuer
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@example.com
    privateKeySecretRef:
      name: letsencrypt-prod-key
    solvers:
      - http01:
          ingress:
            class: nginx

---
# Laravel üçün sertifikat
apiVersion: cert-manager.io/v1
kind: Certificate
metadata:
  name: api-example-com
  namespace: production
spec:
  secretName: api-example-com-tls
  issuerRef:
    name: letsencrypt-prod
    kind: ClusterIssuer
  commonName: api.example.com
  dnsNames:
    - api.example.com
    - www.api.example.com
  duration: 2160h       # 90 gün
  renewBefore: 720h     # 30 gündən qabaq yenilə
```

### Strimzi Kafka Operator

```yaml
apiVersion: kafka.strimzi.io/v1beta2
kind: Kafka
metadata:
  name: production-kafka
  namespace: kafka
spec:
  kafka:
    version: 3.7.0
    replicas: 3
    listeners:
      - name: tls
        port: 9093
        type: internal
        tls: true
        authentication: { type: tls }
    config:
      min.insync.replicas: 2
      default.replication.factor: 3
      auto.create.topics.enable: false
    storage:
      type: jbod
      volumes:
        - id: 0
          type: persistent-claim
          size: 200Gi
          deleteClaim: false
    resources:
      requests: { memory: 4Gi, cpu: 1 }
      limits:   { memory: 8Gi, cpu: 2 }
  zookeeper:
    replicas: 3
    storage:
      type: persistent-claim
      size: 20Gi
  entityOperator:
    topicOperator: {}
    userOperator: {}

---
apiVersion: kafka.strimzi.io/v1beta2
kind: KafkaTopic
metadata:
  name: laravel-events
  namespace: kafka
  labels:
    strimzi.io/cluster: production-kafka
spec:
  partitions: 12
  replicas: 3
  config:
    retention.ms: 604800000   # 7 gün
    segment.bytes: 1073741824 # 1GB
```

### CloudNativePG (PostgreSQL Operator)

```yaml
apiVersion: postgresql.cnpg.io/v1
kind: Cluster
metadata:
  name: laravel-db
  namespace: production
spec:
  instances: 3     # primary + 2 replica

  postgresql:
    parameters:
      shared_buffers: "2GB"
      effective_cache_size: "6GB"
      work_mem: "32MB"
      max_connections: "200"

  bootstrap:
    initdb:
      database: laravel
      owner: laravel_app
      encoding: UTF8

  storage:
    size: 100Gi
    storageClass: gp3

  resources:
    requests: { memory: 4Gi, cpu: 1 }
    limits:   { memory: 8Gi, cpu: 2 }

  backup:
    retentionPolicy: "30d"
    barmanObjectStore:
      destinationPath: s3://laravel-backups/postgres
      s3Credentials:
        accessKeyId:
          name: aws-creds
          key: ACCESS_KEY_ID
        secretAccessKey:
          name: aws-creds
          key: SECRET_ACCESS_KEY
      wal:
        compression: gzip
      data:
        compression: gzip

  monitoring:
    enablePodMonitor: true

---
# Scheduled backup
apiVersion: postgresql.cnpg.io/v1
kind: ScheduledBackup
metadata:
  name: laravel-db-backup
  namespace: production
spec:
  schedule: "0 3 * * *"   # hər gün 03:00
  backupOwnerReference: self
  cluster:
    name: laravel-db
```

### Kubebuilder ilə öz Operator-unuzu yazmaq

```bash
# 1) Kubebuilder install
brew install kubebuilder       # macOS
# Linux: curl -L ... -o /usr/local/bin/kubebuilder

# 2) Yeni layihə
mkdir laravel-operator && cd laravel-operator
kubebuilder init --domain example.com --repo github.com/myorg/laravel-operator

# 3) API yaradırıq
kubebuilder create api --group apps --version v1alpha1 --kind LaravelApp

# Bu yaradır:
# api/v1alpha1/laravelapp_types.go     – CRD Go structs
# internal/controller/laravelapp_controller.go – reconcile məntiqi
# config/crd/bases/apps.example.com_laravelapps.yaml – CRD YAML
```

### CRD Go types (spec + status)

```go
// api/v1alpha1/laravelapp_types.go
package v1alpha1

import (
    corev1 "k8s.io/api/core/v1"
    metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
)

// LaravelAppSpec – user-in desired state
type LaravelAppSpec struct {
    // +kubebuilder:validation:Required
    // +kubebuilder:validation:Pattern=`^[a-z0-9.]+/[a-z0-9.-]+:[a-zA-Z0-9._-]+$`
    Image string `json:"image"`

    // +kubebuilder:validation:Minimum=1
    // +kubebuilder:validation:Maximum=50
    // +kubebuilder:default=3
    Replicas int32 `json:"replicas,omitempty"`

    // +kubebuilder:validation:Enum=production;staging;development
    Environment string `json:"environment"`

    Database DatabaseRef `json:"database"`

    Horizon HorizonSpec `json:"horizon,omitempty"`

    Resources corev1.ResourceRequirements `json:"resources,omitempty"`

    Ingress IngressSpec `json:"ingress,omitempty"`
}

type DatabaseRef struct {
    SecretName string `json:"secretName"`
}

type HorizonSpec struct {
    Enabled   bool  `json:"enabled,omitempty"`
    Replicas  int32 `json:"replicas,omitempty"`
}

type IngressSpec struct {
    Enabled bool   `json:"enabled,omitempty"`
    Host    string `json:"host,omitempty"`
    TLS     bool   `json:"tls,omitempty"`
}

// LaravelAppStatus – real state
type LaravelAppStatus struct {
    ReadyReplicas int32              `json:"readyReplicas"`
    Phase         string             `json:"phase"` // Pending, Running, Failed
    LastMigration string             `json:"lastMigration,omitempty"`
    Conditions    []metav1.Condition `json:"conditions,omitempty"`
}

// +kubebuilder:object:root=true
// +kubebuilder:subresource:status
// +kubebuilder:subresource:scale:specpath=.spec.replicas,statuspath=.status.readyReplicas
// +kubebuilder:printcolumn:name="Replicas",type=integer,JSONPath=`.status.readyReplicas`
// +kubebuilder:printcolumn:name="Phase",type=string,JSONPath=`.status.phase`
// +kubebuilder:printcolumn:name="Age",type=date,JSONPath=`.metadata.creationTimestamp`
type LaravelApp struct {
    metav1.TypeMeta   `json:",inline"`
    metav1.ObjectMeta `json:"metadata,omitempty"`

    Spec   LaravelAppSpec   `json:"spec,omitempty"`
    Status LaravelAppStatus `json:"status,omitempty"`
}

// +kubebuilder:object:root=true
type LaravelAppList struct {
    metav1.TypeMeta `json:",inline"`
    metav1.ListMeta `json:"metadata,omitempty"`
    Items           []LaravelApp `json:"items"`
}
```

### Reconcile məntiqi

```go
// internal/controller/laravelapp_controller.go
func (r *LaravelAppReconciler) Reconcile(ctx context.Context, req ctrl.Request) (ctrl.Result, error) {
    log := log.FromContext(ctx)

    // 1) LaravelApp oxu
    var app appsv1alpha1.LaravelApp
    if err := r.Get(ctx, req.NamespacedName, &app); err != nil {
        return ctrl.Result{}, client.IgnoreNotFound(err)
    }

    // 2) Migration Job - bir dəfə icra et
    if app.Status.LastMigration != app.Spec.Image {
        if err := r.runMigrationJob(ctx, &app); err != nil {
            return ctrl.Result{RequeueAfter: 30 * time.Second}, err
        }
        app.Status.LastMigration = app.Spec.Image
    }

    // 3) Deployment (web tier)
    deploy := r.buildDeployment(&app)
    if err := ctrl.SetControllerReference(&app, deploy, r.Scheme); err != nil {
        return ctrl.Result{}, err
    }
    if err := r.createOrUpdate(ctx, deploy); err != nil {
        return ctrl.Result{}, err
    }

    // 4) Horizon deployment (əgər enabled)
    if app.Spec.Horizon.Enabled {
        horizon := r.buildHorizonDeployment(&app)
        ctrl.SetControllerReference(&app, horizon, r.Scheme)
        if err := r.createOrUpdate(ctx, horizon); err != nil {
            return ctrl.Result{}, err
        }
    }

    // 5) Service
    svc := r.buildService(&app)
    ctrl.SetControllerReference(&app, svc, r.Scheme)
    r.createOrUpdate(ctx, svc)

    // 6) Ingress
    if app.Spec.Ingress.Enabled {
        ing := r.buildIngress(&app)
        ctrl.SetControllerReference(&app, ing, r.Scheme)
        r.createOrUpdate(ctx, ing)
    }

    // 7) Status update
    var currentDeploy appsv1.Deployment
    if err := r.Get(ctx, types.NamespacedName{Name: app.Name, Namespace: app.Namespace}, &currentDeploy); err == nil {
        app.Status.ReadyReplicas = currentDeploy.Status.ReadyReplicas
        if app.Status.ReadyReplicas == app.Spec.Replicas {
            app.Status.Phase = "Running"
        } else {
            app.Status.Phase = "Pending"
        }
    }

    if err := r.Status().Update(ctx, &app); err != nil {
        return ctrl.Result{}, err
    }

    // 30 saniyədən sonra yenidən reconcile
    return ctrl.Result{RequeueAfter: 30 * time.Second}, nil
}

// SetupWithManager
func (r *LaravelAppReconciler) SetupWithManager(mgr ctrl.Manager) error {
    return ctrl.NewControllerManagedBy(mgr).
        For(&appsv1alpha1.LaravelApp{}).
        Owns(&appsv1.Deployment{}).
        Owns(&corev1.Service{}).
        Owns(&networkingv1.Ingress{}).
        Owns(&batchv1.Job{}).
        Complete(r)
}
```

### Custom Resource (LaravelApp) istifadə

```yaml
apiVersion: apps.example.com/v1alpha1
kind: LaravelApp
metadata:
  name: my-app
  namespace: production
spec:
  image: myregistry.io/laravel:v2.1.0
  replicas: 5
  environment: production
  database:
    secretName: postgres-creds
  horizon:
    enabled: true
    replicas: 2
  ingress:
    enabled: true
    host: api.example.com
    tls: true
  resources:
    requests: { cpu: 500m, memory: 512Mi }
    limits:   { cpu: 2,    memory: 1Gi }

# kubectl apply -f laravelapp.yaml
# Operator avtomatik:
# - migration job icra edir
# - web deployment (5 replica)
# - horizon deployment (2 replica)
# - service + ingress + TLS
# - status update edir
```

## PHP/Laravel ilə İstifadə

### Operator-dan istifadə: Laravel-in K8s-də full avtomatlaşdırılması

`LaravelApp` custom resource developer üçün abstract edir – yalnız `image`, `replicas`, `database ref` verir, qalan infrastruktur (deployment, service, ingress, horizon, migration, TLS, PDB, HPA, PodMonitor) operator tərəfindən avtomatik qurulur. Bu yanaşma Helm chart-dan fərqlidir: Helm sadəcə template render edir, operator **davranış** (migration, backup) əlavə edir.

### Operator ilə avtomatik backup (CronJob yaradılması)

```go
// reconciler hissəsindən
func (r *LaravelAppReconciler) ensureBackupCronJob(ctx context.Context, app *v1alpha1.LaravelApp) error {
    if app.Spec.Environment != "production" {
        return nil // yalnız prod-da backup
    }

    job := &batchv1.CronJob{
        ObjectMeta: metav1.ObjectMeta{
            Name:      app.Name + "-backup",
            Namespace: app.Namespace,
        },
        Spec: batchv1.CronJobSpec{
            Schedule: "0 2 * * *",
            JobTemplate: batchv1.JobTemplateSpec{
                Spec: batchv1.JobSpec{
                    Template: corev1.PodTemplateSpec{
                        Spec: corev1.PodSpec{
                            RestartPolicy: corev1.RestartPolicyOnFailure,
                            Containers: []corev1.Container{{
                                Name:  "backup",
                                Image: app.Spec.Image,
                                Command: []string{"php", "artisan", "backup:run", "--disable-notifications"},
                                EnvFrom: []corev1.EnvFromSource{{
                                    SecretRef: &corev1.SecretEnvSource{
                                        LocalObjectReference: corev1.LocalObjectReference{Name: app.Spec.Database.SecretName},
                                    },
                                }},
                            }},
                        },
                    },
                },
            },
        },
    }
    ctrl.SetControllerReference(app, job, r.Scheme)
    return r.createOrUpdate(ctx, job)
}
```

### External Secrets Operator (Vault → K8s Secret)

```yaml
apiVersion: external-secrets.io/v1beta1
kind: SecretStore
metadata:
  name: vault-store
  namespace: production
spec:
  provider:
    vault:
      server: "https://vault.example.com"
      path: "kv"
      version: "v2"
      auth:
        kubernetes:
          mountPath: "kubernetes"
          role: "laravel-app"
---
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: laravel-secrets
  namespace: production
spec:
  refreshInterval: 1h
  secretStoreRef:
    name: vault-store
    kind: SecretStore
  target:
    name: laravel-env
  data:
    - secretKey: APP_KEY
      remoteRef: { key: laravel/prod, property: app_key }
    - secretKey: DB_PASSWORD
      remoteRef: { key: laravel/prod, property: db_password }
    - secretKey: STRIPE_SECRET
      remoteRef: { key: laravel/prod, property: stripe_secret }
```

Laravel deployment həmin `laravel-env` secret-ini `envFrom` ilə istifadə edir – operator rotation avtomatik qoyur.

### Karpenter (auto-scaling node operator)

```yaml
apiVersion: karpenter.sh/v1
kind: NodePool
metadata:
  name: laravel-workers
spec:
  template:
    spec:
      nodeClassRef:
        name: default
      requirements:
        - key: kubernetes.io/arch
          operator: In
          values: ["amd64"]
        - key: karpenter.sh/capacity-type
          operator: In
          values: ["spot", "on-demand"]
        - key: node.kubernetes.io/instance-type
          operator: In
          values: ["m5.large", "m5a.large", "m6i.large"]
  limits:
    cpu: "100"
    memory: "400Gi"
  disruption:
    consolidationPolicy: WhenEmptyOrUnderutilized
    consolidateAfter: 30s
```

## Interview Sualları (Q&A)

**S1: Operator pattern nə üçündür, sadə Helm chart kifayət deyilmi?**
C: Helm chart **bir dəfə** template render edib deploy edir – sonra heç nə etmir. Operator **davamlı** işləyir: (1) day-2 operations (backup, restore, upgrade, scale, failover), (2) cluster state-i davamlı `desired state`-ə çəkir (GitOps-dan fərqli dəqiqlikdə), (3) xüsusi biznes məntiqi – DB migration sequential, PVC backup, version-specific upgrade. Helm "ilk install" üçün yaxşıdır, operator "yaşadan" üçün. Məsələn, PostgreSQL Helm chart qurur, amma failover etmir; operator failover edir, replica promote edir, backup götürür.

**S2: CRD və ConfigMap fərqi nədir?**
C: **ConfigMap** – ixtiyari key-value data, K8s heç bir məna vermir. **CRD** – K8s API-ə yeni resurs tipi qeyd edir: (1) **schema validation** (OpenAPI – required, enum, pattern), (2) **RBAC** – `kubectl auth can-i create postgrescluster`, (3) **status subresource** – spec və status ayrıca update olunur, (4) **printer columns** `kubectl get ...` cədvəlində özel sütunlar, (5) **conversion webhook** versiyalar arası, (6) **finalizer** dəstəyi. ConfigMap tətbiqin konfiqurasiyasıdır; CRD yeni K8s obyektidir.

**S3: Reconcile loop niyə "idempotent" olmalıdır?**
C: Reconcile eyni resurs üçün dəfələrlə çağırıla bilər (hadisə baş verən hər dəfə, 30 saniyədə bir requeue). Hər dəfə eyni nəticə verməlidir – əgər "create" işləyirsə, ikinci dəfə "AlreadyExists" error verməməlidir. Patterns: (1) `CreateOrUpdate` – əvvəl Get, sonra Create və ya Update. (2) `Server-Side Apply` – K8s avtomatik merge edir. (3) `OwnerReference` – əl ilə silməsək, owner silindikdə children-lər avtomatik silinir. Idempotency olmadan operator qeyri-sabit olur, flapping baş verir.

**S4: Operator versioning necə idarə olunur (v1alpha1 → v1)?**
C: Kubernetes API maturity: `v1alpha1` (breaking dəyişikliklər mümkündür) → `v1beta1` (stabil amma API dəyişə bilər) → `v1` (backward-compatible). Operator yeni versiya gətirdikdə: (1) CRD-də yeni version əlavə olunur, (2) **Conversion webhook** – köhnə kind-dan yeni-ə tərcümə, (3) Storage version seçilir, (4) Existing resurs-lar avtomatik yeni versiyaya convert olunur, (5) Deprecation dövrü verilir (2-3 minor release). kubebuilder bunu API versioning command ilə avtomatlaşdırır.

**S5: Operator-da finalizer nədir?**
C: Finalizer – resurs silinərkən ilk "cleanup" addımını təmin edən mexanizm. `metadata.finalizers: ["laravelapp.example.com/cleanup"]` əlavə olunur. User `kubectl delete` edəndə K8s resursu dərhal silmir – `deletionTimestamp` qoyur. Operator bunu gördükdə: (1) cleanup et (external DB sil, S3 bucket boşalt), (2) Finalizer list-dən öz entry-sini çıxar. Finalizer boşaldıqdan sonra K8s resursu faktiki silir. Bu external resursları orfan olmadan təmizləməyə imkan verir.

**S6: Operator SDK və kubebuilder fərqi nədir?**
C: Hər ikisi operator yaratmaq üçün framework-dür: (1) **kubebuilder** – Kubernetes SIG tərəfindən, Go-based, sadə struktur, controller-runtime-dan birbaşa istifadə edir. (2) **Operator SDK** – Red Hat tərəfindən, OpenShift ekosistemi ilə sıx, Go + Ansible + Helm-based operators yaza bilir. Operator SDK kubebuilder-ı daxilində istifadə edir, əlavə OLM (Operator Lifecycle Manager) dəstəyi verir. Red Hat/OpenShift istifadə edən enterprise-lar Operator SDK seçir, yalnız vanilla Kubernetes-dirsə kubebuilder daha yüngüldür.

**S7: `Watch` və `Own` arasında fərq nədir?**
C: Controller-in hansı resurs dəyişikliklərinə reaksiya verəcəyini təyin edir: (1) **`For`** – ana resurs tipi (LaravelApp). (2) **`Owns`** – operator-ın yaratdığı child resurslar (Deployment, Service). Child dəyişərsə parent üçün reconcile baş verir – OwnerReference ilə bağlıdır. (3) **`Watches`** – ixtiyari resurs tipi izlə və müəyyən məntiqlə event-ləri parent resursa çevir (məsələn, ConfigMap dəyişəndə LaravelApp reconcile olsun). Owns ən ümumidir, Watches advanced use-case üçündür.

**S8: Operator-lar necə test olunur?**
C: Üç səviyyə: (1) **Unit test** – reconciler fənksiyaları fake client-lə yoxla (controller-runtime-da `fake.NewClientBuilder`). (2) **envtest** – real API server + etcd binary-si lokal işlədilir, reconciler orada test olunur (KinD-dən tez). (3) **e2e** – real K8s klasterində (KinD, k3d, minikube) operator deploy edilir, real scenario sınanır. Ginkgo + Gomega test framework-ü kubebuilder-in default-udur. CI-da e2e testlər matrix strategy ilə müxtəlif K8s versiyalarında işləyir.

**S9: Operator-lar niyə bəzi hallarda problemdir?**
C: Problemlər: (1) **Maintenance** – hər K8s upgrade-də operator test etmək lazım, API version deprecation. (2) **RBAC overhead** – operator-lar çox geniş icazələr tələb edir, security risk. (3) **Performance** – çox CRD və çox reconcile = API server yükü, etcd doldurması. (4) **Debugging** – "niyə resurs yaranmadı?" → operator log-u, webhook log-u, RBAC, admission controller – mürəkkəb flow. (5) **Vendor lock-in** – Postgres Operator seçəndə o specific operator-a bağlanırsan. Sadə use case-lər üçün Helm + Argo CD kifayətdir.

**S10: Operator maturity level-i nədir və 5-ə çatmaq lazımdırmı?**
C: Red Hat 5 səviyyəli capability model-i: L1 Basic Install, L2 Seamless Upgrades, L3 Full Lifecycle, L4 Deep Insights, L5 Auto Pilot. **Hər operator L5-ə ehtiyac duymur** – daxili tooling üçün L2 yaxşıdır; kommersiya operator-ları (OperatorHub-da satılan) üçün L4-L5 gözlənilir. L5 (auto-healing, auto-tuning) pricey – ML, extensive testing lazımdır. Praktik yanaşma: use case-i müəyyən et, lazım olan səviyyəyə çatmağa fokuslan, "enterprise grade" hərəkət etmə tələb olunmadıqca.

## Best Practices

1. **Kubebuilder ilə başla** – struktur və boilerplate avtomatik.
2. **CRD validation** – OpenAPI schema, required fields, enum, pattern hamısı CRD-də.
3. **Status subresource** – spec və status ayrı update – user və controller qarışmır.
4. **OwnerReference** – child resurslar avtomatik silinsin, orfan qalmasın.
5. **Finalizer** external resurs cleanup üçün – S3 bucket, DNS, external DB.
6. **Reconcile idempotent** – CreateOrUpdate və ya Server-Side Apply pattern.
7. **RequeueAfter** – periodic reconciliation, health check (30s-5min).
8. **Error handling** – transient error-da retry, permanent-də user-a bildir (status condition).
9. **Event recording** – `r.Recorder.Event(app, "Normal", "Created", ...)` user-ə görünür.
10. **Metrics** – controller-runtime default metric-lər + custom business metric.
11. **Leader election** – operator multi-replica run edirsə, yalnız bir instance aktiv olsun.
12. **RBAC minimum** – operator ehtiyac duyduğu resurslara məhdud icazə.
13. **Webhook** validation/mutation – CRD validation əvvəl user input-u yoxla.
14. **Versioning** – v1alpha1 → v1beta1 → v1, conversion webhook.
15. **OperatorHub listing** – community-yə açıq operator üçün OLM bundle hazırla.
