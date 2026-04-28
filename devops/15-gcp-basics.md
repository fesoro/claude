# GCP Əsasları (Middle)

## Nədir? (What is it?)

Google Cloud Platform – Google-un açıq cloud infrastructure platformasıdır (2008). AWS və Azure ilə birlikdə üç böyük cloud provayderindən biridir. Google-un öz infrastructure-ını (YouTube, Gmail, Search) təmin edən texnologiyalardan yaradılıb – məsələn, Kubernetes (Borg-dan), BigQuery (Dremel-dən), Spanner. GCP güclü tərəfləri: data analytics (BigQuery), ML/AI (Vertex AI), Kubernetes (GKE), sərfəli qiymətləndirmə və sustained use discounts.

## Əsas Konseptlər (Key Concepts)

### GCP Hiyerarşiyası

```
Organization
   └─ Folder(s)         (departments, environments)
       └─ Project(s)    (billing, API, resources)
           └─ Resource  (VM, bucket, database)

Hər resource bir Project-ə bağlıdır.
Billing Account bir və ya çox Project-ə bağlanır.
```

### Compute xidmətləri

```
COMPUTE ENGINE (IaaS) – Virtual Machines
   AWS EC2-nin ekvivalenti
   n1-standard-2, e2-medium, c2-standard-4 kimi machine type-lar
   Custom machine type (istədiyin qədər CPU/RAM)
   Preemptible / Spot VM: 60-91% endirim, 24 saatdan az
   Sustained use discount: avtomatik endirim uzunmüddətli istifadə üçün

GKE (Google Kubernetes Engine) – Managed Kubernetes
   Kubernetes-in doğma evi (Google yaratmışdır)
   Autopilot mode: tam managed (Google node-ları idarə edir)
   Standard mode: node pool-ları özün idarə edirsən
   Node auto-upgrade, auto-repair

CLOUD RUN – Stateless Containers (serverless)
   Container deploy edirsən, Google ölçeklendirir (0-a kimi)
   Pay-per-use (millisecond billing)
   Knative əsasında
   HTTP request-driven

CLOUD FUNCTIONS – FaaS
   Kod yaz, trigger əsasında çalış
   HTTP, Pub/Sub, Cloud Storage event
   Node.js, Python, Go, Java, PHP, Ruby
```

### Storage xidmətləri

```
CLOUD STORAGE (GCS) – Object storage
   AWS S3 ekvivalenti
   Storage class-lar:
   - Standard: tez-tez giriş
   - Nearline: ayda 1 dəfə (30 gün min)
   - Coldline: rübdə 1 dəfə (90 gün min)
   - Archive: ildə 1 dəfə (365 gün min)
   Bucket-lar region/multi-region olur

PERSISTENT DISK – Block storage (VM üçün)
   Standard (HDD), Balanced, SSD, Extreme
   Snapshot dəstəyi

FILESTORE – Managed NFS
   AWS EFS ekvivalenti
```

### Database xidmətləri

```
CLOUD SQL – Managed MySQL/PostgreSQL/SQL Server
   Auto backup, HA (failover replica), read replicas
   Private IP dəstəyi (VPC)
   Laravel üçün ideal RDBMS

CLOUD SPANNER – Global distributed SQL
   Horizontal scale + ACID + SQL
   Multi-region consistency (TrueTime)
   Baha, amma unikal kombinasiya

FIRESTORE – NoSQL document DB
   Real-time sync, mobile-friendly
   Serverless, autoscale

BIGTABLE – NoSQL wide-column
   Petabyte scale
   Time-series, analytics

MEMORYSTORE – Managed Redis/Memcached
   ElastiCache ekvivalenti
```

### Şəbəkə (Networking)

```
VPC (Virtual Private Cloud):
   Global (AWS-dən fərqli) – bir VPC bütün region-larda
   Subnet region-ə bağlıdır
   Shared VPC: bir VPC çox project-də

CLOUD LOAD BALANCING:
   Global HTTP(S) LB (anycast IP, bütün dünyada)
   Regional internal LB
   Network LB (L4)

CLOUD CDN: global content caching
CLOUD ARMOR: DDoS və WAF
CLOUD DNS: managed DNS
CLOUD INTERCONNECT: dedicated bağlantı on-prem ilə
```

### Identity və Security

```
IAM (Identity and Access Management):
   Principal (kim) → Role (nə edə bilər) → Resource (nəyə)
   
   Role növləri:
   - Basic: Owner, Editor, Viewer (çox geniş, tövsiyə olunmur)
   - Predefined: role/storage.objectViewer (konkret xidmət)
   - Custom: özün tərtib edirsən

SERVICE ACCOUNT:
   Application üçün "user" – VM, GKE pod, Cloud Run istifadə edir
   Key ilə (JSON) və ya Workload Identity ilə

SECRET MANAGER:
   Secrets (password, API key) saxlayır
   Version-lu, IAM nəzarət, audit log
   Laravel config üçün ideal

CLOUD KMS: encryption key management
CLOUD IDENTITY: user directory (SSO, MFA)
```

### Build və Deploy

```
CLOUD BUILD:
   CI/CD service – container build, deploy
   cloudbuild.yaml faylında pipeline
   GitHub/Bitbucket/GitLab inteqrasiyası

ARTIFACT REGISTRY:
   Docker image, npm, Maven, Python paket registry
   Container Registry-nin əvəzi

CLOUD DEPLOY:
   Progressive delivery (GKE, Cloud Run-a)
```

## Praktiki Nümunələr (Practical Examples)

### gcloud CLI Əsasları

```bash
# Auth
gcloud auth login
gcloud auth application-default login
gcloud config set project my-project

# VM yarat
gcloud compute instances create web-1 \
  --zone=europe-west1-b \
  --machine-type=e2-medium \
  --image-family=debian-12 \
  --image-project=debian-cloud \
  --tags=http-server

# Firewall
gcloud compute firewall-rules create allow-http \
  --allow=tcp:80 \
  --target-tags=http-server

# GKE cluster yarat
gcloud container clusters create-auto laravel-cluster \
  --region=europe-west1

# Cloud Run deploy
gcloud run deploy laravel-app \
  --image=gcr.io/my-project/laravel-app:v1 \
  --region=europe-west1 \
  --allow-unauthenticated
```

### Terraform GCP

```hcl
terraform {
  required_providers {
    google = { source = "hashicorp/google", version = "~> 5.0" }
  }
  backend "gcs" {
    bucket = "my-tfstate"
    prefix = "prod"
  }
}

provider "google" {
  project = "my-project"
  region  = "europe-west1"
}

resource "google_compute_network" "vpc" {
  name                    = "laravel-vpc"
  auto_create_subnetworks = false
}

resource "google_compute_subnetwork" "subnet" {
  name          = "laravel-subnet"
  ip_cidr_range = "10.0.0.0/24"
  region        = "europe-west1"
  network       = google_compute_network.vpc.id
}

resource "google_sql_database_instance" "laravel_db" {
  name             = "laravel-db"
  database_version = "MYSQL_8_0"
  region           = "europe-west1"
  settings {
    tier              = "db-custom-2-8192"
    availability_type = "REGIONAL"
    backup_configuration {
      enabled                        = true
      point_in_time_recovery_enabled = true
    }
  }
  deletion_protection = true
}

resource "google_container_cluster" "gke" {
  name     = "laravel-gke"
  location = "europe-west1"
  enable_autopilot = true
  network    = google_compute_network.vpc.id
  subnetwork = google_compute_subnetwork.subnet.id
}
```

### Cloud Run Konfiqurasiyası (YAML)

```yaml
apiVersion: serving.knative.dev/v1
kind: Service
metadata:
  name: laravel-app
  namespace: my-project
spec:
  template:
    metadata:
      annotations:
        autoscaling.knative.dev/minScale: "1"
        autoscaling.knative.dev/maxScale: "100"
        run.googleapis.com/cloudsql-instances: "my-project:europe-west1:laravel-db"
    spec:
      containerConcurrency: 80
      containers:
        - image: gcr.io/my-project/laravel-app:v2
          ports: [{ containerPort: 8080 }]
          resources:
            limits:
              cpu: "2"
              memory: "1Gi"
          env:
            - name: APP_ENV
              value: production
            - name: DB_CONNECTION
              value: mysql
            - name: DB_SOCKET
              value: /cloudsql/my-project:europe-west1:laravel-db
            - name: APP_KEY
              valueFrom:
                secretKeyRef:
                  name: app-key
                  key: latest
```

### Cloud Build Pipeline

```yaml
# cloudbuild.yaml
steps:
  - name: "gcr.io/cloud-builders/docker"
    args:
      - "build"
      - "-t"
      - "gcr.io/$PROJECT_ID/laravel-app:$SHORT_SHA"
      - "."
  
  - name: "gcr.io/cloud-builders/docker"
    args: ["push", "gcr.io/$PROJECT_ID/laravel-app:$SHORT_SHA"]
  
  - name: "gcr.io/cloud-builders/gcloud"
    args:
      - "run"
      - "deploy"
      - "laravel-app"
      - "--image=gcr.io/$PROJECT_ID/laravel-app:$SHORT_SHA"
      - "--region=europe-west1"
      - "--service-account=laravel-sa@$PROJECT_ID.iam.gserviceaccount.com"

timeout: 1200s
options:
  machineType: "E2_HIGHCPU_8"
```

## PHP/Laravel ilə İstifadə

### Cloud Run üçün Dockerfile

```dockerfile
FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor

RUN docker-php-ext-install pdo pdo_mysql opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader
RUN php artisan config:cache && php artisan route:cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Cloud Run 8080 port istəyir
ENV PORT=8080
EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
```

### Laravel Cloud SQL Bağlantısı

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    // Cloud Run-da socket via /cloudsql/
    'unix_socket' => env('DB_SOCKET'),
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

### Secret Manager Integration

```php
// composer require google/cloud-secret-manager

use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;

class GcpSecretService
{
    private SecretManagerServiceClient $client;
    
    public function __construct()
    {
        $this->client = new SecretManagerServiceClient();
    }
    
    public function get(string $name): string
    {
        $projectId = env('GCP_PROJECT_ID');
        $secretName = $this->client->secretVersionName(
            $projectId,
            $name,
            'latest'
        );
        
        $response = $this->client->accessSecretVersion($secretName);
        return $response->getPayload()->getData();
    }
}

// Usage (bootstrap-da):
// .env yoxsa Secret Manager-dan oxu
if (app()->environment('production')) {
    $secrets = new GcpSecretService();
    config(['database.connections.mysql.password' => $secrets->get('db-password')]);
    config(['services.stripe.secret' => $secrets->get('stripe-key')]);
}
```

### Cloud Storage (S3 əvəzinə)

```php
// composer require league/flysystem-google-cloud-storage

// config/filesystems.php
'disks' => [
    'gcs' => [
        'driver' => 'gcs',
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'key_file' => env('GOOGLE_CLOUD_KEY_FILE'),
        'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
        'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX'),
        'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI'),
        'visibility' => 'public',
    ],
],

// Istifadə:
Storage::disk('gcs')->put('avatars/user-1.jpg', $content);
$url = Storage::disk('gcs')->temporaryUrl('avatars/user-1.jpg', now()->addMinutes(10));
```

### GKE üçün Workload Identity

```yaml
# Pod Service Account-a GCP Service Account mapping
apiVersion: v1
kind: ServiceAccount
metadata:
  name: laravel-ksa
  namespace: production
  annotations:
    iam.gke.io/gcp-service-account: laravel-gsa@my-project.iam.gserviceaccount.com
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  template:
    spec:
      serviceAccountName: laravel-ksa
      containers:
        - name: app
          image: gcr.io/my-project/laravel-app:v2
          # GOOGLE_APPLICATION_CREDENTIALS lazım deyil,
          # Workload Identity avtomatik auth verir
```

```bash
# Binding
gcloud iam service-accounts add-iam-policy-binding \
  laravel-gsa@my-project.iam.gserviceaccount.com \
  --role=roles/iam.workloadIdentityUser \
  --member="serviceAccount:my-project.svc.id.goog[production/laravel-ksa]"
```

## Interview Sualları (Q&A)

**S1: Cloud Run və GKE arasında necə seçim edək?**
C: **Cloud Run** stateless container-lər üçündür, avtomatik 0-a scale olunur, setup minimaldır. Laravel monolit, API, stateless işlər üçün idealdır. **GKE** tam Kubernetes imkanları (DaemonSet, StatefulSet, operator, mesh) verir – microservice arxitektura, mürəkkəb workload, xüsusi networking lazım olduqda. Kiçik-orta layihə üçün Cloud Run, kompleks microservice üçün GKE.

**S2: GCP VPC AWS VPC-dən necə fərqlənir?**
C: GCP VPC **global**-dir – bir VPC bütün region-larda ola bilər, subnet-lər region-specific. AWS-də VPC **region-specific**-dir. GCP yanaşması multi-region workload üçün asan (peering lazım deyil), amma network segmentation üçün diqqət tələb edir. Həm də GCP-də firewall rule-ları VPC səviyyəsindədir (AWS security group instance səviyyəsində).

**S3: Service Account key istifadəsi niyə tövsiyə olunmur?**
C: JSON key faylları secret-dir və sızması böyük risk yaradır (rotation çətindir, audit zəif). Bunun əvəzinə **Workload Identity** (GKE-də) və ya **Service Account impersonation** tövsiyə olunur – heç bir key yaradılmır. Kənar sistemlər üçün **Workload Identity Federation** (OIDC) – AWS, Azure, GitHub Actions bir-birinə key olmadan auth olur.

**S4: Preemptible VM nə vaxt istifadə etmək olar?**
C: **Preemptible (Spot) VM-lər** 60-91% ucuzdur, amma Google onları istənilən an (24 saat içində mütləq) söndürə bilər. Uyğundur: batch processing, CI/CD runner, fault-tolerant distributed system (Hadoop, Kubernetes node), dev/test. Uyğun deyil: production web server, database, session server.

**S5: Cloud Run maksimum request müddəti nədir, necə bypass etmək olar?**
C: Default maksimum 60 dəqiqədir (hər request 1 saatdan çox ola bilməz). Uzun işlər üçün: (1) **Cloud Run jobs** – batch job kimi işləyir, vaxt limiti daha çoxdur. (2) **Cloud Tasks** + Cloud Run – uzun işi hissələrə böl. (3) **GKE/Compute Engine** – vaxt məhdudiyyəti yoxdur.

**S6: BigQuery nə vaxt Cloud SQL-dən üstündür?**
C: **BigQuery** OLAP üçündür – petabyte miqyasda analytical query (SUM, AVG, GROUP BY milyardlarla sətir üzərində). **Cloud SQL** OLTP üçündür – tipik CRUD əməliyyatlar. Laravel əsas DB-si Cloud SQL olmalıdır; analytics və reporting üçün data BigQuery-ə pipe olunur (Dataflow, Pub/Sub).

**S7: GKE Autopilot və Standard fərqi nədir?**
C: **Autopilot** – Google node-ları (infrastructure) tam idarə edir, sən yalnız pod saxlayırsan. Pod-a görə ödəyirsən. Daha sadə, amma bəzi resurs məhdudiyyətləri var (DaemonSet limit, privileged pod yox). **Standard** – sən node pool-ları idarə edirsən, istənilən yapılandırma mümkündür, node başına ödəyirsən. Çox yönlü iş yükləri üçün Standard daha uyğundur.

**S8: Workload Identity nədir və niyə Service Account Key-dən yaxşıdır?**
C: Workload Identity GKE pod-una birbaşa GCP Service Account icazəsi verir, heç bir JSON key yaradılmadan. Key rotation problemi yoxdur, audit yaxşıdır, secret leakage riski azalır. GKE-də Kubernetes Service Account ↔ GCP Service Account binding qurulur, pod bu KSA-dan istifadə etdikdə avtomatik GCP credential əldə edir.

**S9: GCP global HTTP(S) load balancer-in fərqi nədir AWS ALB-dən?**
C: GCP global LB **anycast IP** istifadə edir – bütün dünyadan bir IP-yə request gəlir, Google ən yaxın region-a route edir. AWS ALB region-specific-dir, multi-region üçün Route53 və ya CloudFront lazımdır. Həm də GCP LB tək IP-də HTTP/2, gRPC, WebSocket dəstəkləyir, URL map ilə routing edir.

**S10: Laravel-i Cloud Run-a deploy edərkən nələrə diqqət etmək lazımdır?**
C: (1) **Stateless** olmalıdır – sessionlar Redis/Memorystore-a çıxarılmalı (not file). (2) **Cloud SQL** Unix socket (`/cloudsql/`) ilə bağlan. (3) `CONFIG_CACHE` və `ROUTE_CACHE` build zamanı run et (cold start-ı azalt). (4) **Max concurrency** düzgün qoy (Laravel-də 80-100 tipik). (5) **Startup probe** uzun olsa, `/health` endpoint yaz. (6) **Queue worker** Cloud Run-a uyğun deyil – Cloud Run jobs və ya GKE istifadə et. (7) Log-ları **stdout-a** yaz (Cloud Logging avtomatik toplayır).

## Best Practices

1. **Project hierarchy** – Organization → Folders (dev/staging/prod) → Projects.
2. **IAM principle of least privilege** – Basic Owner/Editor əvəzinə predefined və custom role.
3. **Service Account-lar minimal icazə** ilə, key əvəzinə Workload Identity.
4. **Secret Manager** işlət, .env-də həssas data saxlamağa son.
5. **VPC Service Controls** həssas service-lər üçün (exfiltration risk azalt).
6. **Cost management** – budget alert, committed use discount, sustained use.
7. **Organization policies** – guardrails (external IP icazə verilsin, yalnız EU region).
8. **Cloud Monitoring + Logging** default olaraq aktiv et.
9. **Backup strategiyası** – Cloud SQL automated backup + PITR.
10. **Multi-zone HA** – Cloud SQL regional, GKE regional cluster.
11. **Artifact Registry** istifadə et (Container Registry deprecated).
12. **Cloud Build + Cloud Deploy** CI/CD üçün.
13. **Infrastructure as Code** – Terraform state-i GCS-də saxla (versioning + locking).
14. **Audit Logs** incələ – kim, nə vaxt, nə etdi.
15. **CIS GCP Benchmark** ilə security posture yoxla.

---

## Praktik Tapşırıqlar

1. Cloud Run-da Laravel deploy edin: `Dockerfile` yazın (PHP 8.3 + Nginx), `gcloud run deploy`, `--add-cloudsql-instances` flag ilə Cloud SQL qoşun, `--set-env-vars` ilə environment variables qurun; URL-i `gcloud run services describe` ilə əldə edin
2. GKE Autopilot cluster qurun: `gcloud container clusters create-auto`, Helm ilə Laravel app deploy edin, `HorizontalPodAutoscaler` konfigurasiya edin; `kubectl get pods -w` ilə auto-scaling izləyin
3. Workload Identity konfigurasiya edin: Kubernetes ServiceAccount → GCP Service Account binding; `gcloud iam service-accounts add-iam-policy-binding`; pod-da `GOOGLE_APPLICATION_CREDENTIALS` olmadan GCS-ə yazma testi edin
4. Cloud Build pipeline yazın: `cloudbuild.yaml` — composer install, phpunit, Docker build+push (Artifact Registry), Cloud Run deploy; trigger-i GitHub push event-inə bağlayın; build logs-u real-time izləyin
5. Cloud SQL HA failover test edin: `gcloud sql instances failover` əmri işlədin; Laravel `DB_HOST`-u Cloud SQL proxy socket-ə bağladıqda failover zamanı connection-ların necə işlədiyini yoxlayın
6. GCP IAM Least Privilege qurun: custom role yaradın — yalnız `cloudsql.instances.connect`, `storage.objects.create`, `storage.objects.get`; service account-a bu rolu atayın; `gcloud projects get-iam-policy` ilə yoxlayın

## Əlaqəli Mövzular

- [AWS Əsasları](14-aws-basics.md) — AWS ilə müqayisə, migration
- [Azure Əsasları](16-azure-basics.md) — Azure ilə müqayisə
- [Terraform Əsasları](23-terraform-basics.md) — GCP Terraform provider
- [Container Security](29-container-security.md) — GKE pod security, Workload Identity
- [CI/CD Deployment](39-cicd-deployment.md) — Cloud Build pipeline
