# Image Tagging və Versioning Strategiyası

> **Səviyyə (Level):** ⭐⭐ Middle
> **Oxu müddəti:** ~15-20 dəqiqə
> **Kateqoriya:** CI/CD & Release Engineering

## Nədir? (What is it?)

Docker image-lər **tag**-lərlə identifikasiya olunur. Tag — bir image-ə verilən insan-oxunaqlı ad: `myapp:v1.2.3`, `myapp:main`, `myapp:abc1234`. Bir image-in bir neçə tag-i ola bilər — onlar eyni image-ə istinad edir.

Problem: bir çox komanda hər deploy-da `myapp:latest` tag-ı istifadə edir. Bu **antipattern**-dir:
- `latest` bu gün v1.2.3-dür, sabah v1.3.0 — **mutable reference**
- Production-da 10 pod var, hər biri fərqli vaxtda çəkib, fərqli versiyalar işlədir
- Rollback mümkün deyil — köhnə `latest` üzərinə yeni `latest` yazılıb, artıq yoxdur
- Debug zamanı "hansı image production-dadır?" sualına cavab verə bilmirsən

Bu fayl **immutable tagging** strategiyasını və production-ready versioning pattern-larını izah edir.

## `latest` Niyə Antipattern?

### Senari 1: Reproducible deploy yoxdur

```bash
# Dev maşında
docker build -t myapp:latest .
docker push myapp:latest     # v1.0.0 kodu

# 3 saat sonra hotfix
docker build -t myapp:latest .
docker push myapp:latest     # v1.0.1 kodu — eyni tag!
```

Production cluster-də:
- Pod A 3 saat əvvəl çəkilib: `myapp:latest` → v1.0.0 image ID: sha256:abc...
- Pod B indi yeni startup-la: `myapp:latest` → v1.0.1 image ID: sha256:def...

**İki pod fərqli kod işlədir**, amma hər ikisi özünü "latest" adlandırır. Logging, debugging çaş-baş.

### Senari 2: Rollback imkansızdır

```bash
# Yeni deploy pis getdi
kubectl set image deployment/app app=myapp:latest    # hələ v1.0.1
# Rollback ?
kubectl set image deployment/app app=myapp:latest    # yenə v1.0.1 — eyni tag!
```

Köhnə versiya `latest` üzərinə yazıldığı üçün heç yerdə ad ilə istinad edilmir. `kubectl rollout undo` işləyə bilər (K8s öz daxili image ID-sini xatırlayır) — amma yeni pod spin up edilərsə `imagePullPolicy: Always` ilə yenə yeni `latest`-i çəkəcək.

### Senari 3: Cache-də göstərə bilmirsən

CI-də:
```yaml
cache-from: myapp:latest
```

Bu mənasızdır. Cache əvvəlki build-dən gəlməlidir — `latest` isə hələ bitməmişdir.

## Immutable Tag Policy

**Qızıl qayda:** bir tag bir dəfə push olunandan sonra heç vaxt dəyişməsin.

### AWS ECR

```bash
aws ecr put-image-tag-mutability \
  --repository-name myapp \
  --image-tag-mutability IMMUTABLE
```

İndi `docker push myapp:v1.0.0` ikinci dəfə fail edəcək.

### GitLab Container Registry

```yaml
# .gitlab-ci.yml
push:
  script:
    # Protected tag — yenidən push olunmaz
    - docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA
```

Registry-də tag mutability control yoxdur, amma CI pipeline-da enforce edilir (eyni SHA iki dəfə build olunmur).

### Docker Hub

Docker Hub native IMMUTABLE yoxdur, amma Pro hesabda policy var. Community-də `ghcr.io` və ECR tövsiyə olunur.

## Tag Növləri

### 1. Semantic Version (semver)

```
v1.2.3
```

- `1` — major (breaking change)
- `2` — minor (yeni feature, geri uyğun)
- `3` — patch (bug fix)

Git tag-dən çıxarılır:
```bash
git tag v1.2.3
git push --tags
# CI: release-triggered, build myapp:v1.2.3
```

**İnsan-oxunaqlı**, release note-lara uyğun, changelog-da yazılır.

### 2. Git Commit SHA

```
abc1234
a3f9e2b4c8d1
```

Qısa (7 char) və ya tam (40 char) SHA.

```bash
# CI-də
docker build -t myapp:${GITHUB_SHA::7} .
docker push myapp:${GITHUB_SHA::7}
```

**Unikal**, **reproducible** — hər commit öz image-i. Hər push öz image-ini yaradır.

### 3. Date / Timestamp

```
2026-04-25
2026-04-25-1430
20260425.1430
```

Nadir istifadə olunur, amma nightly build-lər üçün uyğundur:
```
myapp:nightly-2026-04-25
```

### 4. Branch

```
main
develop
feature-user-auth
```

Mutable! Yalnız dev / preview environment-lər üçün:
```
myapp:main         # ən son main build-i (staging üçün)
myapp:pr-142       # pull request preview
```

Production-da **istifadə etmə**.

### 5. Combination — Ən Çox Tövsiyə Olunur

```
v1.2.3-abc1234
```

Semver + git SHA birləşir. Insan üçün oxunur (`v1.2.3`), debug üçün dəqiq commit göstərir (`abc1234`).

Həmçinin:
```
v1.2.3-abc1234-2026-04-25
```

## Multi-Tag Pattern

Bir build, bir neçə tag. Registry-də yalnız bir image saxlanılır (deduplication), amma bir neçə adla istinad olunur.

```bash
IMAGE=myapp
VERSION=v1.2.3
SHA=$(git rev-parse --short HEAD)

# Build bir dəfə
docker build -t $IMAGE:$VERSION-$SHA .

# Həmin image-ə əlavə tag-lər
docker tag $IMAGE:$VERSION-$SHA $IMAGE:$VERSION       # v1.2.3
docker tag $IMAGE:$VERSION-$SHA $IMAGE:1.2            # v1.2 floating minor
docker tag $IMAGE:$VERSION-$SHA $IMAGE:1              # v1 floating major
docker tag $IMAGE:$VERSION-$SHA $IMAGE:latest         # latest

# Hamısını push
docker push $IMAGE:$VERSION-$SHA   # IMMUTABLE — unikal
docker push $IMAGE:$VERSION        # immutable — sem-ver xüsusi
docker push $IMAGE:1.2             # MUTABLE — minor hər patch-də dəyişir
docker push $IMAGE:1               # MUTABLE — major hər minor-da dəyişir
docker push $IMAGE:latest          # MUTABLE — hər deploy dəyişir
```

**Qayda:**
- `vX.Y.Z-<sha>` və `vX.Y.Z` — immutable (production deploy-larda)
- `vX.Y`, `vX`, `latest` — mutable (convenience, amma asla production deploy-da istifadə etmə)

## GitHub Actions Nümunəsi

```yaml
name: Build and Push

on:
  push:
    branches: [main]
    tags: ['v*']

jobs:
  build:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/${{ github.repository }}/app
          tags: |
            # Git tag-dən (v1.2.3 push olanda)
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}
            type=semver,pattern={{major}}
            # Branch adı (main push-da)
            type=ref,event=branch
            # PR üçün
            type=ref,event=pr
            # SHA
            type=sha,format=short
            # Birləşmiş: v1.2.3-abc1234
            type=raw,value={{version}}-{{sha}},enable=${{ startsWith(github.ref, 'refs/tags/v') }}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
```

`docker/metadata-action` avtomatik bütün tag-ləri generate edir:
- `git push origin main` → `myapp:main` + `myapp:sha-abc1234`
- `git tag v1.2.3 && git push --tags` → `myapp:1.2.3` + `myapp:1.2` + `myapp:1` + `myapp:1.2.3-abc1234`

## Registry Retention / Lifecycle Rules

Registry-dəki image-lər disk yeyir. Hər commit üçün image varsa, 1000 commit = 1000 image = 100+ GB.

### AWS ECR

```json
{
  "rules": [
    {
      "rulePriority": 1,
      "description": "Keep last 30 tagged images",
      "selection": {
        "tagStatus": "tagged",
        "tagPrefixList": ["v"],
        "countType": "imageCountMoreThan",
        "countNumber": 30
      },
      "action": { "type": "expire" }
    },
    {
      "rulePriority": 2,
      "description": "Remove untagged after 7 days",
      "selection": {
        "tagStatus": "untagged",
        "countType": "sinceImagePushed",
        "countUnit": "days",
        "countNumber": 7
      },
      "action": { "type": "expire" }
    },
    {
      "rulePriority": 3,
      "description": "Keep PR images only 14 days",
      "selection": {
        "tagStatus": "tagged",
        "tagPrefixList": ["pr-"],
        "countType": "sinceImagePushed",
        "countUnit": "days",
        "countNumber": 14
      },
      "action": { "type": "expire" }
    }
  ]
}
```

### Google Artifact Registry

```bash
gcloud artifacts repositories create-cleanup-policy myapp \
  --location=us-central1 \
  --policy='
  [
    {
      "name": "keep-recent-versions",
      "action": { "type": "KEEP" },
      "condition": {
        "tagState": "TAGGED",
        "tagPrefixes": ["v"],
        "versionAge": "30d"
      }
    },
    {
      "name": "delete-untagged",
      "action": { "type": "DELETE" },
      "condition": {
        "tagState": "UNTAGGED",
        "versionAge": "7d"
      }
    }
  ]'
```

### Docker Hub

Pro hesabda tag cleanup rules. Free hesabda əl ilə silmək lazımdır.

## Rollback Strategiyası

Immutable tag-lərin əsas faydası — **rollback bir tag dəyişikliyidir**.

### Kubernetes ilə

```bash
# Hazırda işləyən
kubectl get deployment/app -o jsonpath='{.spec.template.spec.containers[0].image}'
# myapp:v1.2.3-abc1234

# Rollback köhnə versiyaya
kubectl set image deployment/app app=myapp:v1.2.2-def5678
kubectl rollout status deployment/app
```

Və ya `rollout undo`:
```bash
kubectl rollout history deployment/app
kubectl rollout undo deployment/app --to-revision=3
```

### Docker Compose ilə

```yaml
# docker-compose.yml
services:
  app:
    image: myapp:v1.2.3-abc1234
```

Rollback:
```bash
# v1.2.2-def5678-ə dəyiş
sed -i 's/v1.2.3-abc1234/v1.2.2-def5678/' docker-compose.yml
docker compose up -d
```

Və ya `.env`-də:
```
APP_VERSION=v1.2.3-abc1234
```

```yaml
services:
  app:
    image: myapp:${APP_VERSION}
```

Rollback: `.env`-də versiyanı dəyiş, `docker compose up -d`.

## Kubernetes və `imagePullPolicy`

```yaml
spec:
  containers:
  - name: app
    image: myapp:v1.2.3-abc1234
    imagePullPolicy: IfNotPresent        # immutable tag → çox uyğundur
```

| Policy | Nə edir |
|--------|---------|
| `Always` | Hər pod start-da registry-dən çəkir (bandwidth yeyir, amma `latest` üçün zəruridir) |
| `IfNotPresent` | Node-da varsa çəkmir — immutable tag üçün ideal |
| `Never` | Heç vaxt çəkmir — local image istifadə edir (dev / minikube) |

**Immutable tag** (`v1.2.3-abc1234`) → `IfNotPresent` + sürətli pod start.
**Mutable tag** (`latest`, `main`) → `Always` + hər restart network sync.

## Real Laravel Deploy Nümunəsi

### CI/CD — GitHub Actions

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    tags: ['v*']

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Extract version
        id: version
        run: |
          VERSION=${GITHUB_REF#refs/tags/}
          SHA=${GITHUB_SHA::7}
          echo "version=${VERSION}" >> $GITHUB_OUTPUT
          echo "sha=${SHA}" >> $GITHUB_OUTPUT
          echo "full_tag=${VERSION}-${SHA}" >> $GITHUB_OUTPUT

      - name: Login to GHCR
        run: echo "${{ secrets.GITHUB_TOKEN }}" | docker login ghcr.io -u ${{ github.actor }} --password-stdin

      - name: Build
        run: |
          docker build \
            --tag $REGISTRY/$IMAGE_NAME:${{ steps.version.outputs.full_tag }} \
            --tag $REGISTRY/$IMAGE_NAME:${{ steps.version.outputs.version }} \
            --label org.opencontainers.image.version=${{ steps.version.outputs.version }} \
            --label org.opencontainers.image.revision=${{ github.sha }} \
            --label org.opencontainers.image.created=$(date -u +"%Y-%m-%dT%H:%M:%SZ") \
            .

      - name: Push
        run: |
          docker push $REGISTRY/$IMAGE_NAME:${{ steps.version.outputs.full_tag }}
          docker push $REGISTRY/$IMAGE_NAME:${{ steps.version.outputs.version }}

      - name: Deploy to K8s
        run: |
          kubectl set image deployment/laravel-app \
            app=$REGISTRY/$IMAGE_NAME:${{ steps.version.outputs.full_tag }} \
            -n production
          kubectl rollout status deployment/laravel-app -n production --timeout=5m
```

### Deployment YAML

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
  namespace: production
  labels:
    app: laravel
    version: v1.4.2      # release version
spec:
  replicas: 4
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
        version: v1.4.2
    spec:
      containers:
      - name: app
        image: ghcr.io/acme/laravel-app:v1.4.2-abc1234   # IMMUTABLE
        imagePullPolicy: IfNotPresent
        envFrom:
          - secretRef: { name: laravel-secrets }
        livenessProbe:
          httpGet: { path: /health, port: 9000 }
        resources:
          requests: { cpu: "500m", memory: "512Mi" }
          limits: { cpu: "1000m", memory: "1Gi" }
```

### Rollback senarió

Saat 14:30 — `v1.4.2` deploy. 14:45 — monitoring error rate spike göstərir. 14:46 — rollback:

```bash
# Ən son stabil versiya
kubectl set image deployment/laravel-app \
  app=ghcr.io/acme/laravel-app:v1.4.1-def5678 \
  -n production

kubectl rollout status deployment/laravel-app -n production
# deployment "laravel-app" successfully rolled out

# Verify
kubectl get pods -n production -l app=laravel -o jsonpath='{.items[*].spec.containers[0].image}'
# hər pod: v1.4.1-def5678
```

Rollback **30 saniyə**. Yeni build, yeni push lazım deyil — image artıq registry-dədir.

## Image Labels (OCI)

Tag-dan əlavə, image-ə metadata label-lar əlavə et:

```dockerfile
LABEL org.opencontainers.image.source="https://github.com/acme/laravel-app"
LABEL org.opencontainers.image.version="1.4.2"
LABEL org.opencontainers.image.revision="abc1234"
LABEL org.opencontainers.image.created="2026-04-25T14:30:00Z"
LABEL org.opencontainers.image.authors="team@acme.com"
LABEL org.opencontainers.image.licenses="MIT"
```

Sonra:
```bash
docker inspect myapp:v1.4.2 --format '{{ json .Config.Labels }}' | jq
```

Production-da pod-un hansı commit-dən build olduğunu bilmək üçün çox faydalıdır.

## Tələlər (Gotchas)

### 1. `latest` production-dadır — incident zamanı heç kim bilmir kod hansıdır

On-call mühəndis gecə 2-də çağırılır. `docker ps` image `myapp:latest`. "Hansı commit?" heç kim bilmir. Git `main` branch-da 50 commit var — hansı?

**Həll:** `latest`-i unudun. Hər deploy-a immutable tag verin. Label-larla commit revision yazın.

### 2. Eyni SHA, iki build — fərqli image

`CI-də build A: main-abc1234 (14:00).` `Dev yerdə 14:30: docker build -t myapp:abc1234.` Hər iki-sini push edir. Registry-də iki fərqli image eyni SHA ilə. Hansı production-dadır?

**Həll:** Registry-də `IMMUTABLE` policy. Yalnız CI push edə bilsin.

### 3. `v1.2` mutable tag production-da

```yaml
image: myapp:v1.2     # floating minor
```

Hotfix `v1.2.4` push olundu — `v1.2` tag yenisinə keçdi. Pod restart-da yeni kod. "Niyə feature dəyişib?" — heç kim izləmir.

**Həll:** Production-da yalnız full version (`v1.2.3`) və ya `v1.2.3-abc1234`.

### 4. Registry rate limit

Docker Hub free: 100 anonymous pull / 6 saat. Production cluster 50 pod restart → pull rate limit → pod fail. 

**Həll:** 
- Self-host registry (GHCR, ECR)
- `imagePullPolicy: IfNotPresent` + immutable tag — restart-da təkrar çəkmir
- Pull secret konfiqurasiya et (authenticated 200 pull / 6 saat)

### 5. Retention policy silib, rollback mümkün deyil

Retention "son 10 tag" — v1.4.2 problem verdi, v1.3.0-a rollback istənir, amma silinib.

**Həll:** Production-da istifadə olunan hər versiyanı retain et:
- Semver tag-ləri → 90 gün saxla
- PR tag-ləri → 14 gün
- Untagged → 7 gün

### 6. Tag floating — `v1` deploy olundu, 2 ay sonra `v1` fərqli kod

`Deployment.yaml`:
```yaml
image: myapp:v1
```

İlk deploy: `v1` → `v1.0.0`. İki ay sonra pod restart: `v1` → `v1.5.0` (yeni release-lər). Pod fərqli kod işlədir, heç kim kod push etməyib.

**Həll:** Deploy-da full tag. Floating tag-lər yalnız docs-da ("v1 always works" kimi).

### 7. `docker pull` cache-də qalmış image

```bash
docker pull myapp:v1.2.3
# ... işləyir
```

Amma aylar əvvəl v1.2.3 fərqli kod idi (mutable registry). Registry-də yenilənib, lokal cache köhnə. 

**Həll:** IMMUTABLE registry policy. Və ya `--pull=always`.

### 8. Digest istifadə — ultimate immutability

Tag yaxındır, amma digest daha dəqiqdir:
```
myapp@sha256:abc123...def
```

Digest — image content-in hash-i. Content dəyişirsə digest də dəyişir. İki image eyni digest-ə malikdirsə, tam identikdirlər.

```yaml
image: ghcr.io/acme/app@sha256:abc123def456...
```

Paranoid production-larda istifadə olunur (Kubernetes supply-chain security).

## Best Practices

1. **Heç vaxt `latest` production deploy-da istifadə etmə.** Immutable tag həmişə.
2. **Semver + git SHA birləşdir.** `v1.2.3-abc1234` — həm insan, həm maşın üçün.
3. **Registry-də IMMUTABLE policy.** Push-dan sonra dəyişməsin.
4. **`docker/metadata-action` istifadə et.** CI-də tag generation-i avtomatlaşdırır.
5. **Retention rules qur.** 30 gün semver, 7 gün untagged, 14 gün PR.
6. **OCI label-lar əlavə et.** Image içinə commit SHA, version, timestamp yaz.
7. **`imagePullPolicy: IfNotPresent`** immutable tag ilə — sürətli restart.
8. **Release tag-dən deploy et.** Manual `kubectl set image` yerinə Git tag → CI → deploy.
9. **Digest istifadə et** critical production-da (supply chain security).

## Müsahibə Sualları

- **Q:** `latest` tag niyə pisdir?
  - Mutable — dəyişir. Iki pod eyni `latest` yazsa da fərqli image işlədə bilər. Rollback mümkün deyil (köhnə `latest` üzərinə yazıldı). "Hansı kod production-dadır?" sualına cavab vermək olmur.

- **Q:** Immutable tag strategiyası necə qurulur?
  - Registry-də `IMMUTABLE` policy. CI-də hər build-ə unikal tag (`vX.Y.Z-<sha>`). Deploy YAML-da tam tag yazılır. Rollback əvvəlki tag-i göstərməklə olur.

- **Q:** Semver və git SHA birləşdirmək niyə yaxşıdır?
  - Semver insan üçün (`v1.2.3` — changelog, release note). SHA maşın üçün (hansı kommitdir?). Birləşdirsən: `v1.2.3-abc1234` — hər iki tərəf üçün.

- **Q:** `imagePullPolicy: Always` və `IfNotPresent` fərqi?
  - `Always` — hər pod start-da registry-ə bakır (bandwidth, latency). `IfNotPresent` — node-da varsa yox (sürətli). Immutable tag ilə `IfNotPresent` ideal, content dəyişməz.

- **Q:** Retention policy necə qurursan?
  - Production versiyalar (semver) — 90 gün. Untagged — 7 gün. PR preview — 14 gün. ECR/GCR lifecycle rules ilə avtomatik.

- **Q:** Rollback 30 saniyə necə olur?
  - Əvvəlki versiya registry-dədir (retention policy onu saxlayıb). `kubectl set image` ilə tag dəyiş — yeni pod köhnə image-i `IfNotPresent`-lə çəkir (node-da artıq var), start edir. Yeni build yoxdur.

- **Q:** Digest-dən istifadə etmək nə vaxt?
  - Supply-chain security (tag mutate olmasın — nadir hücum vektoru). Production-da ultra-paranoid. Default-da tag kifayətdir.

- **Q:** `docker/metadata-action` nə edir?
  - GitHub event-inə əsasən avtomatik tag generate edir. `v1.2.3` git tag push olduqda `1.2.3`, `1.2`, `1` tag-ləri. `main` branch push-da `main` + `sha-abc1234`. Manual tag yazmağa ehtiyac yoxdur.
