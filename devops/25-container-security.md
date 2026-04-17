# Container Security (Konteyner Təhlükəsizliyi)

## Nədir? (What is it?)

Container security – Docker image-lərinin, konteyner runtime-ın və orchestration platformalarının (Kubernetes) təhlükəsizliyini təmin edən praktika və alətlər toplusudur. Konteynerlərin təhlükəsizliyi çoxqatlıdır: image scanning (vulnerability), runtime security (syscall filtering), pod security policies, network policies, RBAC (role-based access control). Modern DevSecOps praktikasında shift-left security təbliğ edilir – probleminin erkən mərhələdə aşkar edilməsi.

## Əsas Konseptlər (Key Concepts)

### Image Scanning

```bash
# Image scanning = Docker image-lərini məlum vulnerability-lər üçün yoxlamaq
# CVE (Common Vulnerabilities and Exposures) database-ə əsaslanır

# Trivy (ən populyar, free, open source)
trivy image php:8.2-fpm
trivy image --severity HIGH,CRITICAL nginx:latest
trivy image --ignore-unfixed nginx:latest
trivy image --format json -o result.json nginx:latest

# Filesystem scan (image build-dən əvvəl)
trivy fs --security-checks vuln,config .

# Git repo scan (secret və config problem)
trivy repo https://github.com/myorg/laravel-app

# SBOM (Software Bill of Materials) generate
trivy image --format cyclonedx nginx:latest > sbom.json

# Kubernetes cluster scan
trivy k8s --report summary cluster

# Snyk (commercial)
snyk container test nginx:latest

# Grype (Anchore)
grype nginx:latest

# Clair (scanner for registries)
# Registry-yə image push olanda avtomatik scan

# CI/CD-də inteqrasiya
# .github/workflows/security.yml
- name: Run Trivy scan
  uses: aquasecurity/trivy-action@master
  with:
    image-ref: myapp:${{ github.sha }}
    format: 'sarif'
    output: 'trivy-results.sarif'
    severity: 'CRITICAL,HIGH'
    exit-code: '1'        # Fail build if vulnerabilities found
```

### Secure Dockerfile

```dockerfile
# ZƏIF Dockerfile (təhlükəli)
FROM ubuntu:latest                      # latest tag - reproducibility yox
RUN apt-get update && apt-get install -y php
COPY . /app                              # Bütün şeyi kopyalayır (secret-lər daxil)
CMD ["php", "/app/server.php"]          # Root istifadəçi ilə işləyir
EXPOSE 80

# GÜÇLÜ Dockerfile (təhlükəsiz)
FROM php:8.2-fpm-alpine@sha256:abc...   # Spesifik image, digest pin

# Root olmayan istifadəçi yarat
RUN addgroup -g 1000 laravel && \
    adduser -u 1000 -G laravel -s /bin/sh -D laravel

# Lazımsız paketləri silmək
RUN apk add --no-cache \
    composer=2.6.5-r0 && \
    rm -rf /var/cache/apk/*

# Spesifik fayllar kopyalamaq (.dockerignore istifadə et)
COPY --chown=laravel:laravel composer.json composer.lock /app/
WORKDIR /app

# Dependency install as non-root
USER laravel
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY --chown=laravel:laravel . /app/

# Readonly filesystem (mümkün olduqda)
# Health check
HEALTHCHECK --interval=30s --timeout=3s CMD wget -qO- http://localhost:8000/health || exit 1

# Minimum privilege
USER laravel
EXPOSE 8000
CMD ["php-fpm", "-F"]
```

```bash
# .dockerignore (vacib!)
cat > .dockerignore <<EOF
.git
.github
.env
.env.*
!.env.example
node_modules
vendor
storage/logs/*
storage/framework/cache/*
*.md
tests/
docker-compose.yml
Dockerfile*
EOF
```

### Multi-stage Build (smaller & safer images)

```dockerfile
# Build stage
FROM composer:2 AS composer
WORKDIR /app
COPY composer.* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# Runtime stage (minimum)
FROM php:8.2-fpm-alpine
RUN apk add --no-cache nginx supervisor \
    && docker-php-ext-install pdo_mysql opcache

RUN addgroup -g 1000 www && adduser -u 1000 -G www -D www

COPY --from=composer --chown=www:www /app/vendor /var/www/html/vendor
COPY --from=frontend --chown=www:www /app/public/build /var/www/html/public/build
COPY --chown=www:www . /var/www/html

USER www
EXPOSE 80
CMD ["supervisord", "-c", "/etc/supervisord.conf"]

# Nəticə: 150MB əvəzinə 80MB, az vulnerability
```

### Runtime Security

```bash
# Docker security options
docker run \
  --read-only \                               # Readonly filesystem
  --tmpfs /tmp \                              # tmpfs for writable
  --cap-drop ALL \                            # Drop all Linux capabilities
  --cap-add NET_BIND_SERVICE \                # Yalnız port binding üçün
  --security-opt no-new-privileges:true \     # setuid binaries blok
  --security-opt seccomp=seccomp-profile.json \ # System call filtering
  --security-opt apparmor=docker-default \    # AppArmor profile
  --pids-limit 100 \                          # Fork bomb qoruması
  --memory 512m \                             # Memory limit
  --cpus 0.5 \                                # CPU limit
  --user 1000:1000 \                          # Non-root user
  myapp:latest

# Docker Bench for Security (CIS benchmark)
docker run -it --net host --pid host --userns host --cap-add audit_control \
    -e DOCKER_CONTENT_TRUST=$DOCKER_CONTENT_TRUST \
    -v /var/lib:/var/lib -v /var/run/docker.sock:/var/run/docker.sock \
    -v /usr/lib/systemd:/usr/lib/systemd -v /etc:/etc \
    --label docker_bench_security \
    docker/docker-bench-security

# Falco (runtime security, anomaly detection)
# Abnormal behavior detection
helm install falco falcosecurity/falco

# Falco rule nümunəsi
- rule: Unauthorized Shell in Container
  desc: Detect shell spawned in container
  condition: container.id != host and proc.name in (bash, sh, zsh)
  output: "Shell spawned in container (user=%user.name container=%container.name)"
  priority: WARNING
```

### Kubernetes Pod Security

```yaml
# Pod Security Standards (K8s 1.25+)
# Levels:
# - Privileged: heç bir məhdudiyyət
# - Baseline: əsas restriction-lar
# - Restricted: ciddi (tövsiyə olunur)

apiVersion: v1
kind: Namespace
metadata:
  name: production
  labels:
    pod-security.kubernetes.io/enforce: restricted
    pod-security.kubernetes.io/audit: restricted
    pod-security.kubernetes.io/warn: restricted

---
# Secure Pod nümunəsi
apiVersion: v1
kind: Pod
metadata:
  name: laravel-secure
spec:
  securityContext:
    runAsNonRoot: true
    runAsUser: 1000
    runAsGroup: 1000
    fsGroup: 1000
    seccompProfile:
      type: RuntimeDefault
  
  containers:
  - name: laravel
    image: laravel:2.0
    securityContext:
      allowPrivilegeEscalation: false
      readOnlyRootFilesystem: true
      runAsNonRoot: true
      capabilities:
        drop: ["ALL"]
        add: ["NET_BIND_SERVICE"]
    
    resources:
      requests:
        memory: "256Mi"
        cpu: "250m"
      limits:
        memory: "512Mi"
        cpu: "500m"
    
    volumeMounts:
    - name: tmp
      mountPath: /tmp
    - name: cache
      mountPath: /var/www/storage/framework/cache
  
  volumes:
  - name: tmp
    emptyDir: {}
  - name: cache
    emptyDir: {}
```

### Network Policies

```yaml
# Default deny all ingress
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny-ingress
  namespace: production
spec:
  podSelector: {}
  policyTypes:
  - Ingress

---
# Laravel app-ə yalnız ALB-dən gəlsin
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: laravel-ingress
  namespace: production
spec:
  podSelector:
    matchLabels:
      app: laravel
  policyTypes:
  - Ingress
  - Egress
  ingress:
  - from:
    - podSelector:
        matchLabels:
          app: nginx-ingress
    ports:
    - protocol: TCP
      port: 80
  egress:
  - to:
    - podSelector:
        matchLabels:
          app: mysql
    ports:
    - protocol: TCP
      port: 3306
  - to:
    - podSelector:
        matchLabels:
          app: redis
    ports:
    - protocol: TCP
      port: 6379
  - to: []
    ports:
    - protocol: UDP
      port: 53          # DNS
```

### RBAC (Role-Based Access Control)

```yaml
# Role - namespace səviyyəsində icazələr
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  namespace: production
  name: developer
rules:
- apiGroups: [""]
  resources: ["pods", "pods/log"]
  verbs: ["get", "list", "watch"]
- apiGroups: ["apps"]
  resources: ["deployments"]
  verbs: ["get", "list", "watch", "update", "patch"]

---
# RoleBinding - rol-u user/group-a bağlamaq
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: developer-binding
  namespace: production
subjects:
- kind: User
  name: alice@example.com
  apiGroup: rbac.authorization.k8s.io
- kind: Group
  name: dev-team
  apiGroup: rbac.authorization.k8s.io
roleRef:
  kind: Role
  name: developer
  apiGroup: rbac.authorization.k8s.io

---
# ClusterRole - cluster-wide icazələr
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: readonly
rules:
- apiGroups: ["*"]
  resources: ["*"]
  verbs: ["get", "list", "watch"]

---
# ServiceAccount (pod-lar üçün identity)
apiVersion: v1
kind: ServiceAccount
metadata:
  name: laravel-sa
  namespace: production
automountServiceAccountToken: false      # Default false edin

---
# Pod-a ServiceAccount bağlamaq
apiVersion: v1
kind: Pod
metadata:
  name: laravel
spec:
  serviceAccountName: laravel-sa
  automountServiceAccountToken: true
```

## Praktiki Nümunələr (Practical Examples)

### CI/CD-də security scan

```yaml
# .github/workflows/security.yml
name: Security Scan

on:
  push:
    branches: [main]
  pull_request:

jobs:
  container-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build image
        run: docker build -t laravel:${{ github.sha }} .
      
      - name: Trivy vulnerability scan
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: laravel:${{ github.sha }}
          format: 'sarif'
          output: 'trivy-results.sarif'
          severity: 'CRITICAL,HIGH'
          exit-code: '1'
      
      - name: Upload Trivy results
        uses: github/codeql-action/upload-sarif@v2
        with:
          sarif_file: 'trivy-results.sarif'
      
      - name: Hadolint (Dockerfile linter)
        uses: hadolint/hadolint-action@v3
        with:
          dockerfile: Dockerfile
      
      - name: Docker Scout (official)
        uses: docker/scout-action@v1
        with:
          command: cves
          image: laravel:${{ github.sha }}
          only-severities: critical,high
```

### Kyverno Policy (admission control)

```yaml
apiVersion: kyverno.io/v1
kind: ClusterPolicy
metadata:
  name: require-non-root
spec:
  validationFailureAction: enforce
  rules:
  - name: check-runAsNonRoot
    match:
      resources:
        kinds: [Pod]
    validate:
      message: "Containers must not run as root"
      pattern:
        spec:
          securityContext:
            runAsNonRoot: true

---
apiVersion: kyverno.io/v1
kind: ClusterPolicy
metadata:
  name: disallow-latest-tag
spec:
  validationFailureAction: enforce
  rules:
  - name: require-image-tag
    match:
      resources:
        kinds: [Pod]
    validate:
      message: "Images must use specific version tag (not latest)"
      pattern:
        spec:
          containers:
          - image: "!*:latest"
```

## PHP/Laravel ilə İstifadə

### Laravel production-ready Docker

```dockerfile
FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    zip unzip \
    libpng-dev libzip-dev \
    && docker-php-ext-install pdo_mysql opcache zip gd bcmath \
    && docker-php-ext-configure opcache --enable-opcache

# OPcache production config
RUN echo "opcache.enable=1\n\
opcache.memory_consumption=256\n\
opcache.max_accelerated_files=20000\n\
opcache.validate_timestamps=0\n\
opcache.save_comments=1\n\
opcache.interned_strings_buffer=16" > /usr/local/etc/php/conf.d/opcache.ini

# Non-root user
RUN addgroup -g 1000 laravel && adduser -u 1000 -G laravel -D laravel

FROM base AS builder
WORKDIR /var/www/html
COPY --chown=laravel:laravel composer.* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts
COPY --chown=laravel:laravel . .
RUN composer dump-autoload --optimize && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

FROM base AS runtime
WORKDIR /var/www/html
COPY --from=builder --chown=laravel:laravel /var/www/html /var/www/html

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Writable directories (minimize)
RUN chmod -R 755 storage bootstrap/cache && \
    chown -R laravel:laravel storage bootstrap/cache

USER laravel
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD wget -qO- http://localhost:8080/health || exit 1
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### Laravel Kubernetes security manifest

```yaml
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
      serviceAccountName: laravel-sa
      automountServiceAccountToken: false
      
      securityContext:
        runAsNonRoot: true
        runAsUser: 1000
        fsGroup: 1000
        seccompProfile:
          type: RuntimeDefault
      
      containers:
      - name: laravel
        image: laravel:2.0@sha256:abc...
        imagePullPolicy: IfNotPresent
        
        securityContext:
          allowPrivilegeEscalation: false
          readOnlyRootFilesystem: true
          capabilities:
            drop: ["ALL"]
        
        env:
        - name: APP_KEY
          valueFrom:
            secretKeyRef:
              name: laravel-secrets
              key: app-key
        
        resources:
          requests:
            memory: "256Mi"
            cpu: "200m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        
        volumeMounts:
        - name: storage
          mountPath: /var/www/html/storage
        - name: cache
          mountPath: /var/www/html/bootstrap/cache
        
        livenessProbe:
          httpGet:
            path: /health
            port: 8080
          initialDelaySeconds: 30
        
        readinessProbe:
          httpGet:
            path: /ready
            port: 8080
          initialDelaySeconds: 5
      
      volumes:
      - name: storage
        persistentVolumeClaim:
          claimName: laravel-storage
      - name: cache
        emptyDir:
          medium: Memory
          sizeLimit: 100Mi
```

## Interview Sualları (5-10 Q&A)

**S1: Container və VM arasında təhlükəsizlik fərqi nədir?**
C: VM – güclü isolation (hypervisor ilə ayrı kernel), breach VM-dən qaçmaq çox çətin. Container – host kernel paylaşılır, kernel exploit ilə qaçmaq mümkün (qaçsa bütün host-a zərər). Container daha sürətli, amma isolation daha zəif. gVisor, Kata Containers – container + VM hybrid isolation yaxşılaşdırır. Production-da sensitive workload üçün VM istifadə edilə bilər.

**S2: Dockerfile-da niyə `FROM alpine:latest` və `USER root` pis praktika sayılır?**
C: `latest` tag – reproducibility yoxdur, sabah başqa image ola bilər, supply chain attack riski. `USER root` – container breakout olsa root access verir, privilege escalation, file system damage. Düzgün: spesifik version (`alpine:3.18`) və ya digest (`@sha256:...`), `USER` directive ilə non-root.

**S3: Image scanning niyə vacibdir və nə tapır?**
C: Image-də istifadə olunan kitabxanaların məlum vulnerability-lərini (CVE) aşkar edir – əsas OS paketləri (apt/apk), dil paketləri (npm, composer, pip), hətta application secret-ləri. Trivy, Snyk, Grype istifadə olunur. Build time-da scan edin, registry-də push-dan əvvəl, runtime-da davamlı. CI/CD-də critical vulnerability olsa build fail edin.

**S4: Kubernetes RBAC nədir və niyə lazımdır?**
C: RBAC – role-based access control. Kim hansı resource-a nə edə bilər. Subject (User/Group/ServiceAccount) + Role (rules) + RoleBinding. Namespace-specific (Role) və cluster-wide (ClusterRole). Least privilege prinsipi: hər pod/user yalnız lazım olan icazəni alsın. Default "admin" istifadə etməyin.

**S5: Pod Security Standards nədir?**
C: K8s 1.25+ built-in security policy. 3 səviyyə: Privileged (no restrictions), Baseline (common restrictions), Restricted (hardened). Namespace-ə label qoyaraq aktivləşir (`pod-security.kubernetes.io/enforce: restricted`). Pod Security Policy (PSP) deprecated, əvəzi budur. OPA Gatekeeper, Kyverno ilə genişlədilə bilər.

**S6: Network Policy niyə lazımdır?**
C: Default K8s-də hər pod hər podla əlaqə qura bilər (flat network). Network Policy ilə traffic restrict olunur (zero-trust model). Məsələn: frontend yalnız backend ilə, backend yalnız DB ilə. Lateral movement-ə qarşı müdafiə – bir pod kompromise olsa, digərlərinə yayılması çətinləşir. Calico, Cilium CNI dəstəkləyir.

**S7: Docker capabilities nədir və niyə drop edilməlidir?**
C: Linux capabilities – root permission-ları kiçik hissələrə bölür (NET_BIND_SERVICE, SYS_ADMIN və s.). Default Docker 14 capability verir (çoxu lazımsız). `--cap-drop ALL` ilə hamısı drop, lazım olan `--cap-add NET_BIND_SERVICE`. Attack surface azalır – container breakout olsa da daha az zərər.

**S8: readOnlyRootFilesystem niyə tövsiyə olunur?**
C: Container filesystem-i readonly edir – malware/attacker fayl yaza bilmir. Yalnız lazım olan qovluqlar (tmp, cache) emptyDir/tmpfs mount edilir. Persistent data volume-də saxlanır. Laravel storage/logs üçün volume mount edin. Immutable container principle ilə uyğundur.

**S9: Image signing (Notary, Cosign) niyə lazımdır?**
C: Supply chain security – image-in mənbəsini doğrulayır. Image push olanda imzalanır, pull olanda verify olunur. Man-in-the-middle attack, malicious image substitution qarşısını alır. Sigstore/Cosign open source, free. Kubernetes admission controller (Kyverno, OPA) ilə yalnız imzalı image-lərə icazə verin.

**S10: Falco və runtime security nə üçün lazımdır?**
C: Image scan statik analiz, runtime security isə işləyən container-in davranışını izləyir. Anomaly detection: unexpected shell spawn, sensitive file access, network connection to suspicious IP. Falco – eBPF əsaslı, syscall-ları izləyir. Rule-based alerting. Zero-day attack-ləri aşkar etmək üçün vacibdir.

## Best Practices

1. **Non-root istifadəçi**: Dockerfile-da `USER` directive, rootless container işlədin.
2. **Minimum base image**: Alpine, distroless istifadə edin (hücum səthi kiçik).
3. **Specific image tags**: `latest` əvəzinə `1.2.3` və ya digest (`@sha256:...`).
4. **Multi-stage builds**: Build tools production image-də olmasın.
5. **.dockerignore**: Secret və lazımsız fayllar image-ə düşməsin.
6. **Image scanning**: CI/CD-də Trivy, critical/high vulnerability build fail etsin.
7. **Image signing**: Cosign ilə imzala, Kyverno ilə verify et.
8. **Read-only filesystem**: `--read-only` və ya `readOnlyRootFilesystem: true`.
9. **Drop capabilities**: Default `cap-drop ALL`, yalnız lazım olanları əlavə edin.
10. **Resource limits**: CPU, memory, PID limits – DoS attack qarşısını alır.
11. **Secrets management**: Environment variable-də plaintext yox, Secret resource və ya Vault.
12. **Network policies**: Default deny, explicit allow (zero-trust).
13. **RBAC least privilege**: Hər user/SA yalnız lazım olan icazə.
14. **Pod Security Standards**: `restricted` namespace-lərdə.
15. **Runtime security**: Falco ilə anomaly detection, audit logs.
16. **Regular patching**: Base image-ləri rebuild edin (həftəlik), dependencies update.
17. **CIS benchmarks**: Docker Bench, kube-bench ilə yoxlayın.
