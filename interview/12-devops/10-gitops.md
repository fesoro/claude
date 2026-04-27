# GitOps (Senior ⭐⭐⭐)

## İcmal
GitOps — Git repository-ni həm application kodu, həm də infrastruktur konfiqurasiyasının single source of truth kimi istifadə edən operational modeli. Argocd, Flux kimi alətlər Git-dəki dəyişiklikləri avtomatik olaraq Kubernetes cluster-ə tətbiq edir. "Git commit = deploy" — bu GitOps-un əsas ideyasıdır. Senior developer kimi bu modeli başa düşmək modern CD strategiyasını anlamaq deməkdir.

## Niyə Vacibdir
Klassik CI/CD pipeline-da deploy bir script icra edir — "kubectl apply" ya da "helm upgrade" manual ya da CI agent tərəfindən çağırılır. GitOps bu modeli tərsinə çevirir: cluster özü Git-dən pull edir. Bu fərq: audit trail (hər dəyişiklik git history-də), rollback (git revert = deploy rollback), drift detection (cluster Git-dən fərqlənərsə sync edir). Production cluster-ə birbaşa erişim olmadan da deploy etmək mümkün olur.

## Əsas Anlayışlar

### GitOps Prinsipləri (OpenGitOps v1.0):

**1. Declarative:**
Sistemin arzu edilən vəziyyəti deklarativ olaraq təsvir edilir.
- Kubernetes YAML, Helm chart, Kustomize overlay
- "3 replika olsun" — "kubectl scale et" deyil

**2. Versioned and Immutable:**
Arzu edilən vəziyyət versiyalanmışdır, dəyişdirilə bilməz (immutable).
- Git history = tam audit trail
- Hər deploy hansı commit-ə uyğun soruşulabilir

**3. Pulled Automatically:**
Proqram agentləri arzu edilən vəziyyəti avtomatik tətbiq edir.
- ArgoCD / Flux Git-i izləyir, dəyişikliyi pull edir
- Cluster kənardan push almır (güvənlik)

**4. Continuously Reconciled:**
Software agentləri mövcud vəziyyəti arzu edilən vəziyyətə davamlı olaraq uyğunlaşdırır.
- Drift detected → avtomatik sync
- Manual kubectl apply → ArgoCD geri qaytarır

---

### Push vs Pull Model Fərqi:

**Traditional Push (CI/CD):**
```
Developer → git push → CI pipeline → kubectl apply → Cluster
                                           ↑
                                    CI agent cluster-ə erişir
```

**GitOps Pull:**
```
Developer → git push → Git repo
                           ↑
                      ArgoCD/Flux izləyir → Cluster
                                                ↑
                                       Cluster özü pull edir
```

**Pull model-in üstünlükleri:**
- CI agent-in cluster credentials-ı olmasına ehtiyac yoxdur
- Drift detection — cluster-in əl ilə dəyişdirilməsi aşkarlanır
- Audit trail — kim, nə, nə vaxt dəyişdirdi

---

### ArgoCD:

**Quraşdırma:**
```bash
kubectl create namespace argocd
kubectl apply -n argocd -f https://raw.githubusercontent.com/argoproj/argo-cd/stable/manifests/install.yaml

# ArgoCD CLI
argocd login argocd.example.com
```

**Application konfiqurasiyası:**
```yaml
# ArgoCD Application CRD
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: laravel-app
  namespace: argocd
spec:
  project: default

  source:
    repoURL: https://github.com/mycompany/k8s-configs
    targetRevision: main
    path: apps/laravel-app/overlays/production

  destination:
    server: https://kubernetes.default.svc
    namespace: production

  syncPolicy:
    automated:
      prune: true      # Git-dən silinsə cluster-dən də sil
      selfHeal: true   # Manual dəyişiklik → revert to Git
    syncOptions:
      - CreateNamespace=true
```

**Repository strukturu:**
```
k8s-configs/
├── apps/
│   └── laravel-app/
│       ├── base/
│       │   ├── deployment.yaml
│       │   ├── service.yaml
│       │   └── kustomization.yaml
│       └── overlays/
│           ├── staging/
│           │   ├── kustomization.yaml
│           │   └── patch-replicas.yaml
│           └── production/
│               ├── kustomization.yaml
│               └── patch-replicas.yaml
```

---

### Kustomize ilə Multi-Environment:

```yaml
# apps/laravel-app/base/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 2
  template:
    spec:
      containers:
        - name: app
          image: ghcr.io/mycompany/laravel-app:latest

# apps/laravel-app/overlays/production/kustomization.yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
resources:
  - ../../base
patches:
  - path: patch-replicas.yaml

# apps/laravel-app/overlays/production/patch-replicas.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 10  # Production: 10 replika
```

---

### Image Update Automation (Flux):

```yaml
# Flux ImageUpdateAutomation — yeni Docker image → avtomatik git commit
apiVersion: image.toolkit.fluxcd.io/v1beta2
kind: ImageUpdateAutomation
metadata:
  name: laravel-app-auto
spec:
  interval: 5m
  sourceRef:
    kind: GitRepository
    name: k8s-configs
  git:
    checkout:
      ref:
        branch: main
    commit:
      author:
        name: FluxBot
        email: flux@example.com
      messageTemplate: "chore: update laravel-app to {{range .Updated.Images}}{{.}}{{end}}"
    push:
      branch: main
  update:
    path: apps/laravel-app
    strategy: Setters
```

---

### Secrets GitOps-da:

Git-ə plain text secret qoyma! Bir neçə strategiya:

**Sealed Secrets:**
```bash
# kubeseal ilə secret encrypt et
kubectl create secret generic app-secret \
  --from-literal=DB_PASSWORD=supersecret \
  --dry-run=client -o yaml | kubeseal -o yaml > sealed-secret.yaml

# sealed-secret.yaml git-ə push edilə bilər — encrypted
# SealedSecrets controller decrypt edib real Secret yaradır
```

**External Secrets Operator:**
```yaml
# AWS Secrets Manager-dan pull
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: app-secrets
spec:
  refreshInterval: 1h
  secretStoreRef:
    name: aws-secretsmanager
    kind: ClusterSecretStore
  target:
    name: app-secrets
  data:
    - secretKey: DB_PASSWORD
      remoteRef:
        key: production/app/db-password
```

---

### GitOps Workflow (Developer Gözündən):

```
1. Feature development
   git checkout -b feature/new-api
   code, test, commit, push

2. Application CI pipeline (GitHub Actions)
   - Tests run
   - Docker image build + push
   - image tag: v1.2.3-abc1234

3. Manifest update (avtomatik ya da manual)
   - Image tag: latest → v1.2.3-abc1234
   - apps/laravel-app/overlays/production/ dəyişdirilir
   - git commit + push to k8s-configs repo

4. ArgoCD dəyişikliyi aşkarlar
   - Git → Cluster diff
   - Sync policy ilə avtomatik apply

5. Rollback lazımdırsa:
   git revert <commit>
   git push
   ArgoCD sync edir — əvvəlki versiya bərpa olunur
```

---

### GitOps Anti-Patterns:

- `kubectl apply` birbaşa production-da (Git-dən kənar)
- Secrets-i plain text Git-ə push etmək
- Single Git repo — application code + manifests (separation of concerns pozulur)
- ArgoCD-nin `selfHeal: false` olması — drift aradan qaldırılmır

---

### GitOps vs Traditional CI/CD:

| | Traditional CI/CD | GitOps |
|--|-------------------|--------|
| Deploy trigger | CI pipeline push | Git commit (pull) |
| Cluster erişim | CI agent-ə lazım | Lazım deyil |
| Drift detection | Yox | Var (reconciliation) |
| Rollback | Script/manual | git revert |
| Audit trail | CI logs | Git history |
| Multi-cluster | Kompleks | ArgoCD ApplicationSet |

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"GitOps nədir?" sualına "Git-dən deploy edirik" demə. Pull model, drift detection, single source of truth, audit trail — bunları izah et. ArgoCD ya da Flux-u məncə bilmək istifadə etmişsiniz kimi verir. Secrets management (Sealed Secrets, External Secrets) — bu əla cavab detalıdır.

**Follow-up suallar:**
- "Push CI/CD ilə GitOps arasındakı fərq nədir?"
- "GitOps-da rollback necə edilir?"
- "Secrets Git-də necə saxlanır?"

**Ümumi səhvlər:**
- "GitOps = Git-dən deploy" — çox sadə tərif
- ArgoCD `selfHeal` olmadan GitOps əksik işləyir
- Secrets plain text Git-ə push etmək
- Application kodu ilə manifest-ləri eyni repoda saxlamaq (separation of concerns)

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"ArgoCD istifadə edirik" vs "Pull model-in niyə üstün olduğunu, drift detection-ı, Sealed Secrets-i, multi-environment kustomize strategiyasını izah edə bilmək."

## Nümunələr

### Tipik Interview Sualı
"GitOps nədir? Ənənəvi CI/CD-dən nə fərqlənir?"

### Güclü Cavab
"GitOps-da Git single source of truth-dur — həm application kodu, həm infra konfiqurasiyası üçün. Ənənəvi CI/CD-da CI agent cluster-ə 'push' edir — kubectl apply. GitOps-da isə ArgoCD kimi operator özü Git-dən 'pull' edir. Bu fərqin üç böyük faydası var: birincisi, CI agent-in cluster credentials-ı olmasına ehtiyac yoxdur — güvənlik artır. İkincisi, drift detection — kimsə kubectl ilə manual dəyişiklik etsə ArgoCD geri qaytarır. Üçüncüsü, rollback sadədir — git revert + push = əvvəlki version. Bizdə Sealed Secrets istifadə edirik — encrypted secret-lər Git-ə push edilir, cluster-da decrypt olunur."

## Praktik Tapşırıqlar
- Lokal Kind cluster qurun, ArgoCD install edin
- Kustomize ilə staging/production overlay yarat
- ArgoCD Application CRD ilə deployment konfigurasiya et
- Sealed Secrets install edib bir secret encrypt et

## Əlaqəli Mövzular
- [01-cicd-pipeline-design.md](01-cicd-pipeline-design.md) — CI pipeline image yaradır, GitOps deploy edir
- [02-container-orchestration.md](02-container-orchestration.md) — GitOps Kubernetes üzərində işləyir
- [03-infrastructure-as-code.md](03-infrastructure-as-code.md) — IaC + GitOps = infra də Git-dən idarə
