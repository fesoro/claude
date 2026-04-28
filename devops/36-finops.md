# FinOps (Lead)

## Nədir? (What is it?)

FinOps (Financial Operations) – cloud xərclərini mühəndislik, maliyyə və biznes komandaları arasında **paylaşılan məsuliyyət** kimi idarə edən operativ mədəniyyət və təcrübələr toplusudur. Ənənəvi IT-də server alışı CFO-nun capex qərarı idi; cloud-da hər developer `t3.xlarge` açıb aylarla unuda bilər – xərc kiçik qərarların cəmi kimi böyüyür. FinOps bunu həll edir: (1) **Visibility** – hər komanda öz xərcini görsün, (2) **Optimization** – right-sizing, reserved, spot, lifecycle, (3) **Accountability** – showback/chargeback, tag-lərlə cost attribution. **FinOps Foundation** (Linux Foundation) – bu sahənin standartlaşdırıcısıdır, üç fazlı framework (Inform, Optimize, Operate) təklif edir. Tools: AWS Cost Explorer, Azure Cost Management, GCP Billing, third-party (Cloudability, CloudHealth, Vantage, Kubecost, Infracost).

## Əsas Konseptlər (Key Concepts)

### FinOps Framework (3 faza, təkrar edən dövr)

```
1) INFORM (görmək)
   - Visibility – dashboard, hər komanda öz xərcini görür
   - Allocation – hansı komanda/layihə/mühit nə qədər sərf edir
   - Benchmarking – sənayə ilə müqayisə
   - Budgeting və Forecasting – aylıq büdcə, anomaly alert

2) OPTIMIZE (azaltmaq)
   - Rightsizing – instance ölçüsünü istifadəyə uyğunlaşdır
   - Reserved Instances / Savings Plans – uzunmüddətli öhdəlik
   - Spot Instances – 70-90% endirim, kəsintili iş yükü
   - Autoscaling – tələb üzrə
   - Storage tier – S3 Glacier, archive
   - Unused resources – unattached EBS, old snapshot, idle LB

3) OPERATE (davam etmək)
   - KPI-lər, cost-per-unit (request, user, transaction)
   - Automation – auto-tagging, auto-rightsizing
   - Governance – policy, guardrail
   - Culture – "cost-aware engineering"
```

### Pricing modelləri (AWS)

```
ON-DEMAND          – saat hesabı, ən baha, fleksibel
RESERVED INSTANCES – 1 və ya 3 il öhdəlik
   Standard      – 72% endirim, dəyişdirilmir
   Convertible   – 54% endirim, familiya dəyişmək olar
SAVINGS PLANS     – daha çevik reserved
   Compute       – EC2, Fargate, Lambda üçün (66% endirim)
   EC2 Instance  – konkret familyaya bağlı (72% endirim)
SPOT INSTANCES    – istifadə olunmayan capacity (70-90% endirim)
                    2 dəqiqə xəbərdarlıqla geri alına bilər
DEDICATED HOSTS   – fiziki server, compliance üçün
```

### Unit Economics

```
Cost per:
- 1000 request
- Active user
- GB processed
- Customer transaction
- API call

Unit economics biznes metrikasına bağlayır – "müşteri sayı 2x olarsa xərc 1.4x olmalıdır"
```

### Tagging Strategy

```
Məcburi tag-lər (hər resursda):
- Environment (dev, staging, production)
- Owner (komanda email)
- CostCenter (maliyyə mərkəzi)
- Project (layihə adı)
- ManagedBy (terraform, manual)
- ExpiresOn (auto-delete üçün tarix)

Cost allocation üçün AWS-də Cost Allocation Tags aktiv olmalıdır (1 günlük gecikmə).
```

### Anomaly Detection

```
AWS Cost Anomaly Detection – ML əsaslı, yüksələn xərci aşkarla
Azure Cost Management – Budget Alert, Anomaly
GCP Recommender – sizing tövsiyələri

Tipik anomaly:
- Test instance açıq qalıb
- S3 lifecycle policy yoxdur, mlrd kiçik fayl toplandı
- NAT Gateway üzərindən Internet trafiki partladı
- Log ingest (CloudWatch, Datadog) qeyri-kontrolsuz artdı
- Cross-region trafik (planlanmamış)
```

## Praktiki Nümunələr

### AWS Cost Explorer – aylıq xərc

```bash
# CLI ilə cost report
aws ce get-cost-and-usage \
  --time-period Start=2026-03-01,End=2026-04-01 \
  --granularity MONTHLY \
  --metrics "UnblendedCost" "UsageQuantity" \
  --group-by Type=DIMENSION,Key=SERVICE

# Tag-ə görə group
aws ce get-cost-and-usage \
  --time-period Start=2026-03-01,End=2026-04-01 \
  --granularity MONTHLY \
  --metrics "UnblendedCost" \
  --group-by Type=TAG,Key=Environment
```

### Terraform – Tagging Enforcement

```hcl
# providers.tf
provider "aws" {
  region = "eu-central-1"

  default_tags {
    tags = {
      Environment = var.environment
      ManagedBy   = "terraform"
      Owner       = var.team_email
      CostCenter  = var.cost_center
      Project     = var.project
    }
  }
}

# Policy-də yoxla (OPA / Conftest)
# policies/must-have-cost-tags.rego
package terraform.aws.tagging

required_tags := {"Environment", "Owner", "CostCenter", "Project"}

deny[msg] {
    resource := input.resource_changes[_]
    resource.change.actions[_] == "create"
    resource.change.after.tags
    missing := required_tags - {k | resource.change.after.tags[k]}
    count(missing) > 0
    msg := sprintf("%v: cost tag-ləri yoxdur: %v", [resource.address, missing])
}
```

### AWS Savings Plan hesablama

```python
# compute-savings.py
import boto3
from datetime import datetime, timedelta

ce = boto3.client('ce', region_name='us-east-1')

# Savings Plans tövsiyəsi
resp = ce.get_savings_plans_purchase_recommendation(
    SavingsPlansType='COMPUTE_SP',
    TermInYears='ONE_YEAR',
    PaymentOption='NO_UPFRONT',
    LookbackPeriodInDays='THIRTY_DAYS'
)

for rec in resp['SavingsPlansPurchaseRecommendation']['SavingsPlansPurchaseRecommendationDetails']:
    print(f"Commitment: ${rec['HourlyCommitmentToPurchase']}/saat")
    print(f"Aylıq qənaət: ${rec['EstimatedMonthlySavingsAmount']}")
    print(f"ROI: {rec['EstimatedSavingsPercentage']}%")
```

### Right-Sizing Script (CloudWatch metrics)

```python
# rightsize.py
import boto3
from datetime import datetime, timedelta

ec2 = boto3.client('ec2')
cw  = boto3.client('cloudwatch')

instances = ec2.describe_instances(Filters=[
    {'Name': 'instance-state-name', 'Values': ['running']}
])

for r in instances['Reservations']:
    for i in r['Instances']:
        iid = i['InstanceId']
        itype = i['InstanceType']

        # Son 14 günün CPU max-ı
        stats = cw.get_metric_statistics(
            Namespace='AWS/EC2',
            MetricName='CPUUtilization',
            Dimensions=[{'Name': 'InstanceId', 'Value': iid}],
            StartTime=datetime.utcnow() - timedelta(days=14),
            EndTime=datetime.utcnow(),
            Period=3600,
            Statistics=['Maximum', 'Average']
        )

        if not stats['Datapoints']:
            continue

        max_cpu = max(p['Maximum'] for p in stats['Datapoints'])
        avg_cpu = sum(p['Average'] for p in stats['Datapoints']) / len(stats['Datapoints'])

        if max_cpu < 20 and avg_cpu < 10:
            print(f"{iid} ({itype}): downsize et (max CPU {max_cpu:.1f}%, avg {avg_cpu:.1f}%)")
```

### Spot Instance (EKS üçün)

```yaml
# eks-spot-nodegroup.yaml
apiVersion: eksctl.io/v1alpha5
kind: ClusterConfig
metadata:
  name: laravel-cluster
  region: eu-central-1

managedNodeGroups:
  - name: spot-workers
    instanceTypes: ["m5.large", "m5a.large", "m5d.large", "m4.large"]
    spot: true
    minSize: 2
    maxSize: 20
    desiredCapacity: 4
    labels:
      workload-type: stateless
    taints:
      - key: spot
        value: "true"
        effect: PreferNoSchedule
    tags:
      Environment: production
      CostStrategy: spot
```

### Kubecost deployment (K8s cost monitoring)

```bash
# Helm ilə install
helm repo add kubecost https://kubecost.github.io/cost-analyzer/
helm install kubecost kubecost/cost-analyzer \
  --namespace kubecost --create-namespace \
  --set kubecostToken="<token>" \
  --set prometheus.server.retention=30d

# UI: http://kubecost.example.com
# Hər namespace, deployment, pod-un aylıq xərcini göstərir
# "Efficiency" metric – CPU request vs usage
```

### Auto-Shutdown Lambda (gecə saatları)

```python
# lambda_stop_dev.py
import boto3
import os

def lambda_handler(event, context):
    ec2 = boto3.client('ec2', region_name=os.environ['REGION'])
    tag_value = os.environ.get('ENV_TAG', 'dev')

    instances = ec2.describe_instances(Filters=[
        {'Name': 'tag:Environment', 'Values': [tag_value]},
        {'Name': 'tag:AutoShutdown', 'Values': ['true']},
        {'Name': 'instance-state-name', 'Values': ['running']}
    ])

    ids = [i['InstanceId'] for r in instances['Reservations'] for i in r['Instances']]
    if ids:
        ec2.stop_instances(InstanceIds=ids)
        print(f"Stopped {len(ids)} instances: {ids}")
    return {'stopped': len(ids)}

# EventBridge rule: cron(0 19 ? * MON-FRI *) – axşam 19:00 UTC saxla
# cron(0 7 ? * MON-FRI *) – səhər 07:00 başla
```

## PHP/Laravel ilə İstifadə

### Laravel tətbiqinin xərcini izləmək

```php
// app/Http/Middleware/CostTracking.php
namespace App\Http\Middleware;

use Closure;
use Aws\CloudWatch\CloudWatchClient;

class CostTracking
{
    public function __construct(private CloudWatchClient $cw) {}

    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;

        // Custom metric: cost-per-request üçün
        $this->cw->putMetricData([
            'Namespace' => 'Laravel/Cost',
            'MetricData' => [[
                'MetricName' => 'RequestDuration',
                'Value'      => $duration,
                'Unit'       => 'Seconds',
                'Dimensions' => [
                    ['Name' => 'Endpoint', 'Value' => $request->route()->getName() ?? 'unnamed'],
                    ['Name' => 'Tenant',   'Value' => $request->header('X-Tenant', 'default')],
                ],
            ]],
        ]);

        return $response;
    }
}
```

### S3 Lifecycle Policy (Laravel storage)

```hcl
# terraform/s3-lifecycle.tf
resource "aws_s3_bucket_lifecycle_configuration" "laravel_uploads" {
  bucket = aws_s3_bucket.laravel.id

  rule {
    id     = "old-uploads-to-ia"
    status = "Enabled"
    filter { prefix = "uploads/" }

    transition {
      days          = 30
      storage_class = "STANDARD_IA"
    }
    transition {
      days          = 90
      storage_class = "GLACIER_IR"
    }
    transition {
      days          = 180
      storage_class = "DEEP_ARCHIVE"
    }
  }

  rule {
    id     = "logs-cleanup"
    status = "Enabled"
    filter { prefix = "logs/" }
    expiration {
      days = 90
    }
    noncurrent_version_expiration {
      noncurrent_days = 30
    }
  }

  rule {
    id     = "incomplete-multipart"
    status = "Enabled"
    abort_incomplete_multipart_upload {
      days_after_initiation = 7
    }
  }
}
```

### RDS – Reserved Instance hesablama

```php
// app/Console/Commands/EstimateRdsRi.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Aws\Ce\CostExplorerClient;

class EstimateRdsRi extends Command
{
    protected $signature = 'finops:rds-ri';
    protected $description = 'RDS reserved instance tövsiyələrini göstər';

    public function handle(CostExplorerClient $ce)
    {
        $result = $ce->getReservationPurchaseRecommendation([
            'Service' => 'Amazon Relational Database Service',
            'LookbackPeriodInDays' => 'SIXTY_DAYS',
            'TermInYears' => 'ONE_YEAR',
            'PaymentOption' => 'PARTIAL_UPFRONT',
        ]);

        foreach ($result['Recommendations'] ?? [] as $rec) {
            foreach ($rec['RecommendationDetails'] as $detail) {
                $this->info(sprintf(
                    "%s x%s | upfront: $%s | aylıq qənaət: $%s",
                    $detail['InstanceDetails']['RDSInstanceDetails']['InstanceType'],
                    $detail['RecommendedNumberOfInstancesToPurchase'],
                    $detail['UpfrontCost'],
                    $detail['EstimatedMonthlySavingsAmount']
                ));
            }
        }
    }
}
```

### Queue worker-lər spot EC2-də

```yaml
# horizon supervisor – spot termination handling
# /etc/supervisor/conf.d/horizon.conf
[program:horizon]
process_name=%(program_name)s
command=php /var/www/laravel/artisan horizon
autostart=true
autorestart=true
stopwaitsecs=120        # spot termination 2 dəqiqə verir, job-u bitir
stopsignal=TERM
user=www-data
```

```php
// app/Console/Kernel.php – spot termination check
use Illuminate\Support\Facades\Http;

$schedule->call(function () {
    try {
        $meta = Http::timeout(1)->get('http://169.254.169.254/latest/meta-data/spot/termination-time');
        if ($meta->successful()) {
            // 2 dəqiqə var – graceful shutdown
            \Artisan::call('horizon:terminate');
        }
    } catch (\Throwable $e) { /* ignore */ }
})->everyMinute();
```

### Infracost – PR-da xərc impact

```yaml
# .github/workflows/infracost.yml
name: Infracost

on:
  pull_request:
    paths: ['terraform/**']

jobs:
  infracost:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: infracost/actions/setup@v3
        with:
          api-key: ${{ secrets.INFRACOST_API_KEY }}

      - name: Generate baseline
        run: |
          git checkout ${{ github.event.pull_request.base.sha }}
          infracost breakdown --path=terraform/ --format=json --out-file=base.json

      - name: Generate diff
        run: |
          git checkout ${{ github.event.pull_request.head.sha }}
          infracost diff --path=terraform/ --compare-to=base.json --format=json --out-file=diff.json

      - name: Post comment on PR
        run: infracost comment github --path=diff.json --repo=$GITHUB_REPOSITORY --pull-request=${{ github.event.pull_request.number }} --github-token=${{ secrets.GITHUB_TOKEN }} --behavior=update
```

## Interview Sualları (Q&A)

**S1: FinOps nədir və niyə DevOps komandasının işidir?**
C: FinOps – cloud xərclərini mədəni, operativ və texniki praktika kimi idarə etməkdir. DevOps komandası xərci ən çox **yaradan** tərəfdir (instance açır, storage istifadə edir, data transfer yaradır). Finance komandası xərci yalnız **görür**. FinOps bu boşluğu bağlayır – mühəndislər xərci "maliyyəçi problemi" olaraq yox, "performance kimi metric" olaraq görür. Cost-per-unit KPI-lərini mühəndislər həll edirlər. Ona görə DevOps/SRE və Platform komandaları FinOps-un mərkəzindədir.

**S2: Reserved Instance və Savings Plan fərqi nədir?**
C: **Reserved Instance (RI)** – konkret instance type, region, OS-ə öhdəlik (1-3 il). Endirim yaxşıdır (40-72%), amma fleksibel deyil – `t3.medium` RI varsa, `t3.large` yazanda tam tarif. **Savings Plan (SP)** – saatlıq **dollar** öhdəliyi (məs. $10/saat). Hansı instance istifadə etməyindən asılı deyil – endirim avtomatik tətbiq olunur. Compute SP həm Lambda, həm Fargate, həm EC2-yə şamil olur. SP daha çevikdir, müasir tövsiyə SP-dir; dəyişən iş yükü üçün xüsusilə uyğundur.

**S3: Spot instance-ın riskləri nələrdir? Hansı iş yükü üçün uyğundur?**
C: Risk – AWS istənilən vaxt 2 dəqiqə xəbərdarlıqla instance-ı geri ala bilər. Uyğun iş yükləri: **stateless, interrupt-safe, batch** – CI runner, data processing, video encoding, ML training, web fleet replica-larından bir hissəsi, Kubernetes worker node-lar (K8s pod-ları avtomatik başqa node-a köçürür). Uyğun **deyil**: database primary, stateful service, yeganə instance olan kritik API. Qarışıq strategiya: 30% on-demand (baseline) + 70% spot (elastic).

**S4: Tagging strategy olmadan niyə cost allocation mümkün olmur?**
C: Cloud provider bill "bu ay $50k EC2" göstərir, amma "hansı komanda, hansı layihə" demir. Tag-lər (`Environment`, `Owner`, `Project`, `CostCenter`) hər resursa "sahib" verir. Cost allocation Tag-lər aktiv olduqdan sonra (AWS-də Cost Allocation Tags) hər tag dəyərinə görə xərc parçalanır. Tag-lərsiz resurslar "untagged" bucket-ə düşür və heç kim sahiblik etmir. Güclü tagging strategy + enforcement (OPA, Cloud Custodian) FinOps-un təməlidir.

**S5: Right-sizing necə edilir?**
C: Son 14-30 günün CPU, memory, network, IOPS metric-lərinə baxırsan. **Max CPU < 20% və avg < 10%** olan instance **çox böyükdür** – bir ölçü aşağı sal. **Sustained CPU > 80%** – bir ölçü böyük lazımdır. AWS Compute Optimizer avtomatik tövsiyələr verir – "r5.4xlarge yerinə r5.2xlarge kifayətdir, $200/ay qənaət". Right-sizing adətən 20-40% xərc azaldır. K8s üçün Kubecost, Vertical Pod Autoscaler (VPA) resource request-ləri tənzimləyir.

**S6: "Showback" və "Chargeback" fərqi nədir?**
C: **Showback** – hər komandaya öz xərcini göstərir, amma faktura kəsmir – "Siz bu ay $8k sərf etdiniz" (məlumatlandırma). **Chargeback** – həqiqi daxili faktura – komandanın büdcəsindən o məbləğ çıxılır. Showback FinOps-un ilk mərhələsidir (transparency), chargeback daha yüksək maturity tələb edir (dəqiq tag-ing, shared cost split qaydaları). Əksər təşkilat showback ilə başlayır, biznes stabil olanda chargeback-ə keçir.

**S7: Cost anomaly-lərin ən çox yayılmış səbəbləri nələrdir?**
C: (1) **NAT Gateway** üzərindən yüksək trafik – GB-a görə ödəniş, tez partlayır. (2) **CloudWatch / log ingest** – verbose logging, qeyri-məhdud retention. (3) **Data transfer** – cross-region, cross-AZ, Internet egress. (4) **Unattached EBS volume-ları** – snapshot qalıb, volume silinməyib. (5) **Idle load balancer, elastic IP** – istifadə olunmayan amma durma haqqı. (6) **S3 multipart upload** natamam yüklənməlr qalır. (7) **Dev/test resources-in gecə açıq qalması** – auto-shutdown yoxdur. (8) **Oversized instance** – test zamanı `m5.xlarge` açıldı, production-a keçdi.

**S8: K8s-də cost attribution necə edilir?**
C: K8s-də bir node birdən çox komandanın pod-larını daşıyır – AWS bill bu ayrılığı göstərmir. **Kubecost** həlldir: Prometheus-dan hər pod-un CPU, memory istifadəsini alır, node xərcini pod-lar arasında **istifadə payı** üzrə bölür. Namespace, label, deployment səviyyəsində xərc göstərir. Alternativ – **OpenCost** (CNCF), həmin məntiq, vendor-neutral. Namespace-ə görə chargeback/showback pipeline-ları Kubecost-dan gələn data ilə qurulur.

**S9: FinOps KPI-ləri nələrdir?**
C: (1) **Cost per transaction / request / user** – biznesə bağlı unit economics. (2) **Committed use coverage** – xərcin neçə %-i RI/SP-da. (3) **Idle resource %** – istifadə olunmayan, amma ödənilən. (4) **Untagged resource %** – sahiblik yoxluğu. (5) **Forecast accuracy** – büdcə vs faktiki. (6) **Cost variance** – aydan aya dəyişiklik. (7) **Savings realized** – optimization nəticəsi. (8) **Storage efficiency** – hot/cold tier ratio. Bu metric-lər dashboard-da hər komanda üçün ayrıca göstərilməlidir.

**S10: Cloud xərclərini azaltmaq üçün ən təsirli 3 addım nədir?**
C: (1) **Tagging + visibility** – heç kim görmədiyi xərci optimize edə bilməz. Əvvəlcə hər resursa sahib və kontekst bağla. (2) **Commitment-based discount** – dayanıqlı baseline workload-a Savings Plan al (adətən 20-30% dərhal qənaət). (3) **Auto-shutdown + right-sizing** – dev/test resource-lar gecə və həftəsonu bağlı olsun (40% qənaət), production right-size (20-40%). Əlavə: S3 lifecycle policy, log retention azaltmaq, NAT Gateway replacement (VPC Endpoint). Tipik təşkilat bu 3 addımla xərcini 30-50% azalda bilir.

## Best Practices

1. **Tagging enforcement** – OPA/Conftest ilə CI-da məcburi tag yoxlaması.
2. **Default tags** Terraform provider-də – unutma riski yoxdur.
3. **Budget alert-lər** 50%, 80%, 100% – SNS/Slack ilə bildiriş.
4. **Cost Anomaly Detection** aktiv olsun – AWS Cost Anomaly, Azure Cost Anomaly.
5. **Reserved/Savings coverage** hədəf 60-80% steady-state workload üçün.
6. **Spot instance** stateless iş yükü üçün – həmişə mixed-instance ASG, multiple instance types.
7. **Right-sizing schedule** – kvartalda bir dəfə (AWS Compute Optimizer avtomatik).
8. **Auto-shutdown** dev/test resurs üçün (Lambda + EventBridge).
9. **S3 lifecycle policy** məcburi – IA, Glacier, Expiration.
10. **Log retention** minimum lazımi müddət – CloudWatch, Datadog tez bahalaşır.
11. **Infracost** PR-da xərc impact göstərsin.
12. **Kubecost / OpenCost** K8s üçün namespace səviyyəsində visibility.
13. **Unit economics** KPI – cost-per-request biznesə bağlı olsun.
14. **FinOps komitəsi** – Finance, Engineering, Product nümayəndələri aylıq görüş.
15. **Cloud Custodian / AWS Config** – idle resource, unattached EBS, old snapshot avtomatik silsin.

---

## Praktik Tapşırıqlar

1. AWS Cost Explorer ilə mövcud xərclər auditini edin: top-5 ən baha servis, son 3 ay trend, mühit üzrə breakdown (dev/staging/prod tag-larla); `aws ce get-cost-and-usage` CLI ilə avtomatik aylıq report çəkin
2. Rightsizing tövsiyələri tətbiq edin: `aws compute-optimizer get-ec2-instance-recommendations` çalışdırın; over-provisioned instance-ları müəyyən edin; non-prod mühitdə bir instance-ı endir (t3.medium → t3.small), performance metriklerini izləyin
3. S3 lifecycle policy yazın: uploads/ bucket-i üçün — 30 gün sonra Intelligent-Tiering, 90 gün sonra Glacier, 365 gün sonra delete; `aws s3api put-bucket-lifecycle-configuration`; 1 ay sonra xərcin neçə faiz azaldığını hesablayın
4. Reserved Instance vs On-Demand analiz edin: mövcud production EC2 instance-larının son 3 aylıq utilization-ını görün; 1 illik RI alındıqda qənaəti hesablayın; break-even point tapın; Savings Plan ilə RI-nı müqayisə edin
5. Infracost-u CI/CD-ə inteqrasiya edin: `infracost breakdown --path=terraform/` ilə Terraform dəyişikliyinin xərc təsirini PR-da göstərin; threshold qurun — aylıq $500-dan artıq xərc artımı varsa PR-ı bloklasın; aylıq xərc forecast çıxarın
6. Idle resource-ları tapın: `aws ec2 describe-instances --filters Name=instance-state-name,Values=stopped` ilə dayandırılmış EC2-ları tapın; attached olmayan EBS volume-ları tapın (`aws ec2 describe-volumes --filters Name=status,Values=available`); köhnə snapshot-ları tapın; hamısını terminate edin

## Əlaqəli Mövzular

- [AWS Əsasları](14-aws-basics.md) — AWS pricing modeli, instance tipi seçimi
- [AWS Advanced](26-aws-advanced.md) — Auto Scaling, Spot instance
- [Terraform Əsasları](23-terraform-basics.md) — Infracost Terraform inteqrasiyası
- [Site Reliability](34-site-reliability.md) — reliability vs cost trade-off
- [Multi-Cloud](37-multi-cloud.md) — multi-cloud cost optimization
