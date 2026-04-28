# Kubernetes Deep Dive (Lead)

> **Səviyyə (Level):** ⭐⭐⭐⭐ Lead

## Nədir? (What is it?)

Kubernetes Deep Dive — klaster idarəetməsinin ən dərin qatlarını əhatə edir: RBAC ilə təhlükəsizlik, NetworkPolicy ilə şəbəkə izolyasiyası, CRD və Operator Pattern ilə K8s-i genişləndirmə, multi-tenancy arxitekturası. Bu mövzular Senior-dan Lead-ə keçid üçün vacibdir — artıq yalnız manifest yazmırsınız, klasterin arxitekturasını formalaşdırırsınız.

## RBAC (Role-Based Access Control)

### 1. RBAC Arxitekturası

```
Subject → (Bind) → Role/ClusterRole → (Grants) → Resources + Verbs

Subjects:
  User         — insan (kubectl, CI/CD)
  ServiceAccount — pod/process
  Group         — user qrupu

Role Tipləri:
  Role         — namespace-scoped
  ClusterRole  — cluster-wide (PV, Node, Namespace özü)
```

### 2. ServiceAccount (Laravel App üçün)

```yaml
# sa.yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: laravel-app
  namespace: production
  annotations:
    eks.amazonaws.com/role-arn: "arn:aws:iam::123:role/laravel-s3"  # IRSA
automountServiceAccountToken: false   # Default token mount-u söndür

---
# role.yaml — yalnız lazım olan icazələr
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: laravel-app
  namespace: production
rules:
  - apiGroups: [""]
    resources: ["secrets"]
    resourceNames: ["laravel-secret"]   # Yalnız bu Secret-ə icazə
    verbs: ["get"]
  - apiGroups: [""]
    resources: ["configmaps"]
    resourceNames: ["laravel-config"]
    verbs: ["get", "list", "watch"]

---
# rolebinding.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: laravel-app
  namespace: production
subjects:
  - kind: ServiceAccount
    name: laravel-app
    namespace: production
roleRef:
  kind: Role
  name: laravel-app
  apiGroup: rbac.authorization.k8s.io
```

### 3. ClusterRole və Aggregation

```yaml
# Agregated ClusterRole — ayrı team-lər öz rule-larını əlavə edir
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: monitoring
  labels:
    rbac.example.com/aggregate-to-monitoring: "true"
rules:
  - apiGroups: ["monitoring.coreos.com"]
    resources: ["*"]
    verbs: ["*"]

---
# ClusterRole ilə aggregationRule
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: monitoring-full
aggregationRule:
  clusterRoleSelectors:
    - matchLabels:
        rbac.example.com/aggregate-to-monitoring: "true"
rules: []   # Agregasiya avtomatik doldurur
```

### 4. RBAC Audit

```bash
# ServiceAccount-un icazələrini yoxla
kubectl auth can-i get pods --as=system:serviceaccount:production:laravel-app -n production
# yes

kubectl auth can-i delete deployments --as=system:serviceaccount:production:laravel-app -n production
# no

# Bütün icazələri siyahıla (kubectl-plugin-access lazımdır)
kubectl access-matrix -n production --sa laravel-app

# Audit log-da RBAC failure-lar
# /var/log/audit.log → reason:"RBAC: not allowed"
```

## NetworkPolicy — Şəbəkə İzolyasiyası

### 1. Default Deny All

Default olaraq K8s pod-lar bir-biriləri ilə azad danışa bilər. NetworkPolicy ilə izolyasiya qurulur:

```yaml
# default-deny.yaml — bütün ingress/egress blokla
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny-all
  namespace: production
spec:
  podSelector: {}        # Bütün pod-lara tətbiq et
  policyTypes:
    - Ingress
    - Egress
```

### 2. Laravel App üçün Selective Allow

```yaml
# laravel-network-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: laravel-app
  namespace: production
spec:
  podSelector:
    matchLabels:
      app: laravel
  policyTypes:
    - Ingress
    - Egress

  ingress:
    # Yalnız ingress-nginx-dən gəlsin
    - from:
        - namespaceSelector:
            matchLabels:
              kubernetes.io/metadata.name: ingress-nginx
          podSelector:
            matchLabels:
              app.kubernetes.io/name: ingress-nginx
      ports:
        - protocol: TCP
          port: 9000

  egress:
    # MySQL-ə çıxış
    - to:
        - podSelector:
            matchLabels:
              app: mysql
      ports:
        - protocol: TCP
          port: 3306
    # Redis-ə çıxış
    - to:
        - podSelector:
            matchLabels:
              app: redis
      ports:
        - protocol: TCP
          port: 6379
    # DNS
    - to:
        - namespaceSelector:
            matchLabels:
              kubernetes.io/metadata.name: kube-system
          podSelector:
            matchLabels:
              k8s-app: kube-dns
      ports:
        - protocol: UDP
          port: 53
    # External HTTP/HTTPS (mail, API)
    - to:
        - ipBlock:
            cidr: 0.0.0.0/0
            except:
              - 10.0.0.0/8      # Cluster-internal blok
              - 172.16.0.0/12
              - 192.168.0.0/16
      ports:
        - protocol: TCP
          port: 443
```

### 3. Namespace İzolyasiyası

```yaml
# Staging namespace-i production-dan ayır
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: deny-from-other-namespaces
  namespace: production
spec:
  podSelector: {}
  ingress:
    - from:
        - podSelector: {}           # Yalnız eyni namespace-dən
```

## CRD (Custom Resource Definition) və Operator Pattern

### 1. CRD Nədir

CRD — K8s API-ni genişləndirir. Öz resource tipinizi yarada bilərsiniz:

```yaml
# laravel-app-crd.yaml
apiVersion: apiextensions.k8s.io/v1
kind: CustomResourceDefinition
metadata:
  name: laravelapps.example.com
spec:
  group: example.com
  names:
    kind: LaravelApp
    listKind: LaravelAppList
    plural: laravelapps
    singular: laravelapp
    shortNames: ["la"]
  scope: Namespaced
  versions:
    - name: v1
      served: true
      storage: true
      schema:
        openAPIV3Schema:
          type: object
          properties:
            spec:
              type: object
              required: ["image", "replicas"]
              properties:
                image:
                  type: string
                replicas:
                  type: integer
                  minimum: 1
                  maximum: 100
                dbHost:
                  type: string
                queueWorkers:
                  type: integer
                  default: 2
            status:
              type: object
              properties:
                phase:
                  type: string
                  enum: ["Pending", "Running", "Failed"]
                readyReplicas:
                  type: integer
      additionalPrinterColumns:
        - name: Image
          type: string
          jsonPath: .spec.image
        - name: Replicas
          type: integer
          jsonPath: .spec.replicas
        - name: Phase
          type: string
          jsonPath: .status.phase
```

```yaml
# CR instance (LaravelApp custom resource)
apiVersion: example.com/v1
kind: LaravelApp
metadata:
  name: my-laravel
  namespace: production
spec:
  image: "myregistry/laravel:1.2.0"
  replicas: 5
  dbHost: "mysql-service"
  queueWorkers: 3
```

### 2. Operator Pattern (Go ilə)

Operator = CRD + Controller. Controller desired state-i actual state-ə çatdırır:

```go
// Simplified Operator reconciler (kubebuilder ilə)
func (r *LaravelAppReconciler) Reconcile(ctx context.Context, req ctrl.Request) (ctrl.Result, error) {
    // CR-ni oxu
    var app examplev1.LaravelApp
    if err := r.Get(ctx, req.NamespacedName, &app); err != nil {
        return ctrl.Result{}, client.IgnoreNotFound(err)
    }

    // Deployment yaratmaq və ya yeniləmək
    deployment := &appsv1.Deployment{
        ObjectMeta: metav1.ObjectMeta{
            Name:      app.Name,
            Namespace: app.Namespace,
        },
    }
    _, err := ctrl.CreateOrUpdate(ctx, r.Client, deployment, func() error {
        deployment.Spec.Replicas = &app.Spec.Replicas
        deployment.Spec.Template.Spec.Containers[0].Image = app.Spec.Image
        return ctrl.SetControllerReference(&app, deployment, r.Scheme)
    })

    // Status yenilə
    app.Status.Phase = "Running"
    app.Status.ReadyReplicas = *deployment.Status.ReadyReplicas
    r.Status().Update(ctx, &app)

    return ctrl.Result{RequeueAfter: 30 * time.Second}, err
}
```

### 3. Controller Reconcile Loop

```
Watch CR/Resource changes
       ↓
Event queue (work queue)
       ↓
Reconcile(namespace/name)
       ↓
Read actual state
       ↓
Compare with desired state
       ↓
Take action (create/update/delete)
       ↓
Update status
       ↓
Requeue (xəta varsa)
```

## Multi-Tenancy Arxitekturası

### 1. Namespace-Based Tenancy

Ən sadə model — hər team/müştəri öz namespace-ı alır:

```yaml
# team-a-namespace.yaml
apiVersion: v1
kind: Namespace
metadata:
  name: team-a
  labels:
    team: team-a
    environment: production

---
# ResourceQuota — team-a-nın xərci limitlənir
apiVersion: v1
kind: ResourceQuota
metadata:
  name: team-a-quota
  namespace: team-a
spec:
  hard:
    requests.cpu: "20"
    requests.memory: "40Gi"
    limits.cpu: "40"
    limits.memory: "80Gi"
    pods: "100"
    services: "20"
    persistentvolumeclaims: "20"
    count/deployments.apps: "20"

---
# LimitRange — default limits
apiVersion: v1
kind: LimitRange
metadata:
  name: team-a-limits
  namespace: team-a
spec:
  limits:
    - default:
        cpu: "500m"
        memory: "512Mi"
      defaultRequest:
        cpu: "100m"
        memory: "128Mi"
      type: Container
    - max:
        cpu: "4"
        memory: "8Gi"
      type: Container

---
# Team-a-ya öz namespace-ı üçün tam icazə
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: team-a-admin
  namespace: team-a
subjects:
  - kind: Group
    name: team-a
    apiGroup: rbac.authorization.k8s.io
roleRef:
  kind: ClusterRole
  name: admin
  apiGroup: rbac.authorization.k8s.io
```

### 2. Hierarchical Namespaces (HNC)

```bash
# HNC quraşdır
kubectl apply -f https://github.com/kubernetes-sigs/hierarchical-namespaces/releases/latest

# Sub-namespace yarat
kubectl hns create staging --namespace team-a

# team-a → team-a-staging iyerarxiyası
# Policies (NetworkPolicy, RBAC) avtomatik miras alınır
```

### 3. Kapsül / vCluster (Virtual Clusters)

Hər tenant öz virtual K8s API server-ini alır:

```bash
# vCluster quraşdır
helm repo add loft-sh https://charts.loft.sh
helm install my-vcluster loft-sh/vcluster \
    --namespace team-a \
    --create-namespace \
    --set "syncer.extraArgs[0]=--tls-san=team-a.example.com"

# vCluster-ə qoşulma
vcluster connect my-vcluster -n team-a
```

## Advanced Scheduling

### 1. Node Affinity

```yaml
spec:
  affinity:
    nodeAffinity:
      # Tələb olunur (hard)
      requiredDuringSchedulingIgnoredDuringExecution:
        nodeSelectorTerms:
          - matchExpressions:
              - key: kubernetes.io/arch
                operator: In
                values: ["amd64"]
              - key: node-role
                operator: In
                values: ["app"]
      # Üstünlük verilir (soft)
      preferredDuringSchedulingIgnoredDuringExecution:
        - weight: 80
          preference:
            matchExpressions:
              - key: zone
                operator: In
                values: ["eu-west-1a"]
```

### 2. Pod Anti-Affinity (High Availability)

```yaml
spec:
  affinity:
    podAntiAffinity:
      # Eyni node-da iki laravel pod olmasın
      requiredDuringSchedulingIgnoredDuringExecution:
        - labelSelector:
            matchLabels:
              app: laravel
          topologyKey: kubernetes.io/hostname
      # Eyni zone-da çox olmasın (soft)
      preferredDuringSchedulingIgnoredDuringExecution:
        - weight: 50
          podAffinityTerm:
            labelSelector:
              matchLabels:
                app: laravel
            topologyKey: topology.kubernetes.io/zone
```

### 3. Taints ve Tolerations

```bash
# Node-u dedicated et (yalnız toleration-u olan pod-lar gəlsin)
kubectl taint nodes gpu-node dedicated=gpu:NoSchedule

# Öz node-larında işləyən sistem component-ləri üçün
kubectl taint nodes master node-role.kubernetes.io/master:NoSchedule
```

```yaml
spec:
  tolerations:
    # GPU node-unda çalışa bilmək üçün
    - key: "dedicated"
      operator: "Equal"
      value: "gpu"
      effect: "NoSchedule"
```

### 4. Priority Classes

```yaml
# PriorityClass — kritik iş yükləri üçün
apiVersion: scheduling.k8s.io/v1
kind: PriorityClass
metadata:
  name: high-priority
value: 1000000
globalDefault: false
description: "Laravel web — eviction-da son sıra"

---
apiVersion: scheduling.k8s.io/v1
kind: PriorityClass
metadata:
  name: low-priority
value: 100
description: "Batch job — eviction-da ilk sıra"
```

```yaml
spec:
  priorityClassName: high-priority
```

## Pod Security Standards (PSS)

K8s 1.25-dən PSP (deprecated) əvəzinə PSS:

```yaml
# Namespace label ilə enforce et
apiVersion: v1
kind: Namespace
metadata:
  name: production
  labels:
    pod-security.kubernetes.io/enforce: restricted
    pod-security.kubernetes.io/audit: restricted
    pod-security.kubernetes.io/warn: restricted
```

**Security Profile Levels:**

| Level | Məhdudiyyət |
|-------|-------------|
| `privileged` | Heç bir məhdudiyyət |
| `baseline` | Minimum — privileged container yox, hostNetwork yox |
| `restricted` | Root yox, readOnlyRootFilesystem, seccompProfile |

```yaml
# restricted-ə uyğun pod spec
spec:
  securityContext:
    runAsNonRoot: true
    runAsUser: 1000
    fsGroup: 1000
    seccompProfile:
      type: RuntimeDefault
  containers:
    - name: laravel
      securityContext:
        allowPrivilegeEscalation: false
        readOnlyRootFilesystem: true
        capabilities:
          drop: ["ALL"]
      volumeMounts:
        - name: tmp
          mountPath: /tmp
        - name: storage
          mountPath: /var/www/html/storage
  volumes:
    - name: tmp
      emptyDir: {}
    - name: storage
      emptyDir: {}
```

## Praktiki Nümunələr

### Multi-Team Laravel Platform

```yaml
# Platform operator-u hər team üçün namespace, quota, RBAC yaradır
apiVersion: v1
kind: Namespace
metadata:
  name: team-payments
  labels:
    team: payments
    tier: critical
    pod-security.kubernetes.io/enforce: restricted

---
apiVersion: v1
kind: ResourceQuota
metadata:
  name: quota
  namespace: team-payments
spec:
  hard:
    requests.cpu: "16"
    requests.memory: "32Gi"
    limits.memory: "64Gi"
    pods: "50"

---
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: deny-cross-team
  namespace: team-payments
spec:
  podSelector: {}
  ingress:
    - from:
        - namespaceSelector:
            matchLabels:
              kubernetes.io/metadata.name: team-payments
        - namespaceSelector:
            matchLabels:
              kubernetes.io/metadata.name: ingress-nginx
  policyTypes:
    - Ingress
```

### RBAC Audit Skripti

```bash
#!/bin/bash
# Klasterdəki bütün ClusterRoleBinding-ləri yoxla
echo "=== ClusterAdmin bindings ==="
kubectl get clusterrolebindings -o json | \
  jq '.items[] | select(.roleRef.name == "cluster-admin") |
      {name: .metadata.name, subjects: .subjects}'

echo -e "\n=== Service Accounts with Secret access ==="
kubectl get roles -A -o json | \
  jq '.items[] | select(
    .rules[]?.resources[]? == "secrets" and
    .rules[]?.verbs[]? == "*"
  ) | {namespace: .metadata.namespace, name: .metadata.name}'
```

## PHP/Laravel ilə İstifadə

### Laravel App üçün Minimum Privilege Setup

```yaml
# laravel tam secure setup
---
apiVersion: v1
kind: ServiceAccount
metadata:
  name: laravel
  namespace: production
automountServiceAccountToken: false

---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel
  namespace: production
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
      serviceAccountName: laravel
      securityContext:
        runAsNonRoot: true
        runAsUser: 1000
        fsGroup: 1000
        seccompProfile:
          type: RuntimeDefault
      containers:
        - name: php-fpm
          image: myregistry/laravel:1.0.0
          securityContext:
            allowPrivilegeEscalation: false
            readOnlyRootFilesystem: true
            capabilities:
              drop: ["ALL"]
          resources:
            requests:
              cpu: 250m
              memory: 256Mi
            limits:
              memory: 512Mi
          volumeMounts:
            - name: tmp
              mountPath: /tmp
            - name: laravel-storage
              mountPath: /var/www/html/storage
            - name: laravel-bootstrap
              mountPath: /var/www/html/bootstrap/cache
      volumes:
        - name: tmp
          emptyDir: {}
        - name: laravel-storage
          emptyDir: {}
        - name: laravel-bootstrap
          emptyDir: {}
```

## İntervyu Sualları

### S1: RBAC-da Role və ClusterRole fərqi nədir?
**C:** Role namespace-scopeddur — yalnız bir namespace-dakı resurslar üzərində icazə verir. ClusterRole cluster-widedur — bütün namespace-lərdə, həm də cluster-level resurslar üçün (PV, Node, Namespace) istifadə olunur. ClusterRole bir namespace-ə RoleBinding ilə bind edilə bilər — bu, eyni icazə şablonunu fərqli namespace-lərdə istifadə etmək üçün çox vacibdir.

### S2: NetworkPolicy olmadan default davranış necədir?
**C:** Default olaraq bütün pod-lar bir-biriləri ilə danışa bilər — namespace fərqi olmadan. Bu "flat network" modeli asanlıq üçündür. NetworkPolicy yaradıldıqda yalnız spec-ə uyğun trafikə icazə verilir, qalanı bloklanır. İlk addım default-deny yaratmaq, sonra selective allow əlavə etməkdir.

### S3: CRD niyə lazımdır?
**C:** CRD K8s API-ni domain-specific resurslara görə genişləndirir. Məsələn `LaravelApp` resursu yaratmaqla development team-i Deployment, Service, ConfigMap, HPA YAML-larını ayrı-ayrı idarə etmək əvəzinə yalnız bir YAML ilə işləyə bilər. Operator bu resursları watch edib lazımi K8s resurlarını avtomatik yaradır.

### S4: Operator Pattern nədir?
**C:** Operator = CRD + Controller. Controller reconcile loop ilə istənilən (desired) vəziyyəti real (actual) vəziyyətə çatdırır. Database Operator buna ən yaxşı nümunədir: PostgreSQL, MongoDB Operator-ları backup, failover, scaling-i avtomatik idarə edir. Human operator-un etdiyi işi proqramlı olaraq edir.

### S5: Multi-tenancy-də namespace-based ilə vCluster arasında fərq?
**C:** Namespace-based tenancy klasterin namespace-lərini izolyasiya edir — ResourceQuota, RBAC, NetworkPolicy ilə. Amma tenant admin hüquqlarını ala bilmir. vCluster hər tenant üçün virtual K8s API server yaradır — tenant öz klasterini sanki tam idarə edir (CRD, RBAC, hər şey). Qiymət: daha çox resource overhead.

### S6: Pod Security Standards Restricted profilinin tələbləri?
**C:** readOnlyRootFilesystem, runAsNonRoot: true, allowPrivilegeEscalation: false, capabilities drop ALL, seccompProfile RuntimeDefault. Bu parametrlər konteynerin host sisteminə erişimini maksimum azaldır.

### S7: Pod anti-affinity niyə HA üçün vacibdir?
**C:** Anti-affinity olmadan K8s bütün pod-ları eyni node-a yerləşdirə bilər (scheduler-in bin packing optimizasiyası). Bir node crash olarsa bütün pod-lar birlikdə düşür. `requiredDuringScheduling` ile anti-affinity tələb edilərsə, pod-lar fərqli node-lara dağılır — yaxud hərəsi fərqli zone-da.

## Best Practices

1. **Least Privilege RBAC** — hər ServiceAccount yalnız lazım olan resurslara icazə alsın
2. **automountServiceAccountToken: false** — default token-i söndür, explicit mount et
3. **Default Deny NetworkPolicy** — namespace yarandıqda dərhal tətbiq et
4. **ResourceQuota hər namespace-ə** — "noisy neighbor" qarşısı
5. **LimitRange default-ları** — resources göstərməyən pod-lara default tətbiq et
6. **PSS restricted mode** — production namespace-lər üçün
7. **readOnlyRootFilesystem** — writeable-ı `emptyDir` volume ilə explict et
8. **Pod anti-affinity** — replicated app-lar üçün mütləq
9. **PriorityClass** — kritik iş yükləri eviction-da sonuncu olsun
10. **CRD validation schema** — required field-lər, min/max, enum
11. **Operator yerinə Helm** — sadə use-case üçün Custom Controller yazmaq overkill-dir
12. **vCluster ilə dev environments** — hər developer öz mini cluster-i alsın
13. **Audit log** — kim, nə vaxt, hansı resursa dəydi
14. **OPA/Gatekeeper** — kompleks policy-lər üçün (cross-field validation, korporativ qaydalar)

## Əlaqəli Mövzular

- [kubernetes-basics.md](18-kubernetes-basics.md) — K8s arxitekturası əsasları
- [kubernetes-deployments.md](20-kubernetes-deployments.md) — Rolling update, HPA, probes
- [kubernetes-configmaps-secrets.md](22-kubernetes-configmaps-secrets.md) — ConfigMap, Secret, ESO
- [kubernetes-autoscaling.md](31-kubernetes-autoscaling.md) — HPA, VPA, KEDA
- [kubernetes-observability.md](33-kubernetes-observability.md) — Prometheus, Grafana, Loki
- [distroless-rootless-docker.md](28-distroless-rootless-docker.md) — Container security
- [docker-security.md](10-docker-security.md) — Docker-level security
