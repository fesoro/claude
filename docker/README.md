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

## Necə İstifadə Etməli

1. Faylları sıra ilə oxuyun (01-dən 21-ə qədər)
2. Hər fayldakı praktiki nümunələri öz maşınınızda sınayın
3. Interview suallarını cavablandırmağa çalışın
4. Best practice-ləri yadda saxlayın

## Əsas Texnologiyalar

- **Docker Engine** - Konteynerləşdirmə platforması
- **Docker Compose** - Multi-container orkestrasi
- **Kubernetes (K8s)** - Konteyner orkestrasiyası
- **Helm** - K8s paket meneceri
- **Docker Swarm** - Docker-un öz orkestrasiya həlli
