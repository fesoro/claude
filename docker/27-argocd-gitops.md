# ArgoCD və GitOps

## Nədir? (What is it?)

**GitOps** — infrastructure və application deployment-ı Git-dəki declarative manifests ilə idarə etmək metodologiyasıdır. Git "source of truth"-dur, cluster Git-dəki state ilə uyğunlaşdırılır.

**ArgoCD** — Kubernetes üçün ən populyar GitOps continuous delivery alətidir. Git repository-ni izləyir və cluster-i o state-ə çatdırır.

## Əsas Konseptlər

### 1. GitOps Prinsipləri

1. **Declarative** — sistem state-i declarative şəkildə təsvir olunur (YAML, Helm, Kustomize)
2. **Versioned** — hər state Git-də version-lanıb, immutable history
3. **Automated pull** — agent Git-dən çəkir, push əmrləri yox
4. **Continuous reconciliation** — agent daim uyğunlaşdırır

### 2. Push vs Pull Model

```
Push Model (Klassik CI/CD):
Developer → CI/CD Pipeline → [kubectl apply] → Cluster
                              (CI-nin cluster-ə yazmaq icazəsi var — TƏHLÜKƏLİ)

Pull Model (GitOps):
Developer → Git Commit → Git Repository
                             ↑
                   [ArgoCD daim çəkir]
                             ↓
                         Cluster
                   (ArgoCD cluster daxilində işləyir)
```

Pull model üstünlükləri:
- Credential-lar cluster-dən çıxmır
- Cluster-in state-i her zaman Git-lə uyğundur
- Audit trail tam (Git history)
- Rollback: `git revert`

### 3. ArgoCD Arxitekturası

```
┌─────────────────────────────────────────────┐
│            Kubernetes Cluster               │
│                                              │
│  ┌────────────────────────────────────────┐│
│  │           ArgoCD                        ││
│  │                                         ││
│  │  ┌────────┐  ┌────────┐  ┌──────────┐ ││
│  │  │  API   │  │ Repo   │  │Application│ ││
│  │  │ Server │  │ Server │  │Controller │ ││
│  │  └────────┘  └────────┘  └──────────┘ ││
│  │                                         ││
│  │  ┌──────────┐  ┌───────────┐           ││
│  │  │   Redis  │  │   Dex     │           ││
│  │  │(cache)   │  │(SSO/OIDC) │           ││
│  │  └──────────┘  └───────────┘           ││
│  └────────────────────────────────────────┘│
│           │                                  │
│           ↓ watches                          │
│  ┌────────────────────────────────────────┐│
│  │         Applications                    ││
│  │  (Deployments, Services, ...)           ││
│  └────────────────────────────────────────┘│
└─────────────────────────────────────────────┘
           ↑
           │ git clone/pull
           │
┌──────────┴─────────────┐
│   Git Repository        │
│   (manifests, Helm,     │
│    Kustomize)           │
└─────────────────────────┘
```

## Praktiki Nümunələr

### 1. ArgoCD Quraşdırma

```bash
# Namespace və install
kubectl create namespace argocd
kubectl apply -n argocd -f https://raw.githubusercontent.com/argoproj/argo-cd/stable/manifests/install.yaml

# CLI install
brew install argocd  # və ya Linux: curl -sSL -o /usr/local/bin/argocd https://github.com/argoproj/argo-cd/releases/latest/download/argocd-linux-amd64

# Ilk login (admin parolu)
kubectl -n argocd get secret argocd-initial-admin-secret -o jsonpath="{.data.password}" | base64 -d

# Port-forward
kubectl port-forward svc/argocd-server -n argocd 8080:443

# Login
argocd login localhost:8080
argocd account update-password
```

### 2. Application Yaratmaq

```yaml
# laravel-app.yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: laravel-production
  namespace: argocd
  finalizers:
    - resources-finalizer.argocd.argoproj.io
spec:
  project: default
  
  source:
    repoURL: https://github.com/myorg/laravel-k8s.git
    targetRevision: main
    path: environments/production
  
  destination:
    server: https://kubernetes.default.svc
    namespace: production
  
  syncPolicy:
    automated:
      prune: true      # Git-dən silinmiş resurslar cluster-dən də silinsin
      selfHeal: true   # Manual dəyişiklik geri qaytarılsın
    syncOptions:
      - CreateNamespace=true
      - PrunePropagationPolicy=foreground
      - PruneLast=true
    retry:
      limit: 5
      backoff:
        duration: 5s
        factor: 2
        maxDuration: 3m
```

```bash
kubectl apply -f laravel-app.yaml
argocd app sync laravel-production
argocd app get laravel-production
```

### 3. Helm Chart ilə Application

```yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: laravel-staging
spec:
  source:
    repoURL: https://charts.myorg.com
    chart: laravel
    targetRevision: 1.2.0
    helm:
      releaseName: laravel-staging
      valueFiles:
        - values-staging.yaml
      parameters:
        - name: image.tag
          value: "1.0.0-rc.5"
        - name: replicas
          value: "2"
  destination:
    server: https://kubernetes.default.svc
    namespace: staging
```

### 4. App of Apps Pattern

Tək bir "root" Application bütün digər Application-ları idarə edir:

```yaml
# root-app.yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: root
spec:
  source:
    repoURL: https://github.com/myorg/argocd-apps.git
    path: apps/
    targetRevision: main
  destination:
    server: https://kubernetes.default.svc
    namespace: argocd
```

```
argocd-apps/
├── apps/
│   ├── laravel-prod.yaml
│   ├── laravel-staging.yaml
│   ├── redis.yaml
│   ├── prometheus.yaml
│   └── cert-manager.yaml
```

Yeni tətbiq əlavə etmək: `apps/` folderində yeni YAML + git commit + push.

### 5. Sync Waves və Hooks

Resurs-ların deploy ardıcıllığını idarə etmək:

```yaml
# Wave -1: Namespace əvvəl yaradılsın
apiVersion: v1
kind: Namespace
metadata:
  name: production
  annotations:
    argocd.argoproj.io/sync-wave: "-1"

---
# Wave 0: DB migration pre-sync hook
apiVersion: batch/v1
kind: Job
metadata:
  name: migrate-db
  annotations:
    argocd.argoproj.io/hook: PreSync
    argocd.argoproj.io/hook-delete-policy: HookSucceeded
spec:
  template:
    spec:
      containers:
        - name: migrate
          image: myregistry/laravel:1.0.0
          command: ["php", "artisan", "migrate", "--force"]
      restartPolicy: Never

---
# Wave 1: Application deploy
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel
  annotations:
    argocd.argoproj.io/sync-wave: "1"
```

### 6. ApplicationSet (Multi-cluster/Multi-env)

Eyni template bir çox environment üçün:

```yaml
apiVersion: argoproj.io/v1alpha1
kind: ApplicationSet
metadata:
  name: laravel-multi-env
spec:
  generators:
    - list:
        elements:
          - env: dev
            cluster: https://dev.cluster.com
          - env: staging
            cluster: https://staging.cluster.com
          - env: production
            cluster: https://prod.cluster.com
  template:
    metadata:
      name: 'laravel-{{env}}'
    spec:
      project: default
      source:
        repoURL: https://github.com/myorg/laravel-k8s.git
        targetRevision: main
        path: 'environments/{{env}}'
      destination:
        server: '{{cluster}}'
        namespace: '{{env}}'
      syncPolicy:
        automated:
          prune: true
          selfHeal: true
```

## PHP/Laravel ilə İstifadə

### Laravel GitOps Repo Strukturu

```
laravel-k8s/
├── base/
│   ├── deployment.yaml
│   ├── service.yaml
│   ├── ingress.yaml
│   ├── configmap.yaml
│   ├── hpa.yaml
│   └── kustomization.yaml
├── environments/
│   ├── dev/
│   │   ├── kustomization.yaml
│   │   └── patches/
│   │       ├── replicas.yaml
│   │       └── image.yaml
│   ├── staging/
│   │   └── kustomization.yaml
│   └── production/
│       ├── kustomization.yaml
│       └── patches/
│           ├── replicas.yaml
│           └── resources.yaml
└── README.md
```

`base/kustomization.yaml`:
```yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
resources:
  - deployment.yaml
  - service.yaml
  - ingress.yaml
  - configmap.yaml
  - hpa.yaml

commonLabels:
  app: laravel
```

`environments/production/kustomization.yaml`:
```yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
namespace: production

resources:
  - ../../base

patches:
  - path: patches/replicas.yaml
  - path: patches/resources.yaml

images:
  - name: laravel
    newName: myregistry/laravel
    newTag: 1.2.3
```

### Image Updater ilə Avtomatik Deploy

ArgoCD Image Updater yeni image-ləri avtomatik çəkib Git-i yeniləyir:

```yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: laravel-production
  annotations:
    argocd-image-updater.argoproj.io/image-list: laravel=myregistry/laravel
    argocd-image-updater.argoproj.io/laravel.update-strategy: semver
    argocd-image-updater.argoproj.io/laravel.allow-tags: regexp:^v\d+\.\d+\.\d+$
    argocd-image-updater.argoproj.io/write-back-method: git
    argocd-image-updater.argoproj.io/git-branch: main
spec:
  # ...
```

Axın: CI image build edir → Registry-yə push edir → Image Updater çəkir → Git-ə push edir → ArgoCD deploy edir.

### Laravel Migration Deploy Strategiyası

```yaml
# PreSync hook — DB migration
apiVersion: batch/v1
kind: Job
metadata:
  name: laravel-migrate-{{.Values.image.tag}}
  annotations:
    argocd.argoproj.io/hook: PreSync
    argocd.argoproj.io/hook-delete-policy: BeforeHookCreation
spec:
  backoffLimit: 3
  template:
    spec:
      containers:
        - name: migrate
          image: myregistry/laravel:{{.Values.image.tag}}
          command: ["sh", "-c"]
          args:
            - |
              php artisan down --render='errors::503'
              php artisan migrate --force
              php artisan config:cache
              php artisan route:cache
              php artisan view:cache
              php artisan up
          envFrom:
            - secretRef:
                name: laravel-secrets
      restartPolicy: Never
```

### Rollback

```bash
# Son 10 sync-ı göstər
argocd app history laravel-production

# Specific revision-a qayıt
argocd app rollback laravel-production 42

# Və ya Git-də
git revert HEAD
git push
# ArgoCD avto detect edib rollback edir
```

## Interview Sualları

**1. GitOps nədir?**
Infrastructure və app deployment-ı Git-dəki declarative manifests ilə idarə etmə metodologiyası. Git = single source of truth, agent daim Git-dən çəkir və uyğunlaşdırır.

**2. ArgoCD-in push/pull modeli nə deməkdir?**
- Push: CI birbaşa cluster-ə apply edir (credential CI-də)
- Pull: ArgoCD cluster daxilində, Git-dən çəkir (credential cluster-də, daha təhlükəsiz)

**3. ArgoCD sync policy options?**
- `automated`: manual yox, auto apply
- `prune`: Git-dən silinən resurslar cluster-dən də silinsin
- `selfHeal`: manual dəyişiklik geri qaytarılsın
- `CreateNamespace`: namespace avto yaradılsın

**4. App of Apps pattern?**
Tək "root" Application altında bir çox Application-ı idarə etmək. Yeni app əlavə etmək: sadəcə Git-ə YAML əlavə edirik.

**5. Sync waves nə işə yarayır?**
Resurs-ların deploy ardıcıllığını idarə edir. Məs: -1 wave-də Namespace, 0-da CRD, 1-də Deployment. Negative waves pozitiv-dən əvvəl işləyir.

**6. Hook-lar nə vaxt istifadə olunur?**
`PreSync`, `Sync`, `PostSync`, `SyncFail`. DB migration (PreSync), smoke test (PostSync), cleanup (SyncFail).

**7. ApplicationSet nə edir?**
Template-based generator. Bir template-dən bir çox Application yaradır (generators: list, cluster, git, matrix). Multi-tenant və multi-cluster üçün.

**8. ArgoCD-ni Flux-dan nə fərqləndirir?**
- ArgoCD: Web UI, daha user-friendly, CRD-based
- Flux: Lightweight, hər component ayrı controller, daha Kubernetes-native

**9. Image Updater nə edir?**
Registry-dəki yeni image-ləri izləyir və avtomatik Git-dəki tag-i yeniləyir. CI image push edər, Image Updater Git-i yeniləyər, ArgoCD deploy edər.

**10. Security considerations?**
- RBAC: ArgoCD-in hansı namespace-ə access-i olduğu
- Git credentials: SSH key / PAT saxlanması
- SSO: Dex ilə OIDC integrasiyası
- Audit log: hər sync, user action log-lanır

## Best Practices

1. **App of Apps pattern** — bir-birinin içində təşkilat edin
2. **Environment-per-folder** və ya **environment-per-branch** seç — qarışdırma
3. **Auto-sync + pruning** production-da — amma approval workflow ilə
4. **Kustomize ya Helm** — vanilla YAML-dan qaç
5. **Sync waves** istifadə et — Namespace/CRD/App ardıcıllığı
6. **PreSync hook-da DB migration** — Laravel artisan migrate
7. **Image Updater** — GitOps loop-u CI-də qırma, avtomatlaşdır
8. **Notifications** konfiqurə et — sync fail slack/email
9. **RBAC** qur — developer prod-a push edə bilməsin
10. **Disaster Recovery** — ArgoCD özü də GitOps-da idarə olunsun (backup, restore mümkün olsun)
