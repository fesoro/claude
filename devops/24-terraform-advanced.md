# Terraform Advanced (Senior)

## Nədir? (What is it?)

Terraform-ın əsas konseptlərini öyrəndikdən sonra real production mühitlərdə istifadə üçün modullar, workspaces, remote state, import, lifecycle qaydaları və s. bilmək vacibdir. Bu mövzular böyük komandaların infrastrukturu təhlükəsiz və təkrar istifadə olunan şəkildə idarə etməsinə imkan verir.

## Əsas Konseptlər (Key Concepts)

### Modules (Modullar)

```hcl
# Modul = təkrar istifadə olunan Terraform kodu
# Öz input (variables) və output-ları var

# modules/vpc/main.tf
resource "aws_vpc" "this" {
  cidr_block           = var.cidr_block
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = merge(var.tags, {
    Name = "${var.name}-vpc"
  })
}

resource "aws_subnet" "public" {
  count                   = length(var.public_subnets)
  vpc_id                  = aws_vpc.this.id
  cidr_block              = var.public_subnets[count.index]
  availability_zone       = var.azs[count.index]
  map_public_ip_on_launch = true

  tags = merge(var.tags, {
    Name = "${var.name}-public-${count.index + 1}"
  })
}

resource "aws_internet_gateway" "this" {
  vpc_id = aws_vpc.this.id
  tags   = merge(var.tags, { Name = "${var.name}-igw" })
}

# modules/vpc/variables.tf
variable "name" { type = string }
variable "cidr_block" { type = string }
variable "public_subnets" { type = list(string) }
variable "azs" { type = list(string) }
variable "tags" { type = map(string); default = {} }

# modules/vpc/outputs.tf
output "vpc_id" { value = aws_vpc.this.id }
output "public_subnet_ids" { value = aws_subnet.public[*].id }
```

```hcl
# Modulu istifadə etmək
# environments/production/main.tf

module "vpc" {
  source = "../../modules/vpc"

  name           = "production"
  cidr_block     = "10.0.0.0/16"
  public_subnets = ["10.0.1.0/24", "10.0.2.0/24"]
  azs            = ["eu-central-1a", "eu-central-1b"]
  tags           = { Environment = "production" }
}

module "web_servers" {
  source = "../../modules/ec2"

  instance_count = 3
  instance_type  = "t3.large"
  subnet_ids     = module.vpc.public_subnet_ids    # Modul output-u istifadə
  vpc_id         = module.vpc.vpc_id
}

# Terraform Registry-dən modul
module "vpc" {
  source  = "terraform-aws-modules/vpc/aws"
  version = "5.0.0"

  name = "production-vpc"
  cidr = "10.0.0.0/16"

  azs             = ["eu-central-1a", "eu-central-1b"]
  private_subnets = ["10.0.1.0/24", "10.0.2.0/24"]
  public_subnets  = ["10.0.101.0/24", "10.0.102.0/24"]

  enable_nat_gateway = true
  single_nat_gateway = true
}

# Git repository-dən modul
module "app" {
  source = "git::https://github.com/company/terraform-modules.git//modules/laravel?ref=v1.2.0"
}
```

### Workspaces

```bash
# Workspace = eyni Terraform kodu ilə fərqli state-lər
# Dev, staging, production üçün eyni kodu istifadə etmək

terraform workspace list              # Mövcud workspace-lər
terraform workspace new staging       # Yeni workspace yarat
terraform workspace new production
terraform workspace select staging    # Workspace-ə keç
terraform workspace show              # Cari workspace

# terraform.workspace dəyişəni kodda istifadə olunur
```

```hcl
# Workspace-ə görə fərqli dəyərlər
locals {
  env_config = {
    default = {
      instance_type  = "t3.micro"
      instance_count = 1
      db_class       = "db.t3.micro"
    }
    staging = {
      instance_type  = "t3.small"
      instance_count = 2
      db_class       = "db.t3.small"
    }
    production = {
      instance_type  = "t3.large"
      instance_count = 3
      db_class       = "db.r5.large"
    }
  }

  config = local.env_config[terraform.workspace]
}

resource "aws_instance" "web" {
  count         = local.config.instance_count
  instance_type = local.config.instance_type
  ami           = data.aws_ami.ubuntu.id

  tags = {
    Name        = "laravel-${terraform.workspace}-web-${count.index + 1}"
    Environment = terraform.workspace
  }
}
```

### Remote State

```hcl
# backend.tf - S3 ilə remote state
terraform {
  backend "s3" {
    bucket         = "company-terraform-state"
    key            = "production/laravel/terraform.tfstate"
    region         = "eu-central-1"
    encrypt        = true
    dynamodb_table = "terraform-locks"    # State locking
  }
}
```

```bash
# DynamoDB table yaratmaq (state locking üçün)
aws dynamodb create-table \
  --table-name terraform-locks \
  --attribute-definitions AttributeName=LockID,AttributeType=S \
  --key-schema AttributeName=LockID,KeyType=HASH \
  --billing-mode PAY_PER_REQUEST
```

```hcl
# Başqa state-dən data oxumaq (remote state data source)
data "terraform_remote_state" "vpc" {
  backend = "s3"
  config = {
    bucket = "company-terraform-state"
    key    = "production/vpc/terraform.tfstate"
    region = "eu-central-1"
  }
}

# İstifadə
resource "aws_instance" "web" {
  subnet_id = data.terraform_remote_state.vpc.outputs.public_subnet_ids[0]
}
```

### State Locking

```
State locking nə edir?
- Eyni vaxtda iki nəfərin terraform apply etməsinin qarşısını alır
- DynamoDB ilə S3 backend, PostgreSQL ilə pg backend
- Lock əldə edilə bilmirsə: terraform apply gözləyir və ya error verir

terraform force-unlock LOCK_ID     # Lock-u manual silmək (dikkatli!)
```

### Import

```bash
# Mövcud resursu Terraform state-ə əlavə etmək
# (manual yaradılmış resursları Terraform-a keçirmək)

# 1) Terraform kodunu yaz
resource "aws_instance" "existing" {
  ami           = "ami-12345678"
  instance_type = "t3.medium"
}

# 2) Import et
terraform import aws_instance.existing i-0abcdef1234567890

# 3) terraform plan - fərqləri yoxla, kodu düzəlt
terraform plan

# Terraform 1.5+ import block
import {
  to = aws_instance.existing
  id = "i-0abcdef1234567890"
}

# Kod generasiyası (1.5+)
terraform plan -generate-config-out=generated.tf
```

### Lifecycle Rules

```hcl
resource "aws_instance" "web" {
  ami           = data.aws_ami.ubuntu.id
  instance_type = var.instance_type

  lifecycle {
    # Resurs silinib yenidən yaradılmasın
    prevent_destroy = true

    # Bu atributlar dəyişsə ignore et (manual dəyişiklik)
    ignore_changes = [
      ami,
      tags["LastModifiedBy"],
      user_data,
    ]

    # Əvvəlcə yenisini yarat, sonra köhnəni sil
    create_before_destroy = true

    # Custom condition
    precondition {
      condition     = var.instance_type != "t3.nano"
      error_message = "t3.nano production üçün çox kiçikdir"
    }

    postcondition {
      condition     = self.public_ip != ""
      error_message = "Instance public IP almalıdır"
    }
  }
}

# Replace triggered by
resource "aws_instance" "web" {
  # ...

  lifecycle {
    replace_triggered_by = [
      aws_ami.app.id,        # AMI dəyişəndə instance-ı yenidən yarat
    ]
  }
}
```

### Moved Block (Refactoring)

```hcl
# Resursu yenidən adlandırmaq (state-ə təsir etmədən)
moved {
  from = aws_instance.web
  to   = aws_instance.laravel_web
}

# Modula köçürmək
moved {
  from = aws_instance.web
  to   = module.web.aws_instance.this
}

# count-dan for_each-ə keçmək
moved {
  from = aws_instance.web[0]
  to   = aws_instance.web["web-1"]
}
```

### Terraform Functions

```hcl
locals {
  # String functions
  name_upper = upper("laravel")                     # "LARAVEL"
  name_lower = lower("LARAVEL")                     # "laravel"
  joined     = join(",", ["web1", "web2", "web3"])  # "web1,web2,web3"
  trimmed    = trimspace("  hello  ")               # "hello"
  formatted  = format("server-%02d", 5)             # "server-05"

  # Collection functions
  merged  = merge({ a = 1 }, { b = 2 })             # { a=1, b=2 }
  keys    = keys({ a = 1, b = 2 })                  # ["a", "b"]
  flat    = flatten([["a", "b"], ["c"]])             # ["a", "b", "c"]
  lookup  = lookup({ a = 1 }, "a", 0)               # 1
  element = element(["a", "b", "c"], 1)              # "b"
  compact = compact(["a", "", "b", null])            # ["a", "b"]

  # Type conversion
  str_num = tonumber("42")                           # 42
  num_str = tostring(42)                             # "42"
  to_set  = toset(["a", "b", "a"])                   # ["a", "b"]

  # Conditional
  size = var.environment == "production" ? "t3.large" : "t3.micro"

  # File
  script  = file("${path.module}/scripts/init.sh")
  templated = templatefile("${path.module}/templates/env.tpl", {
    db_host = aws_db_instance.mysql.address
    redis   = aws_elasticache_cluster.redis.cache_nodes[0].address
  })

  # CIDR
  subnet_cidrs = cidrsubnet("10.0.0.0/16", 8, 1)    # "10.0.1.0/24"

  # for expression
  server_names = [for i in range(3) : "web-${i + 1}"]  # ["web-1", "web-2", "web-3"]
  upper_names  = { for k, v in var.tags : k => upper(v) }
}
```

### Dynamic Blocks

```hcl
resource "aws_security_group" "web" {
  name   = "web-sg"
  vpc_id = module.vpc.vpc_id

  dynamic "ingress" {
    for_each = var.ingress_rules
    content {
      from_port   = ingress.value.port
      to_port     = ingress.value.port
      protocol    = "tcp"
      cidr_blocks = ingress.value.cidr_blocks
      description = ingress.value.description
    }
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

variable "ingress_rules" {
  default = [
    { port = 80,  cidr_blocks = ["0.0.0.0/0"],    description = "HTTP" },
    { port = 443, cidr_blocks = ["0.0.0.0/0"],    description = "HTTPS" },
    { port = 22,  cidr_blocks = ["10.0.0.0/16"],  description = "SSH" },
  ]
}
```

## Praktiki Nümunələr (Practical Examples)

### CI/CD Pipeline ilə Terraform

```yaml
# .github/workflows/terraform.yml
name: Terraform
on:
  push:
    branches: [main]
    paths: ['terraform/**']
  pull_request:
    paths: ['terraform/**']

jobs:
  plan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: hashicorp/setup-terraform@v3

      - name: Terraform Init
        run: terraform init
        working-directory: terraform/environments/production

      - name: Terraform Plan
        run: terraform plan -out=tfplan -no-color
        working-directory: terraform/environments/production
        env:
          AWS_ACCESS_KEY_ID: ${{ secrets.AWS_ACCESS_KEY_ID }}
          AWS_SECRET_ACCESS_KEY: ${{ secrets.AWS_SECRET_ACCESS_KEY }}

      - name: Comment Plan on PR
        if: github.event_name == 'pull_request'
        uses: actions/github-script@v7
        with:
          script: |
            const output = `#### Terraform Plan
            \`\`\`
            ${{ steps.plan.outputs.stdout }}
            \`\`\``;
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: output
            })

  apply:
    needs: plan
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: production
    steps:
      - uses: actions/checkout@v4
      - uses: hashicorp/setup-terraform@v3

      - name: Terraform Apply
        run: |
          terraform init
          terraform apply -auto-approve
        working-directory: terraform/environments/production
```

## PHP/Laravel ilə İstifadə

### Terraform ilə Laravel İnfrastruktur Modulu

```hcl
# modules/laravel/main.tf - Laravel üçün tam infrastruktur
module "vpc" {
  source         = "../vpc"
  name           = var.name
  cidr_block     = var.vpc_cidr
  public_subnets = var.public_subnets
  azs            = var.azs
}

resource "aws_instance" "web" {
  for_each      = var.web_servers
  ami           = data.aws_ami.ubuntu.id
  instance_type = each.value.instance_type
  subnet_id     = module.vpc.public_subnet_ids[0]

  tags = { Name = "${var.name}-${each.key}" }
}

resource "aws_db_instance" "mysql" {
  identifier     = "${var.name}-db"
  engine         = "mysql"
  engine_version = "8.0"
  instance_class = var.db_instance_class
  # ...
}

resource "aws_s3_bucket" "storage" {
  bucket = "${var.name}-storage-${var.environment}"
}

# Laravel .env template
resource "local_file" "env" {
  filename = "${path.module}/output/.env.${var.environment}"
  content = templatefile("${path.module}/templates/env.tpl", {
    app_env    = var.environment
    db_host    = aws_db_instance.mysql.address
    redis_host = aws_elasticache_cluster.redis.cache_nodes[0].address
    s3_bucket  = aws_s3_bucket.storage.bucket
  })
}
```

## Interview Sualları

### S1: Terraform modules niyə istifadə olunur?
**C:** 1) Kod təkrarını azaltmaq - eyni VPC, EC2 kodunu hər mühit üçün yazmamaq, 2) Abstraction - mürəkkəb infrastrukturu sadə interface arxasında gizlətmək, 3) Versioning - modulun versiyasını idarə etmək, 4) Testing - modulu ayrıca test etmək, 5) Team collaboration - komanda üzvləri eyni modulları istifadə edir. Registry-dən hazır modullar da istifadə olunur (terraform-aws-modules).

### S2: Remote state niyə vacibdir?
**C:** 1) Komanda üzvləri eyni state-ə çatır, 2) State locking - eyni vaxtda iki nəfərin apply etməsinin qarşısını alır (DynamoDB), 3) Encryption - state faylı şifrələnir (S3 server-side encryption), 4) Versioning - S3 versioning ilə state tarixçəsi, 5) Backup - avtomatik backup. Local state team iş üçün uyğun deyil və təhlükəsiz deyil.

### S3: terraform import nə vaxt istifadə olunur?
**C:** Manual yaradılmış (console/CLI ilə) resursları Terraform idarəsinə keçirmək üçün. Addımlar: 1) Terraform kodunu yaz, 2) `terraform import resource.name actual-id` çalışdır, 3) `terraform plan` ilə fərqləri yoxla, 4) Kodu uyğunlaşdır ki plan "no changes" göstərsin. Terraform 1.5+ import block ilə daha rahat: `import { to = ...; id = "..." }` və `terraform plan -generate-config-out` ilə kod generasiyası.

### S4: Lifecycle rules nə üçün istifadə olunur?
**C:** `prevent_destroy` - kritik resursun təsadüfən silinməsinin qarşısını alır (database). `create_before_destroy` - əvvəlcə yenisini yaradır, sonra köhnəni silir (zero-downtime). `ignore_changes` - Terraform-dan kənar dəyişiklikləri ignore edir (auto-scaling, manual tag dəyişikliyi). `replace_triggered_by` - başqa resurs dəyişəndə bu resursu yenidən yaratmaq.

### S5: Workspace vs ayrı directory ilə environment management fərqi?
**C:** **Workspaces**: eyni kod, fərqli state. Sadədir amma bütün mühitlər eyni backend-i paylaşır, bir provider konfiqurasiyası istifadə edir. **Ayrı directory**: hər mühit üçün ayrı qovluq (environments/prod/, environments/staging/). Daha çevik - fərqli provider, backend, dəyişikliklər mümkün. Production və development fərqli ola bilər. Böyük komandalar üçün ayrı directory + shared modules daha yaxşıdır.

### S6: State drift nədir və necə həll olunur?
**C:** State drift - Terraform state ilə real infrastruktur arasındakı fərqdir. Console-dan manual dəyişiklik ediləndə baş verir. `terraform plan` drift-i göstərir. `terraform apply` state-i real infrastruktura uyğunlaşdırır (və ya əksinə). `terraform refresh` yalnız state-i yeniləyir. Drift-in qarşısını almaq üçün: bütün dəyişiklikləri Terraform ilə edin, `ignore_changes` lazımi hallarda istifadə edin, CI/CD ilə müntəzəm plan çalışdırın.

## Best Practices

1. **Modulları versiyalayın** - Git tag ilə, `ref=v1.2.0`
2. **Remote state + locking** həmişə istifadə edin
3. **State-i kiçik saxlayın** - Böyük monolith state yavaşdır, parçalayın
4. **prevent_destroy** kritik resurslarda istifadə edin (DB, S3)
5. **Plan output-u** PR-da comment kimi əlavə edin
6. **.terraform.lock.hcl** Git-ə əlavə edin
7. **Sensitive output** markerlayın - `sensitive = true`
8. **Moved blocks** refactoring üçün istifadə edin - state manual manipulyasiya yox
9. **terraform fmt** ilə kodu formatlayin
10. **terraform validate** CI pipeline-da çalışdırın

---

## Praktik Tapşırıqlar

1. Reusable Terraform module yaradın: `modules/rds-cluster` — Multi-AZ RDS, parameter group, subnet group, security group — versioning (`source = "git::https://..."`, `?ref=v1.2.0`); semver tag-ları yaradın; başqa layihədən module-u versiya ilə çağırın
2. Workspace-lərlə multi-environment idarə edin: `terraform workspace new staging`, `terraform workspace new production`; `locals { env_config = { staging = {...}, production = {...} } }` ilə mühitə görə fərqli instance type seçin; `terraform workspace show` ilə cari workspace-i yoxlayın
3. `lifecycle` qaydaları test edin: `prevent_destroy = true` olan RDS instance-i `terraform destroy`-da nə baş verdiğini görün; `create_before_destroy = true` ilə security group replace-ini sıfır downtime ilə edin; `ignore_changes = [ami]` ilə AMI manual dəyişikliyinin Terraform tərəfindən override edilmədiyini test edin
4. Dynamic blocks yazın: EC2 security group-un inbound rules-larını list variable-dan `dynamic "ingress"` bloku ilə generasiya edin; `for_each = var.allowed_ports` ilə; plan output-da bütün rule-ların yarandığını yoxlayın
5. GitHub Actions-da Terraform CI/CD qurun: `terraform fmt -check`, `terraform validate`, `terraform plan -out=tfplan` (PR-da), `terraform apply tfplan` (merge sonrası); OIDC ilə AWS-ə auth edin (secret olmadan); plan output-unu PR comment-ə yazın
6. `terraform state` əmrləri ilə state surgery edin: `terraform state list` ilə resursları görün, `terraform state mv` ilə modulu rename edin, `terraform state rm` ilə resursu state-dən çıxarın (silmədən), `terraform state pull/push` ilə state-i export/import edin

## Əlaqəli Mövzular

- [Terraform Əsasları](23-terraform-basics.md) — IaC, providers, resources, state
- [AWS Əsasları](14-aws-basics.md) — AWS resource tipleri
- [GitOps](35-gitops.md) — Terraform GitOps workflow, Atlantis
- [Secrets Management](28-secrets-management.md) — Vault Terraform provider, sensitive outputs
- [CI/CD Deployment](39-cicd-deployment.md) — Terraform CI/CD pipeline
