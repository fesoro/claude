# Infrastructure as Code (Senior ⭐⭐⭐)

## İcmal
Infrastructure as Code (IaC) — server, network, database kimi infrastruktur resurslarını manual GUI/CLI əvəzinə kod (deklarativ ya da imperative fayl) ilə idarə etmək yanaşmasıdır. Terraform, Pulumi, AWS CloudFormation, Ansible — IaC alətlərinin ən məşhurlarıdır. Senior developer kimi ən azından Terraform-ın əsaslarını bilmək gözlənilir.

## Niyə Vacibdir
"Siz serveri necə qurursunuz?" sualına "AWS Console-da manual" cavabı artıq yetərli deyil. Infrastructure-ın kodda olması: versioning (git history), repeatability (eyni konfig istənilən mühitdə), disaster recovery (infra sıfırdan rebuild etmək), peer review (infra dəyişiklikləri PR ilə keçir) kimi faydalar verir. DevOps boundary-si aradan qalxdıqca backend developer-lar IaC-ı bilməlidir.

## Əsas Anlayışlar

### IaC Yanaşmaları:

**Declarative (nə istəyirəm):**
- İstənilən final state müəyyənləşdirilir
- Tool özü mövcud state ilə müqayisə edib fərqi tətbiq edir
- Nümunə: Terraform, CloudFormation, Kubernetes YAML
- Üstünlük: Idempotent — dəfələrlə apply etmək eyni nəticə

**Imperative (necə ediləcəyini):**
- Addım-addım əmrlər yazılır
- Nümunə: Ansible (əsasən), Bash script
- Problem: İkinci dəfə run etdikdə fərqli nəticə verə bilər

---

### Terraform Əsasları:

Terraform HashiCorp Configuration Language (HCL) istifadə edir.

```hcl
# main.tf — AWS RDS + EC2 nümunəsi

terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  backend "s3" {
    bucket = "mycompany-terraform-state"
    key    = "production/app.tfstate"
    region = "us-east-1"
  }
}

provider "aws" {
  region = var.aws_region
}

# VPC
resource "aws_vpc" "main" {
  cidr_block = "10.0.0.0/16"

  tags = {
    Name        = "production-vpc"
    Environment = var.environment
  }
}

# RDS PostgreSQL
resource "aws_db_instance" "postgres" {
  identifier        = "app-postgres-${var.environment}"
  engine            = "postgres"
  engine_version    = "16.1"
  instance_class    = var.db_instance_class
  allocated_storage = 20
  storage_type      = "gp3"

  db_name  = var.db_name
  username = var.db_username
  password = var.db_password  # secrets manager ilə ideal

  vpc_security_group_ids = [aws_security_group.rds.id]
  db_subnet_group_name   = aws_db_subnet_group.main.name

  backup_retention_period = 7
  skip_final_snapshot     = false
  deletion_protection     = true

  tags = {
    Environment = var.environment
  }
}

# Output
output "rds_endpoint" {
  value     = aws_db_instance.postgres.endpoint
  sensitive = false
}
```

```hcl
# variables.tf
variable "aws_region" {
  type    = string
  default = "us-east-1"
}

variable "environment" {
  type        = string
  description = "staging ya da production"

  validation {
    condition     = contains(["staging", "production"], var.environment)
    error_message = "Environment must be staging or production."
  }
}

variable "db_instance_class" {
  type    = string
  default = "db.t3.micro"
}
```

---

### Terraform Workflow:

```bash
# 1. Initialize — provider plugin-ları yüklə
terraform init

# 2. Format — HCL formatlaması
terraform fmt

# 3. Validate — syntax yoxlaması
terraform validate

# 4. Plan — nə dəyişəcəyini gör (destructive əməliyyat yoxdur)
terraform plan -var-file=production.tfvars

# 5. Apply — dəyişiklikləri tətbiq et
terraform apply -var-file=production.tfvars

# 6. State inspection
terraform state list
terraform state show aws_db_instance.postgres

# 7. Destroy (diqqətli ol!)
terraform destroy -target=aws_instance.test
```

---

### Terraform State:

State faylı Terraform-ın "mövcud infrastructure nədir?" sualının cavabıdır. Bu fayl:

- `terraform.tfstate` (lokal ya da remote)
- Real cloud resurslar ilə kod arasındakı xəritə
- **Remote state** məcburidir team iş üçün — eyni anda iki nəfər apply edərsə conflict!

```hcl
# S3 remote backend + DynamoDB state locking
terraform {
  backend "s3" {
    bucket         = "company-tfstate"
    key            = "app/terraform.tfstate"
    region         = "us-east-1"
    encrypt        = true
    dynamodb_table = "terraform-state-lock"  # locking!
  }
}
```

---

### Terraform Modules:

Reusable konfiqurasiya blokları:

```hcl
# modules/rds/main.tf — reusable RDS module

module "app_database" {
  source = "./modules/rds"

  identifier    = "app-db"
  instance_type = "db.t3.small"
  environment   = "production"
}

module "analytics_database" {
  source = "./modules/rds"

  identifier    = "analytics-db"
  instance_type = "db.r6g.large"
  environment   = "production"
}
```

---

### Ansible — Konfigurasiya İdarəsi:

Terraform infra yaradır; Ansible server-ləri konfiqurasiya edir.

```yaml
# playbook.yml — Laravel deploy
---
- hosts: web_servers
  become: yes
  vars:
    php_version: "8.3"
    app_path: "/var/www/app"

  tasks:
    - name: Install PHP
      apt:
        name:
          - "php{{ php_version }}-fpm"
          - "php{{ php_version }}-pgsql"
          - "php{{ php_version }}-redis"
        state: present

    - name: Deploy Laravel app
      git:
        repo: "{{ app_repo }}"
        dest: "{{ app_path }}"
        version: "{{ app_version }}"

    - name: Run composer install
      composer:
        command: install
        working_dir: "{{ app_path }}"
        no_dev: yes

    - name: Run migrations
      command: php artisan migrate --force
      args:
        chdir: "{{ app_path }}"
```

---

### IaC Best Practices:

**1. State-i remote saxla:**
S3 + DynamoDB (AWS), GCS (GCP), Terraform Cloud

**2. Module-lər istifadə et:**
DRY prinsipi — eyni RDS konfiqini kopyalama

**3. Workspace ya da directory separasiyası:**
```
infra/
├── modules/
│   ├── rds/
│   ├── eks/
│   └── vpc/
├── environments/
│   ├── staging/
│   └── production/
```

**4. Variables + tfvars:**
Sensitive dəyişənlər `.tfvars` faylında, ya da environment variable ilə

**5. Plan review CI/CD-də:**
```yaml
# Pull request-də plan göstər
- run: terraform plan -no-color > plan.txt
- uses: actions/github-script@v6
  with:
    script: |
      const plan = require('fs').readFileSync('plan.txt', 'utf8')
      github.rest.issues.createComment({
        body: `\`\`\`hcl\n${plan}\n\`\`\``
      })
```

---

### Drift Detection:

Real infrastructure ilə IaC kodu arasındakı fərq:
- Kimsə manual dəyişiklik etdisə — drift yaranır
- `terraform plan` drift-i aşkarlayır
- Atlantis ya da Terraform Cloud avtomatik drift detection edir

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"IaC istifadə edirsinizmi?" sualına yalnız "Terraform bilirik" demə. Remote state, module-lər, CI/CD inteqrasiyası (plan → review → apply), environment separasiyası haqqında danış. "Infrastructure dəyişikliklərini kod kimi PR ilə review edirik" — bu professional approach-dır.

**Follow-up suallar:**
- "Terraform state file-ı nədir, niyə vacibdir?"
- "Drift detection nədir?"
- "Terraform destroy-dan necə qorunursunuz?"

**Ümumi səhvlər:**
- Local state fayl istifadə etmək (team üçün uyğun deyil)
- `terraform apply` birbaşa — plan review etmədən
- Secrets-i `.tf` faylına hardcode etmək
- Module-siz hər environment üçün ayrıca kod yazmaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Terraform bilirik" vs "Remote state, module-lər, CI/CD-də plan review, deletion_protection kimi qoruma tədbirləri — bunları birlikdə tətbiq edirik."

## Nümunələr

### Tipik Interview Sualı
"Staging və production mühitləri üçün infra-nı necə idarə edirsiniz?"

### Güclü Cavab
"Terraform istifadə edirik, remote state S3-də saxlanır, DynamoDB ilə locking. Qovluq strukturu: `environments/staging/` və `environments/production/` — hər biri ayrı tfvars faylına malikdir. Paylaşılan komponentlər (VPC, RDS module, EKS cluster module) `modules/` altında. CI/CD: PR açıldıqda `terraform plan` icra edilir, nəticə PR-a comment kimi əlavə edilir, review sonrası merge — `terraform apply` avtomatik. Production üçün əlavə manual approval. Kritik resurslar üçün `deletion_protection = true` və `prevent_destroy = true` lifecycle qoyulmuşdur."

## Praktik Tapşırıqlar
- Terraform install edib lokal AWS/GCP üçün sadə EC2/VM yarat
- Remote state S3-da konfiqurasiya et
- Bir RDS module yaz, iki mühitdə (staging, prod) istifadə et
- Terraform plan-ı GitHub Actions-da PR comment kimi göstər

## Əlaqəli Mövzular
- [01-cicd-pipeline-design.md](01-cicd-pipeline-design.md) — CI/CD-da IaC workflow
- [02-container-orchestration.md](02-container-orchestration.md) — Kubernetes manifest-ləri də IaC-dır
- [08-capacity-planning.md](08-capacity-planning.md) — Infra capacity IaC ilə plan edilir
- [10-gitops.md](10-gitops.md) — GitOps = IaC + CD inteqrasiyası
