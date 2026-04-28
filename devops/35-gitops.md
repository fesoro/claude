# GitOps (Lead)

## Nədir? (What is it?)

GitOps – infrastructure və application deployment-ləri Git repository-dən idarə edən operational framework-dir. Git single source of truth olur; declarative configuration (YAML/HCL) repo-da saxlanılır, operator (ArgoCD, Flux) cluster-i Git ilə sync edir. Termin 2017-də Weaveworks tərəfindən yaradılıb. Əsas ideya: istehsalat mühitinə birbaşa `kubectl apply` etmirik – bütün dəyişikliklər PR ilə gəlir, review olur, audit log Git-də qalır. Nəticədə rollback asanlaşır (git revert), uyğunluq (compliance) təmin olunur, drift detection avtomatik olur.

## Əsas Konseptlər (Key Concepts)

### GitOps-in 4 Prinsipi (OpenGitOps)

```
1. DECLARATIVE
   Sistem state declarative şəkildə təsvir olunur
   (imperative deyil, "necə" yox, "nə" yazılır)

2. VERSIONED & IMMUTABLE
   State Git-də saxlanır, tarixçə hər zaman əldə oluna bilər
   Hər dəyişiklik commit-dir

3. PULLED AUTOMATICALLY
   Software agent-lər approved state-i avtomatik tətbiq edir
   (push deyil, pull model)

4. CONTINUOUSLY RECONCILED
   Agent sistem state-ini davamlı müşahidə edir
   Drift baş verərsə, avtomatik düzəldir (və ya alert verir)
```

### Pull vs Push Model

```
PUSH MODEL (ənənəvi CI/CD):
   CI pipeline → kubectl apply → Cluster
   
   Problem:
   - CI-də Kubernetes credentials lazımdır (təhlükəsizlik riski)
   - Drift detection yoxdur
   - Cluster-ə kənardan çıxış lazımdır
   - Rollback mürəkkəbdir

PULL MODEL (GitOps):
   Developer → PR → Git repo
                        ↓
                  Agent (ArgoCD/Flux) cluster içində
                        ↓
                  Git-i polling edir, dəyişikliyi tətbiq edir
   
   Üstünlüklər:
   - Cluster credentials heç zaman CI-də olmur
   - Drift avtomatik düzəldilir
   - Firewall daha sərt ola bilər (inbound yox)
   - Multi-cluster asan idarə olunur
```

### ArgoCD vs Flux

```
ArgoCD (Intuit, CNCF graduated):
   - Web UI var (vizual, diagram göstərir)
   - Multi-cluster
   - ApplicationSet CRD (template-lər)
   - SSO integration
   - Daha "user-friendly"

Flux (Weaveworks, CNCF graduated):
   - CLI və CRD əsaslı (UI yoxdur, lakin Weave GitOps əlavə edir)
   - Kustomize-first
   - Helm controller, Notification controller
   - Image automation (yeni tag-i avtomatik commit edir)
   - Daha "modular"

Seçim:
   UI və vizual: ArgoCD
   GitOps-native, CLI-first: Flux
```

### Progressive Delivery

```
Progressive Delivery = canary + feature flags + observability
GitOps ilə birlikdə: dəyişiklik Git-də, rollout alətlə.

Argo Rollouts (ArgoCD ekosistemi):
   - Canary, blue-green deployment
   - Analysis template (Prometheus metric yoxla)
   - Avtomatik promote/abort

Flagger (Flux ekosistemi):
   - Service mesh ilə işləyir (Istio, Linkerd, App Mesh)
   - Canary, A/B testing, blue-green
   - Webhook-larla integration

Nümunə axın:
   v2 deploy → 10% traffic v2-yə → 5 dəqiqə gözlə
   → error rate yoxla → OK-dirsə 25% → 50% → 100%
   → Xəta olsa, avtomatik rollback
```

## Praktiki Nümunələr (Practical Examples)

### ArgoCD Application

```yaml
# application.yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: laravel-app
  namespace: argocd
spec:
  project: default
  source:
    repoURL: https://github.com/company/k8s-manifests
    targetRevision: main
    path: apps/laravel-app/production
  destination:
    server: https://kubernetes.default.svc
    namespace: production
  syncPolicy:
    automated:
      prune: true        # Git-dən silinən resursları cluster-dan da sil
      selfHeal: true     # Manual dəyişikliyi Git-ə uyğun geri qaytar
    syncOptions:
      - CreateNamespace=true
      - PrunePropagationPolicy=foreground
    retry:
      limit: 5
      backoff:
        duration: 5s
        factor: 2
        maxDuration: 3m
```

### Repo Strukturu (Monorepo)

```
k8s-manifests/
├── apps/
│   ├── laravel-app/
│   │   ├── base/
│   │   │   ├── deployment.yaml
│   │   │   ├── service.yaml
│   │   │   └── kustomization.yaml
│   │   ├── staging/
│   │   │   ├── kustomization.yaml   # base + staging-specific
│   │   │   └── configmap.yaml
│   │   └── production/
│   │       ├── kustomization.yaml
│   │       └── hpa.yaml
│   └── worker/
│       └── ...
├── infrastructure/
│   ├── ingress-nginx/
│   ├── cert-manager/
│   └── monitoring/
└── apps-of-apps/
    └── root-app.yaml     # ArgoCD app-of-apps pattern
```

### Flux Kustomization

```yaml
# flux-system/apps.yaml
apiVersion: kustomize.toolkit.fluxcd.io/v1
kind: Kustomization
metadata:
  name: laravel-app
  namespace: flux-system
spec:
  interval: 5m
  path: ./apps/laravel-app/production
  prune: true
  sourceRef:
    kind: GitRepository
    name: manifests-repo
  healthChecks:
    - apiVersion: apps/v1
      kind: Deployment
      name: laravel-app
      namespace: production
  timeout: 3m
```

### Argo Rollouts Canary

```yaml
apiVersion: argoproj.io/v1alpha1
kind: Rollout
metadata:
  name: laravel-app
spec:
  replicas: 10
  strategy:
    canary:
      steps:
        - setWeight: 10
        - pause: { duration: 5m }
        - setWeight: 25
        - pause: { duration: 5m }
        - analysis:
            templates:
              - templateName: success-rate
        - setWeight: 50
        - pause: { duration: 10m }
        - setWeight: 100
      canaryService: laravel-canary
      stableService: laravel-stable
      trafficRouting:
        istio:
          virtualService:
            name: laravel-vs
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
          image: registry.io/laravel-app:v2.0.0
---
apiVersion: argoproj.io/v1alpha1
kind: AnalysisTemplate
metadata:
  name: success-rate
spec:
  metrics:
    - name: success-rate
      interval: 1m
      successCondition: result[0] >= 0.99
      failureLimit: 3
      provider:
        prometheus:
          address: http://prometheus:9090
          query: |
            sum(rate(http_requests_total{status!~"5..",app="laravel"}[2m]))
            / sum(rate(http_requests_total{app="laravel"}[2m]))
```

### Environment Promotion PR Axını

```
1. Developer feature-branch-də kod yazır
2. CI image build edir: registry/app:feature-xyz-abc123

3. PR → main merge olur
   CI image build: registry/app:main-def456
   CI yazır: manifests-repo/apps/app/staging/kustomization.yaml
   image: main-def456

4. ArgoCD staging-ə deploy edir
   QA test edir

5. Promotion PR:
   manifests-repo/apps/app/production/kustomization.yaml
   image: main-def456   (staging-də testdən keçmiş)
   
6. Review → merge → ArgoCD prod-a deploy edir
```

## PHP/Laravel ilə İstifadə

### Laravel Deployment GitOps Axını

```yaml
# .github/workflows/build.yml
name: Build and Update Manifests

on:
  push:
    branches: [main]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Build Docker image
        run: |
          IMAGE_TAG=${GITHUB_SHA::8}
          docker build -t ghcr.io/company/laravel-app:${IMAGE_TAG} .
          docker push ghcr.io/company/laravel-app:${IMAGE_TAG}
          echo "IMAGE_TAG=${IMAGE_TAG}" >> $GITHUB_ENV
      
      - name: Update manifests repo
        run: |
          git clone https://${{ secrets.GH_PAT }}@github.com/company/k8s-manifests.git
          cd k8s-manifests/apps/laravel-app/staging
          
          # Kustomize edit image tag
          kustomize edit set image \
            ghcr.io/company/laravel-app=ghcr.io/company/laravel-app:${IMAGE_TAG}
          
          git config user.name "CI Bot"
          git config user.email "ci@company.com"
          git add .
          git commit -m "chore(staging): update laravel-app to ${IMAGE_TAG}"
          git push origin main
```

### Laravel Manifest Nümunəsi

```yaml
# apps/laravel-app/base/deployment.yaml
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
      initContainers:
        - name: migrate
          image: ghcr.io/company/laravel-app
          command: ["php", "artisan", "migrate", "--force"]
          envFrom:
            - secretRef:
                name: laravel-secrets
      containers:
        - name: app
          image: ghcr.io/company/laravel-app
          ports:
            - containerPort: 8080
          envFrom:
            - configMapRef:
                name: laravel-config
            - secretRef:
                name: laravel-secrets
          readinessProbe:
            httpGet:
              path: /health
              port: 8080
          livenessProbe:
            httpGet:
              path: /health
              port: 8080
            initialDelaySeconds: 30
---
# apps/laravel-app/staging/kustomization.yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
namespace: staging
resources:
  - ../base
images:
  - name: ghcr.io/company/laravel-app
    newTag: main-def456
configMapGenerator:
  - name: laravel-config
    literals:
      - APP_ENV=staging
      - APP_URL=https://staging.example.com
```

### Secrets üçün Sealed Secrets

```yaml
# GitOps-da plain secret saxlaya bilmərik; encrypt edirik
apiVersion: bitnami.com/v1alpha1
kind: SealedSecret
metadata:
  name: laravel-secrets
  namespace: production
spec:
  encryptedData:
    APP_KEY: AgBvK7x...encrypted...
    DB_PASSWORD: AgC4q9z...encrypted...
```

```bash
# Yaradılma:
echo -n 'base64password' | kubectl create secret generic laravel-secrets \
  --dry-run=client --from-file=DB_PASSWORD=/dev/stdin -o yaml \
  | kubeseal -o yaml > sealed-secret.yaml

# sealed-secret.yaml Git-ə commit oluna bilər (public repo-da belə)
```

## Interview Sualları (Q&A)

**S1: GitOps pull model niyə push model-dən daha təhlükəsizdir?**
C: Pull model-də cluster credentials CI/CD sistemində saxlanmır. CI yalnız Git-ə yazır. Cluster içindəki agent (ArgoCD/Flux) Git-dən oxuyur. Bu, inbound firewall rule tələb etmir, attack surface-i azaldır. Push model-də CI-nin kubeconfig-i kompromis olsa, attacker cluster-ə tam çıxış əldə edir.

**S2: ArgoCD-də "self-heal" nə edir?**
C: Self-heal aktiv olduqda, ArgoCD Git state-dən fərqli cluster state-i avtomatik Git-ə uyğun geri qaytarır. Məsələn, kimsə `kubectl edit deployment` edib replicas dəyişsə, ArgoCD Git-dəki qiymətə geri dəyişir. Bu drift-in qarşısını alır və Git-in single source of truth olmasını təmin edir.

**S3: Application code və manifests eyni repo-da olmalıdır, ya ayrı?**
C: Ən çox tövsiyə olunan pattern – **ayrı repo**. Səbəblər: (1) manifests repo-da hər image update yeni commit deməkdir; kod repo bu commit-lərlə zibilənmir. (2) Manifests repo-ya müxtəlif access (SRE komandası) ola bilər. (3) Kod test etmək və deploy etmək ayrı konsepsiyalardır. Kiçik layihələrdə monorepo da uyğundur.

**S4: Environment promotion GitOps-da necə olur?**
C: Tipik pattern – hər environment üçün ayrı directory (staging/, production/). Staging-də image-lər avtomatik yenilənir (CI tərəfindən). Production-a keçid PR ilə olur: staging-dəki image tag-ini production kustomization-una köçürən PR yaradılır. Review → merge → ArgoCD deploy edir. Başqa pattern – per-environment branch (staging, production), lakin bu daha az tövsiyə olunur.

**S5: Secrets GitOps-da necə idarə olunur?**
C: Plain secrets heç vaxt Git-ə commit olunmamalıdır. Variantlar: (1) **Sealed Secrets** – public key ilə encrypt, yalnız cluster-daki controller decrypt edir. (2) **SOPS** (Mozilla) – Age/GPG ilə encrypt, Flux native dəstək. (3) **External Secrets Operator** – Git-də reference saxlanır (secret name), faktiki dəyər Vault/AWS Secrets Manager-dan gəlir. Ən geniş yayılmış – External Secrets Operator.

**S6: Argo Rollouts ilə canary deployment-in avtomatik abort-u necə işləyir?**
C: AnalysisTemplate-də metric və successCondition tərif olunur (məs. `success_rate >= 0.99`). Rollout canary addımında pause edib analysis run edir. Prometheus-dan query edir, əgər successCondition nə qədər dəfə fail olsa (`failureLimit`), Rollout status="Degraded" olur və avtomatik stable version-a geri qaytarır. Beləliklə pis deploy real traffic-ə tam çatmır.

**S7: GitOps drift detection nə deməkdir?**
C: Cluster-dakı faktiki state Git-dəki declared state-dən fərqlənməsi. Drift səbəbləri: manual `kubectl edit`, başqa alətin dəyişikliyi, resursun əl ilə silinməsi. ArgoCD/Flux davamlı reconcile edir və drift-i göstərir (OutOfSync status). Self-heal aktivsə avtomatik düzəldilir; əks halda alert yaradılır, SRE baxır.

**S8: ApplicationSet ArgoCD-də nəyə görə lazımdır?**
C: ApplicationSet template-lər vasitəsilə çoxlu Application yaratmağa imkan verir. Məsələn, 20 microservice üçün eyni pattern varsa, hər biri üçün 20 YAML yazmırsan – bir ApplicationSet Git directory-ni scan edir, hər qovluqdan avtomatik Application yaradır. Generator variantları: List, Git, Cluster, Matrix. Multi-cluster deployment üçün də faydalıdır.

**S9: GitOps-da rollback necə olur?**
C: İki yol var: (1) **git revert** – problemli commit-i geri qaytarırsan, PR açırsan, merge olduqda ArgoCD avtomatik köhnə state-ə qayıdır. (2) **ArgoCD rollback CLI** – `argocd app rollback <app> <revision>` – lakin bu Git ilə sync-dən çıxarır, sonra Git-i düzəltmək lazımdır. Ən təmiz yol – git revert, çünki audit trail qalır.

**S10: Flagger vs Argo Rollouts fərqi nədir?**
C: **Flagger** service mesh-ə əsaslanır (Istio, Linkerd, App Mesh, NGINX, Gloo); traffic routing-i mesh edir. Flux ekosisteminə daha yaxındır. **Argo Rollouts** öz CRD-si ilə deployment-i əvəz edir (Deployment yox, Rollout resource), mesh lazım deyil (lakin dəstəkləyir). ArgoCD ilə birlikdə yaxşı işləyir. Funksionallıq oxşardır – canary, blue-green, analysis.

## Best Practices

1. **Ayrı manifests repo** istifadə et (kod və config ayrı).
2. **Kustomize və ya Helm** ilə environment-lərə görə dəyərləri dəyiş (hard-code etmə).
3. **Sealed Secrets və ya External Secrets Operator** ilə secrets-i düzgün idarə et.
4. **Branch protection** qoy – main-ə direkt push bağlı, yalnız PR ilə.
5. **Auto-sync + self-heal** production-da aktiv et (drift-in qarşısını al).
6. **Prune=true** istifadə et ki, Git-dən silinənlər cluster-dan da silinsin.
7. **App-of-apps pattern** ilə ArgoCD-də çoxlu Application-ı mərkəzi idarə et.
8. **Progressive delivery** canary + analysis ilə risk azalt.
9. **Notification-lar** qur (Slack, email) sync fail olduqda xəbər gəlsin.
10. **RBAC** ArgoCD-də düzgün konfiqurasiya et – komandalar yalnız öz namespace-lərinə çıxış olsun.
11. **Sync waves və hooks** ilə deployment ardıcıllığını idarə et (məs. əvvəlcə CRD, sonra tətbiq).
12. **Image automation** (Flux) və ya image updater (ArgoCD) ilə yeni image tag-i avtomatik Git-ə commit et.
13. **Disaster recovery** – ArgoCD-nin özünü də Git-dən idarə et (bootstrap skripti ilə qur).
14. **Read-only tokens** işlət Git repo üçün – write yalnız CI-dən olsun.
15. **Health checks** tərif et – deployment health-ini ArgoCD düzgün anlasın.

---

## Praktik Tapşırıqlar

1. Argo CD qurun: `kubectl create namespace argocd`, `kubectl apply -n argocd -f install.yaml`; Laravel Helm chart-ı Git repo-ya yerləşdirin; `Application` CRD yazın (`repoURL`, `path`, `targetRevision: HEAD`); `argocd app sync` ilə ilk sync edin; UI-da sağlamlığı yoxlayın
2. Automated sync konfigurasiya edin: `syncPolicy: automated: prune: true, selfHeal: true`; deployment YAML-da image tag-ı manual dəyişdirin, Argo CD-nin 3 dəqiqə içərisində geri qaytardığını görün; selfHeal-in "drift" yarandıqda trigger etdiyini anlayın
3. ApplicationSet ilə multi-environment qurun: `generators: [{list: {elements: [{env: dev}, {env: staging}, {env: prod}]}}]`; hər environment üçün ayrı namespace, ayrı values.yaml; `git push` edib üç mühitin eyni vaxtda sync-lənməsini izləyin
4. Argo Rollouts ilə canary deployment: `Rollout` resource yazın, `canary: steps: [{setWeight: 10}, {pause: {duration: 5m}}, {setWeight: 100}]`; `kubectl argo rollouts promote` ilə manual promote; error rate yüksəkdirsə `kubectl argo rollouts abort` edin
5. Git repo secret-lərini idarə edin: Argo CD-yə private repo giriş üçün SSH key konfigurasiya edin (`argocd repo add`); Bitnami Sealed Secrets quruyun — `kubeseal` ilə secret-i şifrələyin, Git-ə commit edin; cluster-da avtomatik decrypt olduğunu görün
6. Argo CD RBAC qurun: `dev` roluna yalnız staging namespace-a sync icazəsi verin, production-a yalnız `lead` rolu sync etsin; `argocd proj role create` ilə proje role-ları; SSO (GitHub OAuth) ilə Argo CD login konfigurasiya edin

## Əlaqəli Mövzular

- [CI/CD Deployment](39-cicd-deployment.md) — GitOps ilə CI/CD birliyi
- [Deployment Strategies](44-deployment-strategies.md) — Argo Rollouts, canary, progressive
- [Terraform Advanced](24-terraform-advanced.md) — Terraform GitOps workflow, Atlantis
- [Secrets Management](28-secrets-management.md) — Sealed Secrets, Vault Agent
- [DORA Metrics](45-dora-metrics.md) — GitOps-un deployment frequency-ə təsiri
