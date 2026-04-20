# Container Runtimes (Konteyner Runtime-ları)

## Nədir? (What is it?)

**Container runtime** — container-lərin həyat dövriyyəsini idarə edən (yaratmaq, işlətmək, dayandırmaq) və Linux kernel feature-larına (namespaces, cgroups) interfeys təmin edən aşağı səviyyəli komponentdir.

Docker əslində bir orkestrator-dur. Real iş ona daxil olan runtime-lar tərəfindən görülür.

## Əsas Konseptlər

### 1. Container Runtime Hierarchy

```
┌─────────────────────────────────────────┐
│         Docker CLI / Kubernetes          │ ← High-level user interface
├─────────────────────────────────────────┤
│  dockerd / kubelet                       │ ← Container engine
├─────────────────────────────────────────┤
│  containerd / CRI-O                      │ ← High-level runtime (CRI)
├─────────────────────────────────────────┤
│  runc / crun / gVisor / kata             │ ← Low-level runtime (OCI)
├─────────────────────────────────────────┤
│  Linux Kernel (namespaces, cgroups)      │ ← OS layer
└─────────────────────────────────────────┘
```

### 2. OCI Standartları

**OCI (Open Container Initiative)** — container formatları və runtime davranışı üçün açıq standartdır:

- **OCI Runtime Spec** — container-in necə işlədiləcəyini təyin edir
- **OCI Image Spec** — image strukturunu təyin edir  
- **OCI Distribution Spec** — image registry protokolunu təyin edir

Bütün müasir runtime-lar OCI-uyğundur.

### 3. CRI (Container Runtime Interface)

Kubernetes kubelet-in runtime ilə əlaqə qurmaq üçün standart interfeysi. `dockershim` Kubernetes 1.24-dən çıxarıldı, indi CRI-uyğun runtime-lar işlədilir (containerd, CRI-O).

## Praktiki Nümunələr

### containerd

Docker 1.11-dən containerd-i istifadə edir. Kubernetes-də ən populyar CRI runtime-dır.

```bash
# containerd-in birbaşa istifadəsi
ctr image pull docker.io/library/nginx:latest
ctr run --rm -t docker.io/library/nginx:latest nginx1

# Namespace-lər
ctr --namespace k8s.io containers list
ctr --namespace default images list

# nerdctl — Docker-ə bənzər CLI
nerdctl pull nginx
nerdctl run -d --name web nginx
nerdctl ps
```

**Xüsusiyyətləri:**
- Yüngül və performanslı
- Plug-in arxitekturası (snapshotter, content store)
- Rich CRI dəstəyi
- BuildKit-i birbaşa daxil edə bilir

### runc

**runc** — Linux konteyner yaratmaq üçün OCI reference runtime-dır. Docker, containerd və CRI-O-nun arxasında işləyir.

```bash
# OCI bundle yarat
mkdir mycontainer && cd mycontainer
mkdir rootfs
docker export $(docker create alpine) | tar -C rootfs -xf -

# config.json yarat
runc spec

# Konteynerı birbaşa runc ilə işlət
sudo runc run mycontainer
```

`config.json` nümunəsi:
```json
{
  "ociVersion": "1.1.0",
  "process": {
    "terminal": true,
    "args": ["/bin/sh"],
    "env": ["PATH=/usr/local/sbin:/usr/local/bin:/usr/bin"],
    "cwd": "/"
  },
  "root": {"path": "rootfs"},
  "linux": {
    "namespaces": [
      {"type": "pid"},
      {"type": "network"},
      {"type": "mount"},
      {"type": "uts"},
      {"type": "ipc"}
    ]
  }
}
```

### CRI-O

Red Hat tərəfindən Kubernetes üçün yaradılmış CRI runtime-dir.

```bash
# CRI-O service
systemctl status crio

# crictl — CRI üçün CLI
crictl pull nginx
crictl ps
crictl images
crictl logs <container_id>
```

**Xüsusiyyətləri:**
- K8s-a optimize edilib (heç bir əlavə feature yoxdur)
- Podman ilə eyni runtime istifadə edir
- Red Hat OpenShift-in default runtime-ı

### crun

**crun** — C dilində yazılmış, runc-dən 2x sürətli OCI runtime-dır.

```bash
# Test
time runc --version
time crun --version

# CRI-O-da crun istifadə etmək
# /etc/crio/crio.conf
default_runtime = "crun"
```

### gVisor (runsc)

Google-un user-space sandbox runtime-ı. Kernel-ə birbaşa sistem çağırışı yerinə öz user-space kernel-i işlədir.

```
Normal container:              gVisor container:
app → kernel                   app → gVisor kernel → host kernel
(sistem çağırışı host-a)       (iki səviyyəli izolyasiya)
```

```bash
# Docker-də gVisor
sudo apt-get install runsc
docker run --runtime=runsc -it alpine
```

**Xüsusiyyətlər:**
- Güclü izolyasiya (syscall-lar sandbox-dan keçir)
- Performans vergisi var (~10-15% yavaş)
- VM-lər qədər izolyasiya, konteyner qədər tez

### Kata Containers

VM-based container runtime. Hər konteyner öz lightweight VM-ində işləyir.

```bash
# Kata quraşdır
sudo apt-get install kata-runtime

# Docker-də
docker run --runtime=kata-runtime -it alpine
```

**Arxitektura:**
```
┌────────────────────────────────┐
│      Host Kernel               │
├────────────────────────────────┤
│   Lightweight VM (QEMU)        │
│   ┌──────────────────────┐    │
│   │   Guest Kernel        │    │
│   ├──────────────────────┤    │
│   │   Container           │    │
│   └──────────────────────┘    │
└────────────────────────────────┘
```

Multi-tenant cloud environment üçün yaxşıdır (kernel exploit-dən qorunma).

## PHP/Laravel ilə İstifadə

### containerd ilə Laravel Deploy

Kubernetes cluster containerd istifadə edirsə:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel
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
      runtimeClassName: runc  # və ya: kata-qemu, gvisor
      containers:
        - name: laravel
          image: myregistry/laravel:1.0.0
          ports:
            - containerPort: 9000
```

### RuntimeClass ilə Sandbox Laravel

Multi-tenant Laravel app — hər tenant üçün güclü izolyasiya:

```yaml
# RuntimeClass təyinatı
apiVersion: node.k8s.io/v1
kind: RuntimeClass
metadata:
  name: gvisor
handler: runsc

---
# Tenant üçün izolə edilmiş pod
apiVersion: v1
kind: Pod
metadata:
  name: tenant-a-laravel
spec:
  runtimeClassName: gvisor
  containers:
    - name: laravel
      image: myregistry/laravel:1.0.0
```

### Docker Desktop Runtime Dəyişdirmək

```json
// ~/.docker/daemon.json
{
  "runtimes": {
    "runsc": {
      "path": "/usr/local/bin/runsc"
    },
    "kata": {
      "path": "/usr/bin/kata-runtime"
    }
  },
  "default-runtime": "runc"
}
```

```bash
# Sandbox runtime ilə test
docker run --runtime=runsc -it php:8.3-cli bash
# Sensitive code-u burada test edə bilərsən
```

## Interview Sualları

**1. Docker ilə runc fərqi?**
Docker — high-level engine (CLI, daemon, build, registry). runc — low-level OCI runtime, real container-ı kernel-də yaradan alət. Docker → containerd → runc chain-i var.

**2. containerd və CRI-O arasında fərq?**
- **containerd**: Docker-in altında, Kubernetes-də populyar, daha çox feature
- **CRI-O**: Kubernetes üçün specifik, minimal, Red Hat/OpenShift-də default

Hər ikisi CRI-uyğundur və runc istifadə edir.

**3. OCI nədir?**
Open Container Initiative — container standartları təşkilatıdır. Image format (OCI Image), runtime spec (OCI Runtime), registry (OCI Distribution) standartları var.

**4. Dockershim niyə çıxarıldı?**
Kubernetes 1.24-də `dockershim` (kubelet-dən Docker-a körpü) çıxarıldı. Səbəb: Docker CRI-uyğun deyildi, ayrıca shim lazım idi. İndi birbaşa containerd/CRI-O istifadə olunur. Docker image-ləri hələ də işləyir.

**5. gVisor niyə istifadə olunur?**
Güclü sandbox izolyasiyası üçün. Container-in syscall-ları birbaşa kernel-ə getmir — user-space kernel vasitəsilə. Untrusted code işlədəndə (PaaS, online compiler) faydalıdır.

**6. Kata Containers nə vaxt istifadə etmək lazımdır?**
Multi-tenant cloud (AWS Fargate, oxşarı), kernel-level izolyasiya tələb olunduqda. Container performansı VM-in təhlükəsizliyi ilə birləşir.

**7. crun niyə runc-dən sürətlidir?**
runc Go-da yazılıb (GC overhead). crun C-də yazılıb, memory footprint 1/3, startup time 2x sürətli. cgroup v2 üçün də optimize edilib.

**8. Runtime seçərkən nə nəzərə alınır?**
- **Performance**: crun > runc > kata > gvisor
- **Security**: kata > gvisor > runc
- **Ecosystem**: containerd + runc ən geniş istifadə olunur
- **K8s uyğunluq**: CRI dəstəyi vacibdir

## Best Practices

1. **Kubernetes-də containerd istifadə et** — ən yaxşı dəstək və performans
2. **RuntimeClass ilə workload-ları ayır** — untrusted workload-lar üçün gVisor/Kata
3. **crictl istifadə et** — CRI səviyyəsində debug üçün
4. **OCI spec-ə uyğun qal** — image və runtime standartlarına
5. **nerdctl** Docker-bənzər CLI lazım olsa — containerd üçün
6. **Runtime CVE-lərini izlə** — runc-də container escape tarixçəsi var
7. **Multi-tenant üçün sandbox** — gVisor ya Kata mütləq
8. **Runtime version yeniliyi** — xüsusilə təhlükəsizlik yamaları üçün
9. **`runc --version`** yoxla — köhnə runc-lərdə CVE var
10. **Hyperscaler-lərə diqqət** — AWS Fargate microVM, Google Cloud Run gVisor işlədir
