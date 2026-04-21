# Kubernetes RBAC (Role-Based Access Control)

## Nədir? (What is it?)

**RBAC (Role-Based Access Control)** — Kubernetes API-də kimin hansı resurslar üzərində hansı əməliyyatı edə biləcəyini müəyyən edən authorization mexanizmidir. K8s 1.6-dan sonra default olaraq aktivdir.

RBAC dörd əsas obyektdən ibarətdir:
- **Role / ClusterRole** — icazələr toplusu (nə etmək olar)
- **RoleBinding / ClusterRoleBinding** — subject-ə (user, group, ServiceAccount) role-u əlavə edir
- **ServiceAccount** — pod-un cluster-lə danışmaq üçün identity-si
- **Subjects** — User, Group, ServiceAccount (kim?)

## Əsas Konseptlər

### 1. Role vs ClusterRole

| Xüsusiyyət | Role | ClusterRole |
|------------|------|-------------|
| Scope | Bir namespace | Bütün cluster |
| Namespaced resource? | Hə | Hə |
| Cluster-scoped (nodes, PV)? | Yox | Hə |
| Non-resource URL (`/healthz`)? | Yox | Hə |
| Aggregation? | Yox | Hə |

```
┌──────────────────────────────────────────────┐
│  Cluster                                      │
│  ┌────────────────────────────────────────┐  │
│  │  ClusterRole: view-nodes               │  │
│  │  verbs: [get, list]                    │  │
│  │  resources: [nodes]                    │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌─ Namespace: dev ───────────────────────┐  │
│  │  Role: pod-reader                      │  │
│  │  verbs: [get, list, watch]             │  │
│  │  resources: [pods, pods/log]           │  │
│  └────────────────────────────────────────┘  │
│                                               │
│  ┌─ Namespace: prod ──────────────────────┐  │
│  │  Role: deployer                        │  │
│  │  verbs: [create, update, patch]        │  │
│  │  resources: [deployments]              │  │
│  └────────────────────────────────────────┘  │
└──────────────────────────────────────────────┘
```

### 2. Verbs (Əməliyyatlar)

| Verb | İzah |
|------|------|
| `get` | Tək obyekt almaq |
| `list` | Bütün obyektləri siyahıla |
| `watch` | Dəyişiklikləri izlə (streaming) |
| `create` | Yarat |
| `update` | Tam yenilə |
| `patch` | Qismən yenilə |
| `delete` | Sil |
| `deletecollection` | Toplu sil |
| `*` | Hamısı (təhlükəli!) |

Xüsusi verbs:
- `bind` / `escalate` — Role-ları bağlamaq/artırmaq
- `impersonate` — Başqasının adından əməl
- `use` — PodSecurityPolicy, SCC üçün

### 3. Resources və Sub-resources

```yaml
resources:
  - pods              # əsas resurs
  - pods/log          # sub-resource (log oxu)
  - pods/exec         # sub-resource (container-a gir)
  - pods/portforward  # sub-resource (port-forward)
  - deployments/scale # scale sub-resource
```

## Praktiki Nümunələr

### 1. ServiceAccount Yaratmaq

```yaml
# serviceaccount.yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: laravel-app
  namespace: production
  annotations:
    # AWS EKS IRSA (IAM Roles for Service Accounts)
    eks.amazonaws.com/role-arn: arn:aws:iam::123456789012:role/LaravelS3Access
automountServiceAccountToken: true  # default
```

```bash
kubectl apply -f serviceaccount.yaml

# Pod-a təyin et
kubectl get sa -n production
# NAME           SECRETS   AGE
# default        0         5d
# laravel-app    0         1m

# Token al (K8s 1.24+ manual token yaradılmalıdır)
kubectl create token laravel-app -n production --duration=1h
```

### 2. Role Yaratmaq

```yaml
# role-pod-reader.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: pod-reader
  namespace: production
rules:
  - apiGroups: [""]              # core API group
    resources: ["pods", "pods/log", "pods/status"]
    verbs: ["get", "list", "watch"]
  - apiGroups: [""]
    resources: ["configmaps"]
    verbs: ["get", "list"]
  - apiGroups: ["apps"]
    resources: ["deployments"]
    verbs: ["get", "list", "watch"]
```

### 3. RoleBinding

```yaml
# rolebinding.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: laravel-pod-reader
  namespace: production
subjects:
  - kind: ServiceAccount
    name: laravel-app
    namespace: production
  - kind: User
    name: alice@example.com
    apiGroup: rbac.authorization.k8s.io
  - kind: Group
    name: backend-team
    apiGroup: rbac.authorization.k8s.io
roleRef:
  kind: Role
  name: pod-reader
  apiGroup: rbac.authorization.k8s.io
```

### 4. ClusterRole və ClusterRoleBinding

```yaml
# clusterrole-node-viewer.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: node-viewer
rules:
  - apiGroups: [""]
    resources: ["nodes", "nodes/status", "nodes/metrics"]
    verbs: ["get", "list", "watch"]
  - nonResourceURLs: ["/metrics", "/healthz"]
    verbs: ["get"]
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRoleBinding
metadata:
  name: monitoring-nodes
subjects:
  - kind: ServiceAccount
    name: prometheus
    namespace: monitoring
roleRef:
  kind: ClusterRole
  name: node-viewer
  apiGroup: rbac.authorization.k8s.io
```

### 5. Aggregated ClusterRole

Bir neçə ClusterRole-u birləşdirmək üçün label selector istifadə olunur:

```yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: monitoring
aggregationRule:
  clusterRoleSelectors:
    - matchLabels:
        rbac.example.com/aggregate-to-monitoring: "true"
rules: []  # controller avtomatik doldurur
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: monitoring-pods
  labels:
    rbac.example.com/aggregate-to-monitoring: "true"
rules:
  - apiGroups: [""]
    resources: ["pods", "services"]
    verbs: ["get", "list", "watch"]
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: monitoring-nodes
  labels:
    rbac.example.com/aggregate-to-monitoring: "true"
rules:
  - apiGroups: [""]
    resources: ["nodes"]
    verbs: ["get", "list", "watch"]
```

Default aggregated role-lar: `admin`, `edit`, `view` — custom CRD-lər öz label ilə onlara rules əlavə edə bilir.

### 6. kubectl auth can-i

İcazə yoxlamaq üçün ən sürətli alət:

```bash
# Mənim icazələrim
kubectl auth can-i create pods -n production
# yes

kubectl auth can-i delete nodes
# no

kubectl auth can-i "*" "*" --all-namespaces
# no (cluster-admin olmasan)

# Başqa user/SA yoxla
kubectl auth can-i list pods -n production \
    --as=system:serviceaccount:production:laravel-app
# yes

kubectl auth can-i update deployments \
    --as=alice@example.com \
    --as-group=backend-team

# Bütün icazələri göstər
kubectl auth can-i --list -n production
# Resources                   Verbs
# pods                        [get list watch]
# configmaps                  [get list]
# deployments.apps            [get list watch]
```

### 7. Least Privilege (Ən Az İcazə)

```yaml
# PIS — çox geniş
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: bad-role
rules:
  - apiGroups: ["*"]
    resources: ["*"]
    verbs: ["*"]

# YAXSI — dar scope
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: laravel-migrator
  namespace: production
rules:
  - apiGroups: ["batch"]
    resources: ["jobs"]
    verbs: ["create", "get", "list", "watch", "delete"]
    resourceNames: []  # və ya spesifik adlar
  - apiGroups: [""]
    resources: ["pods", "pods/log"]
    verbs: ["get", "list", "watch"]
  - apiGroups: [""]
    resources: ["configmaps"]
    resourceNames: ["migration-config"]  # yalnız bu CM
    verbs: ["get"]
```

### 8. Impersonation

Admin başqa user-in adından əməl edə bilər (debug üçün faydalı):

```bash
kubectl get pods --as=alice@example.com
kubectl get pods --as=system:serviceaccount:production:laravel-app
kubectl get pods --as=alice --as-group=backend --as-group=dev

# Impersonation üçün icazə lazımdır
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: impersonator
rules:
  - apiGroups: [""]
    resources: ["users", "groups", "serviceaccounts"]
    verbs: ["impersonate"]
```

### 9. Audit Policy

API server-də kimin nə etdiyini qeyd etmək:

```yaml
# audit-policy.yaml
apiVersion: audit.k8s.io/v1
kind: Policy
omitStages:
  - "RequestReceived"
rules:
  # Secret-ləri metadata səviyyəsində
  - level: Metadata
    resources:
      - group: ""
        resources: ["secrets", "configmaps"]

  # Pod create/delete — tam request+response
  - level: RequestResponse
    resources:
      - group: ""
        resources: ["pods"]
    verbs: ["create", "delete", "deletecollection"]

  # kube-system-də az log
  - level: None
    namespaces: ["kube-system"]
    resources:
      - group: ""
        resources: ["events"]

  # Default — metadata
  - level: Metadata
```

kube-apiserver-də aktivləşdir:

```yaml
# /etc/kubernetes/manifests/kube-apiserver.yaml
spec:
  containers:
    - command:
        - kube-apiserver
        - --audit-policy-file=/etc/kubernetes/audit-policy.yaml
        - --audit-log-path=/var/log/kubernetes/audit.log
        - --audit-log-maxage=30
        - --audit-log-maxbackup=10
        - --audit-log-maxsize=100
```

### 10. OIDC Integration

Şirkət SSO (Google, Okta, Keycloak) ilə K8s auth:

```yaml
# kube-apiserver flags
- --oidc-issuer-url=https://accounts.google.com
- --oidc-client-id=kubernetes
- --oidc-username-claim=email
- --oidc-groups-claim=groups
- --oidc-username-prefix=oidc:
```

Sonra user `kubectl` ilə OIDC token istifadə edir (kubelogin plugin populyardır):

```bash
kubectl oidc-login setup \
    --oidc-issuer-url=https://accounts.google.com \
    --oidc-client-id=kubernetes
```

RoleBinding:

```yaml
subjects:
  - kind: User
    name: "oidc:alice@example.com"
    apiGroup: rbac.authorization.k8s.io
  - kind: Group
    name: "oidc:backend-team"
    apiGroup: rbac.authorization.k8s.io
```

## PHP/Laravel ilə İstifadə

### Laravel Pod üçün ServiceAccount

Laravel pod-u S3-ə qoşulmaq üçün AWS IRSA ilə ServiceAccount istifadə edir:

```yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: laravel-s3
  namespace: production
  annotations:
    eks.amazonaws.com/role-arn: arn:aws:iam::123456789012:role/LaravelS3
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel
spec:
  template:
    spec:
      serviceAccountName: laravel-s3
      containers:
        - name: laravel
          image: myregistry/laravel:1.0.0
          env:
            - name: AWS_REGION
              value: eu-central-1
            # AWS SDK avto istifadə edir: Web Identity Token
            # IAM credentials lazım deyil
```

Laravel kod:

```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'region' => env('AWS_REGION'),
    'bucket' => 'laravel-uploads',
    // Credentials YOX — IRSA avto ilə gəlir
],

// İstifadə
Storage::disk('s3')->put('file.pdf', $contents);
```

### Laravel Migration Job üçün RBAC

Laravel migration pod-u yalnız lazım olan icazələrə sahib olmalıdır:

```yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: laravel-migrator
  namespace: production
---
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: laravel-migrator
  namespace: production
rules:
  # Öz migration job-unu idarə etsin
  - apiGroups: ["batch"]
    resources: ["jobs"]
    verbs: ["get", "list", "watch"]
  # Secret-ləri yalnız oxu
  - apiGroups: [""]
    resources: ["secrets"]
    resourceNames: ["laravel-db-credentials"]
    verbs: ["get"]
---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: laravel-migrator
  namespace: production
subjects:
  - kind: ServiceAccount
    name: laravel-migrator
    namespace: production
roleRef:
  kind: Role
  name: laravel-migrator
  apiGroup: rbac.authorization.k8s.io
```

### Developer-lərə Namespace Access

Backend team yalnız `dev` və `staging` namespace-də işləsin:

```yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: backend-team-dev
  namespace: dev
subjects:
  - kind: Group
    name: "oidc:backend-team"
    apiGroup: rbac.authorization.k8s.io
roleRef:
  kind: ClusterRole
  name: edit  # built-in — create/update/delete resources
  apiGroup: rbac.authorization.k8s.io
---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: backend-team-staging
  namespace: staging
subjects:
  - kind: Group
    name: "oidc:backend-team"
    apiGroup: rbac.authorization.k8s.io
roleRef:
  kind: ClusterRole
  name: edit
  apiGroup: rbac.authorization.k8s.io
---
# Production-da yalnız view
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: backend-team-prod-view
  namespace: production
subjects:
  - kind: Group
    name: "oidc:backend-team"
    apiGroup: rbac.authorization.k8s.io
roleRef:
  kind: ClusterRole
  name: view
  apiGroup: rbac.authorization.k8s.io
```

## Interview Sualları

**1. Role və ClusterRole fərqi?**
Role — bir namespace-ə bağlı, yalnız namespaced resursları idarə edir. ClusterRole — cluster-wide, həm namespaced həm də cluster-scoped (nodes, PV) resursları idarə edir, non-resource URL-lər və aggregation dəstəkləyir.

**2. ServiceAccount niyə lazımdır?**
Pod-un K8s API-yə (və cloud provider-ə IRSA ilə) necə identifikasiya olunacağını təyin edir. Default SA minimum icazəyə sahibdir. Hər tətbiq üçün dedicated SA yaratmaq best practice-dir.

**3. Aggregated ClusterRole nədir?**
`aggregationRule` ilə label selector istifadə edərək bir neçə ClusterRole-u bir "meta" role-a birləşdirmək mexanizmi. Default `admin/edit/view` bunun üstündə qurulub — CRD install olduqda öz rules-ını label ilə əlavə edə bilir.

**4. `kubectl auth can-i` necə işləyir?**
API server-in `SubjectAccessReview` API-nə sorğu göndərir. Real əməliyyat etmədən yalnız authorization yoxlayır. `--as` flag-i ilə başqasının adından test etmək mümkündür.

**5. Least privilege principle necə tətbiq olunur?**
1. Hər tətbiq/service üçün ayrı SA yarat
2. `*` wildcard istifadə etmə
3. `resourceNames` ilə spesifik obyektləri hədəflə
4. ClusterRole yerinə mümkün olduqda Role istifadə et
5. `kubectl auth can-i --list` ilə yoxla

**6. Impersonation nə vaxt istifadə olunur?**
Admin başqa user-in icazələrini simulyasiya etmək istəyərsə. Debug və troubleshooting üçün. `system:masters` group-un üzvləri heç bir restriction olmadan impersonate edə bilər.

**7. Audit Policy-nin səviyyələri?**
`None` — log yazma, `Metadata` — yalnız request header-ləri, `Request` — request body, `RequestResponse` — həm body həm cavab. Secret-lər üçün Metadata, production dəyişikliklər üçün RequestResponse istifadə edilir.

**8. OIDC K8s-ə necə inteqrasiya olunur?**
kube-apiserver flags (`--oidc-issuer-url`, `--oidc-client-id`, `--oidc-username-claim`) vasitəsilə. User kubelogin ilə token alır, kubectl `Authorization: Bearer <token>` ilə göndərir. Bu sayədə LDAP/SSO ilə centralized auth olur.

**9. Pod Service Account token K8s 1.24-dən sonra necə dəyişdi?**
Əvvəl: SA yaradıldıqda avtomatik Secret (non-expiring token) yaranırdı. Yeni: TokenRequest API ilə time-bound (default 1h) projected token pod-a mount olunur. Manual token üçün `kubectl create token sa-name` işlədilir.

**10. ClusterRoleBinding ilə bir ClusterRole-u namespace-ə bağlaya bilərikmi?**
Hə — RoleBinding istifadə edərək ClusterRole-u bir namespace-ə məhdudlaşdırmaq mümkündür. Məsələn built-in `view` ClusterRole-u yalnız `dev` namespace-də tətbiq etmək.

## Best Practices

1. **Default SA-ya rely etmə** — hər tətbiq üçün dedicated SA
2. **`automountServiceAccountToken: false`** — SA lazım deyilsə
3. **`resourceNames`** ilə spesifik obyektləri hədəflə
4. **Built-in role-lardan istifadə et** — `view`, `edit`, `admin` (amma cluster-admin YOX)
5. **Aggregated role** — extensibility üçün
6. **Audit logging** — production-da mütləq
7. **OIDC/SSO** inteqrasiyası — individual user-lər üçün
8. **`kubectl auth can-i --list`** — periodic review
9. **rbac-lookup / rbac-tool** — GUI üçün (FairwindsOps)
10. **OPA/Kyverno** — RBAC-ın üstündə policy-as-code
11. **Secret-ləri `resourceNames`-də hədəflə** — hamısını açma
12. **`bind`/`escalate` verb-lərini diqqətli ver** — privilege escalation riski
