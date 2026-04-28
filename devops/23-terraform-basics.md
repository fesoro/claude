# Terraform Əsasları (Senior)

## Nədir? (What is it?)

Terraform HashiCorp tərəfindən yaradılmış Infrastructure as Code (IaC) alətidir. HCL (HashiCorp Configuration Language) ilə infrastrukturu kod kimi təsvir edib, avtomatik yaradır, dəyişir və silir. AWS, Azure, GCP, DigitalOcean və 100+ provider dəstəkləyir. Deklarativ yanaşma: "nə istəyirəm" yazırsınız, Terraform "necə edəcəyini" həll edir.

## Əsas Konseptlər (Key Concepts)

### IaC (Infrastructure as Code) Konsepti

```
Manual İnfrastruktur:
- AWS Console-a gir -> EC2 yarat -> Security Group əlavə et -> ...
- Təkrar etmək çətin, xəta baş verə bilər
- Sənədləşdirmə yox, audit trail yox

Infrastructure as Code:
- Kod fayllarında infrastruktur təsvir et
- git ilə version control
- Code review, audit trail
- Təkrar yaratmaq asandır
- Müxtəlif mühitlər (dev, staging, prod) eyni kodla

IaC Alətləri:
- Terraform - Multi-cloud, deklarativ
- AWS CloudFormation - Yalnız AWS
- Pulumi - Proqramlaşdırma dilləri ilə (Python, Go, TS)
- Ansible - Prosedural, konfiqurasiya idarəsi
```

### Terraform İş Axını

```
terraform init     -> Provider plugin-lərini yüklə
terraform plan     -> Nə dəyişəcəyini göstər (dry-run)
terraform apply    -> Dəyişiklikləri tətbiq et
terraform destroy  -> Bütün resursları sil

Write -> Plan -> Apply döngüsü
```

### Quraşdırma

```bash
# Ubuntu/Debian
wget -O- https://apt.releases.hashicorp.com/gpg | sudo gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/hashicorp.list
sudo apt update && sudo apt install terraform

# Versiya
terraform version

# Autocomplete
terraform -install-autocomplete
```

### Provider

```hcl
# providers.tf
terraform {
  required_version = ">= 1.5.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"    # 5.x versiyalar
    }
  }
}

provider "aws" {
  region  = "eu-central-1"
  profile = "production"     # AWS CLI profile

  default_tags {
    tags = {
      Environment = "production"
      ManagedBy   = "terraform"
      Project     = "laravel-app"
    }
  }
}

# Multi-region
provider "aws" {
  alias  = "us_east"
  region = "us-east-1"
}
```

### Resources

```hcl
# main.tf

# VPC
resource "aws_vpc" "main" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name = "laravel-vpc"
  }
}

# Subnet
resource "aws_subnet" "public" {
  count                   = 2
  vpc_id                  = aws_vpc.main.id
  cidr_block              = "10.0.${count.index + 1}.0/24"
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  map_public_ip_on_launch = true

  tags = {
    Name = "public-subnet-${count.index + 1}"
  }
}

# Security Group
resource "aws_security_group" "web" {
  name        = "laravel-web-sg"
  description = "Security group for Laravel web servers"
  vpc_id      = aws_vpc.main.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["10.0.0.0/16"]    # Yalnız VPC daxilindən
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

# EC2 Instance
resource "aws_instance" "web" {
  count                  = 2
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = var.instance_type
  key_name               = aws_key_pair.deploy.key_name
  vpc_security_group_ids = [aws_security_group.web.id]
  subnet_id              = aws_subnet.public[count.index].id

  root_block_device {
    volume_size = 30
    volume_type = "gp3"
  }

  user_data = templatefile("${path.module}/scripts/init.sh", {
    app_env = var.environment
  })

  tags = {
    Name = "laravel-web-${count.index + 1}"
  }
}

# RDS (MySQL)
resource "aws_db_instance" "mysql" {
  identifier             = "laravel-db"
  engine                 = "mysql"
  engine_version         = "8.0"
  instance_class         = "db.t3.medium"
  allocated_storage      = 50
  max_allocated_storage  = 200
  storage_type           = "gp3"

  db_name  = "laravel"
  username = var.db_username
  password = var.db_password

  vpc_security_group_ids = [aws_security_group.db.id]
  db_subnet_group_name   = aws_db_subnet_group.main.name

  backup_retention_period = 7
  multi_az               = true
  skip_final_snapshot    = false
  final_snapshot_identifier = "laravel-db-final"

  tags = {
    Name = "laravel-mysql"
  }
}

# S3 Bucket
resource "aws_s3_bucket" "storage" {
  bucket = "laravel-app-storage-${var.environment}"
}

resource "aws_s3_bucket_versioning" "storage" {
  bucket = aws_s3_bucket.storage.id
  versioning_configuration {
    status = "Enabled"
  }
}

# ElastiCache (Redis)
resource "aws_elasticache_cluster" "redis" {
  cluster_id           = "laravel-redis"
  engine               = "redis"
  node_type            = "cache.t3.micro"
  num_cache_nodes      = 1
  port                 = 6379
  security_group_ids   = [aws_security_group.redis.id]
  subnet_group_name    = aws_elasticache_subnet_group.main.name
}
```

### Variables

```hcl
# variables.tf

variable "environment" {
  description = "Environment name (dev, staging, production)"
  type        = string
  default     = "development"

  validation {
    condition     = contains(["development", "staging", "production"], var.environment)
    error_message = "Environment must be development, staging, or production."
  }
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.medium"
}

variable "instance_count" {
  description = "Number of web server instances"
  type        = number
  default     = 2
}

variable "db_username" {
  description = "Database master username"
  type        = string
  sensitive   = true
}

variable "db_password" {
  description = "Database master password"
  type        = string
  sensitive   = true
}

variable "allowed_cidr_blocks" {
  description = "CIDR blocks allowed for SSH"
  type        = list(string)
  default     = ["10.0.0.0/16"]
}

variable "tags" {
  description = "Common tags"
  type        = map(string)
  default = {
    Project   = "laravel"
    ManagedBy = "terraform"
  }
}
```

```hcl
# terraform.tfvars
environment    = "production"
instance_type  = "t3.large"
instance_count = 3

# terraform.tfvars-da sensitive dəyişənlər OLMAMALIDIR!
# Bunlar üçün: environment variables, Vault, AWS Secrets Manager

# Environment variable ilə
# export TF_VAR_db_username="admin"
# export TF_VAR_db_password="secret123"
```

### Outputs

```hcl
# outputs.tf

output "vpc_id" {
  description = "VPC ID"
  value       = aws_vpc.main.id
}

output "web_server_ips" {
  description = "Public IPs of web servers"
  value       = aws_instance.web[*].public_ip
}

output "db_endpoint" {
  description = "RDS endpoint"
  value       = aws_db_instance.mysql.endpoint
}

output "redis_endpoint" {
  description = "ElastiCache Redis endpoint"
  value       = aws_elasticache_cluster.redis.cache_nodes[0].address
}

output "s3_bucket_name" {
  description = "S3 bucket for file storage"
  value       = aws_s3_bucket.storage.bucket
}

output "laravel_env" {
  description = "Laravel .env values for deployment"
  value = <<-EOT
    DB_HOST=${aws_db_instance.mysql.address}
    DB_PORT=3306
    DB_DATABASE=laravel
    REDIS_HOST=${aws_elasticache_cluster.redis.cache_nodes[0].address}
    AWS_BUCKET=${aws_s3_bucket.storage.bucket}
  EOT
  sensitive = true
}
```

### Data Sources

```hcl
# data.tf

# Ən son Ubuntu AMI
data "aws_ami" "ubuntu" {
  most_recent = true
  owners      = ["099720109477"]    # Canonical

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd/ubuntu-jammy-22.04-amd64-server-*"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
}

# Availability Zones
data "aws_availability_zones" "available" {
  state = "available"
}

# Mövcud VPC
data "aws_vpc" "existing" {
  id = "vpc-12345678"
}

# AWS Account ID
data "aws_caller_identity" "current" {}

# SSM Parameter (secrets)
data "aws_ssm_parameter" "db_password" {
  name = "/laravel/production/db_password"
}
```

### State

```bash
# State nədir?
# Terraform yaratdığı resursların vəziyyətini saxlayan JSON fayldır
# terraform.tfstate - local state faylı
# Real infrastruktur vəziyyətini izləyir

# State əmrləri
terraform state list                            # Bütün resursları siyahıla
terraform state show aws_instance.web[0]        # Resursu detallı göstər
terraform state mv aws_instance.old aws_instance.new  # Resursu yenidən adlandır
terraform state rm aws_instance.web[0]          # State-dən sil (resursu silmir)
terraform state pull                            # Remote state-i göstər

# QEYD: terraform.tfstate faylını git-ə ƏLAVƏ ETMƏYİN!
# Sensitive data olur (passwords, keys)
# Remote state istifadə edin (S3, Terraform Cloud)
```

## Praktiki Nümunələr (Practical Examples)

### Laravel İnfrastruktur Layihəsi

```
terraform/
├── environments/
│   ├── dev/
│   │   ├── main.tf
│   │   ├── terraform.tfvars
│   │   └── backend.tf
│   ├── staging/
│   └── production/
├── modules/
│   ├── vpc/
│   ├── ec2/
│   ├── rds/
│   └── s3/
├── providers.tf
├── variables.tf
└── outputs.tf
```

### User Data Script (EC2 Init)

```bash
#!/bin/bash
# scripts/init.sh - EC2 başlanğıc scripti

# PHP və lazımlı paketlər
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-redis php8.3-xml php8.3-curl php8.3-mbstring php8.3-zip composer

# Nginx konfiqurasiya
sudo tee /etc/nginx/sites-available/laravel.conf > /dev/null <<'NGINX'
server {
    listen 80;
    root /var/www/laravel/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
NGINX

sudo ln -sf /etc/nginx/sites-available/laravel.conf /etc/nginx/sites-enabled/default
sudo systemctl restart nginx
```

## PHP/Laravel ilə İstifadə

### Terraform Output-larını Laravel .env-ə yazmaq

```bash
#!/bin/bash
# generate-env.sh

cd terraform/environments/production

DB_HOST=$(terraform output -raw db_endpoint | cut -d: -f1)
REDIS_HOST=$(terraform output -raw redis_endpoint)
S3_BUCKET=$(terraform output -raw s3_bucket_name)

cat > /var/www/laravel/.env.production <<EOF
APP_ENV=production
DB_HOST=$DB_HOST
DB_PORT=3306
DB_DATABASE=laravel
REDIS_HOST=$REDIS_HOST
AWS_BUCKET=$S3_BUCKET
FILESYSTEM_DISK=s3
EOF
```

## Interview Sualları

### S1: Terraform state nədir və niyə vacibdir?
**C:** State faylı (terraform.tfstate) Terraform-ın yaratdığı resursların real vəziyyətini izləyən JSON fayldır. Hər resursun ID-si, atributları saxlanılır. State olmadan Terraform hansı resursları idarə etdiyini bilmir. `terraform plan` state-i real infrastrukturla müqayisə edərək fərqləri göstərir. State sensitive data ehtiva edə bilər, buna görə Git-ə əlavə edilməməli, remote state (S3+DynamoDB) istifadə olunmalıdır.

### S2: terraform plan və terraform apply fərqi nədir?
**C:** `terraform plan` dry-run-dır - nə yaradılacaq, nə dəyişəcək, nə silinəcəyini göstərir amma heç nə etmir. `terraform apply` planı icra edir, real infrastruktur dəyişiklikləri edir. Best practice: əvvəlcə plan çalışdır, nəticəni yoxla, sonra apply et. CI/CD-də: `terraform plan -out=tfplan` ilə planı faylda saxla, sonra `terraform apply tfplan` ilə həmin planı tətbiq et.

### S3: Terraform vs CloudFormation vs Ansible fərqləri nədir?
**C:** **Terraform**: multi-cloud (AWS, Azure, GCP), deklarativ, state-based, infrastruktur yaratmaq üçün. **CloudFormation**: yalnız AWS, deklarativ, AWS-ə native inteqrasiya. **Ansible**: prosedural/deklarativ, agentless, əsasən konfiqurasiya idarəsi (software quraşdırma, konfiqurasiya). Terraform infrastruktur yaradır (EC2, RDS), Ansible serveri konfiqurasiya edir (PHP, Nginx quraşdırma). Birlikdə istifadə olunurlar.

### S4: Variable-larda sensitive data necə idarə olunur?
**C:** 1) `sensitive = true` flag - plan/apply output-da göstərilmir, 2) terraform.tfvars Git-ə əlavə etmə (.gitignore), 3) `TF_VAR_name` environment variable-ları, 4) AWS Secrets Manager/SSM Parameter Store-dan data source ilə oxu, 5) HashiCorp Vault provider, 6) CI/CD pipeline secrets. Heç vaxt plain text password-ları kod fayllarında saxlamayın.

### S5: count və for_each arasında fərq nədir?
**C:** `count` rəqəm qəbul edir, index ilə müraciət olunur (`aws_instance.web[0]`). Ortadan element silinəndə sonrakılar renumber olur - destroy/recreate. `for_each` map/set qəbul edir, key ilə müraciət olunur (`aws_instance.web["web1"]`). Element silinəndə digərləri təsirlənmir. for_each daha təhlükəsiz və tövsiyə olunandır. count yalnız sadə hallarda (eyni resursdan N ədəd) istifadə olunmalıdır.

### S6: terraform init nə edir?
**C:** 1) Provider plugin-lərini yükləyir (.terraform/ qovluğuna), 2) Backend-i konfiqurasiya edir (S3, Terraform Cloud), 3) Modulları yükləyir (registry, git), 4) .terraform.lock.hcl faylı yaradır (provider versiya kilidi). Yeni proyektdə, provider/modul dəyişdikdə, backend dəyişdikdə init çalışdırılmalıdır. `-upgrade` flag provider-ləri yeniləyir.

## Best Practices

1. **Remote state** istifadə edin - S3 + DynamoDB lock
2. **State faylını Git-ə əlavə etməyin** - .gitignore-a əlavə edin
3. **terraform plan** həmişə apply-dan əvvəl çalışdırın
4. **Variable validation** edin - yanlış dəyərləri əvvəlcədən tutun
5. **Modul istifadə edin** - Təkrar kod yazmayın
6. **Tagging** strategiyası qurun - Environment, Team, Project
7. **Sensitive variables** düzgün idarə edin - Vault, SSM, env vars
8. **.terraform.lock.hcl** Git-ə əlavə edin - provider versiya sabitliyi
9. **CI/CD** ilə inteqrasiya edin - Manual apply etməyin
10. **Kiçik adımlarla** dəyişiklik edin - Böyük dəyişikliklər risklidir

---

## Praktik Tapşırıqlar

1. Laravel infrastructure üçün Terraform yazın: `provider "aws"`, VPC + subnet + security group + EC2 + RDS; `variables.tf`-də `environment`, `instance_type`, `db_password`; `terraform plan` çıxışını oxuyun, `terraform apply` edin, `terraform destroy` ilə silin
2. Remote state konfigurasiya edin: S3 bucket + DynamoDB table yaradın (manual), `backend "s3"` bloku əlavə edin, `terraform init -migrate-state`; başqa terminal-dan `terraform plan` işlədib state lock-u izləyin
3. Data source istifadə edin: `data "aws_ami" "ubuntu"` ilə ən son Ubuntu 24.04 AMI-ni dynamik tapın, `data "aws_availability_zones"` ilə mövcud zone-ları çəkin; hardcode-suz infrastructure yazın
4. `count` vs `for_each` fərqini praktiki göstərin: eyni konfiqurasiyanı hər iki üsulla yazın — 3 subnet yaradın; `count` istifadəsindəki index problem-ini (`count.index` vs resource silinməsi) müəyyən edin; `for_each` ilə daha güvənli variantını yazın
5. `terraform import` ilə mövcud resursu state-ə əlavə edin: AWS console-da manual S3 bucket yaradın, `terraform import aws_s3_bucket.manual <bucket-name>`, `terraform plan`-da heç bir dəyişiklik olmadığını yoxlayın; `moved` bloku ilə resursu rename edin
6. Terraform module yaradın: `modules/laravel-server` — EC2 + security group + Elastic IP — input variables (instance_type, app_name, vpc_id), output (public_ip, instance_id); root module-dan `module "web" { source = "./modules/laravel-server" }` ilə çağırın

## Əlaqəli Mövzular

- [Terraform Advanced](24-terraform-advanced.md) — modules, workspaces, remote state
- [AWS Əsasları](14-aws-basics.md) — AWS resursları, IAM
- [Ansible](25-ansible.md) — Terraform (provision) + Ansible (configure) kombinasiyası
- [Secrets Management](28-secrets-management.md) — Terraform-da sensitive variable
- [CI/CD Deployment](39-cicd-deployment.md) — Terraform CI/CD pipeline
