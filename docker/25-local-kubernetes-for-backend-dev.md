# Local Kubernetes Backend Dev üçün (kind, minikube, k3d)

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

Local Kubernetes — öz laptop-unuzda (macOS/Linux/Windows) işləyən mini K8s klasterdir. Məqsəd: produksiya klasterinə çatmadan manifest-ləri test etmək, debug etmək və Kubernetes-in davranışını öyrənmək.

Backend developer (xüsusilə PHP/Laravel tərəfdən gələn) çox vaxt düşünür: "mən niyə local K8s qurum? Docker Compose bəs deyil?" Cavab: çox vaxt Docker Compose kifayətdir. Amma manifest yazırsansa və ya produksiya davranışını təkrarlamaq istəyirsənsə — local K8s vacibdir.

### Backend Dev Niyə Local K8s İşlətməlidir?

- **Manifest-ləri merge etməzdən əvvəl test etmək** — PR-dakı `deployment.yaml` sintaktik olaraq düzgündür, amma pod həqiqətən qalxırmı?
- **Probe-ları debug etmək** — `readinessProbe` niyə fail olur? Local-da `kubectl describe pod` ilə dərhal görə bilərsən
- **Init container-lərin sırasını yoxlamaq** — migration init container-i main container-dən əvvəl qurtarırmı?
- **HPA (Horizontal Pod Autoscaler) davranışını anlamaq** — real load ilə pod-lar həqiqətən scale edirmi?
- **ConfigMap/Secret mount-larını test etmək** — file kimi mi, env kimi mi mount olunur?
- **K8s-i kiçik sürətdə öyrənmək** — produksiya klasterini sındırmadan

Əgər sən yalnız `kubectl apply -f` və `kubectl logs` çalışdırırsansa, yəqin ki local K8s-ə ehtiyac yoxdur. Amma Helm chart yazırsansa və ya özün manifest author-usansa — lazımdır.

## Əsas Konseptlər

### 1. Local K8s Alətlərinin Müqayisəsi

```
┌──────────────────┬──────────────┬──────────┬─────────────┬──────────────┐
│ Alət             │ Texnologiya  │ RAM      │ Sürət       │ Multi-node   │
├──────────────────┼──────────────┼──────────┼─────────────┼──────────────┤
│ kind             │ Docker-in-   │ ~2 GB    │ Sürətli     │ Bəli (asan)  │
│                  │ Docker       │          │ (~30s)      │              │
├──────────────────┼──────────────┼──────────┼─────────────┼──────────────┤
│ minikube         │ VM (default) │ ~4 GB    │ Orta        │ Bəli         │
│                  │ və ya Docker │          │ (~60s)      │              │
├──────────────────┼──────────────┼──────────┼─────────────┼──────────────┤
│ k3d              │ k3s in       │ ~1 GB    │ Ən sürətli  │ Bəli         │
│                  │ Docker       │          │ (~15s)      │              │
├──────────────────┼──────────────┼──────────┼─────────────┼──────────────┤
│ Docker Desktop   │ Built-in VM  │ ~2 GB    │ Hazırdır    │ Yox (1-node) │
│ K8s              │              │          │ (on/off)    │              │
├──────────────────┼──────────────┼──────────┼─────────────┼──────────────┤
│ Colima (macOS)   │ Lima VM      │ ~2 GB    │ Orta        │ Yox          │
└──────────────────┴──────────────┴──────────┴─────────────┴──────────────┘
```

**Detallı:**

- **kind (Kubernetes IN Docker)** — rəsmi CNCF layihəsi. Hər node bir Docker konteynerdir. Tam-uyğun K8s (upstream kubeadm). CI/CD üçün ən yaxşı. Multi-node asan konfiqurasiya olunur.
- **minikube** — ən köhnə və rəsmi. Default olaraq VM işlədir (VirtualBox/HyperKit/KVM). Addon-lar güclüdür (dashboard, ingress, registry). Bir az ağırdır.
- **k3d** — k3s (lightweight K8s, Rancher-in məhsulu) Docker konteynerlərində. Ən sürətli start, ən az resurs. Bəzi enterprise feature-lar kəsilib (amma backend dev üçün kifayətdir).
- **Docker Desktop K8s** — "Settings → Kubernetes → Enable". Bir kliklik. Amma yalnız 1-node, və hər reset-də bütün cluster silinir.
- **Colima** — macOS-da Docker Desktop alternativi (pulsuz). K8s-i `colima start --kubernetes` ilə aktiv edir.

**Tövsiyə (backend dev üçün):** `kind` və ya `k3d`. kind rəsmi və populyardır, k3d isə daha sürətlidir. Mən gündəlik istifadədə `kind` məsləhət görürəm — CI-də də işlədirsə, local-la CI eyni olur.

### 2. kind Quraşdırmaq və Cluster Yaratmaq

```bash
# Quraşdırma (macOS)
brew install kind

# Quraşdırma (Linux)
curl -Lo ./kind https://kind.sigs.k8s.io/dl/v0.23.0/kind-linux-amd64
chmod +x ./kind
sudo mv ./kind /usr/local/bin/kind

# Sadə cluster (1 control-plane, 0 worker)
kind create cluster --name dev

# Cluster-i silmək
kind delete cluster --name dev

# Cluster-ləri siyahılamaq
kind get clusters
```

**Multi-node cluster (1 control + 2 worker):**

```yaml
# kind-config.yaml
kind: Cluster
apiVersion: kind.x-k8s.io/v1alpha4
name: dev
nodes:
  - role: control-plane
    # Ingress NodePort-larını host-a açırıq (80/443)
    kubeadmConfigPatches:
      - |
        kind: InitConfiguration
        nodeRegistration:
          kubeletExtraArgs:
            node-labels: "ingress-ready=true"
    extraPortMappings:
      - containerPort: 80
        hostPort: 80
        protocol: TCP
      - containerPort: 443
        hostPort: 443
        protocol: TCP
  - role: worker
  - role: worker
```

```bash
kind create cluster --config kind-config.yaml

# Node-ları yoxla
kubectl get nodes
# NAME                 STATUS   ROLES           AGE
# dev-control-plane    Ready    control-plane   1m
# dev-worker           Ready    <none>          45s
# dev-worker2          Ready    <none>          45s
```

### 3. Ingress-Nginx Quraşdırmaq (kind-da)

```bash
# Ingress-nginx-i kind-a uyğun yamanla quraşdır
kubectl apply -f \
  https://raw.githubusercontent.com/kubernetes/ingress-nginx/main/deploy/static/provider/kind/deploy.yaml

# Hazır olmasını gözlə
kubectl wait --namespace ingress-nginx \
  --for=condition=ready pod \
  --selector=app.kubernetes.io/component=controller \
  --timeout=90s
```

İndi `http://localhost` host kompüterdən ingress-ə çatır.

### 4. Local Laravel Image-ini kind-a Yükləmək

Adi registry-dən (Docker Hub) pull olmur, çünki image local-da build edilib:

```bash
# Laravel image-i build et
docker build -t laravel:dev .

# Image-i kind cluster-ə yüklə (vacib addım!)
kind load docker-image laravel:dev --name dev

# Yoxla
docker exec -it dev-control-plane crictl images | grep laravel
```

**Niyə `kind load` lazımdır?** kind node-ları ayrı Docker konteynerlərdir. Host-dakı Docker image-ini görmürlər. `kind load` image-i hər node-a kopyalayır.

### 5. k3d — Alternativ Variant

```bash
# Quraşdırma
curl -s https://raw.githubusercontent.com/k3d-io/k3d/main/install.sh | bash

# Cluster yaratmaq (1 server, 2 agent, port 80/443 mapped)
k3d cluster create dev \
  --servers 1 \
  --agents 2 \
  --port "80:80@loadbalancer" \
  --port "443:443@loadbalancer"

# Image yükləmək
docker build -t laravel:dev .
k3d image import laravel:dev -c dev

# Silmək
k3d cluster delete dev
```

k3d avtomatik olaraq Traefik ingress-i ilə gəlir — ayrıca quraşdırmağa ehtiyac yoxdur.

### 6. kubectl Context İdarəsi

Bir neçə cluster-in olanda context vacibdir:

```bash
# Mövcud context-ləri gör
kubectl config get-contexts

# Aktiv context-i gör
kubectl config current-context
# kind-dev

# Context dəyişmək (prod-a cəsarətlə apply etməmək üçün diqqət!)
kubectl config use-context kind-dev
kubectl config use-context docker-desktop

# Bütün context-lərdə default namespace qoymaq
kubectl config set-context --current --namespace=laravel
```

**Təhlükəsizlik tövsiyəsi:** `kubectl` üçün `kubectx` + `kubens` alətləri quraşdır. Prompt-una hansı context-də olduğunu göstərən `kube-ps1` əlavə et — yanlışlıqla prod-a `kubectl delete` etməkdən qoruyur.

### 7. Skaffold və Tilt — Inner-Loop Dev

Manual döngü: kod dəyişdir → `docker build` → `kind load` → `kubectl rollout restart` — hər dəfə 30+ saniyə. Bunu avtomatlaşdırmaq lazımdır.

**Skaffold** (Google-un alətidir):

```yaml
# skaffold.yaml
apiVersion: skaffold/v4beta11
kind: Config
metadata:
  name: laravel
build:
  artifacts:
    - image: laravel
      docker:
        dockerfile: Dockerfile
      sync:
        # PHP fayllarını dərhal konteynerə sync et (rebuild etmədən)
        manual:
          - src: "app/**/*.php"
            dest: /var/www/html
          - src: "routes/**/*.php"
            dest: /var/www/html
  local:
    push: false  # kind üçün push lazım deyil
deploy:
  kubectl:
    manifests:
      - k8s/*.yaml
```

```bash
# Fayl dəyişdikcə avtomatik rebuild/sync/redeploy
skaffold dev

# Sadəcə bir dəfə build + deploy
skaffold run
```

**Tilt** (Docker-in alətidir, daha UI-yönümlü):

```python
# Tiltfile
docker_build('laravel', '.',
    live_update=[
        sync('./app', '/var/www/html/app'),
        sync('./routes', '/var/www/html/routes'),
        run('php artisan config:clear', trigger=['./config']),
    ])
k8s_yaml('k8s/deployment.yaml')
k8s_yaml('k8s/service.yaml')
k8s_resource('laravel', port_forwards='8080:80')
```

```bash
tilt up
# http://localhost:10350 — Tilt UI
```

Skaffold CLI-ə yaxındır, Tilt vizualdır. Team preference.

### 8. Telepresence — Hybrid Dev

Local + remote birləşməsi: cluster staging-dədir, sən yalnız bir servisi local-da dəyişirsən:

```bash
telepresence connect
telepresence intercept laravel-api --port 8080:80
# İndi cluster trafiki sənin local 8080 portuna gəlir
```

Backend dev üçün faydalıdır: staging-dakı DB, Redis, Kafka-ya çatmaq lazımdır, amma yalnız öz kodun local-da olsun. K8s-in bütün environment-ini qaldırmağa ehtiyac olmur.

## Praktiki Nümunə: Laravel + Postgres + Redis kind-da

### 1. Cluster-i hazırla

```bash
kind create cluster --config kind-config.yaml
kubectl create namespace laravel
```

### 2. Postgres və Redis (Helm ilə)

```bash
helm repo add bitnami https://charts.bitnami.com/bitnami
helm repo update

helm install pg bitnami/postgresql \
  -n laravel \
  --set auth.postgresPassword=dev \
  --set auth.database=laravel \
  --set primary.persistence.enabled=false

helm install redis bitnami/redis \
  -n laravel \
  --set auth.enabled=false \
  --set master.persistence.enabled=false
```

### 3. Laravel manifest-ləri

```yaml
# k8s/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel
  namespace: laravel
spec:
  replicas: 2
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      initContainers:
        - name: migrate
          image: laravel:dev
          imagePullPolicy: Never  # local image — pull etmə
          command: ["php", "artisan", "migrate", "--force"]
          env:
            - name: DB_HOST
              value: pg-postgresql
            - name: DB_PASSWORD
              value: dev
      containers:
        - name: app
          image: laravel:dev
          imagePullPolicy: Never
          ports:
            - containerPort: 80
          env:
            - name: DB_HOST
              value: pg-postgresql
            - name: REDIS_HOST
              value: redis-master
          readinessProbe:
            httpGet:
              path: /up
              port: 80
            periodSeconds: 5
---
apiVersion: v1
kind: Service
metadata:
  name: laravel
  namespace: laravel
spec:
  selector:
    app: laravel
  ports:
    - port: 80
      targetPort: 80
```

### 4. Apply və port-forward

```bash
docker build -t laravel:dev .
kind load docker-image laravel:dev --name dev

kubectl apply -f k8s/

# Service-ə port-forward
kubectl port-forward -n laravel svc/laravel 8080:80

# Brauzerdə: http://localhost:8080
```

### 5. Cleanup

```bash
# Yalnız namespace-i sil
kubectl delete namespace laravel

# Bütün cluster-i sil
kind delete cluster --name dev

# Docker-də qalmış image-lər çox yer tutur — təmizlə
docker system prune -a --volumes
```

## Best Practices

1. **Cluster-i tez-tez sıfırla** — `kind delete cluster && kind create cluster`. Cluster state problemləri aradan qalxır.
2. **Multi-node yalnız lazım olanda** — topology affinity, anti-affinity test edirsənsə. Yoxsa 1-node kifayətdir.
3. **Resources məhdudlaşdır** — laptop-un CPU/RAM-ini yandırmayın. kind konfiqurasiyasında node resurslarını məhdudlaşdır.
4. **Context-ə diqqət** — local context adında "kind" və ya "local" olsun, prompt-da görün.
5. **Skaffold və ya Tilt işlət** — manual rebuild/restart vaxt itkisidir.
6. **`imagePullPolicy: Never`** — local image-lər üçün. Yoxsa K8s registry-dən pull etməyə çalışır və xəta verir.
7. **Helm chart-ları staging-də test et** — amma manifest-ləri local kind-da test et. Helm chart linting ayrıca.

## Tələlər (Gotchas)

### 1. "ImagePullBackOff" error local image üçün
Pod `image pull` etməyə çalışır, amma image Docker Hub-da yoxdur.
```yaml
imagePullPolicy: Never  # bu əlavə olmalıdır
# və `kind load docker-image` etmisənmi?
```

### 2. Ingress 80/443-də görünmür
kind konfiqurasiyasında `extraPortMappings` yoxsa, `localhost:80` çatmır. Həmişə config file ilə yarat.

### 3. PVC yaratmaq — amma storage yoxdur
kind və k3d default `standard` StorageClass ilə gəlir. Amma bəzi minikube konfiqurasiyalarında PVC pending qalır. `kubectl get sc` ilə yoxla.

### 4. DNS problemi (cluster daxili)
`coredns` pod-u `CrashLoopBackOff`-dadırsa, Docker-in `/etc/resolv.conf`-u problemlidir. Docker-i restart et.

### 5. Laptop batareyası tez bitir
kind + Docker + Skaffold laptop-u yandırır. İstifadə etməyəndə `kind delete cluster` et.

### 6. "Too many open files"
Çoxlu pod + Docker = `inotify` limit bitir. Linux-da:
```bash
sudo sysctl fs.inotify.max_user_watches=524288
sudo sysctl fs.inotify.max_user_instances=512
```

### 7. Docker Desktop K8s-i həmişə açıq saxlamaq
Həmişə açıq olmalı deyil — RAM yeyir. Lazım olanda aç.

## Müsahibə Sualları

### S1: Backend developer niyə local K8s işlətsin?
**C:** Manifest-ləri merge etməzdən əvvəl test etmək, probe-ları debug etmək, init container-lərin işini yoxlamaq və HPA davranışını anlamaq üçün. Əgər yalnız `kubectl apply` edirsə və başqalarının manifest-lərini işlədirsə, lazım deyil — docker-compose kifayətdir. Amma manifest author-usansa, lazımdır.

### S2: kind, minikube və k3d arasında fərq nədir?
**C:** Üçü də local K8s verir. **kind** node-ları Docker konteynerlərində işlədir, CNCF rəsmisidir, CI-də populyardır. **minikube** default olaraq VM-də işləyir, addon-ları güclüdür, bir az ağırdır. **k3d** k3s-i (lightweight K8s) Docker-də işlədir, ən sürətli start və ən az resurs. Backend dev üçün kind və ya k3d tövsiyə olunur.

### S3: `kind load docker-image` nə edir?
**C:** Local Docker-də build olunmuş image-i kind cluster-in node konteynerlərinə kopyalayır. kind node-ları ayrı Docker konteynerlərdir və host-un image-lərini görmürlər. Bu addım olmadan pod `ImagePullBackOff` alır.

### S4: Skaffold və Tilt arasında fərq nədir?
**C:** İkisi də inner-loop dev sürətlidir — fayl dəyişdikcə avtomatik rebuild/deploy. **Skaffold** Google-un, CLI-yönümlü, CI-də də istifadə oluna bilər. **Tilt** Docker-in, vizual UI (localhost:10350), daha çox developer experience-ə fokuslanır. Seçim team preference-dir.

### S5: Telepresence nə üçündür?
**C:** Hybrid local dev. Cluster remote-da (staging) qalır, sən yalnız bir servisi local-da dəyişirsən. Cluster-dəki trafik sənin local prosesinə yönləndirilir. Faydalıdır: bütün environment-i local-a qaldırmadan, real remote dependencies (DB, Redis, Kafka) ilə test etmək.

### S6: Local K8s istifadə etməyin vaxtı nə vaxtdır?
**C:** Erkən development-də Docker Compose daha tez və sadədir. Local K8s-ə keç: manifest yazırsansa, Helm chart author-usansa, probe/HPA/init-container debug edirsənsə, K8s öyrənirsənsə, və ya yeni team-member onboarding-də K8s workflow lazımdırsa.

### S7: kind-da ingress necə qurulur?
**C:** kind konfiqurasiyasında control-plane node-a `ingress-ready=true` label-i əlavə olunur və `extraPortMappings` ilə 80/443 portları host-a açılır. Sonra ingress-nginx-in kind-specific deploy.yaml-ı apply olunur. k3d default Traefik ingress ilə gəlir — bu addım lazım deyil.

### S8: "imagePullPolicy: Never" nə vaxt istifadə olunur?
**C:** Local development-də, image registry-də yox, yalnız local Docker-də mövcud olanda. Produksiyada heç vaxt — orada `Always` və ya `IfNotPresent` (dəqiq tag-la) olmalıdır. Local kind/k3d workflow-unda `kind load` sonra `imagePullPolicy: Never` kombinasiyası standartdır.


## Əlaqəli Mövzular

- [kubernetes-basics.md](18-kubernetes-basics.md) — K8s arxitekturası
- [kubernetes-helm.md](23-kubernetes-helm.md) — Helm chart deploy
- [helm-chart-consumer-guide.md](24-helm-chart-consumer-guide.md) — Helm istifadəsi
