# Multi-Cloud (Lead)

## Nədir? (What is it?)

Multi-cloud – bir təşkilatın iş yüklərini **birdən çox public cloud provayderində** (AWS, Azure, GCP, Alibaba, OCI) paralel olaraq işlətməsi strategiyasıdır. Hybrid cloud isə public cloud ilə on-premise datacentre-ni birləşdirir. Multi-cloud-un motivləri: (1) **Vendor lock-in azaltmaq** – bir provayderə tam asılılıqdan qaçmaq, (2) **Best-of-breed** – hər iş üçün ən yaxşı xidmət (AWS Lambda, GCP BigQuery, Azure AD), (3) **Compliance** – məlumatın konkret coğrafi və ya provayder sərhədlərində saxlanması (data residency), (4) **Resilience** – bir provayderin regional çöküşündən qorunmaq, (5) **M&A** – birləşmədən gələn fərqli cloud platformalarını idarə etmək, (6) **Cost arbitrage** – xidmətləri bir-biri ilə müqayisə edib ən uyğun qiyməti seçmək. Multi-cloud **kompleksdir** – IAM, networking, monitoring, deployment hər cloud-da fərqli işləyir. Alətlər: Terraform, Crossplane, Pulumi, Anthos, Azure Arc, AWS Outposts. Bu fəsildə strategiyalar, arxitektura pattern-ləri və Laravel kontekstində tətbiq izah olunur.

## Əsas Konseptlər (Key Concepts)

### Multi-cloud strategiyaları

```
1) ACTIVE-ACTIVE (həqiqi multi-cloud)
   İş yükü eyni anda iki cloud-da çalışır, trafik split
   - Ən mürəkkəb, ən yüksək resilience
   - Data replikasiyası kritik (eventually consistent)
   - Global LB (Route 53, Cloudflare, Akamai)
   Example: read-heavy API, CDN origin-i hər yerdə

2) ACTIVE-PASSIVE (DR – disaster recovery)
   Primary cloud A, standby cloud B
   - Standby minimum resurslu (pilot light / warm standby)
   - Failover manual və ya avtomatik
   - RTO (Recovery Time): 1-60 dəqiqə
   - RPO (Recovery Point): sıfıra yaxın (streaming replica)
   Example: Banking, e-commerce DR site

3) CLOUD-PER-WORKLOAD (best-of-breed)
   Workload A AWS-də, Workload B Azure-da, Workload C GCP-də
   - Vendor lock-in yoxdur amma real multi-cloud DR deyil
   - Komanda bacarığı vacibdir (3 cloud expert)
   - Integration points Ətraflı (cross-cloud networking)

4) HYBRID CLOUD (on-prem + cloud)
   Müəssisə datacenter + public cloud
   - AWS Outposts, Azure Arc, GCP Anthos
   - Data sovereignty, legacy workload
   - VPN / Direct Connect / ExpressRoute

5) CLOUD BURSTING
   Əsas iş on-prem, peak zamanı cloud-a çıxar
   - Batch processing, render farms
   - Qənaətcildir, amma networking çağırışdır
```

### Vendor Lock-in növləri

```
TEXNOLOJİ LOCK-IN (texniki)
- AWS Lambda kod → Google Cloud Functions-a keçid asan deyil
- DynamoDB sxema → Cosmos DB-yə migrasiya ağır
- AWS SQS specific format → GCP Pub/Sub fərqli API

COMMERCIAL LOCK-IN (kommersiya)
- Reserved instance, Savings Plan 3 il
- Enterprise Discount Program
- Data egress fee – məlumatı çıxartmaq bahadır (10TB çıxarış)

OPERATIONAL LOCK-IN (operativ)
- Komanda yalnız AWS biliyinə malikdir
- CI/CD pipeline-lar AWS-specific
- Monitoring CloudWatch-a bağlı
```

### Vendor Lock-in azaltma

```
ABSTRACTION:
- Kubernetes (bütün cloud-larda EKS/AKS/GKE)
- Terraform (HCL + providers – hər cloud üçün module)
- Docker container (portable)
- Crossplane (cloud resursları K8s CRD kimi)
- PostgreSQL, MySQL (managed, amma eyni protokol)

AVOID:
- Cloud-specific PaaS (Firebase, App Engine, Elastic Beanstalk)
- Proprietary NoSQL (DynamoDB, Cosmos DB) əgər portability lazımdır
- Proprietary managed queue (SQS specific behavior)

PORTABLE CHOICES:
- Object storage – S3-compatible API (MinIO, Wasabi, R2)
- DB – PostgreSQL (RDS, Cloud SQL, Azure Database)
- Queue – RabbitMQ, Kafka, Redis Streams
- Secrets – Vault (self-hosted)
- Identity – Keycloak, OIDC
```

### Multi-cloud networking

```
VPN (site-to-site):
- AWS VPN Gateway ↔ Azure VPN Gateway
- IPSec tunnel, şifrəli
- Latency variable, 1-2 Gbps

DEDICATED CIRCUITS:
- AWS Direct Connect (1-100 Gbps, fixed latency)
- Azure ExpressRoute
- GCP Cloud Interconnect
- Equinix Fabric – birdən çox cloud-a tək port

MEGAPORT / ALKIRA / AVIATRIX:
- Multi-cloud SD-WAN
- Transit gateway hər cloud-da, mərkəzləşmiş hub

SERVICE MESH:
- Istio multi-cluster
- Linkerd multi-cluster
- Cilium ClusterMesh
```

### Data sinxronizasiyası

```
DATABASE REPLICA:
- PostgreSQL logical replication cross-cloud
- MySQL GTID-based replication
- MongoDB replica set with cloud nodes
- Galera Cluster (MariaDB/Percona)

CACHE REPLICATION:
- Redis Enterprise multi-cloud
- ElastiCache global datastore (AWS-only)

OBJECT STORAGE:
- S3 Cross-Region Replication (AWS-only)
- Object sync tools (rclone, cloud-native replication)

EVENT STREAMING:
- Kafka MirrorMaker 2 (cross-cloud)
- Confluent Cloud multi-region
- AWS MSK + Azure Event Hubs bridging

CDC (Change Data Capture):
- Debezium (Kafka Connect)
- AWS DMS
```

### Crossplane (K8s control plane for clouds)

```
K8s CRD → Cloud resurs
apiVersion: database.aws.crossplane.io/v1beta1
kind: RDSInstance
→ AWS RDS instance yaranır

XRD (CompositeResourceDefinition) + Composition:
- Abstract "PostgresDatabase" kind
- hər cloud-da implementasiya (AWS RDS, Azure Flexible, GCP Cloud SQL)
- Developer eyni YAML yazır, provider-dən asılı deyil
```

## Praktiki Nümunələr

### Terraform Multi-Cloud (AWS + Azure)

```hcl
# main.tf
terraform {
  required_providers {
    aws     = { source = "hashicorp/aws",     version = "~> 5.0" }
    azurerm = { source = "hashicorp/azurerm", version = "~> 3.100" }
    google  = { source = "hashicorp/google",  version = "~> 5.0" }
  }
  backend "s3" {
    bucket = "tfstate-multicloud"
    key    = "prod.tfstate"
    region = "eu-central-1"
  }
}

provider "aws"     { region = "eu-central-1" }
provider "azurerm" { features {} }
provider "google"  { project = "myproject"; region = "europe-west1" }

# AWS - Primary region
module "aws_laravel" {
  source = "./modules/laravel-aws"
  environment = "production"
  region      = "eu-central-1"
}

# Azure - DR site
module "azure_laravel" {
  source = "./modules/laravel-azure"
  environment   = "production-dr"
  location      = "West Europe"
  is_active     = false
  min_replicas  = 2
}

# GCP - CDN origin / analytics
module "gcp_analytics" {
  source = "./modules/analytics-gcp"
  project_id = "myproject"
}

# Cloudflare - global DNS/LB
provider "cloudflare" { api_token = var.cloudflare_token }

resource "cloudflare_load_balancer" "laravel_api" {
  zone_id          = var.zone_id
  name             = "api.example.com"
  fallback_pool_id = cloudflare_load_balancer_pool.aws.id
  default_pool_ids = [cloudflare_load_balancer_pool.aws.id]

  region_pools {
    region   = "WEU"
    pool_ids = [cloudflare_load_balancer_pool.aws.id, cloudflare_load_balancer_pool.azure.id]
  }
  steering_policy = "geo"
}

resource "cloudflare_load_balancer_pool" "aws" {
  name = "aws-eu-central"
  origins {
    name    = "aws-alb"
    address = module.aws_laravel.alb_dns
    enabled = true
  }
  monitor = cloudflare_load_balancer_monitor.health.id
}

resource "cloudflare_load_balancer_pool" "azure" {
  name = "azure-westeu"
  origins {
    name    = "azure-agw"
    address = module.azure_laravel.agw_fqdn
    enabled = true
  }
  monitor = cloudflare_load_balancer_monitor.health.id
}
```

### Crossplane – Cloud-Agnostic Database

```yaml
# xrd.yaml - abstract resource
apiVersion: apiextensions.crossplane.io/v1
kind: CompositeResourceDefinition
metadata:
  name: xpostgresdatabases.db.example.com
spec:
  group: db.example.com
  names:
    kind: XPostgresDatabase
    plural: xpostgresdatabases
  claimNames:
    kind: PostgresDatabase
    plural: postgresdatabases
  versions:
    - name: v1alpha1
      served: true
      referenceable: true
      schema:
        openAPIV3Schema:
          type: object
          properties:
            spec:
              type: object
              properties:
                parameters:
                  type: object
                  properties:
                    storageGB:   { type: integer }
                    region:      { type: string  }
                    cloud:       { type: string, enum: [aws, azure, gcp] }
                    version:     { type: string  }
---
# Composition for AWS
apiVersion: apiextensions.crossplane.io/v1
kind: Composition
metadata:
  name: postgres-aws
  labels:
    provider: aws
spec:
  compositeTypeRef:
    apiVersion: db.example.com/v1alpha1
    kind: XPostgresDatabase
  resources:
    - name: rds-instance
      base:
        apiVersion: rds.aws.upbound.io/v1beta1
        kind: Instance
        spec:
          forProvider:
            engine: postgres
            engineVersion: "15"
            instanceClass: db.t3.medium
            allocatedStorage: 20
            skipFinalSnapshot: true
      patches:
        - fromFieldPath: spec.parameters.storageGB
          toFieldPath:  spec.forProvider.allocatedStorage
        - fromFieldPath: spec.parameters.region
          toFieldPath:  spec.forProvider.region
---
# Developer-in istifadəsi (cloud-agnostic)
apiVersion: db.example.com/v1alpha1
kind: PostgresDatabase
metadata:
  name: laravel-prod
spec:
  parameters:
    storageGB: 50
    region: eu-central-1
    cloud: aws        # və ya azure / gcp
    version: "15"
```

### Global Traffic Routing (Cloudflare + Origin health)

```hcl
resource "cloudflare_load_balancer_monitor" "health" {
  type           = "https"
  expected_codes = "200"
  method         = "GET"
  path           = "/healthz"
  interval       = 60
  retries        = 2
  timeout        = 5
  header {
    header = "Host"
    values = ["api.example.com"]
  }
}
```

### Multi-cloud Kubernetes (Cluster Mesh)

```yaml
# cluster-1 AWS EKS, cluster-2 Azure AKS
# Cilium ClusterMesh - pod-to-pod cross-cluster

# Install on both clusters:
cilium install --set cluster.name=aws-prod   --set cluster.id=1
cilium install --set cluster.name=azure-prod --set cluster.id=2
cilium clustermesh enable --context aws
cilium clustermesh enable --context azure
cilium clustermesh connect --context aws --destination-context azure

# Global service – failover to another cluster
apiVersion: v1
kind: Service
metadata:
  name: payment-service
  namespace: production
  annotations:
    service.cilium.io/global: "true"
    service.cilium.io/affinity: "local"   # əvvəl lokal, sonra remote
spec:
  type: ClusterIP
  ports: [{port: 8080}]
  selector: {app: payment-service}
```

### PostgreSQL Cross-Cloud Replication

```bash
# Primary AWS RDS-də logical replication aktiv et
# parameter group: rds.logical_replication = 1

# Primary-də publication yarat
PGPASSWORD=xxx psql -h primary.rds.amazonaws.com -U postgres -c "
  CREATE PUBLICATION laravel_pub FOR ALL TABLES;
"

# Replica Azure Flexible Server-da subscription
PGPASSWORD=xxx psql -h replica.postgres.database.azure.com -U postgres -c "
  CREATE SUBSCRIPTION laravel_sub
  CONNECTION 'host=primary.rds.amazonaws.com port=5432 dbname=laravel user=replicator password=xxx'
  PUBLICATION laravel_pub;
"

# Monitor replication lag
psql -c "SELECT * FROM pg_stat_replication;"  # primary-də
psql -c "SELECT * FROM pg_stat_subscription;" # replica-da
```

## PHP/Laravel ilə İstifadə

### Laravel multi-cloud config

```php
// config/cloud.php
return [
    'primary' => env('CLOUD_PRIMARY', 'aws'),

    'aws' => [
        'region' => env('AWS_DEFAULT_REGION', 'eu-central-1'),
        'bucket' => env('AWS_BUCKET'),
    ],
    'azure' => [
        'region' => env('AZURE_REGION', 'westeurope'),
        'container' => env('AZURE_STORAGE_CONTAINER'),
    ],
    'gcp' => [
        'project' => env('GCP_PROJECT'),
        'bucket'  => env('GCP_STORAGE_BUCKET'),
    ],
];

// config/filesystems.php
'disks' => [
    's3-aws' => [
        'driver' => 's3',
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
    ],
    'azure' => [
        'driver'    => 'azure',
        'name'      => env('AZURE_STORAGE_NAME'),
        'key'       => env('AZURE_STORAGE_KEY'),
        'container' => env('AZURE_STORAGE_CONTAINER'),
    ],
    'gcp' => [
        'driver'     => 'gcs',
        'project_id' => env('GCP_PROJECT'),
        'key_file'   => env('GCP_KEY_FILE'),
        'bucket'     => env('GCP_STORAGE_BUCKET'),
    ],
    // Active: primary cloud
    'primary' => [
        'driver' => 'copy',
        'source' => env('CLOUD_PRIMARY') === 'azure' ? 'azure' : 's3-aws',
    ],
],
```

### Cloud-Agnostic Storage Service

```php
// app/Services/CloudStorage.php
namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class CloudStorage
{
    private Filesystem $disk;

    public function __construct(?string $provider = null)
    {
        $provider = $provider ?? config('cloud.primary');
        $this->disk = match($provider) {
            'aws'   => Storage::disk('s3-aws'),
            'azure' => Storage::disk('azure'),
            'gcp'   => Storage::disk('gcp'),
            default => throw new \InvalidArgumentException("Unknown cloud: $provider"),
        };
    }

    public function put(string $path, string $contents): bool
    {
        return $this->disk->put($path, $contents);
    }

    public function url(string $path): string
    {
        return $this->disk->url($path);
    }

    /** Upload hər iki cloud-a (redundancy) */
    public function putRedundant(string $path, string $contents): array
    {
        $results = [];
        foreach (['s3-aws', 'azure'] as $diskName) {
            try {
                Storage::disk($diskName)->put($path, $contents);
                $results[$diskName] = true;
            } catch (\Throwable $e) {
                $results[$diskName] = false;
                \Log::error("Upload fail on {$diskName}", ['error' => $e->getMessage()]);
            }
        }
        return $results;
    }
}
```

### Circuit Breaker ilə cloud failover

```php
// app/Services/CloudFailover.php
use GuzzleHttp\Client;

class CloudFailover
{
    public function __construct(
        private string $primaryUrl,
        private string $secondaryUrl
    ) {}

    public function get(string $path): array
    {
        try {
            return $this->call($this->primaryUrl . $path, 'primary');
        } catch (\Throwable $e) {
            \Log::warning('Primary cloud failed, falling back', [
                'error' => $e->getMessage()
            ]);
            return $this->call($this->secondaryUrl . $path, 'secondary');
        }
    }

    private function call(string $url, string $label): array
    {
        $client = new Client(['timeout' => 5]);
        $response = $client->get($url);
        $body = json_decode($response->getBody()->getContents(), true);

        // Metrics: cloud_requests{target="aws", result="success"}
        \App\Metrics\CloudRequest::record($label, 'success');
        return $body;
    }
}
```

### CI/CD Pipeline for Multi-Cloud Deploy

```yaml
# .github/workflows/multi-cloud-deploy.yml
name: Deploy Laravel to Multi-Cloud

on:
  push:
    branches: [main]

jobs:
  build-image:
    runs-on: ubuntu-latest
    outputs:
      image_tag: ${{ steps.meta.outputs.image_tag }}
    steps:
      - uses: actions/checkout@v4
      - uses: docker/setup-buildx-action@v3
      - id: meta
        run: echo "image_tag=$(git rev-parse --short HEAD)" >> $GITHUB_OUTPUT

      - name: Build and push to all registries
        run: |
          TAG="${{ steps.meta.outputs.image_tag }}"
          # AWS ECR
          docker buildx build --push \
            -t 123.dkr.ecr.eu-central-1.amazonaws.com/laravel:$TAG \
            -t myazurereg.azurecr.io/laravel:$TAG \
            -t europe-west1-docker.pkg.dev/proj/repo/laravel:$TAG \
            --platform linux/amd64,linux/arm64 \
            .

  deploy-aws:
    needs: build-image
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to EKS
        run: |
          aws eks update-kubeconfig --name aws-prod --region eu-central-1
          kubectl set image deployment/laravel-api laravel=.../laravel:${{ needs.build-image.outputs.image_tag }}

  deploy-azure:
    needs: build-image
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to AKS
        run: |
          az aks get-credentials --resource-group rg-prod --name aks-prod
          kubectl set image deployment/laravel-api laravel=.../laravel:${{ needs.build-image.outputs.image_tag }}
```

## Interview Sualları (Q&A)

**S1: Multi-cloud niyə (və niyə yox) seçilməlidir?**
C: **Niyə**: (1) vendor lock-in qorxusu, (2) regulatory – data residency, (3) DR resilience (region-wide outage), (4) best-of-breed, (5) M&A nəticəsi, (6) kommersiya leverage. **Niyə yox**: (1) **mürəkkəblik** – hər cloud-un fərqli API, IAM, networking modeli, (2) **komanda bacarığı** – bir cloud-da expert olmaq çətindir, üç cloud çətindir, (3) **data transfer cost** – cross-cloud trafik bahadır, (4) **eyni funksionallıq iki dəfə build** – observability, CI/CD, security hər cloud-da, (5) **"best-of-breed"** çox vaxt illuziondur – real həyatda hər cloud-da 3-5 xidmət istifadə olunur. Çox vaxt **single cloud + good DR** daha praktikdir.

**S2: Active-active və active-passive fərqi?**
C: **Active-active** – hər iki cloud **eyni anda** iş yükünü daşıyır, trafik split (DNS, Cloudflare LB). Üstünlük: nəzəriyyədə zero-downtime failover, sıfır RTO. Çətinlik: data consistency kritik (two-way replication, conflict resolution), CAP theorem-ə tabedir. **Active-passive** – primary işləyir, secondary standby-də. DR event-ində secondary aktivləşir. Üstünlük: daha sadə data model, tək source of truth. Çətinlik: secondary RTO var (dəqiqələr), data lag (RPO). Əksər şirkət başlayır active-passive ilə, active-active yalnız çox kritik sistemlər üçündür.

**S3: Vendor lock-in real problemdir, yoxsa müasir abstraction-lar bunu həll edib?**
C: Tam həll yoxdur. Kubernetes portability verir, amma managed services (RDS vs Cloud SQL) hələ fərqlidir. Terraform infra-code-u portativ edir, amma provider-ə bağlıdır. Real dünyada 70% portable, 30% cloud-specific – **"gravity well"** yaradır. Lock-in tipləri: texniki (migrasiya ağır), kommersiya (EDP kontrakt), operativ (komanda bilik). **Praktik yanaşma**: kritik yerlərdə portability saxla (PostgreSQL, Kubernetes, S3-compatible), qeyri-kritik yerlərdə best cloud service istifadə et (BigQuery, Lambda), migration üçün tam hazır olma, amma **escape hatch** saxla.

**S4: Multi-cloud networking necə həll edilir?**
C: Seçimlər (sadədən mürəkkəbə): (1) **Public Internet + VPN** – AWS VPN Gateway ↔ Azure VPN Gateway, IPSec tunel, ucuz amma latency dəyişkəndir, 1-2 Gbps. (2) **Dedicated circuit** – AWS Direct Connect + ExpressRoute, Equinix Fabric hub, sabit latency, 10+ Gbps, baha. (3) **Multi-cloud SD-WAN** – Aviatrix, Alkira – mərkəzi transit, hər cloud-da gateway. (4) **CDN overlay** – Cloudflare Magic WAN, servisləri CDN üzərindən birləşdir. Trade-off: cost vs latency vs bandwidth. Əksər orta sazilik üçün VPN kifayətdir; böyük enterprise üçün Equinix Fabric.

**S5: Crossplane nədir və niyə istifadə olunur?**
C: Crossplane – Kubernetes API-sini **control plane** kimi istifadə edərək cloud resurslarını (AWS RDS, Azure Storage, GCP VM) CRD şəklində idarə edir. `kubectl apply -f rds.yaml` → AWS RDS yaranır. Üstünlük: (1) Kubernetes-native, (2) RBAC, GitOps, Argo CD ilə uyğundur, (3) **Composition** ilə abstract platform yarada bilərsən ("PostgresDatabase" kind-ı hər cloud-da fərqli implementasiya). Dezavantaj: yeni layer (learning curve), debug çətindir. Terraform ilə müqayisə: Terraform declarative + imperative apply; Crossplane declarative + continuous reconciliation (K8s operator pattern).

**S6: Data replikasiyası cross-cloud necə idarə olunur?**
C: Strategiyalar: (1) **Database logical replication** – PostgreSQL publication/subscription, MySQL GTID. (2) **CDC tool** – Debezium Kafka-ya yazır, Kafka MirrorMaker başqa cloud-a replicate edir. (3) **Application-level** – dual write (riskli – consistency problemi), event sourcing (Kafka event log replicate). (4) **Managed solutions** – Cockroach DB, Yugabyte (multi-region, multi-cloud native). Data lag (RPO) tipik 1-60 saniyə. Write conflict həlli (CRDT, last-write-wins, vector clock) active-active-də kritikdir. Çox vaxt **single primary + read replicas** seçilir, çünki multi-writer həqiqətən çətindir.

**S7: Multi-cloud xərci necə idarə olunur?**
C: Challenges: (1) hər cloud-un öz billing dashboard-u var, birləşdirmək çətindir. (2) data egress fee – A cloud-dan B cloud-a çıxarış trafiki baha. (3) ayrı RI/SP hər cloud-da. Alətlər: **Vantage, Cloudability, Apptio Cloudability, CloudHealth** multi-cloud cost dashboard. **FinOps Foundation FOCUS** specification – multi-cloud billing standartı. Praktik: (1) tagging strategy hər cloud-da eyni, (2) unit economics – cost per request ölçüsü cloud-agnostic, (3) regular review, "bu işlə bir cloud bəs deyil?" sualını ver.

**S8: Hybrid cloud nədir və nə zaman istifadə olunur?**
C: **Hybrid** = on-prem + public cloud. Nə zaman lazımdır: (1) **Data sovereignty** – konkret ölkədə və ya sertifikatlı DC-də qalmalıdır, (2) **Legacy systems** – mainframe, specific hardware (GPU cluster), (3) **Low latency** – sənaye avtomatlaşdırma, trade platform, (4) **Regulatory** – HIPAA, FedRAMP, (5) **Edge computing** – retail, manufacturing. Alətlər: **AWS Outposts** (AWS hardware DC-də), **Azure Arc** (existing resurs Azure-da idarə et), **GCP Anthos** (GKE-ni on-prem-də). Trend: "sovereign cloud" – data residency + hybrid.

**S9: Kubernetes multi-cluster patterns nələrdir?**
C: (1) **Federation v2 (KubeFed)** – mərkəzi API, bütün cluster-lara paylayır; mürəkkəb, aktiv deyil. (2) **Argo CD ApplicationSet** – GitOps-la hər cluster-a eyni manifest apply. (3) **Cluster Mesh (Cilium/Linkerd/Istio)** – pod-to-pod cross-cluster networking. (4) **Service Mesh multi-cluster** – Istio multi-primary, mTLS hər yerdə. (5) **Red Hat ACM / Rancher** – enterprise multi-cluster platform. Əksər şirkət (3)+(4) kombinasiyası seçir – GitOps deploy + service mesh networking. Tam federation sadə olmadığı üçün azad edilir.

**S10: Multi-cloud strategiyasını **yalnız** vendor lock-in üçün qurmaq doğrudurmu?**
C: **Xeyir** – tək lock-in motivi kifayət deyil. Multi-cloud-un **real xərci** (operativ mürəkkəblik, komanda bacarığı, aylıq $100k+ əlavə tool cost) lock-in-dən gələn teorik riskdən çox vaxt böyükdür. Strategiya **portability saxlamaq** (Kubernetes, Terraform, PostgreSQL) + **single cloud işlətmək**. Əgər real regulator, M&A, kommersiya leverage səbəbi varsa multi-cloud məqsədəuyğundur. Qərar matrisi: 3+ konkret motiv varsa – multi-cloud; yoxsa – single cloud + good DR plan + portability discipline.

## Best Practices

1. **Portability-first mindset** – kritik yerlərdə cloud-specific lock-in-dən qaç.
2. **Kubernetes + Terraform + Docker** – multi-cloud-un texniki əsası.
3. **Single identity** – OIDC / Keycloak / Okta mərkəzi, hər cloud-da IAM federation.
4. **Observability mərkəzləşmiş** – Grafana + Prometheus + Loki multi-cloud data source.
5. **CI/CD cloud-agnostic** – GitHub Actions / GitLab CI her cloud-a deploy.
6. **Secrets management** – Vault self-hosted və ya multi-cloud KMS.
7. **Cost visibility** – Vantage/Cloudability multi-cloud dashboard.
8. **Data replication strategy** – RPO/RTO-ya görə tools seç.
9. **Network design əvvəlcədən** – VPN, Direct Connect, SD-WAN ilkin planla.
10. **Single cloud üçün escape plan saxla** – migration-test kvartalda bir.
11. **Tag consistency** – hər cloud-da eyni schema (Environment, Owner, CostCenter).
12. **Policy as code** – OPA policy-ləri hər cloud-da eyni qaydaları tətbiq etsin.
13. **Chaos engineering** – cross-cloud failover kvartalda bir məşq et.
14. **Documentation critical** – hər cloud-a deploy prosesi, runbook yazılı olmalı.
15. **Komanda training** – cloud-specific expertise çox olmadan multi-cloud riskli.

---

## Praktik Tapşırıqlar

1. Multi-cloud disaster recovery planı hazırlayın: primary AWS, DR GCP; aktiv-passiv arxitektura — RTO < 1 saat, RPO < 15 dəqiqə; data replication strategiyası (DB logical replication, S3 → GCS cross-cloud sync); failover runbook yazın; ildə iki dəfə DR drill keçirin
2. Crossplane ilə cloud-agnostic infrastructure yazın: `provider-aws` + `provider-gcp` quruyun; eyni `Composition` ilə AWS RDS və GCP Cloud SQL yaradın; `CompositeResourceDefinition` (XRD) — developer yalnız `db-size: small/medium/large` seçir, cloud abstracted olur
3. Vendor lock-in audit edin: mövcud arxitekturada AWS-specific component-ləri siyahılayın (SQS, SES, Cognito, DynamoDB); hər biri üçün cloud-agnostic alternativ (RabbitMQ, SMTP, Laravel Socialite, PostgreSQL) tapın; migration xərci hesablayın
4. Multi-cloud network qurun: AWS VPC ↔ GCP VPC arası VPN (AWS VPN Gateway + GCP Cloud VPN); latency ölçün (`ping` + `iperf3`); private IP-lərlə servislərin bir-birini görməsini yoxlayın; BGP routing konfigurasiyası
5. Cloud-agnostic monitoring qurun: Prometheus-u ikinci cloud-da da işlədin; Thanos `sidecar` + `querier` ilə mərkəzləşdirilmiş metrics sorğusu; `external_labels: {cloud: aws}` ilə cloud-specific filtrasiya; Grafana-da multi-cloud dashboard yaradın
6. Active-active database replikasiyası test edin: AWS Aurora Global Database + GCP ilə logical replication simulyasiyası; write conflict resolution strategiyası; primary region down olduqda failover zamanını ölçün; Laravel DB connection pool-un failover-i necə idarə etdiyini görün

## Əlaqəli Mövzular

- [AWS Əsasları](14-aws-basics.md) — AWS-specific servisler
- [GCP Əsasları](15-gcp-basics.md) — GCP-specific servisler
- [Azure Əsasları](16-azure-basics.md) — Azure-specific servisler
- [Terraform Advanced](24-terraform-advanced.md) — multi-provider Terraform konfigurasiyası
- [FinOps](36-finops.md) — multi-cloud cost optimization
- [Site Reliability](34-site-reliability.md) — multi-cloud reliability, DR
