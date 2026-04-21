# Kubernetes Networking və CNI

## Nədir? (What is it?)

**CNI (Container Network Interface)** — Kubernetes-də pod-lara şəbəkə təmin edən plugin interfeysidir. CNCF spec-ə görə, hər CNI plugin pod yarandıqda `ADD`, silindikdə `DEL` çağırılır, IP address və network namespace setup edir.

Kubernetes Network Model-in əsas qaydaları:
1. Hər pod unikal IP-yə sahibdir (NAT olmadan)
2. Hər pod digər bütün pod-larla NAT olmadan danışa bilər
3. Node-dakı agent (kubelet) pod-lara çata bilər
4. Pod öz IP-sini host-un gördüyü kimi görür

## Əsas Konseptlər

### 1. Pod Network Model

```
┌─ Node 1 ─────────────────────┐  ┌─ Node 2 ─────────────────────┐
│  ┌─ Pod A (10.244.1.2) ───┐  │  │  ┌─ Pod C (10.244.2.3) ───┐  │
│  │  container 1           │  │  │  │  container             │  │
│  │  container 2           │  │  │  └────────────────────────┘  │
│  │  (share network ns)    │  │  │                              │
│  └────────────────────────┘  │  │                              │
│  ┌─ Pod B (10.244.1.3) ───┐  │  │                              │
│  │  container             │  │  │                              │
│  └────────────────────────┘  │  │                              │
└──────────────────────────────┘  └──────────────────────────────┘
           │                                   │
           └──────── CNI Overlay/BGP ──────────┘
                    (VXLAN, Geneve, BGP)
```

### 2. CNI Plugin-lərin Müqayisəsi

| CNI | Mode | Performance | Feature-lər |
|-----|------|-------------|-------------|
| **Flannel** | VXLAN overlay | Orta | Sadə, minimal |
| **Calico** | BGP / IPIP / VXLAN / eBPF | Yüksək | NetworkPolicy, BGP peering |
| **Cilium** | eBPF | Ən yüksək | L7 policy, service mesh, observability |
| **Weave Net** | Mesh overlay | Orta | Encryption, simple |
| **AWS VPC CNI** | Native VPC | Yüksək | AWS security groups |
| **Azure CNI** | Native VNet | Yüksək | Azure integration |

## Flannel

### 1. Necə İşləyir (VXLAN)

Flannel hər node-a `/24` subnet verir (məsələn Node 1: `10.244.1.0/24`). Pod-lar arası trafik VXLAN tunel ilə encapsulate olunur və physical network-dən keçir.

```yaml
# kube-flannel-config
apiVersion: v1
kind: ConfigMap
metadata:
  name: kube-flannel-cfg
  namespace: kube-flannel
data:
  net-conf.json: |
    {
      "Network": "10.244.0.0/16",
      "Backend": {
        "Type": "vxlan"
      }
    }
```

### 2. Flannel Backend-lər

| Backend | İzah |
|---------|------|
| `vxlan` | Default, overlay, UDP 8472 |
| `host-gw` | L2 broadcast domain-də işləyir, daha sürətli |
| `wireguard` | Encrypted tunnel |
| `udp` | Debug üçün (slow) |

### 3. Məhdudiyyətlər

- NetworkPolicy dəstəyi YOX (Calico ilə birləşdirmək lazımdır)
- L7 policy YOX
- Observability limitli

## Calico

### 1. BGP Mode

Calico VXLAN əvəzinə BGP ilə route paylaşır. Hər node digər node-lara "mənim pod-larım bu subnet-dədir" deyir:

```
Node 1 (10.244.1.0/24) ──BGP──> Router ──BGP──> Node 2 (10.244.2.0/24)
                    └──────── physical network ─────────┘
```

Overlay yoxdur — packet birbaşa gedir. Performans VXLAN-dan 20-30% yüksəkdir.

### 2. Calico Install

```bash
kubectl apply -f https://raw.githubusercontent.com/projectcalico/calico/v3.27.0/manifests/tigera-operator.yaml
kubectl apply -f https://raw.githubusercontent.com/projectcalico/calico/v3.27.0/manifests/custom-resources.yaml
```

```yaml
apiVersion: operator.tigera.io/v1
kind: Installation
metadata:
  name: default
spec:
  calicoNetwork:
    ipPools:
      - blockSize: 26
        cidr: 10.244.0.0/16
        encapsulation: VXLANCrossSubnet  # eyni subnet-də BGP, fərqli subnet-də VXLAN
        natOutgoing: Enabled
        nodeSelector: all()
```

### 3. BGP Peering (Data Center)

```yaml
apiVersion: projectcalico.org/v3
kind: BGPPeer
metadata:
  name: my-global-peer
spec:
  peerIP: 192.20.30.40
  asNumber: 64567
```

Data center router ilə peer olaraq pod IP-ləri birbaşa external network-ə elan edilir.

### 4. Calico eBPF Mode

kube-proxy əvəzinə eBPF istifadə edir — daha sürətli, daha az CPU:

```yaml
apiVersion: operator.tigera.io/v1
kind: FelixConfiguration
metadata:
  name: default
spec:
  bpfEnabled: true
  bpfKubeProxyIptablesCleanupEnabled: true
```

## Cilium

### 1. eBPF Nə Üçün Fərqlidir

eBPF (extended Berkeley Packet Filter) — Linux kernel-də sandboxed proqramlar işlətməyə imkan verir. Cilium kube-proxy-ni tamamilə əvəz edə bilir:

```
Klassik (iptables):
packet → iptables rule (1000+ rules) → forward

Cilium (eBPF):
packet → eBPF program (direct lookup) → forward
```

### 2. Cilium Install

```bash
cilium install --version 1.14.0 \
    --set kubeProxyReplacement=true \
    --set k8sServiceHost=kubernetes.default.svc.cluster.local \
    --set k8sServicePort=443

cilium hubble enable --ui
```

### 3. Hubble — Network Observability

```bash
# Real-time network flows
hubble observe --namespace production

# HTTP L7 flow
hubble observe --namespace production --protocol http

# Dropped packets
hubble observe --verdict DROPPED
```

UI:
```bash
cilium hubble ui  # browser-də service map
```

### 4. Cilium Service Mesh

Sidecar-siz service mesh (Envoy hər node-da, pod-da deyil):

```yaml
apiVersion: cilium.io/v2
kind: CiliumEnvoyConfig
metadata:
  name: envoy-lb
spec:
  services:
    - name: laravel
      namespace: production
  resources:
    - "@type": type.googleapis.com/envoy.config.listener.v3.Listener
      # Envoy config...
```

### 5. CiliumNetworkPolicy (L7)

Standart NetworkPolicy L3/L4 ilə məhduddur. Cilium L7 (HTTP, gRPC, Kafka) dəstəkləyir:

```yaml
apiVersion: cilium.io/v2
kind: CiliumNetworkPolicy
metadata:
  name: laravel-api-policy
  namespace: production
spec:
  endpointSelector:
    matchLabels:
      app: laravel-api
  ingress:
    - fromEndpoints:
        - matchLabels:
            app: frontend
      toPorts:
        - ports:
            - port: "80"
              protocol: TCP
          rules:
            http:
              - method: "GET"
                path: "/api/users.*"
              - method: "POST"
                path: "/api/login"
                headers:
                  - "Content-Type: application/json"
```

Frontend yalnız `GET /api/users*` və `POST /api/login` çağıra bilər — başqa endpoint bloklanır.

## AWS VPC CNI

### 1. Native VPC Integration

Pod birbaşa VPC IP alır (overlay yox):

```
VPC: 10.10.0.0/16
  Subnet: 10.10.1.0/24 (Node 1)
    Node IP: 10.10.1.10
    Pod IPs: 10.10.1.20, 10.10.1.21, 10.10.1.22
  Subnet: 10.10.2.0/24 (Node 2)
    Pod IPs: 10.10.2.15, 10.10.2.16
```

### 2. ENI (Elastic Network Interface)

Hər node-a bir neçə ENI bağlanır, hər ENI-də bir neçə secondary IP:

```
Node 1 (m5.large, 3 ENI × 10 IP = 29 pod max)
├── ENI 0 (primary): 10.10.1.10, 10.10.1.20, 10.10.1.21, ...
├── ENI 1: 10.10.1.30, 10.10.1.31, ...
└── ENI 2: 10.10.1.40, 10.10.1.41, ...
```

### 3. Üstünlükləri

- Native VPC performance (overlay yox)
- AWS Security Groups pod-larda
- Service mesh-siz VPC routing
- Pod-lara ALB/NLB birbaşa

### 4. Məhdudiyyətlər

- IP tükənmə riski (VPC subnet-i tükənə bilər)
- Node-da max pod sayı instance tipinə bağlıdır
- AWS-specific

## NetworkPolicy

### 1. Əsas Konsept

NetworkPolicy = firewall pod-lar üçün. Default K8s "deny all" deyil — siz aktiv şəkildə isolation tətbiq etməlisiniz.

### 2. Deny All Ingress

```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny-ingress
  namespace: production
spec:
  podSelector: {}          # bütün pod-lara
  policyTypes:
    - Ingress
```

### 3. Allow From Specific Namespace

```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: allow-from-frontend
  namespace: production
spec:
  podSelector:
    matchLabels:
      app: laravel-api
  policyTypes:
    - Ingress
  ingress:
    - from:
        - namespaceSelector:
            matchLabels:
              name: frontend
          podSelector:
            matchLabels:
              app: nextjs
      ports:
        - protocol: TCP
          port: 80
```

### 4. Egress Policy (DNS + External API)

```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: laravel-egress
  namespace: production
spec:
  podSelector:
    matchLabels:
      app: laravel
  policyTypes:
    - Egress
  egress:
    # DNS (CoreDNS)
    - to:
        - namespaceSelector:
            matchLabels:
              name: kube-system
          podSelector:
            matchLabels:
              k8s-app: kube-dns
      ports:
        - protocol: UDP
          port: 53
    # Internal MySQL
    - to:
        - podSelector:
            matchLabels:
              app: mysql
      ports:
        - protocol: TCP
          port: 3306
    # External HTTPS (payment gateway)
    - to:
        - ipBlock:
            cidr: 0.0.0.0/0
            except:
              - 10.0.0.0/8     # private-lər blok
              - 192.168.0.0/16
      ports:
        - protocol: TCP
          port: 443
```

### 5. Deny All Egress (Strict)

```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny-egress
  namespace: production
spec:
  podSelector: {}
  policyTypes:
    - Egress
  # Empty egress — heç yerə çıxa bilməz
```

Sonra explicit allow policy-lər əlavə edilir.

## kube-proxy

### 1. iptables Mode (Default)

Hər Service üçün iptables rule-ları yaradır:

```
Client → Service IP (10.96.0.10:80)
    ↓ iptables DNAT rule
Pod A (10.244.1.2:8080) | Pod B (10.244.1.3:8080)
    ↑ random selection (probabilistic)
```

Problem: 10000 service olduqda 10000+ iptables rule, yavaş (O(n)).

### 2. IPVS Mode

Linux-un yüksək performanslı load balancer-i:

```bash
# kube-proxy flag
--proxy-mode=ipvs

# Verify
kubectl -n kube-system describe configmap kube-proxy
```

Hash-based lookup — O(1), çox servislə də sürətlidir. Load balancing algoritmləri:
- `rr` (round robin) — default
- `lc` (least connection)
- `dh` (destination hash)
- `sh` (source hash)
- `sed` (shortest expected delay)

### 3. eBPF (Cilium kube-proxy-free)

Artıq yuxarıda baxıldı. Ən yüksək performans.

## DNS (CoreDNS)

### 1. CoreDNS Necə İşləyir

```
Pod → CoreDNS (10.96.0.10) → backend resolution

Service: my-svc.my-ns.svc.cluster.local
       └─ type: ClusterIP/Headless
       └─ returns: Service IP və ya Pod IPs (headless)

Pod: pod-ip-addr.my-ns.pod.cluster.local
```

### 2. CoreDNS Config

```yaml
# ConfigMap
apiVersion: v1
kind: ConfigMap
metadata:
  name: coredns
  namespace: kube-system
data:
  Corefile: |
    .:53 {
        errors
        health {
           lameduck 5s
        }
        ready
        kubernetes cluster.local in-addr.arpa ip6.arpa {
           pods insecure
           fallthrough in-addr.arpa ip6.arpa
           ttl 30
        }
        prometheus :9153
        forward . /etc/resolv.conf
        cache 30
        loop
        reload
        loadbalance
    }
```

### 3. Custom DNS (Stub domain)

Şirkət daxili DNS-ə forward:

```yaml
data:
  Corefile: |
    .:53 {
        # ... default ...
    }
    corp.internal:53 {
        forward . 10.150.0.1 10.150.0.2
    }
```

### 4. Pod DNS Config

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: laravel
spec:
  dnsPolicy: "None"
  dnsConfig:
    nameservers:
      - 1.1.1.1
      - 8.8.8.8
    searches:
      - mycompany.com
    options:
      - name: ndots
        value: "2"       # default 5 — performance üçün azalt
```

### 5. NodeLocal DNSCache

Hər node-da lokal cache — latency azaldır, CoreDNS load-u azaldır:

```bash
kubectl apply -f https://raw.githubusercontent.com/kubernetes/kubernetes/master/cluster/addons/dns/nodelocaldns/nodelocaldns.yaml
```

## PHP/Laravel ilə İstifadə

### Laravel Pod üçün NetworkPolicy Tam Set

```yaml
# 1. Default deny
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny
  namespace: production
spec:
  podSelector: {}
  policyTypes: [Ingress, Egress]
---
# 2. Laravel API-yə ingress
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: laravel-api-ingress
  namespace: production
spec:
  podSelector:
    matchLabels:
      app: laravel-api
  policyTypes: [Ingress]
  ingress:
    - from:
        - podSelector:
            matchLabels:
              app: nginx-ingress
      ports:
        - protocol: TCP
          port: 9000
---
# 3. Laravel → MySQL + Redis + CoreDNS
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: laravel-egress
  namespace: production
spec:
  podSelector:
    matchLabels:
      app: laravel-api
  policyTypes: [Egress]
  egress:
    - to:
        - podSelector:
            matchLabels:
              app: mysql
      ports: [{protocol: TCP, port: 3306}]
    - to:
        - podSelector:
            matchLabels:
              app: redis
      ports: [{protocol: TCP, port: 6379}]
    - to:
        - namespaceSelector:
            matchLabels:
              kubernetes.io/metadata.name: kube-system
          podSelector:
            matchLabels:
              k8s-app: kube-dns
      ports:
        - {protocol: UDP, port: 53}
        - {protocol: TCP, port: 53}
    # Payment gateway
    - to:
        - ipBlock:
            cidr: 35.190.247.0/24  # Stripe
      ports: [{protocol: TCP, port: 443}]
```

### Laravel Connection String

K8s DNS istifadə edərək:

```env
# .env in K8s
DB_HOST=mysql.production.svc.cluster.local
# və ya qısa (eyni namespace-də)
DB_HOST=mysql

REDIS_HOST=redis-master.production.svc.cluster.local
QUEUE_CONNECTION=redis
```

`config/database.php`:
```php
'mysql' => [
    'host' => env('DB_HOST', 'mysql.production.svc.cluster.local'),
    'port' => env('DB_PORT', 3306),
    // ...
],
```

## Interview Sualları

**1. CNI nədir?**
Container Network Interface — pod yarandıqda network setup edən plugin interfeysi. Spec CNCF tərəfindən dəstəklənir. Hər pod üçün `ADD`/`DEL` çağırılır, IP allocate edir, veth pair qurur, routing əlavə edir.

**2. Flannel və Calico fərqi?**
Flannel — sadə, VXLAN overlay, NetworkPolicy YOX. Calico — BGP (overlay olmadan), NetworkPolicy var, eBPF mode-u var. Calico production-da daha çox istifadə olunur.

**3. Cilium-un eBPF-in üstünlüyü?**
Kernel-də native işləyir, iptables-dən sürətli (O(1) vs O(n)), L7 visibility (HTTP, gRPC), Hubble ilə real-time observability, kube-proxy-ni tamamilə əvəz edə bilər.

**4. AWS VPC CNI pod IP-lər necə alır?**
ENI (Elastic Network Interface) node-a bağlanır, hər ENI-də bir neçə secondary IP var. Pod bu IP-lərdən alır — birbaşa VPC IP, overlay yoxdur. Instance tipi maksimum pod sayını məhdudlaşdırır.

**5. NetworkPolicy deny-all necə yazılır?**
`podSelector: {}` və `policyTypes: [Ingress]` (və/və ya Egress) — hər şeyi blok edir, sonra allow rules əlavə olunur. Default olaraq K8s allow-all-dur.

**6. kube-proxy iptables vs IPVS?**
iptables — rule-based, O(n) lookup, çox servislə yavaş. IPVS — hash-based, O(1), daha çox load balancing alqoritmi (rr, lc, sh). 1000+ service-də IPVS mütləqdir.

**7. CoreDNS-də ndots niyə vacibdir?**
`ndots: 5` (default) — FQDN-də 5-dən az dot olarsa search domain-lər əlavə edilir. Hər request 5 DNS query ola bilər! `ndots: 2` və ya FQDN istifadə ilə DNS latency azaldılır.

**8. Service ClusterIP pod-a necə çatır?**
1. DNS resolution: `svc.ns.svc.cluster.local` → ClusterIP
2. Packet Service IP-yə gedir
3. kube-proxy (iptables/IPVS) DNAT edir → Pod IP
4. CNI packet-i düzgün node-a yönləndirir
5. Pod cavab verir

**9. Pod-dan külür-kənar (external) DNS resolve edilmir — niyə?**
Çox vaxt NetworkPolicy egress CoreDNS-ə blok edir. CoreDNS `kube-system` namespace-ə UDP 53 + TCP 53 açmaq lazımdır. Həm də egress ipBlock 0.0.0.0/0 olmalıdır (və ya spesifik DNS IP).

**10. CNI seçərkən hansı faktorları nəzərə almalı?**
1. Cloud integration (AWS VPC CNI AWS-də native)
2. NetworkPolicy lazımdır (Flannel yoxdur)
3. L7 visibility lazımdır (Cilium)
4. Performance requirements (Cilium eBPF ən sürətli)
5. BGP peering (Calico)
6. Simplicity (Flannel)

## Best Practices

1. **NetworkPolicy default deny** — zero trust tətbiq et
2. **Namespace-ə görə isolation** — hər mikroservis öz namespace-ində
3. **CoreDNS HPA** — cluster böyüyəndə DNS bottleneck olmasın
4. **NodeLocal DNSCache** — DNS latency və CoreDNS load azaldır
5. **`ndots: 2`** — pod DNS config-də
6. **IPVS mode** — 1000+ service-də
7. **Cilium Hubble** — network troubleshooting üçün
8. **MTU config** — overlay-lərdə düzgün (1450 vs 1500)
9. **Pod IP tükənməsini izlə** — AWS VPC CNI-də xüsusilə
10. **NetworkPolicy test** — `kubectl exec` ilə `nc`, `curl` ilə yoxla
11. **`hostNetwork: true`** istifadə etmə — security və port conflict
12. **Egress rule-larda DNS unutma** — app DNS resolve edə bilməz yoxsa
