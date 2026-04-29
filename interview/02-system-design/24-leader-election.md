# Leader Election (Architect ⭐⭐⭐⭐⭐)

## İcmal
Leader election, distributed sistemdə bir node-un "koordinator" rolunu üzərinə götürməsi mexanizmidir. Kafka broker, Kubernetes controller, etcd cluster, database primary — hamısı leader election istifadə edir. Bu mövzu system design interview-larında çıxır, çünki yüksək availability, split-brain prevention, və consensus problem — hər distributed sistemin əsas problemidir. Architect-level mühəndis Raft, Paxos, Zookeeper ZAB protokollarını izah edə, split-brain-dən qoruna, fencing token ilə zombie leader-dən qoruna bilməlidir.

## Niyə Vacibdir
Kubernetes cluster-da eyni anda iki controller manager işləsə — duplicate pod yaradılır, state corrupted olur. Kafka-da iki broker leader olsa — log divergence baş verir. Leader election-ın düzgün olmaması production-da ciddi data corruption, duplicate processing, split-brain kimi xatalara gətirib çıxarır. Bu protokolları bilmək — yalnız "etcd istifadə edirəm" demək deyil, fencing, lease, quorum, brain-split prevention anlayışlarını dərindən bilməkdir.

## Əsas Anlayışlar

### 1. Niyə Leader Lazımdır?
```
Koordinasiya problemi:
  Distributed sistem → Hər node müstəqil qərar verə bilər
  Conflict: İki node eyni resource-a yazırsa?
  Duplication: İki node eyni job-u emal edirsə?

Leader rolu:
  Write coordination: Bütün write-lar leader-ə gedir
  Job scheduling: Yalnız leader cron job başladır
  Lock grant: Distributed lock leader tərəfindən verilir
  Config change: Cluster config dəyişikliyi leader idarə edir

Leader election lazım olan sistemlər:
  Database primary election (MySQL, PostgreSQL)
  Kafka partition leader
  Kubernetes controller manager
  Distributed job scheduler (Quartz cluster)
  Service mesh control plane
  Distributed lock service
```

### 2. Split-Brain Problemi
```
Network partition:
  5 node cluster
  Network bölündü: {Node1, Node2, Node3} + {Node4, Node5}

  Her iki tərəf "digər tərəf down" düşünür
  Hər iki tərəf özündən leader seçir
  → Split-Brain: 2 leader eyni anda aktiv!

Nəticələri:
  DB: İki primary → write conflict, data divergence
  Job scheduler: Eyni job iki dəfə işlənir
  Lock service: Eyni lock iki node-da

Quorum (çoxluq qaydası):
  N/2 + 1 node-dan ibarət partitionda leader ola bilər
  5 node cluster: Minimum 3 node quorum lazımdır
  
  Partition 1: {Node1, Node2, Node3} → quorum = 3 → leader ola bilər
  Partition 2: {Node4, Node5} → quorum = 2 < 3 → leader ola bilməz!
  
  Həmişə yalnız 1 partition quorum-a malikdir
  → Split-brain imkansızdır
```

### 3. Raft Consensus Algorithm
```
Raft — Paxos-dan anlaşılması asan alternativ
3 rol: Leader, Follower, Candidate

Normal operation:
  Leader: Heartbeat göndərir (hər 150ms)
  Follower: Heartbeat alırsa → "Leader sağdır"
  Follower: Heartbeat olmasa → Election başladır

Election timeout:
  Hər follower random timeout (150ms-300ms)
  İlk timeout olan → Candidate olur
  
  Candidate:
    Term sayısını artırır (epoch)
    Özünə vote verir
    Bütün node-lara "RequestVote" göndərir

Vote request:
  "Mən Candidate-əm, term=5, last_log_index=100"
  Follower şərtlər:
    1. Bu term-dən əvvəl vote verməyib
    2. Candidate-in log ən azı özü qədər up-to-date
  → Hər iki şərt OK → Vote verir

Quorum vote alındı (3/5):
  Candidate → Leader olur
  Bütün node-lara "I am Leader" heartbeat

Log replication:
  Client → Leader: "Set x=5"
  Leader: Log entry yazır (uncommitted)
  Leader → Followers: AppendEntries(x=5)
  Followers: Log entry yazır
  Majority ACK (3/5) → Leader commits
  Leader → Followers: "Commit x=5"

Split vote:
  İki candidate eyni anda election başladır
  Hər biri 2 vote alır → Heç biri quorum yox
  Timeout → Yenidən election
  Random timeout: Birincisi yenidən başladır
```

### 4. Lease-based Leader Election
```
Lease: Məhdud müddətli "leader" sertifikatı

Implementation:
  Distributed lock (etcd/ZooKeeper) üzərindəki TTL key
  Leader: Lock alır, TTL=10s
  Leader: Hər 3 saniyəə "Mən hələ aktivəm" (renew)
  
  Normal operation:
    Leader her 3s → etcd: "renew lease"
    etcd: TTL reset to 10s

  Leader crash:
    Renew dayanır
    10s sonra TTL expires → Lock azad olur
    Waiting node lock alır → Yeni leader

etcd lease example:
  etcdctl lease grant 10       → lease ID: abcd1234
  etcdctl put /leader/node1 "" --lease=abcd1234
  etcdctl lease keep-alive abcd1234  → Background renewal

  Digər node:
    etcdctl get /leader/node1 → Var → Leader node1-dir
    etcdctl get /leader/node1 → Yoxdur (TTL expired) → Election başladır
    etcdctl put /leader/node2 "" --lease=efgh5678 → Cəhd
```

### 5. Fencing Token (Zombie Leader Problemi)
```
Zombie leader:
  Node1 = Leader (lease aldı)
  Node1 GC pause başlayır (stop-the-world 15 saniyə!)
  Lease expires (10s) → Node2 yeni leader olur
  Node1 GC pause bitmər → Node1 özünü hələ leader bilir
  Node1 + Node2 eyni anda leader → Split-brain!

Fencing Token həlli:
  Lease + monotonically increasing token
  Node1 lease alır → Token = 1
  Node1 lease expires
  Node2 lease alır → Token = 2
  Node1 GC bitir, Token=1 ilə write etməyə cəhd edir

  Storage/downstream: "Ən son gördüyüm token = 2"
  Node1 gəlir Token=1 ilə → REJECTED! "Köhnə token"
  Node2 Token=2 ilə gəlir → ACCEPTED

  Monotonic token = anti-zombie fencing
  etcd revision number: Hər write artan revision
  Kafka epoch: Leader epoch, producer fence

ZooKeeper fencing:
  zxid (transaction ID): Monotonic, ZK-əxas
  New leader-in zxid > Old leader-in zxid
  Old leader "stale" request gönderirse → Rejected

Kubernetes fencing:
  resourceVersion: Her resource update-da artar
  Leader: Lease resourceVersion-ını bilir
  Stale leader update cəhd edir → 409 Conflict
  → Leader özünü "resign" edir
```

### 6. ZooKeeper Ephemeral Nodes
```
ZooKeeper recipe for leader election:

  1. Hər node: /election/node-{zxid} ephemeral znode yaradır
     zxid: Unikal, artan ID
     Ephemeral: Node disconnect olduqda silinir

  2. Ən kiçik ID = Leader
     /election/node-001 → Leader
     /election/node-002 → Follower
     /election/node-003 → Follower

  3. Hər follower öndəkini watch edir:
     node-002 watches node-001
     node-003 watches node-002
     → Herd effect yoxdur (bütün node-lar leader-i watch etmir)

  4. node-001 (leader) disconnect:
     ZooKeeper: Ephemeral node silinir
     node-002: "Öndəkim silindi" notification alır
     node-002: ən kiçik node-mu yoxlayır → YES → Leader olur
     node-003: node-002-ni watch etməyə davam edir

  Herd effect problem (alternative):
    Hər kəs /leader-ı watch edərsə:
    Leader dies → 1000 node eyni anda ZK-ya baxır
    ZK thundering herd
    ZNode chain (hər node öndəkini watch edir) həll edir
```

### 7. Kubernetes Leader Election
```
Kubernetes: ConfigMap/Lease resource-u ilə leader election

Leader:
  1. Lease object yaradır (ya da update edir)
  2. Lease holder: Bu pod-un adı
  3. Lease duration: 15s
  4. Renew: Hər 2s

Controller:
  if leader:
    doWork()
  else:
    wait for lease expiry

Code (Go client-go):
  le, err := leaderelection.NewLeaderElector(leaderelection.LeaderElectionConfig{
    Lock:            &resourcelock.LeaseLock{...},
    LeaseDuration:   15 * time.Second,
    RenewDeadline:   10 * time.Second,
    RetryPeriod:     2 * time.Second,
    Callbacks: leaderelection.LeaderCallbacks{
      OnStartedLeading: func(ctx context.Context) {
        // Leader olduq, işi başlat
        runController(ctx)
      },
      OnStoppedLeading: func() {
        // Leader olduğumuzu itirdik, dayandır
        stopController()
      },
      OnNewLeader: func(identity string) {
        log.Printf("New leader: %s", identity)
      },
    },
  })

  le.Run(ctx)  // Blocking: election loop

Multiple controller managers:
  Deployment replicas = 2 (active-passive)
  Only leader pod runs reconciliation loops
  Passive pod: Hazır, lease expire-i gözləyir
```

### 8. Database Primary Election
```
PostgreSQL Patroni:
  DCS (Distributed Configuration Store): etcd/ZooKeeper/Consul
  Leader key: /service/{cluster}/leader

  Leader:
    Promosyon: Standby → Primary (PostgreSQL recovery target)
    Patroni: DCS-ə leader key yazır (TTL=30s)
    Hər 10s: Key renew

  Failover:
    Primary crash → TTL expires (30s)
    Replica leader-lik üçün yarışır
    Quorum: Ən up-to-date replica (wal_lag ən az)
    Winner: DCS-ə leader key yazır
    Winner: pg_promote() çağırır

  Fencing:
    pg_rewind: Köhnə primary geri qayıtdıqda log divergence düzəldir
    pg_ctl promote only via Patroni (manual promotion blocked)

MySQL Group Replication:
  Multi-master option ilə:
    Any node write qəbul edir
    Paxos-based consensus
    Conflict: Sertifikasyon protokollu (last commit wins)

  Single-primary option:
    Primary: Write qəbul edir
    Secondaries: Read only
    Primary fail → Group Replication avtomatik failover

  MySQL InnoDB Cluster = Group Replication + MySQL Shell + Router
```

### 9. Kafka Partition Leader Election
```
Kafka Leader:
  Hər partition üçün bir leader broker
  ISR (In-Sync Replicas): Replikalar arası yetişmiş set

  ZooKeeper (pre-3.x):
    /brokers/topics/{topic}/partitions/{n}/state
    Leader field: broker_id

  KRaft (Kafka 3.x+):
    Raft-based metadata cluster (ZooKeeper free)
    Controller quorum: Leader seçir
    Metadata log: Topic/partition/leader info

  Leader election trigger:
    Broker down → Controller (Kafka controller) detected
    Controller: ISR-dən leader seçir
    ISR empty (all replicas behind) → Unclean election risk

  Unclean leader election:
    unclean.leader.election.enable = false (default)
    ISR empty → Partition unavailable (better than data loss)
    
    unclean.leader.election.enable = true
    ISR empty → Out-of-sync replica leader olur
    → Possible data loss (ISR-dən geridə qalmış log)

  Producer fencing:
    producer.epoch: Yeni leader, producer epoch artırır
    Old producer (zombie) → INVALID_PRODUCER_EPOCH
    → Exactly-once semantics
```

### 10. Leader Election Anti-patterns
```
Anti-pattern 1: Clock-based election
  "Leader = ən yüksək clock-lu node"
  NTP clock skew ±100ms → Yanlış leader
  Fix: Logical clocks (Lamport, Hybrid Logical Clock)

Anti-pattern 2: Network ping-based
  "Digərlərini ping edə bilmirsəm → Mən leader-əm"
  Network partition → Hər iki tərəf leader düşünür
  Fix: Quorum-based election

Anti-pattern 3: Long GC pause ignored
  Java GC stop-the-world → Lease expire → Yeni leader
  Old leader resume → Zombie!
  Fix: Fencing token (monotonic epoch)

Anti-pattern 4: No leader handoff
  Leader down → Bütün cluster pause (election zamanı)
  Fix: Graceful handoff — Leader istərsə könüllü retire:
  1. Leader: "Ben getmak istəyirəm"
  2. Yeni candidate: Seçilir
  3. Köhnə leader: Transfer confirms, stop writing
  4. Yeni leader: Starts

Anti-pattern 5: Thundering herd on election
  100 node, leader dies → 100 node eyni anda election
  Fix: Random jitter timeout
  Fix: ZooKeeper-in watch chain pattern-ı
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Niyə leader lazımdır?" sorusuyla başla (coordination problem motivate et)
2. Split-brain problemini izah et, quorum həllini göstər
3. Lease-based election flow-unu addım-addım izah et
4. Fencing token ilə zombie leader problemini həll et
5. etcd/ZooKeeper-dən birini use case-ə uyğun seç

### Follow-up Suallar
- "Leader GC pause-da olarsa nə olur?" → Zombie leader, fencing token
- "etcd cluster özü down olsa?" → Leader election mümkün deyil, availability tradeoff
- "Leader election latency performansı necə effektləyir?" → Hot path vs election path
- "Active-active vs active-passive?" → Leader election = active-passive, nə zaman active-active daha yaxşıdır

### Ümumi Namizəd Səhvləri
- "Zookeeper istifadə edirik" demək amma necə işlədiyini bilməmək
- Split-brain problemini qeyd etməmək
- Fencing token-ı nə olduğunu bilməmək
- Quorum-un niyə N/2+1 olduğunu izah edə bilməmək
- Leader election latency-nin system-ə effektini düşünməmək (election zamanı system unavailable)

### Senior vs Architect Fərqi
**Senior**: etcd/ZooKeeper-lə leader election implement edir, lease TTL konfiqurasiya edir, split-brain probleminə quorum həlli tətbiq edir.

**Architect**: Fencing token ilə zombie leader-dən qoruyur, Raft consensus protokolunu izah edir (term, vote, log replication), leader election latency-ni SLO ilə əlaqələndirir (election time = unavailability window), active-passive vs active-active decision-ı iş yüküne görə verir, database primary election için Patroni/Orchestrator kimi production-grade həll seçir, Kafka KRaft arxitekturunu ZooKeeper ilə müqayisə edir.

## Nümunələr

### Tipik Interview Sualı
"Design a distributed job scheduler that ensures each job runs exactly once, even if multiple scheduler instances are running."

### Güclü Cavab
```
Distributed job scheduler — leader election design:

Problem:
  N scheduler nodes
  Hər node eyni cron jobs bilir
  Leader olmadan: Hər node eyni job-u start edir → Duplicate!

Architecture:

1. Leader Election (etcd):
  Hər scheduler node:
    etcd lease grant TTL=15s → lease_id
    etcd put /scheduler/leader "{node_id}" lease=lease_id
    Background goroutine: Renew every 5s

  Job execution:
    Leader olduqda: Job planning loop başlar
    Leader deyilsə: Passive wait

  Failover:
    Leader crash → TTL expires (15s)
    Waiting nodes: PUT /scheduler/leader (race)
    Winner: etcd atomic CAS (compare-and-swap) → 1 qalibdir

2. Fencing Token:
  etcd: Hər lease-in revision artar
  Job dispatch: Fencing token (etcd revision) ilə işarrə olunur
  Job worker: "Bu job-u version X-dən böyük token ilə qəbul et"
  
  Zombie scheduler token=10 ilə gəlirsə
  Current leader token=15 ilə dispatched
  Job worker: 10 < 15 → REJECT

3. Job Lock (ek qoruma):
  Hər job execution üçün distributed lock:
  etcd: /jobs/{job_id}/lock (TTL=job_timeout)
  Lock alınarsa: Execute
  Lock alınarmazsa: Skip (başqa node artıq işlədib)
  
  Idempotency: Job completion → lock release + result persist

4. State machine:
  Job states: PENDING → CLAIMED → RUNNING → DONE/FAILED
  Leader: PENDING → CLAIMED (atomic with fencing token)
  Worker: CLAIMED → RUNNING → DONE

5. Split-brain ek qoruma:
  etcd quorum: 3 etcd nodes
  Scheduler nodes: etcd-yə yaza bilmirlərsə → Passivdir

Election timeline:
  T=0:  Node1 leader (token=5)
  T=10: Node1 GC pause starts
  T=15: Lease expires
  T=16: Node2 leader (token=6)
  T=20: Node2 job1 dispatch (token=6)
  T=25: Node1 GC resume, job1 dispatch cəhd (token=5) → REJECTED
  T=30: Node1 lease check → Not leader → Passivdir

Monitoring:
  Election latency (leader down → new leader up): P99 < 20s
  Leader change rate: Too frequent → instability alert
  Job execution gap: Job düşüb düşmədiyini yoxla
  Fencing reject rate: Zombie leader aktivlik göstəricisi
```

### Arxitektura Nümunəsi
```
┌──────────────────────────────────────────────┐
│              Scheduler Cluster                │
│                                               │
│  ┌───────────┐  ┌───────────┐  ┌───────────┐ │
│  │ Scheduler │  │ Scheduler │  │ Scheduler │ │
│  │  Node 1   │  │  Node 2   │  │  Node 3   │ │
│  │ (LEADER)  │  │ (passive) │  │ (passive) │ │
│  └─────┬─────┘  └─────┬─────┘  └─────┬─────┘ │
│        │              │              │        │
│        └──────────────┼──────────────┘        │
│                       │                       │
└───────────────────────┼───────────────────────┘
                        │ lease renew / watch
                        ▼
              ┌─────────────────┐
              │   etcd cluster  │
              │  (3 nodes,      │
              │   quorum=2)     │
              │                 │
              │ /scheduler/     │
              │  leader: node1  │
              │  token: 47      │
              └─────────────────┘
                        │
              Leader election path:
              Node1 down → token 47 expired
              Node2 wins → token 48
              Node2 job dispatch: fencing=48
              Node1 revives: fencing=47 → REJECTED
```

## Praktik Tapşırıqlar
- etcd lease ilə simple leader election implement edin (Go ya da PHP)
- Fencing token simulation: Zombie leader scenario, token rejection test edin
- Kubernetes: Leader election with client-go, Lease resource yoxlayın
- Raft animation: raft.github.io — election, log replication izləyin
- ZooKeeper ephemeral nodes: Election recipe, herd effect test edin

## Əlaqəli Mövzular
- [12-cap-theorem-practice.md](12-cap-theorem-practice.md) — CAP: Partition zamanı consistency vs availability
- [17-distributed-transactions.md](17-distributed-transactions.md) — Leader coordination cross-service
- [15-service-discovery.md](15-service-discovery.md) — Leader-based service registry
- [23-eventual-consistency.md](23-eventual-consistency.md) — Consistency models
- [16-circuit-breaker.md](16-circuit-breaker.md) — Failure detection, leader awareness
