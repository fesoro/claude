# Docker & Kubernetes

PHP/Laravel developer üçün Docker-dən Kubernetes-ə qədər praktik materiallar. Junior-dan Senior-a kimi ardıcıl öyrənmə yolu.

---

## Mündəricat

### Docker Core (01–17) — Junior ⭐ / Middle ⭐⭐

| # | Fayl | Mövzu | Səviyyə |
|---|------|-------|---------|
| 01 | [docker-basics.md](01-docker-basics.md) | Docker nədir, konteyner vs VM, əsas əmrlər | ⭐ Junior |
| 02 | [dockerfile.md](02-dockerfile.md) | Dockerfile instruksiyaları, PHP/Laravel nümunəsi | ⭐ Junior |
| 03 | [dockerignore-build-context.md](03-dockerignore-build-context.md) | .dockerignore, build context optimizasiyası | ⭐⭐ Middle |
| 04 | [multi-stage-builds.md](04-multi-stage-builds.md) | Multi-stage build, image ölçüsünü azaltma | ⭐⭐ Middle |
| 05 | [docker-compose.md](05-docker-compose.md) | docker-compose.yml, Laravel full stack nümunəsi | ⭐ Junior |
| 06 | [database-services-in-docker.md](06-database-services-in-docker.md) | MySQL/Postgres/Redis local dev-də, data persistence | ⭐⭐ Middle |
| 07 | [volumes-and-storage.md](07-volumes-and-storage.md) | Volume-lər, bind mount, data persistence | ⭐⭐ Middle |
| 08 | [networking.md](08-networking.md) | Şəbəkə tipləri, DNS, port mapping | ⭐⭐ Middle |
| 09 | [health-checks.md](09-health-checks.md) | Health check-lər, restart policy-lər | ⭐⭐ Middle |
| 10 | [docker-security.md](10-docker-security.md) | Təhlükəsizlik, non-root, secrets, image scanning | ⭐⭐ Middle |
| 11 | [docker-optimization.md](11-docker-optimization.md) | Layer caching, image optimizasiyası, BuildKit | ⭐⭐ Middle |
| 12 | [docker-registry.md](12-docker-registry.md) | Registry-lər, tagging, ECR/GCR/ACR | ⭐⭐ Middle |
| 13 | [image-tagging-versioning-strategy.md](13-image-tagging-versioning-strategy.md) | Image tagging strategiyası, semver, CI pipeline | ⭐⭐ Middle |
| 14 | [docker-logging.md](14-docker-logging.md) | Logging driver-lər, log rotation, mərkəzləşdirilmiş logging | ⭐⭐ Middle |
| 15 | [docker-debugging.md](15-docker-debugging.md) | Debug alətləri, inspect, stats, troubleshooting | ⭐⭐ Middle |
| 16 | [docker-mac-windows-performance.md](16-docker-mac-windows-performance.md) | Mac/Windows performans problemləri, VirtioFS, bind mount | ⭐⭐ Middle |
| 17 | [docker-anti-patterns-php.md](17-docker-anti-patterns-php.md) | Dockerfile anti-pattern-lər, PHP/Laravel üçün | ⭐⭐ Middle |

---

### Kubernetes (18–25) — Middle ⭐⭐ / Senior ⭐⭐⭐

| # | Fayl | Mövzu | Səviyyə |
|---|------|-------|---------|
| 18 | [kubernetes-basics.md](18-kubernetes-basics.md) | K8s arxitekturası, Pod, Service, Deployment | ⭐⭐ Middle |
| 19 | [kubernetes-services.md](19-kubernetes-services.md) | ClusterIP, NodePort, LoadBalancer, Ingress | ⭐⭐ Middle |
| 20 | [kubernetes-deployments.md](20-kubernetes-deployments.md) | Rolling update, scaling, deployment strategiyaları | ⭐⭐ Middle |
| 21 | [kubernetes-storage.md](21-kubernetes-storage.md) | PV, PVC, StorageClass, stateful app-lər | ⭐⭐ Middle |
| 22 | [kubernetes-configmaps-secrets.md](22-kubernetes-configmaps-secrets.md) | ConfigMap, Secret, Vault inteqrasiyası | ⭐⭐ Middle |
| 23 | [kubernetes-helm.md](23-kubernetes-helm.md) | Helm chart-lar, values.yaml, Laravel Helm chart | ⭐⭐ Middle |
| 24 | [helm-chart-consumer-guide.md](24-helm-chart-consumer-guide.md) | Helm chart istifadəçisi: override, debug, upgrade | ⭐⭐⭐ Senior |
| 25 | [local-kubernetes-for-backend-dev.md](25-local-kubernetes-for-backend-dev.md) | Local K8s: kind, minikube, k3d — dev setup | ⭐⭐⭐ Senior |

---

### Advanced Docker & Patterns (26–29) — Senior ⭐⭐⭐

| # | Fayl | Mövzu | Səviyyə |
|---|------|-------|---------|
| 26 | [container-orchestration-patterns.md](26-container-orchestration-patterns.md) | Sidecar, ambassador, init container pattern-ləri | ⭐⭐⭐ Senior |
| 27 | [laravel-sail-deep-dive.md](27-laravel-sail-deep-dive.md) | Laravel Sail arxitekturası, custom servislər, XDebug | ⭐⭐ Middle |
| 28 | [distroless-rootless-docker.md](28-distroless-rootless-docker.md) | Distroless image-lər, rootless Docker, security context | ⭐⭐⭐ Senior |
| 29 | [buildkit-advanced.md](29-buildkit-advanced.md) | BuildKit advanced: cache/secret/SSH mount, Bake | ⭐⭐⭐ Senior |

---

### Kubernetes Operations (30–34) — Senior ⭐⭐⭐

| # | Fayl | Mövzu | Səviyyə |
|---|------|-------|---------|
| 30 | [kubernetes-troubleshooting.md](30-kubernetes-troubleshooting.md) | CrashLoopBackOff, OOMKilled, ImagePullBackOff, DNS debug | ⭐⭐⭐ Senior |
| 31 | [kubernetes-autoscaling.md](31-kubernetes-autoscaling.md) | HPA, VPA, Cluster Autoscaler, KEDA, scale-to-zero | ⭐⭐⭐ Senior |
| 32 | [kubernetes-jobs-cronjobs.md](32-kubernetes-jobs-cronjobs.md) | Job, CronJob, Laravel scheduler vs K8s CronJob | ⭐⭐⭐ Senior |
| 33 | [kubernetes-observability.md](33-kubernetes-observability.md) | Prometheus, Grafana, Loki, OpenTelemetry, SLO burn rate | ⭐⭐⭐ Senior |
| 34 | [apm-observability-agents-in-docker.md](34-apm-observability-agents-in-docker.md) | Sentry, Datadog, New Relic, OTel agentləri Docker-də | ⭐⭐⭐ Senior |

---

### PHP/Laravel Production Docker (35–51) — Middle ⭐⭐ / Senior ⭐⭐⭐

| # | Fayl | Mövzu | Səviyyə |
|---|------|-------|---------|
| 35 | [php-extensions-in-docker.md](35-php-extensions-in-docker.md) | PHP extension quraşdırma, docker-php-ext-install | ⭐⭐ Middle |
| 36 | [php-laravel-production-dockerfile.md](36-php-laravel-production-dockerfile.md) | Production Laravel Dockerfile — multi-stage, Alpine, tini | ⭐⭐⭐ Senior |
| 37 | [php-fpm-tuning-docker.md](37-php-fpm-tuning-docker.md) | FPM pool, pm.max_children hesablama, OpCache, JIT | ⭐⭐⭐ Senior |
| 38 | [nginx-php-fpm-container-setup.md](38-nginx-php-fpm-container-setup.md) | Sidecar vs supervisord, Unix socket, FastCGI cache, HTTPS | ⭐⭐⭐ Senior |
| 39 | [reverse-proxy-traefik-nginx-docker.md](39-reverse-proxy-traefik-nginx-docker.md) | Traefik vs Nginx reverse proxy Docker Compose-da | ⭐⭐⭐ Senior |
| 40 | [docker-entrypoint-scripts-laravel.md](40-docker-entrypoint-scripts-laravel.md) | Entrypoint pattern, wait-for-db, SIGTERM/PID1/tini | ⭐⭐⭐ Senior |
| 41 | [migrations-in-containers.md](41-migrations-in-containers.md) | Migration strategiyaları: init container, K8s Job, CI | ⭐⭐⭐ Senior |
| 42 | [laravel-queue-workers-scheduler-docker.md](42-laravel-queue-workers-scheduler-docker.md) | Queue worker, Horizon, Scheduler (CronJob), KEDA | ⭐⭐⭐ Senior |
| 43 | [composer-in-docker-best-practices.md](43-composer-in-docker-best-practices.md) | Vendor stage, layer caching, --no-dev, private packages | ⭐⭐ Middle |
| 44 | [dev-vs-prod-docker-setup.md](44-dev-vs-prod-docker-setup.md) | docker-compose.override.yml, profiles, Xdebug dev-only | ⭐⭐ Middle |
| 45 | [docker-file-permissions-php.md](45-docker-file-permissions-php.md) | www-data UID/GID, bind mount, build-time UID, fsGroup | ⭐⭐⭐ Senior |
| 46 | [docker-env-secrets-laravel.md](46-docker-env-secrets-laravel.md) | .env, config:cache gotcha, Docker/K8s Secret, Vault | ⭐⭐ Middle |
| 47 | [testing-php-in-docker.md](47-testing-php-in-docker.md) | PHP/Laravel test-ləri Docker-də, paralel testlər, CI | ⭐⭐ Middle |
| 48 | [resource-limits-sizing-php.md](48-resource-limits-sizing-php.md) | CPU/memory limits, PHP-FPM sizing, K8s requests/limits | ⭐⭐⭐ Senior |
| 49 | [dockerize-existing-laravel-step-by-step.md](49-dockerize-existing-laravel-step-by-step.md) | Mövcud Laravel layihəni dockerize — fayl strukturu, Makefile | ⭐⭐ Middle |
| 50 | [frankenphp-roadrunner-octane-docker.md](50-frankenphp-roadrunner-octane-docker.md) | FrankenPHP, Swoole, RoadRunner, Octane — worker mode | ⭐⭐⭐ Senior |
| 51 | [docker-ci-cd-github-actions-php.md](51-docker-ci-cd-github-actions-php.md) | GitHub Actions, multi-arch, Trivy, Cosign, K8s deploy | ⭐⭐⭐ Senior |

---

### Kubernetes Deep Dive (52) — Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu | Səviyyə |
|---|------|-------|---------|
| 52 | [kubernetes-deep-dive.md](52-kubernetes-deep-dive.md) | K8s dərin: RBAC, network policy, CRD, operator pattern, multi-tenancy | ⭐⭐⭐⭐ Lead |

---

## Oxuma Yolları

### Docker-ə başlamaq (Junior → Middle)
**01 → 02 → 03 → 04 → 05 → 06 → 07 → 08**

### Docker-i dərindən öyrənmək (Middle)
**09 → 10 → 11 → 12 → 13 → 14 → 15 → 16 → 17**

### Mövcud Laravel layihəni dockerize etmək
1. **01–05** — Docker, Dockerfile, Compose əsasları
2. **49** — Addım-addım mövcud layihəni dockerize et
3. **36** — Production Dockerfile template
4. **45** — Fayl icazələri (ən çox rast gələn problem)
5. **44** — Dev vs Prod setup
6. **40** — Entrypoint script-lər
7. **46** — Env/Secrets
8. **42** — Queue worker və scheduler
9. **37–38** — PHP-FPM və Nginx tuning
10. **51** — CI/CD GitHub Actions

### Kubernetes öyrənmək (Middle → Senior)
**18 → 19 → 20 → 21 → 22 → 23 → 24 → 25 → 26 → 30 → 31 → 32 → 33**

### Production hazırlığı (Senior)
**36 → 37 → 38 → 39 → 40 → 41 → 48 → 50 → 51**
