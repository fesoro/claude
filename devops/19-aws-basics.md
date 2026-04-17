# AWS Əsasları (Amazon Web Services Basics)

## Nədir? (What is it?)

AWS dünyanın ən böyük cloud platformasıdır. 200+ xidmət təklif edir: hesablama (EC2), saxlama (S3), database (RDS), şəbəkə (VPC) və s. Laravel proyektlərini AWS-də deploy etmək üçün əsas xidmətləri bilmək vacibdir. Pay-as-you-go model ilə istifadə etdiyiniz qədər ödəyirsiniz.

## Əsas Konseptlər (Key Concepts)

### EC2 (Elastic Compute Cloud)

```bash
# EC2 = Virtual server (instance)
# AMI = Amazon Machine Image (server şablonu)
# Instance Type = Server ölçüsü

# Instance Types:
# t3.micro  - 2 vCPU, 1GB RAM    (Free tier, dev/test)
# t3.small  - 2 vCPU, 2GB RAM    (Kiçik Laravel app)
# t3.medium - 2 vCPU, 4GB RAM    (Orta Laravel app)
# t3.large  - 2 vCPU, 8GB RAM    (Production Laravel)
# m5.large  - 2 vCPU, 8GB RAM    (Ümumi təyinatlı)
# c5.large  - 2 vCPU, 4GB RAM    (CPU intensiv)
# r5.large  - 2 vCPU, 16GB RAM   (Memory intensiv, database)

# AWS CLI ilə EC2 yaratmaq
aws ec2 run-instances \
  --image-id ami-0c55b159cbfafe1f0 \
  --instance-type t3.medium \
  --key-name my-key \
  --security-group-ids sg-12345678 \
  --subnet-id subnet-12345678 \
  --count 1 \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=laravel-web}]'

# Instance-ları göstər
aws ec2 describe-instances --filters "Name=tag:Name,Values=laravel-*"

# Instance-ı dayandır/başlat
aws ec2 stop-instances --instance-ids i-1234567890
aws ec2 start-instances --instance-ids i-1234567890

# Key Pair yaratmaq
aws ec2 create-key-pair --key-name laravel-key --query 'KeyMaterial' --output text > laravel-key.pem
chmod 400 laravel-key.pem

# SSH ilə qoşulmaq
ssh -i laravel-key.pem ubuntu@ec2-xx-xx-xx-xx.compute.amazonaws.com

# EBS (Elastic Block Store) - disk
# gp3 - ümumi təyinatlı SSD (default)
# io2 - yüksək performanslı SSD (database)
# st1 - HDD, throughput optimized
# sc1 - cold HDD, ən ucuz

# Pricing models:
# On-Demand   - Saatlıq ödəniş, heç bir öhdəlik
# Reserved    - 1/3 il öhdəlik, 30-70% endirim
# Spot        - 90% endirim, amma dayandırıla bilər
# Savings Plan - Flexible reserved pricing
```

### S3 (Simple Storage Service)

```bash
# S3 = Object storage (fayllar üçün sonsuz saxlama)
# Bucket = fayl konteyneri, globally unique ad
# Object = fayl + metadata

# Bucket yaratmaq
aws s3 mb s3://laravel-app-storage-production

# Fayl yükləmək
aws s3 cp backup.tar.gz s3://laravel-app-storage-production/backups/
aws s3 sync /var/www/laravel/storage/app/public s3://laravel-app-storage-production/public/

# Faylları göstərmək
aws s3 ls s3://laravel-app-storage-production/

# Faylı silmək
aws s3 rm s3://laravel-app-storage-production/old-file.txt

# Storage Classes:
# S3 Standard         - Tez-tez istifadə olunan data
# S3 Standard-IA      - Nadir istifadə, amma tez lazım olanda
# S3 Glacier          - Arxiv (retrieve 1-5 dəqiqə)
# S3 Glacier Deep     - Uzunmüddətli arxiv (retrieve 12 saat)

# Lifecycle policy - köhnə faylları avtomatik ucuz storage-ə keçir
# Versioning - faylın köhnə versiyalarını saxla
# Server-side encryption - SSE-S3, SSE-KMS

# Public access policy (statik fayllar üçün)
# Bucket policy və ya CloudFront ilə
```

### RDS (Relational Database Service)

```bash
# RDS = Managed database (MySQL, PostgreSQL, MariaDB, Oracle, SQL Server, Aurora)
# Backups, patching, replication avtomatik

# MySQL RDS yaratmaq
aws rds create-db-instance \
  --db-instance-identifier laravel-db \
  --db-instance-class db.t3.medium \
  --engine mysql \
  --engine-version 8.0 \
  --master-username admin \
  --master-user-password 'SecretPass123!' \
  --allocated-storage 50 \
  --storage-type gp3 \
  --vpc-security-group-ids sg-12345678 \
  --multi-az \
  --backup-retention-period 7

# Endpoint-i göstər
aws rds describe-db-instances --db-instance-identifier laravel-db \
  --query 'DBInstances[0].Endpoint.Address' --output text

# Multi-AZ: avtomatik failover, standby replica
# Read Replica: oxuma performansı artırmaq üçün
# Automated backups: günlük snapshot + transaction log
# Aurora: MySQL/PostgreSQL uyğun, 5x performans, avtomatik scaling
```

### VPC (Virtual Private Cloud)

```
VPC = Öz virtual şəbəkəniz AWS-də

VPC (10.0.0.0/16)
├── Public Subnet 1 (10.0.1.0/24) - AZ-a
│   ├── EC2 (web server)
│   └── NAT Gateway
├── Public Subnet 2 (10.0.2.0/24) - AZ-b
│   └── EC2 (web server)
├── Private Subnet 1 (10.0.10.0/24) - AZ-a
│   └── RDS (MySQL primary)
├── Private Subnet 2 (10.0.11.0/24) - AZ-b
│   └── RDS (MySQL standby)
├── Internet Gateway        # VPC-ni internet-ə bağlayır
├── NAT Gateway             # Private subnet-lərin internetə çıxışı
├── Route Tables            # Trafik marşrutu
└── Network ACL             # Subnet səviyyəli firewall
```

```bash
# VPC yaratmaq
aws ec2 create-vpc --cidr-block 10.0.0.0/16
aws ec2 create-subnet --vpc-id vpc-xxx --cidr-block 10.0.1.0/24 --availability-zone eu-central-1a
aws ec2 create-internet-gateway
aws ec2 attach-internet-gateway --internet-gateway-id igw-xxx --vpc-id vpc-xxx
```

### IAM (Identity and Access Management)

```bash
# IAM = Kim nəyə giriş edə bilər

# Konseptlər:
# User     - İnsanlar üçün
# Group    - User qrupları
# Role     - Xidmətlər üçün (EC2, Lambda, ECS)
# Policy   - İcazə qaydaları (JSON)

# Policy nümunəsi - S3 bucket-ə giriş
cat <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::laravel-app-storage/*"
    },
    {
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::laravel-app-storage"
    }
  ]
}
EOF

# EC2 Instance Profile (Role)
# EC2-yə S3-ə giriş vermək üçün IAM Role yaratmaq
# Access key istifadə etmək əvəzinə Instance Profile istifadə edin!

# Best practices:
# - Root account istifadə etmə, IAM user yarat
# - MFA aktiv et
# - Least privilege principle - minimum icazə ver
# - Access key-ləri rotate et
# - Instance Profile istifadə et (EC2 üçün)
```

### Security Groups

```bash
# Security Group = Instance səviyyəli firewall
# Stateful: inbound rule əlavə etsən, outbound avtomatik icazə olur

# Web server SG
aws ec2 create-security-group \
  --group-name laravel-web-sg \
  --description "Laravel web server" \
  --vpc-id vpc-xxx

# Inbound rules
aws ec2 authorize-security-group-ingress --group-id sg-xxx \
  --protocol tcp --port 80 --cidr 0.0.0.0/0           # HTTP

aws ec2 authorize-security-group-ingress --group-id sg-xxx \
  --protocol tcp --port 443 --cidr 0.0.0.0/0          # HTTPS

aws ec2 authorize-security-group-ingress --group-id sg-xxx \
  --protocol tcp --port 22 --source-group sg-bastion   # SSH (bastion-dan)

# Database SG - yalnız web server SG-dən
aws ec2 authorize-security-group-ingress --group-id sg-db-xxx \
  --protocol tcp --port 3306 --source-group sg-web-xxx

# Layered security:
# Internet -> Security Group (instance) -> Network ACL (subnet) -> Route Table
```

### Route 53 (DNS)

```bash
# Route 53 = Managed DNS service

# Hosted zone yaratmaq
aws route53 create-hosted-zone --name example.com --caller-reference $(date +%s)

# DNS record yaratmaq
aws route53 change-resource-record-sets --hosted-zone-id Z1234567 \
  --change-batch '{
    "Changes": [{
      "Action": "CREATE",
      "ResourceRecordSet": {
        "Name": "app.example.com",
        "Type": "A",
        "TTL": 300,
        "ResourceRecords": [{"Value": "10.0.1.10"}]
      }
    }]
  }'

# Record types:
# A     - IPv4 address
# AAAA  - IPv6 address
# CNAME - Alias başqa domain-ə
# MX    - Mail server
# TXT   - Text record (SPF, DKIM)
# ALIAS - AWS-ə xas, root domain üçün (ELB, CloudFront)

# Routing policies:
# Simple     - Bir IP
# Weighted   - % ilə paylaşdır (A/B testing)
# Latency    - Ən yaxın region
# Failover   - Primary/Secondary
# Geolocation - Ölkəyə görə
```

### CloudFront (CDN)

```bash
# CloudFront = Content Delivery Network
# Static faylları (CSS, JS, images) dünyada edge server-lərdə cache edir
# S3 və ya EC2 ilə birlikdə istifadə olunur

# Laravel üçün:
# - public/build/ (Vite/Mix assets) -> CloudFront
# - S3 storage/public -> CloudFront
# - API response caching

# CloudFront + S3 Origin
# Distribution yaratmaq (console-dan daha asandır):
# Origin: S3 bucket
# Viewer Protocol: Redirect HTTP to HTTPS
# Cache Policy: CachingOptimized
# Alternate domain: cdn.example.com
# SSL: ACM certificate

# Invalidation (cache təmizləmə)
aws cloudfront create-invalidation --distribution-id EXXX \
  --paths "/build/*" "/images/*"
```

## Praktiki Nümunələr (Practical Examples)

### Laravel on AWS Arxitekturası

```
                    ┌──────────────┐
                    │  CloudFront  │ (CDN)
                    │   (static)   │
                    └──────┬───────┘
                           │
Internet ──── Route53 ─── ALB (Application Load Balancer)
                           │
              ┌────────────┼────────────┐
              │            │            │
         ┌────┴────┐ ┌────┴────┐ ┌────┴────┐
         │  EC2-1  │ │  EC2-2  │ │  EC2-3  │  (Auto Scaling Group)
         │ (Laravel)│ │ (Laravel)│ │ (Laravel)│
         └────┬────┘ └────┬────┘ └────┬────┘
              │            │            │
         ┌────┴────────────┴────────────┴────┐
         │          Private Subnet           │
         │  ┌─────────┐    ┌──────────┐     │
         │  │  RDS     │    │ElastiCache│     │
         │  │ (MySQL)  │    │ (Redis)   │     │
         │  └─────────┘    └──────────┘     │
         │                                   │
         │  ┌─────────┐    ┌──────────┐     │
         │  │   SQS    │    │    S3    │     │
         │  │ (Queue)  │    │ (Storage)│     │
         │  └─────────┘    └──────────┘     │
         └───────────────────────────────────┘
```

## PHP/Laravel ilə İstifadə

### Laravel AWS Konfiqurasiyası

```php
<?php
// .env (AWS)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=laravel-app-storage
AWS_USE_PATH_STYLE_ENDPOINT=false

// config/filesystems.php
'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),       // CloudFront URL
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
    ],
],

// İstifadə
use Illuminate\Support\Facades\Storage;

// Upload
Storage::disk('s3')->put('avatars/user1.jpg', $fileContent);
$path = Storage::disk('s3')->putFile('avatars', $request->file('avatar'));

// URL
$url = Storage::disk('s3')->url('avatars/user1.jpg');
$tempUrl = Storage::disk('s3')->temporaryUrl('avatars/user1.jpg', now()->addMinutes(5));

// Download
$content = Storage::disk('s3')->get('avatars/user1.jpg');

// Delete
Storage::disk('s3')->delete('avatars/user1.jpg');
```

### Laravel SQS Queue

```php
<?php
// .env
QUEUE_CONNECTION=sqs
SQS_PREFIX=https://sqs.eu-central-1.amazonaws.com/123456789012
SQS_QUEUE=laravel-jobs
AWS_DEFAULT_REGION=eu-central-1

// config/queue.php
'sqs' => [
    'driver' => 'sqs',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'prefix' => env('SQS_PREFIX'),
    'queue' => env('SQS_QUEUE', 'default'),
    'suffix' => env('SQS_SUFFIX'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
],
```

## Interview Sualları

### S1: EC2 instance type-lar arasında fərq nədir?
**C:** T seriyası (t3, t4g): burst performans, ümumi təyinatlı, dev/kiçik production. M seriyası (m5, m6i): balanced CPU/memory, ümumi production. C seriyası (c5, c6i): CPU-optimized, hesablama ağırlıqlı. R seriyası (r5, r6i): memory-optimized, database/cache. I seriyası: storage-optimized, yüksək I/O. Laravel web server üçün t3.medium/large, database üçün r5.large yaxşıdır.

### S2: Security Group və Network ACL arasında fərq nədir?
**C:** **Security Group**: instance səviyyəli, stateful (outbound avtomatik), yalnız allow qaydaları, bütün qaydalar qiymətləndirilir. **Network ACL**: subnet səviyyəli, stateless (inbound/outbound ayrı), allow/deny qaydaları, nömrə sırasına görə qiymətləndirilir. Security Group daha çox istifadə olunur. NACL əlavə təhlükəsizlik qatıdır.

### S3: S3 storage class-ları nə vaxt istifadə olunur?
**C:** **Standard**: tez-tez istifadə (aktiv upload-lar). **Standard-IA**: nadir istifadə amma tez lazım (30+ gün köhnə backup). **Glacier Instant**: arxiv amma millisecond access. **Glacier Flexible**: 1-5 dəqiqə access (aylar-illər köhnə data). **Glacier Deep Archive**: 12 saat access, ən ucuz (compliance data). Lifecycle policy ilə avtomatik keçid. Laravel-də aktiv fayllar Standard, köhnə backup-lar Glacier.

### S4: VPC-də public və private subnet fərqi nədir?
**C:** **Public subnet**: Internet Gateway-ə route var, instance-lar public IP ala bilər, internet-dən birbaşa giriş. Web server-lər burada. **Private subnet**: Internet-ə birbaşa çıxış yox, NAT Gateway vasitəsilə çıxış (update yükləmə), internet-dən giriş yox. Database, cache server-ləri burada. Laravel: web EC2 public, RDS/Redis private subnet-də.

### S5: IAM Best Practice-lər nədir?
**C:** 1) Root account istifadə etmə, MFA aktiv et, 2) Least privilege - minimum lazımlı icazə, 3) IAM Role > Access Key (EC2 üçün Instance Profile), 4) Group-lara policy attach et (user-lara yox), 5) Access key-ləri rotate et (90 gün), 6) Unused credentials-ı sil, 7) Policy conditions istifadə et (IP, MFA, time), 8) CloudTrail aktiv et (audit log).

### S6: Multi-AZ və Read Replica fərqi nədir?
**C:** **Multi-AZ**: high availability üçün - standby replica başqa AZ-da, avtomatik failover (30-60 saniyə), read traffic vermir, əsas DB çökəndə avtomatik keçir. **Read Replica**: performans üçün - read-only kopyalar, read traffic paylaşdırır, ayrı endpoint, manual promote. Laravel-də: Multi-AZ production DB üçün mütləqdir, Read Replica yüksək oxuma yükü olduqda.

## Best Practices

1. **Multi-AZ** istifadə edin - Database və kritik xidmətlər üçün
2. **IAM Role** istifadə edin - Access key-lər əvəzinə
3. **VPC dizayn** edin - Public/private subnet, NAT Gateway
4. **Security Group** düzgün konfiqurasiya edin - Minimum açıq port
5. **S3 versioning** aktiv edin - Təsadüfi silmələrdən qorunma
6. **CloudWatch** monitoring qurun - CPU, memory, disk alert
7. **Tags** istifadə edin - Environment, Team, Cost Center
8. **Reserved Instances** alın - Production üçün endirim
9. **Backup** strategiyası qurun - RDS snapshot, S3 lifecycle
10. **Cost monitoring** edin - AWS Budgets ilə xərc limiti
