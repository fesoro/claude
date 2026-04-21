# Docker & Kubernetes Interview Hazırlığı

Bu qovluq Docker və Kubernetes mövzularını əhatə edən ətraflı materiallar təqdim edir.

## Mündəricat

### Docker Əsasları

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [docker-basics.md](01-docker-basics.md) | Docker nədir, konteyner vs VM, əsas əmrlər |
| 02 | [dockerfile.md](02-dockerfile.md) | Dockerfile instruksiyaları, PHP/Laravel nümunəsi |
| 03 | [multi-stage-builds.md](03-multi-stage-builds.md) | Multi-stage build, image ölçüsünü azaltma |
| 04 | [docker-compose.md](04-docker-compose.md) | docker-compose.yml, Laravel full stack nümunəsi |
| 05 | [volumes-and-storage.md](05-volumes-and-storage.md) | Volume-lər, bind mount, data persistence |
| 06 | [networking.md](06-networking.md) | Şəbəkə tipləri, DNS, port mapping |
| 07 | [health-checks.md](07-health-checks.md) | Health check-lər, restart policy-lər |
| 08 | [docker-security.md](08-docker-security.md) | Təhlükəsizlik, non-root, secrets, scanning |
| 09 | [docker-optimization.md](09-docker-optimization.md) | Layer caching, image optimization, BuildKit |
| 10 | [docker-registry.md](10-docker-registry.md) | Registry-lər, tagging, ECR/GCR/ACR |
| 11 | [docker-logging.md](11-docker-logging.md) | Logging driver-lər, log rotation, mərkəzləşdirilmiş logging |
| 12 | [docker-debugging.md](12-docker-debugging.md) | Debug alətləri, inspect, stats, troubleshooting |

### Kubernetes

| # | Fayl | Mövzu |
|---|------|-------|
| 13 | [kubernetes-basics.md](13-kubernetes-basics.md) | K8s arxitekturası, Pod, Service, Deployment |
| 14 | [kubernetes-services.md](14-kubernetes-services.md) | ClusterIP, NodePort, LoadBalancer, Ingress |
| 15 | [kubernetes-deployments.md](15-kubernetes-deployments.md) | Rolling update, scaling, HPA, deployment strategiyaları |
| 16 | [kubernetes-storage.md](16-kubernetes-storage.md) | PV, PVC, StorageClass, stateful app-lər |
| 17 | [kubernetes-configmaps-secrets.md](17-kubernetes-configmaps-secrets.md) | ConfigMap, Secret, Vault ilə inteqrasiya |
| 18 | [kubernetes-helm.md](18-kubernetes-helm.md) | Helm chart-lar, values.yaml, Laravel Helm chart |

### Orkestrasiya

| # | Fayl | Mövzu |
|---|------|-------|
| 19 | [docker-swarm.md](19-docker-swarm.md) | Swarm mode, service-lər, stack-lər |
| 20 | [container-orchestration-patterns.md](20-container-orchestration-patterns.md) | Sidecar, ambassador, init container pattern-ləri |

### Advanced (Əlavə Mövzular)

| # | Fayl | Mövzu |
|---|------|-------|
| 21 | [laravel-sail-deep-dive.md](21-laravel-sail-deep-dive.md) | Laravel Sail arxitekturası, custom servislər, XDebug, publish |
| 22 | [distroless-rootless-docker.md](22-distroless-rootless-docker.md) | Distroless image-lər, rootless Docker, security context |
| 23 | [buildkit-advanced.md](23-buildkit-advanced.md) | BuildKit advanced, cache/secret/SSH mount, multi-platform, Bake |
| 24 | [container-runtimes.md](24-container-runtimes.md) | containerd, runc, CRI-O, crun, gVisor, Kata, OCI spec |
| 25 | [image-signing.md](25-image-signing.md) | Cosign, Sigstore, SBOM, SLSA, supply chain security |
| 26 | [kubernetes-operators-crds.md](26-kubernetes-operators-crds.md) | CRDs, Operator pattern, Operator SDK, reconcile loop |
| 27 | [argocd-gitops.md](27-argocd-gitops.md) | GitOps, ArgoCD, App of Apps, sync waves, ApplicationSet |
| 28 | [kubernetes-troubleshooting.md](28-kubernetes-troubleshooting.md) | CrashLoopBackOff, OOMKilled, ImagePullBackOff, DNS, debug |

### Kubernetes Advanced

| # | Fayl | Mövzu |
|---|------|-------|
| 29 | [kubernetes-rbac.md](29-kubernetes-rbac.md) | ServiceAccount, Role/ClusterRole, RoleBinding, aggregated roles, OIDC, audit policy |
| 30 | [kubernetes-autoscaling.md](30-kubernetes-autoscaling.md) | HPA (custom/external metrics), VPA, Cluster Autoscaler, Karpenter, KEDA, scale to zero |
| 31 | [kubernetes-networking-cni.md](31-kubernetes-networking-cni.md) | Pod network model, Flannel, Calico (BGP), Cilium (eBPF), AWS VPC CNI, NetworkPolicy, CoreDNS |
| 32 | [service-mesh-comparison.md](32-service-mesh-comparison.md) | Istio (sidecar/ambient), Linkerd, Consul Connect, Kuma, mTLS, traffic splitting |
| 33 | [kubernetes-jobs-cronjobs.md](33-kubernetes-jobs-cronjobs.md) | Job (completions, parallelism, Indexed), CronJob, Laravel scheduler vs K8s |
| 34 | [kubernetes-observability.md](34-kubernetes-observability.md) | Prometheus/ServiceMonitor, Grafana, Loki, Tempo/Jaeger, OpenTelemetry, SLO burn rate |

### Senior PHP Developer üçün Praktik Docker

Bu bölmə Laravel/PHP layihələrinizi rahat dockerize etmək üçün praktik, "kopyala-istifadə et" səviyyəsində hazırlanıb. Hər fayl production-ready config verir, tipik səhvləri (gotchas) göstərir.

| # | Fayl | Mövzu |
|---|------|-------|
| 35 | [php-laravel-production-dockerfile.md](35-php-laravel-production-dockerfile.md) | Tam production Laravel Dockerfile — multi-stage, Alpine, OpCache, non-root, tini |
| 36 | [php-fpm-tuning-docker.md](36-php-fpm-tuning-docker.md) | FPM pool (`pm.max_children` hesablama), OpCache + JIT config, FPM status monitoring |
| 37 | [nginx-php-fpm-container-setup.md](37-nginx-php-fpm-container-setup.md) | Sidecar vs supervisord, Unix socket vs TCP, Nginx config, FastCGI cache, HTTPS |
| 38 | [docker-entrypoint-scripts-laravel.md](38-docker-entrypoint-scripts-laravel.md) | Entrypoint pattern, wait-for-db, migration strategies, SIGTERM / PID 1 / tini |
| 39 | [laravel-queue-workers-scheduler-docker.md](39-laravel-queue-workers-scheduler-docker.md) | Queue worker, Horizon, Scheduler (CronJob), KEDA autoscaling, graceful shutdown |
| 40 | [composer-in-docker-best-practices.md](40-composer-in-docker-best-practices.md) | Vendor stage, layer caching, `--no-dev`, BuildKit cache mount, private packages |
| 41 | [dev-vs-prod-docker-setup.md](41-dev-vs-prod-docker-setup.md) | `docker-compose.override.yml`, profiles, multi-stage target, Xdebug dev-only |
| 42 | [docker-file-permissions-php.md](42-docker-file-permissions-php.md) | www-data UID/GID problemi, bind mount, build-time UID match, fsGroup K8s |
| 43 | [docker-env-secrets-laravel.md](43-docker-env-secrets-laravel.md) | `.env`, `config:cache` gotcha, `APP_KEY` rotation, Docker Secret, K8s Secret, Vault |
| 44 | [dockerize-existing-laravel-step-by-step.md](44-dockerize-existing-laravel-step-by-step.md) | Addım-addım mövcud Laravel layihəni dockerize — fayl strukturu, Makefile, VS Code |
| 45 | [frankenphp-roadrunner-octane-docker.md](45-frankenphp-roadrunner-octane-docker.md) | Application server-lər — FrankenPHP, Swoole, RoadRunner, Octane, worker mode riskləri |
| 46 | [docker-ci-cd-github-actions-php.md](46-docker-ci-cd-github-actions-php.md) | GitHub Actions workflow, cache, multi-arch, Trivy, Cosign, K8s deploy, ArgoCD |

## Necə İstifadə Etməli

1. Faylları sıra ilə oxuyun (01-dən 46-ya qədər)
2. Hər fayldakı praktiki nümunələri öz maşınınızda sınayın
3. Interview suallarını cavablandırmağa çalışın
4. Best practice-ləri yadda saxlayın

### Mövcud Laravel layihəni dockerize etmək istəyirsinizsə

Bu sıra ilə oxuyun:
1. **01-04** — Docker, Dockerfile, Compose əsasları
2. **44** — Addım-addım mövcud layihəni dockerize et (praktik gid)
3. **35** — Production Dockerfile template
4. **42** — Fayl icazələri problemi (ən çox rast gələn)
5. **41** — Dev vs Prod setup (override.yml, Xdebug)
6. **40** — Composer best practices
7. **38** — Entrypoint script-lər
8. **43** — Env/Secrets management
9. **39** — Queue worker və scheduler
10. **36-37** — PHP-FPM və Nginx tuning (performans üçün)
11. **46** — CI/CD GitHub Actions
12. **45** — FrankenPHP/Octane (next-level performans)

## Əsas Texnologiyalar

- **Docker Engine** - Konteynerləşdirmə platforması
- **Docker Compose** - Multi-container orkestrasi
- **Kubernetes (K8s)** - Konteyner orkestrasiyası
- **Helm** - K8s paket meneceri
- **Docker Swarm** - Docker-un öz orkestrasiya həlli
