# eBPF (Extended Berkeley Packet Filter)

## Nədir? (What is it?)

eBPF – Linux kernel-inə user-space kodu **təhlükəsiz və performanslı** şəkildə "inject" etməyə imkan verən inqilabi texnologiyadır. Ənənəvi yanaşmada kernel funksionallığı genişlətmək üçün **kernel module** yazmaq lazım idi – təhlükəli (bütün kernel üçün risk), hər kernel versiyasına uyğunlaşdırma tələb edən. eBPF bunu dəyişdi: VM-lə (in-kernel JIT compiler) yüklənən kiçik kod parçaları kernel hadisələrinə (syscall, network packet, function entry/exit, tracepoint) qoşulur, verification stage-dən keçir (loop yox, memory safe), sonra native sürətlə icra olunur. **Kernel-i yenidən compile etmədən** həm observability (Pixie, Parca, Cilium Hubble), həm networking (Cilium, Calico), həm security (Falco, Tetragon) mümkündür. **BCC, libbpf-CO-RE, Aya (Rust), cilium/ebpf (Go)** – eBPF development framework-ləri. Linux kernel 4.x-dən etibarən geniş yayılıb, 2023-də eBPF Foundation yaradıldı.

## Əsas Konseptlər (Key Concepts)

### eBPF arxitekturası

```
User space
   ↓ (BPF syscall)
Verifier → Safety check (loop yox, memory access safe)
   ↓
JIT Compiler → Native machine code
   ↓
Attach to hook point:
- Tracepoints    (kernel event-lər)
- kprobes        (kernel function)
- uprobes        (user function)
- XDP            (ən erkən network packet – driver səviyyəsi)
- TC             (Traffic Control)
- cgroup         (cgroup hook-lar)
- socket filters
- LSM hooks      (security)
- perf_event
```

### eBPF hook point-ləri

```
XDP (eXpress Data Path):
- NIC driver səviyyəsində, ən erkən
- Terabit-level DDoS filtering
- XDP_PASS, XDP_DROP, XDP_TX, XDP_REDIRECT

TC (Traffic Control):
- Ingress/egress, XDP-dən sonra
- Load balancing, policy

kprobe/kretprobe:
- İstənilən kernel funksiyasına qoş
- openat(), execve() monitor

uprobe/uretprobe:
- User-space funksiyalarına (SSL_read, malloc)
- Language-agnostic observability

Tracepoint:
- Kernel-də stabil event-lər (syscall_enter_open)
- kprobe-dan daha stabil, versiyalar arası dəyişmir

Perf event:
- CPU cycle, cache miss – performance profiling

LSM hook:
- Linux Security Module – fine-grained access control
```

### BPF Maps (data structure)

```
Hash map        – key-value
Array           – fixed size
LRU hash        – köhnə key-lər silinir
Per-CPU         – hər CPU-da kopya
Ring buffer     – kernel → user space event streaming
Stack trace     – profiling üçün
Socket map      – fast sockmap redirect
```

### eBPF ekosistemi

```
Networking:
- Cilium       – K8s CNI, service mesh, network policy
- Calico eBPF dataplane
- Katran       – Facebook L4 LB

Observability:
- Pixie        – K8s APM, no-instrumentation
- Parca        – continuous profiling
- bpftrace     – awk-like tracing tool
- BCC          – Python/Lua bindings + tool suite
- Grafana Beyla – auto-instrumentation

Security:
- Falco        – runtime security, suspicious syscall
- Tetragon     – Cilium-dan, policy enforcement
- Tracee       – Aqua Security

Profiling:
- Parca Agent, Polar Signals
- continuous-profiling, CPU flamegraph
```

### Cilium

```
CNI (Container Network Interface) + Service Mesh + Observability
- Bütün networking eBPF ilə, iptables yox (bəzi mod-larda)
- Pod-to-pod: direct routing, overlay (VXLAN)
- Service load balancing: eBPF-də L4, L7 Envoy
- Network Policy: L3/L4/L7 (HTTP, gRPC, Kafka, DNS)
- Hubble         – flow observability (UI, CLI, metrics)
- Cluster Mesh   – multi-cluster
- Egress Gateway – static egress IP
```

### Tetragon (Security)

```
Cilium-dan runtime security:
- Process execution monitoring (execve, clone)
- File access (open, read, write)
- Network connection (TCP, UDP)
- Policy-based enforcement (kill process, deny syscall)
- Kubernetes-native (CRD, namespace filter)
```

## Praktiki Nümunələr

### bpftrace one-liner-lər (quick debugging)

```bash
# Bütün exec-ləri track
sudo bpftrace -e 'tracepoint:syscalls:sys_enter_execve { printf("%s %s\n", comm, str(args->filename)); }'

# PHP-FPM-də open() çağırılışları
sudo bpftrace -e 'tracepoint:syscalls:sys_enter_openat /comm == "php-fpm"/ { printf("%s\n", str(args->filename)); }'

# TCP connect latency histogramı
sudo bpftrace -e '
kprobe:tcp_v4_connect { @start[tid] = nsecs; }
kretprobe:tcp_v4_connect /@start[tid]/ {
    @lat = hist((nsecs - @start[tid]) / 1000);
    delete(@start[tid]);
}'

# Disk I/O latency per process
sudo bpftrace -e '
kprobe:blk_account_io_start { @start[arg0] = nsecs; }
kprobe:blk_account_io_done /@start[arg0]/ {
    $lat = (nsecs - @start[arg0]) / 1000000;
    @iolat[comm] = hist($lat);
    delete(@start[arg0]);
}'
```

### Cilium – Kubernetes Network Policy

```yaml
# cilium-network-policy.yaml
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
    # 1) Frontend pod-lar HTTP GET/POST göndərə bilər
    - fromEndpoints:
        - matchLabels:
            app: laravel-frontend
      toPorts:
        - ports:
            - port: "80"
              protocol: TCP
          rules:
            http:
              - method: GET
                path: "/api/.*"
              - method: POST
                path: "/api/orders"
                headers:
                  - 'Content-Type: application/json'

    # 2) Ingress controller-dən
    - fromEndpoints:
        - matchLabels:
            app: nginx-ingress
      toPorts:
        - ports: [{port: "80", protocol: TCP}]

  egress:
    # 1) DNS
    - toEndpoints:
        - matchLabels:
            k8s-app: kube-dns
      toPorts:
        - ports: [{port: "53", protocol: UDP}]
          rules:
            dns:
              - matchPattern: "*"

    # 2) MySQL (external)
    - toFQDNs:
        - matchName: "prod-db.example.com"
      toPorts:
        - ports: [{port: "3306", protocol: TCP}]

    # 3) Redis
    - toEndpoints:
        - matchLabels:
            app: redis
      toPorts:
        - ports: [{port: "6379", protocol: TCP}]

    # 4) Stripe API
    - toFQDNs:
        - matchName: "api.stripe.com"
      toPorts:
        - ports: [{port: "443", protocol: TCP}]
          rules:
            http:
              - method: POST
                path: "/v1/charges.*"
```

### Hubble (Cilium observability)

```bash
# Pod-to-pod trafiki gör
hubble observe --namespace production --follow

# L7 HTTP request-lər
hubble observe --protocol http --http-status 500

# Deny olunan trafik (policy violation)
hubble observe --verdict DROPPED --since 1h

# Konkret pod üçün
hubble observe --pod production/laravel-api-0 --follow
```

### Falco Rule (runtime security)

```yaml
# falco-rules.yaml
- rule: Suspicious shell in PHP container
  desc: PHP container-də shell spawn (webshell?)
  condition: >
    spawned_process and
    container and
    container.image.repository contains "laravel" and
    proc.name in (bash, sh, zsh, ash)
  output: >
    Shell spawned in PHP container
    (user=%user.name container=%container.name
     image=%container.image.repository cmdline=%proc.cmdline)
  priority: WARNING
  tags: [container, shell, mitre_execution]

- rule: Sensitive file read
  desc: /etc/shadow, ssh keys oxunması
  condition: >
    open_read and
    (fd.name startswith /etc/shadow or
     fd.name startswith /root/.ssh/ or
     fd.name contains /.env)
  output: >
    Sensitive file read (user=%user.name command=%proc.cmdline file=%fd.name)
  priority: CRITICAL
  tags: [filesystem, mitre_credential_access]
```

### Tetragon Policy (runtime enforcement)

```yaml
# tetragon-policy.yaml
apiVersion: cilium.io/v1alpha1
kind: TracingPolicy
metadata:
  name: block-suspicious-syscalls
spec:
  kprobes:
    - call: "sys_execve"
      syscall: true
      args:
        - index: 0
          type: "string"
      selectors:
        # Laravel container-də əgər /bin/bash spawn olursa – kill
        - matchBinaries:
            - operator: "In"
              values:
                - "/bin/bash"
                - "/bin/sh"
          matchNamespaces:
            - namespace: "Pid"
              operator: "In"
              values: ["host_ns"]
          matchActions:
            - action: Sigkill
```

### XDP DDoS mitigation

```c
// xdp_ddos.c - kernel eBPF program
#include <linux/bpf.h>
#include <bpf/bpf_helpers.h>
#include <linux/if_ether.h>
#include <linux/ip.h>

struct {
    __uint(type, BPF_MAP_TYPE_LRU_HASH);
    __type(key, __u32);        // IP ünvan
    __type(value, __u64);      // Rate counter
    __uint(max_entries, 100000);
} rate_map SEC(".maps");

#define THRESHOLD 1000   // saniyədə max packet

SEC("xdp")
int xdp_ddos_filter(struct xdp_md *ctx) {
    void *data     = (void *)(long)ctx->data;
    void *data_end = (void *)(long)ctx->data_end;

    struct ethhdr *eth = data;
    if ((void *)(eth + 1) > data_end) return XDP_PASS;
    if (eth->h_proto != __constant_htons(ETH_P_IP)) return XDP_PASS;

    struct iphdr *ip = (void *)(eth + 1);
    if ((void *)(ip + 1) > data_end) return XDP_PASS;

    __u32 src_ip = ip->saddr;
    __u64 *count = bpf_map_lookup_elem(&rate_map, &src_ip);

    if (count) {
        if (*count > THRESHOLD) {
            return XDP_DROP;   // DDoS – bas, geri at
        }
        __sync_fetch_and_add(count, 1);
    } else {
        __u64 init = 1;
        bpf_map_update_elem(&rate_map, &src_ip, &init, BPF_ANY);
    }

    return XDP_PASS;
}

char LICENSE[] SEC("license") = "GPL";
```

### Pixie (K8s observability)

```bash
# Install – heç bir instrumentation yoxdur
px deploy

# PxL (Pixie Language) script – Laravel API latency
px run -f - <<EOF
import px
df = px.DataFrame(table='http_events', start_time='-5m')
df = df[df.ctx['service'] == 'laravel-api']
df.latency_ms = df.latency / 1e6
px.display(df.groupby('req_path').agg(
    p99=('latency_ms', 'quantile99'),
    p50=('latency_ms', 'quantile50'),
    total=('latency_ms', 'count')
))
EOF
```

## PHP/Laravel ilə İstifadə

### PHP-FPM process tracing (bpftrace)

```bash
# PHP-FPM hansı fayllara baxır
sudo bpftrace -e '
tracepoint:syscalls:sys_enter_openat /comm == "php-fpm7.4" || comm == "php-fpm8.3"/ {
    @files[str(args->filename)] = count();
}
interval:s:30 {
    print(@files, 20);
    clear(@files);
    exit();
}'

# Slow Laravel request-ləri
sudo bpftrace -e '
uprobe:/usr/bin/php:zend_execute_ex {
    @start[tid] = nsecs;
}
uretprobe:/usr/bin/php:zend_execute_ex /@start[tid]/ {
    $dur = (nsecs - @start[tid]) / 1000000;
    if ($dur > 500) {
        printf("PHP slow: %d ms pid=%d\n", $dur, pid);
    }
    delete(@start[tid]);
}'
```

### Cilium – Laravel microservice mesh

```yaml
# Laravel API → Payment service – mTLS və L7 filter
apiVersion: cilium.io/v2
kind: CiliumNetworkPolicy
metadata:
  name: laravel-payment-policy
  namespace: production
spec:
  endpointSelector:
    matchLabels:
      app: payment-service
  ingress:
    - fromEndpoints:
        - matchLabels:
            app: laravel-api
      authentication:
        mode: "required"    # mTLS (SPIFFE)
      toPorts:
        - ports: [{port: "8080", protocol: TCP}]
          rules:
            http:
              - method: POST
                path: "/api/v1/charge"
                headers:
                  - 'X-Idempotency-Key: .+'
```

### Laravel tətbiqində Grafana Beyla auto-instrumentation

```yaml
# k8s-beyla.yaml – no code change
apiVersion: apps/v1
kind: DaemonSet
metadata:
  name: grafana-beyla
  namespace: monitoring
spec:
  selector:
    matchLabels: {app: beyla}
  template:
    metadata:
      labels: {app: beyla}
    spec:
      hostPID: true
      containers:
        - name: beyla
          image: grafana/beyla:latest
          securityContext:
            privileged: true
          env:
            - name: BEYLA_OPEN_PORT
              value: "80,443,8000-8999"
            - name: BEYLA_SERVICE_NAMESPACE
              value: "production"
            - name: OTEL_EXPORTER_OTLP_ENDPOINT
              value: "http://tempo:4318"
          volumeMounts:
            - {name: kernel-debug, mountPath: /sys/kernel/debug}
      volumes:
        - name: kernel-debug
          hostPath: {path: /sys/kernel/debug}
```

Bu DaemonSet eBPF ilə Laravel request-lərini avtomatik Tempo-ya göndərir – Laravel kodunda heç bir dəyişiklik lazım deyil.

### Falco + Laravel security

```yaml
# Laravel-specific Falco rules
- rule: Laravel .env read by non-PHP
  desc: .env fayl PHP olmayan process tərəfindən oxunur
  condition: >
    open_read and
    fd.name contains "/var/www" and
    fd.name endswith ".env" and
    not proc.name in (php, php-fpm, php-fpm8.3, artisan)
  output: "Laravel .env oxundu (user=%user.name proc=%proc.name file=%fd.name)"
  priority: CRITICAL

- rule: Artisan production-da tinker
  desc: production container-də php artisan tinker
  condition: >
    spawned_process and
    container and
    proc.cmdline contains "artisan tinker" and
    k8s.ns.name = "production"
  output: "Production-da artisan tinker (user=%user.name pod=%k8s.pod.name)"
  priority: WARNING
```

## Interview Sualları (Q&A)

**S1: eBPF nədir və kernel module-dan necə fərqlənir?**
C: **eBPF** kernel-də user-space tərəfindən yüklənən kiçik proqramlardır, amma kernel module-dan təhlükəsizdir. Fərqlər: (1) **Verifier** – eBPF kod yüklənməzdən əvvəl kernel-də təhlükəsizlik yoxlaması (loop yox, memory safe, max 1M instruction). (2) **JIT compile** – native sürətlə işləyir. (3) **Portable** – kernel versiyaları arası uyğunluq (CO-RE – Compile Once Run Everywhere). (4) **Kernel crash etmir** – verifier-dən keçməyən kod yüklənmir. Kernel module-la bütün kernel crash edə bilər, eBPF etməz. Bu sayədə istehsal mühitində də təhlükəsizdir.

**S2: eBPF tipik istifadə halları nələrdir?**
C: 4 əsas kategoriya: (1) **Networking** – Cilium K8s CNI, Katran L4 LB, XDP DDoS mitigation. (2) **Observability** – Pixie auto-instrumentation, Parca continuous profiling, bpftrace debugging, Grafana Beyla. (3) **Security** – Falco suspicious syscall detection, Tetragon runtime enforcement, Tracee. (4) **Performance analysis** – Brendan Gregg-in BCC tool-ları (execsnoop, opensnoop, tcpconnect), flame graph-lar. Göstərilməyən kod dəyişmədən kernel-i vasitəsilə bütün tətbiqləri görmək.

**S3: Cilium və iptables arasında fərq nədir?**
C: **iptables** ənənəvi Linux firewall – qaydalar linear siyahıdır, hər packet O(n) yoxlanır. 10k Kubernetes service olsa minlərlə iptables rule yaranır, performance çökür. **Cilium (eBPF)** hash table istifadə edir – O(1) lookup. Bundan əlavə Cilium **L7 aware** – HTTP method, path, header-lə policy yaza bilərsən (iptables yalnız L3/L4). `kube-proxy` replacement mode-unda Cilium iptables-sız işləyir, latency aşağı düşür, CPU azalır. Böyük K8s cluster-lərdə fərq kəskindir.

**S4: eBPF verifier nədir və niyə lazımdır?**
C: Verifier – eBPF kod kernel-ə yüklənməzdən əvvəl **statik analiz** edən komponent. Yoxladıqları: (1) **Termination** – sonsuz loop yox (bounded loop 5.3+ dəstəklənir), (2) **Memory safety** – bounds check (array out-of-range yox, null pointer yox), (3) **Type safety** – BPF CO-RE ilə struct field access yoxlanır, (4) **Instruction count** – max 1M instruction (loop unrolling ilə), (5) **Kernel state safety** – uncallable function-lara giriş qadağan. Verifier olmadan bad eBPF kod kernel-i çökdürərdi.

**S5: XDP nədir və niyə vacibdir?**
C: **XDP (eXpress Data Path)** – eBPF-in ən erkən network hook point-u, **NIC driver səviyyəsində** işləyir (hətta packet sk_buff yaranmazdan əvvəl). Üstünlük: **terabit-səviyyəli** filtering mümkündür, çünki kernel stack bypass olunur. Cloudflare, Facebook DDoS mitigation üçün XDP istifadə edir – 10M+ packet/s drop edə bilirlər. Qaytarılan değerlər: XDP_PASS (kernel-ə ötür), XDP_DROP (bas), XDP_TX (geri at), XDP_REDIRECT (başqa NIC-ə). XDP hardware offload dəstəkli NIC-lərdə (Netronome) cari CPU belə istifadə etmir.

**S6: Tetragon və Falco fərqi nədir?**
C: Hər ikisi eBPF əsaslı runtime security alətidir: (1) **Falco** – suspicious behavior **detect** edir (log, alert), process spawn-ı dayandırmır; rule-lar YAML-də, DSL sadə. (2) **Tetragon** (Cilium-dan) – detect + **enforce** – kill process, block syscall real-time. Kubernetes-native (CRD), namespace filtering güclüdür. Falco daha geniş community, Tetragon Cilium ekosistemində K8s-focused. Enterprise-da ikisi birlikdə istifadə oluna bilər – Falco visibility, Tetragon enforcement.

**S7: Pixie niyə "no-instrumentation" deyilir?**
C: Ənənəvi APM (New Relic, Datadog APM) kodunuza SDK import, middleware, agent lazımdır. **Pixie** eBPF ilə **kernel səviyyəsində** HTTP, gRPC, MySQL, DNS trafikini sniffə edir – tətbiq kodu dəyişmir, SDK yoxdur, restart lazım deyil. PHP, Go, Python, Node – dil fərq etmir. Üstünlük: **zero setup cost**. Məhdudiyyət: custom metric, business logic trace etmək mümkün deyil (yalnız network və kernel səviyyə). Klasik APM ilə kombinasiya olunur – Pixie overview, APM business logic.

**S8: CO-RE (Compile Once, Run Everywhere) nədir?**
C: Problem: hər kernel versiyasında struct sahələrinin yeri dəyişə bilər, eBPF binary bir sistemdə işləyir, başqasında yox. **CO-RE** həll edir: eBPF program kernel-dəki **BTF (BPF Type Format)** metadata-sından istifadə edir, run-time-da struct offset-ləri yeniləyir. Bir compile → bütün kernel versiyalarında işləyir. `libbpf` framework bunu avtomatlaşdırır. Əvvəllər BCC hər host-da kernel header-lərlə compile edirdi (yavaş, memory-heavy). Müasir eBPF projects CO-RE istifadə edir.

**S9: eBPF-in tətbiq sahələri məhdud deyilmi?**
C: Əvvəllər məhdud idi (verifier sərt, loop yox), amma kernel 5.x-dən getdikcə genişlənir: (1) **BPF LSM** – kernel security policy, (2) **BPF trampoline** – dinamik function replacement, (3) **BPF CPU scheduler (sched_ext)** – planşlayıcı eBPF-də (kernel 6.12+), (4) **struct_ops** – kernel subsystem-i eBPF ilə implement et. Future trends: "kernel bypass" – network stack-in hissələri eBPF-ə keçir, "eBPF-based runtime" – tamamilə eBPF proqramların ekosistemi. Hələ web server yazmaq olmur, amma çox trendi kernel funksiyaları artıq eBPF-dədir.

**S10: eBPF-i öyrənmək və istifadə etmək üçün ən yaxşı başlanğıc nədir?**
C: (1) **bpftrace** öyrən – awk-like tool, tez nəticə verir (`execsnoop`, `opensnoop`). Brendan Gregg-in kitabı. (2) **BCC tool-larını sına** – `/usr/share/bcc/tools/` altında hazır skriptlər. (3) **Cilium + Hubble** K8s cluster-ində qur – eBPF-in network tərəfini bilavasitə gör. (4) **libbpf-bootstrap** repo – ilk eBPF program yaz (Go üçün `cilium/ebpf`, Rust üçün `aya`). (5) **eBPF Foundation** resursları, LWN məqalələri. İlk aylarda bpftrace + Cilium praktiki başlanğıc üçün kifayətdir.

## Best Practices

1. **bpftrace-dən başla** – sadə debugging üçün lazımsız complex eBPF kod yazma.
2. **BCC/libbpf-CO-RE** portabile eBPF üçün – hər kernel versiyasında işləsin.
3. **Verifier error-larını oxu** – çox texniki amma həlli göstərir.
4. **Cilium prod-da** – `kube-proxy replacement` aktiv et, iptables azalt.
5. **Hubble flow log** – network debugging üçün əvəzsizdir.
6. **Falco + Tetragon** – detection + enforcement ayır.
7. **Resource limit** – eBPF map-ları yaddaş yeyə bilir, `max_entries` diqqətli.
8. **LRU maps** saf hash map əvəzinə – köhnə entry-lər avtomatik silinir.
9. **Per-CPU map** hot path-da lock-free performance.
10. **Ring buffer** event streaming üçün – perf buffer-dan yaxşı.
11. **BTF** kernel-də olsun (`CONFIG_DEBUG_INFO_BTF=y`) – CO-RE üçün.
12. **Privileged container** eBPF agent üçün lazımdır – minimum set-dən istifadə et.
13. **Performance baseline** – eBPF CPU overhead izlə (adətən <3%).
14. **eBPF code review** – verifier-dən keçməyən kod production-a getməsin.
15. **Community-driven tool-lar** – Pixie, Parca, Cilium, Falco – təkərdən kəşf etmə.
