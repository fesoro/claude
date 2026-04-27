# Cost Optimization Strategies (Lead ⭐⭐⭐⭐)

## İcmal
Cloud cost optimization — sistemin eyni performance və reliability standartlarında daha az xərc ilə işləməsini təmin etmək üçün tətbiq olunan texniki və arxitektura strategiyalarıdır. "Serverları optimallaşdırmaq" deyil — arxitektura qərarları (caching, async processing, right-sizing), cloud pricing model anlayışı (Reserved vs Spot), və waste elimination birlikdə tətbiq edildiyi zaman həqiqi tasarruf edilir. Lead engineer kimi bu mövzu engineering qərarlarının business impact-ına çevrildiyi nöqtədir.

## Niyə Vacibdir
"Infra xərci artıb, nə edəcəyik?" sualı hər startup-da, scale-up fazasında olan hər şirkətdə mütləq gəlir. Lead developer kimi bu sualı cavablandırmaq üçün texniki alətlər lazımdır: hansı xərci azaldırıq, nə riski artırır, business-ə izah etmək üçün ROI necə hesablanır? Bu anlayış Senior-Lead keçidinin əsas kompetensiyalarından biridir.

## Əsas Anlayışlar

### Cloud Cost Anatomy:

**Tipik breakdown (AWS nümunəsi):**
```
Compute (EC2/ECS/Lambda):          45%
Database (RDS/DynamoDB):           25%
Storage (S3, EBS):                 15%
Network (data transfer, LB, CDN):  10%
Other (SES, SQS, CloudFront, etc): 5%
```

Her kateqoriya üçün ayrı optimizasiya strategiyaları var.

---

### Compute Optimizasiyası:

**Right-sizing:**
```bash
# AWS Compute Optimizer tövsiyələri
aws compute-optimizer get-ec2-instance-recommendations \
  --account-ids 123456789

# Nəticə:
# i3.2xlarge → m6i.large (yetərli, 60% ucuz)
```

**Reserved Instances vs On-Demand vs Spot:**
| | On-Demand | Reserved (1-3il) | Spot |
|--|-----------|-----------------|------|
| Qiymət | 100% | 40-60% | 10-30% |
| Commitment | Yox | 1-3 il | Yox |
| Availability | Zəmanətli | Zəmanətli | Interruptible |
| İstifadə | Dev/test | Production base | Batch jobs |

**Kubernetes cost optimization:**
```yaml
# Pod Disruption Budget — Spot instance sınmasından qorunma
apiVersion: policy/v1
kind: PodDisruptionBudget
metadata:
  name: app-pdb
spec:
  minAvailable: 2  # Spot interruption zamanı ən az 2 pod ayaqda
  selector:
    matchLabels:
      app: laravel-app

# Node affinity — Spot node-larına batch job-ları göndər
spec:
  affinity:
    nodeAffinity:
      preferredDuringSchedulingIgnoredDuringExecution:
        - weight: 1
          preference:
            matchExpressions:
              - key: eks.amazonaws.com/capacityType
                operator: In
                values: ["SPOT"]
```

**Fargate vs EC2 (Kubernetes):**
- Fargate: serverless, per-pod billing, ideal intermittent workloads
- EC2 nodes: reserved, daha ucuz davamlı yük üçün

---

### Auto-Scaling ilə Cost Balansı:

```yaml
# Scheduled scaling — gecə scale down
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: app-hpa
spec:
  minReplicas: 1   # Gecə saatlar minimum
  maxReplicas: 20  # Peak üçün maksimum
```

```bash
# KEDA ilə schedule-based scaling (Kubernetes)
# Iş saatları: 5 replica
# Gecə: 1 replica
kubectl patch deploy app --patch '{"spec":{"replicas":1}}'
# CronJob ya da KEDA ScaledObject ilə avtomatlaşdır
```

---

### Database Optimizasiyası:

**RDS right-sizing:**
```sql
-- CPU/memory istifadəsini yoxla
SELECT
  datname,
  numbackends,
  xact_commit,
  blks_hit::float / (blks_hit + blks_read) AS cache_hit_ratio
FROM pg_stat_database;

-- Cache hit ratio < 0.99 isə: daha çox RAM lazımdır
-- Yaxud: query optimization (disk I/O azalt)
```

**Read replica routing:**
```php
// Laravel read/write split — master write, replica read
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['replica1.db.internal', 'replica2.db.internal'],
    ],
    'write' => [
        'host' => 'master.db.internal',
    ],
    // ...
],

// Read-heavy sorğular replica-ya gedir → master yükü azalır
// Daha kiçik master instance istifadə etmək mümkün olur
```

**Aurora Serverless v2:**
- Minimum capacity: 0.5 ACU (çox az traffic üçün)
- Maximum: 128 ACU (peak üçün)
- Saniyəlik billing — idle sistemi minimal xərc
- Dev/staging üçün ideal

---

### Storage Optimizasiyası:

**S3 Tiering:**
```python
# S3 Intelligent-Tiering — avtomatik tier keçidi
aws s3api put-bucket-intelligent-tiering-configuration \
  --bucket my-app-uploads \
  --id tiering-config \
  --intelligent-tiering-configuration '{
    "Id": "tiering-config",
    "Status": "Enabled",
    "Tierings": [
      {"Days": 90, "AccessTier": "ARCHIVE_ACCESS"},
      {"Days": 180, "AccessTier": "DEEP_ARCHIVE_ACCESS"}
    ]
  }'
```

**Storage tiers:**
| Tier | Cost/GB | Latency | İstifadə |
|------|---------|---------|---------|
| S3 Standard | $0.023 | ms | Aktiv data |
| S3-IA | $0.0125 | ms | Bəndə bir dəfə |
| Glacier Instant | $0.004 | ms | Arxiv, bəzən lazım |
| Glacier Deep | $0.00099 | saatlar | Uzunmüddətli arxiv |

**EBS volume optimization:**
```bash
# gp2 → gp3 migration (30% ucuz, daha yaxşı performance)
aws ec2 modify-volume --volume-id vol-xxx --volume-type gp3
```

---

### Network Xərc Optimizasiyası:

```
Data transfer ödənişlər:
- Inter-region: ~$0.02/GB
- Internet egress: ~$0.09/GB
- Same-region, same-AZ: ücretsiz
- Same-region, cross-AZ: $0.01/GB

Optimizasiya:
- Servisləri eyni AZ-da yerlə (mümkünsə)
- CDN istifadə et — edge-dən serve et, origin traffic azal
- Compression: Gzip/Brotli (bandwidth azalır)
- NAT Gateway: VPC-dən internet trafikinin kaynağı — baha, optimize et
```

**CDN strategiyası:**
```php
// Laravel — static asset CDN-dən serve etmek
// config/filesystems.php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    // CDN URL-i burada set edilir
],

// .env
ASSET_URL=https://cdn.example.com
// asset() helper artıq CDN URL qaytarır
```

---

### Caching ilə Compute/DB Azaltmaq:

```php
// Redis caching — DB query-ləri azalt
// DB sorğu: $0.02 CPU time
// Redis: $0.0001 — 200x ucuz

class ProductRepository
{
    public function findWithCache(int $id): ?Product
    {
        return Cache::remember("product:{$id}", 3600, function () use ($id) {
            return Product::with(['category', 'images'])->find($id);
        });
    }
}

// N+1 query elimination — DB query count azaldır
// 1 sorğu yerinə 1+N → 2 sorğu (eager loading)
Product::with('category')->get(); // 1 DB + 1 join vs N+1
```

---

### FinOps Best Practices:

**Tagging strategy:**
```hcl
# Terraform — hər resursa tag
tags = {
  Environment = "production"
  Team        = "backend"
  Service     = "payment"
  CostCenter  = "engineering"
}
```

**Budget alerts:**
```bash
# AWS Budget — aylıq threshold aşıldıqda alert
aws budgets create-budget \
  --account-id 123456789 \
  --budget '{"BudgetName":"monthly-limit","BudgetLimit":{"Amount":"5000","Unit":"USD"},"TimeUnit":"MONTHLY","BudgetType":"COST"}'
```

**Cost anomaly detection:**
- AWS Cost Anomaly Detection: gündəlik baseline-dən kəskin sapma alert
- Yanlışlıqla açıq qalan dev environment, runaway process

---

### Cost Optimization Roadmap:

```
Quick wins (1-2 həftə):
  - Unused resources sil (stopped instances, unattached EBS)
  - Right-size over-provisioned instances
  - gp2 → gp3 migration

Short-term (1-3 ay):
  - Reserved Instance purchase (1 il)
  - Read replica routing
  - S3 Intelligent-Tiering

Long-term (3-12 ay):
  - Architecture refactor (event-driven, serverless)
  - Caching layer gücləndir
  - Spot instance workload migration
```

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Cloud xərclərini necə azaldırdınız?" sualına "Reserved Instance aldıq" deyib dayandırma. Right-sizing, caching impact, architecture qərarları (Spot, Fargate, async), tagging strategy, budget alert — bunları birlikdə izah et. ROI nümunəsi: "Redis cache əlavə etdik — DB instance-ı downgrade etdik, $1200/ay tasarruf."

**Follow-up suallar:**
- "Spot instance nə vaxt uyğundur?"
- "Read replica routing xərc-i necə azaldır?"
- "FinOps nədir?"

**Ümumi səhvlər:**
- Performance sacrifice edərək cost cut — SLO pozulur
- Reserved Instance almadan Spot-a keçmək
- Tagging olmadan cost attribution mümkün deyil
- Caching lazımını bilmədən scale etmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Cheaper instance aldıq" vs "Əvvəlcə bottleneck tapdıq — DB query-lər çox idi. Redis əlavə etdik, hit ratio %95-ə çıxdı, DB load %60 azaldı. Sonra DB instance downgrade etdik — aylıq $800 tasarruf, performance artdı."

## Nümunələr

### Tipik Interview Sualı
"AWS xərci sizin team-də $15k/ay-dır. Bu rəqəmi 30% azaltmağı istəyirlər. Haradan başlayarsınız?"

### Güclü Cavab
"Əvvəlcə cost breakdown alardım: compute, DB, storage, network faizi. Quick win-lər: AWS Cost Anomaly-yə baxardım — istifadəsiz resurslar var mı? Stopped EC2, unattached EBS, idle dev environments? Compute right-sizing: Compute Optimizer tövsiyələri — over-provisioned instance-lar. DB: cache hit ratio yüksəkdirmi? Aşağıdırsa Redis gücləndir — DB load azalar, daha kiçik instance mümkün. Reserved Instance: stable workload varsa 1 il commit ilə 40% tasarruf. Sonrakı addım: Spot instance-lara batch/async iş köçürmək. Bu 4 addım adətən 25-35% azalma verir."

## Praktik Tapşırıqlar
- AWS Cost Explorer-da son ayın breakdown-ını çıxar
- Unused resources tap: `aws ec2 describe-instances --filters Name=instance-state-name,Values=stopped`
- Bir servisin DB cache hit ratio-sunu ölç
- Terraform-da resource tag-ları əlavə et

## Əlaqəli Mövzular
- [08-capacity-planning.md](08-capacity-planning.md) — Capacity = Cost planlaması
- [02-container-orchestration.md](02-container-orchestration.md) — Kubernetes cost (HPA, Spot)
- [03-infrastructure-as-code.md](03-infrastructure-as-code.md) — IaC ilə resource tagging
- [05-sla-slo-sli.md](05-sla-slo-sli.md) — Cost azaltma SLO-nu nə dərəcədə riskə atır?
