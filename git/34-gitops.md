# GitOps (Lead)

## İcmal

**GitOps** — Git repozitoriyasını infrastruktur və tətbiq deployment-inin **yeganə həqiqət mənbəyi** (single source of truth) kimi istifadə edən bir operational modeldir. İstənilən dəyişiklik Git vasitəsilə edilir: kod, konfiqurasiya, infrastruktur — hamısı PR, review və merge prosesindən keçir.

```
Ənənəvi deployment:         GitOps:
Developer → SSH → Server    Developer → Git PR → Merge → Auto Deploy
Manual commands             Declarative config in repo
"Nə oldu?" bilinmir         Full audit trail (git log)
Rollback? Mürəkkəb          Rollback = git revert + push
```

**İki əsas model:**
1. **Push-based GitOps** — CI/CD sistemi (GitHub Actions, GitLab CI) dəyişikliyi aşkar edib deploy edir
2. **Pull-based GitOps** — Cluster-daxili agent (ArgoCD, Flux) Git-i müntəzəm yoxlayıb öz-özünə sinxronlaşır

---

## Niyə Vacibdir

- **Audit trail**: hər deployment dəyişikliyi `git log`-da görünür — kim nə vaxt nəyi deploy etdi
- **Rollback**: `git revert` + push = əvvəlki vəziyyətə qayıdış
- **PR-based workflow**: infrastruktur dəyişiklikləri kod review-dan keçir
- **Disaster recovery**: repo-dan cluster-ı sıfırdan qurmaq mümkündür
- **Drift detection**: cluster real vəziyyəti repo ilə sinxronlaşmırsa alarm verir

---

## Əsas Anlayışlar

### Deklarativ konfiqurasiya

```
Imperativ (ənənəvi):          Deklarativ (GitOps):
"Run this command"             "This is the desired state"
ssh server "php artisan ..."   Kubernetes Deployment YAML
ansible playbook               Helm chart
Mümkün side-effect             Git-də saxlanılır, idempotent
```

### Git as Single Source of Truth

```
Git Repository
├── app/                   ← Tətbiq kodu
├── infrastructure/
│   ├── k8s/               ← Kubernetes manifests
│   ├── helm/              ← Helm charts
│   └── terraform/         ← Infrastructure as Code
└── config/
    ├── staging.yaml       ← Staging konfiqurasiyası
    └── production.yaml    ← Production konfiqurasiyası
```

### Environment branches vs overlay pattern

**Environment branches:**
```
main → production
staging → staging environment
develop → development environment
```

**Overlay pattern (Kustomize):**
```
base/
  deployment.yaml    ← Ortaq konfigurasiya
overlays/
  staging/
    kustomization.yaml  ← Staging üzərindən dəyişikliklər
  production/
    kustomization.yaml  ← Production üzərindən dəyişikliklər
```

---

## Nümunələr

### Nümunə 1: Push-based GitOps — GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches:
      - main        # → production
      - staging     # → staging

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: ${{ github.ref_name }}
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Deploy to server
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.DEPLOY_KEY }}
          script: |
            cd /var/www/laravel-app
            git pull origin ${{ github.ref_name }}
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            sudo systemctl reload php8.3-fpm
```

### Nümunə 2: Environment-based deployment branching

```
Git branch strategiyası:

main ──────────────────────────────── production
  │
  ├── staging ───────────────────────  staging.example.com
  │
  └── feature/new-checkout ──────────  PR → staging → main
```

```yaml
# .github/workflows/environment-deploy.yml
name: Environment Deploy

on:
  push:
    branches: [main, staging]
  pull_request:
    branches: [main]

jobs:
  deploy-staging:
    if: github.ref == 'refs/heads/staging'
    runs-on: ubuntu-latest
    environment:
      name: staging
      url: https://staging.example.com
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to staging
        env:
          APP_ENV: staging
          DB_HOST: ${{ secrets.STAGING_DB_HOST }}
        run: ./deploy.sh staging

  deploy-production:
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment:
      name: production
      url: https://example.com
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to production
        env:
          APP_ENV: production
          DB_HOST: ${{ secrets.PROD_DB_HOST }}
        run: ./deploy.sh production
```

### Nümunə 3: Laravel deployment script (GitOps-uyğun)

```bash
#!/bin/bash
# deploy.sh — idempotent deployment script
set -euo pipefail

ENVIRONMENT=${1:-staging}
APP_DIR="/var/www/laravel-app"
RELEASE_DIR="$APP_DIR/releases/$(date +%Y%m%d%H%M%S)"
SHARED_DIR="$APP_DIR/shared"
CURRENT_DIR="$APP_DIR/current"

echo "Deploying to $ENVIRONMENT..."

# 1. Yeni release qovluğu
mkdir -p "$RELEASE_DIR"
git clone --depth=1 \
  --branch "$ENVIRONMENT" \
  git@github.com:company/laravel-app.git \
  "$RELEASE_DIR"

# 2. Paylaşılan faylları link et (.env, storage)
ln -sf "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
ln -sf "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

# 3. Dependencies
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --quiet

# 4. Migrations (idempotent)
php artisan migrate --force

# 5. Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Atomic symlink (zero-downtime swap)
ln -sfn "$RELEASE_DIR" "${CURRENT_DIR}.tmp"
mv -Tf "${CURRENT_DIR}.tmp" "$CURRENT_DIR"

# 7. PHP-FPM reload (graceful)
sudo systemctl reload php8.3-fpm

# 8. Köhnə release-ləri sil (son 5 saxla)
ls -dt "$APP_DIR/releases"/* | tail -n +6 | xargs -r rm -rf

echo "Deployed: $(git -C $RELEASE_DIR rev-parse --short HEAD)"
```

### Nümunə 4: Rollback — git revert ilə

```bash
# GitOps-da rollback = git revert + push

# 1. Problemli commit-i tap
git log --oneline main | head -10
# a1b2c3d feat: new checkout flow ← problem burada

# 2. Revert et (yeni commit yaranır, tarix pozulmur)
git revert a1b2c3d --no-edit
git push origin main

# 3. CI/CD avtomatik deploy edir köhnə vəziyyəti
# Audit trail: revert commit git log-da görünür

# Alternativ: əgər bir neçə commit geri qayıtmaq lazımdırsa
git revert HEAD~3..HEAD --no-edit
git push origin main
```

### Nümunə 5: Pull-based GitOps — ArgoCD ilə Laravel (Kubernetes)

```yaml
# argocd/laravel-app.yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: laravel-app
  namespace: argocd
spec:
  project: default
  source:
    repoURL: https://github.com/company/laravel-app.git
    targetRevision: main
    path: infrastructure/k8s/production
  destination:
    server: https://kubernetes.default.svc
    namespace: laravel-production
  syncPolicy:
    automated:
      prune: true       # Silinmiş manifestləri cluster-dan da sil
      selfHeal: true    # Drift aşkar edilərsə avtomatik düzəlt
    syncOptions:
      - CreateNamespace=true
```

```yaml
# infrastructure/k8s/production/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  template:
    spec:
      containers:
        - name: laravel
          image: company/laravel-app:main-a1b2c3d  # CI tərəfindən yenilənir
          env:
            - name: APP_ENV
              value: production
            - name: DB_HOST
              valueFrom:
                secretKeyRef:
                  name: laravel-secrets
                  key: db-host
```

### Nümunə 6: Image tag-ı CI-da avtomatik yeniləmək

```yaml
# .github/workflows/gitops-update.yml
name: GitOps — Update Image Tag

on:
  push:
    branches: [main]

jobs:
  build-and-update:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          token: ${{ secrets.GH_TOKEN }}

      - name: Build Docker image
        run: |
          IMAGE_TAG="main-$(git rev-parse --short HEAD)"
          docker build -t company/laravel-app:$IMAGE_TAG .
          docker push company/laravel-app:$IMAGE_TAG
          echo "IMAGE_TAG=$IMAGE_TAG" >> $GITHUB_ENV

      - name: Update k8s manifest
        run: |
          cd infrastructure/k8s/production
          sed -i "s|image: company/laravel-app:.*|image: company/laravel-app:$IMAGE_TAG|" deployment.yaml
          git config user.email "ci@company.com"
          git config user.name "CI Bot"
          git add deployment.yaml
          git commit -m "chore(deploy): update image to $IMAGE_TAG [skip ci]"
          git push origin main
```

### Nümunə 7: Drift detection — cluster vəziyyəti repo ilə uyğun deyil

```bash
# Kubectl ilə deployment-in faktiki image-ni yoxla
kubectl get deployment laravel-app -o jsonpath='{.spec.template.spec.containers[0].image}'
# company/laravel-app:main-oldsha

# Git-də nə olmalıdır
grep "image:" infrastructure/k8s/production/deployment.yaml
# image: company/laravel-app:main-newsha

# Drift var! ArgoCD avtomatik aşkar edir (selfHeal: true varsa düzəldir)
# Manual sync:
argocd app sync laravel-app
```

---

## Vizual İzah

### Push-based vs Pull-based GitOps

```
PUSH-BASED (GitHub Actions):
  Developer
    │ git push
    v
  GitHub ──── CI trigger ───> GitHub Actions
                                    │ deploy
                                    v
                              Production Server

PULL-BASED (ArgoCD/Flux):
  Developer
    │ git push
    v
  GitHub ←── polling (30s) ── ArgoCD Agent (cluster-da)
                                    │ diff aşkar edildi
                                    v
                              kubectl apply (auto-sync)

Fərq: Pull-based-da agent cluster-daxilindədir,
      outbound connection yetərlidir (inbound lazım deyil).
```

### GitOps deployment axını

```
 ┌─────────────┐    PR      ┌─────────────┐   Merge   ┌─────────────┐
 │  Developer  │──────────> │   GitHub    │──────────>│  main branch│
 └─────────────┘   Review   └─────────────┘           └──────┬──────┘
                                                              │
                                                         CI triggers
                                                              │
                                                              v
                                                    ┌──────────────────┐
                                                    │  GitHub Actions  │
                                                    │  1. Run tests    │
                                                    │  2. Build image  │
                                                    │  3. Update k8s   │
                                                    │     manifest     │
                                                    └────────┬─────────┘
                                                             │ git push
                                                             v
                                                    ┌──────────────────┐
                                                    │  ArgoCD detects  │
                                                    │  diff, applies   │
                                                    │  to cluster      │
                                                    └──────────────────┘
```

### Environment promotion axını

```
feature/x ──PR──> staging ──PR──> main
                     │                │
                     v                v
               staging.app.com   app.com
               (auto-deploy)     (auto-deploy)

Hər environment-ın öz konfiqurasiyası Git-də:
  config/staging.env   → staging branch
  config/production.env → main branch
```

---

## Praktik Baxış

### Laravel layihəsini GitOps-a keçirmək: addım-addım

```bash
# 1. Deployment skriptini idempotent et
# (yuxarıdakı deploy.sh nümunəsi)

# 2. Mühit dəyişənlərini secrets-ə köçür
# .env faylı → GitHub Secrets / Vault
# Repo-da yalnız .env.example qalsın

# 3. Branch-lardan environment-lara map et
git checkout -b staging
git push origin staging

# 4. GitHub Actions workflow yarat (yuxarıdakı nümunə)

# 5. Environment protection rules əlavə et
# GitHub → Settings → Environments → production
# ✅ Required reviewers: tech-lead
# ✅ Wait timer: 5 dakika
# ✅ Only main branch can deploy

# 6. Deployment history izlə
git log --oneline main | grep -E "^[a-f0-9]+ (chore\(deploy\)|revert)"
```

### Secrets management GitOps ilə

```yaml
# ❌ YANLIŞ: secrets-i repo-ya qoymaq
# config/production.yaml
db_password: "secret123"

# ✅ DOĞRU: External secrets operator
# infrastructure/k8s/external-secret.yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: laravel-secrets
spec:
  secretStoreRef:
    name: vault-backend
    kind: ClusterSecretStore
  target:
    name: laravel-secrets
  data:
    - secretKey: db-password
      remoteRef:
        key: laravel/production
        property: db_password
```

### Kustomize ilə environment overlay

```
infrastructure/k8s/
├── base/
│   ├── deployment.yaml    ← 1 replica, dev image
│   ├── service.yaml
│   └── kustomization.yaml
├── overlays/
│   ├── staging/
│   │   ├── kustomization.yaml  ← 2 replica, staging image
│   │   └── config-patch.yaml
│   └── production/
│       ├── kustomization.yaml  ← 5 replica, prod image
│       └── hpa.yaml            ← Horizontal Pod Autoscaler
```

```yaml
# overlays/production/kustomization.yaml
apiVersion: kustomize.config.k8s.io/v1beta1
kind: Kustomization
resources:
  - ../../base
patches:
  - path: config-patch.yaml
images:
  - name: company/laravel-app
    newTag: main-a1b2c3d
replicas:
  - name: laravel-app
    count: 5
```

### GitOps olmadan vs GitOps ilə müqayisə

```
Olmadan:                      Var:
────────                      ────
"Kim deploy etdi?" — Bilmirik "kim deploy etdi?" — git log
"Nə vaxt?"        — Bilmirik  "nə vaxt?" — commit timestamp
"Rollback?"       — Mürəkkəb  "rollback?" — git revert + push
"Staging niyə ..."             "staging niyə..." — git diff main..staging
  production-dan fərqli?
```

---

## Praktik Tapşırıqlar

1. **Push-based GitOps qurmaq**
   ```yaml
   # .github/workflows/deploy.yml
   on:
     push:
       branches: [main]
   jobs:
     deploy:
       environment: production
       steps:
         - uses: actions/checkout@v4
         - run: ./deploy.sh production
   ```
   GitHub → Settings → Environments → production → required reviewers əlavə et.

2. **Rollback test et**
   ```bash
   # Problemli dəyişiklik et
   git commit -m "feat: new feature" --allow-empty
   git push origin main
   # Deployment baş verdi

   # Rollback:
   git revert HEAD --no-edit
   git push origin main
   # Automatic rollback deployment
   ```

3. **Environment branches qur**
   ```bash
   git checkout -b staging
   git push origin staging
   # staging üçün ayrı workflow yaz
   # main → production, staging → staging environment
   ```

4. **Deployment audit**
   ```bash
   # Son 10 deployment-i göstər
   git log --oneline main --grep="chore(deploy)" | head -10
   # Rollback tarixçəsi
   git log --oneline main --grep="revert" | head -5
   ```

---

## Interview Sualları (Q&A)

### Q1: GitOps nədir? Adi CI/CD-dən fərqi nədir?

**Cavab:** GitOps — Git-i deployment-in yeganə həqiqət mənbəyi kimi istifadə edən prakttikdir. Adi CI/CD-dən fərqi:
- Adi CI/CD: pipeline sistemi deployment-i idarə edir, izlənilir amma Git-dən kənarda ola bilər
- GitOps: bütün dəyişiklik Git üzərindən keçir; deployment Git state-nin tətbiqidir

### Q2: Push-based və pull-based GitOps arasında fərq?

**Cavab:**
- **Push-based:** CI/CD sistemi (GitHub Actions) cluster-a deploys edir. Cluster inbound əlaqəyə açıq olmalıdır.
- **Pull-based:** Cluster-daxili agent (ArgoCD, Flux) Git-i polling edir, özü tətbiq edir. Yalnız outbound əlaqə lazımdır — daha təhlükəsiz.

### Q3: GitOps-da secrets necə idarə edilir?

**Cavab:** Secrets heç vaxt Git-ə düşmür. Üsullar:
1. **External Secrets Operator** — Vault/AWS SSM-dən cluster-a çəkir
2. **Sealed Secrets** — şifrənmiş secrets Git-ə commit olunur, cluster-da açılır
3. **GitHub Secrets** — CI/CD üçün, deployment zamanı env var kimi ötürülür

### Q4: GitOps-da rollback necə işləyir?

**Cavab:** `git revert <sha>` + push. Bu yeni commit yaradır, tarix pozulmur. ArgoCD/CI dəyişikliyi aşkar edib avtomatik deploy edir. Audit trail-də rollback aydın görünür.

### Q5: Environment branches vs overlay pattern hansı daha yaxşıdır?

**Cavab:**
- **Environment branches** (main/staging/production): sadədir, kiçik team-lər üçün
- **Overlay pattern** (Kustomize/Helm): daha güclü, DRY, böyük multi-environment setup-lar üçün

Environment branches-da merge conflict riski var; overlay-də base dəyişiklik avtomatik bütün environment-lara tətbiq edilir.

---

## Best Practices

1. **Hər dəyişiklik PR vasitəsilə**: birbaşa main-ə push olmasın. PR → review → merge → auto-deploy.

2. **Environment protection rules**: production üçün required reviewers, wait timer, yalnız main branch icazəsi.

3. **Idempotent deployment script**: eyni skript dəfələrlə işlədildikdə eyni nəticə verməlidir. Migration `--force`, cache:clear varsa problem çıxmaz.

4. **`[skip ci]` tag-ı**: CI-da manifest yeniləmə commit-i `[skip ci]` ilə işarələyin — infinite loop yaranmasın.

5. **Secrets-i Git-dən kənarda saxlayın**: External Secrets Operator, Vault, GitHub Secrets — heç bir credential repo-ya düşməsin.

6. **Deployment audit trail istifadə edin**: `git log --grep="deploy"` ilə kim nəyi nə vaxt deploy etdiyini izləyin.

7. **Rollback prosedurunu sənədləşdirin**: `git revert + push` — bütün komanda bilməlidir.

8. **Drift detection**: ArgoCD/Flux ilə cluster state-i repo ilə müqayisə edilsin. Manual cluster dəyişikliyi → alarm.

9. **Feature flag + GitOps kombinasiyası**: tam hazır olmayan feature-ları flag arxasında git-ə push edin, flag açılanda auto-deploy edilmiş kod aktiv olur.

10. **Multi-environment consistency**: staging-i production-un kiçik surəti kimi saxlayın. Konfiqurasiya fərqləri minimal olsun, overlay pattern istifadə edin.

---

## Əlaqəli Mövzular

- [17-trunk-based-development.md](17-trunk-based-development.md) — feature flags ilə deployment
- [22-git-workflow-team.md](22-git-workflow-team.md) — komanda branching workflow-u
- [26-conventional-commits-semantic-release.md](26-conventional-commits-semantic-release.md) — avtomatik versiyalama
- [29-codeowners-branch-protection.md](29-codeowners-branch-protection.md) — branch protection
